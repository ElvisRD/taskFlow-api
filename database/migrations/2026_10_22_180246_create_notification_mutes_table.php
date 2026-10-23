<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_mutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('muteable_type');
            $table->unsignedBigInteger('muteable_id');
            $table->timestamps();

            $table->unique(['user_id', 'muteable_type', 'muteable_id']);
            $table->index(['muteable_type', 'muteable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_mutes');
    }
};
