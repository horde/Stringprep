<?php

declare(strict_types=1);

namespace Horde\Stringprep\Test;

use Horde\Stringprep\PolicyMibProfile;
use Horde\Stringprep\SaslPrepException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PolicyMibProfile::class)]
class PolicyMibProfileTest extends TestCase
{
    private PolicyMibProfile $profile;

    protected function setUp(): void
    {
        $this->profile = new PolicyMibProfile();
    }

    public function testAsciiPassthrough(): void
    {
        $this->assertSame('policy-name', $this->profile->apply('policy-name'));
    }

    public function testNoCaseFolding(): void
    {
        $this->assertSame('PolicyName', $this->profile->apply('PolicyName'));
    }

    public function testNfkcNormalization(): void
    {
        $this->assertSame('IV', $this->profile->apply("\u{2163}"));
    }

    public function testB1Removal(): void
    {
        $this->assertSame('ab', $this->profile->apply("a\u{00AD}b"));
    }

    public function testNoBidiCheck(): void
    {
        // PolicyMIB does NOT check bidi — mixed Hebrew + Latin should pass
        $result = $this->profile->apply("\u{05D0}abc\u{05D1}");
        $this->assertSame("\u{05D0}abc\u{05D1}", $result);
    }

    public function testAsciiSpaceAllowed(): void
    {
        // C.1.1 is NOT active
        $this->assertSame('hello world', $this->profile->apply('hello world'));
    }

    public function testNonAsciiSpaceNfkcNormalized(): void
    {
        // C.1.2 is NOT active, so non-ASCII spaces aren't prohibited.
        // But NFKC normalization maps U+00A0 to U+0020, so the output
        // contains an ASCII space.
        $this->assertSame('a b', $this->profile->apply("a\u{00A0}b"));
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

    public function testEmptyString(): void
    {
        $this->assertSame('', $this->profile->apply(''));
    }
}
