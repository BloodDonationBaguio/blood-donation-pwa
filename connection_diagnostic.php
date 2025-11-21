<?php
/**
 * Comprehensive Connection Diagnostic Tool
 * Helps identify and resolve connection timeout issues
 */

// Set execution time limit
set_time_limit(60);
ini_set('memory_limit', '128M');

// Headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'server_info' => [],
    'database_tests' => [],
    'network_tests' => [],
    'recommendations' => []
];

// 1. Server Information
$results['server_info'] = [
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'timezone' => date_default_timezone_get(),
    'extensions' => [
        'pdo' => extension_loaded('pdo'),
        'pdo_pgsql' => extension_loaded('pdo_pgsql'),
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'curl' => extension_loaded('curl'),
        'openssl' => extension_loaded('openssl')
    ]
];

// 2. Environment Variables Check
$env_vars = [
    'SUPABASE_URL' => getenv('SUPABASE_URL'),
    'SUPABASE_DB_HOST' => getenv('SUPABASE_DB_HOST'),
    'SUPABASE_DB_PORT' => getenv('SUPABASE_DB_PORT'),
    'SUPABASE_DB_USER' => getenv('SUPABASE_DB_USER'),
    'SUPABASE_DB_PASSWORD' => getenv('SUPABASE_DB_PASSWORD') ? '[SET]' : '[NOT SET]'
];

$results['environment'] = $env_vars;

// 3. Database Connection Tests
function testDatabaseConnection($host, $port, $dbname, $user, $password) {
    $start_time = microtime(true);
    $result = [
        'host' => $host,
        'port' => $port,
        'status' => 'failed',
        'response_time' => 0,
        'error' => null
    ];
    
    try {
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10, // 10 second timeout
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        // Test a simple query
        $stmt = $pdo->query("SELECT version()");
        $version = $stmt->fetchColumn();
        
        $result['status'] = 'success';
        $result['response_time'] = round((microtime(true) - $start_time) * 1000, 2);
        $result['database_version'] = $version;
        
    } catch (PDOException $e) {
        $result['error'] = $e->getMessage();
        $result['response_time'] = round((microtime(true) - $start_time) * 1000, 2);
    }
    
    return $result;
}

// Test Supabase connection
if ($env_vars['SUPABASE_DB_HOST'] && $env_vars['SUPABASE_DB_PASSWORD'] !== '[NOT SET]') {
    $results['database_tests']['supabase'] = testDatabaseConnection(
        $env_vars['SUPABASE_DB_HOST'],
        $env_vars['SUPABASE_DB_PORT'] ?: 5432,
        'postgres',
        $env_vars['SUPABASE_DB_USER'] ?: 'postgres',
        getenv('SUPABASE_DB_PASSWORD')
    );
}

// 4. Network Connectivity Tests
function testNetworkConnectivity($host, $port, $timeout = 5) {
    $start_time = microtime(true);
    $result = [
        'host' => $host,
        'port' => $port,
        'status' => 'failed',
        'response_time' => 0,
        'error' => null
    ];
    
    $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
    
    if ($connection) {
        $result['status'] = 'success';
        $result['response_time'] = round((microtime(true) - $start_time) * 1000, 2);
        fclose($connection);
    } else {
        $result['error'] = "Connection failed: $errstr ($errno)";
        $result['response_time'] = round((microtime(true) - $start_time) * 1000, 2);
    }
    
    return $result;
}

// Test network connectivity to Supabase
if ($env_vars['SUPABASE_DB_HOST']) {
    $results['network_tests']['supabase_db'] = testNetworkConnectivity(
        $env_vars['SUPABASE_DB_HOST'],
        $env_vars['SUPABASE_DB_PORT'] ?: 5432
    );
}

// Test HTTP connectivity to Supabase API
if ($env_vars['SUPABASE_URL']) {
    $start_time = microtime(true);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $env_vars['SUPABASE_URL'] . '/rest/v1/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $results['network_tests']['supabase_api'] = [
        'url' => $env_vars['SUPABASE_URL'],
        'status' => $error ? 'failed' : 'success',
        'http_code' => $http_code,
        'response_time' => round((microtime(true) - $start_time) * 1000, 2),
        'error' => $error ?: null
    ];
}

// 5. Generate Recommendations
$recommendations = [];

// Check for missing extensions
if (!$results['server_info']['extensions']['pdo_pgsql']) {
    $recommendations[] = "Install php-pgsql extension for PostgreSQL support";
}

if (!$results['server_info']['extensions']['openssl']) {
    $recommendations[] = "Install php-openssl extension for SSL connections";
}

// Check environment configuration
if ($env_vars['SUPABASE_DB_PASSWORD'] === '[NOT SET]') {
    $recommendations[] = "Set SUPABASE_DB_PASSWORD environment variable";
}

// Check connection performance
foreach ($results['database_tests'] as $test) {
    if ($test['status'] === 'success' && $test['response_time'] > 5000) {
        $recommendations[] = "Database connection is slow ({$test['response_time']}ms). Consider optimizing network or database configuration.";
    }
    if ($test['status'] === 'failed') {
        $recommendations[] = "Database connection failed: {$test['error']}";
    }
}

// Check network connectivity
foreach ($results['network_tests'] as $test) {
    if ($test['status'] === 'failed') {
        $recommendations[] = "Network connectivity issue: {$test['error']}";
    }
}

// Performance recommendations
if (ini_get('max_execution_time') < 30) {
    $recommendations[] = "Consider increasing max_execution_time for database operations";
}

$results['recommendations'] = $recommendations;

// 6. Health Status Summary
$overall_status = 'healthy';
if (!empty($recommendations)) {
    $overall_status = 'issues_detected';
}

foreach ($results['database_tests'] as $test) {
    if ($test['status'] === 'failed') {
        $overall_status = 'critical';
        break;
    }
}

$results['overall_status'] = $overall_status;

// Output results
echo json_encode($results, JSON_PRETTY_PRINT);
?>
