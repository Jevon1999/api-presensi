<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Member;
use App\Models\Attendance;
use App\Models\BotConfig;
use App\Models\Permission;
use App\Models\Progress;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WahaWebhookController extends Controller
{
    /**
     * Handle incoming webhook from WAHA
     */
    public function handle(Request $request)
    {
        $phoneNumber = null;
        $from = null;
        $config = null;
        
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

            // Extract phone number from WhatsApp ID
            // WhatsApp can send: 6285848607270@c.us, 30107754344618@lid, etc
            // Also check _data.key.remoteJidAlt for actual phone number
            // Remove any @{suffix} to get pure phone number
            $phoneNumber = preg_replace('/@.*$/', '', $from);
            
            // If _data has remoteJidAlt with actual phone number, use that instead
            if (isset($payload['_data']['key']['remoteJidAlt']) && !empty($payload['_data']['key']['remoteJidAlt'])) {
                $remoteJidAlt = $payload['_data']['key']['remoteJidAlt'];
                $altPhoneNumber = preg_replace('/@.*$/', '', $remoteJidAlt);
                // Only use altPhoneNumber if it looks like a valid phone (more digits)
                if (strlen($altPhoneNumber) > strlen($phoneNumber) || preg_match('/^62\d{8,}/', $altPhoneNumber)) {
                    $phoneNumber = $altPhoneNumber;
                }
            }
            
            Log::info('WAHA phone extraction', [
                'from' => $from,
                'remoteJidAlt' => $payload['_data']['key']['remoteJidAlt'] ?? null,
                'extracted_phoneNumber' => $phoneNumber,
                'messageBody' => $messageBody,
            ]);

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
            try {
                $response = $this->processCommand($messageBody, $phoneNumber, $config);
                Log::info('Command processed', [
                    'phoneNumber' => $phoneNumber,
                    'message' => $messageBody,
                    'response_preview' => substr($response, 0, 100)
                ]);
            } catch (\Throwable $e) {
                Log::error('Error processing command: ' . $e->getMessage(), [
                    'phoneNumber' => $phoneNumber,
                    'message' => $messageBody,
                    'trace' => $e->getTraceAsString()
                ]);
                $response = "❌ Terjadi kesalahan saat memproses perintah.\n\nSilakan coba lagi atau ketik *help* untuk bantuan.";
            }

            // Send response via WAHA (format chatId properly)
            try {
                $chatId = $this->formatChatId($phoneNumber);
                $sendResult = $this->sendMessage($config, $chatId, $response);
                
                if (!$sendResult) {
                    Log::error('Failed to send message to WAHA', [
                        'phoneNumber' => $phoneNumber,
                        'chatId' => $chatId
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Error sending message: ' . $e->getMessage(), [
                    'phoneNumber' => $phoneNumber,
                    'trace' => $e->getTraceAsString()
                ]);
            }

            return response()->json(['status' => 'success']);

        } catch (\Throwable $e) {
            Log::error('Webhook error: ' . $e->getMessage(), [
                'phoneNumber' => $phoneNumber,
                'from' => $from,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Try to send error message to user if possible
            if ($phoneNumber && $config) {
                try {
                    $chatId = $this->formatChatId($phoneNumber);
                    $errorMsg = "❌ Terjadi kesalahan teknis.\n\nSilakan hubungi admin atau coba lagi nanti.";
                    $this->sendMessage($config, $chatId, $errorMsg);
                } catch (\Throwable $innerE) {
                    Log::error('Could not send error message to user', [
                        'phoneNumber' => $phoneNumber,
                        'error' => $innerE->getMessage()
                    ]);
                }
            }
            
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
        if (in_array($message, ['masuk', 'checkin', 'check in', 'absen', 'hadir']) || preg_match('/^masuk\s+(wfa|wfo)/i', $originalMessage) || preg_match('/^absen\s+(wfa|wfo)/i', $originalMessage)) {
            return $this->handleCheckIn($phoneNumber, $config, $originalMessage);
        }

        // Check-out commands
        if (in_array($message, ['keluar', 'checkout', 'check out', 'pulang']) || preg_match('/^keluar\s+/i', $originalMessage) || preg_match('/^pulang\s+/i', $originalMessage)) {
            return $this->handleCheckOut($phoneNumber, $config, $originalMessage);
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

        // Progress command - format: progress [isi] or progress edit [isi] or progress lihat
        if (preg_match('/^progress\s*/i', $originalMessage) || $message === 'progress') {
            return $this->handleProgress($phoneNumber, $originalMessage, $config);
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
    private function handleCheckIn($phoneNumber, $config, $originalMessage = '')
    {
        // Find member with flexible format matching
        $memberCheck = $this->getMemberOrErrorMessage($phoneNumber);
        if (!$memberCheck['success']) {
            return $memberCheck['message'];
        }
        $member = $memberCheck['member'];

        // Parse work_type (WFA/WFO) dari message
        // Supported: "masuk wfa", "masuk wfo", "absen WFA", dll
        $workType = 'wfo'; // default
        $lateReason = '';
        
        $messageLower = strtolower($originalMessage);
        if (preg_match('/\bwfa\b/', $messageLower)) {
            $workType = 'wfa';
        } elseif (preg_match('/\bwfo\b/', $messageLower)) {
            $workType = 'wfo';
        }
        
        // Parse late reason jika ada (format: "masuk wfa alasan=...")
        if (preg_match('/alasan=(.+?)(?:\s|$)/i', $originalMessage, $matches)) {
            $lateReason = trim($matches[1]);
        }

        // Check if current time is within attendance hours
        $currentTime = Carbon::now()->format('H:i:s');
        $officeOpenTime = $config->reminder_check_in_time ?? '06:00:00';
        $officeCloseTime = $config->reminder_check_out_time ?? '18:00:00';
        
        if ($currentTime < $officeOpenTime || $currentTime > $officeCloseTime) {
            $openTime = Carbon::createFromFormat('H:i:s', $officeOpenTime)->format('H:i');
            $closeTime = Carbon::createFromFormat('H:i:s', $officeCloseTime)->format('H:i');
            return "⏰ Maaf, jam absen belum dibuka.\n\nJam absen: *{$openTime} - {$closeTime}* WIB\n\nJam sekarang: *" . Carbon::now()->format('H:i') . "* WIB";
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
                
                // If late but no reason provided, ask for reason
                if (!$lateReason) {
                    return "⚠️ *Kamu Check-in Terlambat!*\n\nBatas check-in: *{$thresholdFormatted}* WIB\nWaktu sekarang: *" . Carbon::now()->format('H:i') . "* WIB\n\n📝 *Silakan berikan alasan keterlambatan dengan format:*\n\n*masuk {$workType} alasan=<alasan_kamu>*\n\nContoh:\n*masuk wfo alasan=ada meeting penting*\n*masuk wfa alasan=update sistem server*";
                }
                
                $lateMessage = "\n\n⚠️ *Kamu terlambat!* Batas check-in adalah {$thresholdFormatted} WIB.\nAlasan: {$lateReason}";
            }
        }
        
        if ($attendance) {
            $attendance->update([
                'check_in_time' => $checkInTime,
                'status' => 'hadir',
                'is_late' => $isLate,
                'work_type' => $workType,
                'late_reason' => $lateReason
            ]);
        } else {
            $attendance = Attendance::create([
                'member_id' => $member->id,
                'tanggal' => $today,
                'check_in_time' => $checkInTime,
                'status' => 'hadir',
                'is_late' => $isLate,
                'work_type' => $workType,
                'late_reason' => $lateReason
            ]);
        }

        $formattedTime = Carbon::parse($checkInTime)->format('H:i');
        $workTypeDisplay = strtoupper($workType);
        
        $message = $config->message_success_check_in ?: "✅ *Check-in Berhasil!*\n\n";
        $message .= "Nama: *{$member->nama_lengkap}*\n";
        $message .= "Kantor: *{$member->office->name}*\n";
        $message .= "Tipe Kerja: *{$workTypeDisplay}*\n";
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
     * Allows checkout anytime with progress description
     * Format: "keluar [progress description]" or just "keluar"
     */
    private function handleCheckOut($phoneNumber, $config, $originalMessage = '')
    {
        // Find member with flexible format matching
        $memberCheck = $this->getMemberOrErrorMessage($phoneNumber);
        if (!$memberCheck['success']) {
            return $memberCheck['message'];
        }
        $member = $memberCheck['member'];

        // Parse progress from message (everything after "keluar " or "pulang ")
        $progressDescription = '';
        if (preg_match('/^(?:keluar|pulang)\s+(.+)$/i', $originalMessage, $matches)) {
            $progressDescription = trim($matches[1]);
        }

        // Check if checked in today (no strict time limit for checkout)
        $today = now()->format('Y-m-d');
        $attendance = Attendance::where('member_id', $member->id)
            ->where('tanggal', $today)
            ->first();

        if (!$attendance || !$attendance->check_in_time) {
            return "❌ Kamu belum check-in hari ini.\n\nSilakan check-in terlebih dahulu dengan mengetik *masuk*.";
        }

        if ($attendance->check_out_time) {
            $checkOutTime = Carbon::parse($attendance->check_out_time)->format('H:i');
            return "ℹ️ Kamu sudah check-out hari ini pada pukul *{$checkOutTime}* WIB.";
        }

        // Update check-out time
        $checkOutTime = now()->format('H:i:s');
        $attendance->update([
            'check_out_time' => $checkOutTime
        ]);

        // Save progress if provided (append to existing or create new)
        if ($progressDescription) {
            $existingProgress = Progress::where('member_id', $member->id)
                ->where('tanggal', $today)
                ->first();

            if ($existingProgress) {
                // Append to existing description
                $existingProgress->update([
                    'description' => $existingProgress->description . "\n" . $progressDescription
                ]);
            } else {
                Progress::create([
                    'member_id'   => $member->id,
                    'tanggal'     => $today,
                    'tipe'        => 'hadir',
                    'description' => $progressDescription,
                ]);
            }
            
            Log::info('Progress saved at checkout', [
                'member_id' => $member->id,
                'tanggal' => $today,
                'progress' => $progressDescription
            ]);
        }

        // Calculate working hours
        $checkIn = Carbon::parse($attendance->check_in_time);
        $checkOut = Carbon::parse($checkOutTime);
        $workingHours = $checkIn->diffInHours($checkOut);
        $workingMinutes = $checkIn->diffInMinutes($checkOut) % 60;

        // Get today's progress from Progress table
        $progress = Progress::where('member_id', $member->id)
            ->where('tanggal', $today)
            ->first();

        Log::info('Checkout progress check', [
            'member_id' => $member->id,
            'tanggal' => $today,
            'has_progress' => $progress ? true : false,
            'progress_desc' => $progress ? $progress->description : 'N/A'
        ]);

        $workTypeDisplay = $attendance->work_type ? strtoupper($attendance->work_type) : 'N/A';

        $message = $config->message_success_check_out ?: "✅ *Check-out Berhasil!*\n\n";
        $message .= "Nama: *{$member->nama_lengkap}*\n";
        $message .= "Tipe Kerja: *{$workTypeDisplay}*\n";
        $message .= "Check-in: *{$checkIn->format('H:i')}* WIB\n";
        $message .= "Check-out: *{$checkOut->format('H:i')}* WIB\n";
        $message .= "Durasi Kerja: *{$workingHours} jam {$workingMinutes} menit*\n";
        
        // Add progress info if exists
        if ($progress && $progress->description) {
            $message .= "\n📝 *Progress Hari Ini:*\n{$progress->description}\n";
        } else {
            $message .= "\n💡 *Tips:* Gunakan format *keluar [keterangan progress]* untuk langsung menyimpan progress hari ini.\n";
            $message .= "Contoh: *keluar Membuat laporan web dan update database*\n";
        }
        
        $message .= "\nTerima kasih atas kerja keras kamu hari ini! 🎉";

        return $message;
    }

    /**
     * Handle status check
     */
    private function handleStatus($phoneNumber)
    {
        // Find member with flexible format matching
        $memberCheck = $this->getMemberOrErrorMessage($phoneNumber);
        if (!$memberCheck['success']) {
            return $memberCheck['message'];
        }
        $member = $memberCheck['member'];

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
            $workTypeDisplay = $attendance->work_type ? strtoupper($attendance->work_type) : 'N/A';
            
            $message .= "✅ Check-in: *{$checkInTime}* WIB";
            $message .= " ({$workTypeDisplay})";
            
            // Show if late with reason
            if ($attendance->is_late) {
                $message .= " ⚠️ *Terlambat*";
                if ($attendance->late_reason) {
                    $message .= "\n   Alasan: {$attendance->late_reason}";
                }
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
                
                // Show progress if exists
                $progress = Progress::where('member_id', $member->id)
                    ->where('tanggal', $today)
                    ->first();
                if ($progress && $progress->description) {
                    $message .= "\n📝 *Progress:* {$progress->description}";
                }
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
        
        $message .= "🟢 *masuk* atau *masuk WFA* atau *masuk WFO*\n";
        $message .= "    └ Check-in kehadiran\n";
        $message .= "    └ Contoh: masuk wfo, masuk wfa\n\n";
        
        $message .= "⚠️ *Jika Terlambat, sertakan alasan:*\n";
        $message .= "    *masuk WFO alasan=ada meeting*\n";
        $message .= "    *masuk WFA alasan=update server*\n\n";
        
        $message .= "🔴 *keluar* atau *pulang* [progress]\n";
        $message .= "    └ Check-out kehadiran\n";
        $message .= "    └ Format: keluar [keterangan pekerjaan hari ini]\n";
        $message .= "    └ Contoh: keluar membuat laporan web\n\n";
        
        $message .= "📋 *progress* _[laporan kegiatan]_\n";
        $message .= "    └ Input progress harian (sebelum checkout)\n";
        $message .= "    └ Contoh: progress Membuat API login\n";
        $message .= "    └ *progress lihat* — Lihat progress hari ini\n";
        $message .= "    └ *progress hapus* — Hapus progress hari ini\n\n";
        
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
     * Handle progress command
     * Commands:
     *   progress lihat          - View today's progress
     *   progress hapus          - Delete today's progress
     *   progress [description]  - Input/append progress
     */
    private function handleProgress($phoneNumber, $originalMessage, $config)
    {
        // Find member
        $memberCheck = $this->getMemberOrErrorMessage($phoneNumber);
        if (!$memberCheck['success']) {
            return $memberCheck['message'];
        }
        $member = $memberCheck['member'];

        $today = now()->format('Y-m-d');

        // Check attendance status
        $attendance = Attendance::where('member_id', $member->id)
            ->where('tanggal', $today)
            ->first();

        // Parse sub-command
        $body = trim(preg_replace('/^progress\s*/i', '', $originalMessage));
        $subCommand = strtolower($body);

        // === progress lihat ===
        if ($subCommand === 'lihat' || $subCommand === 'view') {
            $progress = Progress::where('member_id', $member->id)
                ->where('tanggal', $today)
                ->first();

            if (!$progress) {
                return "📝 *Progress Hari Ini*\n\nBelum ada progress yang tercatat untuk hari ini.\n\nKetik *progress [isi kegiatan]* untuk menambahkan.";
            }

            $tipeLabel = match($progress->tipe) {
                'hadir' => '✅ Hadir',
                'sakit' => '🏥 Sakit',
                'izin'  => '📋 Izin',
                default => $progress->tipe,
            };

            $message = "📝 *Progress Hari Ini*\n\n";
            $message .= "Tipe: {$tipeLabel}\n";
            $message .= "Tanggal: " . now()->format('d/m/Y') . "\n\n";
            $message .= "{$progress->description}";

            return $message;
        }

        // === progress hapus ===
        if ($subCommand === 'hapus' || $subCommand === 'delete') {
            if (!$attendance || !$attendance->check_in_time) {
                return "🔒 Kamu belum check-in hari ini. Progress tidak bisa dihapus.";
            }
            if ($attendance->check_out_time) {
                return "🔒 Kamu sudah checkout. Progress tidak bisa dihapus setelah checkout.";
            }

            $progress = Progress::where('member_id', $member->id)
                ->where('tanggal', $today)
                ->first();

            if (!$progress) {
                return "❌ Tidak ada progress untuk hari ini yang bisa dihapus.";
            }

            $progress->delete();
            return "🗑️ *Progress hari ini berhasil dihapus.*\n\nKetik *progress [isi kegiatan]* untuk menambahkan ulang.";
        }

        // === progress [description] - Input/append ===
        if (empty($body)) {
            return "⚠️ *Format Progress:*\n\n*progress [laporan kegiatan]*\n\nContoh:\n*progress Membuat API login dan register*\n*progress Fixing bug halaman dashboard*\n\n💡 Perintah lain:\n*progress lihat* — Lihat progress hari ini\n*progress hapus* — Hapus progress hari ini";
        }

        // Must be checked-in and not checked-out
        if (!$attendance || !$attendance->check_in_time) {
            return "🔒 *Belum Check-in*\n\nKamu harus check-in terlebih dahulu sebelum menginput progress.\n\nKetik *masuk wfo* atau *masuk wfa* untuk check-in.";
        }
        if ($attendance->check_out_time) {
            return "🔒 *Sudah Checkout*\n\nProgress tidak bisa ditambah/diubah setelah checkout.";
        }

        // Check existing progress
        $existingProgress = Progress::where('member_id', $member->id)
            ->where('tanggal', $today)
            ->first();

        if ($existingProgress) {
            // Append to existing
            $existingProgress->update([
                'description' => $existingProgress->description . "\n" . $body
            ]);

            $message = "✅ *Progress Ditambahkan!*\n\n";
            $message .= "Nama: *{$member->nama_lengkap}*\n";
            $message .= "Tanggal: " . now()->format('d/m/Y') . "\n\n";
            $message .= "📝 *Progress Lengkap:*\n{$existingProgress->description}";
            $message .= "\n\n💡 Ketik *progress [isi]* lagi untuk menambah, atau *progress lihat* untuk melihat.";
        } else {
            // Create new
            Progress::create([
                'member_id'   => $member->id,
                'tanggal'     => $today,
                'tipe'        => 'hadir',
                'description' => $body,
            ]);

            $message = "✅ *Progress Tersimpan!*\n\n";
            $message .= "Nama: *{$member->nama_lengkap}*\n";
            $message .= "Tanggal: " . now()->format('d/m/Y') . "\n\n";
            $message .= "📝 *Progress:*\n{$body}";
            $message .= "\n\n💡 Ketik *progress [isi]* lagi untuk menambah, atau *progress lihat* untuk melihat.";
        }

        return $message;
    }

    /**
     * Handle izin (permission) request
     */
    private function handleIzin($phoneNumber, $reason, $config)
    {
        // Find member with flexible format matching
        $memberCheck = $this->getMemberOrErrorMessage($phoneNumber);
        if (!$memberCheck['success']) {
            return $memberCheck['message'];
        }
        $member = $memberCheck['member'];

        // Check if reason is provided
        if (empty($reason)) {
            return "⚠️ *Format Izin Salah*\n\nSilakan ketik dengan format:\n*izin [alasan]*\n\nContoh: izin ada keperluan keluarga";
        }

        // Check if current time is within attendance hours
        $currentTime = Carbon::now()->format('H:i:s');
        $officeOpenTime = $config->reminder_check_in_time ?? '06:00:00';
        $officeCloseTime = $config->reminder_check_out_time ?? '18:00:00';
        
        if ($currentTime < $officeOpenTime || $currentTime > $officeCloseTime) {
            $openTime = Carbon::createFromFormat('H:i:s', $officeOpenTime)->format('H:i');
            $closeTime = Carbon::createFromFormat('H:i:s', $officeCloseTime)->format('H:i');
            return "⏰ Maaf, jam izin sudah ditutup.\n\nJam izin: *{$openTime} - {$closeTime}* WIB\n\nJam sekarang: *" . Carbon::now()->format('H:i') . "* WIB";
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
        // Find member with flexible format matching
        $memberCheck = $this->getMemberOrErrorMessage($phoneNumber);
        if (!$memberCheck['success']) {
            return $memberCheck['message'];
        }
        $member = $memberCheck['member'];

        // Check if reason is provided
        if (empty($reason)) {
            return "⚠️ *Format Sakit Salah*\n\nSilakan ketik dengan format:\n*sakit [keterangan]*\n\nContoh: sakit demam";
        }

        // Check if current time is within attendance hours
        $currentTime = Carbon::now()->format('H:i:s');
        $officeOpenTime = $config->reminder_check_in_time ?? '06:00:00';
        $officeCloseTime = $config->reminder_check_out_time ?? '18:00:00';
        
        if ($currentTime < $officeOpenTime || $currentTime > $officeCloseTime) {
            $openTime = Carbon::createFromFormat('H:i:s', $officeOpenTime)->format('H:i');
            $closeTime = Carbon::createFromFormat('H:i:s', $officeCloseTime)->format('H:i');
            return "⏰ Maaf, jam lapor sakit sudah ditutup.\n\nJam lapor: *{$openTime} - {$closeTime}* WIB\n\nJam sekarang: *" . Carbon::now()->format('H:i') . "* WIB";
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
            
            $response = Http::withHeaders($headers)->timeout(30)->post($url, [
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
     * Find member by phone number (flexible format matching)
     * Database stores: +62xxx format
     * Input can be: +62xxx, 62xxx, 08xxx, or various formats from WhatsApp
     */
    private function findMember($phoneNumber)
    {
        // Extract pure digits only from input
        $inputDigits = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Get all active members
        $members = Member::where('status_aktif', true)->get();
        
        Log::info('findMember - searching', [
            'phoneNumber' => $phoneNumber,
            'inputDigits' => $inputDigits,
            'activeMembers' => $members->map(function($m) {
                $digits = preg_replace('/[^0-9]/', '', $m->no_hp);
                return [
                    'id' => $m->id,
                    'nama' => $m->nama_lengkap,
                    'stored_no_hp' => $m->no_hp,
                    'digits' => $digits,
                ];
            })->toArray(),
        ]);
        
        foreach ($members as $member) {
            $memberDigits = preg_replace('/[^0-9]/', '', $member->no_hp);
            $memberLastDigits = substr($memberDigits, -10);
            $memberLastDigits11 = substr($memberDigits, -11);
            
            $inputLastDigits = substr($inputDigits, -10);
            $inputLastDigits11 = substr($inputDigits, -11);
            
            Log::debug('Comparing phone', [
                'member_id' => $member->id,
                'member_stored' => $member->no_hp,
                'input_original' => $phoneNumber,
                'member_digits' => $memberDigits,
                'input_digits' => $inputDigits,
                'exact_match' => $memberDigits === $inputDigits,
                'last10_match' => $memberLastDigits === $inputLastDigits,
                'last11_match' => $memberLastDigits11 === $inputLastDigits11,
            ]);
            
            // Check exact match first
            if ($memberDigits === $inputDigits) {
                Log::info('✅ Phone matched (exact digits)', [
                    'member_id' => $member->id,
                    'input' => $phoneNumber,
                    'stored' => $member->no_hp,
                    'name' => $member->nama_lengkap,
                ]);
                return $member;
            }
            
            // Check last digits match (handles format variations)
            if ($memberLastDigits === $inputLastDigits || $memberLastDigits11 === $inputLastDigits11) {
                Log::info('✅ Phone matched (last digits)', [
                    'member_id' => $member->id,
                    'input' => $phoneNumber,
                    'stored' => $member->no_hp,
                    'name' => $member->nama_lengkap,
                    'last_10' => $memberLastDigits === $inputLastDigits,
                    'last_11' => $memberLastDigits11 === $inputLastDigits11,
                ]);
                return $member;
            }
        }
        
        Log::info('❌ No active member found with matching phone', [
            'phoneNumber' => $phoneNumber,
            'inputDigits' => $inputDigits,
        ]);
        
        return null;
    }

    /**
     * Find member and return appropriate error message if not found
     * Accepts members with status_aktif=true (regardless of approval status)
     */
    private function getMemberOrErrorMessage($phoneNumber)
    {
        // Try find member dengan status_aktif=true
        $member = $this->findMember($phoneNumber);
        
        if ($member) {
            // Member ditemukan dan aktif
            // Check if rejected untuk warning
            if ($member->status === 'rejected') {
                return ['success' => false, 'message' => "❌ Akun kamu ditolak.\n\nAlasan: {$member->rejection_reason}\n\nSilakan hubungi admin untuk informasi lebih lanjut."];
            }
            // Member aktif, boleh lanjut (regardless of pending/approved status)
            return ['success' => true, 'member' => $member];
        }
        
        // Member tidak ditemukan dengan status_aktif=true
        // Cek apakah nomor ada di database tapi dengan status_aktif=false atau status berbeda
        $inputDigits = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Search ALL members (regardless of status_aktif) to find why not matched
        $memberExists = Member::get()->first(function($m) use ($inputDigits) {
            $memberDigits = preg_replace('/[^0-9]/', '', $m->no_hp);
            $memberLastDigits = substr($memberDigits, -10);
            $inputLastDigits = substr($inputDigits, -10);
            
            return $memberDigits === $inputDigits || $memberLastDigits === $inputLastDigits;
        });
        
        if ($memberExists) {
            // Nomor ada di database tapi tidak aktif atau status problematic
            Log::warning('Member found but not ready for bot usage', [
                'phoneNumber' => $phoneNumber,
                'member_id' => $memberExists->id,
                'nama_lengkap' => $memberExists->nama_lengkap,
                'stored_no_hp' => $memberExists->no_hp,
                'status_aktif' => $memberExists->status_aktif,
                'status' => $memberExists->status,
            ]);
            
            if ($memberExists->status === 'rejected') {
                return ['success' => false, 'message' => "❌ Akun kamu ditolak.\n\nAlasan: {$memberExists->rejection_reason}\n\nSilakan hubungi admin untuk informasi lebih lanjut."];
            }
            
            if ($memberExists->status === 'pending' && !$memberExists->status_aktif) {
                return ['success' => false, 'message' => "⏳ Akun kamu masih dalam proses persetujuan.\n\nSilakan tunggu admin mengaktifkan akun kamu, atau hubungi admin untuk info lebih lanjut."];
            }
            
            if (!$memberExists->status_aktif) {
                return ['success' => false, 'message' => "❌ Akun kamu belum diaktifkan.\n\nSilakan hubungi admin untuk aktivasi."];
            }
            
            // This shouldn't happen - already caught in findMember
            return ['success' => false, 'message' => "❌ Akun kamu mengalami masalah teknis.\n\nSilakan hubungi admin."];
        }
        
        // Nomor sama sekali tidak ditemukan di database
        Log::warning('Phone number not found in database at all', [
            'phoneNumber' => $phoneNumber,
            'inputDigits' => $inputDigits,
            'allMembers' => Member::pluck('no_hp')->toArray(),
            'allMembers_aktif' => Member::where('status_aktif', true)->pluck('no_hp')->toArray(),
        ]);
        return ['success' => false, 'message' => "❌ Nomor HP kamu belum terdaftar.\n\nSilakan hubungi admin untuk registrasi."];
    }

    /**
     * Format phone number to WhatsApp @c.us format
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
        
        // Return in @c.us format (WhatsApp standard)
        return $cleaned . '@c.us';
    }

    /**
     * Normalize phone number to +62xxx format
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
