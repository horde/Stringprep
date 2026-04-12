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
 * Shared RFC 3454 stringprep algorithm backed by ext-intl.
 *
 * Profiles use this trait and implement the abstract configuration methods
 * to select which mapping, normalization, prohibition, and bidi rules apply.
 *
 * Hardcoded codepoint tables cover the places where the frozen Unicode 3.2
 * values from RFC 3454 diverge from modern ICU/IntlChar property values.
 */
trait StringprepTrait
{
    // ------------------------------------------------------------------
    // RFC 3454 frozen tables (Unicode 3.2)
    // ------------------------------------------------------------------

    /**
     * Table B.1 — characters mapped to nothing.
     *
     * Hardcoded because IntlChar DEFAULT_IGNORABLE_CODE_POINT diverges
     * for U+1806 in modern Unicode.
     */
    private const B1_MAP_TO_NOTHING = [
        0x00AD, 0x034F, 0x1806, 0x180B, 0x180C, 0x180D,
        0x200B, 0x200C, 0x200D, 0x2060,
        0xFE00, 0xFE01, 0xFE02, 0xFE03, 0xFE04, 0xFE05,
        0xFE06, 0xFE07, 0xFE08, 0xFE09, 0xFE0A, 0xFE0B,
        0xFE0C, 0xFE0D, 0xFE0E, 0xFE0F,
        0xFEFF,
    ];

    /**
     * Table C.1.2 — non-ASCII space characters.
     *
     * Hardcoded because IntlChar WHITE_SPACE doesn't match the frozen
     * list (e.g. U+200B is in C.1.2 but not WHITE_SPACE in modern Unicode).
     */
    private const C12_NON_ASCII_SPACES = [
        0x00A0, 0x1680,
        0x2000, 0x2001, 0x2002, 0x2003, 0x2004, 0x2005,
        0x2006, 0x2007, 0x2008, 0x2009, 0x200A, 0x200B,
        0x202F, 0x205F, 0x3000,
    ];

    /**
     * Table C.2.2 — non-ASCII control character singletons.
     *
     * Ranges (0x0080–0x009F, 0x206A–0x206F, 0xFFF9–0xFFFC, 0x1D173–0x1D17A)
     * are checked inline in checkProhibitedCodepoint().
     */
    private const C22_NON_ASCII_CONTROL = [
        0x06DD, 0x070F, 0x180E,
        0x200C, 0x200D,
        0x2028, 0x2029,
        0x2060, 0x2061, 0x2062, 0x2063,
        0xFEFF,
    ];

    /**
     * Table C.8 — change display properties / deprecated.
     */
    private const C8_CHANGE_DISPLAY = [
        0x0340, 0x0341,
        0x200E, 0x200F,
        0x202A, 0x202B, 0x202C, 0x202D, 0x202E,
        0x206A, 0x206B, 0x206C, 0x206D, 0x206E, 0x206F,
    ];

    // ------------------------------------------------------------------
    // Abstract configuration — each profile must implement these
    // ------------------------------------------------------------------

    /** Whether to strip B.1 zero-width characters. */
    abstract protected function removeZeroWidthSpaces(): bool;

    /** Whether to apply B.2 Unicode case folding. */
    abstract protected function caseFold(): bool;

    /** Whether to apply NFKC normalization. */
    abstract protected function normalizeToNfkc(): bool;

    /** Whether to enforce RFC 3454 Section 6 bidi rules. */
    abstract protected function checkBidirectional(): bool;

    /**
     * Return the set of C.* prohibition tables active for this profile.
     *
     * Recognised keys: C_1_1, C_1_2, C_2_1, C_2_2, C_3, C_4, C_5,
     * C_6, C_7, C_8, C_9.
     *
     * @return array<string, true>  Table names as keys.
     */
    abstract protected function prohibitedTables(): array;

    // ------------------------------------------------------------------
    // Optional hooks — profiles override when they need custom behaviour
    // ------------------------------------------------------------------

    /**
     * Profile-specific mapping for a single codepoint.
     *
     * Return a replacement string (possibly empty to remove the char),
     * or null to keep the original character.
     */
    protected function mapCodepoint(int $codepoint): ?string
    {
        return null;
    }

    /**
     * Profile-specific prohibition check for a single codepoint.
     *
     * @throws SaslPrepException  If the codepoint is prohibited.
     */
    protected function checkAdditionalProhibited(int $codepoint): void
    {
    }

    // ------------------------------------------------------------------
    // Core algorithm
    // ------------------------------------------------------------------

