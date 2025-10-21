<?php
// Script to update donor registration links from donor.html to donor-registration.php

$files = [
    'index.php',
    'navbar.php',
    'about.php',
    'request.html',
    'findus.html',
    'manifest.json',
    'sw.js'
];

$updated = 0;

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $newContent = str_replace('donor.html', 'donor-registration.php', $content);
        
        if ($content !== $newContent) {
            file_put_contents($file, $newContent);
            $updated++;
            echo "Updated: $file\n";
        }
    } else {
        echo "File not found: $file\n";
    }
}

echo "\nUpdate complete. $updated files were updated.\n";

// Update the donor.html file to redirect to the new page
$redirectContent = <<<EOT
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="refresh" content="0; url=donor-registration.php">
    <title>Redirecting...</title>
</head>
<body>
    <p>If you are not redirected, <a href="donor-registration.php">click here</a>.</p>
</body>
</html>
EOT;

file_put_contents('donor.html', $redirectContent);
echo "Created redirect from donor.html to donor-registration.php\n";

echo "\nAll done! The donor registration system has been updated.\n";
?>
