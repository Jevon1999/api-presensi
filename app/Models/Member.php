<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'user_id',
        'no_hp',
        'office_id',
        'nama_lengkap',
        'jenis_kelamin',
        'asal_sekolah',
        'tanggal_mulai_magang',
        'tanggal_selesai_magang',
        'status_aktif',
        'status',
        'rejection_reason',
        'created_by',
    ];

    protected $casts = [
        'status_aktif' => 'boolean',
        'tanggal_mulai_magang' => 'date',
        'tanggal_selesai_magang' => 'date',
    ];

    public function office() {
        return $this->belongsTo(Office::class);
    }

    public function attendances() {
        return $this->hasMany(Attendance::class);
    }

    public function progresses() {
        return $this->hasMany(Progress::class);
    }

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function user() {
        return $this->belongsTo(User::class);
    }


    //helper: dapatkan kehadiran hari ini
    public function todayAttendance() 
    {
        return $this->hasOne(Attendance::class)
        ->whereDate('tanggal', today());
    }


}
