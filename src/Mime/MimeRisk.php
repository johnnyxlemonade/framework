<?php

declare(strict_types=1);

namespace Lemonade\Framework\Mime;

/**
 * Classifies a known format's handling risk without making an authorization decision.
 */
enum MimeRisk
{
    case Passive;
    case Container;
    case ActiveContent;
    case Executable;
}
