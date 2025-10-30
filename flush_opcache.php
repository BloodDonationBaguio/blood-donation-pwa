<?php
// Flush PHP OPcache safely
header('Content-Type: text/plain');
if (function_exists('opcache_reset')) {
    $ok = @opcache_reset();
    echo $ok ? "OPcache reset: OK\n" : "OPcache reset: FAILED\n";
} else {
    echo "OPcache not enabled or function unavailable.\n";
}
?>