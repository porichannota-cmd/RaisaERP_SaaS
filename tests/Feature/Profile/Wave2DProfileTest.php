<?php

namespace Tests\Feature\Profile;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Registration\Enums\AccountStatus;
use App\Models\User;
use App\Models\UserBankAccount;
use App\Models\UserMfsAccount;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class Wave2DProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_profile(): void
    {
        $response = $this->get('/profile');
        $response->assertRedirect('/login');
    }

    public function test_profile_incomplete_user_can_access_profile(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::PROFILE_INCOMPLETE]);
        $response = $this->actingAs($user)->get('/profile');
        $response->assertStatus(200);
    }

    public function test_blocked_user_cannot_access_profile(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::BLOCKED]);
        $response = $this->actingAs($user)->get('/profile');
        $response->assertSessionHasErrors(['identifier']); // Throws validation exception caught by auth flow or fails directly
    }

    public function test_user_a_cannot_modify_user_b_bank_account(): void
    {
        $userA = User::factory()->create(['account_status' => AccountStatus::PROFILE_INCOMPLETE]);
        $userB = User::factory()->create(['account_status' => AccountStatus::PROFILE_INCOMPLETE]);

        $this->actingAs($userA)->post('/profile/bank-accounts', [
            'bank_name' => 'Bank A',
            'account_holder_name' => 'User A',
            'account_number' => '1234567890',
        ]);

        $bankAccount = UserBankAccount::where('user_id', $userA->id)->first();

        // User B tries to update User A's bank account
        $response = $this->actingAs($userB)->patch('/profile/bank-accounts/' . $bankAccount->id, [
            'bank_name' => 'Bank Hacker',
        ]);

        $response->assertNotFound();
    }

    public function test_same_user_duplicate_bank_rejected(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::PROFILE_INCOMPLETE]);

        $this->actingAs($user)->post('/profile/bank-accounts', [
            'bank_name' => 'Bank A',
            'account_holder_name' => 'User',
            'account_number' => '1234567890',
        ]);

        $response = $this->actingAs($user)->post('/profile/bank-accounts', [
            'bank_name' => 'Bank B',
            'account_holder_name' => 'User',
            'account_number' => '1234567890', // Duplicate
        ]);

        $response->assertSessionHasErrors(['account_number']);
    }

    public function test_different_user_duplicate_bank_accepted(): void
    {
        $userA = User::factory()->create(['account_status' => AccountStatus::PROFILE_INCOMPLETE]);
        $userB = User::factory()->create(['account_status' => AccountStatus::PROFILE_INCOMPLETE]);

        $this->actingAs($userA)->post('/profile/bank-accounts', [
            'bank_name' => 'Bank A',
            'account_holder_name' => 'User A',
            'account_number' => '1234567890',
        ]);

        $response = $this->actingAs($userB)->post('/profile/bank-accounts', [
            'bank_name' => 'Bank B',
            'account_holder_name' => 'User B',
            'account_number' => '1234567890', // Duplicate globally, but valid per user
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals(1, UserBankAccount::where('user_id', $userB->id)->count());
    }

    public function test_plaintext_account_number_never_stored(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::PROFILE_INCOMPLETE]);

        $this->actingAs($user)->post('/profile/bank-accounts', [
            'bank_name' => 'Bank A',
            'account_holder_name' => 'User',
            'account_number' => '1234567890',
        ]);

        $bankAccount = UserBankAccount::where('user_id', $user->id)->first();

        $this->assertNotEquals('1234567890', $bankAccount->account_number_encrypted);
        // Ensure the string '1234567890' doesn't appear in the database record at all
        $this->assertStringNotContainsString('1234567890', $bankAccount->account_number_encrypted);
        $this->assertStringNotContainsString('1234567890', $bankAccount->account_number_fingerprint);
    }

    public function test_setting_second_primary_demotes_first(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::PROFILE_INCOMPLETE]);

        $this->actingAs($user)->post('/profile/bank-accounts', [
            'bank_name' => 'Bank A',
            'account_holder_name' => 'User',
            'account_number' => '1111',
            'is_primary' => true,
        ]);

        $this->actingAs($user)->post('/profile/bank-accounts', [
            'bank_name' => 'Bank B',
            'account_holder_name' => 'User',
            'account_number' => '2222',
            'is_primary' => true,
        ]);

        $accounts = UserBankAccount::where('user_id', $user->id)->orderBy('id')->get();

        $this->assertFalse($accounts[0]->is_primary);
        $this->assertTrue($accounts[1]->is_primary);
    }

    public function test_mfs_duplicate_per_user_rejected_but_allowed_across_users(): void
    {
        $userA = User::factory()->create(['account_status' => AccountStatus::PROFILE_INCOMPLETE]);
        $userB = User::factory()->create(['account_status' => AccountStatus::PROFILE_INCOMPLETE]);

        $payload = [
            'provider' => 'bKash',
            'mobile_number' => '01711000000',
        ];

        $this->actingAs($userA)->post('/profile/mfs-accounts', $payload)->assertSessionHasNoErrors();
        // User A doing it again should fail
        $this->actingAs($userA)->post('/profile/mfs-accounts', $payload)->assertSessionHasErrors(['mobile_number']);

        // User B doing it should succeed
        $this->actingAs($userB)->post('/profile/mfs-accounts', $payload)->assertSessionHasNoErrors();
    }
}
