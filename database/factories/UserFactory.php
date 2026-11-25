<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * PIN-auth users (no email/password). Default PIN is 123456.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected $model = \App\Models\User::class;

    public function definition(): array
    {
        return [
            'name'      => fake()->name(),
            'pin_hash'  => password_hash('123456', PASSWORD_BCRYPT),
            'pin2_hash' => null,
            'is_admin'  => false,
            'active'    => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['is_admin' => true]);
    }

    public function withPin(string $pin): static
    {
        return $this->state(fn () => ['pin_hash' => password_hash($pin, PASSWORD_BCRYPT)]);
    }
}
