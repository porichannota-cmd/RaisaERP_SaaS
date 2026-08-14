<?php

declare(strict_types=1);

namespace App\Domain\Identity\Providers;

use App\Domain\Identity\Contracts\IdentityDocumentExtractionInterface;
use App\Domain\Identity\DTOs\IdentityExtractionResult;
use App\Domain\Identity\Enums\IdentityExtractionProviderStatus;
use App\Models\RegistrationIdentityDocument;

class NullIdentityDocumentExtractionProvider implements IdentityDocumentExtractionInterface
{
    public function extract(RegistrationIdentityDocument $front, ?RegistrationIdentityDocument $back = null): IdentityExtractionResult
    {
        return new IdentityExtractionResult(
            status: IdentityExtractionProviderStatus::NOT_AVAILABLE,
            failureCode: 'NULL_PROVIDER_ACTIVE'
        );
    }
}
