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
 * PRECIS derived property values (RFC 7564 Section 8).
 *
 * Classifies Unicode codepoints for use in PRECIS string classes.
 * Uses IntlChar properties from the current ICU/Unicode version —
 * PRECIS is Unicode-version-agile by design.
 *
 * The decision tree follows RFC 7564 Section 8 exactly, with the
 * Exceptions table from RFC 5892 Section 2.6.
 */
enum PrecisCategory
{
    /** Valid in any PRECIS string class. */
    case Pvalid;

    /** Valid in FreeformClass only (ID_DIS in IdentifierClass). */
    case FreePval;

    /** Prohibited in IdentifierClass, valid in FreeformClass. */
    case IdDis;

    /** Prohibited in any PRECIS string class. */
    case Disallowed;

    /** Allowed only if joining context rule is satisfied. */
    case ContextJ;

    /** Allowed only if other context rule is satisfied. */
    case ContextO;

    /** Unassigned in current Unicode version. */
    case Unassigned;

    /**
     * RFC 5892 Section 2.6 — Exceptions table.
     *
     * Codepoints that need explicit classification overriding the
     * general algorithm. BackwardCompatible (Section 2.7) is empty.
     *
     * @var array<int, PrecisCategory>
     */
    private const EXCEPTIONS = [
        // PVALID — would otherwise be DISALLOWED
        0x00DF => 'Pvalid',      // LATIN SMALL LETTER SHARP S
        0x03C2 => 'Pvalid',      // GREEK SMALL LETTER FINAL SIGMA
        0x06FD => 'Pvalid',      // ARABIC SIGN SINDHI AMPERSAND
        0x06FE => 'Pvalid',      // ARABIC SIGN SINDHI POSTPOSITION MEN
        0x0F0B => 'Pvalid',      // TIBETAN MARK INTERSYLLABIC TSHEG
        0x3007 => 'Pvalid',      // IDEOGRAPHIC NUMBER ZERO

        // CONTEXTO — would otherwise be DISALLOWED or PVALID
        0x00B7 => 'ContextO',    // MIDDLE DOT
        0x0375 => 'ContextO',    // GREEK LOWER NUMERAL SIGN (KERAIA)
        0x05F3 => 'ContextO',    // HEBREW PUNCTUATION GERESH
        0x05F4 => 'ContextO',    // HEBREW PUNCTUATION GERSHAYIM
        0x30FB => 'ContextO',    // KATAKANA MIDDLE DOT
        0x0660 => 'ContextO',    // ARABIC-INDIC DIGIT ZERO
        0x0661 => 'ContextO',    // ARABIC-INDIC DIGIT ONE
        0x0662 => 'ContextO',    // ARABIC-INDIC DIGIT TWO
        0x0663 => 'ContextO',    // ARABIC-INDIC DIGIT THREE
        0x0664 => 'ContextO',    // ARABIC-INDIC DIGIT FOUR
        0x0665 => 'ContextO',    // ARABIC-INDIC DIGIT FIVE
        0x0666 => 'ContextO',    // ARABIC-INDIC DIGIT SIX
        0x0667 => 'ContextO',    // ARABIC-INDIC DIGIT SEVEN
        0x0668 => 'ContextO',    // ARABIC-INDIC DIGIT EIGHT
        0x0669 => 'ContextO',    // ARABIC-INDIC DIGIT NINE
        0x06F0 => 'ContextO',    // EXTENDED ARABIC-INDIC DIGIT ZERO
        0x06F1 => 'ContextO',    // EXTENDED ARABIC-INDIC DIGIT ONE
        0x06F2 => 'ContextO',    // EXTENDED ARABIC-INDIC DIGIT TWO
        0x06F3 => 'ContextO',    // EXTENDED ARABIC-INDIC DIGIT THREE
        0x06F4 => 'ContextO',    // EXTENDED ARABIC-INDIC DIGIT FOUR
        0x06F5 => 'ContextO',    // EXTENDED ARABIC-INDIC DIGIT FIVE
        0x06F6 => 'ContextO',    // EXTENDED ARABIC-INDIC DIGIT SIX
        0x06F7 => 'ContextO',    // EXTENDED ARABIC-INDIC DIGIT SEVEN
        0x06F8 => 'ContextO',    // EXTENDED ARABIC-INDIC DIGIT EIGHT
        0x06F9 => 'ContextO',    // EXTENDED ARABIC-INDIC DIGIT NINE

        // DISALLOWED — would otherwise be PVALID
        0x0640 => 'Disallowed',  // ARABIC TATWEEL
        0x07FA => 'Disallowed',  // NKO LAJANYALAN
        0x302E => 'Disallowed',  // HANGUL SINGLE DOT TONE MARK
        0x302F => 'Disallowed',  // HANGUL DOUBLE DOT TONE MARK
        0x3031 => 'Disallowed',  // VERTICAL KANA REPEAT MARK
        0x3032 => 'Disallowed',  // VERTICAL KANA REPEAT WITH VOICED SOUND MARK
        0x3033 => 'Disallowed',  // VERTICAL KANA REPEAT MARK UPPER HALF
        0x3034 => 'Disallowed',  // VERTICAL KANA REPEAT WITH VOICED SOUND MARK UPPER HALF
        0x3035 => 'Disallowed',  // VERTICAL KANA REPEAT MARK LOWER HALF
        0x303B => 'Disallowed',  // VERTICAL IDEOGRAPHIC ITERATION MARK
    ];

