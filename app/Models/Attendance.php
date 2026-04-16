<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'member_id', 'tanggal', 'check_in_time', 'check_out_time', 'status', 'is_late', 'late_reason', 'work_type'
    ];

    protected $casts = [
        'tanggal' => 'date',
        'check_in_time' => 'datetime:H:i:s',
        'check_out_time' => 'datetime:H:i:s',
        'is_late' => 'boolean'
    ];

    public function member() {
        return $this->belongsTo(Member::class);
    }

    public function permissions() {
        return $this->hasMany(Permission::class);
    }

    public function resetLogs() {
        return $this->hasMany(AttendanceResetLog::class);
    }
}
