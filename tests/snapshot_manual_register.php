<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('TEST_MODE', true);
session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_username'] = 'tester';
$_GET['tab'] = 'manual-register';

ob_start();
include dirname(__DIR__) . '/admin.php';
$html = ob_get_clean();

function find($pattern, $text){ return preg_match($pattern, $text) ? 'FOUND' : 'NOT FOUND'; }
function count_match($pattern, $text){ preg_match_all($pattern, $text, $m); return isset($m[0]) ? count($m[0]) : 0; }

$hasForm = find('/<form[^>]*id="donorForm"/i', $html);
$hasMedicalLabel = find('/Medical Screening\s*\(Sections A–G\)/i', $html);
$hasQuestionBlocks = count_match('/Question\s+[0-9]+/i', $html);
$hasRecaptcha = find('/g-recaptcha/i', $html);
$hasEligibility = find('/id="eligibilityCheck"/i', $html);
$strippedHidden = find('/display\s*:\s*none/i', $html);

header('Content-Type: text/plain');
echo "=== Snapshot: admin.php?tab=manual-register ===\n\n";
echo "Form tag: $hasForm\n";
echo "Medical section heading: $hasMedicalLabel\n";
echo "Question blocks count: $hasQuestionBlocks\n";
echo "Recaptcha present: $hasRecaptcha\n";
echo "Eligibility section present: $hasEligibility\n";
echo "Inline display:none still present: $strippedHidden\n";
echo "\n--- first 1000 chars of HTML ---\n";
echo substr($html, 0, 1000);
echo "\n--- end ---\n";