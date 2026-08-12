<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

use App\Domain\Media\Contracts\ImageOptimizerInterface;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class InterventionImageOptimizer implements ImageOptimizerInterface
{
    private ImageManager $manager;

    public function __construct()
    {
        // For Enterprise scale, Imagick is often preferred if available, but GD is universally available.
        // If Imagick extension is loaded, we can switch, but here we default to GD for safety.
        $this->manager = new ImageManager(new Driver);
    }

    public function optimize(string $sourcePath, string $destinationPath): array
    {
        // Read the image. Intervention Image v3 automatically strips EXIF data upon re-encoding
        // unless specifically instructed to preserve it.
        $image = $this->manager->read($sourcePath);

        $width = $image->width();
        $height = $image->height();

        // Re-encode to WebP format for optimal size and security (stripping dangerous ancillary chunks)
        // Quality 80 is a standard web baseline.
        $encoded = $image->toWebp(80);

        // Save the optimized output to destination
        $encoded->save($destinationPath);

        return [
            'width' => $width,
            'height' => $height,
            'size' => filesize($destinationPath),
            'mime' => 'image/webp',
            'extension' => 'webp',
        ];
    }
}
