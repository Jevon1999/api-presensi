<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Member;
use App\Models\Attendance;
use App\Models\BotConfig;
use Carbon\Carbon;

class SendCheckInReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:send-checkin-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send check-in reminder to all active members who have not checked in yet';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $config = BotConfig::where('is_active', true)->first();

            if (!$config || !$config->reminder_enabled) {
                $this->info('Bot config not active or reminder disabled.');
                return 0;
            }

            $today = Carbon::now()->format('Y-m-d');

            // Get all active members who haven't checked in today
            // This includes members with no attendance record at all
            $members = Member::where('status_aktif', true)
                ->whereNotNull('no_hp')
                ->where(function ($query) use ($today) {
                    // Members with no attendance record at all
                    $query->whereDoesntHave('attendances', function ($subQuery) use ($today) {
                        $subQuery->where('tanggal', $today);
                    })
                    // OR members who have attendance but no check-in
                    ->orWhereHas('attendances', function ($subQuery) use ($today) {
                        $subQuery->where('tanggal', $today)
                            ->whereNull('check_in_time');
                    });
                })
                ->get();

            Log::info("Check-in reminder: Found {$members->count()} members to remind");
            $this->info("Found {$members->count()} members to remind for check-in.");

            $template = $config->message_remind_check_in 
                ?: "🔔 *Reminder Check-in*\n\nHalo {nama}! Jangan lupa untuk check-in hari ini ya.\n\nKetik *masuk* untuk check-in kehadiran.\n\nTerima kasih! 😊";

            $sentCount = 0;
            $failedCount = 0;
            
            foreach ($members as $member) {
                try {
                    $chatId = $this->formatChatId($member->no_hp);
                    $message = str_replace('{nama}', $member->nama_lengkap, $template);
                    
                    if ($this->sendMessage($config, $chatId, $message)) {
                        $sentCount++;
                        Log::info("Check-in reminder sent", ['member' => $member->nama_lengkap]);
                        $this->info("✓ Reminder sent to: {$member->nama_lengkap}");
                    } else {
                        $failedCount++;
                        Log::warning("Failed to send check-in reminder", ['member' => $member->nama_lengkap]);
                        $this->error("✗ Failed to send reminder to: {$member->nama_lengkap}");
                    }

                    // Small delay to avoid overwhelming the API
                    usleep(500000); // 500ms delay
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::error("Error sending check-in reminder to {$member->nama_lengkap}: " . $e->getMessage());
                    $this->error("✗ Error sending to {$member->nama_lengkap}: {$e->getMessage()}");
                }
            }

            $this->info("Check-in reminder completed: {$sentCount} sent, {$failedCount} failed.");
            Log::info("Check-in reminder completed", [
                'sent' => $sentCount,
                'failed' => $failedCount,
                'total' => $members->count()
            ]);

            return 0;

        } catch (\Exception $e) {
            Log::error('Check-in reminder command error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $this->error('Command error: ' . $e->getMessage());
            return 1;
        }
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
            Log::error('Error sending check-in reminder: ' . $e->getMessage());
            return false;
        }
    }
}
