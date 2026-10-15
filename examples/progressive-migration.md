# Example — progressive storage migration

Start with legacy storage:

```php
$settings = pu_options('new_theme_settings', [
    'migrate_from' => 'legacy_theme_settings',
    'migration'    => 'preserve',
]);
```

Read normally:

```php
$heading = $settings->get('heading', 'Default heading');
```

Move only one field lazily when its data shape changes:

```php
$enabled = $settings->get('enabled', false, [
    'migrate_from' => 'show_feature',
    'migration'    => 'lazy',
    'from_legacy'  => static fn ($value): bool => in_array($value, [1, '1', true, 'true'], true),
]);
```

During a rollback-compatible release, synchronize writes to both stores:

```php
$settings->set('enabled', true, [
    'migrate_from' => 'show_feature',
    'migration'    => 'copy',
    'to_legacy'    => static fn (bool $value): string => $value ? '1' : '0',
]);
```
