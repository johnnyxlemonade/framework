<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Robots;

use Lemonade\Framework\Core\AbstractController;
use Lemonade\Framework\Http\HttpStatus;
use Psr\Http\Message\ResponseInterface;

final class RobotsController extends AbstractController
{
    public function __construct(
        private readonly RobotsTxtGenerator $generator,
    ) {}

    public function index(): ResponseInterface
    {
        return $this->response(
            $this->generator->generate(),
            HttpStatus::OK->value,
            'text/plain; charset=UTF-8',
        );
    }
}
