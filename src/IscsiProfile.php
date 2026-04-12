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
 * iSCSI stringprep profile (RFC 3722 Section 6).
 *
 * Stringprep profile for iSCSI names.
 */
class IscsiProfile implements ProfileInterface
{
    use StringprepTrait;

    protected function removeZeroWidthSpaces(): bool
    {
        return true;
    }

    protected function caseFold(): bool
    {
        return true;
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
            'C_1_1' => true, 'C_1_2' => true,
            'C_2_1' => true, 'C_2_2' => true,
            'C_3' => true, 'C_4' => true, 'C_5' => true,
            'C_6' => true, 'C_7' => true, 'C_8' => true, 'C_9' => true,
        ];
    }

    /**
     * RFC 3722 Section 6: additional prohibited characters for iSCSI.
     *
     * Prohibits: 0x0000-0x002C, 0x002F, 0x003B-0x0040, 0x005B-0x0060,
     * 0x007B-0x007F, 0x3002.
     *
     * @throws SaslPrepException
     */
    protected function checkAdditionalProhibited(int $codepoint): void
    {
        if (($codepoint >= 0x0000 && $codepoint <= 0x002C)
            || $codepoint === 0x002F
            || ($codepoint >= 0x003B && $codepoint <= 0x0040)
            || ($codepoint >= 0x005B && $codepoint <= 0x0060)
            || ($codepoint >= 0x007B && $codepoint <= 0x007F)
            || $codepoint === 0x3002) {
            throw new SaslPrepException(
                sprintf('Prohibited iSCSI character U+%04X', $codepoint)
            );
        }
    }
}
