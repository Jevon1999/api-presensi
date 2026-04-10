<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotConfig extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'waha_api_url',
        'waha_api_key',
        'waha_session_name',
        'webhook_url',
        'webhook_secret',
        'webhook_events',
        'reminder_check_in_time',
        'reminder_check_out_time',
        'check_in_late_threshold',
        'require_late_reason',
        'timezone',
        'reminder_enabled',
        'checkout_reminder_enabled',
        'typing_delay_ms',
        'mark_messages_read',
        'reject_calls',
        'message_greeting',
        'message_remind_check_in',
        'message_remind_check_out',
        'message_success_check_in',
        'message_success_check_out',
        'message_already_checked_in',
        'message_error',
        'is_active',
    ];
    
    protected $casts = [
        'webhook_events'             => 'array',
        'reminder_check_in_time'     => 'string',
        'reminder_check_out_time'    => 'string',
        'check_in_late_threshold'    => 'string',
        'reminder_enabled'           => 'boolean',
        'checkout_reminder_enabled'  => 'boolean',
        'mark_messages_read'         => 'boolean',
        'reject_calls'               => 'boolean',
        'require_late_reason'        => 'boolean',
        'is_active'                  => 'boolean',
        'typing_delay_ms'            => 'integer',
    ];
    
    // Singleton pattern: always get first row
    public static function config()
    {
        return static::firstOrCreate(['id' => 1]);
    }

    /**
     * Return a frontend-friendly mapped array
     */
    public function toFrontend(): array
    {
        return [
            'is_active'                  => $this->is_active,
            'reminder_enabled'           => $this->reminder_enabled,
            'reminder_time'              => $this->reminder_check_in_time,
            'checkout_reminder_enabled'  => $this->checkout_reminder_enabled,
            'checkout_reminder_time'     => $this->reminder_check_out_time,
            'check_in_late_threshold'    => $this->check_in_late_threshold,
            'require_late_reason'        => $this->require_late_reason,
            'message_remind_check_in'    => $this->message_remind_check_in,
            'message_remind_check_out'   => $this->message_remind_check_out,
            'message_success_check_in'   => $this->message_success_check_in,
            'message_success_check_out'  => $this->message_success_check_out,
            'message_error'              => $this->message_error,
            'message_remind_late'        => $this->message_error,
            'message_greeting'           => $this->message_greeting,
        ];
    }
}