    /**
     * Classify a Unicode codepoint per the PRECIS decision tree.
     *
     * Follows RFC 7564 Section 8 exactly.
     */
    public static function classify(int $codepoint): self
    {
        // 1. Exceptions (RFC 5892 Section 2.6)
        if (isset(self::EXCEPTIONS[$codepoint])) {
            return self::from(self::EXCEPTIONS[$codepoint]);
        }

        // 2. BackwardCompatible (RFC 5892 Section 2.7) — currently empty

        // 3. Unassigned
        if (!IntlChar::isdefined($codepoint)) {
            return self::Unassigned;
        }

        // 4. ASCII7 (U+0021–U+007E)
        if ($codepoint >= 0x0021 && $codepoint <= 0x007E) {
            return self::Pvalid;
        }

        // 5. JoinControl
        if (IntlChar::hasBinaryProperty($codepoint, IntlChar::PROPERTY_JOIN_CONTROL)) {
            return self::ContextJ;
        }

        // 6. OldHangulJamo
        $hst = IntlChar::getIntPropertyValue($codepoint, IntlChar::PROPERTY_HANGUL_SYLLABLE_TYPE);
        if ($hst === IntlChar::HST_LEADING_JAMO
            || $hst === IntlChar::HST_VOWEL_JAMO
            || $hst === IntlChar::HST_TRAILING_JAMO) {
            return self::Disallowed;
        }

        // 7. PrecisIgnorableProperties
        if (IntlChar::hasBinaryProperty($codepoint, IntlChar::PROPERTY_DEFAULT_IGNORABLE_CODE_POINT)
            || IntlChar::hasBinaryProperty($codepoint, IntlChar::PROPERTY_NONCHARACTER_CODE_POINT)) {
            return self::Disallowed;
        }

        // 8. Controls (General_Category Cc)
        $type = IntlChar::charType($codepoint);
        if ($type === IntlChar::CHAR_CATEGORY_CONTROL_CHAR) {
            return self::Disallowed;
        }

        // 9. HasCompat — NFKC maps to a different string
        if (self::hasCompatMapping($codepoint)) {
            return self::IdDis;
        }

        // 10. LetterDigits (Ll, Lu, Lo, Nd, Lm, Mn, Mc)
        if (in_array($type, [
            IntlChar::CHAR_CATEGORY_LOWERCASE_LETTER,
            IntlChar::CHAR_CATEGORY_UPPERCASE_LETTER,
            IntlChar::CHAR_CATEGORY_OTHER_LETTER,
            IntlChar::CHAR_CATEGORY_DECIMAL_DIGIT_NUMBER,
            IntlChar::CHAR_CATEGORY_MODIFIER_LETTER,
            IntlChar::CHAR_CATEGORY_NON_SPACING_MARK,
            IntlChar::CHAR_CATEGORY_COMBINING_SPACING_MARK,
        ], true)) {
            return self::Pvalid;
        }

        // 11. OtherLetterDigits (Lt, Nl, No, Me)
        if (in_array($type, [
            IntlChar::CHAR_CATEGORY_TITLECASE_LETTER,
            IntlChar::CHAR_CATEGORY_LETTER_NUMBER,
            IntlChar::CHAR_CATEGORY_OTHER_NUMBER,
            IntlChar::CHAR_CATEGORY_ENCLOSING_MARK,
        ], true)) {
            return self::IdDis;
        }

        // 12. Spaces (Zs)
        if ($type === IntlChar::CHAR_CATEGORY_SPACE_SEPARATOR) {
            return self::IdDis;
        }

        // 13. Symbols (Sm, Sc, Sk, So)
        if (in_array($type, [
            IntlChar::CHAR_CATEGORY_MATH_SYMBOL,
            IntlChar::CHAR_CATEGORY_CURRENCY_SYMBOL,
            IntlChar::CHAR_CATEGORY_MODIFIER_SYMBOL,
            IntlChar::CHAR_CATEGORY_OTHER_SYMBOL,
        ], true)) {
            return self::IdDis;
        }

        // 14. Punctuation (Pc, Pd, Ps, Pe, Pi, Pf, Po)
        if (in_array($type, [
            IntlChar::CHAR_CATEGORY_CONNECTOR_PUNCTUATION,
            IntlChar::CHAR_CATEGORY_DASH_PUNCTUATION,
            IntlChar::CHAR_CATEGORY_START_PUNCTUATION,
            IntlChar::CHAR_CATEGORY_END_PUNCTUATION,
            IntlChar::CHAR_CATEGORY_INITIAL_PUNCTUATION,
            IntlChar::CHAR_CATEGORY_FINAL_PUNCTUATION,
            IntlChar::CHAR_CATEGORY_OTHER_PUNCTUATION,
        ], true)) {
            return self::IdDis;
        }

        // 15. Catch-all
        return self::Disallowed;
    }

