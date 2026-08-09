# BEFDS — BioNature Enterprise Form & Design System
## Adapted for RAISA ERP Enterprise OS
**Version:** 1.0.0 | **Date:** 2026-08-09

---

## 1. Overview

BEFDS is the canonical design system for RAISA ERP Enterprise OS.
All UI components MUST be implemented using BEFDS primitives.
No one-off page styling where a reusable component should exist.

The visual language:
- Clean white/off-white surfaces
- Premium enterprise cards
- Compact forms
- Strong information hierarchy
- Restrained shadows
- Rounded cards
- Icon-based sections
- Modern dark-blue enterprise sidebar
- Consistent semantic status colors
- Readable typography
- High-density enterprise information without clutter

---

## 2. Color System

### Primary Palette

```css
:root {
  /* Brand Primary - Enterprise Dark Blue */
  --brand-900: #0d1b2e;
  --brand-800: #122240;
  --brand-700: #163058;
  --brand-600: #1a3d70;
  --brand-500: #1e4d8c;
  --brand-400: #2563ae;
  --brand-300: #3b82c8;
  --brand-200: #93bbdf;
  --brand-100: #dbeafe;
  --brand-50:  #eff6ff;

  /* Sidebar */
  --sidebar-bg:     #122240;
  --sidebar-text:   #94a3b8;
  --sidebar-active: #ffffff;
  --sidebar-accent: #1e4d8c;
  --sidebar-hover:  rgba(255,255,255,0.07);
  --sidebar-border: rgba(255,255,255,0.08);

  /* Semantic Colors */
  --success-700: #15803d;
  --success-500: #22c55e;
  --success-100: #dcfce7;
  --success-50:  #f0fdf4;

  --warning-700: #b45309;
  --warning-500: #f59e0b;
  --warning-100: #fef3c7;
  --warning-50:  #fffbeb;

  --danger-700:  #b91c1c;
  --danger-500:  #ef4444;
  --danger-100:  #fee2e2;
  --danger-50:   #fef2f2;

  --info-700:    #0369a1;
  --info-500:    #0ea5e9;
  --info-100:    #e0f2fe;
  --info-50:     #f0f9ff;

  /* Neutral */
  --neutral-950: #0a0a0a;
  --neutral-900: #171717;
  --neutral-800: #262626;
  --neutral-700: #404040;
  --neutral-600: #525252;
  --neutral-500: #737373;
  --neutral-400: #a3a3a3;
  --neutral-300: #d4d4d4;
  --neutral-200: #e5e5e5;
  --neutral-100: #f5f5f5;
  --neutral-50:  #fafafa;

  /* Surface */
  --surface-page:   #f8fafc;
  --surface-card:   #ffffff;
  --surface-raised: #ffffff;
  --surface-overlay:#ffffff;
  --surface-sunken: #f1f5f9;
}
```

### Dark Mode

```css
[data-theme="dark"] {
  --surface-page:   #0f172a;
  --surface-card:   #1e293b;
  --surface-raised: #253348;
  --surface-overlay:#1e293b;
  --surface-sunken: #0f172a;

  --sidebar-bg:     #0d1b2e;
}
```

---

## 3. Typography

### Font Stack

```css
/* Primary: English interface text */
--font-sans: 'Inter', 'Segoe UI', system-ui, sans-serif;

/* Bangla text */
--font-bangla: 'Hind Siliguri', 'SolaimanLipi', 'Kalpurush', sans-serif;

/* Monospace: code, IDs, amounts */
--font-mono: 'JetBrains Mono', 'Fira Code', 'Consolas', monospace;
```

### Type Scale

```css
--text-xs:   0.75rem;   /* 12px */
--text-sm:   0.875rem;  /* 14px */
--text-base: 1rem;      /* 16px */
--text-lg:   1.125rem;  /* 18px */
--text-xl:   1.25rem;   /* 20px */
--text-2xl:  1.5rem;    /* 24px */
--text-3xl:  1.875rem;  /* 30px */
--text-4xl:  2.25rem;   /* 36px */
```

### Weight Scale

```css
--font-normal:    400;
--font-medium:    500;
--font-semibold:  600;
--font-bold:      700;
```

### Line Height