    /**
     * Apply this stringprep profile to a UTF-8 string.
     *
     * @param string $string  Input UTF-8 string.
     * @param int    $mode    ProfileInterface::MODE_STORE or MODE_QUERY.
     *
     * @return string  The prepared string.
     *
     * @throws SaslPrepException
     */
    public function apply(string $string, int $mode = ProfileInterface::MODE_STORE): string
    {
        if ($string === '') {
            return '';
        }

        // Step 1: Mapping (RFC 3454 Section 3)
        $string = $this->mapString($string);

        // Step 2: Normalization (RFC 3454 Section 4)
        if ($this->normalizeToNfkc()) {
            $normalized = Normalizer::normalize($string, Normalizer::FORM_KC);
            if ($normalized === false) {
                throw new SaslPrepException('NFKC normalization failed');
            }
            $string = $normalized;
        }

        // Steps 3–5 operate on codepoints
        $codepoints = $this->stringToCodepoints($string);

        // Step 3: Prohibition check (RFC 3454 Section 5)
        $tables = $this->prohibitedTables();
        foreach ($codepoints as $cp) {
            $this->checkProhibitedCodepoint($cp, $tables);
            $this->checkAdditionalProhibited($cp);
        }

        // Step 4: Unassigned check (MODE_STORE only, RFC 3454 Section 7)
        if ($mode === ProfileInterface::MODE_STORE) {
            foreach ($codepoints as $cp) {
                if (!IntlChar::isdefined($cp)) {
                    throw new SaslPrepException(
                        sprintf('Unassigned codepoint U+%04X', $cp)
                    );
                }
            }
        }

        // Step 5: Bidirectional check (RFC 3454 Section 6)
        if ($this->checkBidirectional()) {
            $this->checkBidi($codepoints);
        }

        return $string;
    }

    // ------------------------------------------------------------------
    // Mapping
    // ------------------------------------------------------------------

    private function mapString(string $string): string
    {
        $b1Lookup = $this->removeZeroWidthSpaces()
            ? array_flip(self::B1_MAP_TO_NOTHING)
            : [];
        $doCaseFold = $this->caseFold();

        $result = '';
        $length = mb_strlen($string, 'UTF-8');

        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($string, $i, 1, 'UTF-8');
            $cp = IntlChar::ord($char);
            if ($cp === null) {
                throw new SaslPrepException('Invalid UTF-8 sequence');
            }

            // B.1: map to nothing
            if (isset($b1Lookup[$cp])) {
                continue;
            }

            // Profile-specific mapping hook
            $mapped = $this->mapCodepoint($cp);
            if ($mapped !== null) {
                $result .= $mapped;
                continue;
            }

            // B.2: case folding
            if ($doCaseFold) {
                $folded = IntlChar::foldCase($char);
                $result .= $folded;
                continue;
            }

            $result .= $char;
        }

