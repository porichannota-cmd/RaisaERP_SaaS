# RAISA ERP — BUSINESS CAPABILITY ENGINE
**Version:** 1.1.0 | **Date:** 2026-08-09 | **Phase:** 00A

## Change Log
| Version | Date | Change |
|---------|------|--------|
| 1.0.0 | 2026-08-09 | Initial |
| 1.1.0 | 2026-08-09 | Backend enforcement clarification, capability vs permission separation, server-side authority |

---

## 1. Purpose

During tenant/company onboarding, the user selects a business type.
This selection drives capabilities, forms, menus, and workflows.

This MUST NOT be implemented as hundreds of hard-coded if/else blocks.

---

## 2. Capability vs Permission (CRITICAL DISTINCTION — v1.1.0)

| | Capability | Permission |
|-|-----------|-----------|
| Definition | A feature available for a business type | What a specific user is authorized to do |
| Scope | Tenant-wide (all users of the tenant see same capabilities) | User-specific (within RBAC roles) |
| Source | BusinessType + TenantOverride + FeatureFlag | Role assignments via RBAC |
| Frontend | `hasCapability('kot_kitchen')` — hides/shows menu items | `hasPermission('commerce.orders.create')` — hides/shows buttons |
| Backend enforcement | Module route guards + CapabilityGate service | Laravel Policy/Gate (MANDATORY) |
| Both required | A capability enables the feature for the tenant | A permission allows the specific user to use it |

**Frontend checks are PRESENTATION ONLY.** (Invariant I26)
**Every capability-gated backend route must ALSO have server-side enforcement.**

---

## 3. Database Schema

```sql
business_types
  id              CHAR(26) PK
  key             VARCHAR(100) UNIQUE   -- e.g., 'restaurant', 'pharmacy', 'hotel'
  name_en         VARCHAR(200)
  name_bn         VARCHAR(200)
  category        VARCHAR(100)          -- RETAIL, FOOD, HEALTHCARE, HOSPITALITY, etc.
  icon            VARCHAR(100)
  description     TEXT NULL
  is_active       BOOLEAN DEFAULT TRUE
  sort_order      SMALLINT DEFAULT 0
  created_at, updated_at

capabilities
  id              CHAR(26) PK
  key             VARCHAR(100) UNIQUE   -- e.g., 'kot_kitchen', 'batch_expiry', 'room_reservation'
  name_en         VARCHAR(200)
  name_bn         VARCHAR(200)
  module_key      VARCHAR(100) NULL     -- requires this module to be enabled
  description     TEXT NULL
  feature_type    VARCHAR(50)           -- MENU, FORM_FIELD, WORKFLOW, REPORT, FEATURE, COLUMN, TAB
  is_active       BOOLEAN DEFAULT TRUE
  created_at, updated_at

business_type_capabilities
  id              CHAR(26) PK
  business_type_key VARCHAR(100)
  capability_key  VARCHAR(100)
  required        BOOLEAN DEFAULT FALSE  -- if true, always enabled for this type
  default_enabled BOOLEAN DEFAULT TRUE
  sort_order      SMALLINT DEFAULT 0
  PRIMARY KEY-equivalent: UNIQUE (business_type_key, capability_key)

tenant_capability_overrides
  id              CHAR(26) PK
  tenant_id       CHAR(26)
  capability_key  VARCHAR(100)
  enabled         BOOLEAN
  override_reason VARCHAR(500) NULL
  overridden_by   CHAR(26) FK -> users.id
  created_at, updated_at
  UNIQUE (tenant_id, capability_key)

feature_flags
  id              CHAR(26) PK
  tenant_id       CHAR(26) NULL          -- NULL = platform-wide
  flag_key        VARCHAR(100)
  enabled         BOOLEAN
  conditions      JSON NULL
  description     TEXT NULL
  created_at, updated_at
```

---

## 4. Capability Resolution Service

```php
class CapabilityResolutionService
{
    public function resolve(string $tenantId): CapabilitySet
    {
        return Cache::remember(
            "capabilities:{$tenantId}",
            now()->addMinutes(5),
            function () use ($tenantId) {
                $tenant = Tenant::with('primaryCompany.businessType')->findOrFail($tenantId);
                $businessTypeKey = $tenant->primaryCompany?->business_type_key ?? 'generic';

                $base = BusinessTypeCapability::where('business_type_key', $businessTypeKey)->get()->keyBy('capability_key');
                $overrides = TenantCapabilityOverride::where('tenant_id', $tenantId)->get()->keyBy('capability_key');
                $enabledModules = TenantModuleEntitlement::where('tenant_id', $tenantId)->where('enabled', true)->pluck('module_key')->toArray();
                $flags = FeatureFlag::where(fn($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))->get()->keyBy('flag_key');

                return new CapabilitySet($base, $overrides, $enabledModules, $flags);
            }
        );
    }

    public function hasCapability(string $tenantId, string $capabilityKey): bool
    {
        return $this->resolve($tenantId)->has($capabilityKey);
    }
}
```

