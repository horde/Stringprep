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
 * Shared PRECIS processing algorithm (RFC 7564 / RFC 8265).
 *
 * Profiles use this trait and implement the abstract configuration methods.
 * Unlike StringprepTrait (frozen Unicode 3.2), PRECIS is Unicode-version-agile
 * and uses IntlChar properties directly.
 */
trait PrecisTrait
{
    // ------------------------------------------------------------------
    // ICU UScriptCode values — IntlChar does not expose these as constants
    // ------------------------------------------------------------------

    private const USCRIPT_GREEK = 14;
    private const USCRIPT_HAN = 17;
    private const USCRIPT_HEBREW = 19;
    private const USCRIPT_HIRAGANA = 20;
    private const USCRIPT_KATAKANA = 22;

    // ------------------------------------------------------------------
    // Abstract configuration — each profile must implement these
    // ------------------------------------------------------------------

    /**
     * Return the PRECIS string class: 'identifier' or 'freeform'.
     */
    abstract protected function stringClass(): string;

    /** Whether to apply fullwidth/halfwidth width mapping. */
    abstract protected function applyWidthMapping(): bool;

    /** Whether to apply Unicode toLowerCase case mapping. */
    abstract protected function applyCaseMapping(): bool;

    /**
     * Normalization form constant.
     *
     * Typically Normalizer::FORM_C (NFC) or Normalizer::FORM_KC (NFKC).
     */
    abstract protected function normalizationForm(): int;

    /** Whether to enforce RFC 5893 bidi rules. */
    abstract protected function checkBidirectional(): bool;

    // ------------------------------------------------------------------
    // Optional hooks
    // ------------------------------------------------------------------

    /**
     * Profile-specific mapping for a single codepoint.
     *
     * Return a replacement string (possibly empty), or null to keep
     * the original character.
     */
    protected function mapCodepoint(int $codepoint): ?string
    {
        return null;
    }

    /**
     * Post-processing hook applied after normalization.
     *
     * Default: identity. Override for profiles that need trimming,
     * whitespace collapsing, etc. (e.g. Nickname).
     */
    protected function postProcess(string $string): string
    {
        return $string;
    }

    // ------------------------------------------------------------------
    // Core algorithm
    // ------------------------------------------------------------------

    /**
     * Apply this PRECIS profile to a UTF-8 string.
     *
     * Follows the enforcement steps from RFC 8265.
     *
     * @param string $string  Input UTF-8 string.
     * @param int    $mode    ProfileInterface::MODE_STORE or MODE_QUERY.
     *
     * @throws PrecisException
     */
    public function apply(string $string, int $mode = ProfileInterface::MODE_STORE): string
    {
        if ($string === '') {
            return '';
        }

        // Step 1: Width mapping
        if ($this->applyWidthMapping()) {
            $string = $this->widthMap($string);
        }

        // Step 2: Codepoint validation (before mapping/normalization)
        $codepoints = $this->precis_stringToCodepoints($string);
        $isIdentifier = $this->stringClass() === 'identifier';

        foreach ($codepoints as $i => $cp) {
            $cat = PrecisCategory::classify($cp);

            // Unassigned check
            if ($cat === PrecisCategory::Unassigned) {
                if ($mode === ProfileInterface::MODE_STORE) {
                    throw new PrecisException(
                        sprintf('Unassigned codepoint U+%04X', $cp)
                    );
                }
                // MODE_QUERY: unassigned codepoints pass
                continue;
            }

            // Class-based validation
            if ($isIdentifier) {
                if (!$cat->isIdentifierValid()) {
                    throw new PrecisException(
                        sprintf('Codepoint U+%04X not allowed in IdentifierClass', $cp)
                    );
                }
            } else {
                if (!$cat->isFreeformValid()) {
                    throw new PrecisException(
                        sprintf('Codepoint U+%04X not allowed in FreeformClass', $cp)
                    );
                }
            }

            // Contextual rules
            if ($cat->needsContextCheck()) {
                $this->checkContextRules($cp, $i, $codepoints);
            }
        }

        // Step 3: Additional mapping
        $string = $this->precis_mapString($string);

        // Step 4: Case mapping
        if ($this->applyCaseMapping()) {
            $string = mb_strtolower($string, 'UTF-8');
        }

        // Step 5: Normalization
        $normalized = Normalizer::normalize($string, $this->normalizationForm());
        if ($normalized === false) {
            throw new PrecisException('Normalization failed');
        }
        $string = $normalized;

        // Step 6: Post-processing (e.g. Nickname trim/collapse)
        $string = $this->postProcess($string);

        // Step 7: Bidi check
        if ($this->checkBidirectional()) {
            $this->precis_checkBidi($this->precis_stringToCodepoints($string));
        }

        // Step 8: Non-zero length
        if ($string === '') {
            throw new PrecisException('Result must not be empty');
        }

        return $string;
    }

