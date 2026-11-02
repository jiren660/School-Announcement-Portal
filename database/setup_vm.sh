#!/bin/bash
# ====================================================================
# Quick Setup Script for VirtualBox VM (Database Server)
# Run this inside your Kali / Ubuntu VirtualBox Terminal:
#   chmod +x setup_vm.sh
#   sudo ./setup_vm.sh
# ====================================================================

echo "=========================================================="
echo " Setting up MariaDB Database inside VirtualBox VM... "
echo "=========================================================="

# 1. Update and install MariaDB Server
echo "[1/5] Installing MariaDB Server..."
apt-get update -y
apt-get install -y mariadb-server

# 2. Allow remote connections (change bind-address to 0.0.0.0)
echo "[2/5] Configuring MariaDB to accept remote connections from WSL..."
CNF_FILES=(
    "/etc/mysql/mariadb.conf.d/50-server.cnf"
    "/etc/mysql/my.cnf"
    "/etc/mysql/mariadb.cnf"
)

for file in "${CNF_FILES[@]}"; do
    if [ -f "$file" ]; then
        sed -i 's/bind-address\s*=\s*127.0.0.1/bind-address = 0.0.0.0/' "$file"
        echo "Updated bind-address in $file"
    fi
done

# Restart MariaDB
systemctl restart mariadb
systemctl enable mariadb

# 3. Create Database & Remote User
echo "[3/5] Creating database 'school_portal' and remote user 'school_user'..."
mysql -u root <<EOF
CREATE DATABASE IF NOT EXISTS school_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'school_user'@'%' IDENTIFIED BY 'SchoolPass123!';
GRANT ALL PRIVILEGES ON school_portal.* TO 'school_user'@'%';

-- Also allow root from any host for troubleshooting if needed
CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY 'rootpassword';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;

FLUSH PRIVILEGES;
EOF

# 4. Import schema if schema.sql exists in current directory
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [ -f "$DIR/schema.sql" ]; then
    echo "[4/5] Importing schema.sql into school_portal..."
    mysql -u root school_portal < "$DIR/schema.sql"
    echo "Schema imported successfully!"
else
    echo "[4/5] schema.sql not found in $DIR. You can import it later."
fi

# 5. Display IP address
VM_IP=$(hostname -I | awk '{print $1}')
echo ""
echo "=========================================================="
echo " SUCCESS! Your Database is ready for WSL connection."
echo "=========================================================="
echo " VM IP Address: $VM_IP"
echo " Port:          3306"
echo " Database:      school_portal"
echo " Username:      school_user"
echo " Password:      SchoolPass123!"
echo ""
echo "-> Now in your WSL '.env' file, set:"
echo "   DB_HOST=$VM_IP"
echo "=========================================================="
