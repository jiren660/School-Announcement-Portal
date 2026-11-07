# 🎓 School Announcement Portal (International State College of the Philippines)

A modern, high-performance School Announcement & Bulletin Board System built with **PHP** and **MySQL / MariaDB**.

This project implements a distributed architecture where:
* 💻 **Web Application (Frontend + Backend):** Runs inside **WSL (Windows Subsystem for Linux)**.
* 🗄️ **Database Server:** Runs inside an **Oracle VirtualBox Virtual Machine (Kali Linux / Ubuntu)**.
* 🤝 **Team Collaboration:** Version controlled and synchronized via **GitHub**.

---

## 🏗️ System Architecture

```
[ VirtualBox VM (Kali/Ubuntu) ]                    [ WSL 2 (Kali/Ubuntu) ]
   🗄️ MariaDB / MySQL Server                          💻 PHP Web Server (Port 8000)
   (school_portal database)            <===========>  (Frontend, Backend, Admin Panel)
   IP: 192.168.56.x:3306                              Accessible via http://localhost:8000
```

---

## 🚀 Quick Start Guide

### Step 1: Set up the Database in VirtualBox VM
1. Start your VirtualBox VM.
2. In the VM terminal, check your IP address:
   ```bash
   hostname -I
   ```
   *(Note down the IP, e.g., `192.168.56.101`)*
3. Copy and run the automated VM setup script:
   ```bash
   chmod +x database/setup_vm.sh
   sudo ./database/setup_vm.sh
   ```
   *Alternatively, manually import `database/schema.sql`:*
   ```bash
   sudo systemctl start mariadb
   mysql -u root < database/schema.sql
   ```

---

### Step 2: Configure Environment in WSL
1. In WSL, navigate to this project directory:
   ```bash
   cd /mnt/c/Users/Admin/.gemini/antigravity-ide/scratch/School-Announcement-Portal
   ```
2. Copy `.env.example` to `.env` (if not already created):
   ```bash
   cp .env.example .env
   ```
3. Update `DB_HOST` in `.env` to match your VirtualBox VM's IP address:
   ```env
   DB_HOST=192.168.56.101
   DB_PORT=3306
   DB_DATABASE=school_portal
   DB_USERNAME=school_user
   DB_PASSWORD=SchoolPass123!
   ```

---

### Step 3: Run the Web Application
Inside your WSL terminal, start PHP's built-in server:
```bash
php -S 0.0.0.0:8000
```
Open your browser on Windows and navigate to:
👉 **http://localhost:8000**

---

## 🔐 Admin Account Setup

* **URL:** `http://localhost:8000/admin/login.php`
* Admin sign-in checks credentials against the `admins` table; no demo account is used.
* Create a password hash in WSL using PHP:
  ```bash
  php -r 'echo password_hash("CHOOSE_A_STRONG_PASSWORD", PASSWORD_DEFAULT), PHP_EOL;'
  ```
* Insert an administrator into the database, replacing the example values and hash:
  ```sql
  INSERT INTO admins (username, password_hash, full_name, email, role)
  VALUES ('your_username', 'PASTE_GENERATED_HASH_HERE', 'Your Name', 'you@example.com', 'Super Admin');
  ```
* Run the SQL in the `school_portal` database using an authorized database account. Keep the password hash private, and do not store your real password in the SQL statement.

---

## 👥 Team Collaboration Workflow (GitHub)

### For Person 2 (Your Partner):
1. Open WSL on their laptop and clone the repository:
   ```bash
   git clone https://github.com/jiren660/School-Announcement-Portal.git
   cd School-Announcement-Portal
   ```
2. Create their own `.env` file with their VM's IP:
   ```bash
   cp .env.example .env
   nano .env
   ```
3. Import `database/schema.sql` into their local VirtualBox VM.
4. Run the web server:
   ```bash
   php -S 0.0.0.0:8000
   ```

### Daily Git Routine:
* **Before working:** Pull the latest changes:
  ```bash
  git pull origin main
  ```
* **After finishing a task:** Commit and push:
  ```bash
  git add .
  git commit -m "Describe what feature or fix you made"
  git push origin main
  ```

---

## 📁 Project Directory Structure

```
School-Announcement-Portal/
├── admin/                     # Admin Management Panel
│   ├── create.php             # New announcement form
│   ├── delete.php             # Delete handler
│   ├── edit.php               # Edit announcement form
│   ├── index.php              # Admin dashboard with stats
│   ├── login.php              # Admin sign-in screen
│   └── logout.php             # Session logout
├── assets/
│   ├── css/
│   │   └── style.css          # Glassmorphic responsive design
│   └── js/
│       └── app.js             # Live search, filters & modal scripts
├── config/
│   └── database.php           # PDO database connection & .env loader
├── database/
│   ├── schema.sql             # SQL tables, relations, and seed data
│   └── setup_vm.sh            # 1-click VM database auto-installer
├── .env                       # Local DB credentials (ignored by Git)
├── .env.example               # Template environment configuration
├── .gitignore                 # Excludes .env, logs, and temp files
├── index.php                  # Student Announcement Feed & Public Portal
└── README.md                  # Documentation and setup manual
```
