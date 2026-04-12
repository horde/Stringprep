<?php

declare(strict_types=1);

namespace Horde\Stringprep\Test;

use Horde\Stringprep\OpaqueStringProfile;
use Horde\Stringprep\PrecisException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OpaqueStringProfile::class)]
class OpaqueStringProfileTest extends TestCase
{
    private OpaqueStringProfile $profile;

    protected function setUp(): void
    {
        $this->profile = new OpaqueStringProfile();
    }

    public function testAsciiPasswordPassthrough(): void
    {
        $this->assertSame('P@ssw0rd!#$', $this->profile->apply('P@ssw0rd!#$'));
    }

    public function testCasePreserved(): void
    {
        $this->assertSame('PASSWORD', $this->profile->apply('PASSWORD'));
    }

    public function testSpacesAllowed(): void
    {
        // FreeformClass allows spaces
        $this->assertSame('correct horse battery staple', $this->profile->apply('correct horse battery staple'));
    }

    public function testNonAsciiSpaceMappedToAscii(): void
    {
        // U+00A0 NBSP → ASCII space
        $this->assertSame('my password', $this->profile->apply("my\u{00A0}password"));
    }

    public function testEmSpaceMappedToAscii(): void
    {
        $this->assertSame('a b', $this->profile->apply("a\u{2003}b"));
    }

    public function testSymbolsAllowed(): void
    {
        // € is IdDis → allowed in FreeformClass
        $this->assertSame("\u{20AC}100", $this->profile->apply("\u{20AC}100"));
    }

    public function testPunctuationAllowed(): void
    {
        $this->assertSame("it's-a-test!", $this->profile->apply("it's-a-test!"));
    }

    public function testNfcNormalization(): void
    {
        // Combining sequence normalizes
        $this->assertSame("\u{00C0}", $this->profile->apply("A\u{0300}"));
    }

    public function testNoWidthMapping(): void
    {
        // OpaqueString does NOT apply width mapping
        // But fullwidth 'A' (U+FF21) has a compat mapping → IdDis in FreeformClass
        // It's allowed but NOT decomposed (no width mapping)
        // However, NFC normalization may affect it... let's check:
        // U+FF21 is a single character, NFC doesn't decompose it
        $this->assertSame("\u{FF21}", $this->profile->apply("\u{FF21}"));
    }

    public function testControlRejected(): void
    {
        $this->expectException(PrecisException::class);
        $this->profile->apply("a\x07b");
    }

    public function testSoftHyphenRejected(): void
    {
        $this->expectException(PrecisException::class);
        $this->profile->apply("a\u{00AD}b");
    }

    public function testNoBidiCheck(): void
    {
        // OpaqueString has no bidi check — mixed scripts allowed
        $result = $this->profile->apply("\u{05D0}abc\u{05D1}");
        $this->assertSame("\u{05D0}abc\u{05D1}", $result);
    }

    public function testUnicodePassword(): void
    {
        $this->assertSame('münchen123!', $this->profile->apply('münchen123!'));
    }

    public function testEmptyStringPassthrough(): void
    {
        $this->assertSame('', $this->profile->apply(''));
    }
}
