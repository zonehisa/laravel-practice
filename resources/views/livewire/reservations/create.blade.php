<?php

use App\Enums\ReservationSource;
use App\Models\Reservation;

use function Livewire\Volt\state;

state([
    'start_datetime' => '',
    'end_datetime' => '',
    'program_name' => '',
    'customer_name' => '',
    'phone' => '',
    'email' => '',
    'number_of_people' => 1,
    'source' => ReservationSource::Phone->value,
    'notes' => '',
]);

$store = function () {
    $validated = $this->validate([
        'start_datetime' => ['required', 'date', 'after:now'],
        'end_datetime' => ['required', 'date', 'after:start_datetime'],
        'program_name' => ['required', 'string', 'max:255'],
        'customer_name' => ['required', 'string', 'max:255'],
        'phone' => ['nullable', 'string', 'max:255'],
        'email' => ['nullable', 'email', 'max:255'],
        'number_of_people' => ['required', 'integer', 'min:1'],
        'source' => ['required', 'string', 'in:'.implode(',', array_column(ReservationSource::cases(), 'value'))],
        'notes' => ['nullable', 'string'],
    ]);

    Reservation::create([
        'start_datetime' => $validated['start_datetime'],
        'end_datetime' => $validated['end_datetime'],
        'program_name' => $validated['program_name'],
        'customer_name' => $validated['customer_name'],
        'phone' => $validated['phone'] ?? null,
        'email' => $validated['email'] ?? null,
        'number_of_people' => $validated['number_of_people'],
        'source' => ReservationSource::from($validated['source']),
        'status' => \App\Enums\ReservationStatus::Reserved,
        'notes' => $validated['notes'] ?? null,
    ]);

    return redirect()->route('reservations.calendar');
};

?>

<div class="max-w-4xl mx-auto p-6">
    <div class="mb-6">
        <h1 class="text-3xl font-bold">予約登録</h1>
        <a href="{{ route('reservations.calendar') }}" class="text-blue-600 hover:text-blue-800 mt-2 inline-block">
            ← カレンダーに戻る
        </a>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <form wire:submit="store">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- 開始日時 -->
                <div>
                    <label for="start_datetime" class="block text-sm font-medium text-gray-700 mb-2">
                        開始日時 <span class="text-red-500">*</span>
                    </label>
                    <input type="datetime-local" id="start_datetime" wire:model="start_datetime"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @error('start_datetime')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- 終了日時 -->
                <div>
                    <label for="end_datetime" class="block text-sm font-medium text-gray-700 mb-2">
                        終了日時 <span class="text-red-500">*</span>
                    </label>
                    <input type="datetime-local" id="end_datetime" wire:model="end_datetime"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @error('end_datetime')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- 体験プログラム名 -->
                <div class="md:col-span-2">
                    <label for="program_name" class="block text-sm font-medium text-gray-700 mb-2">
                        体験プログラム名 <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="program_name" wire:model="program_name"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @error('program_name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- 予約者名 -->
                <div class="md:col-span-2">
                    <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-2">
                        予約者名 <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="customer_name" wire:model="customer_name"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @error('customer_name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- 電話番号 -->
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                        電話番号
                    </label>
                    <input type="tel" id="phone" wire:model="phone"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @error('phone')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- メールアドレス -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        メールアドレス
                    </label>
                    <input type="email" id="email" wire:model="email"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- 予約人数 -->
                <div>
                    <label for="number_of_people" class="block text-sm font-medium text-gray-700 mb-2">
                        予約人数 <span class="text-red-500">*</span>
                    </label>
                    <input type="number" id="number_of_people" wire:model="number_of_people" min="1"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @error('number_of_people')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- 予約経路 -->
                <div>
                    <label for="source" class="block text-sm font-medium text-gray-700 mb-2">
                        予約経路 <span class="text-red-500">*</span>
                    </label>
                    <select id="source" wire:model="source"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        @foreach(ReservationSource::cases() as $source)
                            <option value="{{ $source->value }}">{{ $source->label() }}</option>
                        @endforeach
                    </select>
                    @error('source')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- 備考 -->
                <div class="md:col-span-2">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                        備考
                    </label>
                    <textarea id="notes" wire:model="notes" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-6 flex gap-4">
                <button type="submit" wire:loading.attr="disabled"
                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50">
                    <span wire:loading.remove>登録</span>
                    <span wire:loading>登録中...</span>
                </button>
                <a href="{{ route('reservations.calendar') }}"
                    class="px-6 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                    キャンセル
                </a>
            </div>
        </form>
    </div>
</div>
