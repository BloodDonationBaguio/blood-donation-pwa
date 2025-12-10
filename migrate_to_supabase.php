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
    // Connect to Supabase PostgreSQL (target)
    $supabase_url = getenv('SUPABASE_URL') ?: getenv('NEXT_PUBLIC_SUPABASE_URL');
    $supabase_service_key = getenv('SUPABASE_SERVICE_ROLE_KEY');
    
    if (!$supabase_url || !$supabase_service_key) {
        die("❌ Supabase credentials not found. Please set SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY.\n");
    }
    
    $project_id = str_replace(['https://', '.supabase.co'], '', $supabase_url);
    $supabase_host = $project_id . '.supabase.co';
    
    $supabase_pdo = new PDO(
        "pgsql:host={$supabase_host};port=5432;dbname=postgres",
        'postgres',
        $supabase_service_key,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    $supabase_connected = true;
    echo "✓ Connected to Supabase PostgreSQL\n";
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
$tables_to_migrate = [
    'admin_users',
    'donors', 
    'blood_units',
    'notifications'
];

// Check if tables exist and migrate
foreach ($tables_to_migrate as $table) {
    try {
        // Check if table exists in Render
        $check_stmt = $render_pdo->query("SELECT to_regclass('public.{$table}')");
        $exists = $check_stmt->fetchColumn() !== null;
        
        if ($exists) {
            migrateTable($render_pdo, $supabase_pdo, $table);
        } else {
            echo "Table {$table} does not exist in Render, skipping...\n\n";
        }
    } catch (PDOException $e) {
        echo "Error checking table {$table}: " . $e->getMessage() . "\n\n";
    }
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