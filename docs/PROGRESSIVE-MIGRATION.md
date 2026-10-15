# Progressive data migration

Pressundo can read old storage while new code is introduced field by field.

## Strategies

### preserve

Default. If legacy storage exists and the new target does not, the legacy value remains the source of truth. No automatic copy is made.

```php
$options = pu_options('new_options', [
    'migrate_from' => 'old_options',
    'migration'    => 'preserve',
]);
```

### lazy

Reads legacy when the modern target is missing, transforms if needed, then writes the value once to the modern target.

```php
$value = pu_get('new_key', null, [
    'migrate_from' => 'old_key',
    'migration'    => 'lazy',
]);
```

### copy

Writes the modern target and, when legacy storage already exists, synchronizes the legacy value for rollback/backward compatibility. If the legacy synchronization fails, the modern write is rolled back.

```php
pu_set('new_key', $value, [
    'migrate_from' => 'old_key',
    'migration'    => 'copy',
]);
```

## Transform callbacks

```php
$value = pu_get('enabled', false, [
    'migrate_from' => 'old_enabled',
    'migration'    => 'lazy',
    'from_legacy'  => static fn ($value) => (bool) $value,
]);
```

Available transformation keys:

```text
from_legacy
transform      (alias used on reads)
to_legacy
sanitize       (writes)
```

Global filters are also available for migration transforms.

## Option namespaces

```php
$settings = pu_options('new_theme_settings', [
    'migrate_from' => 'old_theme_settings',
    'migration'    => 'preserve',
]);

$logo = $settings->get('logo');
```

A per-field `migrate_from` can override the source path:

```php
$title = $settings->get('site_title', '', [
    'migrate_from' => 'old_title',
]);
```

## Meta namespaces

```php
$meta = pu_meta($postId, '_new_page_settings', [
    'migrate_from' => '_old_page_settings',
    'migration'    => 'lazy',
]);
```

Pressundo does not implement a destructive automatic `move` strategy in Free. Deleting old storage should be an explicit release decision after verification.
