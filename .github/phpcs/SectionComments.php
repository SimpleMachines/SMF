<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Fixer\ClassNotation;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * Inserts sectioning comments.
 *
 * @author Jon Stovell
 */
final class SectionComments extends AbstractFixer
{
	/*******************
	 * Public properties
	 *******************/

	public array $comments;

	public string $comment_regex;

	/****************
	 * Public methods
	 ****************/

	public function __construct()
	{
		parent::__construct();

		$this->comments = [
			'case' => implode("\n\t", [
				'/************',
				' * Enum cases',
				' ************/',
			]),
			'const' => implode("\n\t", [
				'/*****************',
				' * Class constants',
				' *****************/',
			]),
			'public_property' => implode("\n\t", [
				'/*******************',
				' * Public properties',
				' *******************/',
			]),
			'public_static_property' => implode("\n\t", [
				'/**************************',
				' * Public static properties',
				' **************************/',
			]),
			'internal_property' => implode("\n\t", [
				'/*********************',
				' * Internal properties',
				' *********************/',
			]),
			'internal_static_property' => implode("\n\t", [
				'/****************************',
				' * Internal static properties',
				' ****************************/',
			]),
			'public_method' => implode("\n\t", [
				'/****************',
				' * Public methods',
				' ****************/',
			]),
			'public_static_method' => implode("\n\t", [
				'/***********************',
				' * Public static methods',
				' ***********************/',
			]),
			'internal_method' => implode("\n\t", [
				'/******************',
				' * Internal methods',
				' ******************/',
			]),
			'internal_static_method' => implode("\n\t", [
				'/*************************',
				' * Internal static methods',
				' *************************/',
			]),
		];

		foreach ($this->comments as $type => $string) {
			$words[] = trim($string, "/* \n\t");
		}

		$this->comment_regex = '/^\/[*\s]+(?:' . implode('|', $words) . ')[*\s]+\/$/';
	}

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
			],
		);
	}

	public function getPriority(): int
	{
		return -110;
	}

	public function isCandidate(Tokens $tokens): bool
	{
		return $tokens->isAnyTokenKindsFound(Token::getClassyTokenKinds());
	}

	/******************
	 * Internal methods
	 ******************/

	protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
	{
		$existing = [];

		foreach ($tokens as $key => $token) {
			if ($token->getId() === T_COMMENT) {
				if (preg_match($this->comment_regex, $token->getContent())) {
					$existing[$key] = true;
				}
			}
		}

		// Does this file contain an enumeration?
		$is_enum = $tokens->isAnyTokenKindsFound([T_ENUM]);

		// Now insert fresh copies of the section comments.
		$slices = [];
		$exists = [
			'case' => false,
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

		$in = [];

		foreach ($tokens as $key => $token) {
			$id = $token->getId();

			// Build up the list of token types so that we can figure out
			// which comment type we will want.
			if (\in_array(
				$id,
				$in === [] ? [
					T_PUBLIC,
					T_PROTECTED,
					T_PRIVATE,
					$is_enum && !$exists['case'] ? T_CASE : -1,
				] : [
					T_CONST,
					T_STATIC,
					T_VARIABLE,
					T_FUNCTION,
				],
				true,
			)) {
				$in[$id] = $key;
			}

			// Which comment type do we want to insert?
			if (isset($in[T_CONST])) {
				$insert_type = 'const';
			} elseif ($is_enum && !$exists['case'] && isset($in[T_CASE])) {
				$insert_type = 'case';
			} elseif (isset($in[T_VARIABLE])) {
				if (isset($in[T_STATIC])) {
					if (isset($in[T_PUBLIC])) {
						$insert_type = 'public_static_property';
					} elseif (isset($in[T_PROTECTED]) || isset($in[T_PRIVATE])) {
						$insert_type = 'internal_static_property';
					}
				} else {
					if (isset($in[T_PUBLIC])) {
						$insert_type = 'public_property';
					} elseif (isset($in[T_PROTECTED]) || isset($in[T_PRIVATE])) {
						$insert_type = 'internal_property';
					}
				}
			} elseif (isset($in[T_FUNCTION])) {
				if (isset($in[T_STATIC])) {
					if (isset($in[T_PUBLIC])) {
						$insert_type = 'public_static_method';
					} elseif (isset($in[T_PROTECTED]) || isset($in[T_PRIVATE])) {
						$insert_type = 'internal_static_method';
					}
				} else {
					if (isset($in[T_PUBLIC])) {
						$insert_type = 'public_method';
					} elseif (isset($in[T_PROTECTED]) || isset($in[T_PRIVATE])) {
						$insert_type = 'internal_method';
					}
				}
			}

			if (isset($insert_type)) {
				if (!$exists[$insert_type]) {
					// Start by assuming we want to insert right before the
					// 'public', 'protected', or 'private' keyword.
					$insert_at = array_first($in);

					// Walk back to include any preceding 'final' or 'readonly'
					// keywords, as well as any comments or whitespace.
					while (
						isset($tokens[$insert_at - 1])
						&& (
							$tokens[$insert_at - 1]->isGivenKind([T_FINAL, T_READONLY, T_ABSTRACT])
							|| $tokens[$insert_at - 1]->isWhitespace()
							|| $tokens[$insert_at - 1]->isComment()
						)
					) {
						$insert_at--;
					}

					// Now we need to take one step forward again.
					$insert_at++;

					if ($tokens[$insert_at]->getContent() !== $this->comments[$insert_type]) {
						// Create the comment to insert.
						$to_insert = [
							new Token([
								T_COMMENT,
								$this->comments[$insert_type],
							]),
						];

						// If necessary, also insert some whitespace.
						if (!$tokens[$insert_at]->isWhitespace()) {
							$to_insert[] = new Token([
								T_WHITESPACE,
								"\n\n\t",
							]);
						}

						// Insert our comment.
						$slices[$insert_at] = $to_insert;

						// This comment type has now been done.
						$exists[$insert_type] = true;
					} else {
						// Normalize whitespace.
						if ($tokens[$insert_at - 1]->isWhitespace()) {
							$prev = $tokens->getPrevMeaningfulToken($insert_at);

							$tokens[$insert_at - 1] = new Token([
								T_WHITESPACE,
								$tokens[$prev]->equals('{') ? "\n\t" : "\n\n\t",
							]);
						}

						if ($tokens[$insert_at + 1]->isWhitespace()) {
							$tokens[$insert_at + 1] = new Token([
								T_WHITESPACE,
								"\n\n\t",
							]);
						}

						$exists[$insert_type] = true;

						// Do not remove this token.
						if (isset($existing[$insert_at])) {
							unset($existing[$insert_at]);
						}
					}
				}

				$in = [];
				unset($insert_type);
			}
		}

		// Any existing tokens to empty out?
		foreach ($existing as $key => $_) {
			$tokens->clearAt($key);

			// If necessary, also delete whitespace.
			if ($tokens[$key + 1]->isWhitespace()) {
				$tokens->clearAt($key + 1);
			}
		}

		// Insert comments.
		if ($slices !== []) {
			$tokens->insertSlices($slices);
		}
	}
}
