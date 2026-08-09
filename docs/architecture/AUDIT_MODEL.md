# RAISA ERP — AUDIT ACTOR MODEL
**Version:** 1.0.0 | **Date:** 2026-08-09 | **Phase:** 00B

---

## 1. Audit Actor Types

```php
enum AuditActorType: string
{
    case USER              = 'USER';            // Authenticated human user
    case PLATFORM_ADMIN    = 'PLATFORM_ADMIN';  // Super Admin cross-tenant
    case SYSTEM            = 'SYSTEM';          // Internal system operation (CLI, migration)
    case QUEUE_WORKER      = 'QUEUE_WORKER';    // Background job execution
    case SCHEDULED_JOB     = 'SCHEDULED_JOB';   // Scheduled/cron task
    case API_CLIENT        = 'API_CLIENT';      // Service principal / API key
    case WEBHOOK           = 'WEBHOOK';         // Inbound external webhook
}
```

---

## 2. Audit Log Schema

```sql
audit_logs
  id              BIGINT UNSIGNED AUTO_INCREMENT PK   -- sequential for ordering
  event_id        CHAR(26) UNIQUE NOT NULL            -- ULID for external reference
  tenant_id       CHAR(26) NULL INDEX                 -- NULL for platform-level events
  actor_type      ENUM('USER','PLATFORM_ADMIN','SYSTEM','QUEUE_WORKER','SCHEDULED_JOB','API_CLIENT','WEBHOOK') NOT NULL
  actor_id        CHAR(26) NULL                       -- user_id / service_principal_id / null for SYSTEM
  impersonator_id CHAR(26) NULL                       -- set when SA impersonates tenant user
  event           VARCHAR(100) NOT NULL               -- e.g., 'INVOICE_CREATED', 'PAYMENT_APPROVED'
  severity        ENUM('INFO','WARNING','CRITICAL') DEFAULT 'INFO'
  auditable_type  VARCHAR(50) NULL                    -- resource type
  auditable_id    CHAR(26) NULL                       -- resource ID
  old_values      JSON NULL                           -- before state (NEVER include secrets)
  new_values      JSON NULL                           -- after state (NEVER include secrets)
  ip_address      VARCHAR(45) NULL                    -- for USER/PLATFORM_ADMIN
  user_agent      TEXT NULL                           -- for USER/PLATFORM_ADMIN
  request_id      VARCHAR(100) NULL                   -- HTTP request ID
  correlation_id  CHAR(36) NULL INDEX                 -- trace cross-service operations
  extra           JSON NULL                           -- additional context
  created_at      TIMESTAMP NOT NULL
  -- NO updated_at. NO deleted_at. IMMUTABLE.
  INDEX idx_audit_tenant_event (tenant_id, event)
  INDEX idx_audit_tenant_created (tenant_id, created_at DESC)
  INDEX idx_audit_actor (actor_type, actor_id)
```

---

## 3. Mandatory Audit Fields by Actor Type

| Field | USER | PLATFORM_ADMIN | SYSTEM | QUEUE_WORKER | SCHEDULED_JOB | API_CLIENT | WEBHOOK |
|-------|------|---------------|--------|-------------|--------------|-----------|---------|
| tenant_id | ✅ | ✅ | ✅/null | ✅ | ✅ | ✅ | ✅ |
| actor_type | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| actor_id | user_id | sa_user_id | null | null | null | principal_id | null |
| impersonator_id | null | sa_user_id | null | null | null | null | null |
| ip_address | ✅ | ✅ | null | null | null | ✅ | ✅ |
| user_agent | ✅ | ✅ | null | null | null | null | null |
| request_id | ✅ | ✅ | null | ✅ | null | ✅ | ✅ |
| correlation_id | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

---

## 4. AuditService

