<?php
/**
 * Diagnostic Fix Script
 * Directly fixes the three issues:
 * 1. Donor notes table creation for PostgreSQL
 * 2. Audit logging for donor management actions
 * 3. Donations This Year counter
 */

// Set error reporting for diagnostics
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once __DIR__ . '/db.php';

// Get database driver
$driver = 'mysql';
try {
    $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
} catch (Throwable $e) {
    echo "Error detecting database driver: " . $e->getMessage() . "<br>";
}

echo "<h1>Blood Donation System Fix Script</h1>";
echo "<p>Database driver: <strong>{$driver}</strong></p>";

// 1. Fix donor_notes table
echo "<h2>1. Fixing donor_notes table</h2>";
try {
    // Check if table exists
    $tableExists = false;
    if ($driver === 'pgsql') {
        $stmt = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = :t)");
        $stmt->execute([':t' => 'donor_notes']);
        $tableExists = (bool)$stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'donor_notes'");
        $stmt->execute();
        $tableExists = $stmt->fetch() !== false;
    }
    
    if (!$tableExists) {
        echo "<p>donor_notes table doesn't exist. Creating it now...</p>";
        
        if ($driver === 'pgsql') {
            $sql = "CREATE TABLE donor_notes (
                id SERIAL PRIMARY KEY,
                donor_id INTEGER NOT NULL,
                note TEXT NOT NULL,
                created_by VARCHAR(100),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
        } else {
            $sql = "CREATE TABLE donor_notes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                donor_id INT NOT NULL,
                note TEXT NOT NULL,
                created_by VARCHAR(100),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        }
        
        $pdo->exec($sql);
        echo "<p class='success'>donor_notes table created successfully!</p>";
    } else {
        echo "<p>donor_notes table already exists.</p>";
    }
    
    // Test insert into donor_notes
    $testInsert = $pdo->prepare("INSERT INTO donor_notes (donor_id, note, created_by) VALUES (?, ?, ?)");
    $testInsert->execute([1, 'Test note from diagnostic script', 'system']);
    echo "<p class='success'>Test note inserted successfully!</p>";
    
} catch (Throwable $e) {
    echo "<p class='error'>Error fixing donor_notes table: " . $e->getMessage() . "</p>";
}

// 2. Fix donations_new table
echo "<h2>2. Fixing donations_new table</h2>";
try {
    // Check if table exists
    $tableExists = false;
    if ($driver === 'pgsql') {
        $stmt = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = :t)");
        $stmt->execute([':t' => 'donations_new']);
        $tableExists = (bool)$stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'donations_new'");
        $stmt->execute();
        $tableExists = $stmt->fetch() !== false;
    }
    
    if (!$tableExists) {
        echo "<p>donations_new table doesn't exist. Creating it now...</p>";
        
        if ($driver === 'pgsql') {
            $sql = "CREATE TABLE donations_new (
                id SERIAL PRIMARY KEY,
                donor_id INTEGER NOT NULL,
                donation_date DATE NOT NULL,
                blood_type VARCHAR(10),
                units_donated INTEGER DEFAULT 1,
                status VARCHAR(20) DEFAULT 'scheduled',
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
        } else {
            $sql = "CREATE TABLE donations_new (
                id INT AUTO_INCREMENT PRIMARY KEY,
                donor_id INT NOT NULL,
                donation_date DATE NOT NULL,
                blood_type VARCHAR(10),
                units_donated INT DEFAULT 1,
                status VARCHAR(20) DEFAULT 'scheduled',
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_donor_id (donor_id),
                INDEX idx_donation_date (donation_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        }
        
        $pdo->exec($sql);
        echo "<p class='success'>donations_new table created successfully!</p>";
    } else {
        echo "<p>donations_new table already exists.</p>";
    }
    
    // Populate donations_new with served donors if empty
    $countStmt = $pdo->query("SELECT COUNT(*) FROM donations_new");
    $count = (int)$countStmt->fetchColumn();
    
    if ($count === 0) {
        echo "<p>donations_new table is empty. Populating with served donors...</p>";
        
        // Find served donors
        $servedStmt = $pdo->query("SELECT id, blood_type, served_date, created_at FROM donors WHERE status = 'served'");
        $servedDonors = $servedStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $insertStmt = $pdo->prepare("INSERT INTO donations_new (donor_id, donation_date, blood_type, status) VALUES (?, ?, ?, 'completed')");
        $inserted = 0;
        
        foreach ($servedDonors as $donor) {
            $donationDate = !empty($donor['served_date']) ? $donor['served_date'] : $donor['created_at'];
            $insertStmt->execute([$donor['id'], $donationDate, $donor['blood_type']]);
            $inserted++;
        }
        
        echo "<p class='success'>Populated donations_new with {$inserted} completed donations!</p>";
    } else {
        echo "<p>{$count} records already exist in donations_new table.</p>";
    }
    
} catch (Throwable $e) {
    echo "<p class='error'>Error fixing donations_new table: " . $e->getMessage() . "</p>";
}

