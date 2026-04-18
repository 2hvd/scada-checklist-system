# SCADA Checklist System

A complete web-based SCADA commissioning checklist management system built with PHP, MySQL, HTML/CSS/JS — designed to run on XAMPP.

## Features

- **Three-role authentication**: Admin, Support, System User (Technician)
- **SWO (Site Work Order) lifecycle** management: Draft → Pending → Registered → In Progress → Submitted → Completed/Closed
- **24-item structured checklist** across 3 phases: Configuration, Commissioning, After Commissioning
- **Real-time progress tracking** with color-coded status indicators
- **Role-based dashboards** tailored for each user type
- **Comments system** for collaboration and review notes
- **CSV export** for checklist reporting
- **Audit logging** for all status changes
- **Session management** for authenticated access
- **Responsive design** — works on desktop and mobile

## Quick Start (XAMPP)

### 1. Install & Place Files

```
XAMPP htdocs/
└── scada-checklist-system/   ← place entire project here
```

### 2. Start XAMPP Services

Start **Apache** and **MySQL** in the XAMPP Control Panel.

### 3. Create the Database

Open **phpMyAdmin** (`http://localhost/phpmyadmin`) and run:

```sql
-- Step 1: Create schema
SOURCE /path/to/scada-checklist-system/database/schema.sql;

-- Step 2: Insert sample data
SOURCE /path/to/scada-checklist-system/database/sample_data.sql;
```

Or import via phpMyAdmin → Import tab.

### 4. Set Passwords

Visit: `http://localhost/scada-checklist-system/setup_passwords.php`

This sets correct bcrypt hashes for the demo accounts. **Delete this file in production.**

### 5. Login

Open: `http://localhost/scada-checklist-system/`

| Username | Password   | Role    |
|----------|------------|---------|
| admin    | admin123   | Admin   |
| support  | support123 | Support |
| user1    | user123    | User    |

## File Structure

```
scada-checklist-system/
├── index.php                    # Entry point / Login
├── setup_passwords.php          # One-time password setup (delete after use)
├── config/
│   ├── db_config.php           # Database configuration
│   └── functions.php           # Utility functions
├── api/
│   ├── auth/                   # Login, logout, session check
│   ├── swo/                    # SWO CRUD, approve, reject, assign
│   ├── checklist/              # Checklist status updates, submit, withdraw
│   ├── comments/               # Add and get comments
│   ├── dashboard/              # Role-specific dashboard data
│   └── export/                 # CSV export
├── assets/
│   ├── css/                    # style.css, dashboard.css, responsive.css
│   └── js/                     # api.js, auth.js, checklist.js, dashboard.js, etc.
├── views/
│   ├── login.html
│   ├── admin/                  # Admin dashboard, SWO management, statistics
│   ├── support/                # Create SWO, my SWOs, review submissions
│   ├── user/                   # Dashboard, checklist editor
│   └── components/             # Shared header, sidebar, footer
└── database/
    ├── schema.sql              # Full database schema
    └── sample_data.sql         # Demo data (3 users, 3 SWOs)
```

## SWO Lifecycle

```
Support Creates SWO (Draft)
        ↓
Support Submits → Pending Admin Review
        ↓
Admin Approves → Registered  (or Rejects → back to Draft)
        ↓
Admin Assigns to User → In Progress
        ↓
User Completes Checklist → Submitted
        ↓
Support Reviews → Completed/Closed  (or requests changes → back to In Progress)
```

## Checklist Items (24 total)

**Section 1: During Configuration** (8 items)  
**Section 2: During Commissioning** (8 items)  
**Section 3: After Commissioning** (8 items)

Status options: `empty (—)`, `done`, `na`, `not_yet`, `still`  
Progress = (done + na) / 24 × 100%

## Configuration

Edit `config/db_config.php` to change database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // Set your MySQL password here
define('DB_NAME', 'scada_checklist');
```

## Security Notes

- All API endpoints use prepared statements (SQL injection protection)
- Passwords stored as bcrypt hashes
- Role-based access control on all endpoints
- Active session validation on protected pages
- Delete `setup_passwords.php` after initial setup
