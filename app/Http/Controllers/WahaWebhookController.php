<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\ProgressController;
use Illuminate\Support\Facades\Log;

class WahaWebhookController extends Controller
{
    public function handle(Request $request)
    {
        try {
            // Get raw JSON input
            $rawInput = $request->getContent();
            
            // Parse JSON manually if needed
            $data = [];
            if ($rawInput) {
                $data = json_decode($rawInput, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::error('JSON Parse Error: ' . json_last_error_msg());
                    return response()->json(['status' => 'json_error'], 400);
                }
            }
            
            // Fallback to request input
            if (empty($data)) {
                $data = $request->all();
            }
            
            // Extract from Waha webhook payload
            $payload = $data['payload'] ?? $data;
            $from = $payload['from'] ?? null;
            $text = strtolower(trim($payload['body'] ?? ''));
            $session = $data['session'] ?? 'default';
            $event = $data['event'] ?? 'message';
            $fromMe = $payload['fromMe'] ?? false;

            Log::info('Webhook received:', [
                'from' => $from,
                'text' => $text,
                'session' => $session,
                'event' => $event,
                'fromMe' => $fromMe,
                'raw_length' => strlen($rawInput)
            ]);

            // Skip jika pesan dari bot sendiri atau tidak ada content
            if (!$from || !$text || $fromMe) {
                Log::info('Message ignored', ['reason' => !$from ? 'no_from' : (!$text ? 'no_text' : 'from_me')]);
                return response()->json(['status' => 'ignored']);
            }

            // ambil nomor WA (hilangkan @c.us dan format)
            $phone = str_replace(['@c.us', '+'], '', $from);

            Log::info('Processing command', ['phone' => $phone, 'command' => $text]);

            // Process command dan kirim response ke WhatsApp
            if ($text === 'checkin') {
                return $this->processAndReply($session, $from, 'checkin', $phone);
            }

            if ($text === 'checkout') {
                return $this->processAndReply($session, $from, 'checkout', $phone);
            }

            if (str_starts_with($text, 'progress ')) {
                $desc = substr($text, 9);
                return $this->processAndReply($session, $from, 'progress', $phone, $desc);
            }

            // Command tidak dikenali
            Log::info('Unknown command', ['command' => $text]);
            
            $this->sendWhatsAppMessage($session, $from, 
                "❓ *Perintah tidak dikenali*\n\n" .
                "Gunakan salah satu perintah berikut:\n" .
                "• `checkin` - Untuk absen masuk\n" .
                "• `checkout` - Untuk absen pulang\n" .
                "• `progress [deskripsi]` - Untuk laporan progress"
            );

            return response()->json(['status' => 'unknown_command']);
            
        } catch (\Exception $e) {
            Log::error('Webhook error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['status' => 'error'], 500);
        }
    }
    
    // Method untuk kirim pesan ke WhatsApp via Waha
    private function sendWhatsAppMessage($session, $chatId, $message)
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['X-Api-Key' => 'bedf62f6c7a34bc69ed45a0dff6b4974'])
                ->post('http://localhost:3000/api/sendText', [
                    'session' => $session,
                    'chatId' => $chatId,
                    'text' => $message
                ]);
            
