<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('order_emails_enabled')->default(true)->after('push_notifications_enabled');
            $table->boolean('promotional_emails_enabled')->default(true)->after('order_emails_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['order_emails_enabled', 'promotional_emails_enabled']);
        });
    }
};
