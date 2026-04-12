<?php

declare(strict_types=1);

namespace Horde\Stringprep\Test;

use Horde\Stringprep\NicknameProfile;
use Horde\Stringprep\PrecisException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NicknameProfile::class)]
class NicknameProfileTest extends TestCase
{
    private NicknameProfile $profile;

    protected function setUp(): void
    {
        $this->profile = new NicknameProfile();
    }

    public function testSimpleNickname(): void
    {
        $this->assertSame('juliet', $this->profile->apply('Juliet'));
    }

    public function testCaseMapping(): void
    {
        $this->assertSame('juliet capulet', $this->profile->apply('JULIET CAPULET'));
    }

    public function testLeadingSpaceTrimmed(): void
    {
        $this->assertSame('juliet', $this->profile->apply('  Juliet'));
    }

    public function testTrailingSpaceTrimmed(): void
    {
        $this->assertSame('juliet', $this->profile->apply('Juliet  '));
    }

    public function testInteriorSpacesCollapsed(): void
    {
        $this->assertSame('juliet capulet', $this->profile->apply('Juliet   Capulet'));
    }

    public function testNonAsciiSpaceMapped(): void
    {
        // U+00A0 NBSP → space, then trim/collapse
        $this->assertSame('a b', $this->profile->apply("A\u{00A0}B"));
    }

    public function testNfkcNormalization(): void
    {
        // NFKC decomposes compatibility characters + case maps
        // U+2160 ROMAN NUMERAL ONE → 'I' → case map → 'i'
        $this->assertSame('i', $this->profile->apply("\u{2160}"));
    }

    public function testSymbolsAllowed(): void
    {
        $this->assertSame("\u{20AC}", $this->profile->apply("\u{20AC}"));
    }

    public function testControlRejected(): void
    {
        $this->expectException(PrecisException::class);
        $this->profile->apply("nick\x07name");
    }

    public function testNoBidiCheck(): void
    {
        // Nickname has no bidi check
        $result = $this->profile->apply("\u{05D0}abc\u{05D1}");
        $this->assertSame("\u{05D0}abc\u{05D1}", $result);
    }

    public function testUnicodeNickname(): void
    {
        $this->assertSame('münchen', $this->profile->apply('München'));
    }

    public function testAllSpacesBecomesEmpty(): void
    {
        // All spaces → trim → empty → rejected
        $this->expectException(PrecisException::class);
        $this->profile->apply('   ');
    }

    public function testEmptyStringPassthrough(): void
    {
        $this->assertSame('', $this->profile->apply(''));
    }
}
