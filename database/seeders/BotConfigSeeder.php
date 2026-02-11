<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BotConfig;

class BotConfigSeeder extends Seeder
{
    public function run(): void
    {
        BotConfig::updateOrCreate(
            ['id' => 1], // Singleton pattern
            [
                // WAHA Connection
                'waha_api_url' => env('WAHA_API_URL', 'http://localhost:3000'),
                'waha_api_key' => env('WAHA_API_KEY', null), // Optional, set if WAHA requires API key
                'waha_session_name' => env('WAHA_SESSION', 'default'),
                
                // Webhook
                'webhook_url' => env('APP_URL', 'http://localhost:8000') . '/api/waha/webhook',
                'webhook_secret' => env('WEBHOOK_SECRET', null),
                'webhook_events' => ['message'],
                
                // Reminder Settings (untuk fitur future)
                'reminder_check_in_time' => '08:00:00',
                'reminder_check_out_time' => '17:00:00',
                'timezone' => 'Asia/Jakarta',
                'reminder_enabled' => false, // Disabled by default
                
                // Bot Behavior
                'typing_delay_ms' => 500, // 0.5 detik
                'mark_messages_read' => true,
                'reject_calls' => false,
                
                // Message Templates (opsional, bisa di-customize)
                'message_greeting' => "Halo! 👋 Selamat datang di Bot Presensi.\n\nSilakan gunakan perintah berikut:\n- *masuk* untuk check-in\n- *keluar* untuk check-out\n- *status* untuk cek kehadiran\n- *help* untuk bantuan",
                
                'message_remind_check_in' => "⏰ Selamat pagi!\n\nWaktunya check-in. Jangan lupa absen ya!",
                
                'message_remind_check_out' => "⏰ Waktunya pulang!\n\nJangan lupa check-out ya!",
                
                'message_success_check_in' => "✅ *Check-in Berhasil!*\n\n",
                
                'message_success_check_out' => "✅ *Check-out Berhasil!*\n\n",
                
                'message_already_checked_in' => null, // Will use default message in controller
                
                'message_error' => "❌ Terjadi kesalahan.\n\nSilakan coba lagi atau hubungi admin.",
                
                'is_active' => true
            ]
        );

        $this->command->info('✅ Bot configuration created/updated successfully!');
    }
}