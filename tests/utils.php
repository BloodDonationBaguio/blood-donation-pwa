<?php
/**
 * Test utilities: minimal assertions and HTML parsing helpers
 */

function t_assert($condition, $message) {
    if (!$condition) {
        echo "[FAIL] $message\n";
        return false;
    }
    echo "[PASS] $message\n";
    return true;
}

function t_section($title) {
    echo "\n=== $title ===\n";
}

function t_result($passed, $failed, $skipped = 0) {
    echo "\nSummary: PASS=$passed, FAIL=$failed, SKIP=$skipped\n";
}

/**
 * Extract "Showing X-Y of Z units" from admin_blood_inventory_modern.php HTML
 */
function parse_showing_badge_counts($html) {
    $pattern = '/Showing\s+(\d+)\-(\d+)\s+of\s+([0-9,]+)\s+units/i';
    if (preg_match($pattern, $html, $m)) {
        return [
            'start' => (int)$m[1],
            'end' => (int)$m[2],
            'total' => (int)str_replace(',', '', $m[3])
        ];
    }
    return null;
}

/**
 * Count data rows in modern admin table by looking for unit-id cells in tbody
 */
function count_table_rows($html) {
    // Narrow to tbody to avoid header rows
    $tbodyPattern = '/<tbody[^>]*>(.*?)<\/tbody>/is';
    if (preg_match($tbodyPattern, $html, $m)) {
        $tbody = $m[1];
        // A data row contains a <code class="unit-id"> element
        preg_match_all('/<code\s+class="unit-id"[^>]*>[^<]*<\/code>/i', $tbody, $mm);
        return count($mm[0]);
    }
    return 0;
}

/**
 * Normalize filter array to expected GET/manager formats
 */
function normalize_filters($filters) {
    $out = [
        'blood_type' => $filters['blood_type'] ?? '',
        'status' => $filters['status'] ?? '',
        'search' => $filters['search'] ?? ''
    ];
    return $out;
}

?>