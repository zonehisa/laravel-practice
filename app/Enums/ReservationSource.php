<?php

namespace App\Enums;

enum ReservationSource: string
{
    case Jalan = 'jalan'; // じゃらん
    case Asoview = 'asoview'; // アソビュー
    case Website = 'website'; // 自社HP
    case Phone = 'phone'; // 電話
    case Other = 'other'; // その他

    public function label(): string
    {
        return match ($this) {
            self::Jalan => 'じゃらん',
            self::Asoview => 'アソビュー',
            self::Website => '自社HP',
            self::Phone => '電話',
            self::Other => 'その他',
        };
    }
}
