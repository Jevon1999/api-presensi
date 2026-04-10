<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Member;
use App\Models\Attendance;
use App\Models\BotConfig;
use Carbon\Carbon;

class SendCheckOutReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:send-checkout-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send check-out reminder to all members who have checked in but not checked out';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $config = BotConfig::where('is_active', true)->first();

        if (!$config || !$config->reminder_enabled) {
            $this->info('Bot config not active or reminder disabled.');
            return 0;
        }

        $today = Carbon::now()->format('Y-m-d');

        // Get all active members who have checked in but not checked out today
        $members = Member::where('status_aktif', true)
            ->whereNotNull('no_hp')
            ->whereHas('attendances', function ($query) use ($today) {
                $query->where('tanggal', $today)
                    ->whereNotNull('check_in_time')
                    ->whereNull('check_out_time');
            })
            ->get();

        $this->info("Found {$members->count()} members to remind for check-out.");

        $template = $config->message_remind_check_out 
            ?: "🔔 *Reminder Check-out*\n\nHalo {nama}! Jangan lupa untuk check-out sebelum pulang ya.\n\nKetik *keluar* untuk check-out kehadiran.\n\nTerima kasih atas kerja kerasmu hari ini! 🎉";

        $sentCount = 0;
        foreach ($members as $member) {
            $chatId = $this->formatChatId($member->no_hp);
            $message = str_replace('{nama}', $member->nama_lengkap, $template);
            
            if ($this->sendMessage($config, $chatId, $message)) {
                $sentCount++;
                $this->info("Reminder sent to: {$member->nama_lengkap}");
            } else {
                $this->error("Failed to send reminder to: {$member->nama_lengkap}");
            }

            // Small delay to avoid overwhelming the API
            usleep(500000); // 500ms delay
        }

        $this->info("Check-out reminder sent to {$sentCount} members.");
        Log::info("Check-out reminder sent to {$sentCount} members.");

        return 0;
    }

    /**
     * Format phone number to WhatsApp chat ID
     */
    private function formatChatId($phoneNumber)
    {
        // Remove all non-digit characters
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // If starts with 0, replace with 62
        if (substr($cleaned, 0, 1) === '0') {
            $cleaned = '62' . substr($cleaned, 1);
        }
        
        // If doesn't start with 62, add 62 prefix
        if (substr($cleaned, 0, 2) !== '62') {
            $cleaned = '62' . $cleaned;
        }
        
        return $cleaned . '@c.us';
    }

    /**
     * Send message via WAHA API
     */
    private function sendMessage($config, $to, $message)
    {
        try {
            $url = rtrim($config->waha_api_url, '/') . '/api/sendText';
            
            $headers = [
                'Content-Type' => 'application/json'
            ];
            
            if ($config->waha_api_key) {
                $headers['X-Api-Key'] = $config->waha_api_key;
            }
            
            $response = Http::withHeaders($headers)->post($url, [
                'session' => $config->waha_session_name ?: 'default',
                'chatId' => $to,
                'text' => $message
            ]);

            return $response->successful();

        } catch (\Exception $e) {
            Log::error('Error sending check-out reminder: ' . $e->getMessage());
            return false;
        }
    }
}
