# Security model

Pressundo applies security at both the WordPress request layer and the field-value layer.

## Options pages

- capability check;
- WordPress nonce;
- schema-aware sanitization;
- server-side validation;
- all-or-nothing save on validation failure;
- concurrent-save revision check.

## Modern and legacy metaboxes

- `edit_post` capability;
- nonce verification;
- autosave and post-revision guards;
- schema-aware sanitization;
- concurrent-save revision check.

## URL policy

URL and media URL values use an HTTP/HTTPS-only policy. Unsafe schemes such as `javascript:` and unrelated protocols such as `ftp:` are removed/rejected.

## Unknown field types

An unregistered type cannot become a raw-data bypass. Unknown values pass through the recursive fallback sanitizer with bounded depth and item counts.

## Repeaters

Repeaters have a hard server-side maximum of 250 rows by default. The maximum can be changed with `pressundo_security_max_repeater_rows`, bounded to a safe range by the framework.

## Code, CSS and JavaScript fields

These controls are designed to store code text. Pressundo normalizes and escapes the values in the admin but does not evaluate or automatically execute them.

Applications that later print user-supplied CSS/JS on the front end are responsible for applying an appropriate capability and output policy for that use case.

## Legacy PHP execution

Arbitrary Vafpress callbacks and unsafe PHP template fallback includes are disabled by default. Compatibility registration does not imply execution permission.

See [Vafpress migration](VAFPRESS-MIGRATION.md) for the explicit opt-in constants.
