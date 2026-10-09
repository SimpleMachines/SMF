<?php

/**
 * Converts a *.template.php file of template_*() functions into Plates
 * templates, one file per sub-template in templates/<Template>/, that render
 * byte for byte what the functions echoed.
 *
 *   php .dev/plates/convert.php [--dry] [--signatures=DIR] Themes/default/Who.template.php ...
 *
 * Usually run through .dev/convert-templates.sh, which also applies the
 * fix-ups the default theme needs and tidies the result.
 *
 * - echo 'literal' becomes the literal as HTML; any other echo argument
 *   becomes <?= ... ?>. A concatenation is split into its parts when nothing
 *   in it binds looser than the dot, and adjacent literals are joined.
 * - if/elseif/else, foreach, for and while with braces become the alternative
 *   syntax. Everything else is kept as a PHP block, as written.
 * - PHP eats the newline that follows ?>. Where every text after a tag starts
 *   with a newline, the tags go on their own lines and each line of text
 *   keeps a trailing newline instead. Otherwise the tags stay inline and an
 *   empty line after a tag keeps the newline it ate.
 * - template_foo($a, $b) as a statement becomes
 *   $this->subTemplate('foo', ['a' => $a, ...]), and inside an expression
 *   $this->fetchSubTemplate(...), with the parameter names of template_foo(),
 *   so the call works whichever kind of template has foo.
 *   function_exists('template_...') becomes $this->hasSubTemplate('...'), and
 *   call_user_func('template_' . $x) becomes a call by the built name.
 * - The parameter names come from the *.template.php files next to the one
 *   being converted, and from those in --signatures=DIR, for calls into
 *   templates that have already been converted.
 *
 * Anything it cannot convert faithfully stops it with a message rather than
 * guessing, and anything it converted but a person should check is listed
 * as a warning: a returned value, a by-reference parameter, a call whose
 * parameters are unknown.
 */

declare(strict_types=1);

final class ConvertError extends RuntimeException {}

final class Converter
{
	/** @var PhpToken[] */
	private array $t;

	private array $signatures;

	public static array $layouts = ['own lines' => 0, 'inline' => 0];

	/**
	 * Functions in templates that are not named template_*, and the
	 * sub-templates they become.
	 */
	private const RENAMED = ['theme_linktree' => 'linktree'];

	private array $warnings = [];

	private const LOOSER_THAN_DOT = [
		'?', '<', '>', '=', '&', '|', '^',
		T_COALESCE, T_BOOLEAN_AND, T_BOOLEAN_OR, T_LOGICAL_AND, T_LOGICAL_OR, T_LOGICAL_XOR,
		T_IS_EQUAL, T_IS_NOT_EQUAL, T_IS_IDENTICAL, T_IS_NOT_IDENTICAL, T_IS_SMALLER_OR_EQUAL,
		T_IS_GREATER_OR_EQUAL, T_SPACESHIP, T_PLUS_EQUAL, T_MINUS_EQUAL, T_MUL_EQUAL, T_DIV_EQUAL,
		T_CONCAT_EQUAL, T_MOD_EQUAL, T_AND_EQUAL, T_OR_EQUAL, T_XOR_EQUAL, T_SL_EQUAL, T_SR_EQUAL,
		T_POW_EQUAL, T_COALESCE_EQUAL, T_DOUBLE_ARROW, T_PRINT, T_YIELD, T_YIELD_FROM,
		T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG, T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
	];

	public function __construct(private string $file, private bool $dry, ?string $signatures_dir = null)
	{
		// The original templates, so that calls into ones already converted still find their parameters.
		$this->signatures = self::collectSignatures(dirname($file)) + ($signatures_dir === null ? [] : self::collectSignatures($signatures_dir));
		$this->t = PhpToken::tokenize(file_get_contents($file));
	}

