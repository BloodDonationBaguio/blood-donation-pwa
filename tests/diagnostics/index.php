<?php
// Diagnostics index: quick links to all test pages
header('Content-Type: text/html; charset=utf-8');
function linkItem($path, $label) {
    $u = htmlspecialchars($path);
    $l = htmlspecialchars($label);
    echo "<li><a href='$u' target='_blank'>$l</a></li>\n";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Diagnostics Suite</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 32px; }
        h1 { margin-bottom: 8px; }
        .card { border: 1px solid #ddd; border-radius: 6px; padding: 16px; margin-bottom: 16px; }
        ul { line-height: 1.8; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Diagnostics Suite</h1>
    <p>Use the tools below to identify backend issues quickly.</p>

    <div class="card">
        <h2>Database</h2>
        <ul>
            <?php linkItem('db_connection_tester.php', 'DB Connection Tester'); ?>
            <?php linkItem('schema_validator.php', 'Schema/Column Validator'); ?>
        </ul>
    </div>

    <div class="card">
        <h2>Application</h2>
        <ul>
            <?php linkItem('form_input_validator.php', 'Form Input Validator Test'); ?>
            <?php linkItem('email_smtp_test.php', 'Email/SMTP Diagnostics'); ?>
            <?php linkItem('server_env_check.php', 'Server Environment Check'); ?>
        </ul>
    </div>

    <p>Tip: run from root at <code>/tests/diagnostics/</code>.</p>
</body>
</html>