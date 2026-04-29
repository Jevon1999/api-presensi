<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\Member;
use App\Models\Attendance;
use App\Models\BotConfig;
use App\Jobs\SendWahaMessage;
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
        try {
            $config = BotConfig::where('is_active', true)->first();

            if (!$config || !$config->reminder_enabled) {
                $this->info('Bot config not active or reminder disabled.');
                return 0;
            }

            $today = Carbon::now()->format('Y-m-d');

            // First: Get all REJECTED members to explicitly exclude
            $rejectedIds = Member::where('status', 'rejected')->pluck('id')->toArray();

            // Get all active members who have checked in but not checked out today
            $members = Member::where('status_aktif', true)
                ->whereNotNull('no_hp')
                ->where('status', '!=', 'rejected')  // Explicitly exclude rejected
                ->whereHas('attendances', function ($query) use ($today) {
                    $query->where('tanggal', $today)
                        ->whereNotNull('check_in_time')
                        ->whereNull('check_out_time')
                        // Exclude members with izin/sakit/alpha status
                        ->whereNotIn('status', ['izin', 'sakit', 'alpha']);
                })
                ->get();

            // DEBUG: Log all members being processed
            $memberDetails = $members->map(function($m) use ($today) {
                $attendance = $m->attendances()->where('tanggal', $today)->first();
                return [
                    'id' => $m->id,
                    'nama' => $m->nama_lengkap,
                    'no_hp' => $m->no_hp,
                    'status_aktif' => $m->status_aktif,
                    'status' => $m->status,
                    'check_in' => $attendance ? $attendance->check_in_time : 'N/A',
                    'check_out' => $attendance ? $attendance->check_out_time : 'N/A',
                    'attendance_status' => $attendance ? $attendance->status : 'N/A'
                ];
            })->toArray();

            Log::info("Check-out reminder: Found {$members->count()} members to remind", [
                'excluded_rejected_count' => count($rejectedIds),
                'members_detail' => $memberDetails
            ]);
            $this->info("Found {$members->count()} members to remind for check-out.");
            $this->table(['ID', 'Nama', 'Phone', 'Aktif', 'Status'], $members->map(function($m) {
                return [$m->id, $m->nama_lengkap, $m->no_hp, $m->status_aktif ? 'Yes' : 'No', $m->status];
            })->toArray());

            $template = $config->message_remind_check_out 
                ?: "🔔 *Reminder Check-out*\n\nHalo {nama}! Jangan lupa untuk check-out sebelum pulang ya.\n\nKetik *keluar* untuk check-out kehadiran.\n\nTerima kasih atas kerja kerasmu hari ini! 🎉";

            $queuedCount = 0;
            $errorCount = 0;
            
            foreach ($members as $member) {
                try {
                    $chatId = $this->formatChatId($member->no_hp);
                    $message = str_replace('{nama}', $member->nama_lengkap, $template);
                    
                    // Queue the message to be sent asynchronously (no blocking HTTP calls)
                    SendWahaMessage::dispatch($config, $chatId, $message);
                    
                    $queuedCount++;
                    Log::info("Check-out reminder queued", ['member' => $member->nama_lengkap, 'chatId' => $chatId]);
                    $this->info("✓ Reminder queued for: {$member->nama_lengkap}");

                    // Small delay to avoid overwhelming the queue
                    usleep(100000); // 100ms delay
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error("Error queueing check-out reminder for {$member->nama_lengkap}: " . $e->getMessage(), [
                        'trace' => $e->getTraceAsString()
                    ]);
                    $this->error("✗ Error queueing reminder for {$member->nama_lengkap}: {$e->getMessage()}");
                }
            }

            $this->info("Check-out reminder command completed: {$queuedCount} queued, {$errorCount} errors.");
            Log::info("Check-out reminder command completed", [
                'queued' => $queuedCount,
                'errors' => $errorCount,
                'total' => $members->count()
            ]);

            return 0;

        } catch (\Exception $e) {
            Log::error('Check-out reminder command error: ' . $e->getMessage(), [
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
            
            $response = Http::withHeaders($headers)->timeout(30)->post($url, [
                'session' => $config->waha_session_name ?: 'default',
                'chatId' => $to,
                'text' => $message
            ]);

            if (!$response->successful()) {
                Log::error('Check-out reminder API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $url,
                    'to' => $to
                ]);
                return false;
            }

            return true;

        } catch (\Throwable $e) {
            Log::error('Check-out reminder exception: ' . $e->getMessage(), [
                'type' => get_class($e),
                'to' => $to,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}
