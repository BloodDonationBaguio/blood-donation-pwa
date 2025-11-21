<?php
/**
 * Email Display Helper - Makes placeholder emails look professional
 */

/**
 * Format email for display in admin interface
 * Converts placeholder emails to user-friendly format
 */
function formatEmailForDisplay($email) {
    if (empty($email)) {
        return '<span class="text-muted">No email provided</span>';
    }
    
    // Check if it's a placeholder email
    if (strpos($email, '@placeholder.local') !== false || 
        strpos($email, '@donor.invalid') !== false) {
        
        // Extract reference number from placeholder email
        if (preg_match('/noemail\.([^@]+)@/', $email, $matches)) {
            $refCode = strtoupper($matches[1]);
            return '<span class="text-muted" title="No email provided">No email (' . $refCode . ')</span>';
        } else if (preg_match('/no-email\+([^@]+)@/', $email, $matches)) {
            $refCode = strtoupper($matches[1]);
            return '<span class="text-muted" title="No email provided">No email (' . $refCode . ')</span>';
        } else {
            return '<span class="text-muted">No email provided</span>';
        }
    }
    
    // Regular email - return as is
    return htmlspecialchars($email);
}

/**
 * Check if email is a placeholder
 */
function isPlaceholderEmail($email) {
    return !empty($email) && (
        strpos($email, '@placeholder.local') !== false || 
        strpos($email, '@donor.invalid') !== false
    );
}

/**
 * Get clean email for database operations (null for placeholders)
 */
function getCleanEmailForDatabase($email) {
    if (isPlaceholderEmail($email)) {
        return null;
    }
    return $email;
}

/**
 * Generate a clean placeholder email
 */
function generatePlaceholderEmail($referenceCode) {
    return 'noemail.' . strtolower($referenceCode) . '@placeholder.local';
}

// Example usage for testing
if (basename($_SERVER['PHP_SELF']) === 'email_display_helper.php') {
    header('Content-Type: application/json');
    
    $test_emails = [
        'john@example.com',
        'noemail.dnr-f384ab@placeholder.local',
        'no-email+dnr-828a26@donor.invalid',
        '',
        null
    ];
    
    $results = [];
    foreach ($test_emails as $email) {
        $results[] = [
            'original' => $email,
            'formatted_display' => formatEmailForDisplay($email),
            'is_placeholder' => isPlaceholderEmail($email),
            'clean_for_db' => getCleanEmailForDatabase($email)
        ];
    }
    
    echo json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'test_results' => $results,
        'example_usage' => [
            'in_admin_table' => 'Use formatEmailForDisplay($donor["email"]) in admin tables',
            'for_email_sending' => 'Use getCleanEmailForDatabase($email) to check if email is real',
            'new_registrations' => 'Use generatePlaceholderEmail($refCode) for new donors without email'
        ]
    ], JSON_PRETTY_PRINT);
}
?>
