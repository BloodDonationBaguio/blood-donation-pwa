# Blood Donation System - Local Installation Guide
## Installing from ZIP File

This guide walks you through installing the Blood Donation System locally from the provided ZIP file.

---

## 📦 What's Included in Your ZIP Package

```
blood-donation-pwa.zip
├── blood-donation-pwa/           # Main application folder
│   ├── index.php                 # Homepage
│   ├── admin.php                 # Admin dashboard
│   ├── dashboard.php             # User dashboard
│   ├── donor-registration.php    # Donor registration form
│   ├── login.php                 # User login
│   ├── signup.php                # User registration
│   ├── track.php                 # Application tracking
│   ├── config/                   # Configuration files
│   ├── includes/                 # PHP includes and functions
│   ├── css/                      # Stylesheets
│   ├── assets/                   # Images and icons
│   ├── database/                 # Database files
│   ├── docs/                     # Documentation
│   └── ...                       # Other application files
├── USER_MANUAL.md                # User manual
├── INSTALLATION_GUIDE.md         # Server installation guide
├── LOCAL_INSTALLATION_GUIDE.md   # This local installation guide
└── QUICK_USER_GUIDE.md           # Quick user guide
```

---

## 🖥️ Local Installation Options

### Option 1: XAMPP (Recommended for Windows)
### Option 2: MAMP (Recommended for macOS)
### Option 3: LAMP/LEMP (Linux)
### Option 4: Docker (Cross-platform)

---

## 🪟 Option 1: XAMPP Installation (Windows)

### Step 1: Download and Install XAMPP
1. **Download XAMPP**
   - Go to: https://www.apachefriends.org/download.html
   - Download XAMPP for Windows (latest version)
   - Choose the version with PHP 8.1+ if available

2. **Install XAMPP**
   - Run the installer as Administrator
   - Install to: `C:\xampp` (default recommended)
   - Select Apache and MySQL components
   - Complete installation

### Step 2: Extract the ZIP File
1. **Extract to XAMPP htdocs**
   ```
   Extract location: C:\xampp\htdocs\blood-donation-pwa-1
   ```
2. **Verify extraction**
   - Navigate to `C:\xampp\htdocs\blood-donation-pwa-1`
   - You should see `index.php`, `admin.php`, etc.

### Step 3: Start XAMPP Services
1. **Open XAMPP Control Panel**
   - Launch from Desktop or Start Menu
2. **Start Apache**
   - Click "Start" button next to Apache
   - Verify it turns green
3. **Start MySQL**
   - Click "Start" button next to MySQL
   - Verify it turns green

### Step 4: Database Setup
1. **Access phpMyAdmin**
   - Open browser: http://localhost/phpmyadmin
   - Or click "Admin" button next to MySQL in XAMPP

2. **Create Database**
   - Click "New" in left sidebar
   - Database name: `blood_donation`
   - Click "Create"

3. **Create Database User**
   - Click "User accounts" tab
   - Click "Add user account"
   - Username: `blooduser`
   - Hostname: `Localhost`
   - Password: `bloodpass123` (choose your own)
   - Click "Check all" under "Database-specific privileges"
   - Click "Go"

