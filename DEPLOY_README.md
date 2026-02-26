# 🚀 Auto Deploy - Quick Start

Auto deployment untuk API Presensi PKL dengan webhook atau cronjob.

## 📦 Files

- `deploy-webhook.php` - Webhook handler di `/public`
- `deploy.sh` - Main deployment script
- `auto-pull.sh` - Cron auto-pull script
- `setup-deploy.sh` - Quick setup helper
- `AUTO_DEPLOY_SETUP.md` - Full documentation

## ⚡ Quick Setup (5 menit)

### Di VPS:

```bash
# 1. SSH ke VPS
ssh user@api.globalintermedia.online

# 2. Masuk ke project directory
cd /var/www/api.globalintermedia.online

# 3. Run setup script
bash setup-deploy.sh

# 4. Edit .env, tambahkan WEBHOOK_SECRET
nano .env

# 5. Test manual deployment
bash deploy.sh
```

### Di GitHub:

```
1. Buka: https://github.com/Jevon1999/api-presensi/settings/hooks
2. Add webhook:
   - Payload URL: https://api.globalintermedia.online/deploy-webhook.php
   - Content type: application/json
   - Secret: (dari output setup-deploy.sh)
   - Events: Just the push event
3. Save
```

## 🎯 Pilihan Deploy

### Option 1: GitHub Webhook (Recommended)
✅ Real-time deployment saat push  
✅ Tidak perlu cron  
✅ Lebih efisien  

### Option 2: Cronjob
✅ No webhook setup needed  
✅ Scheduled checks  
⚠️ Delay 5-15 menit  

```bash
# Setup cron (check setiap 5 menit)
crontab -e

# Tambahkan:
*/5 * * * * /var/www/api.globalintermedia.online/auto-pull.sh
```

## 📝 Test

```bash
# Local: Push code
git add .
git commit -m "Test auto deploy"
git push origin main

# VPS: Monitor logs
tail -f storage/logs/deployment.log
```

## 🔍 Monitoring

```bash
# Deployment log
tail -f storage/logs/deployment.log

# Auto-pull log
tail -f storage/logs/auto-pull.log

# Laravel errors
tail -f storage/logs/laravel.log
```

## 📖 Full Documentation

Baca [AUTO_DEPLOY_SETUP.md](./AUTO_DEPLOY_SETUP.md) untuk:
- Security best practices
- Troubleshooting guide
- Notification setup
- Emergency rollback

## ⏱️ Deployment Process

Script otomatis akan:
1. ✅ Enable maintenance mode
2. ✅ Pull latest code
3. ✅ Install dependencies (jika berubah)
4. ✅ Run migrations
5. ✅ Clear & optimize cache
6. ✅ Regenerate Swagger docs
7. ✅ Set permissions
8. ✅ Restart PHP-FPM
9. ✅ Disable maintenance mode

Total waktu: ~30-60 detik

## 🔐 Security

- Secret key validation
- IP whitelist support
- Maintenance mode during deploy
- Lock file prevention

## 🆘 Emergency

```bash
# Rollback ke commit sebelumnya
git reset --hard HEAD~1
bash deploy.sh

# Disable auto-deploy
# Hapus webhook di GitHub atau disable cron
```

---

**Need help?** Baca dokumentasi lengkap atau check logs.
