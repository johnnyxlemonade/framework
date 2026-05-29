<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Robots;

use Lemonade\Framework\Core\Controller;
use Psr\Http\Message\ResponseInterface;

final class RobotsController extends Controller
{
    public function __construct(
        private readonly RobotsTxtGenerator $generator,
    ) {}

    public function index(): ResponseInterface
    {
        return $this->response(
            $this->generator->generate(),
            200,
            'text/plain; charset=UTF-8',
        );
    }
}
