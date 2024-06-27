<?php
/**
 * This file is modified from original CS fixer source code.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */
declare(strict_types=1);

namespace SMF\Fixer\Whitespace;

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
 * @author Jeremy Darwood	<sleepy@simplemachines.org>
 */
final class closing_tag_fixer extends AbstractFixer implements WhitespacesAwareFixerInterface
{
	public function getName(): string
	{
		return 'SMF/closing_tag_fixer';
	}

	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			'A PHP file must end with a closing tag.',
			[
				new CodeSample("<?php\n\$a = 1;"),
				new CodeSample("<?php\n\$a = 1;\n?>"),
				new CodeSample("<?php\n\if (true){}"),
				new CodeSample("<?php\n\if (true){}\n\n?>"),
			]
		);
	}

	public function getPriority(): int
	{
		return -110;
	}

	public function isCandidate(Tokens $tokens): bool
	{
		$count = $tokens->count();

		// No tokens, not a canidate.
		if ($count == 0) {
			return false;
		}

		// Last character is a white space, needs fixed.
		if ($tokens[$count - 1]->isGivenKind(T_WHITESPACE)) {
			return true;
		}

		// We have closing bracket then closing barcket, single white space and then closing tag.
		if ($tokens[$count - 1]->isGivenKind(T_CLOSE_TAG) && $tokens[$count - 2]->isGivenKind(T_WHITESPACE) && $tokens[$count - 3]->getContent() !== ';') {
			return true;
		}

		// We have a closing bracket, and then closing tag, no white space.
		if ($tokens[$count - 1]->isGivenKind(T_CLOSE_TAG) && $tokens[$count - 2]->getContent() === '}') {
			return true;
		}

	   	return false;
	}

	protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
	{
		$count = $tokens->count();

		// Last character is a white space. Adds a closing tag.
		if ($count > 0 && $tokens[$count - 1]->isGivenKind(T_WHITESPACE)) {
			$tokens[$count - 1] = new Token([
				T_WHITESPACE,
				$this->whitespacesConfig->getLineEnding() . $this->whitespacesConfig->getLineEnding() . '?' . '>'
			]);
		}

		// We have closing bracket then closing barcket, single white space and then closing tag. Add one more return.
		if ($count > 0 && $tokens[$count - 1]->isGivenKind(T_CLOSE_TAG) && $tokens[$count - 2]->isGivenKind(T_WHITESPACE) && $tokens[$count - 3]->getContent() !== ';') {
			$tokens[$count - 2] = new Token([
				T_WHITESPACE,
				$this->whitespacesConfig->getLineEnding() . $this->whitespacesConfig->getLineEnding()
			]);
		}

		// We have a closing bracket, and then closing tag, no white space, add returns.
		// There is no ID/Name for closing curely bracket or semi-colon.
		if ($count > 0 && $tokens[$count - 1]->isGivenKind(T_CLOSE_TAG) && $tokens[$count - 2]->getContent() === '}') {

			$tokens[$count - 1] = new Token([
				T_WHITESPACE,
				$this->whitespacesConfig->getLineEnding() . $this->whitespacesConfig->getLineEnding() . '?' . '>'
			]);
		}
	}
}
