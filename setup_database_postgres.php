<?php
// PostgreSQL Database Setup Script
// Prefer direct PostgreSQL connection via DATABASE_URL if available; otherwise, fall back to app's db.php
if (getenv('DATABASE_URL')) {
    $database_url = getenv('DATABASE_URL');
    $db = parse_url($database_url);
    $dbHost = $db['host'] ?? 'localhost';
    $dbName = ltrim($db['path'] ?? '', '/');
    $dbUser = $db['user'] ?? '';
    $dbPass = $db['pass'] ?? '';
    $dbPort = isset($db['port']) ? $db['port'] : 5432;
    try {
        $pdo = new PDO(
            "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}",
            $dbUser,
            $dbPass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    } catch (PDOException $e) {
        die("<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; border: 1px solid #f5c6cb; background-color: #f8d7da; color: #721c24; border-radius: 5px;'>
            <h2>PostgreSQL Connection Error</h2>
            <p>Failed to connect using DATABASE_URL. Please verify the connection string and network access.</p>
            <ul>
                <li>Host: " . htmlspecialchars($dbHost) . "</li>
                <li>Database: " . htmlspecialchars($dbName) . "</li>
                <li>User: " . htmlspecialchars($dbUser) . "</li>
                <li>Port: " . htmlspecialchars($dbPort) . "</li>
            </ul>
            <p><strong>Error Details:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        </div>");
    }
} else {
    require_once 'db.php';
}

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
        last_login TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✓ admin_users created\n";
    
    // Add last_login column if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL");
        echo "✓ last_login column added\n";
    } catch (PDOException $e) {
        echo "  (last_login column may already exist)\n";
    }
    echo "\n";
    
    echo "Creating donors table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS donors (
        id SERIAL PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        phone VARCHAR(20),
        blood_type VARCHAR(10),
        date_of_birth DATE,
        gender VARCHAR(10),
        address TEXT,
        city VARCHAR(100),
        province VARCHAR(100),
        postal_code VARCHAR(20),
        weight NUMERIC(5,2),
        height NUMERIC(5,2),
        reference_code VARCHAR(50),
        status VARCHAR(20) DEFAULT 'pending',
        served_date TIMESTAMP NULL,
        rejection_reason TEXT,
        unserved_reason TEXT,
        last_donation_date DATE,
        last_reminder_sent DATE,
        seed_flag BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✓ donors created\n";

    // Ensure new columns exist if table pre-existed (safe for reruns)
    $alterStatements = [
        "ALTER TABLE donors ADD COLUMN IF NOT EXISTS postal_code VARCHAR(20)",
        "ALTER TABLE donors ADD COLUMN IF NOT EXISTS weight NUMERIC(5,2)",
        "ALTER TABLE donors ADD COLUMN IF NOT EXISTS height NUMERIC(5,2)",
        "ALTER TABLE donors ADD COLUMN IF NOT EXISTS reference_code VARCHAR(50)",
        "ALTER TABLE donors ADD COLUMN IF NOT EXISTS served_date TIMESTAMP NULL",
        "ALTER TABLE donors ADD COLUMN IF NOT EXISTS rejection_reason TEXT",
        "ALTER TABLE donors ADD COLUMN IF NOT EXISTS unserved_reason TEXT",
        "ALTER TABLE donors ADD COLUMN IF NOT EXISTS last_donation_date DATE",
        "ALTER TABLE donors ADD COLUMN IF NOT EXISTS last_reminder_sent DATE",
        "ALTER TABLE donors ADD COLUMN IF NOT EXISTS seed_flag BOOLEAN DEFAULT FALSE",
        "ALTER TABLE donors ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'pending'",
        "ALTER TABLE donors ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
        "ALTER TABLE donors ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
    ];
    foreach ($alterStatements as $sql) {
        try { $pdo->exec($sql); } catch (PDOException $e) { /* ignore */ }
    }

    // Indexes for dashboard queries and lookups
    $indexStatements = [
        "CREATE INDEX IF NOT EXISTS idx_donors_status ON donors(status)",
        "CREATE INDEX IF NOT EXISTS idx_donors_blood_type ON donors(blood_type)",
        "CREATE INDEX IF NOT EXISTS idx_donors_created_at ON donors(created_at)",
        "CREATE UNIQUE INDEX IF NOT EXISTS idx_donors_reference_code ON donors(reference_code)",
        "CREATE INDEX IF NOT EXISTS idx_donors_status_created ON donors(status, created_at)",
        "CREATE INDEX IF NOT EXISTS idx_donors_seed_flag ON donors(seed_flag)"
    ];
    foreach ($indexStatements as $sql) {
        try { $pdo->exec($sql); } catch (PDOException $e) { /* ignore */ }
    }
    echo "✓ donors columns and indexes ensured\n\n";
    
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
    
    echo "Creating donor_medical_screening_simple table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS donor_medical_screening_simple (
        id SERIAL PRIMARY KEY,
        donor_id INTEGER REFERENCES donors(id) ON DELETE CASCADE,
        reference_code VARCHAR(20),
        screening_data JSONB,
        all_questions_answered BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✓ donor_medical_screening_simple created\n\n";

    echo "Creating blood_requests table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS blood_requests (
        id SERIAL PRIMARY KEY,
        patient_name VARCHAR(100) NOT NULL,
        blood_type VARCHAR(5),
        blood_type_needed VARCHAR(5),
        units_required INT DEFAULT 1,
        hospital_name VARCHAR(255),
        hospital_address TEXT,
        city VARCHAR(100),
        contact_person VARCHAR(100),
        contact_phone VARCHAR(20),
        status VARCHAR(20) DEFAULT 'pending',
        notes TEXT,
        reference_number VARCHAR(32) UNIQUE,
        request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        desired_date DATE,
        admin_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_blood_requests_status ON blood_requests(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_blood_requests_reference ON blood_requests(reference_number)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_blood_requests_request_date ON blood_requests(request_date)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_blood_requests_desired_date ON blood_requests(desired_date)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_blood_requests_blood_type_needed ON blood_requests(blood_type_needed)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_blood_requests_blood_type ON blood_requests(blood_type)");
    echo "✓ blood_requests created\n\n";

    echo "Creating compatibility view 'requests'...\n";
    $pdo->exec("CREATE OR REPLACE VIEW requests AS
        SELECT 
            id,
            patient_name,
            blood_type_needed,
            units_required,
            hospital_name,
            status,
            request_date,
            reference_number,
            hospital_address,
            contact_person,
            contact_phone,
            notes,
            admin_notes,
            blood_type,
            desired_date,
            city,
            created_at,
            updated_at
        FROM blood_requests");
    echo "✓ requests view created\n\n";
    
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