	public function run(): void
	{
		$template = basename($this->file, '.template.php');
		$out_dir = dirname($this->file) . '/templates/' . $template;

		[$license, $uses, $functions, $notes] = $this->parseFile();

		foreach ($notes as $note) {
			$this->warnings[] = 'file-level comment not carried over: ' . substr(preg_replace('/\s+/', ' ', $note), 0, 100);
		}

		$files = [];

		foreach ($functions as $fn) {
			$segments = $this->convertBlock($fn['body'][0] + 1, $fn['body'][1]);
			$body = $this->render($segments);

			$header = "<?php\n\n" . $license . "\n\n";

			$used = array_filter($uses, fn ($use) => preg_match('/\b' . preg_quote($use['alias'], '/') . '\b/', $body));

			if ($used !== []) {
				$header .= implode("\n", array_column($used, 'line')) . "\n\n";
			}

			$header .= "if (!defined('SMF')) {\n\tdie('No direct access...');\n}\n";

			// Not a docblock any more, since nothing follows it to document.
			if ($fn['doc'] !== null) {
				$header .= "\n" . preg_replace('~^(\s*)/\*\*(?=\s)~m', '$1/*', $fn['doc']) . "\n";
			}

			$defaults = array_filter($fn['params'], fn ($p) => $p['default'] !== null);

			if ($defaults !== []) {
				$pairs = array_map(fn ($p) => var_export($p['name'], true) . ' => ' . $p['default'], $defaults);
				$header .= "\n// The parameters that were not passed get the defaults they had as arguments.\n"
					. 'extract([' . implode(', ', $pairs) . "], EXTR_SKIP);\n";
			}

			$files[$fn['name']] = $header . '?>' . $body;
		}

		if ($this->dry) {
			foreach ($files as $name => $content) {
				echo "===== $out_dir/$name.php\n$content\n";
			}
		} else {
			if (!is_dir($out_dir)) {
				mkdir($out_dir, 0o755, true);
			}

			// Every directory gets the theme's stub that refuses direct access.
			$stub = dirname($this->file) . '/index.php';

			if (is_file($stub)) {
				foreach ([dirname($out_dir), $out_dir] as $dir) {
					if (!is_file($dir . '/index.php')) {
						copy($stub, $dir . '/index.php');
					}
				}
			}

			foreach ($files as $name => $content) {
				file_put_contents("$out_dir/$name.php", $content);
			}
		}

		fwrite(STDERR, sprintf("%s: %d sub-templates\n", $template, count($files)));

		foreach (array_unique($this->warnings) as $warning) {
			fwrite(STDERR, "  WARNING $warning\n");
		}
	}

	/***********************************************************************
	 * The file
	 ***********************************************************************/

	private function parseFile(): array
	{
		$license = null;
		$uses = [];
		$functions = [];
		$notes = [];
		$doc = null;
		$n = count($this->t);

		for ($i = 0; $i < $n; $i++) {
			$tok = $this->t[$i];

			if ($tok->is([T_OPEN_TAG, T_WHITESPACE])) {
				continue;
			}

			if ($tok->is(T_DOC_COMMENT)) {
				if ($license === null && str_contains($tok->text, 'Simple Machines Forum (SMF)')) {
					$license = $tok->text;
				} else {
					$doc = $tok->text;
				}

				continue;
			}

			if ($tok->is(T_COMMENT)) {
				// Comments between functions describe the next one, apart from separator lines.
				if (preg_match('~^//\s*[-=*]{5,}\s*$~', trim($tok->text)) === 0) {
					$notes[] = rtrim($tok->text);
				}

				continue;
			}

			if ($tok->is(T_USE)) {
				$end = $this->find($i, ';');
				$line = $this->text($i, $end + 1);
				$alias = preg_match('/\bas\s+(\w+)\s*;$/', $line, $m) ? $m[1] : preg_replace('/^.*\\\\/', '', rtrim(substr($line, 4), '; '));
				$uses[] = ['line' => $line, 'alias' => $alias];
				$i = $end;

				continue;
			}

			if ($tok->is(T_FUNCTION)) {
				$name_at = $this->next($i);
				$function_name = $this->t[$name_at]->text;

				if (!str_starts_with($function_name, 'template_') && !isset(self::RENAMED[$function_name])) {
					throw new ConvertError('top-level function that is not a sub-template: ' . $function_name);
				}

				$open = $this->next($name_at);
				$close = $this->match($open);
				$body_open = $this->next($close);

				if ($this->t[$body_open]->text === ':') {
					// A return type.
					$body_open = $this->find($body_open, '{');
				}

				// Comments written before the function come ahead of its docblock.
				if ($notes !== []) {
					$doc = implode("\n", $notes) . ($doc !== null ? "\n" . $doc : '');
					$notes = [];
				}

				$functions[] = [
					'name' => self::RENAMED[$function_name] ?? substr($function_name, 9),
					'doc' => $doc,
					'params' => $this->parseParams($open + 1, $close),
					'body' => [$body_open, $this->match($body_open)],
				];

				$doc = null;
				$i = $this->match($body_open);

				continue;
			}

			throw new ConvertError('top-level code outside the functions, line ' . $tok->line . ': ' . trim($this->text($i, min($i + 8, $n))));
		}

		if ($license === null) {
			throw new ConvertError('no license docblock');
		}

		return [$license, $uses, $functions, $notes];
	}

