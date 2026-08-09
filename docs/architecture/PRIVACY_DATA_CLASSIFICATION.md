# RAISA ERP — PRIVACY & DATA CLASSIFICATION
**Version:** 1.0.0 | **Date:** 2026-08-09

---

## 1. Data Classification Levels

### PUBLIC
- Examples: Product catalog, published prices, blog content, company public info
- Access: Anyone
- Encryption: TLS in transit only
- Logging: Standard access logs
- Retention: Business need

### INTERNAL
- Examples: Internal reports, team communication logs, operational metrics
- Access: Authenticated users with appropriate role
- Encryption: TLS in transit
- Logging: Standard application logs
- Retention: Business need, configurable per tenant

### CONFIDENTIAL
- Examples: Business contracts, salary data, HR documents, financial statements, customer purchase history, supplier pricing
- Access: Role-restricted, need-to-know
- Encryption: TLS in transit + at-rest encryption for sensitive columns
- Logging: Access logged with user, timestamp, resource
- Retention: Per legal/regulatory requirements

### RESTRICTED
- Examples: NID numbers, passport numbers, banking credentials, security PINs, OTP values, provider API secrets, private keys, personal biometric data
- Access: Strictly controlled, explicit authorization required
- Encryption: TLS in transit + AES-256-GCM at-rest + field-level encryption
- Logging: All access events logged in security_events
- Retention: Minimum required by law; deletion audited
- Masking: Masked in all non-secure contexts (e.g., last 4 digits of NID)

---

## 2. Data Classification Table

| Data Element | Classification | Storage Rule | Display Rule |
|-------------|---------------|-------------|-------------|
| User mobile number | CONFIDENTIAL | Standard | Partial mask: 017****1234 |
| User email | CONFIDENTIAL | Standard | Partial mask |
| User NID number | RESTRICTED | Field-encrypted | Last 4 digits only |
| Passport number | RESTRICTED | Field-encrypted | Masked |
| Bank account number | RESTRICTED | Field-encrypted | Last 4 digits |
| MFS account number | CONFIDENTIAL | Standard | Partial mask |
| Payment provider credentials | RESTRICTED | Encrypted column | NEVER returned |
| SMTP password | RESTRICTED | Encrypted column | NEVER returned |
| User password hash | RESTRICTED | bcrypt hash | NEVER returned |
| Security PIN hash | RESTRICTED | bcrypt hash | NEVER returned |
| OTP value | RESTRICTED | Temporary hash | NEVER logged |
| Recovery codes | RESTRICTED | bcrypt hash | Shown once at setup |
| JWT/API tokens | RESTRICTED | Hashed reference | Shown once at creation |
| GPS coordinates | CONFIDENTIAL | With consent only | Rounded to 3 decimals |
| IP addresses | INTERNAL | Standard | Used for security; not disclosed |
| User agent strings | INTERNAL | Standard | Truncated in logs |
| Salary information | CONFIDENTIAL | Standard | Role-restricted |
| Medical records | RESTRICTED | Encrypted | Role + patient consent |

---

## 3. Never Log These

```
HARD RULE: NEVER log any of the following:
- Passwords (any form)
- OTP values
- Security PINs
- Recovery codes
- Provider API keys or secrets
- Wallet PINs
- Private cryptographic keys
- Session tokens (full value)
- Payment card numbers
- Full NID/passport/banking credentials
```

If a bug or error occurs involving these values: log the event WITHOUT the value.
Example: `"OTP verification attempted - result: FAILED"` not `"OTP: 123456 - FAILED"`

---

## 4. Location Privacy (I14)

Exact live location of users MUST NOT be silently collected.

### Permitted Collection

| Scenario | Required | How |
|----------|----------|-----|
| Delivery personnel during active delivery | Explicit consent + company policy | Opt-in per shift |
| Field sales staff during work hours | Explicit consent + employment contract | Opt-in per session |
| User sharing their own location | User-initiated | Browser permission dialog |
| Shipment tracking (courier GPS) | Courier's responsibility | Via provider adapter |
| Approximate location for fraud detection | IP geolocation only | Server-side, never exact |

### Forbidden

- Silent background collection of user GPS
- SA/TA viewing real-time location of users without their knowledge
- Storing location beyond declared retention period
- Selling or sharing location data with third parties

---

## 5. Data Retention Policy

```
Authentication logs:       90 days (rolling)
Security events:           1 year (rolling)
Audit logs:                7 years (immutable, archived)
Ledger entries:            10 years (immutable, regulatory)
Order/invoice records:     7 years (regulatory)
User profile data:         Duration of account + post-closure period per law
KYC documents:             Duration of account + 5 years (AML/regulatory)
OTP records:               7 days (purged automatically)
Webhook events:            30 days
Notification logs:         30 days
Session data:              Session lifetime + 7 days
Temporary media:           24 hours if upload abandoned
```

---

## 6. Right to Erasure / Data Deletion

- Tenants can request data deletion subject to legal hold periods
- Ledger entries, audit logs, and financial records are EXEMPT from deletion
  during legal retention periods
- Data deletion is an audited, SA-authorized workflow
- Cascade deletion must not break referential integrity; use anonymization
  where full deletion is legally prohibited

---

## 7. GDPR / PDPA Readiness

The platform is designed for:
- Explicit consent at registration
- Purpose limitation (data used only for stated purposes)
- Data minimization (collect only what's needed)
- Storage limitation (retention policies enforced)
- Integrity and confidentiality (encryption, access control)
- Accountability (audit trail for all policy decisions)

---

*Document Owner: Privacy & Compliance Architect*