4. **Import Database Schema**
   - Select the `blood_donation` database
   - Click "Import" tab
   - Browse to: `C:\xampp\htdocs\blood-donation-pwa-1\database\`
   - Select the SQL file (e.g., `schema.sql` or `mysql_schema.sql`)
   - Click "Go"

### Step 5: Configure Database Connection
1. **Edit Database Configuration**
   - Open: `C:\xampp\htdocs\blood-donation-pwa-1\db.php`
   - Find and update these lines:
   ```php
   // Around line 10-15
   $host = 'localhost';
   $dbname = 'blood_donation';
   $username = 'blooduser';
   $password = 'bloodpass123'; // Use your password
   ```

2. **Save the file**

### Step 6: Access the Application
1. **Open Browser**
   - Navigate to: http://localhost/blood-donation-pwa-1
   - Or: http://localhost/blood-donation-pwa-1/index.php

2. **Verify Installation**
   - You should see the Blood Donation System homepage
   - Test user registration and login functions

---

## 🍎 Option 2: MAMP Installation (macOS)

### Step 1: Download and Install MAMP
1. **Download MAMP**
   - Go to: https://www.mamp.info/en/downloads/
   - Download MAMP (free version)

2. **Install MAMP**
   - Open the downloaded DMG file
   - Drag MAMP to Applications folder
   - Launch MAMP from Applications

### Step 2: Extract the ZIP File
1. **Extract to MAMP htdocs**
   ```
   Extract location: /Applications/MAMP/htdocs/blood-donation-pwa-1
   ```
2. **Set permissions**
   ```bash
   sudo chmod -R 755 /Applications/MAMP/htdocs/blood-donation-pwa-1
   ```

### Step 3: Start MAMP Services
1. **Open MAMP Control Panel**
   - Launch from Applications
2. **Start Servers**
   - Click "Start Servers"
   - Apache and MySQL should start (green indicators)

### Step 4: Database Setup
1. **Access phpMyAdmin**
   - Open: http://localhost:8888/phpMyAdmin/
   - Or click "Open phpMyAdmin" in MAMP

2. **Create Database**
   - Click "New" in left sidebar
   - Database name: `blood_donation`
   - Click "Create"

3. **Create User**
   - Click "User accounts"
   - "Add user account"
   - Username: `blooduser`
   - Hostname: `Localhost`
   - Password: `bloodpass123`
   - Under "Database-specific privileges": Check all
   - Click "Go"

4. **Import Schema**
   - Select `blood_donation` database
   - Click "Import"
   - Browse to: `/Applications/MAMP/htdocs/blood-donation-pwa-1/database/`
   - Select SQL file and import

### Step 5: Configure Database
1. **Edit db.php**
   - Open: `/Applications/MAMP/htdocs/blood-donation-pwa-1/db.php`
   - Update database credentials:
   ```php
   $host = 'localhost';
   $dbname = 'blood_donation';
   $username = 'blooduser';
   $password = 'bloodpass123';
   ```

### Step 6: Access Application
1. **Open Browser**
   - Navigate to: http://localhost:8888/blood-donation-pwa-1
2. **Test the system**

---

## 🐧 Option 3: LAMP Installation (Linux)

### Step 1: Install LAMP Stack
```bash
# Update system
sudo apt update

# Install Apache
sudo apt install apache2

# Install MySQL
sudo apt install mysql-server
sudo mysql_secure_installation

# Install PHP
sudo apt install php libapache2-mod-php php-mysql php-cli php-curl php-zip php-gd php-mbstring php-xml php-bcmath

# Install phpMyAdmin (optional)
sudo apt install phpmyadmin
```

### Step 2: Extract ZIP File
```bash
# Navigate to web root
cd /var/www/html

# Extract (adjust path to your ZIP location)
sudo unzip ~/Downloads/blood-donation-pwa.zip

# Set permissions
sudo chown -R www-data:www-data /var/www/html/blood-donation-pwa-1
sudo chmod -R 755 /var/www/html/blood-donation-pwa-1
```

### Step 3: Database Setup
```bash
# Login to MySQL
sudo mysql

# Create database and user
CREATE DATABASE blood_donation;
CREATE USER 'blooduser'@'localhost' IDENTIFIED BY 'bloodpass123';
GRANT ALL PRIVILEGES ON blood_donation.* TO 'blooduser'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Import schema
mysql -u blooduser -p blood_donation < /var/www/html/blood-donation-pwa-1/database/schema.sql
```

### Step 4: Configure Database
```bash
# Edit database configuration
sudo nano /var/www/html/blood-donation-pwa-1/db.php

