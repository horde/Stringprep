<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Stringprep;

use IntlChar;
use Normalizer;

/**
 * PRECIS OpaqueString profile (RFC 8265 Section 5).
 *
 * Prepares passwords and other opaque strings.
 * Uses FreeformClass, NFC normalization, no width mapping, no bidi, no case
 * mapping. Maps non-ASCII spaces (Zs) to ASCII space.
 */
class OpaqueStringProfile implements ProfileInterface
{
    use PrecisTrait;

    protected function stringClass(): string
    {
        return 'freeform';
    }

    protected function applyWidthMapping(): bool
    {
        return false;
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
        return false;
    }

    /**
     * Map non-ASCII spaces (General_Category Zs, except U+0020) to ASCII space.
     */
    protected function mapCodepoint(int $codepoint): ?string
    {
        if ($codepoint !== 0x0020
            && IntlChar::charType($codepoint) === IntlChar::CHAR_CATEGORY_SPACE_SEPARATOR) {
            return ' ';
        }
        return null;
    }
}
