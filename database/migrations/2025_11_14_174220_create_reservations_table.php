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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');
            $table->string('program_name');
            $table->string('customer_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->integer('number_of_people');
            $table->string('source'); // 予約経路
            $table->string('status')->default('reserved'); // 予約状態
            $table->text('notes')->nullable(); // 備考
            $table->timestamps();

            // インデックスを追加（検索パフォーマンス向上）
            $table->index('start_datetime');
            $table->index('end_datetime');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
