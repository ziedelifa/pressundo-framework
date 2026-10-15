# Vafpress compatibility and migration

## Goal

A supported legacy theme can remove the obsolete Vafpress runtime folder while keeping common public Vafpress code temporarily. Pressundo supplies a compatibility runtime, and the developer can migrate to the modern `pu_*` API progressively.

## Covered public compatibility surface

The audited runtime includes the common public APIs around:

```text
VP_Option
VP_Metabox
VP_Security
VP_Option_Control_Set
VP_Site_GoogleWebFont
VP_ShortcodeGenerator (safe facade)
vp_option()
vp_metabox()
common vp_get_* datasources
common WPAlchemy metabox accessors
```

The standard Vafpress field compatibility matrix covers 20/20 audited standard field types.

## Remove Vafpress

For a supported theme, the intended migration is:

1. Install and activate Pressundo Framework.
2. Remove the theme's bootstrap `require` for Vafpress.
3. Remove the obsolete `vafpress-framework/` directory.
4. Keep supported `VP_Option`, `VP_Metabox`, `vp_option()` and `vp_metabox()` calls temporarily.
5. Replace legacy declarations incrementally with modern `pu_*` code.
6. Keep the same storage keys when you want zero data movement.
7. Use progressive migration only when storage keys or shapes actually change.

## Mixing legacy and modern fields

A Vafpress-compatible options page may retain legacy fields while new Pressundo sections are introduced. Both use the same Pressundo renderer and the selected Air, Slate or Compact skin.

This makes migration a code-maintenance task rather than a per-client database migration project.

## Security boundary

Pressundo intentionally does **not** restore the unsafe parts of the old architecture by default.

Disabled by default:

```text
arbitrary whitelisted PHP datasource callbacks
unsafe fallback inclusion of unparseable legacy PHP templates
old generic AJAX execution architecture
old TinyMCE shortcode-generator runtime
undocumented internal VP_Control_Field_* coupling
```

Exceptional opt-ins exist in `wp-config.php`:

```php
define('PRESSUNDO_VAFPRESS_ALLOW_WHITELISTED_CALLBACKS', true);
define('PRESSUNDO_VAFPRESS_ALLOW_UNSAFE_TEMPLATE_INCLUDE', true);
```

Use them only as temporary migration aids on trusted code.

## Debug diagnostics

Advanced Vafpress migration diagnostics are hidden from normal Free UI. To expose them temporarily:

```php
define('PRESSUNDO_FRAMEWORK_DEBUG', true);
```

Do not leave debug diagnostics enabled on production unless you specifically need them.
