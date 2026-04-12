<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Stringprep\Test;

use Horde\Stringprep\SaslPrepException;
use Horde\Stringprep\SaslPrepProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SaslPrepProfile::class)]
class SaslPrepTest extends TestCase
{
    private SaslPrepProfile $saslprep;

    protected function setUp(): void
    {
        $this->saslprep = new SaslPrepProfile();
    }

    // --- Basic passthrough ---

    public function testAsciiPassthrough(): void
    {
        $this->assertSame('hello', $this->saslprep->apply('hello'));
    }

    public function testEmptyString(): void
    {
        $this->assertSame('', $this->saslprep->apply(''));
    }

    public function testAsciiPasswordCharacters(): void
    {
        $this->assertSame('P@ssw0rd!#$', $this->saslprep->apply('P@ssw0rd!#$'));
    }

    // --- B.1 mapping: map to nothing ---

    public function testSoftHyphenRemoved(): void
    {
        // U+00AD SOFT HYPHEN should be stripped
        $this->assertSame('password', $this->saslprep->apply("pass\u{00AD}word"));
    }

    public function testZeroWidthSpaceRemovedByB1(): void
    {
        // U+200B ZERO WIDTH SPACE is in B.1 (mapped to nothing)
        // Note: it's also in C.1.2, but B.1 mapping runs first and removes it
        $this->assertSame('ab', $this->saslprep->apply("a\u{200B}b"));
    }

    public function testZeroWidthNonJoinerRemoved(): void
    {
        // U+200C ZERO WIDTH NON-JOINER
        $this->assertSame('test', $this->saslprep->apply("te\u{200C}st"));
    }

    public function testBomRemoved(): void
    {
        // U+FEFF BOM / ZERO WIDTH NO-BREAK SPACE
        $this->assertSame('test', $this->saslprep->apply("\u{FEFF}test"));
    }

    public function testVariationSelectorRemoved(): void
    {
        // U+FE00 VARIATION SELECTOR-1
        $this->assertSame('abc', $this->saslprep->apply("a\u{FE00}bc"));
    }

    public function testMongolianTodoSoftHyphenRemoved(): void
    {
        // U+1806 - this is the edge case where modern IntlChar diverges
        $this->assertSame('ab', $this->saslprep->apply("a\u{1806}b"));
    }

    // --- C.1.2 mapping: non-ASCII space to ASCII space ---

    public function testNbspMappedToSpace(): void
    {
        // U+00A0 NO-BREAK SPACE → U+0020
        $this->assertSame('a b', $this->saslprep->apply("a\u{00A0}b"));
    }

    public function testEmSpaceMappedToSpace(): void
    {
        // U+2003 EM SPACE → U+0020
        $this->assertSame('a b', $this->saslprep->apply("a\u{2003}b"));
    }

    public function testIdeographicSpaceMappedToSpace(): void
    {
        // U+3000 IDEOGRAPHIC SPACE → U+0020
        $this->assertSame('a b', $this->saslprep->apply("a\u{3000}b"));
    }

    // --- NFKC normalization ---

    public function testNfkcNormalization(): void
    {
        // U+2163 ROMAN NUMERAL FOUR → "IV" after NFKC
        $this->assertSame('IV', $this->saslprep->apply("\u{2163}"));
    }

    public function testNfkcDecomposition(): void
    {
        // U+00C5 (Å, precomposed) and U+212B (Angstrom sign) should
        // both normalize to the same form
        $result1 = $this->saslprep->apply("\u{00C5}");
        $result2 = $this->saslprep->apply("\u{212B}");
        $this->assertSame($result1, $result2);
    }

    // --- No case folding (RFC 4013 correctness) ---

    public function testNoCaseFolding(): void
    {
        // RFC 4013 does NOT specify B.2 case folding
        // Uppercase ASCII must remain uppercase
        $this->assertSame('PASSWORD', $this->saslprep->apply('PASSWORD'));
    }

    public function testRomanNumeralPreservesCase(): void
    {
        // U+2160 ROMAN NUMERAL ONE → "I" (not "i")
        // NFKC decomposes compatibility chars but doesn't case fold
        $result = $this->saslprep->apply("\u{2160}");
        $this->assertSame('I', $result);
    }

    // --- Prohibited characters ---

    public function testProhibitedAsciiControl(): void
    {
        // C.2.1: U+0007 BEL
        $this->expectException(SaslPrepException::class);
        $this->saslprep->apply("a\x07b");
    }

    public function testProhibitedNullChar(): void
    {
        // C.2.1: U+0000 NULL
        $this->expectException(SaslPrepException::class);
        $this->saslprep->apply("a\x00b");
    }

