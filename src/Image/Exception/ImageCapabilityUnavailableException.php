<?php

declare(strict_types=1);

namespace Lemonade\Framework\Image\Exception;

/** Signals that GD or the codec required by an otherwise supported format is unavailable at runtime. */
final class ImageCapabilityUnavailableException extends ImageException
{
}
