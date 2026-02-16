# Apparels Collection - Setup Guide

## Prerequisites
- XAMPP installed on your system
- Web browser

## Installation Steps

1. **Copy Files to XAMPP Directory**
   - Copy the entire `apparels-collection` folder to your XAMPP `htdocs` directory
   - Usually located at: `C:\xampp\htdocs\`

2. **Start XAMPP Services**
   - Open XAMPP Control Panel
   - Start Apache and MySQL services

3. **Access the Application**
   - Open your web browser
   - Navigate to: `http://localhost/apparels-collection`
   - The database tables will be created automatically on first access

4. **Login Credentials**
   - Default Admin Account:
     - Username: `admin`
     - Password: `admin123`

## Folder Structure
```
apparels-collection/
├── admin/              # Admin pages
├── agent/              # Agent pages  
├── css/                # Stylesheets
├── images/             # App icons
├── includes/           # PHP includes
├── js/                 # JavaScript files
├── uploads/            # Receipt images
├── config.php          # Database configuration
├── index.php           # Main redirect
├── login.php           # Login page
└── ...
```

## Features Overview

### For Admins (5 pages):
1. **Dashboard** - Total collections, agents in transit, completed orders, export data
2. **Assignments** - Assign shops to agents, Excel import capability
3. **Agents** - View agent collections, pending items
4. **Management** - Add/remove agents/admins
5. **Store Data** - Add/remove stores/regions, approve bank submissions

### For Agents (3 pages):
1. **Dashboard** - Daily targets, store assignments, collection percentage
2. **Store** - Record collections, upload receipt images, add comments
3. **Submissions** - Submit bank deposits for approval

## PWA Functionality
- The application is installable as a Progressive Web App (PWA)
- Works offline with cached resources
- Auto-syncs when internet connection is restored

## Security Features
- Password-protected access
- Role-based permissions
- Input validation
- File upload security

## Data Management
- Automatic backup of data every 5 minutes to local storage
- Automatic cleanup of data older than 2 days
- Export functionality for daily/weekly reports
- Image uploads for receipts and documentation

## Troubleshooting
- If you get a database error, make sure MySQL is running in XAMPP
- Clear browser cache if PWA features aren't loading correctly
- Check that the `uploads` folder has write permissions