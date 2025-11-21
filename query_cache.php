<?php
/**
 * Simple Query Cache System - Reduces database load
 * Caches dashboard queries for 5 minutes to eliminate repeated slow queries
 */

class SimpleQueryCache {
    private $cache_dir;
    private $default_ttl = 300; // 5 minutes
    
    public function __construct($cache_dir = null) {
        $this->cache_dir = $cache_dir ?: __DIR__ . '/cache';
        
        // Create cache directory if it doesn't exist
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }
    
    /**
     * Get cached data or execute callback if cache miss
     */
    public function get($key, $callback, $ttl = null) {
        $ttl = $ttl ?: $this->default_ttl;
        $cache_file = $this->cache_dir . '/' . md5($key) . '.cache';
        
        // Check if cache exists and is valid
        if (file_exists($cache_file)) {
            $cache_data = json_decode(file_get_contents($cache_file), true);
            
            if ($cache_data && isset($cache_data['expires']) && $cache_data['expires'] > time()) {
                return [
                    'data' => $cache_data['data'],
                    'cached' => true,
                    'cache_age' => time() - $cache_data['created']
                ];
            }
        }
        
        // Cache miss - execute callback
        $data = $callback();
        
        // Store in cache
        $cache_data = [
            'data' => $data,
            'created' => time(),
            'expires' => time() + $ttl
        ];
        
        file_put_contents($cache_file, json_encode($cache_data));
        
        return [
            'data' => $data,
            'cached' => false,
            'cache_age' => 0
        ];
    }
    
    /**
     * Clear specific cache entry
     */
    public function clear($key) {
        $cache_file = $this->cache_dir . '/' . md5($key) . '.cache';
        if (file_exists($cache_file)) {
            unlink($cache_file);
        }
    }
    
    /**
     * Clear all cache entries
     */
    public function clearAll() {
        $files = glob($this->cache_dir . '/*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
    
    /**
     * Clean expired cache entries
     */
    public function cleanExpired() {
        $files = glob($this->cache_dir . '/*.cache');
        $cleaned = 0;
        
        foreach ($files as $file) {
            $cache_data = json_decode(file_get_contents($file), true);
            if (!$cache_data || $cache_data['expires'] <= time()) {
                unlink($file);
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
}

// Example usage for dashboard queries
if (basename($_SERVER['PHP_SELF']) === 'query_cache.php') {
    require_once __DIR__ . '/db.php';
    
    header('Content-Type: application/json');
    
    $cache = new SimpleQueryCache();
    
    // Test the cache system
    $start_time = microtime(true);
    
    $result = $cache->get('dashboard_stats', function() use ($pdo) {
        // This is the slow query that we want to cache
        $query_start = microtime(true);
        
        $stmt = $pdo->query("
            SELECT 
                (SELECT count(*) FROM donors WHERE status = 'approved') as approved_donors,
                (SELECT count(*) FROM blood_inventory WHERE expiry_date > CURRENT_DATE) as available_units,
                (SELECT count(*) FROM donations_new WHERE donation_date >= CURRENT_DATE - INTERVAL '30 days') as recent_donations,
                (SELECT count(*) FROM admin_audit_log WHERE created_at >= CURRENT_DATE - INTERVAL '7 days') as recent_audits
        ");
        
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $query_time = (microtime(true) - $query_start) * 1000;
        
        return [
            'stats' => $data,
            'query_time' => round($query_time, 2) . 'ms',
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }, 300); // Cache for 5 minutes
    
    $total_time = (microtime(true) - $start_time) * 1000;
    
    $response = [
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s'),
        'cache_status' => $result['cached'] ? 'HIT' : 'MISS',
        'cache_age' => $result['cache_age'] . ' seconds',
        'total_execution_time' => round($total_time, 2) . 'ms',
        'data' => $result['data'],
        'performance_improvement' => $result['cached'] ? 
            'Served from cache - ~200ms saved' : 
            'Fresh data - will be cached for next request'
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
}
?>
