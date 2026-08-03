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

$remoteRepository = new class(
    'johnnyxlemonade/framework',
    $root,
) extends GitHubRemoteRepository {
    public function getFileUrl($projectVersion, $relativePath, $line)
    {
        return parent::getFileUrl('master', $relativePath, $line);
    }
};

return new Doctum($iterator, [
    'title' => 'Lemonade Framework API',
    'language' => 'en',

    'build_dir' => $root . '/build/docs',
    'cache_dir' => $root . '/build/doctum-cache',

    'source_dir' => $root,
    'template_dirs' => [
        $root . '/doctum-theme',
    ],
    'remote_repository' => $remoteRepository,
    'theme' => 'lemonade-modern-local',
    'default_opened_level' => 2,

    'footer_link' => [
        'href' => 'https://github.com/johnnyxlemonade/framework',
        'rel' => 'noreferrer noopener',
        'target' => '_blank',
        'before_text' => 'Source code available',
        'link_text' => 'on GitHub',
        'after_text' => '',
    ],
]);
