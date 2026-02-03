# Deployment Guide

Complete guide for deploying the Marketplace application to production.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Server Setup](#server-setup)
3. [Application Installation](#application-installation)
4. [Database Configuration](#database-configuration)
5. [Web Server Configuration](#web-server-configuration)
6. [Cron Setup](#cron-setup)
7. [Security Hardening](#security-hardening)
8. [Monitoring](#monitoring)
9. [Backup Strategy](#backup-strategy)
10. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### Hardware Requirements

**Minimum** (up to 1k transactions/day):
- 1 CPU core
- 1 GB RAM
- 10 GB disk space
- 1 Mbps network

**Recommended** (up to 10k transactions/day):
- 2 CPU cores
- 2 GB RAM
- 50 GB disk space
- 10 Mbps network

**Production** (up to 100k transactions/day):
- 4 CPU cores
- 4 GB RAM
- 100 GB disk space
- 100 Mbps network

### Software Requirements

- **OS**: Ubuntu 22.04 LTS or Debian 12 (recommended)
- **PHP**: 8.0 or higher
- **Python**: 3.8 or higher
- **Nginx**: 1.18 or higher
- **MariaDB**: 10.6 or higher (or MySQL 8.0+)
- **Git**: For deployment
- **Certbot**: For SSL certificates

### External Services

- **Alchemy API**: Free tier sufficient for MVP (300M compute units/month)
- **Domain name**: With DNS access
- **Email service**: For notifications (optional)

---

## Server Setup

### 1. Initial Server Configuration

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Set timezone
sudo timedatectl set-timezone UTC

# Set hostname
sudo hostnamectl set-hostname marketplace

# Create application user
sudo useradd -m -s /bin/bash marketplace
sudo usermod -aG sudo marketplace
```

### 2. Install Required Packages

```bash
# Install PHP and extensions
sudo apt install -y php8.1 php8.1-fpm php8.1-cli php8.1-common \
  php8.1-mysql php8.1-sqlite3 php8.1-mbstring php8.1-curl \
  php8.1-xml php8.1-zip php8.1-bcmath

# Install Python and pip
sudo apt install -y python3 python3-pip python3-venv

# Install Nginx
sudo apt install -y nginx

# Install MariaDB
sudo apt install -y mariadb-server mariadb-client

# Install other utilities
sudo apt install -y git curl wget unzip certbot python3-certbot-nginx
```

### 3. Secure MariaDB

```bash
sudo mysql_secure_installation
```

Follow prompts:
- Set root password: **Yes**
- Remove anonymous users: **Yes**
- Disallow root login remotely: **Yes**
- Remove test database: **Yes**
- Reload privilege tables: **Yes**

---

## Application Installation

### 1. Clone Repository

```bash
# Switch to application user
sudo su - marketplace

# Clone repository
cd /home/marketplace
git clone https://github.com/sanctumos/clawedroad.git
cd marketplace/app
```

### 2. Configure Environment

```bash
# Copy environment template
cp .env.example .env

# Edit environment file
nano .env
```

**Required Configuration**:

```bash
# Database (MariaDB for production)
DB_DRIVER=mariadb
DB_DSN=mysql:host=127.0.0.1;dbname=marketplace;charset=utf8mb4
DB_USER=marketplace_user
DB_PASSWORD=GENERATE_STRONG_PASSWORD_HERE

# Site configuration
SITE_URL=https://marketplace.example.com
SITE_NAME=Marketplace

# Security salts (generate random strings)
SESSION_SALT=$(openssl rand -hex 32)
COOKIE_ENCRYPTION_SALT=$(openssl rand -hex 32)
CSRF_SALT=$(openssl rand -hex 32)

# Blockchain configuration (Python only - DO NOT expose to PHP)
MNEMONIC="twelve word mnemonic phrase here never reuse existing"
ALCHEMY_API_KEY=your_alchemy_api_key_here
ALCHEMY_NETWORK=mainnet

# Commission wallets
COMMISSION_WALLET_MAINNET=0xYourMainnetWallet
COMMISSION_WALLET_SEPOLIA=0xYourSepoliaWallet
COMMISSION_WALLET_BASE=0xYourBaseWallet

# Rate limit: minimum minutes between account creations per IP (default 10; set to 0 to disable)
# ACCOUNT_CREATION_MIN_INTERVAL_MINUTES=10
```

**Generate Strong Salts**:
```bash
echo "SESSION_SALT=$(openssl rand -hex 32)"
echo "COOKIE_ENCRYPTION_SALT=$(openssl rand -hex 32)"
echo "CSRF_SALT=$(openssl rand -hex 32)"
```

**Generate New Mnemonic** (CRITICAL - never reuse):
```bash
# Install bip39 tool
pip3 install mnemonic

# Generate new mnemonic
python3 -c "from mnemonic import Mnemonic; print(Mnemonic('english').generate(strength=128))"
```

**Secure .env File**:
```bash
chmod 600 .env
chown marketplace:marketplace .env
```

### 3. Install Python Dependencies

```bash
# Create virtual environment (optional but recommended)
python3 -m venv venv
source venv/bin/activate

# Install dependencies
pip install -r cron/requirements.txt
```

---

## Database Configuration

### 1. Create Database and User

```bash
sudo mysql -u root -p
```

```sql
-- Create database
CREATE DATABASE marketplace CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user
CREATE USER 'marketplace_user'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';

-- Grant privileges
GRANT ALL PRIVILEGES ON marketplace.* TO 'marketplace_user'@'localhost';

-- Flush privileges
FLUSH PRIVILEGES;

-- Exit
EXIT;
```

### 2. Initialize Schema

```bash
# Via CLI (recommended for first run)
cd /home/marketplace/marketplace/app
php public/schema.php
```

**Expected Output**:
```
Schema, views, and config seeded.
```

### 3. Verify Database

```bash
mysql -u marketplace_user -p marketplace
```

```sql
-- List tables
SHOW TABLES;

-- Check config
SELECT * FROM config;

-- Exit
EXIT;
```

### 4. Create First Admin User

```bash
# Register via CLI or HTTP
curl -X POST http://localhost/register.php \
  -d "username=admin&password=STRONG_ADMIN_PASSWORD"

# Set role to admin
mysql -u marketplace_user -p marketplace
```

```sql
UPDATE users SET role = 'admin' WHERE username = 'admin';
EXIT;
```

---

## Web Server Configuration

### 1. Configure PHP-FPM

```bash
sudo nano /etc/php/8.1/fpm/pool.d/marketplace.conf
```

```ini
[marketplace]
user = marketplace
group = marketplace
listen = /run/php/marketplace.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500

; PRODUCTION SECURITY: Disable error display to prevent information leakage
php_admin_flag[display_errors] = off
php_admin_flag[display_startup_errors] = off
php_admin_value[error_reporting] = E_ALL & ~E_DEPRECATED & ~E_STRICT

; Log errors for debugging (but never display to users)
php_admin_flag[log_errors] = on
php_admin_value[error_log] = /var/log/php-fpm/marketplace-error.log

; Additional security: Expose minimal PHP version info
php_admin_flag[expose_php] = off
```

**Create Log Directory**:
```bash
sudo mkdir -p /var/log/php-fpm
sudo chown marketplace:marketplace /var/log/php-fpm
```

**Restart PHP-FPM**:
```bash
sudo systemctl restart php8.1-fpm
sudo systemctl enable php8.1-fpm
```

### 2. Configure Nginx

```bash
sudo nano /etc/nginx/sites-available/marketplace
```

```nginx
server {
    listen 80;
    server_name marketplace.example.com;
    
    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name marketplace.example.com;
    
    root /home/marketplace/marketplace/app/public;
    index index.php;
    
    # SSL configuration (managed by Certbot)
    ssl_certificate /etc/letsencrypt/live/marketplace.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/marketplace.example.com/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    # Logging
    access_log /var/log/nginx/marketplace-access.log;
    error_log /var/log/nginx/marketplace-error.log;
    
    # Hide Nginx version
    server_tokens off;
    
    # Main location
    location / {
        try_files $uri $uri/ /index.php;
    }
    
    # PHP handler
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/marketplace.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_param PHP_VALUE "env_path=/home/marketplace/marketplace/app/.env";
        
        # Security
        fastcgi_hide_header X-Powered-By;
    }
    
    # Deny access to hidden files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    # Deny access to sensitive files
    location ~ /\.env {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    # Static file caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

**Enable Site**:
```bash
sudo ln -s /etc/nginx/sites-available/marketplace /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default  # Remove default site
```

**Test Configuration**:
```bash
sudo nginx -t
```

**Reload Nginx**:
```bash
sudo systemctl reload nginx
sudo systemctl enable nginx
```

### 3. Obtain SSL Certificate

```bash
sudo certbot --nginx -d marketplace.example.com
```

Follow prompts:
- Enter email address
- Agree to Terms of Service
- Choose whether to share email
- Select redirect option (2 - Redirect HTTP to HTTPS)

**Test Auto-Renewal**:
```bash
sudo certbot renew --dry-run
```

---

## Cron Setup

### 1. Create Cron Script

```bash
sudo nano /usr/local/bin/marketplace-cron.sh
```

```bash
#!/bin/bash

# Marketplace cron wrapper
# Runs Python cron with proper environment

cd /home/marketplace/marketplace/app

# Activate virtual environment if used
# source venv/bin/activate

# Run cron
python3 cron/cron.py

# Exit with Python's exit code
exit $?
```

**Make Executable**:
```bash
sudo chmod +x /usr/local/bin/marketplace-cron.sh
sudo chown marketplace:marketplace /usr/local/bin/marketplace-cron.sh
```

### 2. Configure Crontab

```bash
sudo crontab -u marketplace -e
```

Add:
```cron
# Marketplace cron - runs every 2 minutes
*/2 * * * * /usr/local/bin/marketplace-cron.sh >> /var/log/marketplace-cron.log 2>&1

# Log rotation - keep last 7 days
0 0 * * * find /var/log/marketplace-cron.log -mtime +7 -delete
```

### 3. Create Log File

```bash
sudo touch /var/log/marketplace-cron.log
sudo chown marketplace:marketplace /var/log/marketplace-cron.log
```

### 4. Test Cron

```bash
# Run manually
sudo su - marketplace
/usr/local/bin/marketplace-cron.sh

# Check output
tail -f /var/log/marketplace-cron.log
```

**Expected Output**:
```
Cron run done.
```

---

## Security Hardening

### 1. Firewall Configuration

```bash
# Install UFW
sudo apt install -y ufw

# Default policies
sudo ufw default deny incoming
sudo ufw default allow outgoing

# Allow SSH
sudo ufw allow 22/tcp

# Allow HTTP/HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Enable firewall
sudo ufw enable

# Check status
sudo ufw status
```

### 2. Fail2Ban Setup

```bash
# Install Fail2Ban
sudo apt install -y fail2ban

# Configure for Nginx
sudo nano /etc/fail2ban/jail.local
```

```ini
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[sshd]
enabled = true

[nginx-http-auth]
enabled = true

[nginx-limit-req]
enabled = true
filter = nginx-limit-req
logpath = /var/log/nginx/marketplace-error.log
```

**Start Fail2Ban**:
```bash
sudo systemctl restart fail2ban
sudo systemctl enable fail2ban
```

### 3. File Permissions

```bash
cd /home/marketplace/marketplace/app

# Set ownership
sudo chown -R marketplace:marketplace .

# Set directory permissions
find . -type d -exec chmod 755 {} \;

# Set file permissions
find . -type f -exec chmod 644 {} \;

# Make db/ writable
chmod 775 db

# Protect .env
chmod 600 .env

# Make scripts executable
chmod +x /usr/local/bin/marketplace-cron.sh
```

### 4. Disable PHP Functions

```bash
sudo nano /etc/php/8.1/fpm/php.ini
```

Add/modify:
```ini
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source
```

**Restart PHP-FPM**:
```bash
sudo systemctl restart php8.1-fpm
```

### 5. Verify Error Display is Disabled

**IMPORTANT**: Ensure `display_errors` is disabled in production to prevent information leakage.

The PHP-FPM pool configuration (in step 1 under Web Server Configuration) already sets:
```ini
php_admin_flag[display_errors] = off
php_admin_flag[display_startup_errors] = off
```

**Verify Settings**:
```bash
# Create a test script
cat > /tmp/phpinfo.php << 'EOF'
<?php phpinfo();
EOF

# Check via CLI (note: FPM settings differ from CLI)
php -r "echo 'display_errors: ' . ini_get('display_errors') . PHP_EOL;"

# Check via FPM (temporary test file)
sudo -u marketplace cat > /home/marketplace/marketplace/app/public/_test_phpinfo.php << 'EOF'
<?php phpinfo();
EOF

# Access via browser or curl, check "display_errors" shows "Off"
curl -s https://marketplace.example.com/_test_phpinfo.php | grep -i "display_errors"

# IMPORTANT: Remove test file immediately
sudo rm /home/marketplace/marketplace/app/public/_test_phpinfo.php
```

**Expected Output**: `display_errors` should show `Off` in the PHP-FPM configuration.

**Note**: The `php_admin_flag` directive prevents runtime override, ensuring these settings cannot be changed by application code.

### 5. Secure MariaDB

```bash
sudo mysql -u root -p
```

```sql
-- Remove anonymous users
DELETE FROM mysql.user WHERE User='';

-- Remove remote root login
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');

-- Flush privileges
FLUSH PRIVILEGES;

-- Exit
EXIT;
```

### 6. Enable Automatic Security Updates

```bash
sudo apt install -y unattended-upgrades

sudo dpkg-reconfigure -plow unattended-upgrades
```

Select **Yes** to enable automatic updates.

---

## Monitoring

### 1. System Monitoring

**Install Monitoring Tools**:
```bash
sudo apt install -y htop iotop nethogs
```

**Check System Resources**:
```bash
# CPU and memory
htop

# Disk usage
df -h

# Disk I/O
sudo iotop

# Network usage
sudo nethogs
```

### 2. Application Monitoring

**Check Nginx Status**:
```bash
sudo systemctl status nginx
```

**Check PHP-FPM Status**:
```bash
sudo systemctl status php8.1-fpm
```

**Check MariaDB Status**:
```bash
sudo systemctl status mariadb
```

**Check Cron Execution**:
```bash
tail -f /var/log/marketplace-cron.log
```

### 3. Log Monitoring

**Nginx Access Log**:
```bash
tail -f /var/log/nginx/marketplace-access.log
```

**Nginx Error Log**:
```bash
tail -f /var/log/nginx/marketplace-error.log
```

**PHP-FPM Error Log**:
```bash
tail -f /var/log/php-fpm/marketplace-error.log
```

**MariaDB Error Log**:
```bash
sudo tail -f /var/log/mysql/error.log
```

### 4. Database Monitoring

```bash
mysql -u marketplace_user -p marketplace
```

```sql
-- Check table sizes
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
FROM information_schema.TABLES
WHERE table_schema = 'marketplace'
ORDER BY size_mb DESC;

-- Check transaction counts
SELECT COUNT(*) FROM transactions;
SELECT COUNT(*) FROM transaction_statuses;

-- Check recent transactions
SELECT uuid, current_status, created_at 
FROM v_current_cumulative_transaction_statuses 
ORDER BY created_at DESC 
LIMIT 10;

-- Exit
EXIT;
```

### 5. Set Up Alerts

**Create Alert Script**:
```bash
sudo nano /usr/local/bin/marketplace-alerts.sh
```

```bash
#!/bin/bash

# Check disk space
DISK_USAGE=$(df -h / | awk 'NR==2 {print $5}' | sed 's/%//')
if [ $DISK_USAGE -gt 80 ]; then
    echo "ALERT: Disk usage is ${DISK_USAGE}%" | mail -s "Marketplace Alert" admin@example.com
fi

# Check if cron is running
if ! pgrep -f "marketplace-cron.sh" > /dev/null; then
    echo "ALERT: Marketplace cron is not running" | mail -s "Marketplace Alert" admin@example.com
fi

# Check if Nginx is running
if ! systemctl is-active --quiet nginx; then
    echo "ALERT: Nginx is not running" | mail -s "Marketplace Alert" admin@example.com
fi
```

**Make Executable**:
```bash
sudo chmod +x /usr/local/bin/marketplace-alerts.sh
```

**Add to Crontab**:
```bash
sudo crontab -e
```

Add:
```cron
# Run alerts every 15 minutes
*/15 * * * * /usr/local/bin/marketplace-alerts.sh
```

---

## Backup Strategy

### 1. Database Backup

**Create Backup Script**:
```bash
sudo nano /usr/local/bin/marketplace-backup.sh
```

```bash
#!/bin/bash

# Marketplace backup script

BACKUP_DIR="/home/marketplace/backups"
DATE=$(date +%Y%m%d-%H%M%S)
DB_NAME="marketplace"
DB_USER="marketplace_user"
DB_PASS="YOUR_DB_PASSWORD"

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db-$DATE.sql.gz

# Backup .env file (encrypted)
gpg --encrypt --recipient admin@example.com /home/marketplace/marketplace/app/.env
mv /home/marketplace/marketplace/app/.env.gpg $BACKUP_DIR/env-$DATE.gpg

# Delete backups older than 30 days
find $BACKUP_DIR -name "db-*.sql.gz" -mtime +30 -delete
find $BACKUP_DIR -name "env-*.gpg" -mtime +30 -delete

# Log
echo "Backup completed: $DATE"
```

**Make Executable**:
```bash
sudo chmod +x /usr/local/bin/marketplace-backup.sh
sudo chown marketplace:marketplace /usr/local/bin/marketplace-backup.sh
```

**Add to Crontab**:
```bash
sudo crontab -u marketplace -e
```

Add:
```cron
# Daily backup at 2 AM
0 2 * * * /usr/local/bin/marketplace-backup.sh >> /var/log/marketplace-backup.log 2>&1
```

### 2. Restore from Backup

**Restore Database**:
```bash
# List backups
ls -lh /home/marketplace/backups/

# Restore specific backup
gunzip < /home/marketplace/backups/db-20260131-020000.sql.gz | mysql -u marketplace_user -p marketplace
```

**Restore .env**:
```bash
# Decrypt .env
gpg --decrypt /home/marketplace/backups/env-20260131-020000.gpg > /home/marketplace/marketplace/app/.env

# Set permissions
chmod 600 /home/marketplace/marketplace/app/.env
```

### 3. Off-Site Backup

**Using rsync**:
```bash
# Sync to remote server
rsync -avz --delete /home/marketplace/backups/ user@backup-server:/backups/marketplace/
```

**Using AWS S3**:
```bash
# Install AWS CLI
sudo apt install -y awscli

# Configure AWS credentials
aws configure

# Sync to S3
aws s3 sync /home/marketplace/backups/ s3://your-bucket/marketplace-backups/
```

---

## Troubleshooting

### Common Issues

#### 1. 502 Bad Gateway

**Symptoms**: Nginx returns 502 error

**Diagnosis**:
```bash
# Check PHP-FPM status
sudo systemctl status php8.1-fpm

# Check PHP-FPM error log
sudo tail -f /var/log/php-fpm/marketplace-error.log

# Check Nginx error log
sudo tail -f /var/log/nginx/marketplace-error.log
```

**Solutions**:
```bash
# Restart PHP-FPM
sudo systemctl restart php8.1-fpm

# Check socket permissions
ls -la /run/php/marketplace.sock

# Verify Nginx config
sudo nginx -t
```

#### 2. Database Connection Errors

**Symptoms**: "Connection refused" or "Access denied"

**Diagnosis**:
```bash
# Test database connection
mysql -u marketplace_user -p marketplace

# Check MariaDB status
sudo systemctl status mariadb

# Check MariaDB error log
sudo tail -f /var/log/mysql/error.log
```

**Solutions**:
```bash
# Restart MariaDB
sudo systemctl restart mariadb

# Verify credentials in .env
cat /home/marketplace/marketplace/app/.env | grep DB_

# Check user permissions
mysql -u root -p
```

```sql
SHOW GRANTS FOR 'marketplace_user'@'localhost';
```

#### 3. Cron Not Running

**Symptoms**: Escrow addresses not generated, transactions stuck in PENDING

**Diagnosis**:
```bash
# Check cron log
tail -f /var/log/marketplace-cron.log

# Check crontab
sudo crontab -u marketplace -l

# Run cron manually
sudo su - marketplace
/usr/local/bin/marketplace-cron.sh
```

**Solutions**:
```bash
# Verify Python dependencies
pip list | grep eth-account

# Check .env file
cat /home/marketplace/marketplace/app/.env | grep MNEMONIC

# Check file permissions
ls -la /home/marketplace/marketplace/app/.env
```

#### 4. SSL Certificate Issues

**Symptoms**: "Your connection is not private" or certificate expired

**Diagnosis**:
```bash
# Check certificate expiration
sudo certbot certificates

# Test auto-renewal
sudo certbot renew --dry-run
```

**Solutions**:
```bash
# Renew certificate manually
sudo certbot renew

# Reload Nginx
sudo systemctl reload nginx
```

### Performance Optimization

#### 1. PHP-FPM Tuning

```bash
sudo nano /etc/php/8.1/fpm/pool.d/marketplace.conf
```

Adjust based on available RAM:
```ini
pm.max_children = 50  # Increase for more RAM
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
```

#### 2. MariaDB Tuning

```bash
sudo nano /etc/mysql/mariadb.conf.d/50-server.cnf
```

Add/modify:
```ini
[mysqld]
innodb_buffer_pool_size = 1G  # 50-70% of available RAM
innodb_log_file_size = 256M
max_connections = 200
query_cache_size = 0  # Disable query cache (deprecated)
```

**Restart MariaDB**:
```bash
sudo systemctl restart mariadb
```

#### 3. Nginx Caching

```bash
sudo nano /etc/nginx/sites-available/marketplace
```

Add before `server` block:
```nginx
# Cache configuration
fastcgi_cache_path /var/cache/nginx levels=1:2 keys_zone=marketplace:100m inactive=60m;
fastcgi_cache_key "$scheme$request_method$host$request_uri";
```

Add in `location ~ \.php$` block:
```nginx
fastcgi_cache marketplace;
fastcgi_cache_valid 200 60m;
fastcgi_cache_bypass $http_pragma $http_authorization;
```

**Create Cache Directory**:
```bash
sudo mkdir -p /var/cache/nginx
sudo chown www-data:www-data /var/cache/nginx
```

---

**Document Version**: 1.1  
**Last Updated**: February 3, 2026
