#!/bin/bash

#######################################
# Auto Deploy Setup & Deploy Script
# One-time setup + manual deploy
#######################################

set -e  # Exit on error

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
PROJECT_DIR="/var/www/html/api-presensi"
LOG_FILE="$PROJECT_DIR/storage/logs/deployment.log"

# Functions
print_header() {
    echo -e "${BLUE}=========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}=========================================${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

#######################################
# SETUP MODE (First time run)
#######################################
if [ "$1" = "setup" ]; then
    print_header "SETUP AUTO-DEPLOY"
    
    cd "$PROJECT_DIR" || exit 1
    
    # Create logs directory
    print_success "Creating log directory..."
    mkdir -p storage/logs
    touch storage/logs/deployment.log
    touch storage/logs/auto-pull.log
    
    # Fix permissions
    print_success "Fixing permissions..."
    if [ "$EUID" -eq 0 ]; then
        # Running as root
        chmod -R 775 storage bootstrap/cache
        chown -R www-data:www-data storage bootstrap/cache
        chown pkl:www-data setup.sh auto-pull.sh
        chmod +x setup.sh auto-pull.sh artisan
    else
        # Running as user
        sudo chmod -R 775 storage bootstrap/cache 2>/dev/null || true
        sudo chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
        sudo chmod +x setup.sh auto-pull.sh artisan 2>/dev/null || true
    fi
    
    # Generate webhook secret
    echo ""
    print_success "Generated Webhook Secret:"
    SECRET=$(openssl rand -hex 32)
    echo ""
    echo "  $SECRET"
    echo ""
    print_warning "Add this to .env:"
    echo "  WEBHOOK_SECRET=$SECRET"
    echo ""
    
    # Test webhook endpoint
    print_success "Testing webhook endpoint..."
    WEBHOOK_URL="https://api.globalintermedia.online/deploy-webhook.php"
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$WEBHOOK_URL" 2>/dev/null || echo "000")
    
    if [ "$HTTP_CODE" = "400" ] || [ "$HTTP_CODE" = "401" ]; then
        print_success "Webhook endpoint OK (Status: $HTTP_CODE)"
    else
        print_warning "Webhook status: $HTTP_CODE (setup webhook di GitHub)"
    fi
    
    echo ""
    print_header "SETUP COMPLETED"
    echo ""
    echo "Next steps:"
    echo "  1. Add WEBHOOK_SECRET to .env file"
    echo "  2. Setup GitHub webhook:"
    echo "     URL: $WEBHOOK_URL"
    echo "     Secret: (use generated secret)"
    echo "     Events: Push events only"
    echo ""
    echo "  3. Or setup cronjob:"
    echo "     crontab -e"
    echo "     */5 * * * * $PROJECT_DIR/auto-pull.sh"
    echo ""
    echo "  4. Test deployment:"
    echo "     bash setup.sh deploy"
    echo ""
    
    exit 0
fi

#######################################
# DEPLOY MODE (Manual deploy)
#######################################
if [ "$1" = "deploy" ]; then
    print_header "STARTING DEPLOYMENT"
    
    cd "$PROJECT_DIR" || exit 1
    
    # Check if git repo
    if [ ! -d ".git" ]; then
        print_error "Not a git repository!"
        exit 1
    fi
    
    # Maintenance mode
    log "Enabling maintenance mode..."
    php artisan down --retry=60 --secret="deploy-2026" 2>/dev/null || true
    
    # Stash changes
    log "Stashing local changes..."
    git stash 2>/dev/null || true
    
    # Pull code
    log "Pulling latest code..."
    git pull origin main || {
        print_error "Failed to pull code"
        php artisan up
        exit 1
    }
    
    # Check composer changes
    if git diff HEAD@{1} --name-only 2>/dev/null | grep -q "composer.json"; then
        log "Installing composer dependencies..."
        composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
    fi
    
    # Check npm changes
    if git diff HEAD@{1} --name-only 2>/dev/null | grep -q "package.json"; then
        log "Installing npm dependencies..."
        npm install --production && npm run build
    fi
    
    # Migrations
    log "Running migrations..."
    php artisan migrate --force 2>/dev/null || true
    
    # Clear caches
    log "Clearing caches..."
    php artisan config:clear
    php artisan cache:clear
    php artisan route:clear
    php artisan view:clear
    
    # Optimize
    log "Optimizing..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    
    # Swagger docs
    log "Regenerating Swagger docs..."
    php artisan l5-swagger:generate 2>/dev/null || true
    
    # Restart PHP-FPM
    log "Restarting PHP-FPM..."
    sudo systemctl restart php8.2-fpm 2>/dev/null || print_warning "Could not restart PHP-FPM"
    
    # Queue workers
    if pgrep -f "artisan queue:work" > /dev/null; then
        log "Restarting queue workers..."
        php artisan queue:restart
    fi
    
    # Disable maintenance
    log "Disabling maintenance mode..."
    php artisan up
    
    print_header "DEPLOYMENT COMPLETED"
    log "Deployment completed successfully!"
    
    exit 0
fi

#######################################
# HELP / DEFAULT
#######################################
echo "Usage:"
echo "  bash setup.sh setup   - First time setup"
echo "  bash setup.sh deploy  - Deploy/update application"
echo ""
echo "Examples:"
echo "  # First time"
echo "  bash setup.sh setup"
echo ""
echo "  # Deploy manually"
echo "  bash setup.sh deploy"
echo ""

exit 0
