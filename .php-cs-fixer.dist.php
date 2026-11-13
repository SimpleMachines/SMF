<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */
$finder = (new PhpCsFixer\Finder())
	->in(__DIR__)
	->in(__DIR__ . '/Sources/Unicode')
	// Include files that are otherwise ignored.
	->append([
		new SplFileInfo(__FILE__),
	])
	// Don't touch libraries.
	->exclude([
		'cache',
		'other',
		'Packages',
		'Smileys',
		'Sources/minify',
		'Sources/random_compat',
		'Sources/ReCaptcha',
		'vendor',
	])
	// Skip ssi_example.php.
	->notName(['ssi_examples.php'])
	// Skip anything being ignored in .gitignore.
	->ignoreVCSIgnored(true);

//~ require_once '.github/phpcs/HeaderCommentWithFilterFixer.php';

//~ require_once '.github/phpcs/SectionComments.php';

return (new PhpCsFixer\Config())
	//~ ->registerCustomFixers([
		//~ new SMF\Fixer\Comment\HeaderCommentWithFilterFixer(),
		//~ new SMF\Fixer\ClassNotation\SectionComments(),
	//~ ])
	->setRules([
		'braces_position' => [
			'allow_single_line_anonymous_functions' => true,
			'allow_single_line_empty_anonymous_classes' => true,
			'control_structures_opening_brace' => 'next_line_unless_newline_at_signature_end',
			'anonymous_functions_opening_brace' => 'next_line_unless_newline_at_signature_end',
		],
	])
	->setIndent("\t")
	->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect(40))
	->setFinder($finder);
