<?php

namespace Tests\Unit\Communication;

use App\Domain\Communication\Enums\DestinationType;
use App\Domain\Communication\Exceptions\InvalidDestinationException;
use App\Domain\Communication\Services\DestinationNormalizer;
use PHPUnit\Framework\TestCase;

class DestinationNormalizerTest extends TestCase
{
    private DestinationNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new DestinationNormalizer;
    }

    public function test_normalizes_01_prefix_mobile(): void
    {
        $this->assertSame('+8801712345678', $this->normalizer->normalizeMobile('01712345678'));
    }

    public function test_normalizes_88_prefix_mobile(): void
    {
        $this->assertSame('+8801812345678', $this->normalizer->normalizeMobile('8801812345678'));
    }

    public function test_normalizes_plus88_prefix_mobile(): void
    {
        $this->assertSame('+8801912345678', $this->normalizer->normalizeMobile('+8801912345678'));
    }

    public function test_invalid_mobile_throws(): void
    {
        $this->expectException(InvalidDestinationException::class);
        $this->normalizer->normalizeMobile('12345');
    }

    public function test_invalid_operator_prefix_throws(): void
    {
        $this->expectException(InvalidDestinationException::class);
        $this->normalizer->normalizeMobile('01212345678'); // 012 is not a valid BD operator
    }

    public function test_normalizes_email_to_lowercase(): void
    {
        $this->assertSame('test@example.com', $this->normalizer->normalizeEmail('TEST@EXAMPLE.COM'));
    }

    public function test_invalid_email_throws(): void
    {
        $this->expectException(InvalidDestinationException::class);
        $this->normalizer->normalizeEmail('not-an-email');
    }

    public function test_normalize_dispatches_by_type(): void
    {
        $this->assertSame('+8801712345678', $this->normalizer->normalize('01712345678', DestinationType::MOBILE));
        $this->assertSame('user@example.com', $this->normalizer->normalize('USER@EXAMPLE.COM', DestinationType::EMAIL));
    }

    public function test_mask_mobile_hides_middle_digits(): void
    {
        $masked = $this->normalizer->maskMobile('01712345678');
        $this->assertStringStartsWith('017', $masked);
        $this->assertStringEndsWith('78', $masked);
        $this->assertStringContainsString('*', $masked);
    }

    public function test_mask_email_hides_local_part(): void
    {
        $masked = $this->normalizer->maskEmail('rafique@example.com');
        $this->assertStringStartsWith('r', $masked);
        $this->assertStringContainsString('@example.com', $masked);
        $this->assertStringContainsString('*', $masked);
    }

    public function test_duplicate_formats_map_to_same_canonical(): void
    {
        $a = $this->normalizer->normalizeMobile('01712345678');
        $b = $this->normalizer->normalizeMobile('8801712345678');
        $c = $this->normalizer->normalizeMobile('+8801712345678');
        $this->assertSame($a, $b);
        $this->assertSame($b, $c);
    }
}
