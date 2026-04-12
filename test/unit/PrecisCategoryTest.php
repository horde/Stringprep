<?php

declare(strict_types=1);

namespace Horde\Stringprep\Test;

use Horde\Stringprep\PrecisCategory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrecisCategory::class)]
class PrecisCategoryTest extends TestCase
{
    // --- ASCII7 (U+0021–U+007E) → Pvalid ---

    public function testAsciiLetterPvalid(): void
    {
        $this->assertSame(PrecisCategory::Pvalid, PrecisCategory::classify(0x0041)); // 'A'
    }

    public function testAsciiDigitPvalid(): void
    {
        $this->assertSame(PrecisCategory::Pvalid, PrecisCategory::classify(0x0030)); // '0'
    }

    public function testAsciiExclamationPvalid(): void
    {
        $this->assertSame(PrecisCategory::Pvalid, PrecisCategory::classify(0x0021)); // '!'
    }

    public function testAsciiTildePvalid(): void
    {
        $this->assertSame(PrecisCategory::Pvalid, PrecisCategory::classify(0x007E)); // '~'
    }

    // --- Controls → Disallowed ---

    public function testNullDisallowed(): void
    {
        $this->assertSame(PrecisCategory::Disallowed, PrecisCategory::classify(0x0000));
    }

    public function testTabDisallowed(): void
    {
        $this->assertSame(PrecisCategory::Disallowed, PrecisCategory::classify(0x0009));
    }

    public function testDeleteDisallowed(): void
    {
        $this->assertSame(PrecisCategory::Disallowed, PrecisCategory::classify(0x007F));
    }

    // --- Spaces → IdDis ---

    public function testAsciiSpaceIdDis(): void
    {
        // U+0020 is below ASCII7 range (0x0021–0x007E)
        // General_Category Zs → IdDis
        $this->assertSame(PrecisCategory::IdDis, PrecisCategory::classify(0x0020));
    }

    public function testNbspIdDis(): void
    {
        $this->assertSame(PrecisCategory::IdDis, PrecisCategory::classify(0x00A0));
    }

    // --- LetterDigits → Pvalid ---

    public function testUnicodeLowercaseLetterPvalid(): void
    {
        $this->assertSame(PrecisCategory::Pvalid, PrecisCategory::classify(0x00E9)); // é
    }

    public function testUnicodeUppercaseLetterPvalid(): void
    {
        $this->assertSame(PrecisCategory::Pvalid, PrecisCategory::classify(0x00C9)); // É
    }

    public function testCjkIdeographPvalid(): void
    {
        $this->assertSame(PrecisCategory::Pvalid, PrecisCategory::classify(0x4E2D)); // 中
    }

    public function testArabicLetterPvalid(): void
    {
        $this->assertSame(PrecisCategory::Pvalid, PrecisCategory::classify(0x0627)); // ا
    }

    // --- Symbols → IdDis ---

    public function testDollarSignIdDis(): void
    {
        // U+0024 is in ASCII7 range → Pvalid (not IdDis)
        $this->assertSame(PrecisCategory::Pvalid, PrecisCategory::classify(0x0024));
    }

    public function testEuroSignIdDis(): void
    {
        $this->assertSame(PrecisCategory::IdDis, PrecisCategory::classify(0x20AC)); // €
    }

    // --- Punctuation → IdDis ---

    public function testEmDashIdDis(): void
    {
        $this->assertSame(PrecisCategory::IdDis, PrecisCategory::classify(0x2014)); // —
    }

    // --- Exceptions (RFC 5892 Section 2.6) ---

    public function testSharpSPvalid(): void
    {
        $this->assertSame(PrecisCategory::Pvalid, PrecisCategory::classify(0x00DF)); // ß
    }

    public function testFinalSigmaPvalid(): void
    {
        $this->assertSame(PrecisCategory::Pvalid, PrecisCategory::classify(0x03C2)); // ς
    }

    public function testIdeographicNumberZeroPvalid(): void
    {
        $this->assertSame(PrecisCategory::Pvalid, PrecisCategory::classify(0x3007)); // 〇
    }

    public function testMiddleDotContextO(): void
    {
        $this->assertSame(PrecisCategory::ContextO, PrecisCategory::classify(0x00B7)); // ·
    }

    public function testGreekKeraiaContextO(): void
    {
        $this->assertSame(PrecisCategory::ContextO, PrecisCategory::classify(0x0375));
    }

    public function testHebrewGereshContextO(): void
    {
        $this->assertSame(PrecisCategory::ContextO, PrecisCategory::classify(0x05F3));
    }

    public function testKatakanaMiddleDotContextO(): void
    {
        $this->assertSame(PrecisCategory::ContextO, PrecisCategory::classify(0x30FB));
    }

    public function testArabicIndicDigitContextO(): void
    {
        $this->assertSame(PrecisCategory::ContextO, PrecisCategory::classify(0x0660));
    }

    public function testArabicTatweelDisallowed(): void
    {
        $this->assertSame(PrecisCategory::Disallowed, PrecisCategory::classify(0x0640));
    }

    // --- JoinControl → ContextJ ---

    public function testZwnjContextJ(): void
    {
        $this->assertSame(PrecisCategory::ContextJ, PrecisCategory::classify(0x200C));
    }

    public function testZwjContextJ(): void
    {
        $this->assertSame(PrecisCategory::ContextJ, PrecisCategory::classify(0x200D));
    }

    // --- HasCompat → IdDis ---

    public function testRomanNumeralIdDis(): void
    {
        // U+2160 ROMAN NUMERAL ONE — NFKC maps to 'I'
        $this->assertSame(PrecisCategory::IdDis, PrecisCategory::classify(0x2160));
    }

    public function testFullwidthAIdDis(): void
    {
        // U+FF21 FULLWIDTH LATIN CAPITAL LETTER A — NFKC maps to 'A'
        $this->assertSame(PrecisCategory::IdDis, PrecisCategory::classify(0xFF21));
    }

    // --- PrecisIgnorable → Disallowed ---

    public function testSoftHyphenDisallowed(): void
    {
        // U+00AD — Default_Ignorable_Code_Point
        $this->assertSame(PrecisCategory::Disallowed, PrecisCategory::classify(0x00AD));
    }

    public function testBomDisallowed(): void
    {
        // U+FEFF — Default_Ignorable_Code_Point
        $this->assertSame(PrecisCategory::Disallowed, PrecisCategory::classify(0xFEFF));
    }

    // --- Unassigned ---

    public function testUnassignedCodepoint(): void
    {
        // U+0378 is unassigned in Unicode
        $this->assertSame(PrecisCategory::Unassigned, PrecisCategory::classify(0x0378));
    }

    // --- Identifier vs Freeform validity ---

    public function testPvalidValidInBoth(): void
    {
        $cat = PrecisCategory::Pvalid;
        $this->assertTrue($cat->isIdentifierValid());
        $this->assertTrue($cat->isFreeformValid());
    }

    public function testIdDisValidInFreeformOnly(): void
    {
        $cat = PrecisCategory::IdDis;
        $this->assertFalse($cat->isIdentifierValid());
        $this->assertTrue($cat->isFreeformValid());
    }

    public function testDisallowedValidInNeither(): void
    {
        $cat = PrecisCategory::Disallowed;
        $this->assertFalse($cat->isIdentifierValid());
        $this->assertFalse($cat->isFreeformValid());
    }

    public function testContextJValidInBoth(): void
    {
        $cat = PrecisCategory::ContextJ;
        $this->assertTrue($cat->isIdentifierValid());
        $this->assertTrue($cat->isFreeformValid());
    }
}
