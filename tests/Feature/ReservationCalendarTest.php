<?php

declare(strict_types=1);

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('予約カレンダーページが表示できる', function () {
    $response = $this->get('/reservations');

    $response->assertSuccessful();
});

test('月表示で予約が表示される', function () {
    // 今月の予約を作成
    $reservation = Reservation::factory()->create([
        'start_datetime' => now()->startOfMonth()->addDays(5)->setTime(10, 0),
        'end_datetime' => now()->startOfMonth()->addDays(5)->setTime(12, 0),
        'program_name' => '藍染体験',
        'customer_name' => 'テスト太郎',
        'status' => ReservationStatus::Reserved,
    ]);

    $component = Volt::test('reservations.calendar');

    // 今月の予約が含まれていることを確認
    $reservations = $component->get('reservations');
    expect($reservations)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
    expect($reservations->contains('id', $reservation->id))->toBeTrue();
});

test('週表示に切り替えられる', function () {
    $component = Volt::test('reservations.calendar');

    // デフォルトは月表示
    expect($component->get('viewMode'))->toBe('month');

    // 週表示に切り替え
    $component->call('setViewMode', 'week');

    expect($component->get('viewMode'))->toBe('week');
});

test('月表示で前月・次月に移動できる', function () {
    $component = Volt::test('reservations.calendar');

    $currentMonth = $component->get('currentDate')->format('Y-m');

    // 次月に移動
    $component->call('nextMonth');
    $nextMonth = $component->get('currentDate')->format('Y-m');
    expect($nextMonth)->not->toBe($currentMonth);

    // 前月に移動
    $component->call('previousMonth');
    $previousMonth = $component->get('currentDate')->format('Y-m');
    expect($previousMonth)->toBe($currentMonth);
});

test('週表示で前週・次週に移動できる', function () {
    $component = Volt::test('reservations.calendar');
    $component->call('setViewMode', 'week');

    $currentWeekStart = $component->get('currentDate')->format('Y-m-d');

    // 次週に移動
    $component->call('nextWeek');
    $nextWeekStart = $component->get('currentDate')->format('Y-m-d');
    expect($nextWeekStart)->not->toBe($currentWeekStart);

    // 前週に移動
    $component->call('previousWeek');
    $previousWeekStart = $component->get('currentDate')->format('Y-m-d');
    expect($previousWeekStart)->toBe($currentWeekStart);
});

test('予約がない日も正しく表示される', function () {
    // 予約を作成しない

    $component = Volt::test('reservations.calendar');

    // エラーが発生しないことを確認
    $reservations = $component->get('reservations');
    expect($reservations)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
    expect($reservations->count())->toBe(0);
});
