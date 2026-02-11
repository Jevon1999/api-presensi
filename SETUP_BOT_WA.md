# Setup Bot WhatsApp Presensi dengan WAHA

## 📋 Prerequisites

1. Docker dan Docker Compose terinstall
2. Laravel API berjalan di port 8000
3. WAHA (WhatsApp HTTP API) akan berjalan di port 3000

## 🚀 Langkah-langkah Setup

### 1. Install dan Jalankan WAHA dengan Docker

```bash
# Pull image WAHA
docker pull devlikeapro/waha

# Jalankan WAHA di port 3000
docker run -d \
  --name waha \
  -p 3000:3000 \
  -v $(pwd)/sessions:/app/.sessions \
  -e WHATSAPP_HOOK_EVENTS=message,session.status \
  devlikeapro/waha

# Cek status container
docker ps
docker logs waha
```

### 2. Scan QR Code WhatsApp

Buka browser dan akses: `http://localhost:3000`

#### Mendapatkan QR Code:
```bash
# Gunakan curl atau Postman
curl -X GET http://localhost:3000/api/sessions/default

# Atau akses di browser
# http://localhost:3000/api/default/auth/qr
```

Scan QR code dengan WhatsApp yang akan dijadikan bot.

### 3. Konfigurasi Database Bot

Jalankan seeder atau insert manual ke tabel `bot_configs`:

```bash
# Via artisan
php artisan db:seed --class=BotConfigSeeder
```

Atau insert manual ke database:

```sql
INSERT INTO bot_configs (
    id,
    waha_api_url,
    waha_api_key,
    waha_session_name,
    webhook_url,
    webhook_secret,
    webhook_events,
    reminder_enabled,
    typing_delay_ms,
    mark_messages_read,
    reject_calls,
    message_greeting,
    message_success_check_in,
    message_success_check_out,
    message_already_checked_in,
    is_active,
    created_at,
    updated_at
) VALUES (
    1,
    'http://localhost:3000',
    NULL,
    'default',
    'http://localhost:8000/api/waha/webhook',
    NULL,
    '["message"]',
    1,
    500,
    1,
    0,
    'Halo! 👋 Selamat datang di Bot Presensi.',
    '✅ *Check-in Berhasil!*\n\n',
    '✅ *Check-out Berhasil!*\n\n',
    'Kamu sudah check-in hari ini.',
    1,
    NOW(),
    NOW()
);
```

### 4. Setup Webhook di WAHA

Daftarkan webhook URL ke WAHA:

```bash
curl -X POST http://localhost:3000/api/webhooks \
  -H "Content-Type: application/json" \
  -d '{
    "url": "http://localhost:8000/api/waha/webhook",
    "events": ["message"],
    "hmac": null,
    "retries": null,
    "customHeaders": null
  }'
```

**PENTING:** Jika Laravel berjalan di Docker juga, gunakan:
- `http://host.docker.internal:8000/api/waha/webhook` (untuk Docker Desktop di Mac/Windows)
- Atau gunakan IP address host machine

### 5. Registrasi Member dengan Nomor HP

Pastikan member sudah terdaftar dengan format nomor HP yang benar:

```sql
-- Contoh insert member
INSERT INTO members (
    no_hp,
    office_id,
    nama_lengkap,
    jenis_kelamin,
    asal_sekolah,
    tanggal_mulai_magang,
    tanggal_selesai_magang,
    status_aktif,
    created_by,
    created_at,
    updated_at
) VALUES (
    '6281234567890',  -- Format: 62 + nomor tanpa 0 di depan
    1,
    'John Doe',
    'L',
    'SMK Example',
    '2026-02-01',
    '2026-05-01',
    1,
    1,
    NOW(),
    NOW()
);
```

**Format Nomor HP:**
- Harus dimulai dengan `62` (kode negara Indonesia)
- Contoh: `6281234567890` (dari 081234567890)
- Bot akan otomatis normalisasi format

## 🤖 Cara Menggunakan Bot

Pengguna dapat mengirim pesan WhatsApp ke nomor bot dengan perintah:

### Perintah Check-in:
- `masuk`
- `checkin`
- `check in`
- `absen`
- `hadir`

### Perintah Check-out:
- `keluar`
- `checkout`
- `check out`
- `pulang`

### Perintah Cek Status:
- `status`
- `cek`
- `info`

