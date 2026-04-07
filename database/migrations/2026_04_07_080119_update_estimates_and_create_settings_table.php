<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->string('client_name', 100)->nullable()->after('client_id');
            $table->string('client_nickname', 100)->nullable()->after('client_name');
            $table->string('client_phone', 50)->nullable()->after('client_nickname');
            $table->unsignedSmallInteger('validity_days')->default(3)->after('status');
            $table->timestamp('issued_at')->nullable()->after('validity_days');
            $table->text('memo')->nullable()->after('issued_at');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn(['client_name', 'client_nickname', 'client_phone', 'validity_days', 'issued_at', 'memo']);
        });

        Schema::dropIfExists('settings');
    }
};
