<?php

declare(strict_types=1);

namespace SMF\Tests\Support;

/**
 * One response from HttpClient.
 *
 * Parsing is deliberately DOM based rather than string matching. The theme moves
 * around a lot, so a test that greps for a phrase fails the next time somebody
 * reflows the markup, which teaches everyone to ignore it.
 */
final class HttpResponse
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var \DOMDocument|null The parsed body, once something has asked for it.
	 */
	private ?\DOMDocument $dom = null;

	/****************
	 * Public methods
	 ****************/

	/**
	 * @param int $status The HTTP status.
	 * @param string $body The response body.
	 * @param array $headers Headers, lowercased name => value. Where a header
	 *     appeared more than once only the last is here; Set-Cookie is the one
	 *     that routinely does, so it has its own list below.
	 * @param string $url The URL that was requested.
	 * @param array $set_cookies Every Set-Cookie header, in the order sent.
	 *     Logging in sends two - the session and the forum's own - and keeping
	 *     only one of them loses whichever the test cares about.
	 */
	public function __construct(
		public readonly int $status,
		public readonly string $body,
		public readonly array $headers,
		public readonly string $url,
		public readonly array $set_cookies = [],
	) {}

	/**
	 * The response body as a DOM document.
	 *
	 * SMF emits HTML5, which DOMDocument grumbles about; the warnings are not
	 * interesting and would fail the test under failOnWarning, so they are
	 * collected and discarded rather than raised.
	 *
	 * Parsed once per response. Note the cache has to be a property: a static
	 * inside this method is shared by every instance of the class, so the first
	 * page fetched would be handed back for every page after it.
	 *
	 * @return \DOMDocument The parsed body.
	 */
	public function dom(): \DOMDocument
	{
		if ($this->dom instanceof \DOMDocument) {
			return $this->dom;
		}

		$dom = new \DOMDocument();

		$previous = libxml_use_internal_errors(true);
		$dom->loadHTML('<?xml encoding="UTF-8">' . $this->body, LIBXML_NOWARNING | LIBXML_NOERROR);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		return $this->dom = $dom;
	}

	/**
	 * Runs an XPath query against the body.
	 *
	 * @param string $expression The XPath expression.
	 * @return \DOMNodeList The matching nodes.
	 */
	public function xpath(string $expression): \DOMNodeList
	{
		$result = (new \DOMXPath($this->dom()))->query($expression);

		return $result === false ? new \DOMNodeList() : $result;
	}

	/**
	 * The page title, without the forum name SMF appends to it.
	 *
	 * @return string The title, or an empty string when there is none.
	 */
	public function title(): string
	{
		$titles = $this->xpath('//title');

		return $titles->length === 0 ? '' : trim((string) $titles->item(0)?->textContent);
	}

	/**
	 * All visible text, with runs of whitespace collapsed.
	 *
	 * For assertions where the structure genuinely does not matter, such as
	 * checking an error message reached the page at all.
	 *
	 * @return string The text content of the body.
	 */
	public function text(): string
	{
		$body = $this->xpath('//body');
		$text = $body->length === 0 ? $this->body : (string) $body->item(0)?->textContent;

		return trim((string) preg_replace('~\s+~u', ' ', $text));
	}

	/**
	 * Whatever the page is complaining about.
	 *
	 * SMF renders a fatal error as an ordinary page with a box on it, and the
	 * surrounding menus and news run to several hundred characters, so quoting
	 * the start of the body in a failure message reliably shows everything
	 * except the reason. This picks out the reason.
	 *
	 * @return string The error text, or an empty string when there is none.
	 */
	public function errorText(): string
	{
		$found = [];

		foreach ($this->xpath('//*[contains(@class, "errorbox")] | //*[contains(@class, "error_message")] | //*[@id="fatal_error"]') as $node) {
			$text = trim((string) preg_replace('~\s+~u', ' ', $node->textContent));

			if ($text !== '') {
				$found[] = $text;
			}
		}

		return implode(' / ', array_unique($found));
	}

	/**
	 * Every field a browser would submit for the given form.
	 *
	 * SMF puts more than one hidden field in its forms - the session check that
	 * User::checkSession() insists on, and often a SecurityToken as well - and
	 * the names of both are generated per session. Collecting them all is both
	 * simpler and more honest than knowing which is which.
	 *
	 * Buttons are left out, because a browser submits only the one that was
	 * clicked and SMF branches on which that was. The posting form offers both
	 * "preview" and "post"; sending the pair means the preview wins and the reply
	 * is silently never made, with a perfectly good 200 to show for it. Pass the
	 * button you mean to press as an override.
	 *
	 * @param string $xpath Which form. Defaults to the first one on the page.
	 * @return array The fields, name => value.
	 */
	public function formFields(string $xpath = '//form'): array
	{
		$form = $this->xpath($xpath)->item(0);

		if (!$form instanceof \DOMElement) {
			return [];
		}

		$fields = [];
		$finder = new \DOMXPath($this->dom());

		foreach ($finder->query('.//input | .//textarea | .//select', $form) ?: [] as $input) {
			if (!$input instanceof \DOMElement) {
				continue;
			}

			$name = $input->getAttribute('name');

			if ($name === '') {
				continue;
			}

			$type = strtolower($input->getAttribute('type'));

			// A browser only submits these when they are ticked, and submitting
			// an unticked one turns every checkbox on the page into a yes.
			if (\in_array($type, ['checkbox', 'radio'], true) && !$input->hasAttribute('checked')) {
				continue;
			}

			// Only the button that was clicked gets submitted. See above.
			if (\in_array($type, ['submit', 'button', 'reset', 'image'], true)) {
				continue;
			}

			$fields[$name] = match ($input->nodeName) {
				'textarea' => $input->textContent,
				'select' => $this->selectedOption($finder, $input),
				default => $input->getAttribute('value'),
			};
		}

		return $fields;
	}

	/**
	 * Where the given form posts to.
	 *
	 * @param string $xpath Which form. Defaults to the first one on the page.
	 * @return string The action attribute, or the current URL when it has none.
	 */
	public function formAction(string $xpath = '//form'): string
	{
		$form = $this->xpath($xpath)->item(0);

		if (!$form instanceof \DOMElement) {
			return $this->url;
		}

		$action = $form->getAttribute('action');

		return $action === '' ? $this->url : $action;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * The value a browser would submit for a select element.
	 *
	 * @param \DOMXPath $finder An XPath instance for this document.
	 * @param \DOMElement $select The select element.
	 * @return string The selected value, or the first option's.
	 */
	private function selectedOption(\DOMXPath $finder, \DOMElement $select): string
	{
		$options = $finder->query('.//option', $select) ?: new \DOMNodeList();

		$first = '';

		foreach ($options as $option) {
			if (!$option instanceof \DOMElement) {
				continue;
			}

			$value = $option->hasAttribute('value') ? $option->getAttribute('value') : $option->textContent;

			if ($first === '') {
				$first = $value;
			}

			if ($option->hasAttribute('selected')) {
				return $value;
			}
		}

		return $first;
	}
}