```css
--leading-tight:  1.25;
--leading-snug:   1.375;
--leading-normal: 1.5;
--leading-relaxed:1.625;
```

---

## 4. Spacing System (4px base)

```css
--space-0:   0;
--space-0_5: 0.125rem;  /* 2px */
--space-1:   0.25rem;   /* 4px */
--space-1_5: 0.375rem;  /* 6px */
--space-2:   0.5rem;    /* 8px */
--space-2_5: 0.625rem;  /* 10px */
--space-3:   0.75rem;   /* 12px */
--space-4:   1rem;      /* 16px */
--space-5:   1.25rem;   /* 20px */
--space-6:   1.5rem;    /* 24px */
--space-8:   2rem;      /* 32px */
--space-10:  2.5rem;    /* 40px */
--space-12:  3rem;      /* 48px */
--space-16:  4rem;      /* 64px */
--space-20:  5rem;      /* 80px */
```

---

## 5. Border Radius

```css
--radius-none: 0;
--radius-sm:   0.25rem;   /* 4px */
--radius-md:   0.375rem;  /* 6px */
--radius-lg:   0.5rem;    /* 8px */
--radius-xl:   0.75rem;   /* 12px */
--radius-2xl:  1rem;      /* 16px */
--radius-full: 9999px;    /* pill */
```

---

## 6. Shadows

```css
--shadow-xs:  0 1px 2px 0 rgb(0 0 0 / 0.04);
--shadow-sm:  0 1px 3px 0 rgb(0 0 0 / 0.07), 0 1px 2px -1px rgb(0 0 0 / 0.06);
--shadow-md:  0 4px 6px -1px rgb(0 0 0 / 0.07), 0 2px 4px -2px rgb(0 0 0 / 0.05);
--shadow-lg:  0 10px 15px -3px rgb(0 0 0 / 0.07), 0 4px 6px -4px rgb(0 0 0 / 0.05);
--shadow-xl:  0 20px 25px -5px rgb(0 0 0 / 0.08), 0 8px 10px -6px rgb(0 0 0 / 0.05);
```

---

## 7. Form Element Heights

```css
--input-height-sm: 2rem;     /* 32px - compact tables */
--input-height-md: 2.25rem;  /* 36px - standard forms */
--input-height-lg: 2.75rem;  /* 44px - prominent actions */
```

---

## 8. Breakpoints

```css
--bp-sm:  640px;   /* Mobile landscape */
--bp-md:  768px;   /* Tablet */
--bp-lg:  1024px;  /* Desktop */
--bp-xl:  1280px;  /* Wide desktop */
--bp-2xl: 1536px;  /* Ultra-wide */
```

---

## 9. Sidebar Specification

```
Width: 64px (collapsed icon-only) / 240px (expanded)
Background: var(--sidebar-bg) = #122240
Top: Tenant logo + name
Nav groups: icon + label + submenu support
Bottom: User avatar, settings, logout
Active state: white text + brand-500 background
Hover state: rgba(255,255,255,0.07) background
Transition: smooth 200ms ease
Mobile: drawer overlay, toggle hamburger
```

---

## 10. Status Colors

```
ACTIVE / SUCCESS / COMPLETED / PAID / VERIFIED:  success-* colors
PENDING / PROCESSING / REVIEW:                    warning-* colors
FAILED / REJECTED / OVERDUE / DANGER:             danger-* colors
INFO / NEW / PARTIAL:                             info-* colors
INACTIVE / CANCELLED / DISABLED / DRAFT:          neutral-* colors
```

---

## 11. Icon Rules

- Library: Lucide React (already installed)
- Size: 16px (sm), 20px (md/default), 24px (lg)
- Sidebar icons: 20px
- Form prefix icons: 16px
- Action buttons: 16px
- Alert/status icons: 20px
- Stroke width: 1.5px (default Lucide)
- Never use images where icons suffice

---

## 12. Bangla/English Bilingual Rules

- Font switches automatically based on content type
- `lang="bn"` attribute on Bangla-specific containers
- Number formatting: Bengali numerals optional (tenant setting)
- Date formatting: Gregorian (default), Bangla calendar optional
- UI language toggle: persisted per user
- All translatable strings via i18n keys (never hard-coded)

---

*Document Owner: UI/UX Architect*
