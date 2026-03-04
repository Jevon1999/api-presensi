<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Member;
use App\Models\Attendance;
use App\Models\BotConfig;
use App\Models\Permission;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WahaWebhookController extends Controller
{
    /**
     * Handle incoming webhook from WAHA
     */
    public function handle(Request $request)
    {
        try {
            Log::info('WAHA Webhook received', $request->all());

            $event = $request->input('event');
            $payload = $request->input('payload');

            // Only process 'message' event (ignore 'message.any' to prevent duplicate processing)
            if ($event !== 'message') {
                return response()->json(['status' => 'ignored', 'reason' => 'not a message event']);
            }

            // Ignore messages from me (bot's own messages)
            if ($payload['fromMe'] ?? false) {
                return response()->json(['status' => 'ignored', 'reason' => 'message from bot']);
            }

            // Ignore group messages
            if (isset($payload['from']) && strpos($payload['from'], '@g.us') !== false) {
                return response()->json(['status' => 'ignored', 'reason' => 'group message']);
            }

            // Ignore status/story broadcasts
            if (isset($payload['from']) && strpos($payload['from'], 'status@broadcast') !== false) {
                return response()->json(['status' => 'ignored', 'reason' => 'status broadcast']);
            }

            $from = $payload['from'] ?? null;
            $messageBody = trim($payload['body'] ?? '');

            if (!$from || !$messageBody) {
                return response()->json(['status' => 'ignored', 'reason' => 'no sender or empty message']);
            }

            // Extract phone number from WhatsApp ID (remove @c.us)
            $phoneNumber = str_replace('@c.us', '', $from);

            // Get bot config
            $config = BotConfig::where('is_active', true)->first();
            if (!$config) {
                Log::error('Bot config not found or inactive');
                return response()->json(['status' => 'error', 'message' => 'Bot not configured']);
            }

            // Mark message as read
            if ($config->mark_messages_read) {
                $this->markAsRead($config, $payload['id'] ?? null);
            }

            // Send typing indicator
            if ($config->typing_delay_ms > 0) {
                $this->sendTyping($config, $from);
                usleep($config->typing_delay_ms * 1000);
            }

            // Process command
            $response = $this->processCommand($messageBody, $phoneNumber, $config);

            // Send response via WAHA
            $this->sendMessage($config, $from, $response);

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('Webhook error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Process incoming message and execute command
     */
    private function processCommand($message, $phoneNumber, $config)
    {
        $originalMessage = $message;
        $message = strtolower($message);

        // Check-in commands
        if (in_array($message, ['masuk', 'checkin', 'check in', 'absen', 'hadir'])) {
            return $this->handleCheckIn($phoneNumber, $config);
        }

        // Check-out commands
        if (in_array($message, ['keluar', 'checkout', 'check out', 'pulang'])) {
            return $this->handleCheckOut($phoneNumber, $config);
        }

        // Status command
        if (in_array($message, ['status', 'cek', 'info'])) {
            return $this->handleStatus($phoneNumber);
        }

        // Izin (permission) command - format: izin [alasan]
        if (preg_match('/^izin\s*(.*)$/i', $originalMessage, $matches)) {
            $reason = trim($matches[1]);
            return $this->handleIzin($phoneNumber, $reason, $config);
        }

        // Sakit (sick) command - format: sakit [keterangan]
        if (preg_match('/^sakit\s*(.*)$/i', $originalMessage, $matches)) {
            $reason = trim($matches[1]);
            return $this->handleSakit($phoneNumber, $reason, $config);
        }

        // Help command
        if (in_array($message, ['help', 'bantuan', 'menu', 'mulai', 'start'])) {
            return $this->handleHelp($config);
        }

        // Unknown command
        return "Maaf, perintah tidak dikenali. 🤔\n\nKetik *help* untuk melihat daftar perintah yang tersedia.";
    }

    /**
     * Handle check-in
     */
    private function handleCheckIn($phoneNumber, $config)
    {
        $normalized = $this->normalizePhoneNumber($phoneNumber);

        // Find member
        $member = Member::where('no_hp', $normalized)
            ->where('status_aktif', true)
            ->first();

        if (!$member) {
            return "Maaf, nomor HP kamu belum terdaftar atau tidak aktif. 😔\n\nSilakan hubungi admin untuk registrasi.";
        }

        // Check if already checked in today
        $today = now()->format('Y-m-d');
        $attendance = Attendance::where('member_id', $member->id)
            ->where('tanggal', $today)
            ->first();

        if ($attendance && $attendance->check_in_time) {
            $checkInTime = Carbon::parse($attendance->check_in_time)->format('H:i');
            return $config->message_already_checked_in 
                ?: "Halo *{$member->nama_lengkap}* 👋\n\nKamu sudah check-in hari ini pada pukul *{$checkInTime}* WIB.";
        }

        // Create or update attendance
        $checkInTime = now()->format('H:i:s');
        
        // Check if late
        $isLate = false;
        $lateMessage = "";
        if ($config->check_in_late_threshold) {
            $lateThreshold = Carbon::parse($config->check_in_late_threshold);
            $currentTime = Carbon::parse($checkInTime);
            if ($currentTime->gt($lateThreshold)) {
                $isLate = true;
                $thresholdFormatted = $lateThreshold->format('H:i');
                $lateMessage = "\n\n⚠️ *Kamu terlambat!* Batas check-in adalah {$thresholdFormatted} WIB.";
            }
        }
        
        if ($attendance) {
            $attendance->update([
                'check_in_time' => $checkInTime,
                'status' => 'hadir',
                'is_late' => $isLate
            ]);
        } else {
            $attendance = Attendance::create([
                'member_id' => $member->id,
                'tanggal' => $today,
                'check_in_time' => $checkInTime,
                'status' => 'hadir',
                'is_late' => $isLate
            ]);
        }

        $formattedTime = Carbon::parse($checkInTime)->format('H:i');
        $message = $config->message_success_check_in ?: "✅ *Check-in Berhasil!*\n\n";
        $message .= "Nama: *{$member->nama_lengkap}*\n";
        $message .= "Kantor: *{$member->office->name}*\n";
        $message .= "Waktu: *{$formattedTime}* WIB\n";
        $message .= "Tanggal: " . now()->format('d/m/Y');
        $message .= $lateMessage;
        if (!$isLate) {
            $message .= "\n\nSelamat bekerja! 💪";
        }

        return $message;
    }

    /**
     * Handle check-out
     */
    private function handleCheckOut($phoneNumber, $config)
    {
        $normalized = $this->normalizePhoneNumber($phoneNumber);

        // Find member
        $member = Member::where('no_hp', $normalized)
            ->where('status_aktif', true)
            ->first();

        if (!$member) {
            return "Maaf, nomor HP kamu belum terdaftar atau tidak aktif. 😔\n\nSilakan hubungi admin untuk registrasi.";
        }

        // Check if checked in today
        $today = now()->format('Y-m-d');
        $attendance = Attendance::where('member_id', $member->id)
            ->where('tanggal', $today)
            ->first();

        if (!$attendance || !$attendance->check_in_time) {
            return "Maaf, kamu belum check-in hari ini. 😅\n\nSilakan check-in terlebih dahulu dengan mengetik *masuk*.";
        }

        if ($attendance->check_out_time) {
            $checkOutTime = Carbon::parse($attendance->check_out_time)->format('H:i');
            return "Halo *{$member->nama_lengkap}* 👋\n\nKamu sudah check-out hari ini pada pukul *{$checkOutTime}* WIB.";
        }

        // Update check-out time
        $checkOutTime = now()->format('H:i:s');
        $attendance->update([
            'check_out_time' => $checkOutTime
        ]);

        // Calculate working hours
        $checkIn = Carbon::parse($attendance->check_in_time);
        $checkOut = Carbon::parse($checkOutTime);
        $workingHours = $checkIn->diffInHours($checkOut);
        $workingMinutes = $checkIn->diffInMinutes($checkOut) % 60;

        $message = $config->message_success_check_out ?: "✅ *Check-out Berhasil!*\n\n";
        $message .= "Nama: *{$member->nama_lengkap}*\n";
        $message .= "Check-in: *{$checkIn->format('H:i')}* WIB\n";
        $message .= "Check-out: *{$checkOut->format('H:i')}* WIB\n";
        $message .= "Durasi Kerja: *{$workingHours} jam {$workingMinutes} menit*\n\n";
        $message .= "Terima kasih atas kerja keras kamu hari ini! 🎉";

        return $message;
    }

    /**
     * Handle status check
     */
    private function handleStatus($phoneNumber)
    {
        $normalized = $this->normalizePhoneNumber($phoneNumber);

        // Find member
        $member = Member::where('no_hp', $normalized)
            ->where('status_aktif', true)
            ->first();

        if (!$member) {
            return "Maaf, nomor HP kamu belum terdaftar atau tidak aktif. 😔\n\nSilakan hubungi admin untuk registrasi.";
        }

        // Get today's attendance
        $today = now()->format('Y-m-d');
        $attendance = Attendance::where('member_id', $member->id)
            ->where('tanggal', $today)
            ->first();

        $message = "📊 *Status Kehadiran Hari Ini*\n\n";
        $message .= "Nama: *{$member->nama_lengkap}*\n";
        $message .= "Kantor: *{$member->office->name}*\n";
        $message .= "Tanggal: " . now()->format('d/m/Y') . "\n\n";

        if (!$attendance) {
            $message .= "Status: ❌ *Belum Check-in*\n\n";
            $message .= "Silakan ketik *masuk* untuk check-in.";
        } elseif ($attendance->status === 'izin') {
            $permission = Permission::where('attendance_id', $attendance->id)->first();
            $message .= "Status: 📋 *Izin*\n";
            if ($permission) {
                $message .= "Alasan: {$permission->reason}\n";
            }
        } elseif ($attendance->status === 'sakit') {
            $permission = Permission::where('attendance_id', $attendance->id)->first();
            $message .= "Status: 🏥 *Sakit*\n";
            if ($permission) {
                $message .= "Keterangan: {$permission->reason}\n";
            }
            $message .= "\nSemoga lekas sembuh! 🙏";
        } elseif (!$attendance->check_in_time) {
            $message .= "Status: ❌ *Belum Check-in*\n\n";
            $message .= "Silakan ketik *masuk* untuk check-in.";
        } else {
            $checkInTime = Carbon::parse($attendance->check_in_time)->format('H:i');
            $message .= "✅ Check-in: *{$checkInTime}* WIB";
            
            // Show if late
            if ($attendance->is_late) {
                $message .= " ⚠️ (Terlambat)";
            }
            $message .= "\n";

            if ($attendance->check_out_time) {
                $checkOutTime = Carbon::parse($attendance->check_out_time)->format('H:i');
                $checkIn = Carbon::parse($attendance->check_in_time);
                $checkOut = Carbon::parse($attendance->check_out_time);
                $workingHours = $checkIn->diffInHours($checkOut);
                $workingMinutes = $checkIn->diffInMinutes($checkOut) % 60;
                
                $message .= "✅ Check-out: *{$checkOutTime}* WIB\n";
                $message .= "⏱️ Durasi: *{$workingHours} jam {$workingMinutes} menit*\n";
            } else {
                $message .= "❌ Check-out: *Belum*\n\n";
                $message .= "Ketik *keluar* untuk check-out.";
            }
        }

        return $message;
    }

    /**
     * Handle help command
     */
    private function handleHelp($config)
    {
        $message = "👋 *Halo! Selamat datang di Bot Presensi*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        $message .= "📝 *DAFTAR PERINTAH*\n\n";
        
        $message .= "🟢 *masuk*\n";
        $message .= "    └ Check-in kehadiran\n\n";
        
        $message .= "🔴 *keluar*\n";
        $message .= "    └ Check-out kehadiran\n\n";
        
        $message .= "📊 *status*\n";
        $message .= "    └ Cek status kehadiran hari ini\n\n";
        
        $message .= "📋 *izin* _[alasan]_\n";
        $message .= "    └ Ajukan izin tidak hadir\n\n";
        
        $message .= "🏥 *sakit* _[keterangan]_\n";
        $message .= "    └ Lapor sakit\n\n";
        
        $message .= "❓ *help*\n";
        $message .= "    └ Tampilkan menu ini\n\n";
        
        $message .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "💡 *Alias:* _checkin, checkout, absen, pulang, cek, info_";

        return $message;
    }

    /**
     * Handle izin (permission) request
     */
    private function handleIzin($phoneNumber, $reason, $config)
    {
        $normalized = $this->normalizePhoneNumber($phoneNumber);

        // Find member
        $member = Member::where('no_hp', $normalized)
            ->where('status_aktif', true)
            ->first();

        if (!$member) {
            return "Maaf, nomor HP kamu belum terdaftar atau tidak aktif. 😔\n\nSilakan hubungi admin untuk registrasi.";
        }

        // Check if reason is provided
        if (empty($reason)) {
            return "⚠️ *Format Izin Salah*\n\nSilakan ketik dengan format:\n*izin [alasan]*\n\nContoh: izin ada keperluan keluarga";
        }

        $today = now()->format('Y-m-d');
        
        // Check if already have attendance today
        $attendance = Attendance::where('member_id', $member->id)
            ->where('tanggal', $today)
            ->first();

        if ($attendance && $attendance->check_in_time) {
            return "Halo *{$member->nama_lengkap}* 👋\n\nKamu sudah check-in hari ini, tidak bisa mengajukan izin.\n\nJika perlu pulang lebih awal, silakan check-out dengan mengetik *keluar*.";
        }

        // Check if already have izin/sakit today
        if ($attendance && in_array($attendance->status, ['izin', 'sakit'])) {
            $statusText = $attendance->status === 'izin' ? 'izin' : 'sakit';
            return "Halo *{$member->nama_lengkap}* 👋\n\nKamu sudah mengajukan {$statusText} hari ini.";
        }

        // Create or update attendance with izin status
        if ($attendance) {
            $attendance->update([
                'status' => 'izin'
            ]);
        } else {
            $attendance = Attendance::create([
                'member_id' => $member->id,
                'tanggal' => $today,
                'status' => 'izin',
            ]);
        }

        // Create permission record
        Permission::create([
            'attendance_id' => $attendance->id,
            'reason' => $reason,
            'type' => 'izin'
        ]);

        $message = "📋 *Izin Tercatat!*\n\n";
        $message .= "Nama: *{$member->nama_lengkap}*\n";
        $message .= "Tanggal: " . now()->format('d/m/Y') . "\n";
        $message .= "Alasan: {$reason}\n\n";
        $message .= "Izin kamu sudah tercatat. Semoga urusanmu lancar! 🙏";

        return $message;
    }

    /**
     * Handle sakit (sick) request
     */
    private function handleSakit($phoneNumber, $reason, $config)
    {
        $normalized = $this->normalizePhoneNumber($phoneNumber);

        // Find member
        $member = Member::where('no_hp', $normalized)
            ->where('status_aktif', true)
            ->first();

        if (!$member) {
            return "Maaf, nomor HP kamu belum terdaftar atau tidak aktif. 😔\n\nSilakan hubungi admin untuk registrasi.";
        }

        // Check if reason is provided
        if (empty($reason)) {
            return "⚠️ *Format Sakit Salah*\n\nSilakan ketik dengan format:\n*sakit [keterangan]*\n\nContoh: sakit demam";
        }

        $today = now()->format('Y-m-d');
        
        // Check if already have attendance today
        $attendance = Attendance::where('member_id', $member->id)
            ->where('tanggal', $today)
            ->first();

        if ($attendance && $attendance->check_in_time) {
            return "Halo *{$member->nama_lengkap}* 👋\n\nKamu sudah check-in hari ini. Jika perlu pulang karena sakit, silakan check-out dengan mengetik *keluar*.";
        }

        // Check if already have izin/sakit today
        if ($attendance && in_array($attendance->status, ['izin', 'sakit'])) {
            $statusText = $attendance->status === 'izin' ? 'izin' : 'sakit';
            return "Halo *{$member->nama_lengkap}* 👋\n\nKamu sudah mengajukan {$statusText} hari ini.";
        }

        // Create or update attendance with sakit status
        if ($attendance) {
            $attendance->update([
                'status' => 'sakit'
            ]);
        } else {
            $attendance = Attendance::create([
                'member_id' => $member->id,
                'tanggal' => $today,
                'status' => 'sakit',
            ]);
        }

        // Create permission record
        Permission::create([
            'attendance_id' => $attendance->id,
            'reason' => $reason,
            'type' => 'sakit'
        ]);

        $message = "🏥 *Laporan Sakit Tercatat!*\n\n";
        $message .= "Nama: *{$member->nama_lengkap}*\n";
        $message .= "Tanggal: " . now()->format('d/m/Y') . "\n";
        $message .= "Keterangan: {$reason}\n\n";
        $message .= "Semoga lekas sembuh! 🙏💪";

        return $message;
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
            
            // Add API key if configured
            if ($config->waha_api_key) {
                $headers['X-Api-Key'] = $config->waha_api_key;
            }
            
            $response = Http::withHeaders($headers)->post($url, [
                'session' => $config->waha_session_name ?: 'default',
                'chatId' => $to,
                'text' => $message
            ]);

            if (!$response->successful()) {
                Log::error('Failed to send WAHA message', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'to' => $to
                ]);
                
                // Return false to indicate failure
                return false;
            }
            
            Log::info('Message sent successfully', ['to' => $to]);
            return true;

        } catch (\Exception $e) {
            Log::error('Error sending WAHA message: ' . $e->getMessage(), [
                'to' => $to,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Send typing indicator
     */
    private function sendTyping($config, $to)
    {
        try {
            $url = rtrim($config->waha_api_url, '/') . '/api/sendSeen';
            
            $headers = [
                'Content-Type' => 'application/json'
            ];
            
            // Add API key if configured
            if ($config->waha_api_key) {
                $headers['X-Api-Key'] = $config->waha_api_key;
            }
            
            Http::withHeaders($headers)->post($url, [
                'session' => $config->waha_session_name ?: 'default',
                'chatId' => $to
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending typing indicator: ' . $e->getMessage());
        }
    }

    /**
     * Mark message as read
     */
    private function markAsRead($config, $messageId)
    {
        if (!$messageId) return;

        try {
            // WAHA doesn't have a specific mark as read endpoint
            // The sendSeen endpoint serves this purpose
        } catch (\Exception $e) {
            Log::error('Error marking message as read: ' . $e->getMessage());
        }
    }

    /**
     * Normalize phone number to international format (+62xxx)
     */
    private function normalizePhoneNumber($phoneNumber)
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
        
        // Return with + prefix to match database format
        return '+' . $cleaned;
    }
}
