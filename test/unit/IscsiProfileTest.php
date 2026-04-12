<?php

declare(strict_types=1);

namespace Horde\Stringprep\Test;

use Horde\Stringprep\IscsiProfile;
use Horde\Stringprep\SaslPrepException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IscsiProfile::class)]
class IscsiProfileTest extends TestCase
{
    private IscsiProfile $profile;

    protected function setUp(): void
    {
        $this->profile = new IscsiProfile();
    }

    public function testValidIscsiName(): void
    {
        // Valid iSCSI qualified name components (letters, digits, dash, dot, colon)
        $this->assertSame('iqn.2026-04.com.example', $this->profile->apply('iqn.2026-04.com.example'));
    }

    public function testCaseFolding(): void
    {
        $this->assertSame('iqn.2026-04.com.example', $this->profile->apply('IQN.2026-04.COM.EXAMPLE'));
    }

    public function testProhibitedAsciiSpace(): void
    {
        // C.1.1: ASCII space is prohibited
        $this->expectException(SaslPrepException::class);
        $this->profile->apply('hello world');
    }

    public function testProhibitedSlash(): void
    {
        // Additional iSCSI prohibition: 0x002F
        $this->expectException(SaslPrepException::class);
        $this->profile->apply('a/b');
    }

    public function testProhibitedAtSign(): void
    {
        // 0x0040 is in range 0x003B-0x0040
        $this->expectException(SaslPrepException::class);
        $this->profile->apply('user@host');
    }

    public function testProhibitedExclamationMark(): void
    {
        // 0x0021 is in range 0x0000-0x002C
        $this->expectException(SaslPrepException::class);
        $this->profile->apply('test!');
    }

    public function testProhibitedSquareBracket(): void
    {
        // 0x005B is in range 0x005B-0x0060
        $this->expectException(SaslPrepException::class);
        $this->profile->apply('[test]');
    }

    public function testProhibitedCurlyBrace(): void
    {
        // 0x007B is in range 0x007B-0x007F
        $this->expectException(SaslPrepException::class);
        $this->profile->apply('{test}');
    }

    public function testProhibitedIdeographicFullStop(): void
    {
        // U+3002 IDEOGRAPHIC FULL STOP
        $this->expectException(SaslPrepException::class);
        $this->profile->apply("test\u{3002}");
    }

    public function testAllowedDashDotColon(): void
    {
        // Dash (0x2D), dot (0x2E), colon (0x3A) must be allowed
        // 0x2D-0x2E are between 0x002C and 0x002F (both excluded from the gaps)
        // 0x3A is between 0x0040+1 range
        $this->assertSame('a-b.c', $this->profile->apply('a-b.c'));
    }

    public function testB1Removal(): void
    {
        $this->assertSame('ab', $this->profile->apply("a\u{00AD}b"));
    }

    public function testProhibitedPrivateUse(): void
    {
        $this->expectException(SaslPrepException::class);
        $this->profile->apply("a\u{E000}b");
    }
}