	private function parseParams(int $a, int $b): array
	{
		$params = [];

		foreach ($this->split($a, $b, ',') as [$s, $e]) {
			$var = null;
			$default = null;

			for ($i = $s; $i < $e; $i++) {
				if ($this->t[$i]->is(T_ELLIPSIS)) {
					throw new ConvertError('variadic parameter');
				}

				if ($this->t[$i]->is(T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG)) {
					$this->warnings[] = 'by-reference parameter ' . $this->t[$this->next($i)]->text . ' at line ' . $this->t[$i]->line . ' is now passed by value: check nothing writes to it';
				}

				if ($this->t[$i]->is(T_VARIABLE) && $var === null) {
					$var = substr($this->t[$i]->text, 1);
				}

				if ($this->t[$i]->text === '=') {
					$default = trim($this->text($i + 1, $e));

					break;
				}
			}

			if ($var !== null) {
				$params[] = ['name' => $var, 'default' => $default];
			}
		}

		return $params;
	}

	private static function collectSignatures(string $dir): array
	{
		$signatures = [];

		foreach (glob($dir . '/*.template.php') as $file) {
			preg_match_all('/^function (template_\w+|' . implode('|', array_keys(self::RENAMED)) . ')\s*\(([^)]*)\)/m', file_get_contents($file), $m, PREG_SET_ORDER);

			foreach ($m as [, $name, $params]) {
				preg_match_all('/\$(\w+)/', $params, $vars);
				$signatures[self::RENAMED[$name] ?? substr($name, 9)] = $vars[1];
			}
		}

		return $signatures;
	}

	/***********************************************************************
	 * Statements
	 ***********************************************************************/

	/**
	 * Converts the statements in [$a, $b) into segments: ['text', s],
	 * ['echo', code], ['php', code] or ['ctl', code].
	 */
	private function convertBlock(int $a, int $b): array
	{
		$segments = [];
		$i = $a;

		while ($i < $b) {
			$tok = $this->t[$i];

			if ($tok->is(T_WHITESPACE)) {
				$i++;

				continue;
			}

			if ($tok->is([T_COMMENT, T_DOC_COMMENT])) {
				$segments[] = ['php', rtrim($tok->text)];
				$i++;

				continue;
			}

			if ($tok->is(T_ECHO)) {
				$end = $this->find($i, ';');

				foreach ($this->split($i + 1, $end, ',') as [$s, $e]) {
					array_push($segments, ...$this->convertEchoArg($s, $e));
				}

				$i = $end + 1;

				continue;
			}

			if ($tok->is(T_IF)) {
				[$converted, $i] = $this->convertIf($i);
				array_push($segments, ...$converted);

				continue;
			}

			if ($tok->is([T_FOREACH, T_FOR, T_WHILE])) {
				$open = $this->next($i);
				$close = $this->match($open);
				$body = $this->next($close);

				if ($this->t[$body]->text !== '{') {
					throw new ConvertError('loop without braces, line ' . $tok->line);
				}

				$keyword = strtolower($tok->text);
				$segments[] = ['ctl', $keyword . ' ' . $this->rewrite($open, $close + 1) . ':'];
				array_push($segments, ...$this->convertBlock($body + 1, $this->match($body)));
				$segments[] = ['ctl', 'end' . $keyword . ';'];
				$i = $this->match($body) + 1;

				continue;
			}

			if ($tok->is(T_RETURN) && $this->t[$this->next($i)]->text !== ';') {
				$this->warnings[] = 'returns a value at line ' . $tok->line . ', which a Plates template cannot: output it instead';
			}

			// Anything else is kept as PHP, as it was written.
			$end = $this->statementEnd($i);
			$segments[] = ['php', $this->rewrite($i, $end + 1)];
			$i = $end + 1;
		}

		return $this->callsThroughVariables($segments);
	}

	/**
	 * $f = 'template_prefix_' . $x; followed by $f(); calls a sub-template
	 * whose name is built at runtime.
	 */
	private function callsThroughVariables(array $segments): array
	{
		for ($i = 0; $i + 1 < count($segments); $i++) {
			if (
				$segments[$i][0] === 'php'
				&& $segments[$i + 1][0] === 'php'
				&& preg_match('/^\$(\w+) = \'template_([^\']*)\' \. (.+);$/s', $segments[$i][1], $m)
				&& $segments[$i + 1][1] === '$' . $m[1] . '();'
			) {
				$name = $m[2] === '' ? $m[3] : "'" . $m[2] . "' . " . $m[3];
				array_splice($segments, $i, 2, [['php', '$this->subTemplate(' . $name . ');']]);
			}
		}

		return $segments;
	}

