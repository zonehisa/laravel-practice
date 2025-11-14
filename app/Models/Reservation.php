<?php

namespace App\Models;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    /** @use HasFactory<\Database\Factories\ReservationFactory> */
    use HasFactory;

    protected $fillable = [
        'start_datetime',
        'end_datetime',
        'program_name',
        'customer_name',
        'phone',
        'email',
        'number_of_people',
        'source',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
            'source' => ReservationSource::class,
            'status' => ReservationStatus::class,
        ];
    }
}
