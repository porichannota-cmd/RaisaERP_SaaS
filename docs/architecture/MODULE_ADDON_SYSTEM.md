# RAISA ERP — MODULE & ADD-ON SYSTEM
**Version:** 1.1.0 | **Date:** 2026-08-09 | **Phase:** 00A

## Change Log
| Version | Date | Change |
|---------|------|--------|
| 1.0.0 | 2026-08-09 | Initial |
| 1.1.0 | 2026-08-09 | Platform-controlled deployment model, tenant activation vs code execution, I27 enforcement |

---

## 1. Architecture Overview

### Core Principle (Invariant I27)

**Module installation is a PLATFORM-CONTROLLED DEPLOYMENT.**
**Tenants ACTIVATE deployed modules. They do NOT upload or execute code.**

```
PLATFORM DEPLOYMENT (CI/CD pipeline — engineering team)
  -> Deploys new module code to all application servers
  -> Runs platform-level module migrations (if any)
  -> Registers module in modules registry table

TENANT ACTIVATION (via ERP UI — tenant admin)
  -> Tenant Admin enables/disables module in their dashboard
  -> This creates/updates tenant_module_entitlements record
  -> Module's enable() method is called (within the already-deployed code)
  -> Module runs tenant-specific setup (e.g., seeding default settings)
  -> NO code is uploaded. NO PHP is eval'd. NO arbitrary execution.
```

This distinction is CRITICAL for security. A tenant cannot introduce malicious code
by "installing" a module. All executable code comes from platform deployments only.

---

## 2. Module Registry

```sql
modules
  id              CHAR(26) PK
  key             VARCHAR(100) UNIQUE NOT NULL  -- e.g., 'pos', 'pharmacy', 'ecommerce'
  name            VARCHAR(200) NOT NULL
  description     TEXT NULL
  version         VARCHAR(20) NOT NULL          -- semver: 1.0.0
  type            ENUM('CORE','ADDON','PREMIUM')
  category        VARCHAR(50)                   -- ESSENTIAL, LOGISTICS, PAYMENT, INDUSTRY, AI, COMPLIANCE
  dependencies    JSON                          -- array of module keys required
  min_plan        VARCHAR(50) NULL              -- minimum subscription plan required
  status          ENUM('STABLE','BETA','DEPRECATED','REMOVED')
  deployed_at     TIMESTAMP NULL                -- when deployed to platform
  changelog       JSON NULL
  created_at, updated_at

tenant_module_entitlements
  id              CHAR(26) PK
  tenant_id       CHAR(26) NOT NULL FK -> tenants.id
  module_key      VARCHAR(100) NOT NULL
  enabled         BOOLEAN DEFAULT FALSE
  installed_at    TIMESTAMP NULL     -- when tenant first activated this module
  enabled_at      TIMESTAMP NULL     -- when tenant last enabled
  disabled_at     TIMESTAMP NULL     -- when tenant last disabled
  version_when_activated VARCHAR(20) NULL
  entitlement_source ENUM('PLAN','MANUAL','TRIAL','PROMO')
  settings_override JSON NULL         -- tenant-specific module settings
  created_at, updated_at
  UNIQUE KEY uq_entitlement (tenant_id, module_key)
  INDEX idx_ent_tenant (tenant_id)
```

---

## 3. Module Contract Interface

```php
namespace App\Platform\Contracts;

interface ModuleContract
{
    /** Unique snake_case identifier. E.g., 'pos', 'pharmacy'. */
    public function key(): string;

    public function name(): string;

    /** Semantic version: 1.0.0 */
    public function version(): string;

    /** Array of module keys this module depends on */
    public function dependencies(): array;

    /**
     * Migration file paths for this module.
     * These run PLATFORM-WIDE on deployment, not per-tenant.
     */
    public function migrations(): array;

    /** Array of [name => description] permission definitions */
    public function permissions(): array;

    /** Register routes — called only if module is enabled for current tenant */
    public function routes(): void;

    /** Menu item definitions for tenant navigation */
    public function menu(): array;

    /** Settings schema definition for this module */
    public function settings(): array;

    /**
     * Called ONCE when module is first activated for a tenant.
     * Seeds default settings, creates initial data if needed.
     * Does NOT deploy code. Does NOT execute arbitrary code.
     */
    public function install(string $tenantId): void;

    /** Called when tenant enables the module */
    public function enable(string $tenantId): void;

    /**
     * Called when tenant disables the module.
     * MUST NOT affect core ERP behavior. (Invariant I20)
     * MUST NOT delete tenant business data.
     */
    public function disable(string $tenantId): void;

    /**
     * Called on platform deployment of a new module version.
     * Handles any data migration or settings upgrade.
     */
    public function upgrade(string $fromVersion, string $tenantId): void;

    /** Returns operational health for this module for the given tenant */
    public function healthStatus(string $tenantId): ModuleHealthStatus;
}
```

