<?php
// PostgreSQL Database Setup Script
// Run this ONCE on Render to create all tables

require_once 'db.php';

echo "<!DOCTYPE html><html><head><title>Database Setup</title></head><body>";
echo "<h1>Blood Donation System - Database Setup</h1>";
echo "<pre>";

try {
    // Create admin_users table
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
    echo "✓ admin_users table created\n\n";
    
    // Create donors table
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
    echo "✓ donors table created\n\n";
    
    // Create blood_units table
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
    echo "✓ blood_units table created\n\n";
    
    // Create notifications table
    echo "Creating notifications table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id SERIAL PRIMARY KEY,
        user_id INTEGER,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✓ notifications table created\n\n";
    
    // Insert default admin user
    echo "Creating default admin user...\n";
    $username = 'admin';
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $email = 'admin@blooddonation.com';
    $full_name = 'System Administrator';
    
    $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, email, full_name, role) 
                          VALUES (?, ?, ?, ?, 'super_admin') 
                          ON CONFLICT (username) DO NOTHING");
    $stmt->execute([$username, $password, $email, $full_name]);
    
    echo "✓ Admin user created\n";
    echo "  Username: admin\n";
    echo "  Password: admin123\n\n";
    
    echo "=====================================\n";
    echo "✅ DATABASE SETUP COMPLETE!\n";
    echo "=====================================\n\n";
    echo "You can now:\n";
    echo "1. Login to admin panel with username: admin, password: admin123\n";
    echo "2. Register donors\n";
    echo "3. Manage blood inventory\n\n";
    echo "⚠️ IMPORTANT: Delete this file after setup for security!\n";
    
} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='admin_login.php'>Go to Admin Login</a></p>";
echo "</body></html>";
?>