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
 * Nameprep profile (RFC 3491).
 *
 * Stringprep profile for Internationalized Domain Names (IDNA).
 */
class NameprepProfile implements ProfileInterface
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
            'C_1_2' => true, 'C_2_2' => true,
            'C_3' => true, 'C_4' => true, 'C_5' => true,
            'C_6' => true, 'C_7' => true, 'C_8' => true, 'C_9' => true,
        ];
    }
}