	private function convertIf(int $i): array
	{
		$segments = [];
		$keyword = 'if';

		while (true) {
			$open = $this->next($i);
			$close = $this->match($open);
			$body = $this->next($close);

			if ($this->t[$body]->text !== '{') {
				throw new ConvertError('if without braces, line ' . $this->t[$i]->line);
			}

			$segments[] = ['ctl', $keyword . ' ' . $this->rewrite($open, $close + 1) . ':'];
			array_push($segments, ...$this->convertBlock($body + 1, $this->match($body)));
			$after = $this->match($body) + 1;

			// Comments between a closing brace and the else stay with the branch before.
			$k = $after;

			while ($k < count($this->t) && $this->t[$k]->is([T_WHITESPACE, T_COMMENT])) {
				if ($this->t[$k]->is(T_COMMENT)) {
					$comment = ['php', rtrim($this->t[$k]->text)];
				}

				$k++;
			}

			$next = $this->t[$k];

			if ($next->is(T_ELSEIF) || ($next->is(T_ELSE) && $this->t[$this->next($k)]->is(T_IF))) {
				if (isset($comment)) {
					$segments[] = $comment;
					unset($comment);
				}

				$i = $next->is(T_ELSEIF) ? $k : $this->next($k);
				$keyword = 'elseif';

				continue;
			}

			if ($next->is(T_ELSE)) {
				if (isset($comment)) {
					$segments[] = $comment;
					unset($comment);
				}

				$body = $this->next($k);

				if ($this->t[$body]->text !== '{') {
					throw new ConvertError('else without braces, line ' . $next->line);
				}

				$segments[] = ['ctl', 'else:'];
				array_push($segments, ...$this->convertBlock($body + 1, $this->match($body)));
				$after = $this->match($body) + 1;
			}

			$segments[] = ['ctl', 'endif;'];

			// A comment that followed the whole if belongs to what comes next.
			return [$segments, $after];
		}
	}

	private function convertEchoArg(int $a, int $b): array
	{
		[$a, $b] = $this->trim($a, $b);

		if ($b - $a === 1 && $this->t[$a]->is(T_CONSTANT_ENCAPSED_STRING)) {
			$literal = $this->t[$a]->text;

			if ($literal[0] === "'") {
				$value = $this->decodeSingle(substr($literal, 1, -1));

				return str_contains($value, '<?') ? [['echo', $literal]] : [['text', $value]];
			}

			if (!str_contains($literal, '\\') && !str_contains($literal, '$') && !str_contains($literal, '<?')) {
				return [['text', substr($literal, 1, -1)]];
			}

			return [['echo', $literal]];
		}

		if ($this->isTemplateCall($a, $b)) {
			return [['php', $this->rewrite($a, $b) . ';']];
		}

		if ($this->canSplitConcat($a, $b)) {
			$segments = [];

			foreach ($this->split($a, $b, '.') as [$s, $e]) {
				array_push($segments, ...$this->convertEchoArg($s, $e));
			}

			return $segments;
		}

		return [['echo', $this->rewrite($a, $b)]];
	}

	private function decodeSingle(string $s): string
	{
		$out = '';

		for ($i = 0, $n = strlen($s); $i < $n; $i++) {
			if ($s[$i] === '\\' && $i + 1 < $n && ($s[$i + 1] === '\\' || $s[$i + 1] === "'")) {
				$out .= $s[++$i];
			} else {
				$out .= $s[$i];
			}
		}

		return $out;
	}

	private function canSplitConcat(int $a, int $b): bool
	{
		$depth = 0;
		$dots = 0;

		for ($i = $a; $i < $b; $i++) {
			$tok = $this->t[$i];

			if ($this->opens($tok)) {
				$depth++;
			} elseif (in_array($tok->text, [')', ']', '}'], true)) {
				$depth--;
			} elseif ($depth === 0) {
				if ($tok->text === '.') {
					$dots++;
				} elseif ($tok->is(self::LOOSER_THAN_DOT)) {
					return false;
				}
			}

			// A call that outputs while the concatenation is being built would move.
			if ($tok->is(T_STRING) && str_starts_with($tok->text, 'template_')) {
				return false;
			}
		}

		return $dots > 0;
	}

