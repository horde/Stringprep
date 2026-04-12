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
 * PRECIS Nickname profile (RFC 8266).
 *
 * Prepares human-friendly nicknames for chat, messaging, etc.
 * Uses FreeformClass, NFKC normalization, case mapping, no bidi.
 * Additional mapping: non-ASCII spaces to ASCII space.
 * Post-processing: trim leading/trailing spaces, collapse interior runs.
 */
class NicknameProfile implements ProfileInterface
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
        return true;
    }

    protected function normalizationForm(): int
    {
        return Normalizer::FORM_KC;
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

    /**
     * Trim leading/trailing spaces and collapse interior space runs.
     */
    protected function postProcess(string $string): string
    {
        // Collapse runs of spaces to single space
        $string = (string) preg_replace('/  +/', ' ', $string);
        // Trim
        return trim($string);
    }
}
