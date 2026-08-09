# RAISA ERP — PROVIDER ADAPTER ARCHITECTURE
**Version:** 1.1.0 | **Date:** 2026-08-09 | **Phase:** 00A

## Change Log
| Version | Date | Change |
|---------|------|--------|
| 1.0.0 | 2026-08-09 | Initial |
| 1.1.0 | 2026-08-09 | Payment provider routing correction, intent pinning, failover policy clarification |

---

## 1. Principle

All external provider integrations use the Adapter Pattern with defined contracts.
Provider logic never leaks into business domains.
All credentials encrypted server-side, NEVER exposed to browser. (I09)

---

## 2. Payment Provider Routing (REVISED v1.1.0)

### 2.1 Selection BEFORE Intent Creation

Provider selection happens BEFORE creating a payment intent:

```php
class PaymentProviderRouter
{
    /**
     * Select the best available provider for a new payment.
     * Called BEFORE intent creation.
     * Returns: provider_key to be pinned to the new intent.
     */
    public function selectProvider(string $tenantId, Money $amount, string $currency): string
    {
        $providers = ProviderConfiguration::forTenant($tenantId)
            ->ofType('PAYMENT')
            ->enabled()
            ->orderBy('priority')
            ->get();

        foreach ($providers as $config) {
            $adapter = $this->factory->make($config);
            if ($adapter->healthStatus()->isHealthy()) {
                return $config->provider_key;
            }
        }

        throw new NoAvailablePaymentProviderException();
    }
}
```

### 2.2 Intent Pinning (Invariant I25)

```php
class PaymentService
{
    public function createIntent(PaymentRequest $request): PaymentIntent
    {
        // Provider selected HERE — before intent creation
        $providerKey = $this->router->selectProvider(
            tenantId: app('tenant.id'),
            amount: $request->amount,
            currency: $request->currency,
        );

        // Intent created WITH provider pinned
        $intent = PaymentIntent::create([
            'tenant_id'       => app('tenant.id'),
            'provider_key'    => $providerKey,  // LOCKED — immutable after creation
            'amount'          => $request->amount->toDecimalString(),
            'currency'        => $request->amount->currency(),
            'idempotency_key' => $request->idempotencyKey,
            'status'          => 'pending',
        ]);

        // Initiate with pinned provider
        $adapter = $this->factory->make($providerKey, app('tenant.id'));
        $result = $adapter->initiate(PaymentIntent::fromModel($intent));

        $intent->update([
            'provider_ref' => $result->providerRef,
            'status'       => 'initiated',
        ]);

        return $intent;
    }
}
```

### 2.3 Failed Intent Recovery

```php
// IF provider fails AFTER intent created:
// DO NOT silently change provider_key on existing intent.
// Mark the intent failed. User creates a NEW intent.

$intent->update([
    'status'         => 'provider_failed',
    'failure_reason' => $e->getMessage(),
]);

AuditLog::record('PAYMENT_PROVIDER_FAILURE', [
    'intent_id'    => $intent->id,
    'provider_key' => $intent->provider_key,
    'reason'       => $e->getMessage(),
]);

// User retries -> creates NEW intent with NEW idempotency_key
// NEW intent may use different provider (fresh selection)
```

---

## 3. Payment Provider Contract

```php
interface PaymentProviderContract
{
    public function initiate(PaymentIntent $intent): PaymentInitiateResult;
    public function verify(string $providerRef): PaymentVerifyResult;
    public function capture(string $providerRef): PaymentCaptureResult;
    public function refund(string $providerRef, Money $amount): RefundResult;
    public function query(string $providerRef): PaymentQueryResult;
    public function verifyWebhookSignature(string $payload, string $signature): bool;
    public function reconcile(DateRange $range): Collection;
    public function healthCheck(): ProviderHealthStatus;
}
```

### Target Bangladesh Payment Adapters (require authorized credentials)
- bKash (Payment Gateway API)
- Nagad (Merchant API)
- SSLCommerz
- EPS / Easy Payment System
- Bank/Card providers

**Status until credentials provided:** SANDBOX mode only. Never fabricate success. (I11)

---

## 4. Courier Provider Contract

```php
interface CourierProviderContract
{
    public function createShipment(ShipmentRequest $request): ShipmentResult;
    public function cancelShipment(string $trackingId): CancelResult;
    public function getTrackingStatus(string $trackingId): TrackingStatus;
    public function bulkTrack(array $trackingIds): Collection;
    public function calculateCharge(CourierChargeRequest $request): CourierCharge;
    public function createLabel(string $trackingId): LabelResult;
    public function createManifest(array $trackingIds): ManifestResult;
    public function verifyWebhookSignature(string $payload, string $signature): bool;
    public function healthCheck(): ProviderHealthStatus;
}
```