	private function isTemplateCall(int $a, int $b): bool
	{
		if (!$this->t[$a]->is(T_STRING) || !str_starts_with($this->t[$a]->text, 'template_')) {
			return false;
		}

		$open = $this->next($a);

		return $this->t[$open]->text === '(' && $this->match($open) === $b - 1;
	}

	/***********************************************************************
	 * Rewriting calls
	 ***********************************************************************/

	private function rewrite(int $a, int $b): string
	{
		$out = '';

		// A call that is a whole statement outputs; one inside an expression is used as a value.
		[$whole_a, $whole_b] = $this->trim($a, $b);

		if ($whole_b > $whole_a && $this->t[$whole_b - 1]->text === ';') {
			[$whole_a, $whole_b] = $this->trim($whole_a, $whole_b - 1);
		}

		for ($i = $a; $i < $b; $i++) {
			$tok = $this->t[$i];
			$prev = $this->t[$this->prev($i)] ?? null;
			$after_member = $prev !== null && $prev->is([T_FUNCTION, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW]);

			if ($tok->is(T_STRING) && !$after_member && ($open = $this->next($i)) < $b && $this->t[$open]->text === '(') {
				$close = $this->match($open);
				$args = $this->split($open + 1, $close, ',');
				$method = $i === $whole_a && $close === $whole_b - 1 ? 'subTemplate' : 'fetchSubTemplate';

				if (str_starts_with($tok->text, 'template_') || isset(self::RENAMED[$tok->text])) {
					$out .= $this->subTemplateCall(self::RENAMED[$tok->text] ?? substr($tok->text, 9), $args, $method);
					$i = $close;

					continue;
				}

				if (in_array(strtolower($tok->text), ['function_exists', 'call_user_func', 'call_user_func_array'], true) && $args !== []) {
					$first = $this->stripTemplatePrefix(...$args[0]);

					if ($first !== null) {
						$rest = array_map(fn ($arg) => $this->rewrite(...$this->trim(...$arg)), array_slice($args, 1));

						// A Plates template takes its parameters by name, so name them after
						// whichever sub-templates the built name can reach, if they agree.
						if (strtolower($tok->text) === 'call_user_func' && $rest !== []) {
							$names = $this->namesForDynamicCall(...$args[0]);

							if ($names !== null && count($names) >= count($rest)) {
								$rest = array_map(fn ($n, $arg) => var_export($n, true) . ' => ' . $arg, array_slice($names, 0, count($rest)), $rest);
							} else {
								$this->warnings[] = 'call through a built name at line ' . $tok->line . ' passes its arguments in order, which a Plates template cannot take';
							}
						}

						$out .= match (strtolower($tok->text)) {
							'function_exists' => '$this->hasSubTemplate(' . $first . ')',
							'call_user_func' => '$this->' . $method . '(' . $first . ($rest === [] ? '' : ', [' . implode(', ', $rest) . ']') . ')',
							'call_user_func_array' => '$this->' . $method . '(' . $first . ', ' . ($rest[0] ?? '[]') . ')',
						};
						$i = $close;

						continue;
					}
				}
			}

			if ($tok->is(T_CONSTANT_ENCAPSED_STRING) && preg_match('/^.template_/', $tok->text)) {
				$this->warnings[] = 'a template_ name in a string, line ' . $tok->line . ': ' . $this->text($a, $b);
			}

			$out .= $tok->text;
		}

		return $out;
	}

	/**
	 * The parameter names shared by every known sub-template a name built at
	 * runtime could be, such as 'template_bi_' . $board['type'] . '_icon'.
	 */
	private function namesForDynamicCall(int $a, int $b): ?array
	{
		$pattern = '';
		$depth = 0;

		for ($i = $a; $i < $b; $i++) {
			$tok = $this->t[$i];

			if ($this->opens($tok)) {
				$depth++;
			} elseif (in_array($tok->text, [')', ']', '}'], true)) {
				$depth--;
			}

			// Only the literals being concatenated are part of the name, not an array key inside the variables.
			if ($depth === 0 && $tok->is(T_CONSTANT_ENCAPSED_STRING)) {
				$pattern .= preg_quote(substr($tok->text, 1, -1), '/');
			} elseif (!$tok->is(T_WHITESPACE) && $tok->text !== '.' && !str_ends_with($pattern, '.*')) {
				$pattern .= '.*';
			}
		}

		$pattern = '/^' . preg_replace('/^template_/', '', $pattern) . '$/';
		$found = null;

		foreach ($this->signatures as $name => $params) {
			if (preg_match($pattern, $name)) {
				if ($found !== null && $found !== $params) {
					return null;
				}

				$found = $params;
			}
		}

		return $found;
	}

