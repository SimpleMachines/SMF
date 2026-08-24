<?php

declare(strict_types=1);

namespace SMF\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Fetches the pages a visitor can reach and checks the forum was not quietly
 * unhappy about any of them.
 *
 * This is the cheapest broad coverage available. Every one of these goes through
 * Forum::execute(), so it exercises the action lookup, the permission checks, the
 * theme, the templates and the database on the way - and none of it is reachable
 * from the unit suite, which has no request and no forum.
 *
 * The error log assertion is the point. SMF records most of what goes wrong there
 * rather than showing it, so a page can return a perfectly good 200 while logging
 * an undefined array key on every hit. Checking the status alone would call that
 * a pass.
 */
#[CoversNothing]
class GuestPagesTest extends HttpTestCase
{
	/****************
	 * Public methods
	 ****************/

	#[DataProvider('guestPages')]
	public function testThePageLoadsAndLogsNothing(string $path, string $name): void
	{
		$response = $this->fetch($path);

		$this->assertLooksLikeAForumPage($response, $name);
		$this->assertNoErrorsLogged($name . ' (' . $path . ') logged something.' . "\n");
	}

	public function testTheBoardIndexListsAtLeastOneBoard(): void
	{
		$response = $this->fetch('');

		$this->assertGreaterThan(
			0,
			$response->xpath('//a[contains(@href, "board=")]')->length,
			'the board index links to no boards, so a fresh install has nothing in it',
		);

		$this->assertNoErrorsLogged();
	}

	/**
	 * The one page here that is not HTML. It is worth its place because the feed
	 * is built by hand rather than by the template layer, so nothing else in this
	 * file would notice it breaking.
	 */
	public function testTheFeedIsXmlAndParses(): void
	{
		$response = $this->fetch('?action=.xml;type=rss2');

		$this->assertStringContainsString(
			'xml',
			strtolower($response->headers['content-type'] ?? ''),
			'the feed did not come back as XML',
		);

		$previous = libxml_use_internal_errors(true);
		$parsed = simplexml_load_string($response->body);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		$this->assertNotFalse($parsed, 'the feed is not well formed XML');
		$this->assertNoErrorsLogged('the feed logged something.' . "\n");
	}

	/**
	 * An action that does not exist should be a 404, not a 200 with an apology
	 * and not a 500.
	 */
	public function testAnUnknownActionIsNotFound(): void
	{
		$this->fetch('?action=smf_tests_no_such_action', 404);
	}

	/**
	 * Registration refuses to start for a visitor who sends no cookies at all,
	 * because a registration that cannot keep a session cannot be completed.
	 *
	 * It is in its own test rather than the sweep above because it is the one
	 * page here that a cold request is genuinely not allowed to reach, and the
	 * difference between the two halves is worth stating: arriving at the forum
	 * first is what makes it work, and that is what a browser does.
	 */
	public function testRegistrationNeedsASessionFirst(): void
	{
		$this->http->forgetCookies();

		$cold = $this->http->get('?action=signup');

		$this->assertSame(403, $cold->status, 'a cookieless visitor was allowed into registration');

		// Arrive at the forum the way a person would, which sets the session
		// cookie, and then go to register.
		$this->fetch('');

		$agreement = $this->fetch('?action=signup');

		$this->assertLooksLikeAForumPage($agreement, 'the registration agreement');

		// requireAgreement is on by default, so step one is the agreement rather
		// than the form. Note the form has to be named: the first form on any SMF
		// page is the search box in the header.
		$registration_form = '//form[contains(@action, "action=signup")]';

		$this->assertGreaterThan(
			0,
			$agreement->xpath($registration_form . '//input[@name="accept_agreement"]')->length,
			'registration did not start at the agreement',
		);

		// Buttons are not submitted unless named, so say which one we press.
		$form = $this->http->submit($agreement, [
			'accept_agreement' => 'I accept the terms of the agreement.',
		], $registration_form);

		$this->assertSame(200, $form->status, 'accepting the agreement returned ' . $form->status);

		$this->assertGreaterThan(
			0,
			$form->xpath('//input[@name="user"]')->length,
			'accepting the agreement did not lead to the registration form',
		);

		$this->assertNoErrorsLogged('registering logged something.' . "\n");
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * The pages a guest can reach on a stock install.
	 *
	 * Deliberately only actions that a fresh forum can serve without any content
	 * having been created and without being logged in, so this stays green on a
	 * forum straight out of .docker/install-forum.sh.
	 *
	 * @return array The cases, path and a readable name.
	 */
	public static function guestPages(): array
	{
		return [
			'board index' => ['', 'the board index'],
			'help' => ['?action=help', 'help'],
			'login form' => ['?action=login', 'the login form'],
			'recent posts' => ['?action=recent', 'recent posts'],
			'unread' => ['?action=unread', 'unread posts'],
			'search form' => ['?action=search', 'the search form'],
			'member list' => ['?action=mlist', 'the member list'],
			'statistics' => ['?action=stats', 'the statistics page'],
			'credits' => ['?action=credits', 'the credits page'],
			'who is online' => ['?action=who', 'who is online'],
			'agreement' => ['?action=agreement', 'the registration agreement'],
			'first board' => ['?board=1.0', 'the first board'],
		];
	}
}
