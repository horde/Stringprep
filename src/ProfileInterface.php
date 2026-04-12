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
 * Common interface for string preparation profiles.
 *
 * Covers both RFC 3454 stringprep profiles (via StringprepTrait) and
 * RFC 7564 PRECIS profiles (via PrecisTrait).
 */
interface ProfileInterface
{
    /**
     * Stored strings mode — unassigned codepoints (A.1) are prohibited.
     * Use for values being stored/committed (e.g. passwords).
     */
    public const MODE_STORE = 1;

    /**
     * Query strings mode — unassigned codepoints are allowed.
     * Use for values being looked up (e.g. usernames for comparison).
     */
    public const MODE_QUERY = 0;

    /**
     * Apply this stringprep profile to a UTF-8 string.
     *
     * @param string $string  Input UTF-8 string.
     * @param int    $mode    MODE_STORE or MODE_QUERY.
     *
     * @return string  The prepared string.
     *
     * @throws SaslPrepException  If the string fails validation.
     */
    public function apply(string $string, int $mode = self::MODE_STORE): string;
}
