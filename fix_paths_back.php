<?php
// Fix path references back to original structure
echo "Fixing path references back to original structure...\n\n";

// Fix admin.php
if (file_exists('admin.php')) {
    $content = file_get_contents('admin.php');
    $content = str_replace("require_once __DIR__ . '/../config/db.php';", "require_once 'db.php';", $content);
    $content = str_replace("require_once __DIR__ . '/../src/includes/", "require_once 'includes/", $content);
    $content = str_replace("require_once(__DIR__ . \"/../src/includes/", "require_once('includes/", $content);
    file_put_contents('admin.php', $content);
    echo "Fixed admin.php\n";
}

// Fix admin-login.php
if (file_exists('admin-login.php')) {
    $content = file_get_contents('admin-login.php');
    $content = str_replace("require_once __DIR__ . '/../config/db.php';", "require_once 'db.php';", $content);
    $content = str_replace("require_once __DIR__ . '/../src/includes/", "require_once 'includes/", $content);
    file_put_contents('admin-login.php', $content);
    echo "Fixed admin-login.php\n";
}

// Fix all other PHP files
$phpFiles = glob('*.php');
foreach ($phpFiles as $file) {
    if ($file !== 'fix_paths_back.php') {
        $content = file_get_contents($file);
        $content = str_replace("require_once __DIR__ . '/../config/db.php';", "require_once 'db.php';", $content);
        $content = str_replace("require_once __DIR__ . '/../src/includes/", "require_once 'includes/", $content);
        $content = str_replace("require_once('includes/", "require_once('includes/", $content);
        $content = str_replace("include 'includes/", "include 'includes/", $content);
        $content = str_replace("include('includes/", "include('includes/", $content);
        $content = str_replace("href=\"../assets/css/", "href=\"css/", $content);
        $content = str_replace("src=\"../assets/css/", "src=\"css/", $content);
        file_put_contents($file, $content);
        echo "Fixed $file\n";
    }
}

// Fix includes files
$includeFiles = glob('includes/*.php');
foreach ($includeFiles as $file) {
    $content = file_get_contents($file);
    $content = str_replace("require_once __DIR__ . '/../../config/db.php';", "require_once 'db.php';", $content);
    $content = str_replace("require_once __DIR__ . '/../db.php';", "require_once 'db.php';", $content);
    file_put_contents($file, $content);
    echo "Fixed $file\n";
}

echo "\nPath references fixed successfully!\n";
?>