# Update credentials
$host = 'localhost';
$dbname = 'blood_donation';
$username = 'blooduser';
$password = 'bloodpass123';
```

### Step 5: Access Application
- Open browser: http://localhost/blood-donation-pwa-1

---

## 🐳 Option 4: Docker Installation (Cross-Platform)

### Step 1: Install Docker
- **Windows:** Download Docker Desktop from docker.com
- **macOS:** Download Docker Desktop from docker.com
- **Linux:** Install via package manager

### Step 2: Create Docker Compose File
Create `docker-compose.yml` in the extracted folder:
```yaml
version: '3.8'

services:
  web:
    image: php:8.1-apache
    ports:
      - "8080:80"
    volumes:
      - ./:/var/www/html
    depends_on:
      - db
    environment:
      - APACHE_DOCUMENT_ROOT=/var/www/html

  db:
    image: mysql:8.0
    ports:
      - "3306:3306"
    environment:
      MYSQL_ROOT_PASSWORD: rootpass
      MYSQL_DATABASE: blood_donation
      MYSQL_USER: blooduser
      MYSQL_PASSWORD: bloodpass123
    volumes:
      - db_data:/var/lib/mysql
      - ./database:/docker-entrypoint-initdb.d

  phpmyadmin:
    image: phpmyadmin/phpmyadmin
    ports:
      - "8081:80"
    environment:
      PMA_HOST: db
      PMA_USER: root
      PMA_PASSWORD: rootpass

volumes:
  db_data:
```

### Step 3: Start Docker Container
```bash
# Navigate to project folder
cd blood-donation-pwa-1

# Start containers
docker-compose up -d

# Access application
# Browser: http://localhost:8080
# phpMyAdmin: http://localhost:8081
```

---

## 🔧 Common Configuration Steps

### Database Configuration File
The main database configuration is in `db.php`. Update these lines:

```php
// Find this section in db.php
$host = 'localhost';           // Database host
$dbname = 'blood_donation';    // Database name
$username = 'blooduser';       // Database username
$password = 'your_password';   // Database password
```

### File Permissions (Linux/macOS)
```bash
# Set proper ownership
sudo chown -R www-data:www-data /path/to/blood-donation-pwa-1

# Set directory permissions
sudo find /path/to/blood-donation-pwa-1 -type d -exec chmod 755 {} \;

# Set file permissions
sudo find /path/to/blood-donation-pwa-1 -type f -exec chmod 644 {} \;

# Special permissions for writable directories
sudo chmod -R 775 /path/to/blood-donation-pwa-1/logs
sudo chmod -R 775 /path/to/blood-donation-pwa-1/uploads
```

### PHP Configuration
Ensure your PHP configuration includes:
```ini
; In php.ini
max_execution_time = 300
memory_limit = 128M
upload_max_filesize = 10M
post_max_size = 10M
display_errors = On          ; For development only
error_reporting = E_ALL       ; For development only
```

---

## 🧪 Testing Your Installation

### Basic Functionality Test
1. **Access Homepage**
   - Open: http://localhost/blood-donation-pwa-1 (or appropriate URL)
   - Should see the Blood Donation System homepage

2. **Test User Registration**
   - Click "Sign Up"
   - Fill registration form
   - Submit and verify account creation

3. **Test Login**
   - Use created credentials
   - Verify dashboard access

4. **Test Donor Registration**
   - Login and click "Donate Now"
   - Fill donor form
   - Submit and save reference number

5. **Test Tracking**
   - Go to "Track Application"
   - Enter reference number
   - Verify status display

### Database Connection Test
Create a test file `test_db.php`:
```php
<?php
try {
    require_once 'db.php';
    echo "✅ Database connection successful!";
    
    // Test query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users_new");
    $result = $stmt->fetch();
    echo "<br>✅ Users table accessible. Records: " . $result['count'];
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage();
}
?>
```
Access: http://localhost/blood-donation-pwa-1/test_db.php

---

## 🚨 Troubleshooting Common Issues

### Issue 1: Blank White Page
**Problem:** Page loads but shows nothing
**Solutions:**
```php
// Add to top of index.php for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

### Issue 2: Database Connection Failed
**Problem:** "Could not connect to database"
**Solutions:**
1. Verify database service is running
2. Check credentials in db.php
3. Ensure database and user exist
4. Test connection manually

