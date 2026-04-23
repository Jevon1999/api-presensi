<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Holiday extends Model
{
    protected $fillable = [
        'tanggal',
        'nama',
        'tahun',
        'source',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'tahun'   => 'integer',
    ];

    /**
     * Check apakah tanggal tertentu adalah hari libur.
     */
    public static function isHoliday(Carbon $date): bool
    {
        return static::where('tanggal', $date->toDateString())->exists();
    }

    /**
     * Ambil nama hari libur pada tanggal tertentu, atau null jika bukan libur.
     */
    public static function getHolidayName(Carbon $date): ?string
    {
        return static::where('tanggal', $date->toDateString())->value('nama');
    }

    /**
     * Ambil semua libur untuk satu tahun.
     */
    public static function forYear(int $year)
    {
        return static::where('tahun', $year)->orderBy('tanggal')->get();
    }
}
