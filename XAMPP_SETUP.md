# XAMPP Setup Guide for Skills Way Website

## ✅ Current Status
Your XAMPP is already running successfully on:
- **Apache**: Port 80 and 443
- **MySQL**: Port 3306

## 🔧 Quick Setup Steps

### 1. Database Setup
1. Open phpMyAdmin: http://localhost/phpmyadmin
2. Click on "Import" tab
3. Choose file: `D:\software\xamp\xampp\htdocs\website\database\setup_xampp.sql`
4. Click "Go" to import

**OR** run the SQL manually:
- Click "SQL" tab in phpMyAdmin
- Copy and paste the contents of `setup_xampp.sql`
- Click "Go"

### 2. Access Your Website
Your website is now available at:
- **Main Site**: http://localhost/website/
- **Admin Login**: http://localhost/website/login.php

### 3. Default Login Credentials
- **Username**: admin
- **Password**: admin123

## 📁 File Locations

### Working Directory (XAMPP)
```
D:\software\xamp\xampp\htdocs\website\
```
**This is where you should work!** XAMPP serves files from here.

### Development Directory (Optional)
```
d:\py\python\website\
```
This appears to be a backup/development copy. You can sync changes between them if needed.

## 🔍 Database Configuration
Updated in `config/database.php`:
- **Host**: localhost
- **Database**: skills_way_vocational
- **Username**: root
- **Password**: (empty - XAMPP default)

## 🚀 Next Steps
1. Import the database using the SQL file
2. Visit http://localhost/website/
3. Login with admin credentials
4. Start developing!

## 📝 Important Notes
- Always work in `D:\software\xamp\xampp\htdocs\website\` for changes to appear
- XAMPP must be running for the website to work
- phpMyAdmin is at http://localhost/phpmyadmin
- Check XAMPP Control Panel to start/stop services

## 🐛 Troubleshooting
If you see database errors:
1. Make sure MySQL is running in XAMPP Control Panel
2. Import the database setup script
3. Check database credentials in `config/database.php`
