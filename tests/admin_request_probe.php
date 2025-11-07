<?php
// Probe admin.php without session to detect redirects/blank responses
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/utils.php';
t_section('Admin Request Probe (no session)');

$url = 'http://127.0.0.1:8000/admin.php?tab=pending-donors&v=' . urlencode((string)microtime(true));

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'ignore_errors' => true,
        'max_redirects' => 0,
        'header' => "Accept: text/html\r\n"
    ]
]);

$html = @file_get_contents($url, false, $context);
$code = 0; $location = '';
if (isset($http_response_header)) {
    foreach ($http_response_header as $h) {
        if (preg_match('/^HTTP\/[0-9.]+\s+(\d+)/', $h, $m)) { $code = (int)$m[1]; }
        if (stripos($h, 'Location:') === 0) { $location = trim(substr($h, 9)); }
    }
}

t_pass("HTTP status=$code");
if ($location) { t_pass("Location header=$location"); } else { t_pass('No Location header'); }

$len = is_string($html) ? strlen($html) : 0;
t_pass("Body length=$len");

echo $t_output;
if ($len) { echo '<hr><pre>' . htmlspecialchars(substr($html, 0, 1000)) . '</pre>'; }
?>