    public function testProhibitedDelete(): void
    {
        // C.2.1: U+007F DELETE
        $this->expectException(SaslPrepException::class);
        $this->saslprep->apply("a\x7Fb");
    }

    public function testProhibitedNonAsciiControl(): void
    {
        // C.2.2: U+0080-0x009F range
        $this->expectException(SaslPrepException::class);
        $this->saslprep->apply("a\u{0085}b");
    }

    public function testProhibitedPrivateUse(): void
    {
        // C.3: U+E000 (start of private use area)
        $this->expectException(SaslPrepException::class);
        $this->saslprep->apply("a\u{E000}b");
    }

    public function testProhibitedNonCharacter(): void
    {
        // C.4: U+FDD0
        $this->expectException(SaslPrepException::class);
        $this->saslprep->apply("a\u{FDD0}b");
    }

    public function testProhibitedIdeographicDescription(): void
    {
        // C.7: U+2FF0
        $this->expectException(SaslPrepException::class);
        $this->saslprep->apply("a\u{2FF0}b");
    }

    public function testProhibitedChangeDisplay(): void
    {
        // C.8: U+202A LEFT-TO-RIGHT EMBEDDING
        $this->expectException(SaslPrepException::class);
        $this->saslprep->apply("a\u{202A}b");
    }

    public function testProhibitedTaggingCharacter(): void
    {
        // C.9: U+E0001 LANGUAGE TAG
        $this->expectException(SaslPrepException::class);
        $this->saslprep->apply("a\u{E0001}b");
    }

    // --- Bidirectional checks ---

    public function testPureHebrewAllowed(): void
    {
        // Pure RandALCat string: Hebrew characters
        $result = $this->saslprep->apply("\u{05D0}\u{05D1}\u{05D2}");
        $this->assertSame("\u{05D0}\u{05D1}\u{05D2}", $result);
    }

    public function testPureArabicAllowed(): void
    {
        // Pure RandALCat string: Arabic characters
        $result = $this->saslprep->apply("\u{0627}\u{0628}\u{0629}");
        $this->assertSame("\u{0627}\u{0628}\u{0629}", $result);
    }

    public function testMixedHebrewLatinProhibited(): void
    {
        // RandALCat + LCat mixed: Hebrew + Latin
        $this->expectException(SaslPrepException::class);
        $this->saslprep->apply("\u{05D0}abc\u{05D1}");
    }

    public function testRandALCatMustStartString(): void
    {
        // String with RandALCat that doesn't start with RandALCat
        // U+0020 (space) has bidi class WS, not L or R, followed by Hebrew
        // This actually passes because space is not LCat — but if we construct
        // a string that has RandALCat not at position 0:
        // digits (0-9) have bidi class EN (European Number), not L
        // so we need actual LCat characters
        $this->expectException(SaslPrepException::class);
        $this->saslprep->apply("a\u{05D0}");
    }

    // --- MODE_STORE vs MODE_QUERY ---

    public function testModeStoreIsDefault(): void
    {
        // Default should be MODE_STORE
        $this->assertSame('hello', $this->saslprep->apply('hello'));
    }

    public function testModeQueryAllowsDefinedChars(): void
    {
        // Defined characters should pass in MODE_QUERY too
        $this->assertSame(
            'hello',
            $this->saslprep->apply('hello', SaslPrepProfile::MODE_QUERY)
        );
    }

    // --- Realistic IMAP credentials ---

    public function testTypicalUsername(): void
    {
        $this->assertSame(
            'user@example.com',
            $this->saslprep->apply('user@example.com')
        );
    }

    public function testTypicalPassword(): void
    {
        $this->assertSame(
            'correct horse battery staple',
            $this->saslprep->apply('correct horse battery staple')
        );
    }

    public function testPasswordWithNbsp(): void
    {
        // NBSP in password gets mapped to regular space
        $this->assertSame(
            'my password',
            $this->saslprep->apply("my\u{00A0}password")
        );
    }

    public function testUnicodeUsername(): void
    {
        // Common non-ASCII username (German)
        $this->assertSame('müller', $this->saslprep->apply('müller'));
    }

    // --- Multiple mappings in one string ---

    public function testMultipleMappingsAndRemovals(): void
    {
        // Combine B.1 removal + C.1.2 space mapping
        $input = "\u{FEFF}hello\u{00A0}world\u{200C}";
        // FEFF removed (B.1), NBSP→space (C.1.2), 200C removed (B.1)
        $this->assertSame('hello world', $this->saslprep->apply($input));
    }
}
