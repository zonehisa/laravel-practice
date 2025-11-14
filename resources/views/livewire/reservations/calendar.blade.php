<?php

use App\Models\Reservation;

use function Livewire\Volt\computed;
use function Livewire\Volt\state;

state(['viewMode' => 'month']); // 'month' or 'week'
state(['currentDate' => fn () => now()]);

// 現在表示している期間の予約を取得
$reservations = computed(function () {
    $start = match ($this->viewMode) {
        'month' => $this->currentDate->copy()->startOfMonth()->startOfDay(),
        'week' => $this->currentDate->copy()->startOfWeek()->startOfDay(),
        default => $this->currentDate->copy()->startOfMonth()->startOfDay(),
    };

    $end = match ($this->viewMode) {
        'month' => $this->currentDate->copy()->endOfMonth()->endOfDay(),
        'week' => $this->currentDate->copy()->endOfWeek()->endOfDay(),
        default => $this->currentDate->copy()->endOfMonth()->endOfDay(),
    };

    return Reservation::whereBetween('start_datetime', [$start, $end])
        ->where('status', '!=', 'cancelled')
        ->orderBy('start_datetime')
        ->get();
});

// 月表示の日付配列を生成
$monthDays = computed(function () {
    $days = [];
    $startOfMonth = $this->currentDate->copy()->startOfMonth();
    $endOfMonth = $this->currentDate->copy()->endOfMonth();
    $startOfWeek = $startOfMonth->copy()->startOfWeek();

    $current = $startOfWeek->copy();
    while ($current->lte($endOfMonth) || $current->isSameWeek($endOfMonth)) {
        $days[] = $current->copy();
        $current->addDay();
        if (count($days) >= 42) { // 6週間分
            break;
        }
    }

    return $days;
});

// 週表示の日付配列を生成
$weekDays = computed(function () {
    $days = [];
    $startOfWeek = $this->currentDate->copy()->startOfWeek();

    for ($i = 0; $i < 7; $i++) {
        $days[] = $startOfWeek->copy()->addDays($i);
    }

    return $days;
});

$setViewMode = fn (string $mode) => $this->viewMode = $mode;

$nextMonth = function () {
    $this->currentDate = $this->currentDate->copy()->addMonth();
};

$previousMonth = function () {
    $this->currentDate = $this->currentDate->copy()->subMonth();
};

$nextWeek = function () {
    $this->currentDate = $this->currentDate->copy()->addWeek();
};

$previousWeek = function () {
    $this->currentDate = $this->currentDate->copy()->subWeek();
};

$goToToday = fn () => $this->currentDate = now();

?>

<div class="max-w-7xl mx-auto p-6">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-3xl font-bold">予約カレンダー</h1>
        <div class="flex gap-4 items-center">
            <a href="{{ route('reservations.create') }}" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                予約登録
            </a>
            <button wire:click="goToToday" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                今日
            </button>
            <div class="flex gap-2">
                <button wire:click="setViewMode('month')" 
                    class="px-4 py-2 rounded-md {{ $viewMode === 'month' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    月表示
                </button>
                <button wire:click="setViewMode('week')" 
                    class="px-4 py-2 rounded-md {{ $viewMode === 'week' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    週表示
                </button>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow">
        <div class="p-4 border-b flex items-center justify-between">
            <div class="flex gap-4 items-center">
                <button wire:click="{{ $viewMode === 'month' ? 'previousMonth' : 'previousWeek' }}" 
                    class="px-3 py-1 bg-gray-200 rounded-md hover:bg-gray-300">
                    ←
                </button>
                <h2 class="text-xl font-semibold">
                    @if($viewMode === 'month')
                        {{ $currentDate->format('Y年n月') }}
                    @else
                        {{ $currentDate->copy()->startOfWeek()->format('Y年n月j日') }} ～ {{ $currentDate->copy()->endOfWeek()->format('n月j日') }}
                    @endif
                </h2>
                <button wire:click="{{ $viewMode === 'month' ? 'nextMonth' : 'nextWeek' }}" 
                    class="px-3 py-1 bg-gray-200 rounded-md hover:bg-gray-300">
                    →
                </button>
            </div>
        </div>

        @if($viewMode === 'month')
            <!-- 月表示 -->
            <div class="p-4">
                <div class="grid grid-cols-7 gap-1 mb-2">
                    @foreach(['日', '月', '火', '水', '木', '金', '土'] as $day)
                        <div class="text-center font-semibold text-gray-600 py-2">{{ $day }}</div>
                    @endforeach
                </div>
                <div class="grid grid-cols-7 gap-1">
                    @foreach($this->monthDays as $day)
                        <div class="min-h-[100px] border border-gray-200 p-2 {{ $day->isSameDay(now()) ? 'bg-blue-50' : '' }} {{ !$day->isSameMonth($currentDate) ? 'bg-gray-50 text-gray-400' : '' }}">
                            <div class="text-sm font-semibold mb-1">{{ $day->day }}</div>
                            <div class="space-y-1">
                                @foreach($this->reservations as $reservation)
                                    @if($reservation->start_datetime->isSameDay($day))
                                        <a href="{{ route('reservations.show', $reservation) }}" 
                                           class="block text-xs bg-blue-500 text-white p-1 rounded truncate hover:bg-blue-600" 
                                           title="{{ $reservation->program_name }} - {{ $reservation->customer_name }}">
                                            {{ $reservation->start_datetime->format('H:i') }} {{ $reservation->program_name }}
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <!-- 週表示 -->
            <div class="p-4">
                <div class="grid grid-cols-7 gap-1">
                    @foreach($this->weekDays as $day)
                        <div class="border border-gray-200 p-2">
                            <div class="text-center font-semibold mb-2 {{ $day->isSameDay(now()) ? 'text-blue-600' : '' }}">
                                <div>{{ $day->format('n/j') }}</div>
                                <div class="text-sm text-gray-600">{{ ['日', '月', '火', '水', '木', '金', '土'][$day->dayOfWeek] }}</div>
                            </div>
                            <div class="space-y-1">
                                @foreach($this->reservations as $reservation)
                                    @if($reservation->start_datetime->isSameDay($day))
                                        <a href="{{ route('reservations.show', $reservation) }}" 
                                           class="block text-xs bg-blue-500 text-white p-2 rounded mb-1 hover:bg-blue-600">
                                            <div class="font-semibold">{{ $reservation->start_datetime->format('H:i') }} - {{ $reservation->end_datetime->format('H:i') }}</div>
                                            <div>{{ $reservation->program_name }}</div>
                                            <div class="text-xs opacity-90">{{ $reservation->customer_name }}</div>
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
