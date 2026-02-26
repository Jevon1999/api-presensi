# 🚀 Auto Deploy - Setup Guide

Simple auto-deployment untuk API Presensi PKL.

## ⚡ Quick Setup (3 Langkah)

### 1. Setup di VPS

```bash
# SSH ke VPS
ssh pkl@api.globalintermedia.online

# Masuk ke project
cd /var/www/html/api-presensi

# Run setup
bash setup.sh setup
```

Output akan memberikan `WEBHOOK_SECRET` - simpan ini!

### 2. Edit .env

```bash
nano .env

# Tambahkan di bawah APP_KEY:
WEBHOOK_SECRET=paste-secret-dari-setup
```

### 3. Setup GitHub Webhook

1. Buka: https://github.com/Jevon1999/api-presensi/settings/hooks
2. Add webhook:
   - **Payload URL:** `https://api.globalintermedia.online/deploy-webhook.php`
   - **Content type:** `application/json`
   - **Secret:** Paste secret dari step 1
   - **Events:** Just the push event
3. Save

## ✅ Done!

Sekarang setiap kali push ke `main` branch, otomatis deploy ke VPS.

---

## 📝 Commands

```bash
# Setup (first time only)
bash setup.sh setup

# Manual deploy
bash setup.sh deploy

# Monitor logs
tail -f storage/logs/deployment.log
```

---

## 🕐 Alternative: Cronjob

Jika tidak bisa setup webhook:

```bash
crontab -e

# Check update setiap 5 menit
*/5 * * * * /var/www/html/api-presensi/auto-pull.sh
```

---

## 🧪 Test

```bash
# Local: Push code
git add .
git commit -m "Test deploy"
git push origin main

# VPS: Watch logs
tail -f storage/logs/deployment.log
```

---

## 📦 Files

- `setup.sh` - Setup & manual deploy
- `deploy.sh` - Auto deploy script (dipanggil webhook/cron)
- `auto-pull.sh` - Cron script
- `public/deploy-webhook.php` - Webhook handler

---

## 🔧 Troubleshooting

### Permission error
```bash
# Run setup dengan sudo
sudo bash setup.sh setup
```

### Webhook tidak trigger
```bash
# Check GitHub webhook deliveries
# Settings > Webhooks > Recent Deliveries

# Check logs
tail -50 storage/logs/deployment.log
```

### Manual deploy gagal
```bash
# Reset dan coba lagi
cd /var/www/html/api-presensi
git reset --hard origin/main
bash setup.sh deploy
```

---

## 🎯 Deployment Process

Otomatis akan:
1. ✅ Maintenance mode
2. ✅ Pull code
3. ✅ Install dependencies
4. ✅ Run migrations
5. ✅ Clear cache
6. ✅ Optimize
7. ✅ Restart PHP-FPM
8. ✅ Back online

Total: ~30-60 detik

---

**That's it!** Simple, bukan? 🎉
