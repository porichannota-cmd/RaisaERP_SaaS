# RAISA ERP — COMPONENT CONTRACTS
**Version:** 1.0.0 | **Date:** 2026-08-09

---

## Purpose

This document defines the TypeScript contract (props interface) for all canonical BEFDS components.
Components MUST adhere to these contracts. Changes require version bump + documentation update.

---

## Core Component Contracts

### Button
```tsx
interface ButtonProps {
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger' | 'warning' | 'success' | 'link';
  size?: 'xs' | 'sm' | 'md' | 'lg';
  loading?: boolean;
  disabled?: boolean;
  leftIcon?: React.ReactNode;
  rightIcon?: React.ReactNode;
  fullWidth?: boolean;
  onClick?: (e: React.MouseEvent) => void;
  type?: 'button' | 'submit' | 'reset';
  children: React.ReactNode;
  className?: string;
}
```

### Input
```tsx
interface InputProps {
  name: string;
  label?: string;
  placeholder?: string;
  type?: 'text' | 'email' | 'password' | 'tel' | 'number' | 'search';
  prefix?: React.ReactNode;
  suffix?: React.ReactNode;
  hint?: string;
  error?: string;
  disabled?: boolean;
  required?: boolean;
  value?: string;
  onChange?: (value: string) => void;
  className?: string;
}
```

### FileUpload
```tsx
interface FileUploadProps {
  name: string;
  label?: string;
  accept: string[];          // MIME types: ['image/jpeg', 'image/png']
  maxSize: number;           // bytes
  multiple?: boolean;
  camera?: boolean;          // Enable camera capture on mobile
  preview?: boolean;         // Show image preview after upload
  hint?: string;
  error?: string;
  disabled?: boolean;
  onUpload: (result: MediaUploadResult) => void;
  onError: (error: UploadError) => void;
}

interface MediaUploadResult {
  intentId: string;
  status: 'PENDING' | 'APPROVED' | 'REJECTED';
  previewUrl?: string;
}
```

### DataTable
```tsx
interface DataTableProps<T> {
  columns: ColumnDef<T>[];
  data: T[];
  pagination?: PaginationState;
  onPaginationChange?: (pagination: PaginationState) => void;
  sorting?: SortingState;
  onSortingChange?: (sorting: SortingState) => void;
  filters?: FilterState;
  onFilterChange?: (filters: FilterState) => void;
  selectable?: boolean;
  onSelectionChange?: (selectedIds: string[]) => void;
  bulkActions?: BulkAction[];
  loading?: boolean;
  emptyState?: React.ReactNode;
  className?: string;
}
```

### Badge
```tsx
interface BadgeProps {
  variant: 'success' | 'warning' | 'danger' | 'info' | 'neutral' | 'primary';
  size?: 'sm' | 'md';
  dot?: boolean;             // Show colored dot only, no text
  icon?: React.ReactNode;
  children: React.ReactNode;
  className?: string;
}
```

### OTPInput
```tsx
interface OTPInputProps {
  length?: number;           // default 6
  onComplete: (otp: string) => void;
  onChange?: (otp: string) => void;
  resendCountdown?: number;  // seconds
  onResend?: () => void;
  disabled?: boolean;
  error?: string;
  autoFocus?: boolean;
}
```

### KYCCard
```tsx
interface KYCCardProps {
  status: KYCStatus;
  nidMasked?: string;
  name?: string;
  verifiedAt?: string;
  photoUrl?: string;         // signed URL - never permanent public URL
  onVerify?: () => void;
  onUpload?: () => void;
}

type KYCStatus =
  | 'NID_UNVERIFIED'
  | 'NID_PENDING'
  | 'NID_OCR_EXTRACTED'
  | 'NID_PORICHOY_VERIFIED'
  | 'NID_FAILED'
  | 'NID_MANUAL_REVIEW';
```

### StatCard
```tsx
interface StatCardProps {
  label: string;
  value: string | number;
  change?: {
    value: number;
    direction: 'up' | 'down' | 'neutral';
    period?: string;        // e.g., "vs last month"
  };
  icon?: React.ReactNode;
  color?: 'primary' | 'success' | 'warning' | 'danger' | 'info';
  loading?: boolean;
  href?: string;            // Optional link to detail view
}
```

---

## Navigation Component Contracts

### SidebarItem
```tsx
interface SidebarItemProps {
  label: string;
  labelBn?: string;          // Bangla label if different
  icon: React.ReactNode;
  href?: string;
  onClick?: () => void;
  badge?: string | number;
  active?: boolean;
  disabled?: boolean;
  requiredCapability?: string;   // Automatically hidden if capability disabled
  requiredPermission?: string;   // Automatically hidden if no permission
}
```

### Breadcrumbs
```tsx
interface BreadcrumbsProps {
  items: Array<{
    label: string;
    href?: string;
  }>;
}
```

---

## Layout Contracts

### AppShell
```tsx
interface AppShellProps {
  sidebar: React.ReactNode;
  header?: React.ReactNode;
  children: React.ReactNode;
}
```

### PageHeader
```tsx
interface PageHeaderProps {
  title: string;
  titleBn?: string;
  description?: string;
  actions?: React.ReactNode;
  breadcrumbs?: BreadcrumbItem[];
}
```

---

## Rules for Component Implementation

1. All components use BEFDS tokens (CSS variables), not hardcoded colors.
2. All interactive elements have keyboard focus states.
3. All form fields have associated labels (accessibility).
4. Loading states implemented for all data-dependent components.
5. Empty states implemented for all list components.
6. Error states implemented for all form components.
7. Mobile-responsive behavior specified for all layout components.
8. i18n-ready: no hardcoded user-facing text in components.

---

*Document Owner: UI/UX Architect*
