<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Registration;

use App\Domain\Registration\Services\EnterpriseUserIdGenerator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class EnterpriseUserIdGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_correct_format(): void
    {
        $generator = new EnterpriseUserIdGenerator;
        $id = $generator->generate();

        $year = date('Y');
        $this->assertMatchesRegularExpression("/^USR-{$year}-[A-F0-9]{8}$/", $id);
    }

    public function test_generates_unique_ids(): void
    {
        $generator = new EnterpriseUserIdGenerator;
        $id1 = $generator->generate();
        $id2 = $generator->generate();

        $this->assertNotEquals($id1, $id2);
    }

    public function test_collision_retry_throws_after_max_attempts(): void
    {
        // Mock the User model to always say the ID exists
        $generator = new class extends EnterpriseUserIdGenerator
        {
            protected function buildCandidate(): string
            {
                return 'USR-2026-COLLIDED';
            }
        };

        // Seed the collision
        User::factory()->create([
            'email' => 'col@example.com',
            'enterprise_user_id' => 'USR-2026-COLLIDED',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('EnterpriseUserIdGenerator: failed to generate a unique enterprise_user_id');

        $generator->generate();
    }
}
