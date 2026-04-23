<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Progress extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'progresses';

    protected $fillable = [
        'member_id',
        'tanggal',
        'tipe',
        'description',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'tipe'    => 'string',
    ];

    //relasi
    public function member() 
    {
        return $this->belongsTo(Member::class);
    }

}
