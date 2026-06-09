<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Backfill any existing records where client_app_user_id IS NULL
        $nullRecords = DB::table('client_user')->whereNull('client_app_user_id')->get();
        foreach ($nullRecords as $record) {
            DB::table('client_user')
                ->where('id', $record->id)
                ->update(['client_app_user_id' => (string) Str::uuid()]);
        }

        // 2. Change column to NOT NULL
        Schema::table('client_user', function (Blueprint $table) {
            $table->string('client_app_user_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Change column back to nullable
        Schema::table('client_user', function (Blueprint $table) {
            $table->string('client_app_user_id')->nullable()->change();
        });
    }
};
