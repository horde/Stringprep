# Horde Stringprep

Unicode string preparation for authentication and protocol identifiers.
Provides both legacy **RFC 3454 Stringprep** and modern **RFC 7564 PRECIS**
profiles, backed by PHP's `ext-intl` with no bundled vendor dependencies.

Requires PHP 8.1+ and `ext-intl`.

## Stringprep profiles (RFC 3454)

Frozen to Unicode 3.2. Used by older protocol specifications.

| Profile | Class | RFC | Purpose |
|---|---|---|---|
| SASLprep | `SaslPrepProfile` | 4013 | SASL usernames and passwords |
| Nameprep | `NameprepProfile` | 3491 | Internationalized domain names |
| iSCSI | `IscsiProfile` | 3722 | iSCSI naming |
| Nodeprep | `NodeprepProfile` | 6122 App A | XMPP node identifiers |
| Resourceprep | `ResourceprepProfile` | 6122 App B | XMPP resource identifiers |
| PolicyMIB | `PolicyMibProfile` | 4011 | Policy-based management |
| Trace | `TraceProfile` | 4505 | Anonymous SASL |

## PRECIS profiles (RFC 7564)

Unicode-version-agile. Recommended for new implementations.

| Profile | Class | RFC | Purpose |
|---|---|---|---|
| UsernameCaseMapped | `UsernameCaseMappedProfile` | 8265 | Usernames (case-insensitive) |
| UsernameCasePreserved | `UsernameCasePreservedProfile` | 8265 | Usernames (case-sensitive) |
| OpaqueString | `OpaqueStringProfile` | 8265 | Passwords |
| Nickname | `NicknameProfile` | 8266 | Chat/display nicknames |

## Usage

All profiles implement `ProfileInterface` with the same `apply()` method:

```php
use Horde\Stringprep\SaslPrepProfile;
use Horde\Stringprep\OpaqueStringProfile;
use Horde\Stringprep\ProfileInterface;

// Legacy stringprep
$saslprep = new SaslPrepProfile();
$username = $saslprep->apply($input, ProfileInterface::MODE_QUERY);

// Modern PRECIS
$opaque = new OpaqueStringProfile();
$password = $opaque->apply($input, ProfileInterface::MODE_STORE);
```

`MODE_STORE` (default) rejects unassigned codepoints.
`MODE_QUERY` allows them.

See [doc/UPGRADING.md](doc/UPGRADING.md) for migration from the bundled
`lib/` Znerol implementation.
