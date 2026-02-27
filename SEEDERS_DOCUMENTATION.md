# Database Seeders - API Presensi PKL

## Daftar Seeders

1. **UserSeeder** - Generate admin dan user accounts
2. **OfficeSeeder** - Generate data kantor dan lokasi
3. **BotConfigSeeder** - Konfigurasi WhatsApp Bot
4. **MemberSeeder** - Generate data peserta PKL
5. **AttendanceSeeder** - Generate data kehadiran 30 hari terakhir
6. **ProgressSeeder** - Generate laporan progress harian

## Cara Menjalankan Seeders

### Fresh Migration + Seed (Hapus semua data lama)
```bash
php artisan migrate:fresh --seed
```

### Seed saja (tanpa migrasi ulang)
```bash
php artisan db:seed
```

### Seed specific seeder
```bash
php artisan db:seed --class=UserSeeder
php artisan db:seed --class=AttendanceSeeder
```

## Data yang Dihasilkan

### 👤 Users (10 accounts)
| Email | Password | Role | Status |
|-------|----------|------|--------|
| admin@globalintermedia.online | admin123 | admin | active |
| admin.jakarta@globalintermedia.online | admin123 | admin | active |
| admin.bandung@globalintermedia.online | admin123 | admin | active |
| admin.surabaya@globalintermedia.online | admin123 | admin | active |
| hr@globalintermedia.online | user123 | user | active |
| supervisor@globalintermedia.online | user123 | user | active |
| manager@globalintermedia.online | user123 | user | active |
| admin@test.com | password | admin | active |
| user@test.com | password | user | active |
| inactive@test.com | password | user | inactive |

### 🏢 Offices (3 kantor)
- **HQ001** - Kantor Pusat Jakarta
- **BDG001** - Kantor Cabang Bandung
- **SBY001** - Kantor Cabang Surabaya

Setiap office punya lokasi dengan geofencing radius.

### 👨‍🎓 Members (6 peserta PKL)
- 2 member di Jakarta (Budi Santoso, Siti Rahayu)
- 2 member di Bandung (Andi Wijaya, Dewi Lestari)
- 1 member di Surabaya (Rudi Hartono)
- 1 member non-aktif untuk testing

### 📅 Attendances
- Data kehadiran untuk **30 hari terakhir** (skip weekend)
- Status random dengan distribusi:
  - 85% hadir (dengan waktu check-in & check-out)
  - 7% izin
  - 5% sakit
  - 3% alpha
- Data hari ini:
  - Member 1: Sudah check-in & check-out
  - Member 2: Baru check-in
  - Member 3: Belum absen

### 📝 Progresses
- Laporan progress harian untuk member yang hadir
- 80% dari hari hadir ada laporan progress
- 1-3 aktivitas per hari
- Variasi aktivitas: Backend, Frontend, Database, DevOps, General

## Testing

### Login Test
```bash
# Via curl
curl -X POST https://api.presensi.globalintermedia.online/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@test.com","password":"password"}'

# Via Swagger UI
https://api.presensi.globalintermedia.online/api/documentation
```

### Check Database
```bash
# Via artisan tinker
php artisan tinker

>>> User::count()
>>> Member::count()
>>> Attendance::whereDate('tanggal', today())->count()
>>> Progress::whereDate('tanggal', today())->count()
```

## Notes

- Seeders dijalankan secara berurutan karena ada foreign key dependencies
- Data dummy menggunakan informasi realistis untuk testing yang lebih baik
- Attendance dan Progress data generated untuk 30 hari terakhir
- Semua password di-hash menggunakan bcrypt

## Production Warning ⚠️

**JANGAN** jalankan seeder di production! Seeder hanya untuk development/testing.

Jika perlu data awal di production:
1. Buat seeder khusus production dengan data minimal
2. Atau input manual via admin panel
3. Atau impor dari CSV/Excel

## Troubleshooting

### Error: Foreign key constraint fails
```bash
# Jalankan fresh migration
php artisan migrate:fresh --seed
```

### Error: Duplicate entry
```bash
# Clear database dulu
php artisan migrate:fresh
php artisan db:seed
```

### Performance lambat
```bash
# Seeding banyak data bisa lambat, tunggu hingga selesai
# Atau matikan query log sementara di AppServiceProvider
```
