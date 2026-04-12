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
 * Policy MIB stringprep profile (RFC 4011 Section 9.1.1).
 *
 * Stringprep profile for Policy-Based Management MIB strings.
 * Notable: no bidirectional checking, no C.1.1/C.1.2 prohibition.
 */
class PolicyMibProfile implements ProfileInterface
{
    use StringprepTrait;

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
        return false;
    }

    protected function prohibitedTables(): array
    {
        return [
            'C_2_1' => true, 'C_2_2' => true,
            'C_3' => true, 'C_4' => true, 'C_5' => true,
            'C_6' => true, 'C_7' => true, 'C_8' => true, 'C_9' => true,
        ];
    }
}
