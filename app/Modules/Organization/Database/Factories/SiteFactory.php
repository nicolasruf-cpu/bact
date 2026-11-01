<?php

namespace App\Modules\Organization\Database\Factories;

use App\Modules\Organization\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('S??#')),
            'name' => 'Sede '.fake()->unique()->city(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
