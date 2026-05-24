<?php

/**
 * Derive PHP-CS-Fixer indent + line ending from .editorconfig so the two
 * configs stay in sync. PHP-CS-Fixer doesn't read .editorconfig natively;
 * this tiny parser pulls just the properties we need from the section that
 * matches *.php (handles the JetBrains brace-list export form) with a
 * fallback to the root `[*]` section.
 *
 * Adapted properties:
 *   - indent_style + indent_size → setIndent()
 *   - end_of_line               → setLineEnding()
 *
 * `insert_final_newline = true` and `trim_trailing_whitespace = true` are
 * already enforced by the @PER-CS2.0 ruleset (single_blank_line_at_eof,
 * no_trailing_whitespace). `max_line_length` is advisory only — PHP-CS-Fixer
 * has no fixer that wraps long lines.
 */
$editorconfig = (static function (): array {
    $path = __DIR__.'/.editorconfig';
    if (! is_file($path)) {
        return [];
    }

    $defaults = [];
    $phpOverrides = [];
    $bucket = null;

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || $line[0] === ';') {
            continue;
        }

        if (preg_match('/^\[(.+)\]$/', $line, $m)) {
            $section = $m[1];
            $bucket = match (true) {
                $section === '*' => 'default',
                str_contains($section, '*.php') => 'php',
                default => null,
            };

            continue;
        }

        if ($bucket === null || ! str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($bucket === 'default') {
            $defaults[$key] = $value;
        } else {
            $phpOverrides[$key] = $value;
        }
    }

    return $phpOverrides + $defaults;
})();

$indent = ($editorconfig['indent_style'] ?? 'space') === 'tab'
    ? "\t"
    : str_repeat(' ', (int) ($editorconfig['indent_size'] ?? 4));

$lineEnding = ($editorconfig['end_of_line'] ?? 'lf') === 'crlf' ? "\r\n" : "\n";

return (new PhpCsFixer\Config())
    ->setIndent($indent)
    ->setLineEnding($lineEnding)
    ->setRules([
        '@PER-CS2.0' => true,
        '@PER-CS2.0:risky' => false,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'not_operator_with_successor_space' => true,
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
