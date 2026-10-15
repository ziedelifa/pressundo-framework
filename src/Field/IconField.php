<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

final class IconField extends TextField
{
    public function type(): string { return 'icon'; }
}
