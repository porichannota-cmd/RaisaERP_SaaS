<?php

declare(strict_types=1);

namespace Tests\Feature\Registration;

use App\Domain\Registration\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Wave2ASchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_email_can_be_nullable(): void
    {
        $user = User::factory()->create(['email' => null]);

        $this->assertNull($user->email);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => null]);
    }

    public function test_user_email_must_be_unique_if_not_null(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $this->expectException(QueryException::class);
        User::factory()->create(['email' => 'test@example.com']);
    }

    public function test_legacy_email_users_can_authenticate(): void
    {
        $user = User::factory()->create([
            'email' => 'legacy@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->assertTrue(auth()->attempt(['email' => 'legacy@example.com', 'password' => 'password123']));
    }

    public function test_mobile_canonical_must_be_unique(): void
    {
        User::factory()->create(['mobile_canonical' => '+8801700000000', 'email' => 'test1@example.com']);

        $this->expectException(QueryException::class);
        User::factory()->create(['mobile_canonical' => '+8801700000000', 'email' => 'test2@example.com']);
    }

    public function test_account_status_casts_to_enum(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::ACTIVE->value]);

        $user->refresh();

        $this->assertInstanceOf(AccountStatus::class, $user->account_status);
        $this->assertEquals(AccountStatus::ACTIVE, $user->account_status);
    }
}
