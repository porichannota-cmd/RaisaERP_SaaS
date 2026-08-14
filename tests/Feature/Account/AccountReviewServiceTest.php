<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Domain\Account\Services\AccountReviewService;
use App\Domain\Identity\Enums\IdentityVerificationStatus;
use App\Domain\Registration\Enums\AccountStatus;
use App\Models\AccountReviewRequest;
use App\Models\ProfileSectionStatus;
use App\Models\User;
use App\Models\UserIdentityVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    private AccountReviewService $reviewService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reviewService = app(AccountReviewService::class);
    }

    public function test_can_request_review_when_prerequisites_met()
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::PROFILE_INCOMPLETE,
        ]);

        UserIdentityVerification::create([
            'user_id' => $user->id,
            'document_type' => 'nid',
            'provider' => 'null',
            'status' => IdentityVerificationStatus::VERIFIED,
            'verified_at' => now(),
        ]);

        foreach (['PERSONAL', 'CONTACT', 'ADDRESS'] as $section) {
            ProfileSectionStatus::create([
                'user_id' => $user->id,
                'section' => $section,
                'status' => 'COMPLETE',
            ]);
        }

        $request = $this->reviewService->requestReview($user);

        $this->assertNotNull($request);
        $this->assertEquals('PENDING', $request->status);

        $user->refresh();
        $this->assertEquals(AccountStatus::PENDING_APPROVAL, $user->account_status);
        $this->assertDatabaseHas('account_status_history', [
            'user_id' => $user->id,
            'new_status' => 'pending_approval',
        ]);
    }

    public function test_cannot_request_review_if_identity_not_verified()
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::PROFILE_INCOMPLETE,
        ]);

        // Identity is FAILED instead of VERIFIED
        UserIdentityVerification::create([
            'user_id' => $user->id,
            'document_type' => 'nid',
            'provider' => 'null',
            'status' => IdentityVerificationStatus::FAILED,
        ]);

        $this->expectException(ValidationException::class);
        $this->reviewService->requestReview($user);
    }

    public function test_can_approve_account()
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::PENDING_APPROVAL,
        ]);

        $request = AccountReviewRequest::create([
            'user_id' => $user->id,
            'status' => 'PENDING',
        ]);

        $admin = User::factory()->create();

        $decision = $this->reviewService->approve($request, $admin);

        $this->assertEquals('APPROVE', $decision->decision);

        $request->refresh();
        $this->assertEquals('APPROVED', $request->status);

        $user->refresh();
        $this->assertEquals(AccountStatus::ACTIVE, $user->account_status);
    }

    public function test_can_reject_account()
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::PENDING_APPROVAL,
        ]);

        $request = AccountReviewRequest::create([
            'user_id' => $user->id,
            'status' => 'PENDING',
        ]);

        $admin = User::factory()->create();

        $decision = $this->reviewService->reject($request, $admin, 'Incomplete details');

        $this->assertEquals('REJECT', $decision->decision);
        $this->assertEquals('Incomplete details', $decision->reason);

        $request->refresh();
        $this->assertEquals('REJECTED', $request->status);

        $user->refresh();
        $this->assertEquals(AccountStatus::REJECTED, $user->account_status);
    }

    public function test_cannot_approve_twice()
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::ACTIVE, // already active
        ]);

        $request = AccountReviewRequest::create([
            'user_id' => $user->id,
            'status' => 'APPROVED', // already approved
        ]);

        $admin = User::factory()->create();

        $this->expectException(ValidationException::class);
        $this->reviewService->approve($request, $admin);
    }

    public function test_cannot_request_review_if_profile_incomplete()
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::PROFILE_INCOMPLETE,
        ]);

        UserIdentityVerification::create([
            'user_id' => $user->id,
            'document_type' => 'nid',
            'provider' => 'null',
            'status' => IdentityVerificationStatus::VERIFIED,
            'verified_at' => now(),
        ]);

        // We only complete 2 out of 3 required sections
        foreach (['PERSONAL', 'CONTACT'] as $section) {
            ProfileSectionStatus::create([
                'user_id' => $user->id,
                'section' => $section,
                'status' => 'COMPLETE',
            ]);
        }

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Required base profile sections are not fully complete.');
        $this->reviewService->requestReview($user);
    }

    public function test_null_provider_safety_test()
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::PROFILE_INCOMPLETE,
        ]);

        foreach (['PERSONAL', 'CONTACT', 'ADDRESS'] as $section) {
            ProfileSectionStatus::create([
                'user_id' => $user->id,
                'section' => $section,
                'status' => 'COMPLETE',
            ]);
        }

        // Test with NOT_STARTED
        $verification = UserIdentityVerification::create([
            'user_id' => $user->id,
            'document_type' => 'nid',
            'provider' => 'null',
            'status' => IdentityVerificationStatus::NOT_STARTED,
        ]);

        try {
            $this->reviewService->requestReview($user);
            $this->fail('Should have thrown ValidationException');
        } catch (ValidationException $e) {
            $this->assertEquals('Identity is not verified.', $e->getMessage());
        }

        // Test with MANUAL_REVIEW_REQUIRED
        $verification->update([
            'status' => IdentityVerificationStatus::MANUAL_REVIEW_REQUIRED,
        ]);

        try {
            $this->reviewService->requestReview($user);
            $this->fail('Should have thrown ValidationException');
        } catch (ValidationException $e) {
            $this->assertEquals('Identity is not verified.', $e->getMessage());
        }
    }

    public function test_duplicate_pending_request_prevention()
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::PROFILE_INCOMPLETE,
        ]);

        UserIdentityVerification::create([
            'user_id' => $user->id,
            'document_type' => 'nid',
            'provider' => 'null',
            'status' => IdentityVerificationStatus::VERIFIED,
            'verified_at' => now(),
        ]);

        foreach (['PERSONAL', 'CONTACT', 'ADDRESS'] as $section) {
            ProfileSectionStatus::create([
                'user_id' => $user->id,
                'section' => $section,
                'status' => 'COMPLETE',
            ]);
        }

        $request1 = $this->reviewService->requestReview($user);
        $this->assertNotNull($request1);

        // Attempt second submission
        try {
            $this->reviewService->requestReview($user);
            $this->fail('Should throw validation exception on duplicate submission attempt');
        } catch (ValidationException $e) {
            $this->assertEquals('User must be in PROFILE_INCOMPLETE or REJECTED state to request approval.', $e->getMessage());
        }
        
        $this->assertEquals(1, AccountReviewRequest::where('user_id', $user->id)->count());
    }

    public function test_resubmission_flow()
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::REJECTED,
        ]);

        UserIdentityVerification::create([
            'user_id' => $user->id,
            'document_type' => 'nid',
            'provider' => 'null',
            'status' => IdentityVerificationStatus::VERIFIED,
            'verified_at' => now(),
        ]);

        foreach (['PERSONAL', 'CONTACT', 'ADDRESS'] as $section) {
            ProfileSectionStatus::create([
                'user_id' => $user->id,
                'section' => $section,
                'status' => 'COMPLETE',
            ]);
        }

        // Initially rejected, now they resubmit
        $request = $this->reviewService->requestReview($user);

        $this->assertNotNull($request);
        $this->assertEquals('PENDING', $request->status);

        $user->refresh();
        $this->assertEquals(AccountStatus::PENDING_APPROVAL, $user->account_status);
        $this->assertDatabaseHas('account_status_history', [
            'user_id' => $user->id,
            'previous_status' => 'rejected',
            'new_status' => 'pending_approval',
        ]);
    }
}
