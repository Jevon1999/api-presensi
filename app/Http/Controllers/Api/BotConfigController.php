<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotConfig;
use Illuminate\Http\Request;

class BotConfigController extends Controller
{
    /**
     * Get bot config (singleton) — mapped to frontend field names
     */
    public function index()
    {
        $config = BotConfig::config();

        return response()->json([
            'data' => $config->toFrontend(),
        ]);
    }

    /**
     * Update bot config — handles is_active (1/0) and check_in_late_threshold
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'is_active'                  => 'sometimes|boolean',
            'reminder_enabled'           => 'sometimes|boolean',
            'reminder_time'              => 'sometimes|nullable|string',
            'checkout_reminder_enabled'  => 'sometimes|boolean',
            'checkout_reminder_time'     => 'sometimes|nullable|string',
            'check_in_late_threshold'    => 'sometimes|nullable|string',
            'require_late_reason'        => 'sometimes|boolean',
            'message_remind_check_in'    => 'sometimes|nullable|string',
            'message_remind_check_out'   => 'sometimes|nullable|string',
            'message_success_check_in'   => 'sometimes|nullable|string',
            'message_success_check_out'  => 'sometimes|nullable|string',
            'message_error'              => 'sometimes|nullable|string',
            'message_greeting'           => 'sometimes|nullable|string',
        ]);

        $config = BotConfig::config();

        // Map frontend field names to DB column names
        $data = [];

        if (isset($validated['is_active'])) {
            $data['is_active'] = (bool) $validated['is_active'];
        }
        if (isset($validated['reminder_enabled'])) {
            $data['reminder_enabled'] = (bool) $validated['reminder_enabled'];
        }
        if (array_key_exists('reminder_time', $validated)) {
            $data['reminder_check_in_time'] = $validated['reminder_time'];
        }
        if (isset($validated['checkout_reminder_enabled'])) {
            // store as part of reminder_enabled or add new column — use reminder_enabled as master
            // We'll store checkout state in a separate approach: just update check_out field
            $data['checkout_reminder_enabled'] = (bool) $validated['checkout_reminder_enabled'];
        }
        if (array_key_exists('checkout_reminder_time', $validated)) {
            $data['reminder_check_out_time'] = $validated['checkout_reminder_time'];
        }
        if (array_key_exists('check_in_late_threshold', $validated)) {
            $data['check_in_late_threshold'] = $validated['check_in_late_threshold'];
        }
        if (isset($validated['require_late_reason'])) {
            $data['require_late_reason'] = (bool) $validated['require_late_reason'];
        }

        // Message templates
        foreach (['message_remind_check_in', 'message_remind_check_out', 'message_success_check_in', 'message_success_check_out', 'message_error', 'message_greeting'] as $field) {
            if (array_key_exists($field, $validated)) {
                $data[$field] = $validated[$field];
            }
        }

        $config->update($data);

        return response()->json([
            'message' => 'Konfigurasi bot berhasil disimpan.',
            'data'    => $config->fresh(),
        ]);
    }
}
