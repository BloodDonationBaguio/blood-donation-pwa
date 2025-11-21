<?php
/**
 * Monitoring IP Configuration Helper
 * Generates firewall rules and configuration for monitoring services
 */

header('Content-Type: application/json');

// Common monitoring service IP ranges
$monitoring_ips = [
    'uptimerobot' => [
        '69.162.124.224/28',
        '63.143.42.240/28', 
        '50.16.153.186/32',
        '50.16.153.187/32',
        '107.21.1.61/32',
        '23.92.127.2/32',
        '50.16.153.186/32',
        '174.129.142.34/32',
        '174.129.142.35/32',
        '174.129.142.36/32',
        '174.129.142.37/32',
        '69.162.124.224/28',
        '63.143.42.240/28',
        '46.137.190.132/32',
        '122.248.234.23/32',
        '175.45.132.20/32',
        '94.130.180.93/32',
        '159.203.30.41/32',
        '188.226.183.141/32',
        '159.203.30.41/32',
        '46.101.230.157/32',
        '178.62.52.237/32'
    ],
    'pingdom' => [
        '5.172.196.188/32',
        '23.111.159.174/32',
        '27.122.14.7/32',
        '37.252.231.50/32',
        '46.20.45.18/32',
        '46.246.122.114/32',
        '50.16.153.186/32',
        '50.23.94.74/32',
        '52.48.244.35/32',
        '52.52.34.158/32',
        '52.52.95.213/32',
        '52.52.118.192/32',
        '64.237.55.3/32',
        '66.165.229.130/32',
        '69.46.86.219/32',
        '72.46.130.18/32',
        '72.46.131.10/32',
        '76.72.167.90/32',
        '76.72.172.208/32',
        '76.164.234.106/32',
        '82.103.136.16/32',
        '85.93.93.123/32',
        '89.163.146.247/32',
        '89.163.242.206/32',
        '94.75.211.73/32',
        '95.141.32.46/32',
        '103.47.211.210/32',
        '104.129.30.18/32',
        '107.6.106.82/32',
        '148.72.171.17/32',
        '162.218.67.34/32',
        '169.56.174.151/32',
        '174.34.156.130/32',
        '177.75.4.6/32',
        '178.255.152.2/32',
        '179.50.12.18/32',
        '185.39.146.214/32',
        '185.93.3.123/32',
        '185.136.156.82/32',
        '185.180.12.65/32',
        '190.120.230.7/32',
        '196.240.207.18/32',
        '212.78.83.12/32',
        '213.188.229.137/32'
    ],
    'datadog' => [
        '52.6.75.23/32',
        '54.236.171.113/32',
        '52.70.155.25/32',
        '52.86.109.250/32',
        '52.87.212.28/32',
        '52.201.75.131/32',
        '52.204.51.47/32',
        '52.205.102.38/32',
        '52.206.29.2/32',
        '52.206.198.236/32',
        '52.207.47.136/32',
        '52.207.198.2/32',
        '52.207.198.90/32',
        '52.207.198.202/32',
        '54.85.81.108/32',
        '54.85.113.227/32',
        '54.85.176.28/32',
        '54.85.176.203/32',
        '54.85.204.116/32',
        '54.85.204.203/32',
        '54.85.221.84/32',
        '54.85.221.203/32'
    ]
];

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'monitoring_services' => [],
    'firewall_rules' => [],
    'nginx_config' => [],
    'apache_config' => [],
    'cloudflare_config' => []
];

// Generate configurations for each service
foreach ($monitoring_ips as $service => $ips) {
    $results['monitoring_services'][$service] = [
        'name' => ucfirst($service),
        'ip_count' => count($ips),
        'ip_ranges' => $ips
    ];
    
    // Generate iptables rules
    $iptables_rules = [];
    foreach ($ips as $ip) {
        $iptables_rules[] = "iptables -A INPUT -s {$ip} -p tcp --dport 80 -j ACCEPT";
        $iptables_rules[] = "iptables -A INPUT -s {$ip} -p tcp --dport 443 -j ACCEPT";
    }
    $results['firewall_rules'][$service] = $iptables_rules;
    
    // Generate Nginx allow rules
    $nginx_rules = [];
    foreach ($ips as $ip) {
        $nginx_rules[] = "allow {$ip};";
    }
    $results['nginx_config'][$service] = $nginx_rules;
    
    // Generate Apache allow rules
    $apache_rules = [];
    foreach ($ips as $ip) {
        if (strpos($ip, '/32') !== false) {
            $clean_ip = str_replace('/32', '', $ip);
            $apache_rules[] = "Require ip {$clean_ip}";
        } else {
            $apache_rules[] = "Require ip {$ip}";
        }
    }
    $results['apache_config'][$service] = $apache_rules;
}

// Generate combined configurations
$all_ips = [];
foreach ($monitoring_ips as $service => $ips) {
    $all_ips = array_merge($all_ips, $ips);
}
$all_ips = array_unique($all_ips);

$results['combined'] = [
    'total_ip_ranges' => count($all_ips),
    'all_ips' => $all_ips
];

// Generate Cloudflare Access Rules (if using Cloudflare)
$cloudflare_rules = [];
foreach ($all_ips as $ip) {
    $cloudflare_rules[] = [
        'mode' => 'whitelist',
        'configuration' => [
            'target' => 'ip',
            'value' => $ip
        ],
        'notes' => 'Monitoring service IP'
    ];
}
$results['cloudflare_config'] = $cloudflare_rules;

// Generate .htaccess rules for Apache
$htaccess_content = "# Allow monitoring services\n";
$htaccess_content .= "<RequireAll>\n";
foreach ($all_ips as $ip) {
    if (strpos($ip, '/32') !== false) {
        $clean_ip = str_replace('/32', '', $ip);
        $htaccess_content .= "    Require ip {$clean_ip}\n";
    } else {
        $htaccess_content .= "    Require ip {$ip}\n";
    }
}
$htaccess_content .= "</RequireAll>\n";

$results['htaccess_config'] = $htaccess_content;

// Generate instructions
$results['instructions'] = [
    'nginx' => [
        'description' => 'Add these rules to your Nginx server block',
        'location' => '/etc/nginx/sites-available/your-site',
        'example' => "location /health {\n    " . implode("\n    ", array_slice($results['nginx_config']['uptimerobot'], 0, 3)) . "\n    deny all;\n}"
    ],
    'apache' => [
        'description' => 'Add these rules to your Apache virtual host or .htaccess',
        'location' => '/var/www/html/.htaccess or /etc/apache2/sites-available/your-site.conf',
        'example' => "<Directory \"/var/www/html\">\n    " . implode("\n    ", array_slice($results['apache_config']['uptimerobot'], 0, 3)) . "\n</Directory>"
    ],
    'iptables' => [
        'description' => 'Run these commands to allow monitoring IPs through firewall',
        'note' => 'Make sure to save iptables rules after applying',
        'save_command' => 'iptables-save > /etc/iptables/rules.v4'
    ]
];

echo json_encode($results, JSON_PRETTY_PRINT);
?>
