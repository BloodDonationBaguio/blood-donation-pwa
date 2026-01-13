<?php
/**
 * Timezone Configuration
 * Sets all date/time functions to Baguio, Philippines timezone
 */

// Set timezone to Baguio, Philippines (Asia/Manila)
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Manila');
}
