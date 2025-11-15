<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');
$tabFile = dirname(__DIR__) . '/includes/admin/tabs/add-donor.php';
if (!file_exists($tabFile)) { echo "add-donor.php missing\n"; exit; }
$src = file_get_contents($tabFile);
function has($s,$p){ return preg_match($p,$s)?'FOUND':'NOT FOUND'; }
echo "=== add-donor.php renderer markers ===\n\n";
echo "embeds donor-registration: " . has($src,'/include\s+\$formPath/i') . "\n";
echo "removes eligibility: " . has($src,'/id=\"eligibilityCheck\"/i') . "\n";
echo "removes recaptcha: " . has($src,'/g-recaptcha/i') . "\n";
echo "forces visible: " . has($src,'/display\\s*:\\s*none/i') . "\n";
echo "points to process_manual_donor: " . has($src,'/process_manual_donor\.php/i') . "\n";
echo "\n--- first 800 chars of file ---\n";
echo substr($src,0,800);
echo "\n--- end ---\n";