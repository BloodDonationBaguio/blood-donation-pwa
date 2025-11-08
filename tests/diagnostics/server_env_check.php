<?php
// Server Environment Check: PHP version, extensions, key directories permissions
header('Content-Type: text/html; charset=utf-8');

function boolBadge($b) { return $b ? '<span style="color:#389e0d">enabled</span>' : '<span style="color:#cf1322;font-weight:bold">missing</span>'; }
function permInfo($path) {
    $exists = file_exists($path);
    $isDir = is_dir($path);
    $readable = is_readable($path);
    $writable = is_writable($path);
    return [$exists, $isDir, $readable, $writable];
}
function row($k, $v) { echo '<tr><th style="text-align:left;padding:6px 10px">' . htmlspecialchars($k) . '</th><td style="padding:6px 10px">' . $v . '</td></tr>'; }

$phpVersion = phpversion();
$extensions = ['pdo', 'pdo_mysql', 'pdo_pgsql', 'pdo_sqlite', 'openssl', 'curl', 'mbstring', 'json'];
$ini = [
    'memory_limit' => ini_get('memory_limit'),
    'post_max_size' => ini_get('post_max_size'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'max_execution_time' => ini_get('max_execution_time'),
    'date.timezone' => ini_get('date.timezone'),
    'error_log' => ini_get('error_log'),
    'display_errors' => ini_get('display_errors'),
];

$dirs = [
    __DIR__ . '/../../logs',
    __DIR__ . '/../../blood-donation-pwa/logs',
    __DIR__ . '/../../uploads',
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Server Environment Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 32px; }
        table { border-collapse: collapse; border: 1px solid #ddd; }
        th { background: #fafafa; }
    </style>
</head>
<body>
    <h1>Server Environment Check</h1>
    <h2>PHP</h2>
    <table>
        <?php row('PHP Version', htmlspecialchars($phpVersion)); ?>
        <?php row('SAPI', htmlspecialchars(PHP_SAPI)); ?>
    </table>

    <h2>Extensions</h2>
    <table>
        <?php foreach ($extensions as $ext) { row($ext, boolBadge(extension_loaded($ext))); } ?>
    </table>

    <h2>ini Settings</h2>
    <table>
        <?php foreach ($ini as $k => $v) { row($k, htmlspecialchars((string)$v)); } ?>
    </table>

    <h2>Directories</h2>
    <table>
        <tr><th>Path</th><th>Exists</th><th>Is Dir</th><th>Readable</th><th>Writable</th></tr>
        <?php foreach ($dirs as $d) { list($e,$i,$r,$w) = permInfo($d); echo '<tr><td style="padding:6px 10px">' . htmlspecialchars($d) . '</td><td>' . boolBadge($e) . '</td><td>' . boolBadge($i) . '</td><td>' . boolBadge($r) . '</td><td>' . boolBadge($w) . '</td></tr>'; } ?>
    </table>

    <h2>Environment Variables</h2>
    <table>
        <?php foreach (['DB_TYPE','DATABASE_URL','APP_ENV','MAIL_HOST','MAIL_PORT','MAIL_USERNAME','MAIL_PASSWORD','MAIL_FROM','MAIL_FROM_NAME','SENDGRID_API_KEY'] as $key) { $val = getenv($key); row($key, htmlspecialchars($val ? ($key==='MAIL_PASSWORD'||$key==='SENDGRID_API_KEY' ? '[set]' : $val) : '[empty]')); } ?>
    </table>

    <p><a href="index.php">Back to Diagnostics</a></p>
</body>
</html>