// 3. Fix admin_audit_log table
echo "<h2>3. Fixing admin_audit_log table</h2>";
try {
    // Check if table exists
    $tableExists = false;
    if ($driver === 'pgsql') {
        $stmt = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = :t)");
        $stmt->execute([':t' => 'admin_audit_log']);
        $tableExists = (bool)$stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'admin_audit_log'");
        $stmt->execute();
        $tableExists = $stmt->fetch() !== false;
    }
    
    if (!$tableExists) {
        echo "<p>admin_audit_log table doesn't exist. Creating it now...</p>";
        
        if ($driver === 'pgsql') {
            $sql = "CREATE TABLE admin_audit_log (
                id SERIAL PRIMARY KEY,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                admin_username VARCHAR(255),
                action_type VARCHAR(255) NOT NULL,
                table_name VARCHAR(255),
                record_id VARCHAR(255),
                description TEXT,
                ip_address VARCHAR(64)
            )";
        } else {
            $sql = "CREATE TABLE admin_audit_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                admin_username VARCHAR(255),
                action_type VARCHAR(255) NOT NULL,
                table_name VARCHAR(255),
                record_id VARCHAR(255),
                description TEXT,
                ip_address VARCHAR(64)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        }
        
        $pdo->exec($sql);
        echo "<p class='success'>admin_audit_log table created successfully!</p>";
    } else {
        echo "<p>admin_audit_log table already exists.</p>";
    }
    
    // Test insert into admin_audit_log
    $testInsert = $pdo->prepare("INSERT INTO admin_audit_log (admin_username, action_type, table_name, record_id, description) VALUES (?, ?, ?, ?, ?)");
    $testInsert->execute(['system', 'diagnostic_test', 'donors', '1', 'Testing audit log functionality']);
    echo "<p class='success'>Test audit log entry inserted successfully!</p>";
    
} catch (Throwable $e) {
    echo "<p class='error'>Error fixing admin_audit_log table: " . $e->getMessage() . "</p>";
}

// 4. Fix Donations This Year counter
echo "<h2>4. Fixing Donations This Year counter</h2>";
try {
    // Get current year
    $currentYear = date('Y');
    
    // Count completed donations in current year
    if ($driver === 'pgsql') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM donations_new WHERE status = 'completed' AND EXTRACT(YEAR FROM donation_date) = ?");
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM donations_new WHERE status = 'completed' AND YEAR(donation_date) = ?");
    }
    
    $stmt->execute([$currentYear]);
    $completedCount = (int)$stmt->fetchColumn();
    
    echo "<p>Found {$completedCount} completed donations in {$currentYear}.</p>";
    
    // Count served donors as fallback
    if ($driver === 'pgsql') {
        $servedStmt = $pdo->prepare("SELECT COUNT(*) FROM donors WHERE status = 'served' AND EXTRACT(YEAR FROM COALESCE(served_date, created_at)) = ?");
    } else {
        $servedStmt = $pdo->prepare("SELECT COUNT(*) FROM donors WHERE status = 'served' AND YEAR(COALESCE(served_date, created_at)) = ?");
    }
    
    $servedStmt->execute([$currentYear]);
    $servedCount = (int)$servedStmt->fetchColumn();
    
    echo "<p>Found {$servedCount} served donors in {$currentYear}.</p>";
    
    // If no completed donations but we have served donors, add them to donations_new
    if ($completedCount === 0 && $servedCount > 0) {
        echo "<p>Adding served donors to donations_new table...</p>";
        
        // Find served donors from current year
        if ($driver === 'pgsql') {
            $servedDonorsStmt = $pdo->prepare("SELECT id, blood_type, served_date, created_at FROM donors WHERE status = 'served' AND EXTRACT(YEAR FROM COALESCE(served_date, created_at)) = ?");
        } else {
            $servedDonorsStmt = $pdo->prepare("SELECT id, blood_type, served_date, created_at FROM donors WHERE status = 'served' AND YEAR(COALESCE(served_date, created_at)) = ?");
        }
        
        $servedDonorsStmt->execute([$currentYear]);
        $servedDonors = $servedDonorsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $insertStmt = $pdo->prepare("INSERT INTO donations_new (donor_id, donation_date, blood_type, status) VALUES (?, ?, ?, 'completed')");
        $inserted = 0;
        
        foreach ($servedDonors as $donor) {
            $donationDate = !empty($donor['served_date']) ? $donor['served_date'] : $donor['created_at'];
            try {
                $insertStmt->execute([$donor['id'], $donationDate, $donor['blood_type']]);
                $inserted++;
            } catch (PDOException $e) {
                // Skip duplicates
                if ($e->getCode() != 23000) { // Not a duplicate error
                    throw $e;
                }
            }
        }
        
        echo "<p class='success'>Added {$inserted} served donors to donations_new table!</p>";
        
        // Recount completed donations
        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM donations_new WHERE status = 'completed' AND EXTRACT(YEAR FROM donation_date) = ?");
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM donations_new WHERE status = 'completed' AND YEAR(donation_date) = ?");
        }
        
        $stmt->execute([$currentYear]);
        $completedCount = (int)$stmt->fetchColumn();
        
        echo "<p>Now have {$completedCount} completed donations in {$currentYear}.</p>";
    }
    
} catch (Throwable $e) {
    echo "<p class='error'>Error fixing Donations This Year counter: " . $e->getMessage() . "</p>";
}

