<?php
/**
 * Donor 90-day reminder cron
 * - Finds donors marked as served whose last_donation_date was exactly 90 days ago (or more, once, if unsent)
 * - Sends an email encouraging next donation
 * To run on Windows Task Scheduler or cron: php -f includes/donor_reminder_cron.php
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/mail_helper.php';

// Ensure required columns exist
function ensureReminderColumns(PDO $pdo) {
    try {
        $pdo->exec("ALTER TABLE donors_new ADD COLUMN IF NOT EXISTS last_donation_date DATE NULL");
    } catch (Throwable $e) {}
    try {
        $pdo->exec("ALTER TABLE donors_new ADD COLUMN IF NOT EXISTS last_reminder_sent DATE NULL");
    } catch (Throwable $e) {}
}

ensureReminderColumns($pdo);

$today = new DateTime('today');
$targetDate = (clone $today)->modify('-90 days')->format('Y-m-d');

$stmt = $pdo->prepare("SELECT id, first_name, last_name, email, reference_code, blood_type, last_donation_date, last_reminder_sent
                        FROM donors_new
                        WHERE status = 'served' 
                          AND email IS NOT NULL AND email != ''
                          AND last_donation_date IS NOT NULL
                          AND last_donation_date <= :target
                          AND (last_reminder_sent IS NULL OR last_reminder_sent < last_donation_date)");
$stmt->execute([':target' => $targetDate]);
$donors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sentCount = 0;
foreach ($donors as $donor) {
    $name = trim($donor['first_name'] . ' ' . $donor['last_name']);
    $subject = 'It’s time for your next life-saving donation (90-day reminder)';
    $lastDate = date('M d, Y', strtotime($donor['last_donation_date']));
    $message = "<p>Dear <strong>{$name}</strong>,</p>" .
               "<p>Thank you again for donating blood on <strong>{$lastDate}</strong>. " .
               "It’s been about 90 days — you may now be eligible to donate again and help save more lives.</p>" .
               "<p>Please visit the Philippine Red Cross - Baguio Chapter from 8:00 AM to 5:00 PM, or reply to this email for assistance.</p>" .
               "<p>Reference: <code>{$donor['reference_code']}</code><br>Blood Type: <strong>{$donor['blood_type']}</strong></p>" .
               "<p>Thank you for being a hero!</p>" .
               "<p>— PRC Baguio Chapter</p>";

    if (send_confirmation_email($donor['email'], $subject, $message, $name)) {
        $upd = $pdo->prepare("UPDATE donors_new SET last_reminder_sent = CURDATE() WHERE id = ?");
        $upd->execute([$donor['id']]);
        $sentCount++;
    }
}

echo "Donor reminders sent: {$sentCount}\n";
?>


