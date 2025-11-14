<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Reserved = 'reserved'; // 予約済み
    case Cancelled = 'cancelled'; // キャンセル
    case Completed = 'completed'; // 完了

    public function label(): string
    {
        return match ($this) {
            self::Reserved => '予約済み',
            self::Cancelled => 'キャンセル',
            self::Completed => '完了',
        };
    }
}
