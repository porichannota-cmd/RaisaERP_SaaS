# RAISA ERP — MEDIA & FILE SECURITY
**Version:** 1.0.0 | **Date:** 2026-08-09

---

## 1. Canonical Media Engine (I16)

One Media Engine. All file ingestion goes through it. No exceptions.

Use cases: product images, NID photos, profile photos, banners, advertisements,
property images, hotel images, documents, invoice files, OCR input, AI input,
chat attachments, certificates.

---

## 2. Upload Architecture

### 2.1 Preferred Flow (Signed Direct Upload)

```
Browser
  -> POST /api/v1/media/preflight
    (file_name, content_type, size, entity_type, entity_id)
  -> Server validates request context
  -> Server generates: signed_upload_url (short-lived, 15 min)
  -> Server creates: media_upload_intent record (status=PENDING)
  -> Browser uploads directly to Object Storage (S3-compatible)
    (via signed URL - NO proxy through Laravel)
  -> Object Storage stores in: /quarantine/{tenant_uuid}/{uuid}/original
  -> Browser notifies: POST /api/v1/media/{intent_id}/complete
  -> Server dispatches: ProcessMediaUploadJob (async)

ProcessMediaUploadJob (worker):
  -> Download from quarantine
  -> VALIDATE: extension whitelist
  -> VALIDATE: MIME type (server-side detection, not browser-supplied)
  -> VALIDATE: magic bytes signature
  -> VALIDATE: max size per type
  -> VALIDATE: for images: decode safely, check dimensions, pixel count
  -> VALIDATE: no embedded executables, polyglots, malicious SVG/PDF
  -> VALIDATE: no decompression bombs, ZIP bombs (for archives)
  -> STRIP: EXIF/GPS metadata from images
  -> NORMALIZE: orientation
  -> RE-ENCODE: to safe format
  -> GENERATE: thumbnail (100x100), small (300px), medium (800px), large (1600px)
  -> CONVERT: WebP variants
  -> STORE: approved storage: /tenants/{tenant_uuid}/{domain}/{entity_uuid}/{uuid}
  -> UPDATE: media record status=APPROVED, store variants
  -> DELETE: quarantine file

On validation failure:
  -> media record status=REJECTED, rejection_reason
  -> quarantine file deleted
  -> user notified of failure
```

### 2.2 Storage Key Rules

Storage keys are ALWAYS server-generated:
```
tenants/{tenant_uuid}/{entity_type}/{entity_uuid}/{ulid}.{ext}
```

**FORBIDDEN**: accepting file paths from client input.
**FORBIDDEN**: storing original user-supplied filenames as storage keys.
Sanitized original names may be stored as `original_filename` metadata.

---

## 3. Delivery Architecture

### 3.1 Public Assets (product images, banners)

- CDN-delivered via CloudFront/BunnyCDN
- Cache headers set appropriately
- URL: `https://cdn.raisaerp.com/tenants/{tenant_uuid}/...`
- Tenant-scoped (CDN path includes tenant_uuid)

### 3.2 Private / Restricted Assets (NID, documents, invoices, HR files)

```
Browser requests file
  -> GET /api/v1/media/{media_id}/url
    (authorized request with valid session)
  -> Server checks authorization policy
  -> Server generates: signed_delivery_url (5-15 minutes TTL)
  -> Browser downloads directly from CDN/Storage via signed URL

NO permanent public URLs for RESTRICTED files. (Invariant I08)
```

### 3.3 Signed URL Implementation

```php
// Short-lived signed URL (AWS S3 / compatible)
$url = Storage::disk('private')->temporaryUrl(
    $media->storage_path,
    now()->addMinutes(15),
    ['ResponseContentDisposition' => 'attachment; filename="' . $media->safe_filename . '"']
);
```

---

## 4. Validation Rules

### 4.1 Allowed File Types by Category

```
IMAGES (product, profile, banner):
  Allowed: jpg, jpeg, png, webp, gif (no animation), avif
  Forbidden: svg, exe, php, html, js, any script

DOCUMENTS (NID, certificates, contracts):
  Allowed: pdf, docx (with scanning)
  Max size: 10MB

SPREADSHEETS (import data):
  Allowed: csv, xlsx
  Max size: 20MB
  Processing: async, with row limits

VIDEO (property tours, demo):
  Allowed: mp4, webm
  Max size: 500MB
  Processing: async transcoding

AUDIO (voice recordings, AI input):
  Allowed: mp3, ogg, wav, m4a
  Max size: 50MB
```

### 4.2 Security Checks

```
1. Extension check (whitelist, not blacklist)
2. MIME type detection (server-side using PHP finfo, not browser-supplied)
3. Magic byte signature verification
4. For images:
   a. Attempt to decode with GD or Imagick
   b. If decode fails -> reject
   c. Check dimensions (max: 20,000 x 20,000)
   d. Check pixel count (max: 100,000,000)
   e. Check animation frames (limit: 100)
5. For PDFs:
   a. Check for embedded JavaScript
   b. Check for embedded executables
   c. Strip active content
6. For archives:
   a. Check extracted size vs compressed size ratio (bomb detection)
   b. Check file count limit
```

---

## 5. Frontend Rules

```
- Responsive images with srcset for all product grids
- lazy loading for below-fold images
- eager/preload ONLY for critical above-fold hero images
- Reserve dimensions (width/height attributes) to prevent layout shift
- Paginate or virtualize galleries > 50 items
- NEVER display original images in grid (use thumbnail/small variants)
- Progress indicators for uploads
- Clear error messages for rejected files
- File type/size hints in upload UI
```

---

## 6. Media Table Schema

```sql
media_uploads
  id              CHAR(26) PK (ULID)
  tenant_id       CHAR(26) NOT NULL
  entity_type     VARCHAR(50) NOT NULL   -- polymorphic
  entity_id       CHAR(26) NULL          -- can be NULL during preflight
  uploader_id     CHAR(26) NOT NULL      -- user_id
  original_filename VARCHAR(255) NOT NULL
  storage_path    VARCHAR(500) NOT NULL  -- server-generated key
  quarantine_path VARCHAR(500) NULL      -- cleared after processing
  mime_type       VARCHAR(100) NULL      -- detected server-side
  file_size       BIGINT UNSIGNED NOT NULL
  width           SMALLINT UNSIGNED NULL
  height          SMALLINT UNSIGNED NULL
  status          ENUM(PENDING, PROCESSING, APPROVED, REJECTED)
  rejection_reason VARCHAR(500) NULL
  variants        JSON NULL              -- {thumb, small, medium, large, webp}
  exif_stripped   BOOLEAN DEFAULT FALSE
  processed_at    TIMESTAMP NULL
  created_at      TIMESTAMP NOT NULL
  updated_at      TIMESTAMP NOT NULL
```

---

*Document Owner: Security Architect | Invariants: I07, I08, I16*
