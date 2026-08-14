<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlatformReviewerAssignment>
 */
class PlatformReviewerAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'capability' => 'ACCOUNT_REVIEW',
            'status' => 'ACTIVE',
            'granted_at' => now(),
        ];
    }
}
