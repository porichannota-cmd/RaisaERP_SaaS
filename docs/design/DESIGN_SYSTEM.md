# RAISA ERP — DESIGN SYSTEM COMPONENT SPECIFICATIONS
**Version:** 1.0.0 | **Date:** 2026-08-09

---

## Component Library Architecture

Built on top of:
- Radix UI (already installed: Avatar, Checkbox, Dialog, DropdownMenu, Select, etc.)
- Tailwind CSS 4.x
- Lucide React icons
- class-variance-authority (CVA) for variant management
- clsx + tailwind-merge for class composition

All components follow BEFDS tokens. No ad-hoc hardcoded colors in components.

---

## 1. Button Component

```tsx
// Variants: primary | secondary | ghost | danger | warning | success | link
// Sizes: xs | sm | md | lg
// States: default | loading | disabled

<Button variant="primary" size="md" loading={false}>
  <Save size={16} /> Save Changes
</Button>
```

---

## 2. Card Component

```tsx
// Base card - white surface, subtle shadow, rounded-xl
// Variants: default | raised | bordered | sunken

<Card>
  <CardHeader>
    <CardTitle>Card Title</CardTitle>
    <CardDescription>Optional subtitle</CardDescription>
  </CardHeader>
  <CardContent>...</CardContent>
  <CardFooter>...</CardFooter>
</Card>
```

---

## 3. Form Components

### Input
```tsx
<FormField
  name="mobile"
  label="Mobile Number"
  required
  prefix={<Phone size={16} />}
  hint="Enter 11-digit mobile number"
  error={errors.mobile}
/>
```

### Select
```tsx
<FormSelect
  name="division"
  label="Division"
  options={divisions}
  placeholder="Select division..."
  searchable
/>
```

### FileUpload
```tsx
<FileUpload
  name="nid_front"
  label="NID Front"
  accept={['image/jpeg', 'image/png', 'image/webp']}
  maxSize={5 * 1024 * 1024}
  onUpload={handleUpload}
  preview
  camera // shows camera capture option on mobile
/>
```

### OTPInput
```tsx
<OTPInput
  length={6}
  onComplete={handleOTPComplete}
  resendCountdown={60}
  onResend={handleResend}
/>
```

---

## 4. Table Component

```tsx
// Enterprise data table with:
// - Column sorting
// - Pagination (server-side)
// - Row selection
// - Column visibility toggle
// - Export button
// - Search/filter
// - Responsive (horizontal scroll on mobile)
// - Loading skeleton state
// - Empty state
// - Bulk actions

<DataTable
  columns={columns}
  data={data}
  pagination={pagination}
  onSort={handleSort}
  onFilter={handleFilter}
  selectable
  bulkActions={[...]}
/>
```

---

## 5. Modal/Dialog

```tsx
<Dialog open={open} onOpenChange={setOpen}>
  <DialogContent size="md"> {/* sm | md | lg | xl | full */}
    <DialogHeader>
      <DialogTitle>Confirm Action</DialogTitle>
    </DialogHeader>
    <DialogBody>...</DialogBody>
    <DialogFooter>
      <Button variant="ghost" onClick={() => setOpen(false)}>Cancel</Button>
      <Button variant="primary">Confirm</Button>
    </DialogFooter>
  </DialogContent>
</Dialog>
```

---

## 6. Drawer

```tsx
// Slide-in panels for create/edit forms (better than modal for complex forms)
<Drawer open={open} onOpenChange={setOpen} side="right"> {/* left | right */}
  <DrawerContent width="lg"> {/* sm=400px | md=560px | lg=720px | xl=960px */}
    <DrawerHeader title="Add Product" onClose={() => setOpen(false)} />
    <DrawerBody>...</DrawerBody>
    <DrawerFooter>...</DrawerFooter>
  </DrawerContent>
</Drawer>
```

---

## 7. Badge / Status Chip

```tsx
<Badge variant="success">Active</Badge>
<Badge variant="warning">Pending</Badge>
<Badge variant="danger">Overdue</Badge>
<Badge variant="info">Processing</Badge>
<Badge variant="neutral">Draft</Badge>
```

---

## 8. Alert / Toast

```tsx
// Inline alert
<Alert variant="warning" icon={<AlertTriangle />}>
  Your subscription expires in 3 days.
</Alert>

// Toast (top-right notification, auto-dismiss)
toast.success('Order created successfully');
toast.error('Payment failed. Please retry.');
```

---

## 9. Avatar / Profile

```tsx
<Avatar size="md" src={user.avatar} fallback={user.initials} status="online" />
```

---

## 10. KYC / NID Card Component

```tsx
// Specialized component for displaying NID/identity verification status
<KYCCard
  status="NID_PORICHOY_VERIFIED"
  nidMasked="****-****-1234"
  verifiedAt="2026-08-09"
  name={user.name_en}
  photo={user.nid_photo_url} // signed URL
/>
```

---

## 11. Stat Card

```tsx
// Dashboard metric cards
<StatCard
  label="Today's Sales"
  value="৳ 1,24,500"
  change={{ value: 12.5, direction: 'up' }}
  icon={<ShoppingCart />}
  color="primary"
/>
```

---

## 12. Loading States

```tsx
// Skeleton loader (shimmer effect)
<Skeleton className="h-4 w-[200px]" />
<SkeletonCard />
<SkeletonTable rows={5} columns={6} />

// Spinner
<Spinner size="sm" />

// Full page loading
<PageLoader />
```

---

## 13. Empty State

```tsx
<EmptyState
  icon={<Package />}
  title="No products yet"
  description="Add your first product to get started."
  action={<Button variant="primary">Add Product</Button>}
/>
```

---

## 14. Navigation / Sidebar

```tsx
<Sidebar>
  <SidebarLogo />
  <SidebarNav>
    <SidebarGroup label="Sales">
      <SidebarItem icon={<ShoppingCart />} href="/sales" label="Sales" />
      <SidebarItem icon={<FileText />} href="/invoices" label="Invoices" />
    </SidebarGroup>
  </SidebarNav>
  <SidebarFooter>
    <SidebarUserMenu />
  </SidebarFooter>
</Sidebar>
```

---

## 15. Chart Components

```tsx
// Wrapper components over Recharts (to be added in Wave 19)
<LineChart data={data} xKey="date" yKey="amount" />
<BarChart data={data} xKey="month" yKey="sales" />
<DonutChart data={data} />
<AreaChart data={data} />
```

---

## 16. Responsive Breakpoints

```
Mobile (<640px):
  - Single column layout
  - Sidebar as bottom sheet or hamburger drawer
  - Tables: horizontal scroll or card view
  - Forms: full width

Tablet (640-1024px):
  - Two column layout where appropriate
  - Sidebar: collapsed (icon-only, 64px)
  - Cards: 2-column grid

Desktop (>1024px):
  - Full sidebar (240px expanded)
  - Multi-column layouts
  - Side-by-side form/preview panels
```

---

*Document Owner: UI/UX Architect | BEFDS v1.0.0*
