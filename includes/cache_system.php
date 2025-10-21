<?php
/**
 * Advanced Caching System
 * Production-ready caching with multiple backends
 */

class CacheSystem {
    private $config;
    private $backend;
    
    public function __construct() {
        $this->config = $this->loadConfig();
        $this->backend = $this->initializeBackend();
    }
    
    private function loadConfig() {
        $configFile = __DIR__ . '/../config/cache_config.json';
        
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
        } else {
            // Default cache configuration
            $config = [
                'enabled' => true,
                'backend' => 'file', // file, redis, memcached
                'default_ttl' => 3600, // 1 hour
                'file' => [
                    'path' => '../cache/',
                    'max_size' => '100MB'
                ],
                'redis' => [
                    'host' => 'localhost',
                    'port' => 6379,
                    'password' => '',
                    'database' => 0
                ],
                'memcached' => [
                    'host' => 'localhost',
                    'port' => 11211
                ],
                'compression' => true,
                'serialization' => 'json'
            ];
            
            $this->saveConfig($config);
        }
        
        return $config;
    }
    
    public function saveConfig($config) {
        $configDir = __DIR__ . '/../config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        
        file_put_contents(
            $configDir . '/cache_config.json',
            json_encode($config, JSON_PRETTY_PRINT)
        );
    }
    
    private function initializeBackend() {
        switch ($this->config['backend']) {
            case 'file':
                return new FileCache($this->config['file']);
            case 'redis':
                return new RedisCache($this->config['redis']);
            case 'memcached':
                return new MemcachedCache($this->config['memcached']);
            default:
                throw new Exception('Unknown cache backend: ' . $this->config['backend']);
        }
    }
    
    public function get($key) {
        if (!$this->config['enabled']) {
            return null;
        }
        
        try {
            return $this->backend->get($key);
        } catch (Exception $e) {
            error_log("Cache get error: " . $e->getMessage());
            return null;
        }
    }
    
    public function set($key, $value, $ttl = null) {
        if (!$this->config['enabled']) {
            return false;
        }
        
        if ($ttl === null) {
            $ttl = $this->config['default_ttl'];
        }
        
        try {
            return $this->backend->set($key, $value, $ttl);
        } catch (Exception $e) {
            error_log("Cache set error: " . $e->getMessage());
            return false;
        }
    }
    
    public function delete($key) {
        if (!$this->config['enabled']) {
            return false;
        }
        
        try {
            return $this->backend->delete($key);
        } catch (Exception $e) {
            error_log("Cache delete error: " . $e->getMessage());
            return false;
        }
    }
    
    public function clear() {
        if (!$this->config['enabled']) {
            return false;
        }
        
        try {
            return $this->backend->clear();
        } catch (Exception $e) {
            error_log("Cache clear error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getStats() {
        try {
            return $this->backend->getStats();
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}

class FileCache {
    private $path;
    private $maxSize;
    
    public function __construct($config) {
        $this->path = $config['path'];
        $this->maxSize = $this->parseSize($config['max_size']);
        
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }
    
    public function get($key) {
        $file = $this->getFilePath($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = file_get_contents($file);
        $cacheData = json_decode($data, true);
        
        if (!$cacheData || $cacheData['expires'] < time()) {
            unlink($file);
            return null;
        }
        
        return $cacheData['value'];
    }
    
    public function set($key, $value, $ttl) {
        $file = $this->getFilePath($key);
        $dir = dirname($file);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $cacheData = [
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        $result = file_put_contents($file, json_encode($cacheData), LOCK_EX);
        
        if ($result === false) {
            return false;
        }
        
        // Clean up if cache is too large
        $this->cleanup();
        
        return true;
    }
    
    public function delete($key) {
        $file = $this->getFilePath($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }
    
    public function clear() {
        $files = glob($this->path . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            } elseif (is_dir($file)) {
                $this->removeDirectory($file);
            }
        }
        return true;
    }
    
    public function getStats() {
        $files = glob($this->path . '/**/*', GLOB_BRACE);
        $totalSize = 0;
        $count = 0;
        $expired = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $totalSize += filesize($file);
                $count++;
                
                $data = file_get_contents($file);
                $cacheData = json_decode($data, true);
                if ($cacheData && $cacheData['expires'] < time()) {
                    $expired++;
                }
            }
        }
        
        return [
            'total_files' => $count,
            'total_size' => $totalSize,
            'expired_files' => $expired,
            'max_size' => $this->maxSize,
            'usage_percent' => ($this->maxSize > 0) ? ($totalSize / $this->maxSize) * 100 : 0
        ];
    }
    
    private function getFilePath($key) {
        $hash = md5($key);
        $dir = substr($hash, 0, 2);
        return $this->path . '/' . $dir . '/' . $hash . '.cache';
    }
    
    private function cleanup() {
        $files = glob($this->path . '/**/*.cache', GLOB_BRACE);
        $totalSize = 0;
        
        // Calculate total size
        foreach ($files as $file) {
            $totalSize += filesize($file);
        }
        
        // If cache is too large, remove oldest files
        if ($totalSize > $this->maxSize) {
            $fileTimes = [];
            foreach ($files as $file) {
                $fileTimes[$file] = filemtime($file);
            }
            
            asort($fileTimes);
            
            foreach ($fileTimes as $file => $time) {
                unlink($file);
                $totalSize -= filesize($file);
                
                if ($totalSize <= $this->maxSize * 0.8) { // Keep 20% buffer
                    break;
                }
            }
        }
        
        // Remove expired files
        foreach ($files as $file) {
            $data = file_get_contents($file);
            $cacheData = json_decode($data, true);
            if ($cacheData && $cacheData['expires'] < time()) {
                unlink($file);
            }
        }
    }
    
    private function removeDirectory($dir) {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    $this->removeDirectory($path);
                } else {
                    unlink($path);
                }
            }
            rmdir($dir);
        }
    }
    
    private function parseSize($size) {
        $units = ['B' => 1, 'KB' => 1024, 'MB' => 1024*1024, 'GB' => 1024*1024*1024];
        $size = strtoupper(trim($size));
        
        foreach ($units as $unit => $multiplier) {
            if (strpos($size, $unit) !== false) {
                $number = (float) str_replace($unit, '', $size);
                return $number * $multiplier;
            }
        }
        
        return (int) $size;
    }
}

