<?php
/**
 * System Monitoring and Health Check System
 * Production-ready monitoring with alerts and metrics
 */

class MonitoringSystem {
    private $config;
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
        $this->config = $this->loadConfig();
    }
    
    private function loadConfig() {
        $configFile = __DIR__ . '/../config/monitoring_config.json';
        
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
        } else {
            // Default monitoring configuration
            $config = [
                'enabled' => true,
                'health_checks' => [
                    'database' => true,
                    'disk_space' => true,
                    'memory_usage' => true,
                    'response_time' => true,
                    'error_rate' => true
                ],
                'thresholds' => [
                    'disk_usage' => 80, // percentage
                    'memory_usage' => 85, // percentage
                    'response_time' => 2, // seconds
                    'error_rate' => 5 // percentage
                ],
                'alerts' => [
                    'email' => [
                        'enabled' => true,
                        'recipients' => ['admin@blooddonation.com']
                    ],
                    'webhook' => [
                        'enabled' => false,
                        'url' => ''
                    ]
                ],
                'metrics_retention' => 30, // days
                'check_interval' => 300 // seconds
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
            $configDir . '/monitoring_config.json',
            json_encode($config, JSON_PRETTY_PRINT)
        );
    }
    
    public function performHealthCheck() {
        if (!$this->config['enabled']) {
            return ['success' => true, 'message' => 'Monitoring disabled'];
        }
        
        $startTime = microtime(true);
        $checks = [];
        $overallStatus = 'healthy';
        
        // Database health check
        if ($this->config['health_checks']['database']) {
            $dbCheck = $this->checkDatabase();
            $checks['database'] = $dbCheck;
            if (!$dbCheck['healthy']) $overallStatus = 'unhealthy';
        }
        
        // Disk space check
        if ($this->config['health_checks']['disk_space']) {
            $diskCheck = $this->checkDiskSpace();
            $checks['disk_space'] = $diskCheck;
            if (!$diskCheck['healthy']) $overallStatus = 'unhealthy';
        }
        
        // Memory usage check
        if ($this->config['health_checks']['memory_usage']) {
            $memoryCheck = $this->checkMemoryUsage();
            $checks['memory_usage'] = $memoryCheck;
            if (!$memoryCheck['healthy']) $overallStatus = 'unhealthy';
        }
        
        // Response time check
        if ($this->config['health_checks']['response_time']) {
            $responseCheck = $this->checkResponseTime();
            $checks['response_time'] = $responseCheck;
            if (!$responseCheck['healthy']) $overallStatus = 'unhealthy';
        }
        
        // Error rate check
        if ($this->config['health_checks']['error_rate']) {
            $errorCheck = $this->checkErrorRate();
            $checks['error_rate'] = $errorCheck;
            if (!$errorCheck['healthy']) $overallStatus = 'unhealthy';
        }
        
        $endTime = microtime(true);
        $totalTime = round(($endTime - $startTime) * 1000, 2);
        
        $result = [
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => $overallStatus,
            'response_time' => $totalTime,
            'checks' => $checks
        ];
        
        // Log the health check
        $this->logHealthCheck($result);
        
        // Send alerts if unhealthy
        if ($overallStatus === 'unhealthy') {
            $this->sendAlert($result);
        }
        
        return $result;
    }
    
    private function checkDatabase() {
        try {
            $startTime = microtime(true);
            
            // Test basic connection
            $stmt = $this->db->query("SELECT 1");
            $result = $stmt->fetch();
            
            // Test table access
            $stmt = $this->db->query("SELECT COUNT(*) FROM donors_new");
            $count = $stmt->fetchColumn();
            
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);
            
            return [
                'healthy' => true,
                'response_time' => $responseTime,
                'record_count' => $count,
                'message' => 'Database connection successful'
            ];
            
        } catch (Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'message' => 'Database connection failed'
            ];
        }
    }
    
    private function checkDiskSpace() {
        $diskUsage = disk_free_space(__DIR__ . '/../');
        $diskTotal = disk_total_space(__DIR__ . '/../');
        $usagePercent = round((($diskTotal - $diskUsage) / $diskTotal) * 100, 2);
        
        $threshold = $this->config['thresholds']['disk_usage'];
        $healthy = $usagePercent < $threshold;
        
        return [
            'healthy' => $healthy,
            'usage_percent' => $usagePercent,
            'free_space' => $this->formatBytes($diskUsage),
            'total_space' => $this->formatBytes($diskTotal),
            'threshold' => $threshold,
            'message' => $healthy ? 'Disk space adequate' : 'Disk space low'
        ];
    }
    
    private function checkMemoryUsage() {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);
        $usagePercent = round(($memoryUsage / $memoryLimitBytes) * 100, 2);
        
        $threshold = $this->config['thresholds']['memory_usage'];
        $healthy = $usagePercent < $threshold;
        
        return [
            'healthy' => $healthy,
            'usage_percent' => $usagePercent,
            'current_usage' => $this->formatBytes($memoryUsage),
            'memory_limit' => $memoryLimit,
            'threshold' => $threshold,
            'message' => $healthy ? 'Memory usage normal' : 'Memory usage high'
        ];
    }
    
    private function checkResponseTime() {
        $startTime = microtime(true);
        
        // Simulate a typical request
        $this->db->query("SELECT COUNT(*) FROM donors_new");
        
        $endTime = microtime(true);
        $responseTime = round(($endTime - $startTime) * 1000, 2);
        
        $threshold = $this->config['thresholds']['response_time'] * 1000; // Convert to milliseconds
        $healthy = $responseTime < $threshold;
        
        return [
            'healthy' => $healthy,
            'response_time' => $responseTime,
            'threshold' => $threshold,
            'message' => $healthy ? 'Response time acceptable' : 'Response time slow'
        ];
    }
    
    private function checkErrorRate() {
        $logFile = __DIR__ . '/../logs/error.log';
        
        if (!file_exists($logFile)) {
            return [
                'healthy' => true,
                'error_rate' => 0,
                'message' => 'No error log found'
            ];
        }
        
        // Count errors in the last hour
        $oneHourAgo = time() - 3600;
        $errors = 0;
        $totalRequests = 100; // This would be tracked in a real system
        
        $logContent = file_get_contents($logFile);
        $lines = explode("\n", $logContent);
        
        foreach ($lines as $line) {
            if (strpos($line, 'ERROR') !== false || strpos($line, 'FATAL') !== false) {
                $errors++;
            }
        }
        
        $errorRate = $totalRequests > 0 ? round(($errors / $totalRequests) * 100, 2) : 0;
        $threshold = $this->config['thresholds']['error_rate'];
        $healthy = $errorRate < $threshold;
        
        return [
            'healthy' => $healthy,
            'error_rate' => $errorRate,
            'error_count' => $errors,
            'threshold' => $threshold,
            'message' => $healthy ? 'Error rate acceptable' : 'Error rate high'
        ];
    }
    
    private function logHealthCheck($result) {
        $logFile = __DIR__ . '/../logs/health_check.log';
        $logEntry = json_encode($result) . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function sendAlert($healthData) {
        if (!$this->config['alerts']['email']['enabled']) {
            return;
        }
        
        $subject = 'System Health Alert - Blood Donation System';
        $message = "System health check failed:\n\n";
        $message .= "Timestamp: " . $healthData['timestamp'] . "\n";
        $message .= "Status: " . $healthData['status'] . "\n";
        $message .= "Response Time: " . $healthData['response_time'] . "ms\n\n";
        
        $message .= "Failed Checks:\n";
        foreach ($healthData['checks'] as $check => $data) {
            if (!$data['healthy']) {
                $message .= "- $check: " . $data['message'] . "\n";
            }
        }
        
        // Send email alert
        require_once __DIR__ . '/advanced_mail.php';
        $mailer = new AdvancedMail();
        
        foreach ($this->config['alerts']['email']['recipients'] as $recipient) {
            $mailer->sendEmail($recipient, $subject, $message, false);
        }
    }
    
    public function getSystemMetrics() {
        $metrics = [
            'timestamp' => date('Y-m-d H:i:s'),
            'server' => [
                'php_version' => PHP_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'operating_system' => PHP_OS,
                'timezone' => date_default_timezone_get()
            ],
            'database' => [
                'driver' => $this->db->getAttribute(PDO::ATTR_DRIVER_NAME),
                'version' => $this->db->query('SELECT VERSION()')->fetchColumn()
            ],
            'performance' => [
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
            ],
            'disk' => [
                'free_space' => disk_free_space(__DIR__ . '/../'),
                'total_space' => disk_total_space(__DIR__ . '/../')
            ]
        ];
        
        return $metrics;
    }
    
    public function getHealthHistory($hours = 24) {
        $logFile = __DIR__ . '/../logs/health_check.log';
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $cutoffTime = time() - ($hours * 3600);
        $history = [];
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if ($data && strtotime($data['timestamp']) > $cutoffTime) {
                $history[] = $data;
            }
        }
        
        return array_reverse($history); // Most recent first
    }
    
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unit = 0;
        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }
        return round($bytes, 2) . ' ' . $units[$unit];
    }
    
    private function parseMemoryLimit($limit) {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $limit = (int) $limit;
        
        switch ($last) {
            case 'g':
                $limit *= 1024;
            case 'm':
                $limit *= 1024;
            case 'k':
                $limit *= 1024;
        }
        
        return $limit;
    }
}
?>
