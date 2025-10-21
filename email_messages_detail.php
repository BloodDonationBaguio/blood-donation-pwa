<?php
// email_messages_detail.php
echo "<h1>📧 Email Message Content for Each Status</h1>\n";

if (file_exists('includes/enhanced_donor_management.php')) {
    $content = file_get_contents('includes/enhanced_donor_management.php');

    // Split the file into functions to find email messages
    $functions = preg_split('/function\s+\w+\s*\(/', $content);

    echo "<h2>🔍 Email Messages by Function:</h2>\n";

    // Look for approveDonor function
    if (preg_match('/function approveDonor.*?send_confirmation_email.*?subject.*?message.*?(?=function|$)/s', $content, $match)) {
        echo "<h3>✅ Approved Status Email</h3>\n";
        echo "<div style='border:1px solid #28a745; padding:15px; margin:10px 0;'>\n";

        if (preg_match('/\$subject\s*=\s*"([^"]*)"/', $match[0], $subjectMatch)) {
            echo "<strong>Subject:</strong> " . htmlspecialchars($subjectMatch[1]) . "<br><br>\n";
        }

        if (preg_match('/\$message\s*=\s*"(.*?)"\s*;/s', $match[0], $messageMatch)) {
            echo "<strong>Message:</strong><br>\n";
            echo nl2br(htmlspecialchars($messageMatch[1]));
        }

        echo "</div>\n";
    }

    // Look for markDonorServed function
    if (preg_match('/function markDonorServed.*?send_confirmation_email.*?subject.*?message.*?(?=function|$)/s', $content, $match)) {
        echo "<h3>❤️ Served Status Email</h3>\n";
        echo "<div style='border:1px solid #e74c3c; padding:15px; margin:10px 0;'>\n";

        if (preg_match('/\$subject\s*=\s*"([^"]*)"/', $match[0], $subjectMatch)) {
            echo "<strong>Subject:</strong> " . htmlspecialchars($subjectMatch[1]) . "<br><br>\n";
        }

        if (preg_match('/\$message\s*=\s*"(.*?)"\s*;/s', $match[0], $messageMatch)) {
            echo "<strong>Message:</strong><br>\n";
            echo nl2br(htmlspecialchars($messageMatch[1]));
        }

        echo "</div>\n";
    }

    // Look for markDonorUnserved function
    if (preg_match('/function markDonorUnserved.*?subject.*?message.*?(?=function|$)/s', $content, $match)) {
        echo "<h3>⚠️ Unserved Status Email</h3>\n";
        echo "<div style='border:1px solid #f39c12; padding:15px; margin:10px 0;'>\n";

        if (preg_match('/\$subject\s*=\s*"([^"]*)"/', $match[0], $subjectMatch)) {
            echo "<strong>Subject:</strong> " . htmlspecialchars($subjectMatch[1]) . "<br><br>\n";
        }

        if (preg_match('/\$message\s*=\s*"(.*?)"\s*;/s', $match[0], $messageMatch)) {
            echo "<strong>Message:</strong><br>\n";
            echo nl2br(htmlspecialchars($messageMatch[1]));
        }

        echo "</div>\n";
    }

    // Look for updateDonorStatus function
    if (preg_match('/function updateDonorStatus.*?subject.*?message.*?(?=function|$)/s', $content, $match)) {
        echo "<h3>📝 Status Update Email</h3>\n";
        echo "<div style='border:1px solid #3498db; padding:15px; margin:10px 0;'>\n";

        if (preg_match('/\$subject\s*=\s*"([^"]*)"/', $match[0], $subjectMatch)) {
            echo "<strong>Subject:</strong> " . htmlspecialchars($subjectMatch[1]) . "<br><br>\n";
        }

        if (preg_match('/\$message\s*=\s*"(.*?)"\s*;/s', $match[0], $messageMatch)) {
            echo "<strong>Message:</strong><br>\n";
            echo nl2br(htmlspecialchars($messageMatch[1]));
        }

        echo "</div>\n";
    }

} else {
    echo "<h2>❌ enhanced_donor_management.php not found</h2>\n";
}

echo "<h2>📍 File Locations:</h2>\n";
echo "<ul>\n";
echo "<li><strong>Enhanced Donor Management:</strong> <code>includes/enhanced_donor_management.php</code></li>\n";
echo "<li><strong>Mail Helper:</strong> <code>includes/mail_helper.php</code></li>\n";
echo "<li><strong>Mail Helper New:</strong> <code>includes/mail_helper_new.php</code></li>\n";
echo "</ul>\n";

echo "<h2>🔧 How to Edit Email Messages:</h2>\n";
echo "<ol>\n";
echo "<li>Open the file: <code>includes/enhanced_donor_management.php</code></li>\n";
echo "<li>Find the function for the status you want to edit (approveDonor, markDonorServed, etc.)</li>\n";
echo "<li>Modify the <code>\$subject</code> and <code>\$message</code> variables</li>\n";
echo "<li>Save the file</li>\n";
echo "</ol>\n";

echo "<hr>\n";
echo "<p><a href='admin_enhanced_donor_management.php'>← Back to Admin Panel</a></p>\n";
?>
