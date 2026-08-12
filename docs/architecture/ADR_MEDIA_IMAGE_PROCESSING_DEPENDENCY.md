# ADR: Media Image Processing Dependency

## Context
The Raisa ERP Wave 1B architecture introduces a Media Gateway to securely handle uploads (e.g. Identity documents, Avatars, Product Images). To defend against malicious payloads and manage storage costs, the gateway must validate dimensions, re-encode standard uploads into a safer web format (WebP), and strip unsafe EXIF metadata.
While PHP provides native GD and Imagick extensions, using them directly requires complex boilerplate to handle orientation, format negotiation, and memory-safe decoding.

## Decision
We accept `intervention/image` (version 3.x) as the image processing dependency for the enterprise Media Gateway.
- It provides a robust, well-maintained abstraction over the native image drivers.
- It is heavily adopted within the Laravel ecosystem.
- License: MIT.
- The underlying driver is configured to be `GD` to minimize production infrastructure requirements, as GD is universally available and capable of basic JPEG/PNG/WebP operations required by Raisa ERP.

## Runtime Requirements
- PHP 8.1+
- PHP `fileinfo` and `exif` extensions.
- PHP `gd` extension (with WebP support compiled).

## Security Boundaries
- **Intervention Image is not a malware scanner.** It is only an image abstraction library.
- The Media Gateway must still enforce MIME validation, size limits, and dimension bounds *before* handing the file to Intervention, to prevent decompression bombs.
- Uploaded raster images (JPEG/PNG) are typically re-encoded to WebP by Intervention, which safely strips original file headers and EXIF data (unless explicitly preserved) before storage.

## Failure Behavior
If the configured driver (GD) is missing or cannot decode a malicious file, the application securely catches the exception and aborts the upload, returning a truthful `MEDIA_PROCESSING_FAILED` error. The database transaction ensures no false `READY` state is committed, and compensation logic removes orphan temporary or storage files.

## Upgrade Governance
Updates to `intervention/image` must be tightly controlled and require running the `RealMediaIntegrationTest` suite to verify that re-encoding behavior, WebP output, and EXIF handling have not drifted.

## Alternatives Considered
- **Native GD:** Rejected due to the high maintenance burden of manually handling EXIF orientation parsing, format detection, and WebP encoding boilerplate.
- **Imagick:** Not mandated as the primary driver due to higher memory consumption and stricter deployment requirements on standard enterprise VPS instances. GD provides a more minimal attack surface for the required raster operations.