            Log::info('Message sent to WhatsApp:', [
                'chatId' => $chatId,
                'status' => $response->status()
            ]);
            
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Error sending WhatsApp message: ' . $e->getMessage());
            return false;
        }
    }
    
    // Method untuk proses command dan kirim reply
    private function processAndReply($session, $chatId, $command, $phone, $description = null)
    {
        try {
            $message = '';
            
            if ($command === 'checkin') {
                $result = $this->processCheckIn($phone);
                $message = $result['message'];
            } elseif ($command === 'checkout') {
                $result = $this->processCheckOut($phone);
                $message = $result['message'];
            } elseif ($command === 'progress') {
                $result = $this->processProgress($phone, $description);
                $message = $result['message'];
            }
            
            // Kirim pesan ke WhatsApp
            $this->sendWhatsAppMessage($session, $chatId, $message);
            
            return response()->json(['status' => 'sent']);
            
        } catch (\Exception $e) {
            $errorMessage = "❌ *Terjadi kesalahan*\n\nSilakan coba lagi atau hubungi admin.";
            $this->sendWhatsAppMessage($session, $chatId, $errorMessage);
            
            Log::error('Process command error: ' . $e->getMessage());
            return response()->json(['status' => 'error'], 500);
        }
    }

    // Process check-in dan return message
    private function processCheckIn($phone)
    {
        try {
            // Buat request object untuk attendance controller
            $attendanceRequest = new Request([
                'no_hp' => $phone,
            ]);
            
            // Panggil attendance controller langsung
            $attendanceController = new AttendanceController();
            $response = $attendanceController->checkIn($attendanceRequest);
            
            // Extract response data
            $data = json_decode($response->getContent(), true);
            
            // Format pesan yang user-friendly untuk WhatsApp
            if ($response->getStatusCode() == 201) {
                // Sukses check-in
                $member = $data['data']['member'];
                $attendance = $data['data']['attendance'];
                
                $message = "✅ *Check-in Berhasil!*\n\n" .
                          "👤 *Nama:* {$member['nama']}\n" .
                          "🏢 *Kantor:* {$member['office']}\n" .
                          "📅 *Tanggal:* {$attendance['tanggal']}\n" .
                          "🕐 *Waktu Check-in:* {$attendance['check_in_time']}\n" .
                          "📊 *Status:* " . ucfirst($attendance['status']) . "\n\n" .
                          "Selamat bekerja! 💪";
            } else {
                // Error
                $message = "❌ *Check-in Gagal*\n\n" . 
                          "📝 " . ($data['message'] ?? 'Terjadi kesalahan') . "\n\n" .
                          "Silakan coba lagi atau hubungi admin.";
            }
            
            return ['message' => $message];
            
        } catch (\Exception $e) {
            $message = "❌ *Error Check-in*\n\n" .
                      "📝 " . $e->getMessage() . "\n\n" .
                      "Silakan coba lagi atau hubungi admin.";
            
            return ['message' => $message];
        }
    }

    // Process check-out dan return message
    private function processCheckOut($phone)
    {
        try {
            // Buat request object untuk attendance controller
            $attendanceRequest = new Request([
                'no_hp' => $phone,
            ]);
            
            // Panggil attendance controller langsung
            $attendanceController = new AttendanceController();
            $response = $attendanceController->checkOut($attendanceRequest);
            
            // Extract response data
            $data = json_decode($response->getContent(), true);
            
            // Format pesan yang user-friendly untuk WhatsApp
            if ($response->getStatusCode() == 200) {
                // Sukses check-out
                $member = $data['data']['member'];
                $attendance = $data['data']['attendance'];
                
                $message = "✅ *Check-out Berhasil!*\n\n" .
                          "👤 *Nama:* {$member['nama']}\n" .
                          "📅 *Tanggal:* {$attendance['tanggal']}\n" .
                          "🕐 *Check-in:* {$attendance['check_in_time']}\n" .
                          "🕑 *Check-out:* {$attendance['check_out_time']}\n" .
                          "⏱️ *Total Jam Kerja:* {$attendance['working_hours']}\n\n" .
                          "Terima kasih dan sampai jumpa besok! 👋";
            } else {
                // Error
                $message = "❌ *Check-out Gagal*\n\n" . 
                          "📝 " . ($data['message'] ?? 'Terjadi kesalahan') . "\n\n" .
                          "Silakan coba lagi atau hubungi admin.";
            }
            
            return ['message' => $message];
            
        } catch (\Exception $e) {
            $message = "❌ *Error Check-out*\n\n" .
                      "📝 " . $e->getMessage() . "\n\n" .
                      "Silakan coba lagi atau hubungi admin.";
            
            return ['message' => $message];
        }
    }

    // Process progress dan return message
    private function processProgress($phone, $desc)
    {
        try {
            // Buat request object untuk progress controller
            $progressRequest = new Request([
                'no_hp' => $phone,
                'description' => $desc
            ]);
            
            // Panggil progress controller langsung
            $progressController = new ProgressController();
            $response = $progressController->store($progressRequest);
            
            // Extract response data
            $data = json_decode($response->getContent(), true);
            
            // Format pesan yang user-friendly untuk WhatsApp
            if ($response->getStatusCode() == 201) {
                $message = "📝 *Progress Berhasil Disimpan!*\n\n" .
                          "📅 *Tanggal:* " . date('d/m/Y') . "\n" .
                          "📝 *Deskripsi:* $desc\n\n" .
                          "Tetap semangat! 💪";
            } else {
                $message = "❌ *Progress Gagal Disimpan*\n\n" . 
                          "📝 " . ($data['message'] ?? 'Terjadi kesalahan') . "\n\n" .
                          "Silakan coba lagi atau hubungi admin.";
            }
            
            return ['message' => $message];
            
        } catch (\Exception $e) {
            $message = "❌ *Error Progress*\n\n" .
                      "📝 " . $e->getMessage() . "\n\n" .
                      "Silakan coba lagi atau hubungi admin.";
            
            return ['message' => $message];
        }
    }
}
