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
        Schema::create('client_user', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('oauth_clients')->cascadeOnDelete();
            $table->string('client_app_user_id')->nullable()->comment('ID of the user in the client application database');
            $table->enum('status', ['pending_approval', 'approved', 'suspended'])->default('pending_approval');
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users');
            $table->boolean('is_blocked')->default(false);
            $table->timestamps();
            $table->foreignUuid('created_by')->nullable()->constrained('users');
            $table->foreignUuid('updated_by')->nullable()->constrained('users');
            $table->softDeletes();
            $table->foreignUuid('deleted_by')->nullable()->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_user');
    }
};
