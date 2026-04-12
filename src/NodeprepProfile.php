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
 * Nodeprep profile (RFC 6122 Appendix A).
 *
 * Stringprep profile for XMPP node identifiers.
 */
class NodeprepProfile implements ProfileInterface
{
    use StringprepTrait;

    /**
     * Characters additionally prohibited by Nodeprep.
     *
     * " & ' / : < > @
     */
    private const PROHIBITED_NODEPREP = [
        0x0022, 0x0026, 0x0027, 0x002F,
        0x003A, 0x003C, 0x003E, 0x0040,
    ];

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
     * @throws SaslPrepException
     */
    protected function checkAdditionalProhibited(int $codepoint): void
    {
        if (in_array($codepoint, self::PROHIBITED_NODEPREP, true)) {
            throw new SaslPrepException(
                sprintf('Prohibited XMPP node character U+%04X', $codepoint)
            );
        }
    }
}
