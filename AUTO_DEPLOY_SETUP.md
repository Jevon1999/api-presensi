# Auto Deploy Setup Guide
## API Presensi PKL - Webhook & Cron Auto Deploy

Ada 2 cara untuk auto-deploy ke VPS:
1. **Webhook (Recommended)** - Real-time deployment saat push ke GitHub
2. **Cronjob** - Check updates setiap X menit

---

## 🎯 Method 1: GitHub Webhook (Recommended)

### Step 1: Setup di VPS

```bash
# SSH ke VPS
ssh user@api.globalintermedia.online

# Masuk ke project directory
cd /var/www/api.globalintermedia.online

# Buat direktori logs jika belum ada
mkdir -p storage/logs

# Set permission untuk deploy script
chmod +x deploy.sh

# Generate secret key untuk webhook
openssl rand -hex 32
# Simpan output ini, contoh: a1b2c3d4e5f6...
```

### Step 2: Set Environment Variable

```bash
# Edit .env
nano .env

# Tambahkan di bawah APP_KEY:
WEBHOOK_SECRET=a1b2c3d4e5f6...your-secret-key
```

### Step 3: Test Webhook Manually

```bash
# Test jika webhook endpoint bisa diakses
curl https://api.globalintermedia.online/deploy-webhook.php
```

### Step 4: Setup GitHub Webhook

1. Buka repository di GitHub: `https://github.com/Jevon1999/api-presensi`
2. Klik **Settings** > **Webhooks** > **Add webhook**
3. Isi form:
   - **Payload URL**: `https://api.globalintermedia.online/deploy-webhook.php`
   - **Content type**: `application/json`
   - **Secret**: Paste secret key dari step 1
   - **SSL verification**: Enable
   - **Which events?**: Just the push event
   - **Active**: ✓ Checked
4. Klik **Add webhook**

### Step 5: Test Deployment

```bash
# Di local, buat commit dan push
echo "# Test webhook" >> README.md
git add .
git commit -m "Test auto deploy webhook"
git push origin main
```

### Step 6: Check Logs di VPS

```bash
# Lihat deployment log
tail -f storage/logs/deployment.log

# Lihat Laravel log jika ada error
tail -f storage/logs/laravel.log
```

---

## 🕐 Method 2: Cronjob Auto Pull

Alternatif jika tidak bisa setup webhook (misalnya local server/no public IP).

### Step 1: Setup Scripts di VPS

```bash
# SSH ke VPS
ssh user@api.globalintermedia.online

cd /var/www/api.globalintermedia.online

# Set permission
chmod +x deploy.sh auto-pull.sh

# Test manual
bash auto-pull.sh
```

### Step 2: Setup Cron Job

```bash
# Edit crontab
crontab -e

# Tambahkan salah satu (pilih interval yang sesuai):

# Check setiap 5 menit
*/5 * * * * /var/www/api.globalintermedia.online/auto-pull.sh

# Check setiap 15 menit
*/15 * * * * /var/www/api.globalintermedia.online/auto-pull.sh

# Check setiap 1 jam
0 * * * * /var/www/api.globalintermedia.online/auto-pull.sh

# Check setiap hari jam 3 pagi
0 3 * * * /var/www/api.globalintermedia.online/auto-pull.sh
```

### Step 3: Monitor Cron Logs

```bash
# Check cron log
tail -f storage/logs/auto-pull.log

# Check system cron log
sudo tail -f /var/log/cron
# atau
sudo tail -f /var/log/syslog | grep CRON
```

---

## 🔒 Security Best Practices

### 1. Protect Webhook Endpoint

Tambahkan di `.env` VPS:
```env
WEBHOOK_SECRET=very-long-random-secret-key-here
```

### 2. Restrict IP Access (Optional)

Edit nginx config:
```nginx
location = /deploy-webhook.php {
    # Only allow GitHub/GitLab IPs
    allow 140.82.112.0/20;  # GitHub
    allow 185.199.108.0/22; # GitHub
    allow 34.74.90.64/28;   # GitLab
    deny all;
    
    fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

### 3. Setup Sudoers (untuk restart PHP-FPM tanpa password)

```bash
sudo visudo

# Tambahkan di bagian bawah (sesuaikan username):
www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart php8.2-fpm
www-data ALL=(ALL) NOPASSWD: /bin/systemctl reload nginx
```

---

## 📋 File Structure

```
api/
├── deploy.sh                      # Main deployment script
├── auto-pull.sh                   # Auto pull cron script
├── public/
│   └── deploy-webhook.php         # Webhook handler
└── storage/
    └── logs/
        ├── deployment.log         # Deployment activity log
        ├── auto-pull.log          # Cron job log
        └── laravel.log            # Laravel errors
