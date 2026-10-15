# Security model

Pressundo applies security at both the **WordPress request layer** and the **field-value layer**.

The framework follows a secure-by-default approach: validation, sanitization, capability checks and request verification are handled server-side.

## Options pages

Options page saves include:

- capability checks;
- WordPress nonce verification;
- schema-aware sanitization;
- server-side validation;
- all-or-nothing saves when validation fails;
- concurrent-save revision checks.

Invalid values are not partially persisted.

## Modern and legacy metaboxes

Metabox saves include:

- `edit_post` capability checks;
- nonce verification;
- autosave guards;
- post-revision guards;
- schema-aware sanitization;
- server-side validation;
- concurrent-save revision checks.

## URL policy

URL and media URL fields accept only normal HTTP and HTTPS URLs.

Unsafe or unsupported schemes are rejected or removed, including:

```text
javascript:
ftp:
```

This prevents URL fields from being used as a bypass for unsafe protocols.

## Unknown field types

An unregistered field type cannot become a raw-data bypass.

Unknown values are processed by Pressundo's recursive fallback sanitizer with bounded:

- nesting depth;
- array sizes;
- item counts.

This provides a safe fallback while still allowing custom field types to define their own sanitization rules.

## Repeaters

Repeaters have a hard server-side limit of **250 rows by default**.

The limit can be adjusted using:

```php
pressundo_security_max_repeater_rows
```

Custom values are themselves bounded by the framework to prevent unsafe or unreasonably large submissions.

## Code, CSS and JavaScript fields

Code-oriented fields are designed to **store source text**, not execute it.

Pressundo:

- normalizes submitted values;
- sanitizes the surrounding field structure;
- escapes values when rendered in the admin;
- never evaluates stored PHP, CSS or JavaScript automatically.

Applications that later output stored CSS or JavaScript on the front end are responsible for applying an appropriate capability, trust and output policy for that use case.

## Legacy PHP execution

Compatibility with Vafpress does **not** automatically permit legacy PHP execution.

Arbitrary Vafpress callbacks and unsafe PHP template fallback includes are disabled by default.

Explicit opt-in is required for exceptional migration scenarios.

See [Vafpress migration](VAFPRESS-MIGRATION.md) for the available compatibility constants and their security implications.

## Security principles

Pressundo follows these principles:

- validate on the server;
- sanitize before persistence;
- escape on output;
- verify capabilities;
- verify nonces;
- reject unsafe URL schemes;
- bound recursive and repeated data;
- avoid automatic code execution;
- keep legacy execution paths disabled by default.
