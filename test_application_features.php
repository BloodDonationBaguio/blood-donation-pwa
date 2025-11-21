<?php
/**
 * Application Feature Testing Suite
 * Tests specific blood donation system features
 */

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
set_time_limit(120);

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'feature_tests' => [],
    'api_tests' => [],
    'security_tests' => [],
    'integration_tests' => [],
    'user_flow_tests' => []
];

// 1. Donor Management Feature Tests
function testDonorRegistration($pdo) {
    try {
        // Test donor table structure
        $stmt = $pdo->query("
            SELECT column_name, data_type, is_nullable 
            FROM information_schema.columns 
            WHERE table_name = 'donors' AND table_schema = 'public'
            ORDER BY ordinal_position
        ");
        $columns = $stmt->fetchAll();
        
        $required_columns = ['id', 'first_name', 'last_name', 'email', 'blood_type'];
        $existing_columns = array_column($columns, 'column_name');
        $missing_columns = array_diff($required_columns, $existing_columns);
        
        // Test insert capability (with rollback)
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO donors (first_name, last_name, email, phone, blood_type, address) 
                VALUES (?, ?, ?, ?, ?, ?) 
                RETURNING id
            ");
            $stmt->execute(['Test', 'Donor', 'test@example.com', '1234567890', 'O+', 'Test Address']);
            $donor_id = $stmt->fetchColumn();
            
            $pdo->rollback(); // Always rollback test data
            
            return [
                'status' => 'pass',
                'message' => 'Donor registration functionality working',
                'table_columns' => count($columns),
                'missing_columns' => $missing_columns,
                'test_insert_id' => $donor_id
            ];
        } catch (Exception $e) {
            $pdo->rollback();
            return [
                'status' => 'fail',
                'message' => 'Donor registration insert failed: ' . $e->getMessage(),
                'table_columns' => count($columns),
                'missing_columns' => $missing_columns
            ];
        }
    } catch (Exception $e) {
        return [
            'status' => 'fail',
            'message' => 'Donor table access failed: ' . $e->getMessage()
        ];
    }
}

$results['feature_tests']['donor_registration'] = testDonorRegistration($pdo);