	private function subTemplateCall(string $name, array $args, string $method): string
	{
		if ($args === []) {
			return '$this->' . $method . '(' . var_export($name, true) . ')';
		}

		$params = $this->signatures[$name] ?? null;

		if ($params === null) {
			$this->warnings[] = "call to template_$name(), whose parameters are unknown; passed in order";
		}

		$items = [];

		foreach (array_values($args) as $n => [$s, $e]) {
			[$s, $e] = $this->trim($s, $e);

			// A named argument keeps its name.
			if ($this->t[$s]->is(T_STRING) && $this->t[$this->next($s)]->text === ':') {
				$items[] = var_export($this->t[$s]->text, true) . ' => ' . $this->rewrite($this->next($this->next($s)), $e);

				continue;
			}

			if ($params !== null && !isset($params[$n])) {
				throw new ConvertError("template_$name() is called with more arguments than it takes");
			}

			$items[] = ($params !== null ? var_export($params[$n], true) . ' => ' : '') . $this->rewrite($s, $e);
		}

		return '$this->' . $method . '(' . var_export($name, true) . ', [' . implode(', ', $items) . '])';
	}

	/**
	 * For 'template_foo' or 'template_' . $x, the same without the prefix.
	 */
	private function stripTemplatePrefix(int $a, int $b): ?string
	{
		[$a, $b] = $this->trim($a, $b);

		if (!$this->t[$a]->is(T_CONSTANT_ENCAPSED_STRING) || !preg_match('/^([\'"])template_/', $this->t[$a]->text, $m)) {
			return null;
		}

		$rest = substr($this->t[$a]->text, 10);

		if ($rest === $m[1]) {
			// 'template_' . $x: drop the literal and the dot after it.
			$dot = $this->next($a);

			if ($this->t[$dot]->text !== '.') {
				return null;
			}

			return trim($this->rewrite($this->next($dot), $b));
		}

		return $m[1] . $rest . $this->rewrite($a + 1, $b);
	}

	/***********************************************************************
	 * Output
	 ***********************************************************************/

	/**
	 * Lays the segments out as a file.
	 *
	 * PHP eats the newline that follows a closing tag, so where the newlines go
	 * decides what is output. SMF writes each line of HTML as a literal that
	 * starts with a newline. When every line of a sub-template does, its
	 * output is the same with each newline moved to the end of its line
	 * instead: the first newline is written right after the header, the last
	 * line keeps none, and every tag in between can stand on a line of its
	 * own. When they do not, the tags follow the text they come after, and a
	 * line after a tag gets one extra newline for PHP to eat.
	 */
	private function render(array $segments): string
	{
		$segments = $this->protectOpenTags($segments);

		// Consecutive PHP statements share one block.
		$merged = [];

		foreach ($segments as $segment) {
			$last = array_key_last($merged);

			if ($segment[0] === 'text' && $segment[1] === '') {
				continue;
			}

			if ($segment[0] === 'php' && !$this->outputs($segment[1]) && $last !== null && $merged[$last][0] === 'php' && !$this->outputs($merged[$last][1])) {
				$merged[$last][1] .= "\n" . $segment[1];
			} else {
				$merged[] = $segment;
			}
		}

		if ($this->canTrailNewlines($merged)) {
			self::$layouts['own lines']++;

			return $this->renderTrailing($merged);
		}

		self::$layouts['inline']++;

		return $this->renderInline($merged);
	}

