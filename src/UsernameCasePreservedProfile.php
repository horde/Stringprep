<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Stringprep;

use Normalizer;

/**
 * PRECIS UsernameCasePreserved profile (RFC 8265 Section 4).
 *
 * Prepares usernames preserving case.
 * Uses IdentifierClass, NFC normalization, width mapping, and bidi rules.
 */
class UsernameCasePreservedProfile implements ProfileInterface
{
    use PrecisTrait;

    protected function stringClass(): string
    {
        return 'identifier';
    }

    protected function applyWidthMapping(): bool
    {
        return true;
    }

    protected function applyCaseMapping(): bool
    {
        return false;
    }

    protected function normalizationForm(): int
    {
        return Normalizer::FORM_C;
    }

    protected function checkBidirectional(): bool
    {
        return true;
    }
}
