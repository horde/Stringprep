<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Stringprep;

/**
 * SASLprep profile (RFC 4013).
 *
 * Prepares user names and passwords for SASL authentication.
 *
 * Note: RFC 4013 does NOT specify B.2 case folding. The previous znerol
 * implementation applied it, which was a bug. This implementation follows
 * the RFC correctly.
 */
class SaslPrepProfile implements ProfileInterface
{
    use StringprepTrait;

    /** @var array<int, true> */
    private const SPACE_MAP = [
        0x00A0 => true, 0x1680 => true,
        0x2000 => true, 0x2001 => true, 0x2002 => true, 0x2003 => true,
        0x2004 => true, 0x2005 => true, 0x2006 => true, 0x2007 => true,
        0x2008 => true, 0x2009 => true, 0x200A => true, 0x200B => true,
        0x202F => true, 0x205F => true, 0x3000 => true,
    ];

    protected function removeZeroWidthSpaces(): bool
    {
        return true;
    }

    protected function caseFold(): bool
    {
        return false;
    }

    protected function normalizeToNfkc(): bool
    {
        return true;
    }

    protected function checkBidirectional(): bool
    {
        return true;
    }

    protected function prohibitedTables(): array
    {
        return [
            'C_1_2' => true, 'C_2_1' => true, 'C_2_2' => true,
            'C_3' => true, 'C_4' => true, 'C_5' => true,
            'C_6' => true, 'C_7' => true, 'C_8' => true, 'C_9' => true,
        ];
    }

    /**
     * RFC 4013 Section 2.1: map C.1.2 non-ASCII spaces to ASCII space.
     */
    protected function mapCodepoint(int $codepoint): ?string
    {
        if (isset(self::SPACE_MAP[$codepoint])) {
            return ' ';
        }
        return null;
    }
}
