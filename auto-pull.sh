#!/bin/bash

#######################################
# Auto Pull Cron Job Script
# Checks for updates and pulls if available
#######################################

PROJECT_DIR="/var/www/api.globalintermedia.online"
LOG_FILE="$PROJECT_DIR/storage/logs/auto-pull.log"
LOCK_FILE="$PROJECT_DIR/storage/auto-pull.lock"

# Check if another instance is running
if [ -f "$LOCK_FILE" ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Another instance is running" >> "$LOG_FILE"
    exit 0
fi

# Create lock file
touch "$LOCK_FILE"

# Function to cleanup on exit
cleanup() {
    rm -f "$LOCK_FILE"
}
trap cleanup EXIT

# Change to project directory
cd "$PROJECT_DIR" || exit 1

# Fetch latest changes
git fetch origin main &> /dev/null

# Check if there are updates
LOCAL=$(git rev-parse @)
REMOTE=$(git rev-parse @{u})

if [ "$LOCAL" = "$REMOTE" ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] No updates available" >> "$LOG_FILE"
    exit 0
fi

# Updates available, run deployment
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Updates detected, starting deployment..." >> "$LOG_FILE"

# Execute deployment script
bash "$PROJECT_DIR/deploy.sh"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Auto-pull completed" >> "$LOG_FILE"

exit 0
