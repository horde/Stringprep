<?php

declare(strict_types=1);

namespace Horde\Stringprep\Test;

use Horde\Stringprep\ResourceprepProfile;
use Horde\Stringprep\SaslPrepException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResourceprepProfile::class)]
class ResourceprepProfileTest extends TestCase
{
    private ResourceprepProfile $profile;

    protected function setUp(): void
    {
        $this->profile = new ResourceprepProfile();
    }

    public function testValidXmppResource(): void
    {
        $this->assertSame('Home', $this->profile->apply('Home'));
    }

    public function testNoCaseFolding(): void
    {
        // Resourceprep does NOT apply case folding
        $this->assertSame('MyResource', $this->profile->apply('MyResource'));
    }

    public function testSpecialCharsAllowed(): void
    {
        // Unlike Nodeprep, Resourceprep allows @, ", &, etc.
        $this->assertSame('user@host/resource', $this->profile->apply('user@host/resource'));
    }

    public function testB1Removal(): void
    {
        $this->assertSame('ab', $this->profile->apply("a\u{00AD}b"));
    }

    public function testNfkcNormalization(): void
    {
        // No case folding, but NFKC still applies
        $this->assertSame('IV', $this->profile->apply("\u{2163}"));
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

    public function testAsciiSpaceAllowed(): void
    {
        // C.1.1 is NOT active for Resourceprep
        $this->assertSame('hello world', $this->profile->apply('hello world'));
    }

    public function testEmptyString(): void
    {
        $this->assertSame('', $this->profile->apply(''));
    }
}
