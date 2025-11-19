<?php
/**
 * Migration Script: Render PostgreSQL to Supabase
 * This script migrates data from your Render database to Supabase
 */

// Set execution time limit for large datasets
set_time_limit(300);

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include both database connections
$render_connected = false;
$supabase_connected = false;

try {
    // Connect to Render PostgreSQL (source)
    if (getenv('DATABASE_URL')) {
        $database_url = getenv('DATABASE_URL');
        $db = parse_url($database_url);
        $render_host = $db['host'];
        $render_db = ltrim($db['path'], '/');
        $render_user = $db['user'];
        $render_pass = $db['pass'];
        $render_port = isset($db['port']) ? $db['port'] : 5432;
        
        $render_pdo = new PDO(
            "pgsql:host={$render_host};port={$render_port};dbname={$render_db}",
            $render_user,
            $render_pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        $render_connected = true;
        echo "✓ Connected to Render PostgreSQL\n";
    } else {
        die("❌ DATABASE_URL not found. Please set Render database connection.\n");
    }
} catch (PDOException $e) {
    die("❌ Failed to connect to Render: " . $e->getMessage() . "\n");
}

try {
    // Connect to Supabase PostgreSQL (target) using DB password and SSL
    $supabase_url = getenv('SUPABASE_URL') ?: getenv('NEXT_PUBLIC_SUPABASE_URL');
    $supabase_db_host = getenv('SUPABASE_DB_HOST');
    $supabase_db_name = getenv('SUPABASE_DB_NAME') ?: 'postgres';
    $supabase_db_user = getenv('SUPABASE_DB_USER') ?: 'postgres';
    $supabase_db_password = getenv('SUPABASE_DB_PASSWORD');
    $supabase_db_port = getenv('SUPABASE_DB_PORT') ?: '5432';

    if (!$supabase_url || !$supabase_db_password) {
        die("❌ Supabase credentials not found. Please set SUPABASE_URL and SUPABASE_DB_PASSWORD.\n");
    }

    $project_id = str_replace(['https://', '.supabase.co'], '', $supabase_url);
    $supabase_host = $supabase_db_host ?: ('db.' . $project_id . '.supabase.co');

    $supabase_dsn = "pgsql:host={$supabase_host};port={$supabase_db_port};dbname={$supabase_db_name};sslmode=require";
    $supabase_pdo = new PDO(
        $supabase_dsn,
        $supabase_db_user,
        $supabase_db_password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $supabase_connected = true;
    echo "✓ Connected to Supabase PostgreSQL (SSL)\n";
} catch (PDOException $e) {
    die("❌ Failed to connect to Supabase: " . $e->getMessage() . "\n");
}

echo "\n=== Starting Migration ===\n\n";

// Migration functions
function migrateTable($render_pdo, $supabase_pdo, $table_name, $batch_size = 100) {
    echo "Migrating {$table_name}...\n";
    
    try {
        // Get total count
        $count_stmt = $render_pdo->query("SELECT COUNT(*) as count FROM {$table_name}");
        $total_count = $count_stmt->fetch()['count'];
        echo "  Found {$total_count} records\n";
        
        if ($total_count == 0) {
            echo "  No records to migrate\n\n";
            return;
        }
        
        // Get table structure
        $structure_stmt = $render_pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = '{$table_name}' ORDER BY ordinal_position");
        $columns = $structure_stmt->fetchAll();
        $column_names = array_column($columns, 'column_name');
        $column_list = implode(', ', $column_names);
        
        // Migrate in batches
        $offset = 0;
        $migrated = 0;
        
        while ($offset < $total_count) {
            // Get batch from Render
            $batch_stmt = $render_pdo->query("SELECT {$column_list} FROM {$table_name} LIMIT {$batch_size} OFFSET {$offset}");
            $records = $batch_stmt->fetchAll();
            
            // Prepare insert statement for Supabase
            $placeholders = ':' . implode(', :', $column_names);
            $insert_sql = "INSERT INTO {$table_name} ({$column_list}) VALUES ({$placeholders})";
            $insert_stmt = $supabase_pdo->prepare($insert_sql);
            
            // Insert each record
            foreach ($records as $record) {
                try {
                    $insert_stmt->execute($record);
                    $migrated++;
                } catch (PDOException $e) {
                    // Handle duplicate key errors (if data already exists)
                    if (strpos($e->getMessage(), 'duplicate key') !== false) {
                        echo "  Skipping duplicate record\n";
                    } else {
                        echo "  Error migrating record: " . $e->getMessage() . "\n";
                    }
                }
            }
            
            $offset += $batch_size;
            echo "  Progress: {$migrated}/{$total_count}\n";
        }
        
        echo "  ✓ Migrated {$migrated} records to {$table_name}\n\n";
        
    } catch (PDOException $e) {
        echo "  ❌ Error migrating {$table_name}: " . $e->getMessage() . "\n\n";
    }
}

// List of tables to migrate (in order of dependencies)
// Discover all public tables on Render and migrate them
try {
    $tblStmt = $render_pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE'");
    $tables = $tblStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Prioritize key tables first
    $priority = ['admin_users','donors_new','donors','blood_units','blood_inventory','notifications','blood_requests','users','users_new'];
    $tables = array_unique(array_merge($priority, $tables));

    foreach ($tables as $table) {
        // Skip system tables if any slipped through
        if (strpos($table, 'pg_') === 0 || strpos($table, 'sql_') === 0) { continue; }

        // Ensure table exists on Supabase; create if missing
        $existsSup = $supabase_pdo->query("SELECT to_regclass('public." . str_replace("'","''", $table) . "')")->fetchColumn() !== null;
        if (!$existsSup) {
            echo "Creating table {$table} on Supabase...\n";
            // Build CREATE TABLE from source columns
            $cols = $render_pdo->query("SELECT column_name, data_type, is_nullable, character_maximum_length, numeric_precision, numeric_scale, column_default FROM information_schema.columns WHERE table_schema='public' AND table_name='" . str_replace("'","''", $table) . "' ORDER BY ordinal_position")->fetchAll(PDO::FETCH_ASSOC);
            $colDefs = [];
            foreach ($cols as $col) {
                $name = $col['column_name'];
                $type = $col['data_type'];
                // Map common types as-is; add length/precision where defined
                if (in_array($type, ['character varying','varchar','text'])) {
                    if (!empty($col['character_maximum_length'])) {
                        $typeDef = "varchar(" . (int)$col['character_maximum_length'] . ")";
                    } else {
                        $typeDef = ($type === 'text') ? 'text' : 'varchar';
                    }
                } elseif (in_array($type, ['integer','bigint','smallint'])) {
                    $typeDef = $type;
                } elseif (in_array($type, ['numeric','decimal'])) {
                    if (!empty($col['numeric_precision'])) {
                        $typeDef = "numeric(" . (int)$col['numeric_precision'] . (isset($col['numeric_scale']) ? "," . (int)$col['numeric_scale'] : "") . ")";
                    } else { $typeDef = 'numeric'; }
                } elseif (in_array($type, ['timestamp without time zone','timestamp with time zone'])) {
                    $typeDef = 'timestamp';
                } elseif ($type === 'date') { $typeDef = 'date'; }
                elseif ($type === 'boolean') { $typeDef = 'boolean'; }
                else { $typeDef = $type; }

                $nullable = ($col['is_nullable'] === 'YES') ? '' : ' NOT NULL';
                // Avoid copying defaults blindly if they reference sequences; sequences handled later
                $default = '';
                if (!empty($col['column_default']) && stripos($col['column_default'], 'nextval(') === false) {
                    $default = " DEFAULT " . $col['column_default'];
                }
                $colDefs[] = '"' . $name . '" ' . $typeDef . $default . $nullable;
            }
            $createSql = 'CREATE TABLE "' . $table . '" (' . implode(', ', $colDefs) . ')';
            try {
                $supabase_pdo->exec($createSql);
                echo "  ✓ Created {$table}\n";
            } catch (PDOException $e) {
                echo "  ⚠️ Could not create {$table}: " . $e->getMessage() . "\n";
            }
        }

        // Migrate data for this table
        migrateTable($render_pdo, $supabase_pdo, $table);

        // Reset sequences for serial columns if present
        try {
            $seqStmt = $render_pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema='public' AND table_name='" . str_replace("'","''", $table) . "' AND column_default LIKE 'nextval(%'");
            $serialCols = $seqStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($serialCols as $colName) {
                $supabase_pdo->exec("SELECT setval(pg_get_serial_sequence(''public." . str_replace("'","''", $table) . "'', '" . str_replace("'","''", $colName) . "'), COALESCE(MAX(" . $colName . "), 1)) FROM \"" . $table . "\";");
            }
        } catch (Exception $e) {
            // ignore
        }
    }
} catch (PDOException $e) {
    die("❌ Failed to enumerate tables: " . $e->getMessage() . "\n");
}

echo "=== Migration Complete ===\n";
echo "\nNext steps:\n";
echo "1. Update your application to use supabase_db.php instead of db_production.php\n";
echo "2. Set up environment variables for Supabase:\n";
echo "   - SUPABASE_URL=your-supabase-url\n";
echo "   - SUPABASE_SERVICE_ROLE_KEY=your-service-key\n";
echo "3. Test your application with the new Supabase database\n";
echo "4. Update any hardcoded database references in your code\n";

?>