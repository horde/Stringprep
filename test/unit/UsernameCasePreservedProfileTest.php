<?php

declare(strict_types=1);

namespace Horde\Stringprep\Test;

use Horde\Stringprep\PrecisException;
use Horde\Stringprep\UsernameCasePreservedProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UsernameCasePreservedProfile::class)]
class UsernameCasePreservedProfileTest extends TestCase
{
    private UsernameCasePreservedProfile $profile;

    protected function setUp(): void
    {
        $this->profile = new UsernameCasePreservedProfile();
    }

    public function testCasePreserved(): void
    {
        $this->assertSame('Juliet', $this->profile->apply('Juliet'));
    }

    public function testUppercasePreserved(): void
    {
        $this->assertSame('JULIET', $this->profile->apply('JULIET'));
    }

    public function testNfcNormalization(): void
    {
        // Combining sequence normalizes, but case stays
        $this->assertSame("\u{00C0}", $this->profile->apply("A\u{0300}"));
    }

    public function testWidthMapping(): void
    {
        // Fullwidth A → regular A (no case mapping)
        $this->assertSame('A', $this->profile->apply("\u{FF21}"));
    }

    public function testSpaceRejected(): void
    {
        $this->expectException(PrecisException::class);
        $this->profile->apply('hello world');
    }

    public function testDigitsAndSymbolsFromAscii7(): void
    {
        // All ASCII7 printable chars are Pvalid
        $this->assertSame('User-123_test', $this->profile->apply('User-123_test'));
    }

    public function testControlRejected(): void
    {
        $this->expectException(PrecisException::class);
        $this->profile->apply("a\x00b");
    }

    public function testBidiPureHebrewAllowed(): void
    {
        $result = $this->profile->apply("\u{05D0}\u{05D1}\u{05D2}");
        $this->assertSame("\u{05D0}\u{05D1}\u{05D2}", $result);
    }
}
