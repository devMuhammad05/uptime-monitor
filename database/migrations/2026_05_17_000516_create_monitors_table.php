<?php

use App\Enums\MonitorStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('url')->unique();
            $table->unsignedTinyInteger('check_interval')->default(5);
            $table->unsignedTinyInteger('threshold')->default(3);
            $table->string('status')->default(MonitorStatus::Pending);
            $table->unsignedTinyInteger('consecutive_failures')->default(0);
            $table->timestamp('last_checked_at')->nullable();
            $table->decimal('uptime_percentage', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitors');
    }
};
