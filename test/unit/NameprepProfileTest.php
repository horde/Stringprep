<?php

declare(strict_types=1);

namespace Horde\Stringprep\Test;

use Horde\Stringprep\NameprepProfile;
use Horde\Stringprep\SaslPrepException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NameprepProfile::class)]
class NameprepProfileTest extends TestCase
{
    private NameprepProfile $profile;

    protected function setUp(): void
    {
        $this->profile = new NameprepProfile();
    }

    public function testAsciiDomainPassthrough(): void
    {
        $this->assertSame('example.com', $this->profile->apply('example.com'));
    }

    public function testEmptyString(): void
    {
        $this->assertSame('', $this->profile->apply(''));
    }

    public function testCaseFolding(): void
    {
        // Nameprep applies B.2 case folding
        $this->assertSame('example.com', $this->profile->apply('EXAMPLE.COM'));
    }

    public function testCaseFoldingGerman(): void
    {
        // ß stays ß; Ü → ü
        $this->assertSame('münchen', $this->profile->apply('München'));
    }

    public function testNfkcNormalization(): void
    {
        // U+2163 ROMAN NUMERAL FOUR → "iv" (NFKC + case fold)
        $this->assertSame('iv', $this->profile->apply("\u{2163}"));
    }

    public function testB1Removal(): void
    {
        // Soft hyphen removed
        $this->assertSame('example', $this->profile->apply("exam\u{00AD}ple"));
    }

    public function testProhibitedPrivateUse(): void
    {
        $this->expectException(SaslPrepException::class);
        $this->profile->apply("a\u{E000}b");
    }

    public function testProhibitedNonAsciiControl(): void
    {
        // C.2.2 is active
        $this->expectException(SaslPrepException::class);
        $this->profile->apply("a\u{0085}b");
    }

    public function testAsciiControlAllowed(): void
    {
        // C.2.1 is NOT active for Nameprep — ASCII control chars pass
        // (Tab = 0x09 is in C.2.1 range)
        $this->assertSame("a\tb", $this->profile->apply("a\tb"));
    }

    public function testBidiPureHebrew(): void
    {
        $result = $this->profile->apply("\u{05D0}\u{05D1}\u{05D2}");
        $this->assertSame("\u{05D0}\u{05D1}\u{05D2}", $result);
    }

    public function testBidiMixedRejected(): void
    {
        $this->expectException(SaslPrepException::class);
        $this->profile->apply("\u{05D0}abc\u{05D1}");
    }

    public function testInternationalizedDomain(): void
    {
        $this->assertSame('münchen', $this->profile->apply('münchen'));
    }
}
