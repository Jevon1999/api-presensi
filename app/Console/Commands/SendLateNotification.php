<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Member;
use App\Models\Attendance;
use App\Models\BotConfig;
use Carbon\Carbon;

class SendLateNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:send-late-notification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send late notification to members who have not checked in after the late threshold';

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
        $now = Carbon::now();
        
        // Get the late threshold time
        $lateThreshold = $config->check_in_late_threshold;
        if (!$lateThreshold) {
            $this->info('Late threshold not configured.');
            return 0;
        }

        // Parse late threshold time for today
        $lateTime = Carbon::parse($today . ' ' . Carbon::parse($lateThreshold)->format('H:i:s'));
        
        // Only run if current time is past the late threshold
        if ($now->lt($lateTime)) {
            $this->info('Current time is before late threshold. Skipping.');
            return 0;
        }

        // Get all active members who haven't checked in today and have no izin/sakit
        $members = Member::where('status_aktif', true)
            ->whereNotNull('no_hp')
            ->whereDoesntHave('attendances', function ($query) use ($today) {
                $query->where('tanggal', $today)
                    ->where(function ($q) {
                        $q->whereNotNull('check_in_time')
                          ->orWhere('status', 'izin')
                          ->orWhere('status', 'sakit');
                    });
            })
            ->get();

        $this->info("Found {$members->count()} members who are late and have not checked in.");

        $thresholdFormatted = Carbon::parse($lateThreshold)->format('H:i');
        $message = "⚠️ *Notifikasi Keterlambatan*\n\n";
        $message .= "Halo! Kamu belum check-in hari ini dan sudah melewati batas waktu ({$thresholdFormatted} WIB).\n\n";
        $message .= "Jika kamu berhalangan hadir, ketik:\n";
        $message .= "• *izin [alasan]* - untuk izin\n";
        $message .= "• *sakit [keterangan]* - jika sakit\n\n";
        $message .= "Atau ketik *masuk* untuk check-in sekarang (akan tercatat terlambat).";

        $sentCount = 0;
        foreach ($members as $member) {
            $chatId = $this->formatChatId($member->no_hp);
            
            if ($this->sendMessage($config, $chatId, $message)) {
                $sentCount++;
                $this->info("Late notification sent to: {$member->nama_lengkap}");
            } else {
                $this->error("Failed to send late notification to: {$member->nama_lengkap}");
            }

            // Small delay to avoid overwhelming the API
            usleep(500000); // 500ms delay
        }

        $this->info("Late notification sent to {$sentCount} members.");
        Log::info("Late notification sent to {$sentCount} members.");

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
            Log::error('Error sending late notification: ' . $e->getMessage());
            return false;
        }
    }
}
