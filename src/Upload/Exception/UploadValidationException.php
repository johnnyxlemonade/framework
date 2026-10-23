<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Exception;

/**
 * Reports client-controlled upload input that fails transport, policy, content, or quota validation.
 */
class UploadValidationException extends UploadException
{
}