```

---

## 🧪 Testing

### Test Webhook Locally

```bash
# Generate test payload
curl -X POST https://api.globalintermedia.online/deploy-webhook.php \
  -H "Content-Type: application/json" \
  -H "X-Hub-Signature-256: sha256=$(echo -n '{"ref":"refs/heads/main"}' | openssl dgst -sha256 -hmac 'YOUR_SECRET' | cut -d' ' -f2)" \
  -H "X-GitHub-Event: push" \
  -d '{"ref":"refs/heads/main","commits":[{"message":"Test"}]}'
```

### Test Deploy Script

```bash
# SSH ke VPS
cd /var/www/api.globalintermedia.online
bash deploy.sh
```

### Monitor Real-time

```bash
# Terminal 1: Watch deployment log
tail -f storage/logs/deployment.log

# Terminal 2: Push commit dari local
git push origin main
```

---

## 🚨 Troubleshooting

### Webhook tidak trigger

```bash
# 1. Check webhook endpoint
curl -I https://api.globalintermedia.online/deploy-webhook.php

# 2. Check GitHub webhook delivery
# Buka GitHub > Repository > Settings > Webhooks > Recent Deliveries

# 3. Check logs
tail -50 storage/logs/deployment.log
```

### Permission errors

```bash
# Fix permissions
sudo chmod +x deploy.sh auto-pull.sh
sudo chmod -R 775 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

### Git pull conflicts

```bash
# Reset local changes di VPS
cd /var/www/api.globalintermedia.online
git reset --hard origin/main
git clean -fd
```

### PHP-FPM restart gagal

```bash
# Check status
sudo systemctl status php8.2-fpm

# Restart manual
sudo systemctl restart php8.2-fpm

# Check error log
sudo tail -f /var/log/php8.2-fpm.log
```

---

## 📊 Monitoring

### Check Deployment History

```bash
# Lihat 50 deployment terakhir
grep "Starting Auto Deployment" storage/logs/deployment.log | tail -50

# Lihat deployment yang error
grep "ERROR" storage/logs/deployment.log | tail -20

# Check ukuran log file
du -sh storage/logs/*.log
```

### Rotate Logs (Recommended)

```bash
# Tambahkan di crontab
0 0 * * 0 find /var/www/api.globalintermedia.online/storage/logs -name "*.log" -mtime +30 -delete
```

---

## ✅ Deployment Checklist

Setiap kali deploy, script otomatis akan:

- ✅ Enable maintenance mode
- ✅ Pull latest code
- ✅ Install composer dependencies (jika ada perubahan)
- ✅ Install npm dependencies (jika ada perubahan)
- ✅ Run database migrations
- ✅ Clear all caches
- ✅ Optimize cache & routes
- ✅ Regenerate Swagger docs
- ✅ Set correct permissions
- ✅ Restart PHP-FPM
- ✅ Restart queue workers (jika ada)
- ✅ Disable maintenance mode

---

## 🔔 Optional: Notification

### Telegram Notification

Edit `deploy.sh`, uncomment bagian notification dan isi:

```bash
# Get Telegram Bot Token dari @BotFather
# Get Chat ID dari @userinfobot

curl -X POST "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/sendMessage" \
  -d "chat_id=<YOUR_CHAT_ID>" \
  -d "text=✅ Auto deployment berhasil!
Project: API Presensi
Time: $(date)
Server: api.globalintermedia.online"
```

### Slack Notification

```bash
curl -X POST <YOUR_SLACK_WEBHOOK_URL> \
  -H "Content-Type: application/json" \
  -d '{"text":"✅ Deployment successful on api.globalintermedia.online"}'
```

---

## 📝 Notes

- **Maintenance mode** mencegah user akses selama deployment
- Access maintenance page: `https://api.globalintermedia.online?secret=deploy-secret-2026`
- Webhook lebih cepat dan efisien daripada cronjob
- Cronjob cocok untuk scheduled updates
- Selalu test di staging dulu sebelum production

---

## 🆘 Emergency Rollback

Jika deployment bermasalah:

```bash
# SSH ke VPS
cd /var/www/api.globalintermedia.online

# Lihat commit terakhir
git log --oneline -5

# Rollback ke commit sebelumnya
git reset --hard <commit-hash>

# Re-run deployment
bash deploy.sh
```
