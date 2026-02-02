<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BotConfig;

class BotConfigSeeder extends Seeder
{
    public function run(): void
    {
        BotConfig::create([
            'id' => 1, // Singleton
            
            // WAHA Connection
            'waha_api_url' => env('WAHA_API_URL', 'http://localhost:3000'),
            'waha_api_key' => env('WAHA_API_KEY', 'your_api_key_here'),
            'waha_session_name' => env('WAHA_SESSION', 'attendance_bot'),
            
            // Webhook
            'webhook_url' => env('APP_URL', 'http://localhost:8000') . '/api/webhook/waha',
            'webhook_secret' => env('WEBHOOK_SECRET', 'secret123'),
            'webhook_events' => ['message', 'session.status'],
            
            // Reminder Settings
            'reminder_check_in_time' => '08:00:00',
            'reminder_check_out_time' => '17:00:00',
            'timezone' => 'Asia/Jakarta',
            'reminder_enabled' => true,
            
            // Bot Behavior
            'typing_delay_ms' => 1000,
            'mark_messages_read' => true,
            'reject_calls' => false,
            
            // Message Templates
            'message_greeting' => "Halo! 👋\n\nSaya bot absensi PKL otomatis.\nKlik tombol di bawah untuk melakukan presensi.",
            
            'message_remind_check_in' => "⏰ Selamat pagi!\n\nWaktunya check-in. Jangan lupa absen masuk ya!",
            
            'message_remind_check_out' => "⏰ Waktunya pulang!\n\nJangan lupa check-out sebelum meninggalkan kantor.",
            
            'message_success_check_in' => "✅ Check-in berhasil!\n\nWaktu: {time}\nLokasi: {location}\nStatus: Hadir\n\nSelamat bekerja! 💪",
            
            'message_success_check_out' => "✅ Check-out berhasil!\n\nWaktu: {time}\nTotal jam kerja: {hours}\n\nTerima kasih dan sampai jumpa besok! 👋",
            
            'message_already_checked_in' => "ℹ️ Kamu sudah check-in hari ini.\n\nCheck-in: {time}\nStatus: {status}",
            
            'message_error' => "❌ Terjadi kesalahan.\n\nSilakan coba lagi atau hubungi admin jika masalah berlanjut.",
            
            'is_active' => true
        ]);
    }
}