    // ------------------------------------------------------------------
    // Width mapping
    // ------------------------------------------------------------------

    /**
     * Map fullwidth and halfwidth characters to their decomposed forms.
     *
     * Applies NFKD to characters in the Halfwidth and Fullwidth Forms
     * block (U+FF00–U+FFEF), then re-normalizes to NFC.
     */
    private function widthMap(string $string): string
    {
        $result = '';
        $length = mb_strlen($string, 'UTF-8');

        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($string, $i, 1, 'UTF-8');
            $cp = IntlChar::ord($char);

            if ($cp !== null && $cp >= 0xFF00 && $cp <= 0xFFEF) {
                // Decompose fullwidth/halfwidth to standard form
                $decomposed = Normalizer::normalize($char, Normalizer::FORM_KD);
                if ($decomposed !== false) {
                    $result .= $decomposed;
                    continue;
                }
            }
            $result .= $char;
        }

        // Re-normalize to NFC to recombine any decomposed sequences
        $normalized = Normalizer::normalize($result, Normalizer::FORM_C);
        return $normalized !== false ? $normalized : $result;
    }

    // ------------------------------------------------------------------
    // Additional mapping
    // ------------------------------------------------------------------

    private function precis_mapString(string $string): string
    {
        $result = '';
        $length = mb_strlen($string, 'UTF-8');

        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($string, $i, 1, 'UTF-8');
            $cp = IntlChar::ord($char);
            if ($cp === null) {
                throw new PrecisException('Invalid UTF-8 sequence');
            }

            $mapped = $this->mapCodepoint($cp);
            if ($mapped !== null) {
                $result .= $mapped;
            } else {
                $result .= $char;
            }
        }

        return $result;
    }

    // ------------------------------------------------------------------
    // Context rules (RFC 5892 Appendix A)
    // ------------------------------------------------------------------

    /**
     * Validate CONTEXTJ and CONTEXTO rules for a codepoint in context.
     *
     * @param int   $cp          The codepoint to validate.
     * @param int   $index       Position in the codepoints array.
     * @param int[] $codepoints  The full codepoints array.
     *
     * @throws PrecisException
     */
    private function checkContextRules(int $cp, int $index, array $codepoints): void
    {
        switch ($cp) {
            // CONTEXTJ: ZERO WIDTH NON-JOINER (U+200C)
            case 0x200C:
                if ($this->isAfterVirama($index, $codepoints)) {
                    return;
                }
                if ($this->isInJoiningContext($index, $codepoints)) {
                    return;
                }
                throw new PrecisException(
                    'ZERO WIDTH NON-JOINER not in valid joining context'
                );

                // CONTEXTJ: ZERO WIDTH JOINER (U+200D)
            case 0x200D:
                if ($this->isAfterVirama($index, $codepoints)) {
                    return;
                }
                throw new PrecisException(
                    'ZERO WIDTH JOINER not after virama'
                );

                // CONTEXTO: MIDDLE DOT (U+00B7) — between 'l' characters only
            case 0x00B7:
                if ($index > 0
                    && $index < count($codepoints) - 1
                    && $codepoints[$index - 1] === 0x006C
                    && $codepoints[$index + 1] === 0x006C) {
                    return;
                }
                throw new PrecisException(
                    'MIDDLE DOT not between U+006C (l) characters'
                );

                // CONTEXTO: GREEK LOWER NUMERAL SIGN (U+0375)
            case 0x0375:
                if ($index < count($codepoints) - 1
                    && IntlChar::getIntPropertyValue(
                        $codepoints[$index + 1],
                        IntlChar::PROPERTY_SCRIPT
                    ) === self::USCRIPT_GREEK) {
                    return;
                }
                throw new PrecisException(
                    'GREEK LOWER NUMERAL SIGN not followed by Greek character'
                );

                // CONTEXTO: HEBREW PUNCTUATION GERESH (U+05F3)
            case 0x05F3:
                // CONTEXTO: HEBREW PUNCTUATION GERSHAYIM (U+05F4)
            case 0x05F4:
                if ($index > 0
                    && IntlChar::getIntPropertyValue(
                        $codepoints[$index - 1],
                        IntlChar::PROPERTY_SCRIPT
                    ) === self::USCRIPT_HEBREW) {
                    return;
                }
                throw new PrecisException(
                    sprintf('Hebrew punctuation U+%04X not preceded by Hebrew character', $cp)
                );

                // CONTEXTO: KATAKANA MIDDLE DOT (U+30FB)
            case 0x30FB:
                foreach ($codepoints as $other) {
                    $script = IntlChar::getIntPropertyValue($other, IntlChar::PROPERTY_SCRIPT);
                    if ($script === self::USCRIPT_HIRAGANA
                        || $script === self::USCRIPT_KATAKANA
                        || $script === self::USCRIPT_HAN) {
                        return;
                    }
                }
                throw new PrecisException(
                    'KATAKANA MIDDLE DOT without Hiragana/Katakana/Han character in string'
                );

                // CONTEXTO: ARABIC-INDIC DIGITS (U+0660–U+0669)
            case 0x0660: case 0x0661: case 0x0662: case 0x0663: case 0x0664:
            case 0x0665: case 0x0666: case 0x0667: case 0x0668: case 0x0669:
                foreach ($codepoints as $other) {
                    if ($other >= 0x06F0 && $other <= 0x06F9) {
                        throw new PrecisException(
                            'Arabic-Indic and Extended Arabic-Indic digits cannot coexist'
                        );
                    }
                }
                return;

                // CONTEXTO: EXTENDED ARABIC-INDIC DIGITS (U+06F0–U+06F9)
            case 0x06F0: case 0x06F1: case 0x06F2: case 0x06F3: case 0x06F4:
            case 0x06F5: case 0x06F6: case 0x06F7: case 0x06F8: case 0x06F9:
                foreach ($codepoints as $other) {
                    if ($other >= 0x0660 && $other <= 0x0669) {
                        throw new PrecisException(
                            'Extended Arabic-Indic and Arabic-Indic digits cannot coexist'
                        );
                    }
                }
                return;

            default:
                throw new PrecisException(
                    sprintf('No context rule defined for U+%04X', $cp)
                );
        }
    }

    /**
     * Check if the codepoint at $index is preceded by a virama.
     *
     * A virama is a character with Canonical_Combining_Class = 9
     * (Virama).
     */
    private function isAfterVirama(int $index, array $codepoints): bool
    {
        if ($index === 0) {
            return false;
        }
        $ccc = IntlChar::getIntPropertyValue(
            $codepoints[$index - 1],
            IntlChar::PROPERTY_CANONICAL_COMBINING_CLASS
        );
        return $ccc === 9; // Virama
    }

    /**
     * Check if ZWNJ is in a valid joining context per RFC 5892.
     *
     * Pattern: (Joining_Type:{L,D})(Joining_Type:T)*ZWNJ(Joining_Type:T)*(Joining_Type:{R,D})
     */
    private function isInJoiningContext(int $index, array $codepoints): bool
    {
        // Look backward for L or D (skipping T)
        $foundLeft = false;
        for ($i = $index - 1; $i >= 0; $i--) {
            $jt = IntlChar::getIntPropertyValue(
                $codepoints[$i],
                IntlChar::PROPERTY_JOINING_TYPE
            );
            if ($jt === 4 /* LEFT_JOINING */ || $jt === 2 /* DUAL_JOINING */) {
                $foundLeft = true;
                break;
            }
            if ($jt !== 5 /* TRANSPARENT */) {
                break;
            }
        }

        if (!$foundLeft) {
            return false;
        }

        // Look forward for R or D (skipping T)
        for ($i = $index + 1; $i < count($codepoints); $i++) {
            $jt = IntlChar::getIntPropertyValue(
                $codepoints[$i],
                IntlChar::PROPERTY_JOINING_TYPE
            );
            if ($jt === 3 /* RIGHT_JOINING */ || $jt === 2 /* DUAL_JOINING */) {
                return true;
            }
            if ($jt !== 5 /* TRANSPARENT */) {
                return false;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Bidi check (RFC 5893)
    // ------------------------------------------------------------------

    /**
     * RFC 5893 Bidi Rule — more nuanced than RFC 3454 Section 6.
     *
     * @param int[] $codepoints
     *
     * @throws PrecisException
     */
    private function precis_checkBidi(array $codepoints): void
    {
        if (count($codepoints) === 0) {
            return;
        }

        $firstDir = IntlChar::charDirection($codepoints[0]);

        // Rule 1: first character must be L, R, or AL
        $isRtl = ($firstDir === IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT
            || $firstDir === IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT_ARABIC);
        $isLtr = ($firstDir === IntlChar::CHAR_DIRECTION_LEFT_TO_RIGHT);

        if (!$isRtl && !$isLtr) {
            throw new PrecisException(
                'Bidi Rule 1: first character must be L, R, or AL'
            );
        }

        if ($isRtl) {
            $this->checkBidiRtl($codepoints);
        } else {
            $this->checkBidiLtr($codepoints);
        }
    }

    /**
     * RTL label bidi rules (RFC 5893 Rules 2–4).
     *
     * @throws PrecisException
     */
    private function checkBidiRtl(array $codepoints): void
    {
        // Allowed bidi types for RTL
        static $allowed = [
            IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT => true,
            IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT_ARABIC => true,
            IntlChar::CHAR_DIRECTION_ARABIC_NUMBER => true,
            IntlChar::CHAR_DIRECTION_EUROPEAN_NUMBER => true,
            IntlChar::CHAR_DIRECTION_EUROPEAN_NUMBER_SEPARATOR => true,
            IntlChar::CHAR_DIRECTION_COMMON_NUMBER_SEPARATOR => true,
            IntlChar::CHAR_DIRECTION_EUROPEAN_NUMBER_TERMINATOR => true,
            IntlChar::CHAR_DIRECTION_OTHER_NEUTRAL => true,
            IntlChar::CHAR_DIRECTION_BOUNDARY_NEUTRAL => true,
            IntlChar::CHAR_DIRECTION_DIR_NON_SPACING_MARK => true,
        ];

        $hasEN = false;
        $hasAN = false;

        // Rule 2: only allowed types
        foreach ($codepoints as $cp) {
            $dir = IntlChar::charDirection($cp);
            if (!isset($allowed[$dir])) {
                throw new PrecisException(
                    sprintf('Bidi Rule 2: U+%04X has disallowed direction in RTL string', $cp)
                );
            }
            if ($dir === IntlChar::CHAR_DIRECTION_EUROPEAN_NUMBER) {
                $hasEN = true;
            }
            if ($dir === IntlChar::CHAR_DIRECTION_ARABIC_NUMBER) {
                $hasAN = true;
            }
        }

        // Rule 3: last non-NSM character must be R, AL, EN, or AN
        $lastSignificant = $this->lastNonNsmDirection($codepoints);
        if ($lastSignificant !== IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT
            && $lastSignificant !== IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT_ARABIC
            && $lastSignificant !== IntlChar::CHAR_DIRECTION_EUROPEAN_NUMBER
            && $lastSignificant !== IntlChar::CHAR_DIRECTION_ARABIC_NUMBER) {
            throw new PrecisException(
                'Bidi Rule 3: RTL string must end with R, AL, EN, or AN'
            );
        }

        // Rule 4: EN and AN cannot coexist
        if ($hasEN && $hasAN) {
            throw new PrecisException(
                'Bidi Rule 4: RTL string cannot contain both EN and AN'
            );
        }
    }

    /**
     * LTR label bidi rules (RFC 5893 Rules 5–6).
     *
     * @throws PrecisException
     */
    private function checkBidiLtr(array $codepoints): void
    {
        // Allowed bidi types for LTR
        static $allowed = [
            IntlChar::CHAR_DIRECTION_LEFT_TO_RIGHT => true,
            IntlChar::CHAR_DIRECTION_EUROPEAN_NUMBER => true,
            IntlChar::CHAR_DIRECTION_EUROPEAN_NUMBER_SEPARATOR => true,
            IntlChar::CHAR_DIRECTION_COMMON_NUMBER_SEPARATOR => true,
            IntlChar::CHAR_DIRECTION_EUROPEAN_NUMBER_TERMINATOR => true,
            IntlChar::CHAR_DIRECTION_OTHER_NEUTRAL => true,
            IntlChar::CHAR_DIRECTION_BOUNDARY_NEUTRAL => true,
            IntlChar::CHAR_DIRECTION_DIR_NON_SPACING_MARK => true,
        ];

        // Rule 5: only allowed types
        foreach ($codepoints as $cp) {
            $dir = IntlChar::charDirection($cp);
            if (!isset($allowed[$dir])) {
                throw new PrecisException(
                    sprintf('Bidi Rule 5: U+%04X has disallowed direction in LTR string', $cp)
                );
            }
        }

        // Rule 6: last non-NSM character must be L or EN
        $lastSignificant = $this->lastNonNsmDirection($codepoints);
        if ($lastSignificant !== IntlChar::CHAR_DIRECTION_LEFT_TO_RIGHT
            && $lastSignificant !== IntlChar::CHAR_DIRECTION_EUROPEAN_NUMBER) {
            throw new PrecisException(
                'Bidi Rule 6: LTR string must end with L or EN'
            );
        }
    }

    /**
     * Find the bidi direction of the last non-NSM character.
     */
    private function lastNonNsmDirection(array $codepoints): int
    {
        for ($i = count($codepoints) - 1; $i >= 0; $i--) {
            $dir = IntlChar::charDirection($codepoints[$i]);
            if ($dir !== IntlChar::CHAR_DIRECTION_DIR_NON_SPACING_MARK) {
                return $dir;
            }
        }
        // All NSM — return the direction of the first (which would have
        // failed Rule 1 already, but be safe)
        return IntlChar::charDirection($codepoints[0]);
    }

    // ------------------------------------------------------------------
    // Utility
    // ------------------------------------------------------------------

    /**
     * @return int[]
     */
    private function precis_stringToCodepoints(string $string): array
    {
        $codepoints = [];
        $length = mb_strlen($string, 'UTF-8');
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($string, $i, 1, 'UTF-8');
            $cp = IntlChar::ord($char);
            if ($cp === null) {
                throw new PrecisException('Invalid UTF-8 sequence');
            }
            $codepoints[] = $cp;
        }
        return $codepoints;
    }
}
