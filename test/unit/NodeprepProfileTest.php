<?php

declare(strict_types=1);

namespace Horde\Stringprep\Test;

use Horde\Stringprep\NodeprepProfile;
use Horde\Stringprep\SaslPrepException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NodeprepProfile::class)]
class NodeprepProfileTest extends TestCase
{
    private NodeprepProfile $profile;

    protected function setUp(): void
    {
        $this->profile = new NodeprepProfile();
    }

    public function testValidXmppNode(): void
    {
        $this->assertSame('juliet', $this->profile->apply('juliet'));
    }

    public function testCaseFolding(): void
    {
        $this->assertSame('juliet', $this->profile->apply('Juliet'));
    }

    public function testProhibitedDoubleQuote(): void
    {
        $this->expectException(SaslPrepException::class);
        $this->profile->apply('user"name');
    }

    public function testProhibitedAmpersand(): void
    {
        $this->expectException(SaslPrepException::class);
        $this->profile->apply('user&name');
    }

    public function testProhibitedApostrophe(): void
    {
        $this->expectException(SaslPrepException::class);
        $this->profile->apply("user'name");
    }

    public function testProhibitedSlash(): void
    {
        $this->expectException(SaslPrepException::class);
        $this->profile->apply('user/name');
    }

    public function testProhibitedColon(): void
    {
        $this->expectException(SaslPrepException::class);
        $this->profile->apply('user:name');
    }

    public function testProhibitedLessThan(): void
    {
        $this->expectException(SaslPrepException::class);
        $this->profile->apply('user<name');
    }

    public function testProhibitedGreaterThan(): void
    {
        $this->expectException(SaslPrepException::class);
        $this->profile->apply('user>name');
    }

    public function testProhibitedAtSign(): void
    {
        $this->expectException(SaslPrepException::class);
        $this->profile->apply('user@name');
    }

    public function testProhibitedAsciiSpace(): void
    {
        // C.1.1 is active
        $this->expectException(SaslPrepException::class);
        $this->profile->apply('hello world');
    }

    public function testAllowedDashUnderscore(): void
    {
        $this->assertSame('user-name_test', $this->profile->apply('user-name_test'));
    }

    public function testB1Removal(): void
    {
        $this->assertSame('ab', $this->profile->apply("a\u{00AD}b"));
    }

    public function testNfkcNormalization(): void
    {
        // Case fold + NFKC: Roman numeral → lowercase
        $this->assertSame('iv', $this->profile->apply("\u{2163}"));
    }

    public function testUnicodeNodeName(): void
    {
        // Unicode characters allowed (case folded)
        $this->assertSame('münchen', $this->profile->apply('München'));
    }
}
