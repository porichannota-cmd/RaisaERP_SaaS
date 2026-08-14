<?php

declare(strict_types=1);

namespace App\Domain\Identity\Contracts;

use App\Domain\Identity\DTOs\IdentityExtractionResult;
use App\Models\RegistrationIdentityDocument;

interface IdentityDocumentExtractionInterface
{
    /**
     * Attempt to extract structured identity data from the provided document(s).
     */
    public function extract(RegistrationIdentityDocument $front, ?RegistrationIdentityDocument $back = null): IdentityExtractionResult;
}
