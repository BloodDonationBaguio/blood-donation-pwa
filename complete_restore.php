<?php
// Complete System Restore Script
// This will reset the entire system and populate with sample data

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. Drop and recreate database
    $pdo->exec("DROP DATABASE IF EXISTS blood_system");
    $pdo->exec("CREATE DATABASE blood_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE blood_system");
    
    // 2. Create tables
    $tables = [
        "CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(120) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            blood_type VARCHAR(5),
            phone VARCHAR(20),
            address TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            hashed_password VARCHAR(255) NOT NULL,
            role VARCHAR(20) DEFAULT 'super_admin',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE donors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            blood_type VARCHAR(5) NOT NULL,
            last_donation_date DATE,
            is_available BOOLEAN DEFAULT TRUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            full_name VARCHAR(100) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            email VARCHAR(120),
            blood_type_needed VARCHAR(5) NOT NULL,
            city VARCHAR(100) NOT NULL,
            hospital VARCHAR(255),
            reason TEXT NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            reference VARCHAR(32) UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE donations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            donor_id INT NOT NULL,
            recipient_id INT,
            donation_date DATE NOT NULL,
            blood_type VARCHAR(5) NOT NULL,
            units DECIMAL(5,2) NOT NULL,
            status VARCHAR(20) DEFAULT 'completed',
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            is_read BOOLEAN DEFAULT FALSE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
    
    // 3. Create admin account
    $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO admins (username, hashed_password) VALUES ('admin', '$password_hash')");
    
    // 4. Add sample users and donors
    $sample_users = [
        ["John Doe", "john@example.com", "A+", "1234567890", "123 Main St"],
        ["Jane Smith", "jane@example.com", "B-", "0987654321", "456 Oak Ave"],
        ["Mike Johnson", "mike@example.com", "O+", "1122334455", "789 Pine Rd"],
        ["Sarah Williams", "sarah@example.com", "AB+", "5566778899", "321 Elm St"]
    ];
    
    foreach ($sample_users as $user) {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, blood_type, phone, address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user[0], $user[1], $user[2], $user[3], $user[4]]);
        $user_id = $pdo->lastInsertId();
        
        // Make them donors
        $pdo->exec("INSERT INTO donors (user_id, blood_type, last_donation_date) VALUES ($user_id, '{$user[2]}', DATE_SUB(CURDATE(), INTERVAL 2 MONTH))");
    }
    
    // 5. Add sample blood requests
    $sample_requests = [
        [1, "John Doe", "1234567890", "john@example.com", "O+", "New York", "Emergency surgery"],
        [null, "Robert Brown", "3344556677", "robert@example.com", "B+", "Los Angeles", "Regular transfusion"],
        [3, "Mike Johnson", "1122334455", "mike@example.com", "A-", "Chicago", "Accident victim"]
    ];
    
    foreach ($sample_requests as $req) {
        $user_id = $req[0] ?: 'NULL';
        $stmt = $pdo->prepare("INSERT INTO requests (user_id, full_name, phone, email, blood_type_needed, city, reason, reference) 
                              VALUES ($user_id, ?, ?, ?, ?, ?, ?, ?)");
        $ref = strtoupper(uniqid('REQ'));
        $stmt->execute([$req[1], $req[2], $req[3], $req[4], $req[5], $req[6], $ref]);
    }
    
    // 6. Add sample donations
    $pdo->exec("INSERT INTO donations (donor_id, donation_date, blood_type, units, notes) 
               VALUES (2, DATE_SUB(CURDATE(), INTERVAL 1 MONTH), 'B-', 1, 'Regular donation')");
    $pdo->exec("INSERT INTO donations (donor_id, recipient_id, donation_date, blood_type, units, status, notes) 
               VALUES (3, 1, DATE_SUB(CURDATE(), INTERVAL 15 DAY), 'O+', 2, 'completed', 'Emergency donation')");
    
    // 7. Add sample notifications
    $pdo->exec("INSERT INTO notifications (user_id, title, message) 
               VALUES (1, 'Welcome!', 'Your account has been created successfully.')");
    $pdo->exec("INSERT INTO notifications (user_id, title, message) 
               VALUES (1, 'Donation Approved', 'Your blood donation request has been approved.')");
    
    // 8. Output success message
    echo "<h1>System Restore Complete!</h1>";
    echo "<h3>Admin Login Details:</h3>";
    echo "<p><strong>URL:</strong> <a href='admin_login.php'>Admin Login</a></p>";
    echo "<p><strong>Username:</strong> admin</p>";
    echo "<p><strong>Password:</strong> admin123</p>";
    
    echo "<h3>Sample Data Added:</h3>";
    echo "<ul>";
    echo "<li>4 Sample Users/Donors</li>";
    echo "<li>3 Sample Blood Requests</li>";
    echo "<li>2 Sample Donations</li>";
    echo "<li>2 Sample Notifications</li>";
    echo "</ul>";
    
    echo "<p><a href='index.php'>Go to Homepage</a> | ";
    echo "<a href='admin.php'>Go to Admin Panel</a></p>";
    
} catch (PDOException $e) {
    die("<h2>Error during restoration:</h2><p>" . $e->getMessage() . "</p>");
}
?>
