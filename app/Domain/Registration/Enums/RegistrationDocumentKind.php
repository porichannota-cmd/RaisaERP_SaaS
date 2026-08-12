<?php

declare(strict_types=1);

namespace App\Domain\Registration\Enums;

/**
 * Kinds of identity documents allowed in the pre-user staging boundary.
 *
 * PA-07: Only identity-class documents are permitted in registration staging.
 * No arbitrary general media (product, banner, invoice, contract, ZIP, SVG).
 */
enum RegistrationDocumentKind: string
{
    /** User profile photograph. */
    case PROFILE_PHOTO = 'profile_photo';

    /** National ID card — front face. */
    case NID_FRONT = 'nid_front';

    /** National ID card — rear face. */
    case NID_BACK = 'nid_back';

    /** Passport biographical data page. */
    case PASSPORT = 'passport';

    /**
     * MIME types allowed per document kind.
     *
     * @return string[]
     */
    public function allowedMimeTypes(): array
    {
        return match ($this) {
            self::PROFILE_PHOTO => ['image/jpeg', 'image/png', 'image/webp'],
            self::NID_FRONT,
            self::NID_BACK,
            self::PASSPORT => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        };
    }

    /**
     * Maximum file size in bytes for this document kind.
     */
    public function maxSizeBytes(): int
    {
        return match ($this) {
            self::PROFILE_PHOTO => 5 * 1024 * 1024,   // 5 MB
            self::NID_FRONT,
            self::NID_BACK,
            self::PASSPORT => 10 * 1024 * 1024,   // 10 MB
        };
    }

    /**
     * Whether this kind is an identity verification document.
     */
    public function isIdentityDocument(): bool
    {
        return in_array($this, [
            self::NID_FRONT,
            self::NID_BACK,
            self::PASSPORT,
        ]);
    }
}
