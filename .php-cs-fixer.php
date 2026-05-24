<?php

/**
 * Indent + line ending mirror `[*]` end_of_line and `[{*.php,...}]` indent_style
 * from .editorconfig. PHP-CS-Fixer has no native editorconfig integration —
 * keep these in sync if the editorconfig section changes.
 *
 * `insert_final_newline` and `trim_trailing_whitespace` from .editorconfig are
 * already enforced by @PER-CS2.0 (single_blank_line_at_eof,
 * no_trailing_whitespace). `max_line_length` is advisory only; PHP-CS-Fixer
 * has no fixer that wraps long lines.
 */
return (new PhpCsFixer\Config())
    ->setIndent("\t")
    ->setLineEnding("\n")
    ->setRules([
        '@PER-CS2.0' => true,
        '@PER-CS2.0:risky' => false,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'trailing_comma_in_multiline' => true,
        'phpdoc_scalar' => true,
        'unary_operator_spaces' => true,
        'binary_operator_spaces' => true,
        'blank_line_before_statement' => ['statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try']],
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_var_without_name' => true,
        'class_attributes_separation' => ['elements' => ['method' => 'one']],
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline', 'keep_multiple_spaces_after_comma' => true],
        'single_trait_insert_per_statement' => true,
    ])
    ->setRiskyAllowed(false)
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in([__DIR__.'/app', __DIR__.'/config', __DIR__.'/database', __DIR__.'/routes', __DIR__.'/tests', __DIR__.'/bootstrap'])
            ->notPath('cache')
            ->notPath('bootstrap/cache')
            ->name('*.php')
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
    );
