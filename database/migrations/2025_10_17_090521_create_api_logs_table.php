<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('service_name')->nullable()->comment('Nama service/modul yang memanggil');
            $table->string('method')->nullable()->comment('HTTP Method (GET, POST, PUT, DELETE)');
            $table->string('endpoint')->nullable()->comment('Endpoint yang diakses');
            $table->json('payload')->nullable()->comment('Data yang dikirim (request body/parameters)');
            $table->json('response')->nullable()->comment('Response yang diterima');
            $table->integer('status_code')->nullable()->comment('HTTP Status Code');
            $table->string('status')->default('pending')->comment('Status: pending, success, error');
            $table->text('error_message')->nullable()->comment('Pesan error jika ada');
            $table->string('ip_address')->nullable()->comment('IP Address pengirim request');
            $table->string('user_agent')->nullable()->comment('User Agent pengirim request');
            $table->string('request_id')->nullable()->comment('Unique Request ID untuk tracking');
            $table->decimal('response_time', 8, 3)->nullable()->comment('Waktu response dalam milidetik');
            $table->timestamps();

            // Indexes untuk performa query
            $table->index('service_name');
            $table->index('method');
            $table->index('status_code');
            $table->index('status');
            $table->index('request_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};
