#!/bin/bash

#######################################
# Quick Setup Script untuk VPS
# Run this once after first deployment
#######################################

echo "🚀 Setting up Auto-Deploy..."
echo ""

# Set executable permission
echo "📝 Setting executable permissions..."
chmod +x deploy.sh
chmod +x auto-pull.sh

# Create log directory
echo "📁 Creating log directory..."
mkdir -p storage/logs
touch storage/logs/deployment.log
touch storage/logs/auto-pull.log

# Set proper permissions
echo "🔒 Setting proper permissions..."
chmod -R 775 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache

# Generate webhook secret
echo ""
echo "🔐 Generate webhook secret key:"
SECRET=$(openssl rand -hex 32)
echo "   $SECRET"
echo ""
echo "⚠️  Add this to your .env file:"
echo "   WEBHOOK_SECRET=$SECRET"
echo ""

# Test webhook endpoint
echo "🧪 Testing webhook endpoint..."
WEBHOOK_URL="https://api.globalintermedia.online/deploy-webhook.php"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$WEBHOOK_URL")

if [ "$HTTP_CODE" -eq 400 ] || [ "$HTTP_CODE" -eq 401 ]; then
    echo "✅ Webhook endpoint is accessible (Status: $HTTP_CODE)"
else
    echo "⚠️  Webhook endpoint returned status: $HTTP_CODE"
fi

echo ""
echo "📋 Next steps:"
echo "   1. Add WEBHOOK_SECRET to .env"
echo "   2. Setup GitHub webhook:"
echo "      URL: $WEBHOOK_URL"
echo "      Secret: (use the generated secret above)"
echo "   3. Or setup cronjob:"
echo "      */5 * * * * $(pwd)/auto-pull.sh"
echo ""
echo "📖 Read AUTO_DEPLOY_SETUP.md for detailed instructions"
echo ""
echo "✅ Setup completed!"
