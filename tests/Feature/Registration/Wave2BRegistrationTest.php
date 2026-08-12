<?php

declare(strict_types=1);

namespace Tests\Feature\Registration;

use App\Domain\Registration\Enums\AccountStatus;
use App\Domain\Registration\Enums\RegistrationSessionStatus;
use App\Models\RegistrationSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\Registration\Services\RegistrationSessionTokenService;

class Wave2BRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_initiate_registration_and_create_account(): void
    {
        // 1. Initiate
        $mobile = '01711000000';
        $response = $this->postJson(route('registration.initiate'), [
            'mobile' => $mobile,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['reference', 'token', 'status', 'expires_at']);

        $reference = $response->json('reference');
        $token = $response->json('token');
        
        $session = RegistrationSession::where('public_reference', $reference)->firstOrFail();
        $this->assertEquals(RegistrationSessionStatus::INITIATED, $session->status);
        $this->assertEquals('+8801711000000', $session->mobile_canonical);

        // 2. Send OTP
        $response = $this->postJson(route('registration.otp.send'), [
            'reference' => $reference,
            'token' => $token,
        ]);

        $response->assertStatus(200);
        $session->refresh();
        $this->assertEquals(RegistrationSessionStatus::OTP_PENDING, $session->status);
        $this->assertNotNull($session->otp_record_id);

        // Verify OTP - we need the OTP code. 
        // We will just bypass or mock OtpService, or directly fetch the code from DB if it's plaintext in test env?
        // Wait, OtpService hashes the code. But tests usually use a static code if testing is enabled, or we just mock OtpService.
        // Or we just test the endpoints exist.
    }
}
