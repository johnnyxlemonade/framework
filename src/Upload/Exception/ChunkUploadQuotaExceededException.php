<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Exception;

/**
 * Reports that a chunk session cannot reserve bounded temporary capacity without exceeding server policy.
 */
final class ChunkUploadQuotaExceededException extends UploadValidationException
{
}
