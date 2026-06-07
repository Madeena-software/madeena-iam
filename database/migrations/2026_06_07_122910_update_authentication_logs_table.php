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
        Schema::table('authentication_logs', function (Blueprint $table) {
            // Alter polymorphic columns to be nullable
            $table->uuid('authenticatable_id')->nullable()->change();
            $table->string('authenticatable_type')->nullable()->change();

            // Add new columns
            $table->foreignUuid('client_id')->nullable()->constrained('oauth_clients')->nullOnDelete();
            $table->string('status')->nullable();
            $table->string('auth_type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('authentication_logs', function (Blueprint $table) {
            $table->uuid('authenticatable_id')->nullable(false)->change();
            $table->string('authenticatable_type')->nullable(false)->change();

            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn(['status', 'auth_type']);
        });
    }
};
