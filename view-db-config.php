<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to safely display file contents
function displayFileContents($path) {
    if (!file_exists($path)) {
        return "<p style='color:red;'>File does not exist: " . htmlspecialchars($path) . "</p>";
    }
    
    if (!is_readable($path)) {
        return "<p style='color:red;'>File exists but is not readable: " . htmlspecialchars($path) . "</p>";
    }
    
    $content = file_get_contents($path);
    if ($content === false) {
        return "<p style='color:red;'>Could not read file: " . htmlspecialchars($path) . "</p>";
    }
    
    return "<pre>" . htmlspecialchars($content) . "</pre>";
}

// Path to the database configuration file
$dbFile = __DIR__ . '/includes/db.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Configuration Viewer</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
        .file-info { background: #f5f5f5; padding: 10px; margin-bottom: 20px; }
        pre { background: #f0f0f0; padding: 10px; border: 1px solid #ccc; overflow-x: auto; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Database Configuration Viewer</h1>
    
    <div class="file-info">
        <h2>File Information</h2>
        <p>Path: <?= htmlspecialchars($dbFile) ?></p>
        <p>Exists: <?= file_exists($dbFile) ? '<span class="success">Yes</span>' : '<span class="error">No</span>' ?></p>
        <p>Readable: <?= is_readable($dbFile) ? '<span class="success">Yes</span>' : '<span class="error">No</span>' ?></p>
        <p>Size: <?= file_exists($dbFile) ? filesize($dbFile) . ' bytes' : 'N/A' ?></p>
        <p>Last Modified: <?= file_exists($dbFile) ? date('Y-m-d H:i:s', filemtime($dbFile)) : 'N/A' ?></p>
    </div>
    
    <h2>File Contents</h2>
    <?= displayFileContents($dbFile) ?>
    
    <h2>Included Files</h2>
    <pre><?= htmlspecialchars(print_r(get_included_files(), true)) ?></pre>
    
    <h2>Next Steps</h2>
    <ul>
        <li>Check if the database server is running</li>
        <li>Verify the database credentials in the configuration file</li>
        <li>Check the Apache error logs for more information</li>
    </ul>
</body>
</html>
