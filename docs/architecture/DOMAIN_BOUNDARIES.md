# RAISA ERP — DOMAIN BOUNDARIES
**Version:** 1.0.0 | **Date:** 2026-08-09

---

## Domain Map

```
PLATFORM DOMAIN
  PlatformAdmin
  TenantManagement
  SubscriptionBilling
  ModuleRegistry
  SystemConfiguration
  SuperAdminAudit

IDENTITY DOMAIN
  Registration
  Authentication
  OTP
  MFA
  UserProfile
  KYC (NID/Porichoy)
  RBAC
  Sessions
  DeviceTracking

TENANCY DOMAIN
  TenantContext
  CompanyManagement
  BranchManagement
  BusinessTypeEngine
  CapabilityEngine
  FeatureFlags
  TenantSettings

COMMERCE DOMAIN
  Products/Services/Assets (Universal Item Engine)
  Categories
  Pricing
  Variants
  Discounts
  Promotions
  POS
  Sales
  Purchase
  Invoices
  Orders
  Returns
  Quotations

INVENTORY DOMAIN
  StockMovementLedger
  WarehouseManagement
  BatchTracking
  ExpiryTracking
  SerialIMEITracking
  StockTransfer
  StockAdjustment
  StockReservation
  MultiWarehouse

ACCOUNTING DOMAIN
  ChartOfAccounts
  JournalEntries
  GeneralLedger
  TrialBalance
  ProfitLoss
  BalanceSheet
  CashBankAccounts
  BankReconciliation
  FixedAssets
  Expenses
  Receivables
  Payables
  TaxRates
  ExchangeRates

FINANCE DOMAIN (separate from Accounting)
  LedgerEngine (canonical, double-entry)
  WalletEngine
  PaymentProviderAdapter
  Settlement
  Reconciliation
  Cheques

HR DOMAIN
  EmployeeManagement
  Departments
  Designations
  Attendance
  Leave
  Payroll
  SalaryGrades
  ReportingHierarchy

CRM DOMAIN
  CustomerManagement
  SupplierManagement
  LeadManagement
  ContactManagement
  CommunicationHistory
  CustomerAssets

DISTRIBUTION DOMAIN
  DealerNetwork
  TerritoriesZones
  CommissionGroups
  CommissionEngine (versioned, effective-dated)
  RoyaltyEngine
  NetworkHierarchy (EM->ENT->DD->TD->UD->AGT->RTL->CUST)

ECOMMERCE DOMAIN
  Storefront
  HomepageBuilder
  ProductListings
  Cart
  Checkout
  OrderTracking
  Wishlist
  Reviews
  Brands
  Marketplace

MANUFACTURING DOMAIN
  BOM (Bill of Materials)
  WorkOrders
  ProductionBatches
  RawMaterials
  WastageTracking
  QualityControl
  ProductionCosts

WARRANTY DOMAIN
  WarrantyClaims
  ServiceCenter
  Repairs
  Replacements
  Certificates
  ServiceHistory

COMPLIANCE DOMAIN (Add-on, isolated)
  TINMonitoring
  VATMonitoring
  TradeLicense
  ComplianceWorkflow
  InspectionManagement
  RegulatoryAudit

MEDIA DOMAIN (canonical engine)
  UploadPreflightAPI
  QuarantineStorage
  SecurityValidationPipeline
  ApprovedStorage
  CDNDelivery
  SignedURLService
  ImageProcessingPipeline

NOTIFICATION DOMAIN
  NotificationEngine
  SMSProviderAdapter
  EmailService (SMTP)
  WhatsAppProviderAdapter
  InAppNotifications
  PushNotifications
  OTPService
  TemplateEngine

AI DOMAIN (Add-on / optional)
  AIProviderAdapter
  VoiceAssistant
  OCRService
  AIInsights
  AIWorkflows
  AIMemory
  MediaIntelligence

SECURITY DOMAIN
  AuditEngine
  SecurityEventLog
  FraudMonitor
  FraudScoring
  SessionManagement
  DeviceTracking
  SecurityOperationsCenter
  AnomalyDetection

PLATFORM/OPS DOMAIN
  BackupManagement
  DisasterRecovery
  SystemHealth
  ErrorCenter
  APIManagement
  WebhookManagement
  ProviderCredentials
```

---

## Domain Boundary Rules

1. **No cross-domain direct model access.** Domains communicate via:
   - Domain Services (for synchronous orchestration)
   - Events/Listeners (for async decoupling)
   - Shared Kernel types (for value objects)

2. **Shared Kernel** contains:
   - TenantId value object
   - Money value object
   - UserId value object
   - Common exceptions
   - Common interfaces

3. **Anti-corruption layers** protect the Finance/Ledger domain:
   - All other domains post to ledger via `LedgerService->post(LedgerCommand)`
   - Never direct SQL to ledger_entries from outside Finance domain

4. **Media domain** is always invoked via `MediaService->getSignedUrl()` or
   `MediaService->prepareUpload()`. Direct storage access is forbidden.

5. **Add-on domains** (Compliance, AI, Ecommerce, Manufacturing, Warranty) may
   read from core domains via read-only repository interfaces.
   They may write to core domains ONLY through defined service contracts.

---

*Document Owner: Principal Architect*
