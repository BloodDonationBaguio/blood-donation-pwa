<?php
// Database Setup Script for Blood Donation System
// This script creates all necessary tables for the application

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'db.php';

echo "<h1>Blood Donation System - Database Setup</h1>";
echo "<p>Setting up database tables...</p>";

try {
    // Get the database driver name
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    // 1. Create users_new table
    echo "<h2>Creating users_new table...</h2>";
    if ($driver === 'sqlite') {
        $sql_users = "
        CREATE TABLE IF NOT EXISTS users_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role TEXT CHECK(role IN ('user', 'admin', 'super_admin')) DEFAULT 'user',
            status TEXT CHECK(status IN ('active', 'inactive', 'suspended')) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL
        )";
    } else {
        $sql_users = "
        CREATE TABLE IF NOT EXISTS users_new (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(50) DEFAULT 'user',
            status VARCHAR(50) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL
        )";
    }
    $pdo->exec($sql_users);
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_email ON users_new (email)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_role ON users_new (role)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_status ON users_new (status)");
    echo "✅ users_new table created successfully<br>";

    // 2. Create user_remember_tokens table
    echo "<h2>Creating user_remember_tokens table...</h2>";
    if ($driver === 'sqlite') {
        $sql_tokens = "
        CREATE TABLE IF NOT EXISTS user_remember_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users_new(id) ON DELETE CASCADE
        )";
    } else {
        $sql_tokens = "
        CREATE TABLE IF NOT EXISTS user_remember_tokens (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users_new(id) ON DELETE CASCADE
        )";
    }
    $pdo->exec($sql_tokens);
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_token ON user_remember_tokens (token)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_id ON user_remember_tokens (user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_expires ON user_remember_tokens (expires_at)");
    echo "✅ user_remember_tokens table created successfully<br>";

    // 3. Create donors_new table
    echo "<h2>Creating donors_new table...</h2>";
    if ($driver === 'sqlite') {
        $sql_donors = "
        CREATE TABLE IF NOT EXISTS donors_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            first_name VARCHAR(255) NOT NULL,
            last_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            blood_type TEXT CHECK(blood_type IN ('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown')) NOT NULL,
            date_of_birth DATE NOT NULL,
            gender TEXT CHECK(gender IN ('Male', 'Female')) NOT NULL,
            address TEXT NOT NULL,
            city VARCHAR(100) NOT NULL,
            province VARCHAR(100) NOT NULL,
            weight DECIMAL(5,2) NOT NULL,
            height DECIMAL(5,2) NOT NULL,
            reference_code VARCHAR(20) NOT NULL UNIQUE,
            status TEXT CHECK(status IN ('pending', 'approved', 'rejected', 'completed')) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
    } else {
        $sql_donors = "
        CREATE TABLE IF NOT EXISTS donors_new (
            id SERIAL PRIMARY KEY,
            first_name VARCHAR(255) NOT NULL,
            last_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            blood_type VARCHAR(10) NOT NULL,
            date_of_birth DATE NOT NULL,
            gender VARCHAR(10) NOT NULL,
            address TEXT NOT NULL,
            city VARCHAR(100) NOT NULL,
            province VARCHAR(100) NOT NULL,
            weight DECIMAL(5,2) NOT NULL,
            height DECIMAL(5,2) NOT NULL,
            reference_code VARCHAR(20) NOT NULL UNIQUE,
            status VARCHAR(20) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
    }
    $pdo->exec($sql_donors);
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_email_donors ON donors_new (email)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_reference ON donors_new (reference_code)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_status_donors ON donors_new (status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_blood_type ON donors_new (blood_type)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_created_at_donors ON donors_new (created_at)");
    echo "✅ donors_new table created successfully<br>";

    // 4. Create donor_medical_screening_simple table
    echo "<h2>Creating donor_medical_screening_simple table...</h2>";
    if ($driver === 'sqlite') {
        $sql_medical = "
        CREATE TABLE IF NOT EXISTS donor_medical_screening_simple (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            donor_id INTEGER NOT NULL,
            reference_code VARCHAR(20) NOT NULL,
            screening_data TEXT NOT NULL,
            all_questions_answered BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (donor_id) REFERENCES donors_new(id) ON DELETE CASCADE
        )";
    } else {
        $sql_medical = "
        CREATE TABLE IF NOT EXISTS donor_medical_screening_simple (
            id SERIAL PRIMARY KEY,
            donor_id INT NOT NULL,
            reference_code VARCHAR(20) NOT NULL,
            screening_data JSON NOT NULL,
            all_questions_answered BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (donor_id) REFERENCES donors_new(id) ON DELETE CASCADE
        )";
    }
    $pdo->exec($sql_medical);
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_donor_id_medical ON donor_medical_screening_simple (donor_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_reference_medical ON donor_medical_screening_simple (reference_code)");
    echo "✅ donor_medical_screening_simple table created successfully<br>";

    // 5. Create notifications table
    echo "<h2>Creating notifications table...</h2>";
    if ($driver === 'sqlite') {
        $sql_notifications = "
        CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            message TEXT NOT NULL,
            type TEXT CHECK(type IN ('info', 'success', 'warning', 'error')) DEFAULT 'info',
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users_new(id) ON DELETE CASCADE
        )";
    } else {
        $sql_notifications = "
        CREATE TABLE IF NOT EXISTS notifications (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(20) DEFAULT 'info',
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users_new(id) ON DELETE CASCADE
        )";
    }
    $pdo->exec($sql_notifications);
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_id_notifications ON notifications (user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_is_read ON notifications (is_read)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_created_at_notifications ON notifications (created_at)");
    echo "✅ notifications table created successfully<br>";

    // 6. Create blood_inventory table
    echo "<h2>Creating blood_inventory table...</h2>";
    if ($driver === 'sqlite') {
        $sql_blood_inventory = "
        CREATE TABLE IF NOT EXISTS blood_inventory (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            unit_id VARCHAR(50) UNIQUE NOT NULL,
            donor_id INTEGER NOT NULL,
            blood_type TEXT CHECK(blood_type IN ('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown')) NOT NULL,
            collection_date DATE NOT NULL,
            expiry_date DATE NOT NULL,
            status TEXT CHECK(status IN ('available', 'used', 'expired', 'quarantined')) DEFAULT 'available',
            collection_center VARCHAR(100) DEFAULT 'Main Center',
            collection_staff VARCHAR(100),
            test_results TEXT,
            location VARCHAR(100),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (donor_id) REFERENCES donors_new(id) ON DELETE CASCADE
        )";
    } else {
        $sql_blood_inventory = "
        CREATE TABLE IF NOT EXISTS blood_inventory (
            id SERIAL PRIMARY KEY,
            unit_id VARCHAR(50) UNIQUE NOT NULL,
            donor_id INT NOT NULL,
            blood_type VARCHAR(10) NOT NULL,
            collection_date DATE NOT NULL,
            expiry_date DATE NOT NULL,
            status VARCHAR(20) DEFAULT 'available',
            collection_center VARCHAR(100) DEFAULT 'Main Center',
            collection_staff VARCHAR(100),
            test_results JSON,
            location VARCHAR(100),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (donor_id) REFERENCES donors_new(id) ON DELETE CASCADE
        )";
    }
    $pdo->exec($sql_blood_inventory);
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_unit_id ON blood_inventory (unit_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_donor_id_inventory ON blood_inventory (donor_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_blood_type_inventory ON blood_inventory (blood_type)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_status_inventory ON blood_inventory (status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_collection_date ON blood_inventory (collection_date)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_expiry_date ON blood_inventory (expiry_date)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_created_at_inventory ON blood_inventory (created_at)");
    echo "✅ blood_inventory table created successfully<br>";

    // 7. Create blood_inventory_audit table
    echo "<h2>Creating blood_inventory_audit table...</h2>";
    if ($driver === 'sqlite') {
        $sql_blood_inventory_audit = "
        CREATE TABLE IF NOT EXISTS blood_inventory_audit (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            unit_id INTEGER NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            description TEXT NOT NULL,
            details TEXT,
            admin_username VARCHAR(100),
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (unit_id) REFERENCES blood_inventory(id) ON DELETE CASCADE
        )";
    } else {
        $sql_blood_inventory_audit = "
        CREATE TABLE IF NOT EXISTS blood_inventory_audit (
            id SERIAL PRIMARY KEY,
            unit_id INT NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            description TEXT NOT NULL,
            details JSON,
            admin_username VARCHAR(100),
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (unit_id) REFERENCES blood_inventory(id) ON DELETE CASCADE
        )";
    }
    $pdo->exec($sql_blood_inventory_audit);
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_unit_id_audit ON blood_inventory_audit (unit_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_action_type_audit ON blood_inventory_audit (action_type)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_admin_username_audit ON blood_inventory_audit (admin_username)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_created_at_audit ON blood_inventory_audit (created_at)");
    echo "✅ blood_inventory_audit table created successfully<br>";

    // 8. Create blood_requests_inventory table
    echo "<h2>Creating blood_requests_inventory table...</h2>";
    if ($driver === 'sqlite') {
        $sql_blood_requests_inventory = "
        CREATE TABLE IF NOT EXISTS blood_requests_inventory (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            request_id INTEGER,
            unit_id INTEGER NOT NULL,
            blood_type VARCHAR(10) NOT NULL,
            quantity INTEGER DEFAULT 1,
            issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            issued_by VARCHAR(100),
            notes TEXT,
            FOREIGN KEY (unit_id) REFERENCES blood_inventory(id) ON DELETE CASCADE
        )";
    } else {
        $sql_blood_requests_inventory = "
        CREATE TABLE IF NOT EXISTS blood_requests_inventory (
            id SERIAL PRIMARY KEY,
            request_id INT,
            unit_id INT NOT NULL,
            blood_type VARCHAR(10) NOT NULL,
            quantity INT DEFAULT 1,
            issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            issued_by VARCHAR(100),
            notes TEXT,
            FOREIGN KEY (unit_id) REFERENCES blood_inventory(id) ON DELETE CASCADE
        )";
    }
    $pdo->exec($sql_blood_requests_inventory);
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_request_id_req ON blood_requests_inventory (request_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_unit_id_req ON blood_requests_inventory (unit_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_blood_type_req ON blood_requests_inventory (blood_type)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_issued_date_req ON blood_requests_inventory (issued_date)");
    echo "✅ blood_requests_inventory table created successfully<br>";

    // 9. Check table structure and add missing columns if needed
    echo "<h2>Checking table structure...</h2>";

    // Get current table structure
    if ($driver === 'sqlite') {
        $stmt = $pdo->query("PRAGMA table_info(users_new)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $existingColumns = array_column($columns, 'name');
    } else {
        $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'users_new'");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $existingColumns = array_column($columns, 'column_name');
    }

    // Add missing columns
    if (!in_array('name', $existingColumns)) {
        $pdo->exec("ALTER TABLE users_new ADD COLUMN name VARCHAR(255) NOT NULL");
        echo "✅ Added 'name' column<br>";
    }

    if (!in_array('role', $existingColumns)) {
        if ($driver === 'sqlite') {
            $pdo->exec("ALTER TABLE users_new ADD COLUMN role TEXT CHECK(role IN ('user', 'admin', 'super_admin')) DEFAULT 'user'");
        } else {
            $pdo->exec("ALTER TABLE users_new ADD COLUMN role VARCHAR(50) DEFAULT 'user'");
        }
        echo "✅ Added 'role' column<br>";
    }

    if (!in_array('status', $existingColumns)) {
        if ($driver === 'sqlite') {
            $pdo->exec("ALTER TABLE users_new ADD COLUMN status TEXT CHECK(status IN ('active', 'inactive', 'suspended')) DEFAULT 'active'");
        } else {
            $pdo->exec("ALTER TABLE users_new ADD COLUMN status VARCHAR(50) DEFAULT 'active'");
        }
        echo "✅ Added 'status' column<br>";
    }

    if (!in_array('created_at', $existingColumns)) {
        $pdo->exec("ALTER TABLE users_new ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "✅ Added 'created_at' column<br>";
    }

    if (!in_array('updated_at', $existingColumns)) {
        $pdo->exec("ALTER TABLE users_new ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "✅ Added 'updated_at' column<br>";
    }

    if (!in_array('last_login', $existingColumns)) {
        $pdo->exec("ALTER TABLE users_new ADD COLUMN last_login TIMESTAMP NULL");
        echo "✅ Added 'last_login' column<br>";
    }

    // 7. Create a default admin user
    echo "<h2>Creating default admin user...</h2>";

    // Check if admin user already exists
    $stmt = $pdo->prepare("SELECT id FROM users_new WHERE email = ?");
    $stmt->execute(['admin@blooddonation.com']);

    if ($stmt->rowCount() == 0) {
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users_new (name, email, password, role, status) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'System Administrator',
            'admin@blooddonation.com',
            $admin_password,
            'super_admin',
            'active'
        ]);
        echo "✅ Default admin user created successfully<br>";
        echo "<p><strong>Admin Login:</strong><br>";
        echo "Email: admin@blooddonation.com<br>";
        echo "Password: admin123</p>";
    } else {
        echo "✅ Admin user already exists<br>";
    }

    // 7. Create a test regular user
    echo "<h2>Creating test user...</h2>";

    $stmt = $pdo->prepare("SELECT id FROM users_new WHERE email = ?");
    $stmt->execute(['test@example.com']);

    if ($stmt->rowCount() == 0) {
        $user_password = password_hash('test123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users_new (name, email, password, role, status) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'Test User',
            'test@example.com',
            $user_password,
            'user',
            'active'
        ]);
        echo "✅ Test user created successfully<br>";
        echo "<p><strong>Test User Login:</strong><br>";
        echo "Email: test@example.com<br>";
        echo "Password: test123</p>";
    } else {
        echo "✅ Test user already exists<br>";
    }

    echo "<h2>Database Setup Complete! 🎉</h2>";
    echo "<p>All tables have been created successfully. You can now:</p>";
    echo "<ul>";
    echo "<li><a href='login.php'>Login to the system</a></li>";
    echo "<li><a href='profile.php'>Access your profile</a></li>";
    echo "<li><a href='donor-registration.php'>Register as a donor</a></li>";
    echo "<li><a href='admin.php'>Access admin panel</a></li>";
    echo "</ul>";
    
    echo "<h3>Default Login Credentials:</h3>";
    echo "<p><strong>Admin:</strong> admin@blooddonation.com / admin123</p>";
    echo "<p><strong>Test User:</strong> test@example.com / test123</p>";
    
} catch (PDOException $e) {
    echo "<h2>❌ Database Setup Failed</h2>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database connection settings in <code>db.php</code></p>";
} catch (Exception $e) {
    echo "<h2>❌ Setup Failed</h2>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
