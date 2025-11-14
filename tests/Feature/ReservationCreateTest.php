<?php

declare(strict_types=1);

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('予約登録ページが表示できる', function () {
    $response = $this->get('/reservations/create');

    $response->assertSuccessful();
});

test('予約を登録できる', function () {
    $component = Volt::test('reservations.create');

    $startDateTime = now()->addDays(7)->setTime(10, 0);
    $endDateTime = now()->addDays(7)->setTime(12, 0);

    $component->set('start_datetime', $startDateTime->format('Y-m-d H:i'))
        ->set('end_datetime', $endDateTime->format('Y-m-d H:i'))
        ->set('program_name', '藍染体験')
        ->set('customer_name', 'テスト太郎')
        ->set('phone', '090-1234-5678')
        ->set('email', 'test@example.com')
        ->set('number_of_people', 3)
        ->set('source', ReservationSource::Phone->value)
        ->set('notes', 'テスト備考')
        ->call('store');

    // データベースに保存されていることを確認
    expect(Reservation::count())->toBe(1);

    $reservation = Reservation::first();
    expect($reservation->program_name)->toBe('藍染体験');
    expect($reservation->customer_name)->toBe('テスト太郎');
    expect($reservation->phone)->toBe('090-1234-5678');
    expect($reservation->email)->toBe('test@example.com');
    expect($reservation->number_of_people)->toBe(3);
    expect($reservation->source)->toBe(ReservationSource::Phone);
    expect($reservation->status)->toBe(ReservationStatus::Reserved);
    expect($reservation->notes)->toBe('テスト備考');
});

test('必須項目が未入力の場合はバリデーションエラーになる', function () {
    $component = Volt::test('reservations.create');

    // デフォルト値をクリア
    $component->set('number_of_people', '')
        ->set('source', '');

    $component->call('store');

    $component->assertHasErrors([
        'start_datetime',
        'end_datetime',
        'program_name',
        'customer_name',
        'number_of_people',
        'source',
    ]);
});

test('開始日時が終了日時より後の場合はバリデーションエラーになる', function () {
    $component = Volt::test('reservations.create');

    $startDateTime = now()->addDays(7)->setTime(12, 0);
    $endDateTime = now()->addDays(7)->setTime(10, 0);

    $component->set('start_datetime', $startDateTime->format('Y-m-d H:i'))
        ->set('end_datetime', $endDateTime->format('Y-m-d H:i'))
        ->set('program_name', '藍染体験')
        ->set('customer_name', 'テスト太郎')
        ->set('number_of_people', 3)
        ->set('source', ReservationSource::Phone->value)
        ->call('store');

    $component->assertHasErrors(['end_datetime']);
});

test('予約登録後にカレンダーページにリダイレクトされる', function () {
    $component = Volt::test('reservations.create');

    $startDateTime = now()->addDays(7)->setTime(10, 0);
    $endDateTime = now()->addDays(7)->setTime(12, 0);

    $component->set('start_datetime', $startDateTime->format('Y-m-d H:i'))
        ->set('end_datetime', $endDateTime->format('Y-m-d H:i'))
        ->set('program_name', '藍染体験')
        ->set('customer_name', 'テスト太郎')
        ->set('number_of_people', 3)
        ->set('source', ReservationSource::Phone->value)
        ->call('store');

    $component->assertRedirect(route('reservations.calendar'));
});
