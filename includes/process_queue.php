<?php
/**
 * Process Email Queue
 * 
 * This script processes the email queue and should be called by a cron job or scheduled task.
 */

// Set error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/queue_processor.log');
error_reporting(E_ALL);

// Include the email queue functionality
require_once __DIR__ . '/email_queue.php';

// Process the email queue
$processed = process_email_queue();

// Log the result
if ($processed > 0) {
    error_log("Processed $processed emails from the queue");
}

// For command line usage
if (php_sapi_name() === 'cli') {
    echo "Processed $processed emails.\n";
}

exit(0);
?>