Courier providers may use transparent failover (create a new shipment with a different provider).
This is unlike payment providers where intent pinning applies.

---

## 5. SMS Provider Contract

```php
interface SmsProviderContract
{
    public function send(SmsMessage $message): SmsSendResult;
    public function sendBulk(array $messages): array;
    public function sendOtp(string $mobile, string $otp): SmsSendResult;
    public function queryDeliveryStatus(string $messageId): DeliveryStatus;
    public function getBalance(): SmsBalance;
    public function healthCheck(): ProviderHealthStatus;
}
```

SMS providers MAY use transparent failover (attempt secondary provider if primary fails).
OTP SMS specifically: if primary fails, secondary is attempted BEFORE returning failure to user.
Failover is transparent to the user.

---

## 6. Identity Verification Contract

```php
interface IdentityVerificationProviderContract
{
    public function verifyNid(NidVerificationRequest $request): NidVerificationResult;
    public function extractNidData(string $mediaPath): NidExtractionResult;
    public function healthCheck(): ProviderHealthStatus;
}
```

**CRITICAL (I11, I12):**
- If Porichoy credentials absent: status = UNVERIFIED. Never VERIFIED.
- If API returns error: status = FAILED. Never VERIFIED.
- Never fabricate a successful government verification.

---

## 7. AI Provider Contract

```php
interface AiProviderContract
{
    public function complete(AiCompletionRequest $request): AiCompletionResult;
    public function embed(AiEmbedRequest $request): AiEmbedResult;
    public function transcribe(AiTranscribeRequest $request): AiTranscribeResult;
    public function synthesize(AiSynthesizeRequest $request): AiSynthesizeResult;
    public function healthCheck(): ProviderHealthStatus;
}
```

API keys NEVER exposed to browser. (I09)

---

## 8. Email Provider Contract

```php
interface EmailProviderContract
{
    public function send(EmailMessage $message): EmailSendResult;
    public function sendTemplate(string $template, array $data, string $to): EmailSendResult;
    public function queryStatus(string $messageId): EmailDeliveryStatus;
    public function healthCheck(): ProviderHealthStatus;
}
```

Email providers MAY use transparent failover.

---

## 9. Provider Configuration Schema

```sql
provider_configurations
  id              CHAR(26) PK
  tenant_id       CHAR(26) NULL   -- NULL = platform-level default
  provider_type   VARCHAR(50)     -- PAYMENT, COURIER, SMS, AI, EMAIL, WHATSAPP, IDENTITY
  provider_key    VARCHAR(50)     -- e.g., 'bkash', 'pathao', 'mim_sms'
  display_name    VARCHAR(100)
  mode            ENUM('SANDBOX','LIVE')
  credentials     TEXT NOT NULL   -- AES-256 encrypted JSON, never returned to browser
  settings        JSON NULL       -- non-secret settings
  priority        TINYINT DEFAULT 0
  enabled         BOOLEAN DEFAULT FALSE
  health_status   ENUM('HEALTHY','DEGRADED','DOWN','UNKNOWN') DEFAULT 'UNKNOWN'
  last_health_check TIMESTAMP NULL
  created_at, updated_at
```

Credentials column: encrypted with Laravel's `Crypt::encrypt()` (AES-256-CBC).
After initial save, credentials are NEVER returned to the browser in any API response.

---

## 10. Failover Policy Summary

| Provider Type | Failover Policy | Intent Pinning |
|--------------|----------------|---------------|
| Payment | NO transparent failover. Intent pinned. New intent = new selection. | YES (I25) |
| Courier | YES, transparent failover for new shipments. Existing shipments unchanged. | NO |
| SMS | YES, transparent failover before returning failure to user. | NO |
| Email | YES, transparent failover. | NO |
| AI | YES, transparent failover within provider contract. | NO |
| Identity/KYC | NO fallback. Fail with FAILED status. | N/A |

---

## 11. Provider Health Monitoring

```
Every 5 minutes: ProviderHealthCheckJob for each enabled provider
  -> call provider.healthCheck()
  -> update provider_configurations.health_status
  -> if DOWN: alert SA via notification
  -> if was DOWN, now HEALTHY: log recovery

Health check results inform provider selection.
A DEGRADED provider has lower effective priority than HEALTHY.
A DOWN provider is excluded from selection entirely.
```

---

*Document Owner: Principal Architect | v1.1.0 | Invariants: I09, I11, I12, I25*
