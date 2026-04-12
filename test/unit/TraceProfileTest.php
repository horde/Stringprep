<?php

declare(strict_types=1);

namespace Horde\Stringprep\Test;

use Horde\Stringprep\SaslPrepException;
use Horde\Stringprep\TraceProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TraceProfile::class)]
class TraceProfileTest extends TestCase
{
    private TraceProfile $profile;

    protected function setUp(): void
    {
        $this->profile = new TraceProfile();
    }

    public function testAsciiPassthrough(): void
    {
        $this->assertSame('anonymous', $this->profile->apply('anonymous'));
    }

    public function testNoCaseFolding(): void
    {
        $this->assertSame('Anonymous', $this->profile->apply('Anonymous'));
    }

    public function testNoNfkcNormalization(): void
    {
        // Trace does NOT apply NFKC — compatibility characters stay as-is
        $this->assertSame("\u{2163}", $this->profile->apply("\u{2163}"));
    }

    public function testNoB1Removal(): void
    {
        // Trace does NOT remove B.1 characters — soft hyphen stays
        $this->assertSame("a\u{00AD}b", $this->profile->apply("a\u{00AD}b"));
    }

    public function testZeroWidthSpacePreserved(): void
    {
        // B.1 is off, so U+200B stays
        $this->assertSame("a\u{200B}b", $this->profile->apply("a\u{200B}b"));
    }

    public function testBomProhibited(): void
    {
        // U+FEFF is in C.2.2 (active for Trace) — prohibited even though
        // B.1 removal is off (prohibition runs after mapping)
        $this->expectException(SaslPrepException::class);
        $this->profile->apply("\u{FEFF}test");
    }

    public function testProhibitedAsciiControl(): void
    {
        // C.2.1 is active
        $this->expectException(SaslPrepException::class);
        $this->profile->apply("a\x07b");
    }

    public function testProhibitedPrivateUse(): void
    {
        $this->expectException(SaslPrepException::class);
        $this->profile->apply("a\u{E000}b");
    }

    public function testBidiCheckActive(): void
    {
        // Trace DOES check bidi
        $this->expectException(SaslPrepException::class);
        $this->profile->apply("\u{05D0}abc\u{05D1}");
    }

    public function testBidiPureHebrewAllowed(): void
    {
        $result = $this->profile->apply("\u{05D0}\u{05D1}\u{05D2}");
        $this->assertSame("\u{05D0}\u{05D1}\u{05D2}", $result);
    }

    public function testAsciiSpaceAllowed(): void
    {
        // C.1.1 is NOT active
        $this->assertSame('hello world', $this->profile->apply('hello world'));
    }

    public function testEmptyString(): void
    {
        $this->assertSame('', $this->profile->apply(''));
    }
}
