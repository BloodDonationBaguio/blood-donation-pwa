<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$formPath = dirname(__DIR__) . '/donor-registration.php';
header('Content-Type: text/plain');
if (!file_exists($formPath)) { echo "donor-registration.php missing\n"; exit; }
ob_start(); include $formPath; $rendered = ob_get_clean();
$formOnly = '';
if (preg_match('/<form[^>]*id="donorForm"[^>]*>[\s\S]*?<\/form>/i', $rendered, $m)) { $formOnly = $m[0]; } else { $formOnly = $rendered; }
$origLen = strlen($formOnly);
$formOnly = preg_replace('/<div[^>]*id="eligibilityCheck"[\s\S]*?<\/div>/i', '', $formOnly);
$formOnly = preg_replace('/<div[^>]*class="form-section"[^>]*>[\s\S]*?g-recaptcha[\s\S]*?<\/div>/i', '', $formOnly);
$formOnly = preg_replace('/action="[^"]*"/i', 'action="process_manual_donor.php"', $formOnly);
$formOnly = preg_replace('/(<form[^>]*?)\sstyle="[^"]*"/i', '$1', $formOnly);
$formOnly = preg_replace('/style="[^"]*display\s*:\s*none[^"]*"/i', '', $formOnly);
$formOnly = preg_replace('/\shidden(=\"hidden\")?/i', '', $formOnly);
echo "=== Extract Donor Form Transform ===\n\n";
echo "original length: $origLen\n";
echo "transformed length: " . strlen($formOnly) . "\n";
echo "has medical heading: " . (preg_match('/Medical Screening/i', $formOnly) ? 'YES' : 'NO') . "\n";
echo "has Question 1: " . (preg_match('/Question\s+1/i', $formOnly) ? 'YES' : 'NO') . "\n";
echo "has recaptcha: " . (preg_match('/g-recaptcha/i', $rendered) ? 'YES' : 'NO') . "\n";
echo "has eligibility: " . (preg_match('/id="eligibilityCheck"/i', $rendered) ? 'YES' : 'NO') . "\n\n";
echo "--- first 1000 chars of transformed form ---\n";
echo substr($formOnly, 0, 1000);
echo "\n--- end ---\n";