class RedisCache {
    private $redis;
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
        $this->redis = new Redis();
        
        try {
            $this->redis->connect($config['host'], $config['port']);
            if (!empty($config['password'])) {
                $this->redis->auth($config['password']);
            }
            if (isset($config['database'])) {
                $this->redis->select($config['database']);
            }
        } catch (Exception $e) {
            throw new Exception('Redis connection failed: ' . $e->getMessage());
        }
    }
    
    public function get($key) {
        $value = $this->redis->get($key);
        return $value !== false ? json_decode($value, true) : null;
    }
    
    public function set($key, $value, $ttl) {
        return $this->redis->setex($key, $ttl, json_encode($value));
    }
    
    public function delete($key) {
        return $this->redis->del($key);
    }
    
    public function clear() {
        return $this->redis->flushdb();
    }
    
    public function getStats() {
        $info = $this->redis->info();
        return [
            'used_memory' => $info['used_memory'] ?? 0,
            'connected_clients' => $info['connected_clients'] ?? 0,
            'total_commands_processed' => $info['total_commands_processed'] ?? 0,
            'keyspace_hits' => $info['keyspace_hits'] ?? 0,
            'keyspace_misses' => $info['keyspace_misses'] ?? 0
        ];
    }
}

class MemcachedCache {
    private $memcached;
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
        $this->memcached = new Memcached();
        
        try {
            $this->memcached->addServer($config['host'], $config['port']);
        } catch (Exception $e) {
            throw new Exception('Memcached connection failed: ' . $e->getMessage());
        }
    }
    
    public function get($key) {
        $value = $this->memcached->get($key);
        return $value !== false ? json_decode($value, true) : null;
    }
    
    public function set($key, $value, $ttl) {
        return $this->memcached->set($key, json_encode($value), $ttl);
    }
    
    public function delete($key) {
        return $this->memcached->delete($key);
    }
    
    public function clear() {
        return $this->memcached->flush();
    }
    
    public function getStats() {
        $stats = $this->memcached->getStats();
        $server = $this->config['host'] . ':' . $this->config['port'];
        return $stats[$server] ?? [];
    }
}
?>