### Issue 3: Permission Denied
**Problem:** File permission errors
**Solutions:**
```bash
# Linux/macOS
sudo chown -R www-data:www-data /path/to/folder
sudo chmod -R 755 /path/to/folder

# Windows: Right-click folder → Properties → Security → Edit permissions
```

### Issue 4: Port Already in Use
**Problem:** Apache/MYSQL won't start
**Solutions:**
1. Change ports in XAMPP/MAMP configuration
2. Stop other services using ports 80/443/3306
3. Use Task Manager (Windows) or `lsof` (Linux) to find processes

### Issue 5: PHP Version Incompatibility
**Problem:** PHP syntax errors
**Solutions:**
1. Check PHP version: `php -v`
2. Update to PHP 8.0+ if needed
3. Update XAMPP/MAMP to latest version

---

## 📱 Mobile Testing

### Testing on Mobile Devices
1. **Find your local IP address:**
   - Windows: `ipconfig` in Command Prompt
   - macOS/Linux: `ifconfig` in Terminal
   - Look for IPv4 Address (e.g., 192.168.1.100)

2. **Access from mobile:**
   - Connect mobile to same WiFi network
   - Open browser: `http://[your-ip]/blood-donation-pwa-1`
   - Example: `http://192.168.1.100/blood-donation-pwa-1`

### Responsive Design Testing
- Test on different screen sizes
- Verify mobile menu functionality
- Check form usability on touch devices

---

## 🔄 Next Steps After Installation

### 1. Create Admin Account
```sql
-- Insert admin user (run in phpMyAdmin)
INSERT INTO admin_users (username, password, email, created_at) 
VALUES ('admin', '$2y$10$encrypted_password_hash', 'admin@example.com', NOW());
```

### 2. Configure Email Settings
Edit email configuration in config files for password reset functionality.

### 3. Test Complete Workflow
- User registration → Login → Donor registration → Tracking → Admin approval

### 4. Review Documentation
- Read USER_MANUAL.md for user guidance
- Review QUICK_USER_GUIDE.md for step-by-step process

---

## 📞 Support Resources

### Getting Help
- **Documentation:** Check the included manual files
- **Common Issues:** See troubleshooting section above
- **Online Resources:** Search for specific error messages

### File Locations Reference
```
Main Application: /blood-donation-pwa-1/
Database Config:  /blood-donation-pwa-1/db.php
Email Config:    /blood-donation-pwa-1/config/email.php
Logs:           /blood-donation-pwa-1/logs/
Database Files: /blood-donation-pwa-1/database/
Documentation:  /blood-donation-pwa-1/docs/
```

---

## ✅ Installation Checklist

### Pre-Installation
- [ ] Chosen installation method (XAMPP/MAMP/LAMP/Docker)
- [ ] Downloaded required software
- [ ] Backed up existing data (if applicable)

### Installation
- [ ] Extracted ZIP file to correct location
- [ ] Installed and started web server
- [ ] Installed and started database
- [ ] Created database and user
- [ ] Imported database schema
- [ ] Configured database connection
- [ ] Set file permissions

### Testing
- [ ] Homepage loads correctly
- [ ] User registration works
- [ ] Login functionality works
- [ ] Donor registration works
- [ ] Application tracking works
- [ ] Admin panel accessible
- [ ] Mobile responsive design works

### Post-Installation
- [ ] Created admin account
- [ ] Configured email settings
- [ ] Tested complete user workflow
- [ ] Reviewed documentation
- [ ] Bookmarked local URL

---

## 🎉 Installation Complete!

Once you've completed these steps, your Blood Donation System should be running locally. You can now:

1. **Access the system** at your local URL
2. **Register test users** and explore functionality
3. **Review the user manuals** for detailed guidance
4. **Test the complete donation workflow**

For production deployment, refer to the main INSTALLATION_GUIDE.md for server setup instructions.

---

*Last Updated: [Current Date]*  
*Version: 1.0*  
*For support: Check troubleshooting section or consult documentation*