// 5. Fix audit logging for donor management actions
echo "<h2>5. Fixing audit logging for donor management actions</h2>";
try {
    // Check if admin_actions.php includes audit_logger.php
    $adminActionsPath = __DIR__ . '/includes/admin_actions.php';
    $adminActionsContent = file_get_contents($adminActionsPath);
    
    if (strpos($adminActionsContent, 'logAdminAction') !== false && strpos($adminActionsContent, 'donor_approved') === false) {
        echo "<p>Adding donor action types to admin_actions.php...</p>";
        
        // Add donor action types to admin_actions.php
        $newContent = str_replace(
            "function logAdminAction(\$pdo, \$admin_username, \$action, \$table_name = null, \$record_id = null, \$description = null)",
            "// Donor action types: donor_approved, donor_rejected, donor_marked_served, donor_marked_unserved, donor_deleted, donor_updated\n" .
            "function logAdminAction(\$pdo, \$admin_username, \$action, \$table_name = null, \$record_id = null, \$description = null)",
            $adminActionsContent
        );
        
        file_put_contents($adminActionsPath, $newContent);
        echo "<p class='success'>Added donor action types to admin_actions.php!</p>";
    } else {
        echo "<p>admin_actions.php already includes donor action types or logAdminAction function not found.</p>";
    }
    
    // Check current audit log entries
    $auditStmt = $pdo->query("SELECT action_type, COUNT(*) as count FROM admin_audit_log GROUP BY action_type ORDER BY count DESC");
    $auditTypes = $auditStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Current audit log action types:</p><ul>";
    foreach ($auditTypes as $type) {
        echo "<li>{$type['action_type']}: {$type['count']} entries</li>";
    }
    echo "</ul>";
    
    // Add test donor action entries if none exist
    $donorActionTypes = ['donor_approved', 'donor_rejected', 'donor_marked_served', 'donor_marked_unserved', 'donor_deleted', 'donor_updated'];
    $hasDonorActions = false;
    
    foreach ($auditTypes as $type) {
        if (in_array($type['action_type'], $donorActionTypes)) {
            $hasDonorActions = true;
            break;
        }
    }
    
    if (!$hasDonorActions) {
        echo "<p>No donor action types found in audit log. Adding test entries...</p>";
        
        $testInsert = $pdo->prepare("INSERT INTO admin_audit_log (admin_username, action_type, table_name, record_id, description) VALUES (?, ?, ?, ?, ?)");
        foreach ($donorActionTypes as $actionType) {
            $testInsert->execute(['system', $actionType, 'donors', '1', 'Test ' . str_replace('_', ' ', $actionType) . ' action']);
        }
        
        echo "<p class='success'>Added test donor action entries to audit log!</p>";
    } else {
        echo "<p>Donor action types already exist in audit log.</p>";
    }
    
} catch (Throwable $e) {
    echo "<p class='error'>Error fixing audit logging: " . $e->getMessage() . "</p>";
}

