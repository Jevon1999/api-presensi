<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Office extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name'
    ];

    //relasi
    public function location() {
        return $this->hasMany(OfficeLocation::class);
    }

    public function locations() {
        return $this->hasMany(OfficeLocation::class);
    }

    public function members() {
        return $this->hasMany(Member::class);
    }

    //dapetin lokasi aktif
    public function activeLocations() {
        return $this->hasMany(OfficeLocation::class)->where('is_active', true);
    }

}