```php
class AuditService
{
    public function record(
        string            $event,
        ?string           $tenantId         = null,
        AuditActorType    $actorType        = AuditActorType::SYSTEM,
        ?string           $actorId          = null,
        ?string           $impersonatorId   = null,
        ?string           $auditableType    = null,
        ?string           $auditableId      = null,
        ?array            $oldValues        = null,
        ?array            $newValues        = null,
        AuditSeverity     $severity         = AuditSeverity::INFO,
        ?string           $correlationId    = null,
        ?string           $requestId        = null,
        ?array            $extra            = null,
    ): void {
        // CRITICAL: scrub secrets from oldValues/newValues
        $oldValues = $this->scrubSecrets($oldValues);
        $newValues = $this->scrubSecrets($newValues);

        AuditLog::create([
            'event_id'        => (string) Str::ulid(),
            'tenant_id'       => $tenantId ?? app()->bound('tenant.id') ? app('tenant.id') : null,
            'actor_type'      => $actorType->value,
            'actor_id'        => $actorId,
            'impersonator_id' => $impersonatorId,
            'event'           => strtoupper($event),
            'severity'        => $severity->value,
            'auditable_type'  => $auditableType,
            'auditable_id'    => $auditableId,
            'old_values'      => $oldValues ? json_encode($oldValues) : null,
            'new_values'      => $newValues ? json_encode($newValues) : null,
            'ip_address'      => request()?->ip(),
            'user_agent'      => request()?->userAgent(),
            'request_id'      => $requestId ?? request()?->header('X-Request-Id'),
            'correlation_id'  => $correlationId ?? request()?->header('X-Correlation-Id'),
            'extra'           => $extra ? json_encode($extra) : null,
            'created_at'      => now(),
        ]);
    }

    /**
     * Remove RESTRICTED/secret fields from audit values.
     * NEVER log: password, otp, pin, mfa_secret, api_key, credentials, nid_number, etc.
     */
    private function scrubSecrets(?array $values): ?array
    {
        if (!$values) return null;

        $secretFields = [
            'password', 'password_hash', 'otp', 'otp_hash', 'pin', 'mfa_secret',
            'recovery_codes', 'api_key', 'secret', 'credentials', 'token',
            'nid_number', 'nid_number_encrypted', 'passport_number_encrypted',
            'account_number', 'account_number_encrypted', 'card_number',
        ];

        foreach ($secretFields as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = '[REDACTED]';
            }
        }

        return $values;
    }
}
```

---

## 5. Standard Audit Events

```
Platform events:
  TENANT_CREATED, TENANT_SUSPENDED, TENANT_REACTIVATED
  SUBSCRIPTION_CHANGED, MODULE_ENABLED, MODULE_DISABLED

Identity events:
  USER_REGISTERED, USER_LOGIN, USER_LOGOUT, USER_LOGIN_FAILED
  OTP_SENT, OTP_VERIFIED, OTP_FAILED
  MFA_ENROLLED, MFA_VERIFIED, MFA_FAILED, MFA_DISABLED
  PASSWORD_CHANGED, PASSWORD_RESET
  TENANT_SWITCHED, MEMBERSHIP_CREATED, MEMBERSHIP_SUSPENDED, MEMBERSHIP_REVOKED
  GRANT_CREATED, GRANT_REVOKED

KYC events:
  KYC_SUBMITTED, KYC_OCR_COMPLETED, KYC_PORICHOY_VERIFIED, KYC_FAILED

Financial events:
  INVOICE_CREATED, INVOICE_APPROVED, INVOICE_PAID, INVOICE_VOIDED
  PAYMENT_INITIATED, PAYMENT_COMPLETED, PAYMENT_FAILED, PAYMENT_REFUNDED
  LEDGER_ENTRY_POSTED, WALLET_CREDITED, WALLET_DEBITED
  PAYROLL_APPROVED, PAYROLL_PROCESSED

Security events:
  SA_CROSS_TENANT_ACCESS, RATE_LIMIT_HIT, BRUTE_FORCE_LOCKED
  SUSPICIOUS_ACCESS, WEBHOOK_SIGNATURE_FAILED

Data events:
  PRODUCT_CREATED, PRODUCT_DELETED, STOCK_ADJUSTED
  ORDER_CREATED, ORDER_CANCELLED, ORDER_SHIPPED

Severity guidelines:
  INFO: normal business operations
  WARNING: unusual activity, repeated failures, policy approaches
  CRITICAL: security incidents, financial anomalies, SA cross-tenant access
```

---

## 6. Audit Log Immutability

```
audit_logs is IMMUTABLE. No UPDATE. No DELETE. No soft delete.

Retention: 7 years (minimum, per financial regulatory requirements)

Archival: After 12 months, archive to compressed cold storage.
Archive records remain queryable (indexed archive storage).

Access: 
  Tenant can query their own audit logs (with pagination and filters).
  SA can query any tenant's audit logs.
  Audit log queries are themselves audited for CRITICAL events.

Export: Audit logs exportable as CSV/JSON for compliance requests.
```

---

*Document Owner: Security Architect + Compliance Architect | v1.0.0 | Invariant: I21*