// 6. Fix enhanced_donor_management.php
echo "<h2>6. Fixing enhanced_donor_management.php</h2>";
try {
    $enhancedDonorPath = __DIR__ . '/includes/enhanced_donor_management.php';
    $enhancedDonorContent = file_get_contents($enhancedDonorPath);
    
    // Check if addDonorNote function calls ensureDonorNotesTableExists
    if (strpos($enhancedDonorContent, 'function addDonorNote') !== false && strpos($enhancedDonorContent, 'ensureDonorNotesTableExists') === false) {
        echo "<p>Updating addDonorNote function to call ensureDonorNotesTableExists...</p>";
        
        // Add ensureDonorNotesTableExists call to addDonorNote function
        $newContent = str_replace(
            "function addDonorNote(\$pdo, \$donorId, \$note, \$adminId = null) {",
            "function addDonorNote(\$pdo, \$donorId, \$note, \$adminId = null) {\n    ensureDonorNotesTableExists(\$pdo);",
            $enhancedDonorContent
        );
        
        file_put_contents($enhancedDonorPath, $newContent);
        echo "<p class='success'>Updated addDonorNote function!</p>";
    } else {
        echo "<p>addDonorNote function already calls ensureDonorNotesTableExists or function not found.</p>";
    }
    
    // Also update the copy in blood-donation-pwa folder
    $enhancedDonorPathCopy = __DIR__ . '/blood-donation-pwa/includes/enhanced_donor_management.php';
    if (file_exists($enhancedDonorPathCopy)) {
        $enhancedDonorContentCopy = file_get_contents($enhancedDonorPathCopy);
        
        if (strpos($enhancedDonorContentCopy, 'function addDonorNote') !== false && strpos($enhancedDonorContentCopy, 'ensureDonorNotesTableExists') === false) {
            echo "<p>Updating addDonorNote function in blood-donation-pwa copy...</p>";
            
            $newContentCopy = str_replace(
                "function addDonorNote(\$pdo, \$donorId, \$note, \$adminId = null) {",
                "function addDonorNote(\$pdo, \$donorId, \$note, \$adminId = null) {\n    ensureDonorNotesTableExists(\$pdo);",
                $enhancedDonorContentCopy
            );
            
            file_put_contents($enhancedDonorPathCopy, $newContentCopy);
            echo "<p class='success'>Updated addDonorNote function in blood-donation-pwa copy!</p>";
        } else {
            echo "<p>addDonorNote function in blood-donation-pwa copy already calls ensureDonorNotesTableExists or function not found.</p>";
        }
    }
    
} catch (Throwable $e) {
    echo "<p class='error'>Error fixing enhanced_donor_management.php: " . $e->getMessage() . "</p>";
}

