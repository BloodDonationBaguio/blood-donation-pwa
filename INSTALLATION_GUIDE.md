# Blood Donation System - Installation Guide

## Table of Contents
1. [System Requirements](#system-requirements)
2. [Prerequisites](#prerequisites)
3. [Installation Steps](#installation-steps)
4. [Database Setup](#database-setup)
5. [Configuration](#configuration)
6. [Web Server Setup](#web-server-setup)
7. [SSL/HTTPS Setup](#sslhttps-setup)
8. [Testing & Verification](#testing--verification)
9. [Troubleshooting](#troubleshooting)
10. [Maintenance & Updates](#maintenance--updates)

---

## System Requirements

### Server Requirements

**Minimum Requirements:**
- **OS:** Linux (Ubuntu 20.04+), Windows Server 2019+, or macOS
- **RAM:** 2GB minimum, 4GB recommended
- **Storage:** 20GB available space
- **CPU:** 2 cores minimum, 4 cores recommended

**Software Requirements:**
- **Web Server:** Apache 2.4+ or Nginx 1.18+
- **PHP:** Version 8.0+ (8.1+ recommended)
- **Database:** MySQL 8.0+ or PostgreSQL 12+
- **Composer:** Latest stable version

### Client Requirements

**For Users:**
- Modern web browser (Chrome 90+, Firefox 88+, Safari 14+, Edge 90+)
- Internet connection (broadband recommended)
- Device: Desktop, laptop, tablet, or smartphone

---

## Prerequisites

### 1. Web Server Installation

#### Apache (Ubuntu/Debian)
```bash
sudo apt update
sudo apt install apache2
sudo systemctl start apache2
sudo systemctl enable apache2
```

#### Nginx (Ubuntu/Debian)
```bash
sudo apt update
sudo apt install nginx
sudo systemctl start nginx
sudo systemctl enable nginx
```

### 2. PHP Installation

#### Ubuntu/Debian
```bash
sudo apt install php8.1 php8.1-cli php8.1-fpm php8.1-mysql php8.1-pgsql
sudo apt install php8.1-curl php8.1-json php8.1-mbstring php8.1-xml
sudo apt install php8.1-zip php8.1-bcmath php8.1-gd php8.1-intl
```

#### CentOS/RHEL
```bash
sudo dnf install php81 php81-php-cli php81-php-fpm
sudo dnf install php81-php-mysqlnd php81-php-pgsql
sudo dnf install php81-php-curl php81-php-json php81-php-mbstring
```

### 3. Database Installation

#### MySQL
```bash
# Ubuntu/Debian
sudo apt install mysql-server
sudo mysql_secure_installation

# Create database
mysql -u root -p
CREATE DATABASE blood_donation;
CREATE USER 'blooduser'@'localhost' IDENTIFIED BY 'strong_password';
GRANT ALL PRIVILEGES ON blood_donation.* TO 'blooduser'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### PostgreSQL
```bash
# Ubuntu/Debian
sudo apt install postgresql postgresql-contrib
sudo systemctl start postgresql
sudo systemctl enable postgresql

# Create database
sudo -u postgres psql
CREATE DATABASE blood_donation;
CREATE USER blooduser WITH PASSWORD 'strong_password';
GRANT ALL PRIVILEGES ON DATABASE blood_donation TO blooduser;
\q
```

### 4. Composer Installation
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
```

---

## Installation Steps

### Step 1: Download the System

#### Option A: Git Clone (Recommended)
```bash
cd /var/www/html
sudo git clone https://github.com/your-repo/blood-donation-pwa.git
sudo chown -R www-data:www-data blood-donation-pwa
sudo chmod -R 755 blood-donation-pwa
```

#### Option B: Download ZIP
```bash
cd /var/www/html
sudo wget https://github.com/your-repo/blood-donation-pwa/archive/main.zip
sudo unzip main.zip
sudo mv blood-donation-pwa-main blood-donation-pwa
sudo chown -R www-data:www-data blood-donation-pwa
sudo chmod -R 755 blood-donation-pwa
```

### Step 2: Install PHP Dependencies
```bash
cd /var/www/html/blood-donation-pwa
sudo composer install --no-dev --optimize-autoloader
```

### Step 3: Set File Permissions
```bash
# Set proper ownership
sudo chown -R www-data:www-data /var/www/html/blood-donation-pwa

# Set directory permissions
sudo find /var/www/html/blood-donation-pwa -type d -exec chmod 755 {} \;

# Set file permissions
sudo find /var/www/html/blood-donation-pwa -type f -exec chmod 644 {} \;

# Special permissions for writable directories
sudo chmod -R 775 /var/www/html/blood-donation-pwa/logs
sudo chmod -R 775 /var/www/html/blood-donation-pwa/uploads
sudo chmod -R 775 /var/www/html/blood-donation-pwa/cache
```

---

## Database Setup

### Step 1: Import Database Schema

#### MySQL
```bash
mysql -u blooduser -p blood_donation < database/mysql_schema.sql
mysql -u blooduser -p blood_donation < database/seed_data.sql
```

#### PostgreSQL
```bash
psql -U blooduser -d blood_donation -f database/postgres_schema.sql
psql -U blooduser -d blood_donation -f database/seed_data.sql
```

### Step 2: Run Migration Script
```bash
cd /var/www/html/blood-donation-pwa
php migrate_database.php
```

### Step 3: Verify Database Connection
```bash
php test_database_connection.php
```

---

## Configuration

### Step 1: Database Configuration

Create/edit `config/database.php`:
```php
<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'blood_donation');
define('DB_USER', 'blooduser');
define('DB_PASS', 'strong_password');
define('DB_TYPE', 'mysql'); // or 'postgresql'

// PDO Connection
try {
    $pdo = new PDO(
        DB_TYPE === 'mysql' 
            ? "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4"
            : "pgsql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
```

### Step 2: Email Configuration

Create/edit `config/email.php`:
```php
<?php
// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_ENCRYPTION', 'tls');
define('FROM_EMAIL', 'noreply@blooddonation.system');
define('FROM_NAME', 'Blood Donation System');
```

### Step 3: Security Configuration

Create/edit `config/security.php`:
```php
<?php
// Security Configuration
define('ENCRYPTION_KEY', 'your-32-character-encryption-key');
define('JWT_SECRET', 'your-jwt-secret-key');
define('SESSION_LIFETIME', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes
```

### Step 4: Environment Configuration

Create `.env` file:
```env
# Environment
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_HOST=localhost
DB_DATABASE=blood_donation
DB_USERNAME=blooduser
DB_PASSWORD=strong_password

# Email
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
```

---

## Web Server Setup

### Apache Configuration

Create `/etc/apache2/sites-available/blood-donation.conf`:
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/html/blood-donation-pwa
    
    <Directory /var/www/html/blood-donation-pwa>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/blood-donation-error.log
    CustomLog ${APACHE_LOG_DIR}/blood-donation-access.log combined
</VirtualHost>
```

Enable site and modules:
```bash
sudo a2ensite blood-donation.conf
sudo a2enmod rewrite
sudo a2enmod headers
sudo systemctl restart apache2
```

### Nginx Configuration

Create `/etc/nginx/sites-available/blood-donation`:
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/html/blood-donation-pwa;
    index index.php index.html;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\.ht {
        deny all;
    }
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
}
```

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/blood-donation /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

---

## SSL/HTTPS Setup

### Option A: Let's Encrypt (Free SSL)
```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache
# or for nginx: sudo apt install certbot python3-certbot-nginx

# Obtain SSL certificate
sudo certbot --apache -d your-domain.com
# or for nginx: sudo certbot --nginx -d your-domain.com

# Auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

### Option B: Self-Signed Certificate (Development)
```bash
# Create SSL directory
sudo mkdir /etc/ssl/blood-donation

# Generate private key
sudo openssl genrsa -out /etc/ssl/blood-donation/private.key 2048

# Generate certificate
sudo openssl req -new -x509 -key /etc/ssl/blood-donation/private.key -out /etc/ssl/blood-donation/certificate.crt -days 365
```

### Update Apache for HTTPS
```apache
<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /var/www/html/blood-donation-pwa
    
    SSLEngine on
    SSLCertificateFile /etc/ssl/blood-donation/certificate.crt
    SSLCertificateKeyFile /etc/ssl/blood-donation/private.key
    
    # Security headers
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set X-Frame-Options DENY
    Header always set X-Content-Type-Options nosniff
</VirtualHost>
```

---

## Testing & Verification

### Step 1: Basic Functionality Test
```bash
# Test PHP installation
php -v

# Test database connection
php -r "try { new PDO('mysql:host=localhost;dbname=blood_donation', 'blooduser', 'password'); echo 'DB OK'; } catch(Exception $e) { echo 'DB Error: ' . $e->getMessage(); }"

# Test web server
curl -I http://your-domain.com
```

### Step 2: System Health Check
```bash
cd /var/www/html/blood-donation-pwa
php health.php
```

### Step 3: Run Test Suite
```bash
cd /var/www/html/blood-donation-pwa
php test_application_features.php
```

### Step 4: Browser Testing
1. Open browser and navigate to `https://your-domain.com`
2. Verify homepage loads correctly
3. Test user registration process
4. Test login functionality
5. Test donor registration form
6. Test application tracking

### Step 5: Mobile Testing
1. Test on mobile devices
2. Verify responsive design
3. Test PWA functionality
4. Check touch interactions

---

## Troubleshooting

### Common Issues

#### 1. Blank White Page
**Problem:** White screen with no content
**Solution:**
```bash
# Check PHP errors
tail -f /var/log/apache2/error.log

# Enable error display temporarily
sudo nano /var/www/html/blood-donation-pwa/index.php
# Add at top: ini_set('display_errors', 1);
```

#### 2. Database Connection Failed
**Problem:** Cannot connect to database
**Solution:**
```bash
# Check database status
sudo systemctl status mysql
# or: sudo systemctl status postgresql

# Test connection manually
mysql -u blooduser -p blood_donation

# Check credentials in config file
```

#### 3. Permission Issues
**Problem:** File permission errors
**Solution:**
```bash
# Reset permissions
sudo chown -R www-data:www-data /var/www/html/blood-donation-pwa
sudo find /var/www/html/blood-donation-pwa -type d -exec chmod 755 {} \;
sudo find /var/www/html/blood-donation-pwa -type f -exec chmod 644 {} \;
```

#### 4. Email Not Sending
**Problem:** Email notifications not working
**Solution:**
```bash
# Test email configuration
php test_email.php

# Check SMTP settings
# Verify firewall allows SMTP traffic
# Check email provider settings
```

#### 5. SSL Certificate Issues
**Problem:** HTTPS not working
**Solution:**
```bash
# Check certificate status
sudo certbot certificates

# Renew certificate
sudo certbot renew

# Check Apache/Nginx SSL config
sudo apache2ctl configtest
# or: sudo nginx -t
```

### Log Files Locations

**Apache Logs:**
- Error: `/var/log/apache2/error.log`
- Access: `/var/log/apache2/access.log`
- Site-specific: `/var/log/apache2/blood-donation-error.log`

**Nginx Logs:**
- Error: `/var/log/nginx/error.log`
- Access: `/var/log/nginx/access.log`

**PHP Logs:**
- Error: `/var/log/php_errors.log`
- FPM: `/var/log/php8.1-fpm.log`

**Application Logs:**
- Application: `/var/www/html/blood-donation-pwa/logs/`
- Database: Database-specific logs

---

## Maintenance & Updates

### Regular Maintenance Tasks

#### Daily
```bash
# Check system status
php health.php

# Monitor logs
tail -f /var/log/apache2/error.log

# Backup database
mysqldump -u blooduser -p blood_donation > backup_$(date +%Y%m%d).sql
```

#### Weekly
```bash
# Update system packages
sudo apt update && sudo apt upgrade

# Clean old logs
find /var/log -name "*.log" -mtime +30 -delete

# Check SSL certificate expiry
sudo certbot certificates
```

#### Monthly
```bash
# Update PHP dependencies
cd /var/www/html/blood-donation-pwa
sudo composer update

# Database optimization
mysql -u blooduser -p blood_donation -e "OPTIMIZE TABLE donors, users_new, donations_new;"

# Security audit
sudo apt list --upgradable
```

### Backup Strategy

#### Database Backup
```bash
#!/bin/bash
# backup_database.sh
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/blood-donation"
mkdir -p $BACKUP_DIR

# MySQL backup
mysqldump -u blooduser -p blood_donation | gzip > $BACKUP_DIR/db_backup_$DATE.sql.gz

# Keep last 30 days
find $BACKUP_DIR -name "db_backup_*.sql.gz" -mtime +30 -delete
```

#### Files Backup
```bash
#!/bin/bash
# backup_files.sh
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/blood-donation"
mkdir -p $BACKUP_DIR

# Application files backup
tar -czf $BACKUP_DIR/files_backup_$DATE.tar.gz /var/www/html/blood-donation-pwa

# Keep last 7 days
find $BACKUP_DIR -name "files_backup_*.tar.gz" -mtime +7 -delete
```

### Update Process

#### System Updates
1. **Backup current system**
2. **Download new version**
3. **Test on staging environment**
4. **Update production during maintenance window**
5. **Verify functionality**
6. **Monitor for issues**

#### Database Updates
1. **Backup database**
2. **Run migration scripts**
3. **Verify data integrity**
4. **Test application functionality**
5. **Monitor performance**

---

## Security Hardening

### Server Security
```bash
# Configure firewall
sudo ufw enable
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Disable root login
sudo nano /etc/ssh/sshd_config
# Set: PermitRootLogin no

# Install fail2ban
sudo apt install fail2ban
sudo systemctl enable fail2ban
```

### Application Security
```php
// Enable secure headers in config/security.php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
```

### Regular Security Audits
- Check for PHP vulnerabilities
- Update dependencies regularly
- Monitor access logs
- Perform penetration testing
- Review user permissions

---

## Performance Optimization

### Database Optimization
```sql
-- Add indexes for better performance
CREATE INDEX idx_donors_email ON donors(email);
CREATE INDEX idx_donors_status ON donors(status);
CREATE INDEX idx_donations_date ON donations_new(created_at);
```

### Caching Configuration
```php
// Enable OPcache in php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
```

### Web Server Optimization
```apache
# Apache optimization
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
</IfModule>
```

---

## Support & Documentation

### Getting Help
- **Documentation:** Check `/docs/` directory
- **Community Forum:** [Link to forum]
- **Technical Support:** support@blooddonation.system
- **Emergency Support:** emergency@blooddonation.system

### Contributing
- **Bug Reports:** Use GitHub Issues
- **Feature Requests:** Submit via project management
- **Code Contributions:** Follow contribution guidelines

---

*Last Updated: [Current Date]*  
*Version: [Current Version]*  
*For technical support: support@blooddonation.system*