---

## 4. Module Security Rules (NEW v1.1.0)

### 4.1 What Tenants CAN Do
- Enable a deployed module (creates entitlement record, calls enable())
- Disable a deployed module (calls disable(), disables routes and menu)
- Configure module settings (within the settings schema defined by the module)
- View module health status

### 4.2 What Tenants CANNOT Do
- Upload PHP files, classes, or packages (FORBIDDEN)
- Install modules not deployed by the platform (FORBIDDEN)
- Modify module source code (FORBIDDEN)
- Execute arbitrary PHP/commands via module configuration (FORBIDDEN)
- Bypass module dependency requirements (FORBIDDEN)

### 4.3 Configuration Safety

Module settings accepted from tenant UI must be:
- Validated against the module's settings schema (typed, validated)
- Stored as JSON in tenant_module_entitlements.settings_override
- Applied read-only by the module code (no eval, no dynamic class loading)

```php
// CORRECT: Type-safe settings
$maxTableCount = (int) $moduleSettings['max_tables']; // integer, validated

// FORBIDDEN: Dynamic class loading from settings
$class = $moduleSettings['handler_class'];
new $class(); // NEVER from tenant-supplied input
```

---

## 5. Module Discovery & Loading

```php
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, function () {
            $registry = new ModuleRegistry();

            // Auto-discover from app/Modules/*/Module.php
            foreach (glob(app_path('Modules/*/Module.php')) as $file) {
                $class = $this->resolveClass($file);
                if (class_exists($class) && is_a($class, ModuleContract::class, true)) {
                    $registry->register(app($class));
                }
            }

            return $registry;
        });
    }

    public function boot(): void
    {
        $registry = app(ModuleRegistry::class);
        $tenantId = app()->bound('tenant.id') ? app('tenant.id') : null;

        if ($tenantId) {
            foreach ($registry->enabledModulesForTenant($tenantId) as $module) {
                $module->routes(); // Only register routes for enabled modules
            }
        }
    }
}
```

---

## 6. Module Lifecycle States

```
NOT_DEPLOYED (platform hasn't deployed it yet)
  -> platform deploys -> DEPLOYED (in modules table, not yet tenant-activated)
DEPLOYED
  -> tenant activates -> install() -> INSTALLED
INSTALLED
  -> tenant enables -> enable() -> ENABLED
ENABLED
  -> tenant disables -> disable() -> DISABLED (data preserved, routes/menu removed)
DISABLED
  -> tenant re-enables -> enable() -> ENABLED
ENABLED / DISABLED
  -> platform upgrades -> upgrade() -> ENABLED at new version
```

---

## 7. Module Categories

### ESSENTIAL (Wave 1A+)
| Key | Name |
|-----|------|
| pos | Smart POS |
| affiliate | Affiliate Marketing |
| refund | Refund Management |
| otp | OTP Service (core platform) |
| tax_vat | Tax & VAT |
| subscription_club | Subscription & Club |
| preorder | Pre-order |
| auction | Auction |
| wholesale_b2b | Wholesale / B2B |

### INDUSTRY (Wave-dependent)
| Key | Name |
|-----|------|
| pharmacy | Pharmacy |
| restaurant | Restaurant / KOT |
| hotel | Hotel & Reservation |
| property | Property Management |
| manufacturing | Manufacturing / Factory |
| warranty | Warranty & Service Center |
| agriculture | Agriculture & Farming |
| school | School / Education |
| hospital | Hospital & Clinic |
| microfinance | Microfinance |

### LOGISTICS (Wave 14)
| Key | Name |
|-----|------|
| courier_pathao | Pathao Courier |
| courier_steadfast | Steadfast Courier |
| courier_redx | RedX Courier |
| courier_ecourier | eCourier |

### PAYMENTS (Wave 8)
| Key | Name |
|-----|------|
| payment_bkash | bKash Payment |
| payment_nagad | Nagad Payment |
| payment_sslcommerz | SSLCommerz Gateway |
| payment_eps | EPS Payment |

### COMPLIANCE (Wave 16)
| Key | Name |
|-----|------|
| trade_license | E-Trade License |
| revenue_tax | Revenue & Tax |

### AI (Wave 17)
| Key | Name |
|-----|------|
| raisa_ai_core | RAISA AI Core |
| raisa_ai_voice | RAISA AI Voice |
| raisa_ai_ocr | RAISA AI OCR |
| raisa_ai_insights | RAISA AI Insights |

---

## 8. Feature Flags

```sql
feature_flags
  id, tenant_id NULL, flag_key, enabled, description,
  conditions JSON NULL, created_at, updated_at
```

Feature flags allow gradual rollout and A/B testing without code changes.
Feature flags are NOT a mechanism for tenants to load custom code.

---

*Document Owner: Principal Architect | v1.1.0 | Invariants: I19, I20, I27*
