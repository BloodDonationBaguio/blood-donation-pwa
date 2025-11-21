<?php
/**
 * Test Supabase Connection
 * Run this after setting up your .env file
 */

echo "🔍 TESTING SUPABASE CONNECTION (Direct Postgres)\n";
echo "===============================\n\n";

// Check if .env file exists
if (!file_exists('.env')) {
    echo "❌ .env file not found!\n";
    echo "Please create .env file with your Supabase credentials.\n\n";
echo "Example .env file:\n";
echo "SUPABASE_URL=https://your-project.supabase.co\n";
echo "SUPABASE_DB_HOST=db.your-project.supabase.co\n";
echo "SUPABASE_DB_PASSWORD=your-database-password\n";
echo "SUPABASE_DB_USER=postgres\n";
echo "SUPABASE_DB_NAME=postgres\n\n";
    exit(1);
}

// Load environment variables
$env_content = file_get_contents('.env');
$lines = explode("\n", $env_content);
foreach ($lines as $line) {
    if (strpos($line, '=') !== false && !empty(trim($line))) {
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
        putenv(trim($key) . '=' . trim($value));
    }
}

// Check credentials
echo "Checking credentials...\n";
$supabase_url = getenv('SUPABASE_URL');
$supabase_db_password = getenv('SUPABASE_DB_PASSWORD');

if (!$supabase_url) {
    echo "❌ SUPABASE_URL not found in .env file\n";
    exit(1);
}

if (!$supabase_db_password) {
    echo "❌ SUPABASE_DB_PASSWORD not found in .env file\n";
    exit(1);
}

echo "✓ Credentials found\n";
echo "✓ URL: " . substr($supabase_url, 0, 30) . "...\n";
echo "✓ DB Password set\n\n";

// Test connection
echo "Testing connection...\n";
try {
    putenv('SUPABASE_TEST_MODE=1');
    require_once 'supabase_db.php';
    echo "✅ SUCCESS! Connected to Supabase PostgreSQL\n\n";
    echo "Testing database query...\n";
    $stmt = $pdo->query("SELECT current_timestamp as server_time");
    $result = $stmt->fetch();
    echo "✅ Server time: " . $result['server_time'] . "\n\n";
    echo "Checking database tables...\n";
    $tables = ['admin_users', 'donors', 'blood_units', 'notifications'];
    foreach ($tables as $table) {
        if (tableExists($pdo, $table)) {
            echo "✅ Table '{$table}' exists\n";
        } else {
            echo "⚠️  Table '{$table}' not found (will be created)\n";
        }
    }
    echo "\n🎉 All tests passed! Your Supabase connection is working.\n";
} catch (Exception $e) {
    echo "❌ Direct Postgres connection failed: " . $e->getMessage() . "\n";
    echo "Trying REST API fallback...\n";
    $url = rtrim(getenv('SUPABASE_URL'), '/') . '/rest/v1/donors?select=id&limit=1';
    $anon = getenv('SUPABASE_ANON_KEY');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $anon,
        'Authorization: Bearer ' . $anon,
        'Accept: application/json'
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err) {
        echo "❌ REST request error: " . $err . "\n";
        exit(1);
    }
    if ($code >= 200 && $code < 300) {
        echo "✅ REST API reachable (port 443).\n";
        echo "Response: " . $resp . "\n";
        echo "This confirms DNS/HTTPS works. Your network likely blocks port 5432.\n";
        exit(0);
    }
    echo "❌ REST API HTTP " . $code . "\n";
    echo $resp . "\n";
    exit(1);
}

?>