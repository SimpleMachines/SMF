<?php

/**
 * This file is modified from original CS fixer source code.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */
declare(strict_types=1);

namespace SMF\Fixer\ClassNotation;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;
use PhpCsFixer\Fixer\Whitespace;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\Token;

/**
 * Ensure line endings match SMF standards.
 *
 * @author Jon Stovell
 */
final class SectionComments extends AbstractFixer implements WhitespacesAwareFixerInterface
{
	public function getName(): string
	{
		return 'SMF/section_comments';
	}

	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			'Inserts sectioning comments. This is meant to be used in combination with the `ordered_class_elements` rule.',
			[
				new CodeSample(<<<'END'
					<?php

					class Foo
					{
						/**
						 *
						 */
						const MY_CONSTANT = 1;

						/**
						 *
						 */
						public string $a = '';

						/**
						 *
						 */
						protected string $b = '';

						/**
						 *
						 */
						private string $c = '';

						/**
						 *
						 */
						public static string $d = '';

						/**
						 *
						 */
						protected static string $e = '';

						/**
						 *
						 */
						private static string $f = '';

						/**
						 *
						 */
						public function method1(): void {}

						/**
						 *
						 */
						protected function method2(): void {}

						/**
						 *
						 */
						private function method3(): void {}

						/**
						 *
						 */
						public static function method4(): void {}

						/**
						 *
						 */
						protected static function method5(): void {}

						/**
						 *
						 */
						private static function method6(): void {}
					}

					END),
			]
		);
	}

	public function getPriority(): int
	{
		return -110;
	}

	public function isCandidate(Tokens $tokens): bool
	{
		foreach ($tokens as $token) {
			if ($token->isClassy()) {
				return true;
			}
		}

		return false;
	}

	protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
	{
		$found = [
			'const' => false,
			'public_property' => false,
			'public_static_property' => false,
			'internal_property' => false,
			'internal_static_property' => false,
			'public_method' => false,
			'public_static_method' => false,
			'internal_method' => false,
			'internal_static_method' => false,
		];

		$comments = [
			'const' => implode($this->whitespacesConfig->getLineEnding() . $this->whitespacesConfig->getIndent(), [
				'/*****************',
				' * Class constants',
				' *****************/',
			]),
			'public_property' => implode($this->whitespacesConfig->getLineEnding() . $this->whitespacesConfig->getIndent(), [
				'/*******************',
				' * Public properties',
				' *******************/',
			]),
			'public_static_property' => implode($this->whitespacesConfig->getLineEnding() . $this->whitespacesConfig->getIndent(), [
				'/**************************',
				' * Public static properties',
				' **************************/',
			]),
			'internal_property' => implode($this->whitespacesConfig->getLineEnding() . $this->whitespacesConfig->getIndent(), [
				'/*********************',
				' * Internal properties',
				' *********************/',
			]),
			'internal_static_property' => implode($this->whitespacesConfig->getLineEnding() . $this->whitespacesConfig->getIndent(), [
				'/****************************',
				' * Internal static properties',
				' ****************************/',
			]),
			'public_method' => implode($this->whitespacesConfig->getLineEnding() . $this->whitespacesConfig->getIndent(), [
				'/****************',
				' * Public methods',
				' ****************/',
			]),
			'public_static_method' => implode($this->whitespacesConfig->getLineEnding() . $this->whitespacesConfig->getIndent(), [
				'/***********************',
				' * Public static methods',
				' ***********************/',
			]),
			'internal_method' => implode($this->whitespacesConfig->getLineEnding() . $this->whitespacesConfig->getIndent(), [
				'/******************',
				' * Internal methods',
				' ******************/',
			]),
			'internal_static_method' => implode($this->whitespacesConfig->getLineEnding() . $this->whitespacesConfig->getIndent(), [
				'/*************************',
				' * Internal static methods',
				' *************************/',
			]),
		];

		$in = [];

		foreach ($tokens as $key => $token) {
			if (
				$token->getName() === 'T_COMMENT'
				&& ($comment_type = array_search($token->getContent(), $comments)) !== false
			) {
				$found[$comment_type] = true;
				$in = [];
				continue;
			}

			if (in_array(
				$token->getName(),
				empty($in) ? [
					'T_PUBLIC',
					'T_PROTECTED',
					'T_PRIVATE',
				] : [
					'T_CONST',
					'T_STATIC',
					'T_VARIABLE',
					'T_FUNCTION',
				]
			)) {
				$in[$key] = $token->getName();
			}

			if (in_array('T_CONST', $in)) {
				$insert_type = 'const';
			} elseif (in_array('T_VARIABLE', $in)) {
				if (in_array('T_STATIC', $in)) {
					if (in_array('T_PUBLIC', $in)) {
						$insert_type = 'public_static_property';
					} elseif (in_array('T_PROTECTED', $in) || in_array('T_PRIVATE', $in)) {
						$insert_type = 'internal_static_property';
					}
				} else {
					if (in_array('T_PUBLIC', $in)) {
						$insert_type = 'public_property';
					} elseif (in_array('T_PROTECTED', $in) || in_array('T_PRIVATE', $in)) {
						$insert_type = 'internal_property';
					}
				}
			} elseif (in_array('T_FUNCTION', $in)) {
				if (in_array('T_STATIC', $in)) {
					if (in_array('T_PUBLIC', $in)) {
						$insert_type = 'public_static_method';
					} elseif (in_array('T_PROTECTED', $in) || in_array('T_PRIVATE', $in)) {
						$insert_type = 'internal_static_method';
					}
				} else {
					if (in_array('T_PUBLIC', $in)) {
						$insert_type = 'public_method';
					} elseif (in_array('T_PROTECTED', $in) || in_array('T_PRIVATE', $in)) {
						$insert_type = 'internal_method';
					}
				}
			}

			if (isset($insert_type)) {
				if (!$found[$insert_type]) {
					$prepend_to = array_key_first($in);

					while (
						isset($tokens[$prepend_to - 1])
						&& (
							$tokens[$prepend_to - 1]->isWhitespace()
							|| $tokens[$prepend_to - 1]->isComment()
						)
					) {
						$prepend_to--;
					};

					$previous = $prepend_to - 1;

					// Special case for stuff right after the class's opening brace.
					if ($tokens[$previous]->getContent() === '{') {
						$comment = $this->whitespacesConfig->getLineEnding() . $this->whitespacesConfig->getIndent() . $comments[$insert_type] . $this->whitespacesConfig->getLineEnding();
					} else {
						$comment = $this->whitespacesConfig->getLineEnding() . $this->whitespacesConfig->getLineEnding() . $this->whitespacesConfig->getIndent() . $comments[$insert_type];
					}

					$tokens[$prepend_to] = new Token(
						$comment . $tokens[$prepend_to]->getContent(),
					);

					$found[$insert_type] = true;
				}

				$in = [];
				unset($insert_type);
			}
		}
	}
}
