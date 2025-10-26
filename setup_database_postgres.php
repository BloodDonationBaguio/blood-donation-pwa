<?php
// PostgreSQL Database Setup Script
require_once 'db.php';

echo "<!DOCTYPE html><html><head><title>Database Setup</title></head><body>";
echo "<h1>Blood Donation System - Database Setup</h1><pre>";

try {
    echo "Creating admin_users table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        role VARCHAR(20) DEFAULT 'admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✓ admin_users created\n\n";
    
    echo "Creating donors table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS donors (
        id SERIAL PRIMARY KEY,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        phone VARCHAR(20),
        blood_type VARCHAR(5),
        date_of_birth DATE,
        gender VARCHAR(10),
        address TEXT,
        city VARCHAR(50),
        province VARCHAR(50),
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✓ donors created\n\n";
    
    echo "Creating blood_units table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS blood_units (
        id SERIAL PRIMARY KEY,
        donor_id INTEGER REFERENCES donors(id) ON DELETE CASCADE,
        blood_type VARCHAR(5) NOT NULL,
        donation_date DATE NOT NULL,
        expiry_date DATE,
        status VARCHAR(20) DEFAULT 'available',
        volume_ml INTEGER DEFAULT 450,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✓ blood_units created\n\n";
    
    echo "Creating notifications table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id SERIAL PRIMARY KEY,
        user_id INTEGER,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✓ notifications created\n\n";
    
    echo "Creating admin user...\n";
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, email, full_name, role) 
                          VALUES (?, ?, ?, ?, 'super_admin') 
                          ON CONFLICT (username) DO NOTHING");
    $stmt->execute(['admin', $password, 'admin@blooddonation.com', 'System Administrator']);
    
    echo "✓ Admin user created\n";
    echo "  Username: admin\n  Password: admin123\n\n";
    echo "=====================================\n";
    echo "✅ DATABASE SETUP COMPLETE!\n";
    echo "=====================================\n\n";
    echo "Login at: <a href='admin_login.php'>Admin Login</a>\n";
    echo "⚠️ Delete this file after setup!\n";
    
} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "</pre></body></html>";
?>