        return $result;
    }

    // ------------------------------------------------------------------
    // Prohibition
    // ------------------------------------------------------------------

    /**
     * @param array<string, true> $tables
     *
     * @throws SaslPrepException
     */
    private function checkProhibitedCodepoint(int $cp, array $tables): void
    {
        // C.1.1 — ASCII space
        if (isset($tables['C_1_1']) && $cp === 0x0020) {
            throw new SaslPrepException(
                sprintf('Prohibited ASCII space U+%04X', $cp)
            );
        }

        // C.1.2 — Non-ASCII space
        if (isset($tables['C_1_2']) && in_array($cp, self::C12_NON_ASCII_SPACES, true)) {
            throw new SaslPrepException(
                sprintf('Prohibited non-ASCII space U+%04X', $cp)
            );
        }

        // C.2.1 — ASCII control characters
        if (isset($tables['C_2_1'])) {
            if (($cp >= 0x0000 && $cp <= 0x001F) || $cp === 0x007F) {
                throw new SaslPrepException(
                    sprintf('Prohibited ASCII control character U+%04X', $cp)
                );
            }
        }

        // C.2.2 — Non-ASCII control characters
        if (isset($tables['C_2_2'])) {
            if (($cp >= 0x0080 && $cp <= 0x009F)
                || ($cp >= 0x206A && $cp <= 0x206F)
                || ($cp >= 0xFFF9 && $cp <= 0xFFFC)
                || ($cp >= 0x1D173 && $cp <= 0x1D17A)
                || in_array($cp, self::C22_NON_ASCII_CONTROL, true)) {
                throw new SaslPrepException(
                    sprintf('Prohibited non-ASCII control character U+%04X', $cp)
                );
            }
        }

        // C.3 — Private use
        if (isset($tables['C_3'])) {
            if (($cp >= 0xE000 && $cp <= 0xF8FF)
                || ($cp >= 0xF0000 && $cp <= 0xFFFFD)
                || ($cp >= 0x100000 && $cp <= 0x10FFFD)) {
                throw new SaslPrepException(
                    sprintf('Prohibited private use character U+%04X', $cp)
                );
            }
        }

        // C.4 — Non-character code points
        if (isset($tables['C_4'])) {
            if (($cp >= 0xFDD0 && $cp <= 0xFDEF)
                || (($cp & 0xFFFF) >= 0xFFFE)) {
                throw new SaslPrepException(
                    sprintf('Prohibited non-character U+%04X', $cp)
                );
            }
        }

        // C.5 — Surrogate codes
        if (isset($tables['C_5'])) {
            if ($cp >= 0xD800 && $cp <= 0xDFFF) {
                throw new SaslPrepException(
                    sprintf('Prohibited surrogate code U+%04X', $cp)
                );
            }
        }

        // C.6 — Inappropriate for plain text
        if (isset($tables['C_6'])) {
            if ($cp >= 0xFFF9 && $cp <= 0xFFFD) {
                throw new SaslPrepException(
                    sprintf('Prohibited character inappropriate for plain text U+%04X', $cp)
                );
            }
        }

        // C.7 — Inappropriate for canonical representation
        if (isset($tables['C_7'])) {
            if ($cp >= 0x2FF0 && $cp <= 0x2FFB) {
                throw new SaslPrepException(
                    sprintf('Prohibited ideographic description character U+%04X', $cp)
                );
            }
        }

        // C.8 — Change display properties or deprecated
        if (isset($tables['C_8'])) {
            if (in_array($cp, self::C8_CHANGE_DISPLAY, true)) {
                throw new SaslPrepException(
                    sprintf('Prohibited display property/deprecated character U+%04X', $cp)
                );
            }
        }

        // C.9 — Tagging characters
        if (isset($tables['C_9'])) {
            if ($cp === 0xE0001 || ($cp >= 0xE0020 && $cp <= 0xE007F)) {
                throw new SaslPrepException(
                    sprintf('Prohibited tagging character U+%04X', $cp)
                );
            }
        }
    }

    // ------------------------------------------------------------------
    // Bidirectional check (RFC 3454 Section 6)
    // ------------------------------------------------------------------

    /**
     * @param int[] $codepoints
     *
     * @throws SaslPrepException
     */
    private function checkBidi(array $codepoints): void
    {
        if (count($codepoints) === 0) {
            return;
        }

        $hasRandALCat = false;
        $hasLCat = false;

        foreach ($codepoints as $cp) {
            $dir = IntlChar::charDirection($cp);
            if ($dir === IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT
                || $dir === IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT_ARABIC) {
                $hasRandALCat = true;
            } elseif ($dir === IntlChar::CHAR_DIRECTION_LEFT_TO_RIGHT) {
                $hasLCat = true;
            }
        }

        if ($hasRandALCat) {
            if ($hasLCat) {
                throw new SaslPrepException(
                    'Bidirectional check failed: string contains both RandALCat and LCat characters'
                );
            }

            $firstDir = IntlChar::charDirection($codepoints[0]);
            if ($firstDir !== IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT
                && $firstDir !== IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT_ARABIC) {
                throw new SaslPrepException(
                    'Bidirectional check failed: string with RandALCat must start with RandALCat'
                );
            }

            $lastDir = IntlChar::charDirection($codepoints[count($codepoints) - 1]);
            if ($lastDir !== IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT
                && $lastDir !== IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT_ARABIC) {
                throw new SaslPrepException(
                    'Bidirectional check failed: string with RandALCat must end with RandALCat'
                );
            }
        }
    }

    // ------------------------------------------------------------------
    // Utility
    // ------------------------------------------------------------------

    /**
     * @return int[]
     */
    private function stringToCodepoints(string $string): array
    {
        $codepoints = [];
        $length = mb_strlen($string, 'UTF-8');
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($string, $i, 1, 'UTF-8');
            $cp = IntlChar::ord($char);
            if ($cp === null) {
                throw new SaslPrepException('Invalid UTF-8 sequence');
            }
            $codepoints[] = $cp;
        }
        return $codepoints;
    }
}
