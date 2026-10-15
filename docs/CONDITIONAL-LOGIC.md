# Conditional logic

Fields and sections support `show_if`, `hide_if` and `condition_relation`.

## Shorthand equality

```php
pu_text('tracking_id', [
    'show_if' => [
        'enable_tracking' => true,
    ],
]);
```

## Explicit rules

```php
pu_text('custom_width', [
    'show_if' => [
        ['field' => 'layout', 'operator' => '=', 'value' => 'custom'],
        ['field' => 'enabled', 'operator' => 'truthy'],
    ],
    'condition_relation' => 'and',
]);
```

## Operators

```text
=  ==  !=  <>
in  not_in
>  >=  <  <=
empty  not_empty
truthy  falsy
contains  not_contains
```

`condition_relation` accepts `and` or `or`.

Conditions are evaluated server-side for initial rendering and indexed client-side so a changed field only reevaluates dependent controls instead of scanning the whole page.

Hidden controls keep their stored value unless your application explicitly changes or removes it.