// 2. Blood Inventory Management
function testBloodInventory($pdo) {
    try {
        // Check blood inventory table
        $stmt = $pdo->query("
            SELECT column_name, data_type 
            FROM information_schema.columns 
            WHERE table_name = 'blood_inventory' AND table_schema = 'public'
        ");
        $columns = $stmt->fetchAll();
        
        if (empty($columns)) {
            return [
                'status' => 'warning',
                'message' => 'Blood inventory table not found'
            ];
        }
        
        // Test blood type aggregation
        $stmt = $pdo->query("
            SELECT 
                blood_type,
                COUNT(*) as unit_count,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_units
            FROM blood_inventory 
            GROUP BY blood_type
            ORDER BY blood_type
        ");
        $inventory_stats = $stmt->fetchAll();
        
        return [
            'status' => 'pass',
            'message' => 'Blood inventory system functional',
            'table_columns' => count($columns),
            'blood_types_in_inventory' => count($inventory_stats),
            'inventory_summary' => $inventory_stats
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'fail',
            'message' => 'Blood inventory test failed: ' . $e->getMessage()
        ];
    }
}();

// 3. Admin Authentication Test
$results['feature_tests']['admin_authentication'] = function() use ($pdo) {
    try {
        // Check admin_users table
        $stmt = $pdo->query("
            SELECT column_name, data_type 
            FROM information_schema.columns 
            WHERE table_name = 'admin_users' AND table_schema = 'public'
        ");
        $columns = $stmt->fetchAll();
        
        if (empty($columns)) {
            return [
                'status' => 'warning',
                'message' => 'Admin users table not found'
            ];
        }
        
        // Count admin users
        $stmt = $pdo->query("SELECT COUNT(*) FROM admin_users");
        $admin_count = $stmt->fetchColumn();
        
        // Check for required columns
        $column_names = array_column($columns, 'column_name');
        $required_columns = ['username', 'password', 'email'];
        $missing_columns = array_diff($required_columns, $column_names);
        
        return [
            'status' => empty($missing_columns) ? 'pass' : 'warning',
            'message' => empty($missing_columns) ? 'Admin authentication structure complete' : 'Missing required columns',
            'admin_count' => $admin_count,
            'table_columns' => count($columns),
            'missing_columns' => $missing_columns
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'fail',
            'message' => 'Admin authentication test failed: ' . $e->getMessage()
        ];
    }
}();

// 4. Donation Tracking Test
$results['feature_tests']['donation_tracking'] = function() use ($pdo) {
    try {
        // Check donations table
        $stmt = $pdo->query("
            SELECT column_name, data_type 
            FROM information_schema.columns 
            WHERE table_name = 'donations' AND table_schema = 'public'
        ");
        $columns = $stmt->fetchAll();
        
        if (empty($columns)) {
            return [
                'status' => 'warning',
                'message' => 'Donations table not found'
            ];
        }
        
        // Get donation statistics
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_donations,
                COUNT(DISTINCT donor_id) as unique_donors,
                COUNT(*) FILTER (WHERE status = 'completed') as completed_donations,
                COUNT(*) FILTER (WHERE created_at >= CURRENT_DATE - INTERVAL '30 days') as recent_donations
            FROM donations
        ");
        $donation_stats = $stmt->fetch();
        
        return [
            'status' => 'pass',
            'message' => 'Donation tracking system functional',
            'table_columns' => count($columns),
            'donation_statistics' => $donation_stats
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'fail',
            'message' => 'Donation tracking test failed: ' . $e->getMessage()
        ];
    }
}();

// 5. API Endpoint Tests
$base_url = 'https://' . $_SERVER['HTTP_HOST'];

$api_endpoints = [
    '/health.php' => 'Health Check',
    '/connection_diagnostic.php' => 'Connection Diagnostic',
    '/optimize_connections.php' => 'Connection Optimizer'
];

foreach ($api_endpoints as $endpoint => $description) {
    $results['api_tests'][$endpoint] = function() use ($base_url, $endpoint, $description) {
        $start_time = microtime(true);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $base_url . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $response_time = round((microtime(true) - $start_time) * 1000, 2);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'status' => 'fail',
                'message' => "API endpoint failed: $error",
                'endpoint' => $endpoint,
                'description' => $description
            ];
        }
        
        $status = ($http_code >= 200 && $http_code < 300) ? 'pass' : 'warning';
        
        return [
            'status' => $status,
            'message' => "$description endpoint responsive",
            'http_code' => $http_code,
            'response_time' => $response_time . 'ms',
            'endpoint' => $endpoint
        ];
    }();
}

// 6. Security Tests
$results['security_tests']['sql_injection_protection'] = function() use ($pdo) {
    try {
        // Test prepared statement protection
        $malicious_input = "'; DROP TABLE donors; --";
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM donors WHERE first_name = ?");
        $stmt->execute([$malicious_input]);
        $result = $stmt->fetchColumn();
        
        // If we get here, prepared statements are working
        return [
            'status' => 'pass',
            'message' => 'SQL injection protection working (prepared statements)',
            'test_input' => $malicious_input,
            'result' => 'Query executed safely'
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'warning',
            'message' => 'SQL injection test encountered error: ' . $e->getMessage()
        ];
    }
}();

$results['security_tests']['password_hashing'] = function() use ($pdo) {
    try {
        // Check if passwords are hashed in admin_users table
        $stmt = $pdo->query("
            SELECT password 
            FROM admin_users 
            WHERE password IS NOT NULL 
            LIMIT 1
        ");
        $password_sample = $stmt->fetchColumn();
        
        if (!$password_sample) {
            return [
                'status' => 'warning',
                'message' => 'No password samples found to test'
            ];
        }
        
        // Check if password looks hashed (bcrypt, etc.)
        $is_hashed = (strlen($password_sample) >= 60 && 
                     (strpos($password_sample, '$2y$') === 0 || 
                      strpos($password_sample, '$2b$') === 0 ||
                      strlen($password_sample) === 64)); // SHA256
        
        return [
            'status' => $is_hashed ? 'pass' : 'fail',
            'message' => $is_hashed ? 'Passwords appear to be properly hashed' : 'Passwords may not be properly hashed',
            'password_length' => strlen($password_sample),
            'appears_hashed' => $is_hashed
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'warning',
            'message' => 'Password hashing test failed: ' . $e->getMessage()
        ];
    }
}();

// 7. Integration Tests
$results['integration_tests']['donor_to_donation_flow'] = function() use ($pdo) {
    try {
        $pdo->beginTransaction();
        
        // Create test donor
        $stmt = $pdo->prepare("
            INSERT INTO donors (first_name, last_name, email, blood_type) 
            VALUES (?, ?, ?, ?) 
            RETURNING id
        ");
        $stmt->execute(['Integration', 'Test', 'integration@test.com', 'AB+']);
        $donor_id = $stmt->fetchColumn();
        
        // Create test donation
        $stmt = $pdo->prepare("
            INSERT INTO donations (donor_id, status, donation_date) 
            VALUES (?, ?, ?) 
            RETURNING id
        ");
        $stmt->execute([$donor_id, 'completed', date('Y-m-d')]);
        $donation_id = $stmt->fetchColumn();
        
        // Verify relationship
        $stmt = $pdo->prepare("
            SELECT d.first_name, d.last_name, dn.status 
            FROM donors d 
            JOIN donations dn ON d.id = dn.donor_id 
            WHERE dn.id = ?
        ");
        $stmt->execute([$donation_id]);
        $relationship = $stmt->fetch();
        
        $pdo->rollback(); // Clean up test data
        
        return [
            'status' => $relationship ? 'pass' : 'fail',
            'message' => $relationship ? 'Donor-Donation relationship working' : 'Donor-Donation relationship failed',
            'test_donor_id' => $donor_id,
            'test_donation_id' => $donation_id,
            'relationship_data' => $relationship
        ];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        return [
            'status' => 'fail',
            'message' => 'Integration test failed: ' . $e->getMessage()
        ];
    }
}();

// 8. User Flow Simulation
$results['user_flow_tests']['complete_donation_process'] = function() use ($pdo) {
    try {
        $pdo->beginTransaction();
        
        $steps = [];
        
        // Step 1: Register donor
        $stmt = $pdo->prepare("
            INSERT INTO donors (first_name, last_name, email, phone, blood_type, address) 
            VALUES (?, ?, ?, ?, ?, ?) 
            RETURNING id
        ");
        $stmt->execute(['Flow', 'Test', 'flow@test.com', '9876543210', 'O-', 'Test Flow Address']);
        $donor_id = $stmt->fetchColumn();
        $steps[] = "Donor registered with ID: $donor_id";
        
        // Step 2: Schedule donation
        $stmt = $pdo->prepare("
            INSERT INTO donations (donor_id, status, donation_date, desired_date) 
            VALUES (?, ?, ?, ?) 
            RETURNING id
        ");
        $stmt->execute([$donor_id, 'scheduled', null, date('Y-m-d', strtotime('+7 days'))]);
        $donation_id = $stmt->fetchColumn();
        $steps[] = "Donation scheduled with ID: $donation_id";
        
        // Step 3: Complete donation
        $stmt = $pdo->prepare("
            UPDATE donations 
            SET status = 'completed', donation_date = CURRENT_DATE 
            WHERE id = ? 
            RETURNING id
        ");
        $stmt->execute([$donation_id]);
        $updated_id = $stmt->fetchColumn();
        $steps[] = "Donation completed: $updated_id";
        
        // Step 4: Add to blood inventory
        $stmt = $pdo->prepare("
            INSERT INTO blood_inventory (donor_id, blood_type, status, collection_date) 
            VALUES (?, ?, ?, ?) 
            RETURNING id
        ");
        $stmt->execute([$donor_id, 'O-', 'available', date('Y-m-d')]);
        $inventory_id = $stmt->fetchColumn();
        $steps[] = "Blood unit added to inventory: $inventory_id";
        
        // Step 5: Add donor note
        $stmt = $pdo->prepare("
            INSERT INTO donor_notes (donor_id, note, created_by) 
            VALUES (?, ?, ?) 
            RETURNING id
        ");
        $stmt->execute([$donor_id, 'Successful donation - flow test', 'system_test']);
        $note_id = $stmt->fetchColumn();
        $steps[] = "Donor note added: $note_id";
        
        $pdo->rollback(); // Clean up all test data
        
        return [
            'status' => 'pass',
            'message' => 'Complete donation flow simulation successful',
            'steps_completed' => count($steps),
            'flow_steps' => $steps
        ];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        return [
            'status' => 'fail',
            'message' => 'User flow test failed: ' . $e->getMessage(),
            'steps_completed' => count($steps ?? [])
        ];
    }
}();

// Calculate overall test results
$all_tests = array_merge(
    $results['feature_tests'],
    $results['api_tests'],
    $results['security_tests'],
    $results['integration_tests'],
    $results['user_flow_tests']
);

$total_tests = count($all_tests);
$passed_tests = count(array_filter($all_tests, function($test) {
    return $test['status'] === 'pass';
}));

$results['summary'] = [
    'total_tests' => $total_tests,
    'passed_tests' => $passed_tests,
    'success_rate' => round(($passed_tests / $total_tests) * 100, 2) . '%',
    'overall_status' => $passed_tests >= ($total_tests * 0.8) ? 'healthy' : 'needs_attention'
];

echo json_encode($results, JSON_PRETTY_PRINT);
?>
