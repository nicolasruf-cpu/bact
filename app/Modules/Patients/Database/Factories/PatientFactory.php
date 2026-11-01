<?php

namespace App\Modules\Patients\Database\Factories;

use App\Modules\Patients\Enums\Sex;
use App\Modules\Patients\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pacientes SINTÉTICOS para pruebas y demo.
 *
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'document_number' => (string) fake()->unique()->numberBetween(10_000_000, 60_000_000),
            'last_name' => fake()->lastName(),
            'first_name' => fake()->firstName(),
            'birth_date' => fake()->dateTimeBetween('-90 years', '-1 year'),
            'sex' => fake()->randomElement([Sex::Female, Sex::Male]),
        ];
    }
}
