<?php

declare(strict_types=1);

/*
 * PHP CS Fixer configuration for Laravel Modular DDD
 *
 * This configuration ensures consistent code style across the entire package
 * following PSR-12 standards with additional Laravel-specific rules.
 */

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->in(__DIR__ . '/examples')
    ->in(__DIR__ . '/scripts')
    ->name('*.php')
    ->exclude([
        'vendor',
        'coverage-report',
        '.phpunit.cache',
        'storage',
        'bootstrap/cache',
    ])
    ->notName([
        '*.blade.php',
        '_ide_helper.php',
        '_ide_helper_models.php',
        '.phpstorm.meta.php',
    ])
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

$config = new Config();

return $config
    ->setRules([
        // PSR-12 base rules
        '@PSR12' => true,
        '@PSR12:risky' => true,

        // PHP version compatibility
        '@PHP82Migration' => true,
        '@PHP82Migration:risky' => true,

        // Array formatting
        'array_syntax' => ['syntax' => 'short'],
        'array_indentation' => true,
        'normalize_index_brace' => true,
        'no_multiline_whitespace_around_double_arrow' => true,
        'no_trailing_comma_in_singleline_array' => true,
        'trim_array_spaces' => true,
        'whitespace_after_comma_in_array' => true,

        // Binary operators
        'binary_operator_spaces' => [
            'default' => 'single_space',
            'operators' => [
                '=' => 'single_space',
                '=>' => 'single_space',
                '??' => 'single_space',
            ],
        ],
        'concat_space' => ['spacing' => 'one'],
        'operator_linebreak' => ['only_booleans' => true],

        // Blank lines
        'blank_line_after_namespace' => true,
        'blank_line_after_opening_tag' => true,
        'blank_line_before_statement' => [
            'statements' => [
                'break',
                'continue',
                'declare',
                'return',
                'throw',
                'try',
                'yield',
                'yield_from',
            ],
        ],
        'no_blank_lines_after_class_opening' => true,
        'no_blank_lines_after_phpdoc' => true,

        // Braces
        'braces' => [
            'allow_single_line_closure' => true,
            'position_after_functions_and_oop_constructs' => 'next',
            'position_after_control_structures' => 'same',
            'position_after_anonymous_constructs' => 'same',
        ],

        // Casing
        'constant_case' => ['case' => 'lower'],
        'lowercase_keywords' => true,
        'lowercase_static_reference' => true,
        'magic_constant_casing' => true,
        'magic_method_casing' => true,
        'native_function_casing' => true,
        'native_function_type_declaration_casing' => true,

        // Casting
        'cast_spaces' => ['space' => 'single'],
        'lowercase_cast' => true,
        'modernize_types_casting' => true,
        'no_short_bool_cast' => true,
        'short_scalar_cast' => true,

        // Classes and properties
        'class_attributes_separation' => [
            'elements' => [
                'const' => 'only_if_meta',
                'method' => 'one',
                'property' => 'only_if_meta',
                'trait_import' => 'none',
                'case' => 'none',
            ],
        ],
        'class_definition' => [
            'single_line' => true,
            'single_item_single_line' => true,
        ],
        'no_null_property_initialization' => true,
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'case',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_public',
                'property_protected',
                'property_private',
                'construct',
                'destruct',
                'magic',
                'phpunit',
                'method_public',
                'method_protected',
                'method_private',
            ],
            'sort_algorithm' => 'none',
        ],
        'protected_to_private' => true,
        'self_accessor' => true,
        'self_static_accessor' => true,
        'single_class_element_per_statement' => true,
        'visibility_required' => ['elements' => ['property', 'method', 'const']],

        // Comments
        'comment_to_phpdoc' => true,
        'multiline_comment_opening_closing' => true,
        'no_empty_comment' => true,
        'no_trailing_whitespace_in_comment' => true,
        'single_line_comment_style' => ['comment_types' => ['hash']],

        // Control structures
        'control_structure_continuation_position' => ['position' => 'same_line'],
        'elseif' => true,
        'no_alternative_syntax' => true,
        'no_break_comment' => true,
        'no_superfluous_elseif' => true,
        'no_trailing_comma_in_list_call' => true,
        'no_unneeded_control_parentheses' => [
            'statements' => [
                'break',
                'clone',
                'continue',
                'echo_print',
                'return',
                'switch_case',
                'yield',
                'yield_from',
            ],
        ],
        'no_unneeded_curly_braces' => ['namespaces' => true],
        'no_useless_else' => true,
        'simplified_if_return' => true,
        'switch_continue_to_break' => true,
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters'],
        ],
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
        ],

        // Doctrine annotations
        'doctrine_annotation_array_assignment' => true,
        'doctrine_annotation_braces' => true,
        'doctrine_annotation_indentation' => true,
        'doctrine_annotation_spaces' => true,

        // Functions
        'function_declaration' => ['closure_function_spacing' => 'one'],
        'function_typehint_space' => true,
        'lambda_not_used_import' => true,
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => false,
        ],
        'no_spaces_after_function_name' => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'phpdoc_to_param_type' => true,
        'phpdoc_to_property_type' => true,
        'phpdoc_to_return_type' => true,
        'regular_callable_call' => true,
        'return_type_declaration' => ['space_before' => 'none'],
        'static_lambda' => true,

        // Imports
        'fully_qualified_strict_types' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
        'no_leading_import_slash' => true,
        'no_unneeded_import_alias' => true,
        'no_unused_imports' => true,
        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],
        'single_import_per_statement' => true,
        'single_line_after_imports' => true,

        // Language constructs
        'declare_equal_normalize' => ['space' => 'none'],
        'declare_strict_types' => true,
        'dir_constant' => true,
        'echo_tag_syntax' => ['format' => 'long'],
        'ereg_to_preg' => true,
        'error_suppression' => true,
        'explicit_indirect_variable' => true,
        'explicit_string_variable' => true,
        'function_to_constant' => [
            'functions' => ['get_called_class', 'get_class', 'get_class_this', 'php_sapi_name', 'phpversion', 'pi'],
        ],
        'get_class_to_class_keyword' => true,
        'is_null' => true,
        'logical_operators' => true,
        'modernize_strpos' => true,
        'no_alias_language_construct_call' => true,
        'no_unset_cast' => true,
        'no_unset_on_property' => true,
        'pow_to_exponentiation' => true,
        'random_api_migration' => true,
        'set_type_to_cast' => true,

        // Lists
        'list_syntax' => ['syntax' => 'short'],

        // Namespaces
        'clean_namespace' => true,
        'no_leading_namespace_whitespace' => true,

        // Operators
        'assign_null_coalescing_to_coalesce_equal' => true,
        'increment_style' => ['style' => 'post'],
        'new_with_braces' => true,
        'not_operator_with_space' => false,
        'not_operator_with_successor_space' => false,
        'object_operator_without_whitespace' => true,
        'standardize_increment' => true,
        'standardize_not_equals' => true,
        'ternary_operator_spaces' => true,
        'ternary_to_elvis_operator' => true,
        'ternary_to_null_coalescing' => true,
        'unary_operator_spaces' => true,

        // PHPDoc
        'align_multiline_comment' => ['comment_type' => 'phpdocs_only'],
        'general_phpdoc_annotation_remove' => ['annotations' => ['author', 'package']],
        'no_blank_lines_after_phpdoc' => true,
        'no_empty_phpdoc' => true,
        'phpdoc_add_missing_param_annotation' => true,
        'phpdoc_align' => ['align' => 'vertical'],
        'phpdoc_annotation_without_dot' => true,
        'phpdoc_indent' => true,
        'phpdoc_inline_tag_normalizer' => true,
        'phpdoc_line_span' => [
            'const' => 'single',
            'method' => 'multi',
            'property' => 'single',
        ],
        'phpdoc_no_access' => true,
        'phpdoc_no_alias_tag' => true,
        'phpdoc_no_empty_return' => true,
        'phpdoc_no_package' => true,
        'phpdoc_no_useless_inheritdoc' => true,
        'phpdoc_order' => true,
        'phpdoc_order_by_value' => true,
        'phpdoc_return_self_reference' => true,
        'phpdoc_scalar' => true,
        'phpdoc_separation' => true,
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_summary' => true,
        'phpdoc_tag_type' => true,
        'phpdoc_to_comment' => true,
        'phpdoc_trim' => true,
        'phpdoc_trim_consecutive_blank_line_separation' => true,
        'phpdoc_types' => true,
        'phpdoc_types_order' => [
            'null_adjustment' => 'always_last',
            'sort_algorithm' => 'none',
        ],
        'phpdoc_var_annotation_correct_order' => true,
        'phpdoc_var_without_name' => true,

        // Return notation
        'no_useless_return' => true,
        'return_assignment' => true,
        'simplified_null_return' => true,

        // Semicolons
        'multiline_whitespace_before_semicolons' => ['strategy' => 'no_multi_line'],
        'no_empty_statement' => true,
        'no_singleline_whitespace_before_semicolons' => true,
        'semicolon_after_instruction' => true,
        'space_after_semicolon' => ['remove_in_empty_for_expressions' => true],

        // Strings
        'escape_implicit_backslashes' => true,
        'explicit_string_variable' => true,
        'heredoc_to_nowdoc' => true,
        'no_binary_string' => true,
        'simple_to_complex_string_variable' => true,
        'single_quote' => true,
        'string_line_ending' => true,

        // Whitespace
        'array_indentation' => true,
        'compact_nullable_typehint' => true,
        'heredoc_indentation' => true,
        'indentation_type' => true,
        'line_ending' => true,
        'method_chaining_indentation' => true,
        'no_extra_blank_lines' => [
            'tokens' => [
                'case',
                'continue',
                'curly_brace_block',
                'default',
                'extra',
                'parenthesis_brace_block',
                'square_brace_block',
                'switch',
                'throw',
                'use',
            ],
        ],
        'no_spaces_around_offset' => true,
        'no_spaces_inside_parenthesis' => true,
        'no_trailing_whitespace' => true,
        'no_whitespace_in_blank_line' => true,
        'single_line_empty_body' => true,
        'types_spaces' => true,

        // Laravel specific
        'no_php4_constructor' => true,
        'php_unit_construct' => true,
        'php_unit_dedicate_assert' => ['target' => 'newest'],
        'php_unit_dedicate_assert_internal_type' => true,
        'php_unit_expectation' => ['target' => 'newest'],
        'php_unit_fqcn_annotation' => true,
        'php_unit_method_casing' => ['case' => 'camel_case'],
        'php_unit_mock' => ['target' => 'newest'],
        'php_unit_mock_short_will_return' => true,
        'php_unit_namespaced' => ['target' => 'newest'],
        'php_unit_no_expectation_annotation' => [
            'target' => 'newest',
            'use_class_const' => true,
        ],
        'php_unit_set_up_tear_down_visibility' => true,
        'php_unit_strict' => [
            'assertions' => [
                'assertAttributeEquals',
                'assertAttributeNotEquals',
                'assertEquals',
                'assertNotEquals',
            ],
        ],
        'php_unit_test_annotation' => ['style' => 'prefix'],
        'php_unit_test_case_static_method_calls' => [
            'call_type' => 'this',
            'methods' => [],
        ],
        'php_unit_test_class_requires_covers' => false,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache');