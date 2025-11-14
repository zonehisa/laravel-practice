<?php

use App\Enums\ReservationStatus;
use App\Models\Reservation;

use function Livewire\Volt\state;

state(['reservation' => fn (Reservation $reservation) => $reservation]);

$cancel = function () {
    $this->reservation->update([
        'status' => ReservationStatus::Cancelled,
    ]);

    session()->flash('message', '予約をキャンセルしました。');
};

?>

<div class="max-w-4xl mx-auto p-6">
    <div class="mb-6">
        <h1 class="text-3xl font-bold">予約詳細</h1>
        <a href="{{ route('reservations.calendar') }}" class="text-blue-600 hover:text-blue-800 mt-2 inline-block">
            ← カレンダーに戻る
        </a>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">予約ID</label>
                <p class="text-gray-900">{{ $reservation->id }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">予約状態</label>
                <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold
                    {{ $reservation->status === ReservationStatus::Reserved ? 'bg-blue-100 text-blue-800' : '' }}
                    {{ $reservation->status === ReservationStatus::Cancelled ? 'bg-red-100 text-red-800' : '' }}
                    {{ $reservation->status === ReservationStatus::Completed ? 'bg-green-100 text-green-800' : '' }}">
                    {{ $reservation->status->label() }}
                </span>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">開始日時</label>
                <p class="text-gray-900">{{ $reservation->start_datetime->format('Y年n月j日 H:i') }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">終了日時</label>
                <p class="text-gray-900">{{ $reservation->end_datetime->format('Y年n月j日 H:i') }}</p>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">体験プログラム名</label>
                <p class="text-gray-900">{{ $reservation->program_name }}</p>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">予約者名</label>
                <p class="text-gray-900">{{ $reservation->customer_name }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">電話番号</label>
                <p class="text-gray-900">{{ $reservation->phone ?? '-' }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">メールアドレス</label>
                <p class="text-gray-900">{{ $reservation->email ?? '-' }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">予約人数</label>
                <p class="text-gray-900">{{ $reservation->number_of_people }}名</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">予約経路</label>
                <p class="text-gray-900">{{ $reservation->source->label() }}</p>
            </div>

            @if($reservation->notes)
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">備考</label>
                    <p class="text-gray-900 whitespace-pre-wrap">{{ $reservation->notes }}</p>
                </div>
            @endif
        </div>

        <div class="mt-6 flex gap-4">
            @if($reservation->status !== ReservationStatus::Cancelled)
                <a href="{{ route('reservations.edit', $reservation) }}"
                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    編集
                </a>
                <button wire:click="cancel" wire:confirm="予約をキャンセルしますか？"
                    class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                    キャンセル
                </button>
            @endif
        </div>
    </div>
</div>
