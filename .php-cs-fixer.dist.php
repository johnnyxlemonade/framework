<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->files()
    ->in([
        __DIR__ . '/src',
    ])
    ->append([
        __DIR__ . '/bin/lemonade',
    ])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ->setRules([
        '@PER-CS2.0' => true,

        'declare_strict_types' => true,
        'strict_param' => true,

        'array_syntax' => [
            'syntax' => 'short',
        ],

        'single_quote' => true,
        'no_unused_imports' => true,

        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => [
                'class',
                'function',
                'const',
            ],
        ],

        'trailing_comma_in_multiline' => [
            'elements' => [
                'arguments',
                'arrays',
                'match',
                'parameters',
            ],
        ],

        'binary_operator_spaces' => [
            'default' => 'single_space',
        ],

        'concat_space' => [
            'spacing' => 'one',
        ],

        'blank_line_after_opening_tag' => true,
        'blank_line_after_namespace' => true,
        'single_blank_line_at_eof' => true,
        'no_extra_blank_lines' => true,

        'no_superfluous_phpdoc_tags' => [
            'allow_mixed' => true,
        ],
    ]);
