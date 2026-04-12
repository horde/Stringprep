# Upgrading from lib/ (Znerol) to src/ (Horde\Stringprep)

## Overview

The `lib/` path wraps the bundled `znerol/php-stringprep` library, which
is unmaintained and frozen to Unicode 3.2. The `src/` path provides
native implementations backed by `ext-intl` with no bundled vendor code.

Both paths can coexist — switching is a class-name change per call site.

## Quick reference

| lib/ (old) | src/ (new) |
|---|---|
| `Horde_Stringprep::autoload()` | Not needed — PSR-4 autoloading |
| `new Znerol\Component\Stringprep\Profile\SASLprep()` | `new Horde\Stringprep\SaslPrepProfile()` |
| `Znerol\Component\Stringprep\Profile::MODE_QUERY` | `Horde\Stringprep\ProfileInterface::MODE_QUERY` |
| `Znerol\Component\Stringprep\Profile::MODE_STORE` | `Horde\Stringprep\ProfileInterface::MODE_STORE` |
| `Znerol\Component\Stringprep\ProfileException` | `Horde\Stringprep\SaslPrepException` |

## Before (lib/)

```php
Horde_Stringprep::autoload();
$saslprep = new Znerol\Component\Stringprep\Profile\SASLprep();

try {
    $username = $saslprep->apply(
        $username,
        'UTF-8',
        Znerol\Component\Stringprep\Profile::MODE_QUERY
    );
    $password = $saslprep->apply(
        $password,
        'UTF-8',
        Znerol\Component\Stringprep\Profile::MODE_STORE
    );
} catch (Znerol\Component\Stringprep\ProfileException $e) {
    // handle error
}
```

## After (src/)

```php
use Horde\Stringprep\ProfileInterface;
use Horde\Stringprep\SaslPrepException;
use Horde\Stringprep\SaslPrepProfile;

$saslprep = new SaslPrepProfile();

try {
    $username = $saslprep->apply($username, ProfileInterface::MODE_QUERY);
    $password = $saslprep->apply($password, ProfileInterface::MODE_STORE);
} catch (SaslPrepException $e) {
    // handle error
}
```

## API differences

### Signature change

The `$encoding` parameter is gone. The `src/` implementation only accepts
UTF-8 (which is what every caller already passed).

```
# lib/ — three parameters
$saslprep->apply($string, 'UTF-8', $mode);

# src/ — two parameters
$saslprep->apply($string, $mode);
```

### No autoload step

The `Horde_Stringprep::autoload()` call is not needed. All `src/` classes
use PSR-4 autoloading via `Horde\Stringprep\` → `src/`.

### Case folding fix

The `lib/` SASLprep incorrectly applies B.2 case folding. RFC 4013 does
not specify case folding for SASLprep. The `src/` implementation follows
the RFC correctly — uppercase characters in passwords are preserved.

If your application relied on the old case-folding behaviour, passwords
containing uppercase characters will compare differently after upgrading.

## Profile name mapping

| lib/ (Znerol class) | src/ (Horde class) | RFC |
|---|---|---|
| `Profile\SASLprep` | `SaslPrepProfile` | RFC 4013 |
| `Profile\Nameprep` | `NameprepProfile` | RFC 3491 |
| `Profile\ISCSI` | `IscsiProfile` | RFC 3722 |
| `Profile\Nodeprep` | `NodeprepProfile` | RFC 6122 App A |
| `Profile\Resourceprep` | `ResourceprepProfile` | RFC 6122 App B |
| `Profile\PolicyMIB` | `PolicyMibProfile` | RFC 4011 |
| `Profile\Trace` | `TraceProfile` | RFC 4505 |

All `src/` profiles implement `Horde\Stringprep\ProfileInterface`.

## PRECIS profiles (new)

The `src/` path also provides PRECIS profiles (RFC 7564), the modern
successor to stringprep. These are entirely new — there is no lib/
equivalent.

| Class | RFC | Replaces |
|---|---|---|
| `UsernameCaseMappedProfile` | RFC 8265 | SASLprep for usernames |
| `UsernameCasePreservedProfile` | RFC 8265 | SASLprep for usernames |
| `OpaqueStringProfile` | RFC 8265 | SASLprep for passwords |
| `NicknameProfile` | RFC 8266 | (new) |

PRECIS profiles throw `Horde\Stringprep\PrecisException`.
