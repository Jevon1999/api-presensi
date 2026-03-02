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
        'webhook_events' => 'array',
        'reminder_check_in_time' => 'datetime:H:i:s',
        'reminder_check_out_time' => 'datetime:H:i:s',
        'check_in_late_threshold' => 'datetime:H:i:s',
        'reminder_enabled' => 'boolean',
        'mark_messages_read' => 'boolean',
        'reject_calls' => 'boolean',
        'require_late_reason' => 'boolean',
        'is_active' => 'boolean',
        'typing_delay_ms' => 'integer',
    ];
    
    // Singleton pattern: always get first row
    public static function config()
    {
        return static::firstOrCreate(['id' => 1]);
    }
}