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
 * PRECIS UsernameCaseMapped profile (RFC 8265 Section 3).
 *
 * Prepares usernames with case mapping (uppercase → lowercase).
 * Uses IdentifierClass, NFC normalization, width mapping, and bidi rules.
 */
class UsernameCaseMappedProfile implements ProfileInterface
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
        return true;
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
