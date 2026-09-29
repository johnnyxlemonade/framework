<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Value;

/** Selects whether resize preserves the source aspect ratio or deliberately stretches it. */
enum ResizeMode
{
    case Contain;
    case Stretch;
}
