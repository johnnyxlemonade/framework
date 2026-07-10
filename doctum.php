<?php

declare(strict_types=1);

use Doctum\Doctum;
use Doctum\RemoteRepository\GitHubRemoteRepository;
use Symfony\Component\Finder\Finder;

$root = __DIR__;

$iterator = Finder::create()
    ->files()
    ->name('*.php')
    ->in($root . '/src');

return new Doctum($iterator, [
    'title' => 'Lemonade Framework API',
    'build_dir' => $root . '/build/docs',
    'cache_dir' => $root . '/build/doctum-cache',
    'source_dir' => $root,
    'default_opened_level' => 2,
]);
