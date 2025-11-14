<?php

declare(strict_types=1);

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('予約詳細ページが表示できる', function () {
    $reservation = Reservation::factory()->create();

    $response = $this->get("/reservations/{$reservation->id}");

    $response->assertSuccessful();
});

test('予約詳細で予約情報が表示される', function () {
    $reservation = Reservation::factory()->create([
        'program_name' => '藍染体験',
        'customer_name' => 'テスト太郎',
        'phone' => '090-1234-5678',
        'email' => 'test@example.com',
        'number_of_people' => 3,
        'source' => ReservationSource::Phone,
        'status' => ReservationStatus::Reserved,
        'notes' => 'テスト備考',
    ]);

    $component = Volt::test('reservations.show', ['reservation' => $reservation]);

    expect($component->get('reservation'))->toBeInstanceOf(Reservation::class);
    expect($component->get('reservation')->id)->toBe($reservation->id);
});

test('予約を編集できる', function () {
    $reservation = Reservation::factory()->create([
        'program_name' => '藍染体験',
        'customer_name' => 'テスト太郎',
    ]);

    $component = Volt::test('reservations.edit', ['reservation' => $reservation]);

    $startDateTime = now()->addDays(10)->setTime(14, 0);
    $endDateTime = now()->addDays(10)->setTime(16, 0);

    $component->set('start_datetime', $startDateTime->format('Y-m-d H:i'))
        ->set('end_datetime', $endDateTime->format('Y-m-d H:i'))
        ->set('program_name', '絞り染め体験')
        ->set('customer_name', 'テスト花子')
        ->set('number_of_people', 5)
        ->call('update');

    $reservation->refresh();
    expect($reservation->program_name)->toBe('絞り染め体験');
    expect($reservation->customer_name)->toBe('テスト花子');
    expect($reservation->number_of_people)->toBe(5);
});

test('予約をキャンセルできる', function () {
    $reservation = Reservation::factory()->create([
        'status' => ReservationStatus::Reserved,
    ]);

    $component = Volt::test('reservations.show', ['reservation' => $reservation]);

    $component->call('cancel');

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Cancelled);
});

test('キャンセル済みの予約は編集できない', function () {
    $reservation = Reservation::factory()->create([
        'status' => ReservationStatus::Cancelled,
    ]);

    $response = $this->get("/reservations/{$reservation->id}/edit");

    // キャンセル済みの場合は詳細ページにリダイレクトまたはエラー
    $response->assertStatus(403); // または適切なエラーハンドリング
});

test('予約編集後に詳細ページにリダイレクトされる', function () {
    $reservation = Reservation::factory()->create();

    $component = Volt::test('reservations.edit', ['reservation' => $reservation]);

    $startDateTime = now()->addDays(10)->setTime(14, 0);
    $endDateTime = now()->addDays(10)->setTime(16, 0);

    $component->set('start_datetime', $startDateTime->format('Y-m-d H:i'))
        ->set('end_datetime', $endDateTime->format('Y-m-d H:i'))
        ->set('program_name', '絞り染め体験')
        ->set('customer_name', 'テスト花子')
        ->set('number_of_people', 3)
        ->set('source', ReservationSource::Phone->value)
        ->call('update');

    $component->assertRedirect(route('reservations.show', $reservation));
});