	/**
	 * Keeps text that would read as an opening PHP tag out of the HTML.
	 *
	 * The templates write the XML declaration as '<', '?xml ...?', '>' so that
	 * no one literal holds the tag. Written next to each other in a file, the
	 * pieces would make one. Adjacent literals are joined first, and a line
	 * that holds the tag is output as a PHP string instead.
	 */
	private function protectOpenTags(array $segments): array
	{
		$joined = [];

		foreach ($segments as $segment) {
			$last = array_key_last($joined);

			if ($segment[0] === 'text' && $last !== null && $joined[$last][0] === 'text') {
				$joined[$last][1] .= $segment[1];
			} else {
				$joined[] = $segment;
			}
		}

		$out = [];

		foreach ($joined as $segment) {
			if ($segment[0] !== 'text' || !str_contains($segment[1], '<?')) {
				$out[] = $segment;

				continue;
			}

			// Every newline stays in the text, so the layout still sees where lines begin.
			foreach (preg_split('/(\n)/', $segment[1], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $piece) {
				if (str_contains($piece, '<?')) {
					$out[] = ['echo', var_export($piece, true)];
				} elseif ($out !== [] && end($out)[0] === 'text') {
					$out[array_key_last($out)][1] .= $piece;
				} else {
					$out[] = ['text', $piece];
				}
			}
		}

		return $out;
	}

	/** Whether a segment is a tag that outputs nothing. */
	private function isQuietTag(array $segment): bool
	{
		return $segment[0] === 'ctl' || ($segment[0] === 'php' && !$this->outputs($segment[1]));
	}

	/** Whether a PHP statement may write to the output. */
	private function outputs(string $code): bool
	{
		// A call through a variable could be anything, so it counts as output.
		return preg_match('/\b(echo|print|printf|vprintf|readfile|passthru|var_dump|print_r)\b|subTemplate\(|template_\w*\s*\(|copyright\s*\(|\$\w+\s*\(/i', $code) === 1;
	}

	private function canTrailNewlines(array $segments): bool
	{
		$first_output = null;
		$last_output = null;

		foreach ($segments as $i => $segment) {
			if (!$this->isQuietTag($segment)) {
				$first_output ??= $i;
				$last_output = $i;
			}

			// An early return would leave the last line's newline behind.
			if ($segment[0] === 'php' && preg_match('/\breturn\b/', $segment[1])) {
				return false;
			}

			// Whatever follows a tag has to start a line.
			$after_tag = $i === 0 || $this->isQuietTag($segments[$i - 1]);

			if ($after_tag && !$this->isQuietTag($segment) && !($segment[0] === 'text' && $segment[1][0] === "\n")) {
				return false;
			}
		}

		if ($first_output === null) {
			return false;
		}

		// The first line has to be output every time, and so does the last.
		for ($i = 0; $i < count($segments); $i++) {
			if ($segments[$i][0] === 'ctl' && ($i < $first_output || $i > $last_output)) {
				return false;
			}
		}

		return true;
	}

	private function renderTrailing(array $segments): string
	{
		// The header's closing tag eats the first newline, and the second starts the output.
		$out = "\n\n";
		$line_open = false;
		$at_tag = false;
		$n = count($segments);

		foreach ($segments as $i => [$type, $value]) {
			if ($type === 'text' && $value[0] === "\n") {
				if ($line_open) {
					$out .= $at_tag ? "\n\n" : "\n";
				}

				$out .= substr($value, 1);
				$line_open = true;
				$at_tag = false;

				continue;
			}

			if ($type === 'text') {
				$out .= $value;
				$at_tag = false;

				continue;
			}

			if ($type === 'echo' || !$this->isQuietTag([$type, $value])) {
				$out .= $type === 'echo' ? '<?= ' . $value . ' ?>' : $this->tag($value);
				$at_tag = true;

				continue;
			}

			// A tag that outputs nothing ends the line, and stands on its own.
			if ($line_open) {
				$out .= $at_tag ? "\n\n" : "\n";
				$line_open = false;
			}

			$out .= $this->tag($value) . "\n";
			$at_tag = false;
		}

		// A final newline after a closing tag is eaten, so the file can end with one.
		return $at_tag ? $out . "\n" : $out;
	}

	private function renderInline(array $segments): string
	{
		$out = '';
		// Whether the file so far ends with a closing tag, which eats a newline written after it.
		$at_tag = true;

		foreach ($segments as [$type, $value]) {
			if ($type === 'text') {
				if ($at_tag && $value[0] === "\n") {
					$out .= "\n";
				}

				$out .= $value;
				$at_tag = false;

				continue;
			}

			$out .= $type === 'echo' ? '<?= ' . $value . ' ?>' : $this->tag($value);
			$at_tag = true;
		}

		return $at_tag ? $out . "\n" : $out;
	}

	private function tag(string $code): string
	{
		// A line comment would run into the closing tag.
		if (preg_match('~^//\s?(.*)$~', $code, $m) && !str_contains($m[1], '*/')) {
			return '<?php /* ' . rtrim($m[1]) . ' */ ?>';
		}

		return str_contains($code, "\n") ? "<?php\n" . $code . "\n?>" : '<?php ' . $code . ' ?>';
	}

	/***********************************************************************
	 * Tokens
	 ***********************************************************************/

	private function text(int $a, int $b): string
	{
		$s = '';

		for ($i = $a; $i < $b; $i++) {
			$s .= $this->t[$i]->text;
		}

		return $s;
	}

	private function next(int $i): int
	{
		do {
			$i++;
		} while ($i < count($this->t) && $this->t[$i]->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]));

