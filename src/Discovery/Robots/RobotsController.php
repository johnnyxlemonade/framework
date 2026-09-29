<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Robots;

use Lemonade\Framework\Http\HttpStatus;
use Lemonade\Framework\Http\Response\Responses;
use Psr\Http\Message\ResponseInterface;

final class RobotsController
{
    public function __construct(
        private readonly RobotsTxtGenerator $generator,
        private readonly Responses $responses,
    ) {
    }

    public function index(): ResponseInterface
    {
        return $this->responses->text(
            $this->generator->generate(),
            HttpStatus::OK->value,
        );
    }
}
