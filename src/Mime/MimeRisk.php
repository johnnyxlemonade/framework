<?php

declare(strict_types=1);

namespace Lemonade\Framework\Mime;

/**
 * Classifies a format for security policies that decide how detected content may be stored or delivered.
 */
enum MimeRisk: string
{
    case Passive = 'passive';
    case Container = 'container';
    case ActiveContent = 'active_content';
    case Executable = 'executable';
    case Unclassified = 'unclassified';
}