---

## 5. Server-Side Capability Enforcement (NEW v1.1.0)

Capabilities are enforced server-side via route middleware and service gates.

### 5.1 Route Middleware

```php
// routes/api.php
Route::middleware(['auth', 'tenant', 'capability:kot_kitchen'])
    ->group(function () {
        Route::get('/kitchen-orders', [KitchenOrderController::class, 'index']);
    });
```

### 5.2 Capability Gate Middleware

```php
class RequiresCapability
{
    public function handle(Request $request, Closure $next, string $capabilityKey): Response
    {
        $tenantId = app('tenant.id');
        abort_unless(
            app(CapabilityResolutionService::class)->hasCapability($tenantId, $capabilityKey),
            403,
            "Feature '{$capabilityKey}' is not enabled for this tenant."
        );
        return $next($request);
    }
}
```

### 5.3 Service-Level Enforcement

```php
class KitchenOrderService
{
    public function createKOT(array $data): KitchenOrder
    {
        // Service also asserts capability — defense in depth
        abort_unless(
            $this->capabilities->has('kot_kitchen'),
            403,
            'KOT feature not available for this business type.'
        );
        // ... also enforces user permission via Policy
        $this->authorize('commerce.orders.create');
        // ...
    }
}
```

### 5.4 Two-Gate Rule

For every capability-gated feature:
- Gate 1: `capability:{key}` middleware on the route
- Gate 2: Explicit capability check inside the domain service

Both gates must pass. Frontend check is NOT a gate.

---

## 6. Business Type Capability Map

### Restaurant / Café / Fast Food / Cloud Kitchen
`kot_kitchen`, `table_management`, `floor_management`, `menu_management`,
`modifier_groups`, `recipe_costing`, `kitchen_display`, `dine_in_pos`,
`takeaway_pos`, `online_ordering`, `delivery_tracking`

### Pharmacy
`batch_tracking`, `expiry_tracking`, `medicine_database`, `generic_vs_branded`,
`rx_prescription`, `drug_interaction_alert`, `pharmacy_pos`, `controlled_substance_log`

### Hotel / Resort / Guest House
`room_management`, `room_reservation`, `housekeeping`, `room_service_orders`,
`banquet_management`, `front_desk_pos`, `check_in_check_out`, `rate_management`

### Property / Apartment / Building Management
`property_listing`, `unit_management`, `tenancy_contracts`, `rent_collection`,
`maintenance_requests`, `utility_billing`, `property_owner_ledger`

### Manufacturing / Factory / Garments / Textile / Furniture
`bom_management`, `work_orders`, `production_batches`, `raw_material_consumption`,
`wastage_tracking`, `finished_goods`, `production_costing`, `qc_management`

### Electronics / Mobile Shop
`imei_tracking`, `serial_tracking`, `warranty_management`, `service_center`

### Healthcare / Hospital / Clinic / Diagnostic
`patient_management`, `appointment_booking`, `prescription`, `lab_results`,
`bed_management`, `doctor_schedule`, `opd_ipd_management`

### Distribution / Wholesale
`dealer_network`, `territory_management`, `commission_engine`, `route_planning`,
`van_sales`, `credit_limit_management`, `sr_reporting`

### Agriculture / Fish / Dairy / Poultry / Feed
`lot_batch_tracking`, `seasonal_cycles`, `pond_management`, `harvest_tracking`,
`feed_consumption`, `cold_storage_management`, `weight_measurement`

### School / College / University
`student_management`, `class_section`, `attendance_tracking`, `exam_management`,
`result_processing`, `fee_collection`, `library_management`

### Microfinance / NGO / Cooperative
`loan_management`, `emi_schedule`, `savings_accounts`, `group_management`,
`field_officer_management`, `collection_tracking`

---

## 7. Frontend Usage

Capabilities are shared to frontend via Inertia shared data (read-only, for UX only):

```php
// InertiaServiceProvider.php
Inertia::share('capabilities', fn() =>
    app(CapabilityResolutionService::class)
        ->resolve(app('tenant.id'))
        ->toArray()
);
```

```tsx
// React hook — PRESENTATION ONLY
const { hasCapability } = useCapabilities();

// Hides menu item — but backend still enforces
{hasCapability('kot_kitchen') && (
    <SidebarItem icon={<Utensils />} href="/kitchen" label="Kitchen Orders" />
)}

// Hides form field — but backend validates and ignores unknown fields
{hasCapability('batch_tracking') && (
    <FormField name="batch_number" label="Batch #" />
)}
```

---

*Document Owner: ERP Domain Architect | v1.1.0 | Invariant: I26*