		return $i;
	}

	private function prev(int $i): int
	{
		do {
			$i--;
		} while ($i >= 0 && $this->t[$i]->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]));

		return $i;
	}

	private function trim(int $a, int $b): array
	{
		while ($a < $b && $this->t[$a]->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
			$a++;
		}

		while ($b > $a && $this->t[$b - 1]->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
			$b--;
		}

		return [$a, $b];
	}

	private function opens(PhpToken $tok): bool
	{
		return in_array($tok->text, ['(', '[', '{'], true) || $tok->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES]);
	}

	private function match(int $open): int
	{
		$depth = 0;

		for ($i = $open, $n = count($this->t); $i < $n; $i++) {
			if ($this->opens($this->t[$i])) {
				$depth++;
			} elseif (in_array($this->t[$i]->text, [')', ']', '}'], true) && --$depth === 0) {
				return $i;
			}
		}

		throw new ConvertError('unbalanced brackets from line ' . $this->t[$open]->line);
	}

	/** The next $char at the same depth as $i. */
	private function find(int $i, string $char): int
	{
		$depth = 0;

		for ($n = count($this->t); $i < $n; $i++) {
			$tok = $this->t[$i];

			if ($depth === 0 && $tok->text === $char) {
				return $i;
			}

			if ($this->opens($tok)) {
				$depth++;
			} elseif (in_array($tok->text, [')', ']', '}'], true)) {
				$depth--;
			}
		}

		throw new ConvertError("no $char from line " . $this->t[$i - 1]->line);
	}

	/**
	 * Where a statement kept as written ends: its semicolon, or the closing
	 * brace of a switch, try or do block and whatever belongs to it.
	 */
	private function statementEnd(int $i): int
	{
		$tok = $this->t[$i];

		if ($tok->is(T_SWITCH)) {
			return $this->match($this->next($this->match($this->next($i))));
		}

		if ($tok->is(T_TRY)) {
			$end = $this->match($this->next($i));

			while ($this->t[$this->next($end)]->is([T_CATCH, T_FINALLY])) {
				$k = $this->next($end);

				if ($this->t[$k]->is(T_CATCH)) {
					$k = $this->match($this->next($k));
				}

				$end = $this->match($this->next($k));
			}

			return $end;
		}

		if ($tok->is(T_DO)) {
			return $this->find($this->match($this->next($i)), ';');
		}

		if ($tok->is([T_IF, T_ELSE, T_ELSEIF, T_FOREACH, T_FOR, T_WHILE])) {
			throw new ConvertError('control structure that could not be converted, line ' . $tok->line);
		}

		return $this->find($i, ';');
	}

	/** Splits [$a, $b) on $char at the top level. */
	private function split(int $a, int $b, string $char): array
	{
		$parts = [];
		$depth = 0;
		$start = $a;

		for ($i = $a; $i < $b; $i++) {
			$tok = $this->t[$i];

			if ($this->opens($tok)) {
				$depth++;
			} elseif (in_array($tok->text, [')', ']', '}'], true)) {
				$depth--;
			} elseif ($depth === 0 && $tok->text === $char) {
				$parts[] = [$start, $i];
				$start = $i + 1;
			}
		}

		$parts[] = [$start, $b];

		// A trailing comma leaves nothing after it.
		return array_values(array_filter($parts, fn ($p) => trim($this->text($p[0], $p[1])) !== ''));
	}
}

$dry = false;
$signatures_dir = null;
$files = [];

foreach (array_slice($argv, 1) as $arg) {
	if ($arg === '--dry') {
		$dry = true;
	} elseif (str_starts_with($arg, '--signatures=')) {
		$signatures_dir = substr($arg, strlen('--signatures='));
	} else {
		$files[] = $arg;
	}
}

if ($files === []) {
	fwrite(STDERR, "usage: php .dev/plates/convert.php [--dry] [--signatures=DIR] Themes/<theme>/<Template>.template.php ...\n");

	exit(2);
}

$failed = false;

foreach ($files as $file) {
	try {
		(new Converter($file, $dry, $signatures_dir))->run();
	} catch (ConvertError $e) {
		fwrite(STDERR, basename($file) . ': NOT CONVERTED - ' . $e->getMessage() . "\n");
		$failed = true;
	}
}

fwrite(STDERR, sprintf("layouts: %d with tags on their own lines, %d inline\n", Converter::$layouts['own lines'], Converter::$layouts['inline']));

exit($failed ? 1 : 0);
