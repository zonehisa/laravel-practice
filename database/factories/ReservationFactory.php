<?php

namespace Database\Factories;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDateTime = $this->faker->dateTimeBetween('now', '+1 month');
        $endDateTime = (clone $startDateTime)->modify('+2 hours');

        return [
            'start_datetime' => $startDateTime,
            'end_datetime' => $endDateTime,
            'program_name' => $this->faker->randomElement(['藍染体験', '絞り染め体験', '型染め体験', '手織り体験']),
            'customer_name' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->safeEmail(),
            'number_of_people' => $this->faker->numberBetween(1, 10),
            'source' => $this->faker->randomElement(ReservationSource::cases()),
            'status' => ReservationStatus::Reserved,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