### Perintah Help:
- `help`
- `bantuan`
- `menu`
- `mulai`
- `start`

## 📝 Contoh Interaksi

```
User: masuk
Bot: ✅ Check-in Berhasil!

     Nama: John Doe
     Kantor: Kantor Pusat
     Waktu: 08:30 WIB
     Tanggal: 11/02/2026

     Selamat bekerja! 💪

User: status
Bot: 📊 Status Kehadiran Hari Ini

     Nama: John Doe
     Kantor: Kantor Pusat
     Tanggal: 11/02/2026

     ✅ Check-in: 08:30 WIB
     ❌ Check-out: Belum

     Ketik keluar untuk check-out.

User: keluar
Bot: ✅ Check-out Berhasil!

     Nama: John Doe
     Check-in: 08:30 WIB
     Check-out: 17:00 WIB
     Durasi Kerja: 8 jam 30 menit

     Terima kasih atas kerja keras kamu hari ini! 🎉
```

## 🔧 Testing Webhook

Test webhook secara manual:

```bash
curl -X POST http://localhost:8000/api/waha/webhook \
  -H "Content-Type: application/json" \
  -d '{
    "event": "message",
    "session": "default",
    "payload": {
      "id": "test123",
      "timestamp": 1234567890,
      "from": "6281234567890@c.us",
      "fromMe": false,
      "body": "masuk",
      "hasMedia": false
    }
  }'
```

## 📊 Monitoring

### Cek Log Webhook
```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Filter hanya webhook
tail -f storage/logs/laravel.log | grep "WAHA Webhook"
```

### Cek Status WAHA
```bash
# Cek sessions aktif
curl http://localhost:3000/api/sessions

# Cek webhooks terdaftar
curl http://localhost:3000/api/webhooks

# Cek logs container
docker logs waha -f
```

## 🐛 Troubleshooting

### Bot tidak merespon
1. Cek WAHA container: `docker ps`
2. Cek logs: `docker logs waha`
3. Cek webhook terdaftar: `curl http://localhost:3000/api/webhooks`
4. Cek Laravel logs: `tail -f storage/logs/laravel.log`

### Webhook tidak sampai ke Laravel
1. Pastikan URL webhook benar (gunakan `host.docker.internal` jika Laravel di Docker)
2. Test dengan ngrok jika development: `ngrok http 8000`
3. Cek firewall dan network

### Member tidak ditemukan
1. Cek format nomor HP di database (harus `62xxx`)
2. Cek status_aktif = 1
3. Cek logs untuk melihat nomor yang diterima

### QR Code tidak muncul
1. Restart container: `docker restart waha`
2. Reset session:
   ```bash
   curl -X DELETE http://localhost:3000/api/sessions/default
   curl -X POST http://localhost:3000/api/sessions/default/start
   ```

## 🔐 Keamanan (Production)

Untuk production, tambahkan keamanan:

1. **Gunakan HTTPS** untuk webhook URL
2. **Tambahkan HMAC signature** di WAHA webhook
3. **Set API Key** di WAHA (environment variable)
4. **Gunakan reverse proxy** (Nginx) untuk Laravel
5. **Batasi akses** dengan firewall

## 📦 Docker Compose (Opsional)

Buat `docker-compose.yml` untuk menjalankan semua service:

```yaml
version: '3.8'

services:
  waha:
    image: devlikeapro/waha
    container_name: waha
    ports:
      - "3000:3000"
    volumes:
      - ./sessions:/app/.sessions
    environment:
      - WHATSAPP_HOOK_EVENTS=message,session.status
    restart: unless-stopped

  # Tambahkan service lain jika perlu
```

Jalankan dengan:
```bash
docker-compose up -d
```

## 🎯 Fitur Bot

✅ Check-in otomatis via WhatsApp
✅ Check-out dengan perhitungan jam kerja
✅ Cek status kehadiran real-time
✅ Normalisasi format nomor HP otomatis
✅ Typing indicator untuk UX lebih baik
✅ Mark message as read
✅ Pesan kustomisasi via database
✅ Multi-perintah support (alias)
✅ Logging lengkap untuk debugging

## 📞 Support

Jika ada pertanyaan atau issue, cek:
- Laravel logs: `storage/logs/laravel.log`
- WAHA logs: `docker logs waha`
- Database bot_configs untuk konfigurasi

---

**Happy Coding! 🚀**
