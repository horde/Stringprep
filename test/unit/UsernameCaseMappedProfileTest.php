<?php

declare(strict_types=1);

namespace Horde\Stringprep\Test;

use Horde\Stringprep\PrecisException;
use Horde\Stringprep\UsernameCaseMappedProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UsernameCaseMappedProfile::class)]
class UsernameCaseMappedProfileTest extends TestCase
{
    private UsernameCaseMappedProfile $profile;

    protected function setUp(): void
    {
        $this->profile = new UsernameCaseMappedProfile();
    }

    public function testAsciiPassthrough(): void
    {
        $this->assertSame('juliet', $this->profile->apply('juliet'));
    }

    public function testCaseMapping(): void
    {
        $this->assertSame('juliet', $this->profile->apply('Juliet'));
    }

    public function testAllUppercase(): void
    {
        $this->assertSame('juliet', $this->profile->apply('JULIET'));
    }

    public function testUnicodeCaseMapping(): void
    {
        $this->assertSame('münchen', $this->profile->apply('München'));
    }

    public function testNfcNormalization(): void
    {
        // U+0041 + U+0300 (A + combining grave) → U+00C0 (À) → lowercase à
        $this->assertSame("\u{00E0}", $this->profile->apply("A\u{0300}"));
    }

    public function testWidthMapping(): void
    {
        // U+FF21 FULLWIDTH LATIN CAPITAL LETTER A → 'a' (width map + case map)
        $this->assertSame('a', $this->profile->apply("\u{FF21}"));
    }

    public function testSpaceRejected(): void
    {
        // Spaces are IdDis — not allowed in IdentifierClass
        $this->expectException(PrecisException::class);
        $this->profile->apply('hello world');
    }

    public function testSymbolRejected(): void
    {
        // € (U+20AC) is IdDis
        $this->expectException(PrecisException::class);
        $this->profile->apply("price\u{20AC}");
    }

    public function testControlRejected(): void
    {
        $this->expectException(PrecisException::class);
        $this->profile->apply("a\x07b");
    }

    public function testSoftHyphenRejected(): void
    {
        // PrecisIgnorable → Disallowed
        $this->expectException(PrecisException::class);
        $this->profile->apply("a\u{00AD}b");
    }

    public function testEmptyStringPassthrough(): void
    {
        $this->assertSame('', $this->profile->apply(''));
    }

    public function testDigitsAllowed(): void
    {
        $this->assertSame('user123', $this->profile->apply('user123'));
    }

    public function testEmailStyleUsername(): void
    {
        // @ and . are in ASCII7 range → Pvalid
        $this->assertSame('user@example.com', $this->profile->apply('user@example.com'));
    }

    public function testArabicUsername(): void
    {
        $result = $this->profile->apply("\u{0627}\u{0628}\u{0629}");
        $this->assertSame("\u{0627}\u{0628}\u{0629}", $result);
    }

    public function testBidiMixedRtlLtrRejected(): void
    {
        // Hebrew + Latin mixed violates bidi
        $this->expectException(PrecisException::class);
        $this->profile->apply("\u{05D0}abc\u{05D1}");
    }
}
