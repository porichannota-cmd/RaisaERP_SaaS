<?php

declare(strict_types=1);

namespace App\Domain\Media\Contracts;

interface ImageOptimizerInterface
{
    /**
     * Optimizes the image at the given path/data.
     * Extracts metadata (width, height), strips EXIF, and converts to a safe target format (e.g. WebP).
     *
     * @param  string  $sourcePath  The absolute path to the source image file
     * @param  string  $destinationPath  The absolute path where the optimized image should be saved
     * @return array{width: int, height: int, size: int, mime: string, extension: string}
     */
    public function optimize(string $sourcePath, string $destinationPath): array;
}