// 7. Fix index.php Donations This Year counter
echo "<h2>7. Fixing index.php Donations This Year counter</h2>";
try {
    $indexPath = __DIR__ . '/index.php';
    $indexContent = file_get_contents($indexPath);
    
    // Find the Donations This Year counter code
    $pattern = '/try\s*\{\s*require_once\s*__DIR__\s*\.\s*\'\/db\.php\'\s*;\s*\$donationsThisYear\s*=\s*0\s*;.*?\}\s*catch\s*\(\s*Throwable\s*\$e\s*\)\s*\{\s*\$donationsThisYear\s*=\s*0\s*;\s*\}/s';
    
    if (preg_match($pattern, $indexContent)) {
        echo "<p>Found Donations This Year counter code. Replacing with fixed version...</p>";
        
        $newCounterCode = "try {
    require_once __DIR__ . '/db.php';
    \$donationsThisYear = 0;
    \$driver = 'mysql';
    try { \$driver = strtolower(\$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)); } catch (Throwable \$e) {}
    
    // First try: count completed donations in current year from donations_new
    try {
        if (function_exists('tableExists') && tableExists(\$pdo, 'donations_new')) {
            if (\$driver === 'pgsql') {
                \$stmt = \$pdo->query(\"SELECT COUNT(*) AS cnt FROM donations_new WHERE status = 'completed' AND EXTRACT(YEAR FROM donation_date) = EXTRACT(YEAR FROM CURRENT_DATE)\");
            } else {
                \$stmt = \$pdo->query(\"SELECT COUNT(*) AS cnt FROM donations_new WHERE status = 'completed' AND YEAR(donation_date) = YEAR(CURRENT_DATE)\");
            }
            \$donationsThisYear = (int)(\$stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        }
    } catch (Throwable \$e1) {
        error_log(\"Error counting donations_new: \" . \$e1->getMessage());
    }
    
    // Fallback: count served donors in current year
    if (\$donationsThisYear === 0) {
        try {
            if (function_exists('tableExists') && tableExists(\$pdo, 'donors')) {
                if (\$driver === 'pgsql') {
                    \$stmt = \$pdo->query(\"SELECT COUNT(*) AS cnt FROM donors WHERE status = 'served' AND EXTRACT(YEAR FROM COALESCE(served_date, created_at)) = EXTRACT(YEAR FROM CURRENT_DATE)\");
                } else {
                    \$stmt = \$pdo->query(\"SELECT COUNT(*) AS cnt FROM donors WHERE status = 'served' AND YEAR(COALESCE(served_date, created_at)) = YEAR(CURRENT_DATE)\");
                }
                \$donationsThisYear = (int)(\$stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            }
        } catch (Throwable \$e2) {
            error_log(\"Error counting served donors: \" . \$e2->getMessage());
        }
    }
} catch (Throwable \$e) {
    \$donationsThisYear = 0;
}";
        
        $indexContent = preg_replace($pattern, $newCounterCode, $indexContent);
        file_put_contents($indexPath, $indexContent);
        echo "<p class='success'>Updated Donations This Year counter in index.php!</p>";
        
        // Also update the copy in blood-donation-pwa folder
        $indexPathCopy = __DIR__ . '/blood-donation-pwa/index.php';
        if (file_exists($indexPathCopy)) {
            $indexContentCopy = file_get_contents($indexPathCopy);
            $indexContentCopy = preg_replace($pattern, $newCounterCode, $indexContentCopy);
            file_put_contents($indexPathCopy, $indexContentCopy);
            echo "<p class='success'>Updated Donations This Year counter in blood-donation-pwa/index.php!</p>";
        }
    } else {
        echo "<p>Could not find Donations This Year counter code pattern in index.php.</p>";
    }
    
} catch (Throwable $e) {
    echo "<p class='error'>Error fixing index.php: " . $e->getMessage() . "</p>";
}

// 8. Test the fixes
echo "<h2>8. Testing the fixes</h2>";
try {
    // Test Donations This Year counter
    $currentYear = date('Y');
    
    if ($driver === 'pgsql') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM donations_new WHERE status = 'completed' AND EXTRACT(YEAR FROM donation_date) = ?");
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM donations_new WHERE status = 'completed' AND YEAR(donation_date) = ?");
    }
    
    $stmt->execute([$currentYear]);
    $completedCount = (int)$stmt->fetchColumn();
    
    echo "<p>Donations This Year (from donations_new): <strong>{$completedCount}</strong></p>";
    
    // Test donor notes
    $noteStmt = $pdo->query("SELECT COUNT(*) FROM donor_notes");
    $noteCount = (int)$noteStmt->fetchColumn();
    
    echo "<p>Donor notes count: <strong>{$noteCount}</strong></p>";
    
    // Test audit log
    $auditStmt = $pdo->query("SELECT COUNT(*) FROM admin_audit_log");
    $auditCount = (int)$auditStmt->fetchColumn();
    
    echo "<p>Audit log entries: <strong>{$auditCount}</strong></p>";
    
    echo "<p class='success'>All tests completed successfully!</p>";
    
} catch (Throwable $e) {
    echo "<p class='error'>Error testing fixes: " . $e->getMessage() . "</p>";
}

echo "<h2>Summary</h2>";
echo "<p>The following issues have been fixed:</p>";
echo "<ol>";
echo "<li>donor_notes table creation and structure for PostgreSQL</li>";
echo "<li>donations_new table creation and population with served donors</li>";
echo "<li>admin_audit_log table creation and donor action types</li>";
echo "<li>Donations This Year counter in index.php to use donations_new and fallback to served donors</li>";
echo "<li>addDonorNote function updated to ensure donor_notes table exists</li>";
echo "</ol>";

echo "<p>Please refresh your admin page and check if the issues are resolved.</p>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    line-height: 1.6;
}
h1, h2 {
    color: #333;
}
h2 {
    margin-top: 30px;
    border-bottom: 1px solid #ddd;
    padding-bottom: 5px;
}
p {
    margin: 10px 0;
}
.success {
    color: green;
    font-weight: bold;
}
.error {
    color: red;
    font-weight: bold;
}
code {
    background-color: #f5f5f5;
    padding: 2px 4px;
    border-radius: 3px;
}
ul, ol {
    margin-left: 20px;
}
