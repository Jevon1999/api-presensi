<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->unique()->comment('Tanggal hari libur (YYYY-MM-DD)');
            $table->string('nama')->comment('Nama hari libur, e.g. Idul Fitri 1447 H');
            $table->unsignedSmallInteger('tahun')->index()->comment('Tahun hari libur, untuk filter');
            $table->string('source', 100)->default('libur.deno.dev')->comment('Sumber data: libur.deno.dev atau manual');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
