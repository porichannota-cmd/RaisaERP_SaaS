# RAISA ERP — AI GOVERNANCE
**Version:** 1.0.0 | **Date:** 2026-08-09

---

## 1. AI Provider Abstraction

All AI capabilities are implemented behind provider contracts.
No AI provider API key is ever exposed to the browser. (Invariant I09)

AI features are optional add-ons. Core ERP MUST work without AI. (Invariant I20)

---

## 2. AI Feature Categories

| Feature | Description | Risk Level |
|---------|-------------|------------|
| Voice Assistant | Natural language ERP queries | LOW |
| AI Assistant Chat | Context-aware help | LOW |
| AI Memory | Personalized context storage | MEDIUM |
| AI OCR | NID/document extraction | MEDIUM |
| AI Insights | Business analytics | LOW |
| AI Recommendations | Product/action suggestions | LOW |
| AI Workflows | Automated multi-step flows | HIGH |
| Media Intelligence | Image classification | LOW |
| Voice Analytics | Call transcript analysis | MEDIUM |

---

## 3. AI Governance Rules

### 3.1 Authorization (CRITICAL)

AI MUST NEVER directly execute high-risk financial or admin actions without:
1. Deterministic authorization check (not AI-based)
2. Required human confirmation/approval step
3. Audit trail creation

```php
// CORRECT: AI suggests, human confirms
$aiSuggestion = $aiService->suggestRefund($orderId);
// AI generates suggestion only
// Human clicks "Approve Refund" button
// System executes through canonical RefundService with RBAC check

// FORBIDDEN: AI directly posts to ledger
$aiService->executeRefund($orderId); // NEVER
```

### 3.2 Tenant Isolation

- AI models cannot access cross-tenant data
- AI memory is scoped per tenant + per user
- AI context window never includes other tenant data

### 3.3 Data Classification

AI processing respect data classification:
- PUBLIC/INTERNAL data: can be sent to cloud AI providers
- CONFIDENTIAL data: flag before sending to cloud AI; tenant policy applies
- RESTRICTED data (NID, PIN, passwords): NEVER sent to AI providers

### 3.4 Rate Limits & Cost Budgets

```sql
ai_usage_budgets
  tenant_id, provider_key, feature_key,
  monthly_budget_tokens, monthly_used_tokens,
  daily_budget_tokens, daily_used_tokens,
  alert_threshold_pct, hard_limit_enabled,
  reset_date, created_at, updated_at
```

When budget exceeded: AI features disabled, not ERP core features.

### 3.5 Audit Trail

```sql
ai_interactions
  id, tenant_id, user_id, feature_key, provider_key,
  input_summary (NOT raw input with secrets),
  output_summary,
  action_taken, action_approved_by,
  tokens_used, cost_estimate,
  latency_ms, created_at
```

---

## 4. RAISA AI Voice Assistant

```
User speaks -> browser MediaRecorder -> audio blob
  -> POST /api/v1/ai/voice/transcribe
    -> server receives audio blob (not processed by browser)
    -> AI Provider Adapter (ElevenLabs/Whisper): transcription
    -> IntentClassificationService: classify intent
    -> If HIGH_RISK_INTENT: require UI confirmation before action
    -> BusinessCommandService: execute (with RBAC check)
    -> AI Provider Adapter: synthesize response audio
    -> Return: text + audio URL (short-lived signed URL)
```

### Voice Feature Boundary

```
ALLOWED voice commands:
  - "Show me today's sales summary"
  - "What is our stock level for product X?"
  - "Create a new customer contact for [name]"

REQUIRES EXPLICIT CONFIRMATION:
  - "Approve refund for order #..."
  - "Transfer funds to [account]"
  - "Delete product ..."

NEVER EXECUTED BY VOICE ALONE:
  - Financial posting > configurable threshold
  - User role changes
  - System configuration changes
```

---

## 5. AI OCR / NID Extraction

```
User uploads NID photo -> Media Engine (quarantine)
  -> POST /api/v1/ai/ocr/extract-nid {media_intent_id}
    -> Server fetches from quarantine (authorized)
    -> AI OCR Provider Adapter: extract fields
    -> Return: extracted fields (not verified, self-declared)
    -> UI: pre-fills form fields (editable)
    -> Status: NID_OCR_EXTRACTED

SEPARATE STEP:
  -> Porichoy verification (requires authorized API credentials)
    -> Status: NID_PORICHOY_VERIFIED (only if actually verified)
```

---

## 6. AI Memory Architecture

```sql
ai_memory_entries
  id, tenant_id, user_id, memory_key, content (JSON),
  embedding VECTOR NULL (future),
  relevance_score, last_accessed_at,
  expires_at NULL,  -- TTL for transient memories
  created_at, updated_at
```

Memory never stores: passwords, PINs, OTPs, financial secrets.
Memory respects user privacy: user can view and delete their AI memory.

---

*Document Owner: AI Architect | Invariants: I09, I11, I14, I20*