    /**
     * Check if a codepoint is allowed in IdentifierClass.
     */
    public function isIdentifierValid(): bool
    {
        return match ($this) {
            self::Pvalid, self::ContextJ, self::ContextO => true,
            default => false,
        };
    }

    /**
     * Check if a codepoint is allowed in FreeformClass.
     */
    public function isFreeformValid(): bool
    {
        return match ($this) {
            self::Pvalid, self::FreePval, self::IdDis,
            self::ContextJ, self::ContextO => true,
            default => false,
        };
    }

    /**
     * Check if a codepoint requires contextual validation.
     */
    public function needsContextCheck(): bool
    {
        return $this === self::ContextJ || $this === self::ContextO;
    }

    /**
     * Check if NFKC maps a codepoint to a different string.
     */
    private static function hasCompatMapping(int $codepoint): bool
    {
        $char = IntlChar::chr($codepoint);
        if ($char === null) {
            return false;
        }
        $nfkc = Normalizer::normalize($char, Normalizer::FORM_KC);
        return $nfkc !== false && $nfkc !== $char;
    }

    /**
     * Resolve an enum case from the exceptions table string value.
     */
    private static function from(string $name): self
    {
        return match ($name) {
            'Pvalid' => self::Pvalid,
            'FreePval' => self::FreePval,
            'IdDis' => self::IdDis,
            'Disallowed' => self::Disallowed,
            'ContextJ' => self::ContextJ,
            'ContextO' => self::ContextO,
            'Unassigned' => self::Unassigned,
        };
    }
}
