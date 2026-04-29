<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\Member;
use App\Models\Attendance;
use App\Models\BotConfig;
use App\Jobs\SendWahaMessage;
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

        $template = $config->message_error ?: "⚠️ *Notifikasi Keterlambatan*\n\nHalo {nama}! Kamu belum check-in hari ini dan melewati batas waktu telat/alpha.\n\nJika kamu berhalangan hadir, ketik:\n• *izin [alasan]* - untuk izin\n• *sakit [keterangan]* - jika sakit\n\nAtau ketik *masuk* untuk check-in sekarang (akan tercatat terlambat).";

        $queuedCount = 0;
        $errorCount = 0;
        
        foreach ($members as $member) {
            try {
                $chatId = $this->formatChatId($member->no_hp);
                $message = str_replace('{nama}', $member->nama_lengkap, $template);
                
                // Queue the message to be sent asynchronously (no blocking HTTP calls)
                SendWahaMessage::dispatch($config, $chatId, $message);
                
                $queuedCount++;
                $this->info("Late notification queued for: {$member->nama_lengkap}");
            } catch (\Exception $e) {
                $errorCount++;
                Log::error("Error queueing late notification for {$member->nama_lengkap}: " . $e->getMessage());
                $this->error("Error queueing late notification for {$member->nama_lengkap}: {$e->getMessage()}");
            }

            // Small delay to avoid overwhelming the queue
            usleep(100000); // 100ms delay
        }

        $this->info("Late notification command completed: {$queuedCount} queued, {$errorCount} errors.");
        Log::info("Late notification command completed", [
            'queued' => $queuedCount,
            'errors' => $errorCount,
            'total' => $members->count()
        ]);

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
