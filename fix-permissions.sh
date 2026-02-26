#!/bin/bash

#######################################
# Fix Permissions - Run this dengan sudo
# Untuk mengatasi permission issues
#######################################

echo "🔧 Fixing permissions for auto-deploy..."

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "❌ Error: Please run with sudo"
    echo "   Usage: sudo bash fix-permissions.sh"
    exit 1
fi

PROJECT_DIR="/var/www/html/api-presensi"

# Check if directory exists
if [ ! -d "$PROJECT_DIR" ]; then
    echo "❌ Error: Project directory not found: $PROJECT_DIR"
    echo "   Update PROJECT_DIR in this script"
    exit 1
fi

cd "$PROJECT_DIR" || exit 1

echo "📁 Current directory: $PROJECT_DIR"
echo ""

# Set executable for scripts
echo "✓ Setting executable permissions for scripts..."
chmod +x deploy.sh auto-pull.sh setup-deploy.sh fix-permissions.sh

# Fix storage and cache permissions
echo "✓ Fixing storage permissions..."
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Fix deploy scripts ownership (allow user pkl to run)
echo "✓ Fixing script ownership..."
chown pkl:www-data deploy.sh auto-pull.sh setup-deploy.sh fix-permissions.sh
chmod 775 deploy.sh auto-pull.sh setup-deploy.sh fix-permissions.sh

# Create log files if not exist
echo "✓ Creating log files..."
mkdir -p storage/logs
touch storage/logs/deployment.log
touch storage/logs/auto-pull.log
chmod 664 storage/logs/deployment.log storage/logs/auto-pull.log
chown www-data:www-data storage/logs/*.log

# Set proper group permissions for whole project
echo "✓ Setting group permissions..."
chgrp -R www-data .
find . -type d -exec chmod 775 {} \;
find . -type f -exec chmod 664 {} \;

# Restore executable for scripts
chmod +x deploy.sh auto-pull.sh setup-deploy.sh fix-permissions.sh artisan

echo ""
echo "✅ Permissions fixed!"
echo ""
echo "📋 Summary:"
echo "   - User 'pkl' can run scripts"
echo "   - User 'www-data' can write to storage"
echo "   - Both users in same group"
echo ""
echo "🧪 Test deploy now:"
echo "   bash deploy.sh"
