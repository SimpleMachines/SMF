<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF;

/**
 * Represents a user's avatar.
 *
 * The avatar is a complicated thing, and historically had multiple
 * representations in the code. This supports everything.
 */
class Avatar implements \ArrayAccess
{
	use ArrayAccessHelper;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var Url
	 *
	 * URL that should be used for the avatar image.
	 */
	public Url $url;

	/**
	 * @var string
	 *
	 * URL string of the avatar as stored in the member's table.
	 *
	 * Can differ from the finalized string value of $this->url in a variety of
	 * circumstances.
	 */
	public string $original_url;

	/**
	 * @var string
	 *
	 * Name of the avatar's file.
	 */
	public string $filename;

	/**
	 * @var int
	 *
	 * If this avatar's file is managed by SMF's file attachment system, this is
	 * the ID of the relevant file attachment.
	 */
	public int $id_attach;

	/**
	 * @var int
	 *
	 * If this avatar's file is managed by SMF's file attachment system, this is
	 * the attachment type of the relevant file attachment.
	 */
	public int $attachment_type;

	/**
	 * @var int
	 *
	 * Intrinsic width of the avatar image, if known.
	 */
	public int $width;

	/**
	 * @var int
	 *
	 * Intrinsic height of the avatar image, if known.
	 */
	public int $height;

	/**
	 * @var int
	 *
	 * ID of the member whose avatar this is.
	 */
	public int $id_member;

	/**
	 * @var string
	 *
	 * Email address of the member whose avatar this is.
	 *
	 * Only used for Gravatars.
	 */
	public string $email;

	/**
	 * @var string
	 *
	 * HTML <img> tag for this avatar.
	 */
	public string $image {
		// This &get hook lets us set a default value programmatically.
		&get {
			$this->image ??= '<img class="avatar" src="' . (string) $this->url . '" alt="">';

			return $this->image;
		}
	}

	/**
	 * @var string
	 *
	 * Extra data used for the form that allows the user to choose an avatar.
	 */
	public string $custom {
		// This &get hook lets us set a default value programmatically.
		&get {
			$this->custom ??= $this->url->isWebsite() ? (string) $this->url : 'http://';

			return $this->custom;
		}
	}

	/**
	 * @var string
	 *
	 * Extra data used for the form that allows the user to choose an avatar.
	 */
	public string $selection {
		// This &get hook lets us set a default value programmatically.
		&get {
			$this->selection ??= $this->url->isWebsite() ? (string) $this->url : '';

			return $this->selection;
		}
	}

	/**
	 * @var bool
	 *
	 * Extra data used for the form that allows the user to choose an avatar.
	 */
	public bool $allow_server_stored {
		// This &get hook lets us set a default value programmatically.
		&get {
			$this->allow_server_stored ??= (
				(
					empty(Config::$modSettings['gravatarEnabled'])
					|| empty(Config::$modSettings['gravatarOverride'])
				)
				&& (
					User::$me->allowedTo('profile_server_avatar')
					|| (
						!Profile::$member->is_me
						&& User::$me->allowedTo('profile_extra_any')
					)
				)
			);

			return $this->allow_server_stored;
		}
	}

	/**
	 * @var bool
	 *
	 * Extra data used for the form that allows the user to choose an avatar.
	 */
	public bool $allow_upload {
		// This &get hook lets us set a default value programmatically.
		&get {
			$this->allow_upload ??= (
				(
					empty(Config::$modSettings['gravatarEnabled'])
					|| empty(Config::$modSettings['gravatarOverride'])
				)
				&& (
					User::$me->allowedTo('profile_upload_avatar')
					|| (
						!Profile::$member->is_me
						&& User::$me->allowedTo('profile_extra_any')
					)
				)
			);

			return $this->allow_upload;
		}
	}

	/**
	 * @var bool
	 *
	 * Extra data used for the form that allows the user to choose an avatar.
	 */
	public bool $allow_external {
		// This &get hook lets us set a default value programmatically.
		&get {
			$this->allow_external ??= (
				(
					empty(Config::$modSettings['gravatarEnabled'])
					|| empty(Config::$modSettings['gravatarOverride'])
				)
				&& (
					User::$me->allowedTo('profile_remote_avatar')
					|| (
						!Profile::$member->is_me
						&& User::$me->allowedTo('profile_extra_any')
					)
				)
			);

			return $this->allow_external;
		}
	}

	/**
	 * @var bool
	 *
	 * Extra data used for the form that allows the user to choose an avatar.
	 */
	public bool $allow_gravatar {
		// This &get hook lets us set a default value programmatically.
		&get {
			$this->allow_gravatar ??= (
				!empty(Config::$modSettings['gravatarEnabled'])
				&& User::$me->allowedTo('profile_gravatar')
			);

			return $this->allow_gravatar;
		}
	}

	/**
	 * @var string
	 *
	 * Extra data used for the form that allows the user to choose an avatar.
	 */
	public string $choice {
		// This &get hook lets us set a default value programmatically.
		&get {
			$this->choice ??= $this->getChoice();

			return $this->choice;
		}
	}

	/**
	 * @var string
	 *
	 * Extra data used for the form that allows the user to choose an avatar.
	 */
	public string $server_pic {
		// This &get hook lets us set a default value programmatically.
		&get {
			$this->server_pic ??= (
				$this->choice === 'server_stored' && $this->original_url !== ''
				? $this->original_url
				: 'blank.png'
			);

			return $this->server_pic;
		}
	}

	/**
	 * @var string
	 *
	 * Extra data used for the form that allows the user to choose an avatar.
	 */
	public string $external {
		// This &get hook lets us set a default value programmatically.
		&get {
			$this->external ??= $this->getExternal();

			return $this->external;
		}
	}

	/**
	 * @var bool
	 *
	 * Whether this avatar's file is stored in the custom_avatar directory.
	 *
	 * For the sake of compatibility with \ArrayAccess it is possible to write
	 * to this property, but doing so is pointless because the value will be
	 * overwritten the next time the property is read.
	 *
	 * @deprecated 3.0
	 */
	public bool $custom_dir {
		&get {
			$this->custom_dir = ($this->attachment_type ?? null) === 1;

			return $this->custom_dir;
		}
	}

	/**
	 * @var string
	 *
	 * Backward compatibility alias of `(string) $this->url`.
	 *
	 * @deprecated 3.0
	 */
	public string $href {
		&get {
			// This intentionally does not use $this->href in order to keep this
			// property virtual rather than backed, because backed properties
			// cannot have both &get and set hooks.
			$href = (string) $this->url;

			return $href;
		}
		set {
			$this->url = new Url($value);
		}
	}

	/**
	 * @var string
	 *
	 * Backward compatibility alias of $this->original_url.
	 *
	 * @deprecated 3.0
	 */
	public string $avatar {
		&get => $this->original_url;
		set {
			$this->original_url = $value;
		}
	}

	/**
	 * @var string
	 *
	 * Backward compatibility alias of $this->original_url.
	 *
	 * @deprecated 3.0
	 */
	public string $name {
		&get => $this->original_url;
		set {
			$this->original_url = $value;
		}
	}

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Arbitrary data for \ArrayAccess
	 */
	private array $arbitrary_data = [];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var string
	 *
	 * Cache for self::getGravatarUrl()
	 */
	private static string $gravatar_params;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct(
		Url|string|null $url = null,
		?string $original_url = null,
		?string $filename = null,
		?int $id_attach = null,
		?int $attachment_type = null,
		?int $width = null,
		?int $height = null,
		?string $email = null,
		?int $id_member = null,
	) {
		foreach (['id_attach', 'attachment_type', 'width', 'height'] as $var) {
			if (isset(${$var})) {
				$this->{$var} = ${$var};
			}
		}

		if (isset($original_url)) {
			$this->original_url = trim($original_url);
		}

		if (isset($filename)) {
			$this->filename = ltrim(strtr(trim($filename), '\\', '/'), '/');
		}

		if (isset($id_member)) {
			$this->id_member = $id_member;
		} elseif (!empty($this->id_attach)) {
			$request = Db::$db->query(
				'SELECT id_member
				FROM {db_prefix}attachments
				WHERE id_attach = {int:id}
				LIMIT 1',
				[
					'id_attach' => $this->id_attach,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$this->id_member = (int) $row['id_member'];
			}

			Db::$db->free_result($request);
		}

		if (!isset($email) && isset($this->id_member, User::$loaded[$this->id_member])) {
			$email = User::$loaded[$this->id_member]->email;
		}

		if (EmailAddress::create($email ?? '')->isValid()) {
			$this->email = $email;
		}

		// Figure out the correct URL to use.
		if (!(($url ?? null) instanceof Url)) {
			$url = new Url(trim($url ?? ''), true);
		}

		// Special handling for Gravatar URLs.
		if ((string) $url === 'gravatar://') {
			$url = new Url('gravatar://' . ($this->email ?? 'invalid'));
		}

		if (empty(Config::$modSettings['gravatarEnabled']) && $url->isGravatar()) {
			$url = new Url('');
		}

		if ($this->constructGravatar($url)) {
			return;
		}

		// If $url is empty, can we do something useful with $original_url?
		if ((string) $url === '' && !empty($this->original_url)) {
			$url = new Url($this->original_url, true)->proxied();
		}

		// An avatar picked out of the gallery is stored as a path relative to
		// the avatars directory rather than as a URL, so anything with no
		// scheme to it is a file name and not somewhere to fetch from. Saying
		// so here means the search below finds it on its second attempt instead
		// of guessing from the URL path, which only works out for a forum that
		// is not at the root of its domain.
		if (empty($this->filename) && !$url->isValid() && !str_contains((string) $url, '://')) {
			$name = ltrim(strtr((string) $url, '\\', '/'), '/');

			if ($name !== '' && !str_contains($name, ':')) {
				$this->filename = $name;
			}
		}

		// If we still don't have a valid URL, try to figure it out.
		while (!$url->isValid()) {
			$attempt ??= 0;

			switch (++$attempt) {
				// Do we have an attachment ID number?
				case 1:
					if (!empty($this->id_attach)) {
						$attachment = new Attachment($this->id_attach);

						// Validate for security reasons.
						if (
							$attachment->type === Attachment::TYPE_AVATAR
							&& $attachment->is_image
						) {
							// If it's in the custom_avatar dir, prefer a direct link.
							if (
								!empty(Config::$modSettings['custom_avatar_url'])
								&& !empty(Config::$modSettings['custom_avatar_dir'])
								&& str_starts_with($attachment->path, Config::$modSettings['custom_avatar_dir'])
							) {
								$url = new Url(str_replace(Config::$modSettings['custom_avatar_dir'], Config::$modSettings['custom_avatar_url'], $attachment->path));
							}
							// Otherwise, use the dlattach action.
							else {
								$url = new Url($attachment->href . ';type=avatar');
							}
						}
					}

					break;

				// Is the file in the avatar gallery directory?
				case 2:
					if (
						!empty($this->filename)
						&& !empty(Config::$modSettings['avatar_url'])
						&& is_file(self::getGalleryDir() . '/' . ltrim($this->filename, '\\/'))
						&& Utils::checkMimeType(
							self::getGalleryDir() . '/' . ltrim($this->filename, '\\/'),
							'image/',
							true,
						)
					) {
						$url = new Url(Config::$modSettings['avatar_url'] . '/' . ltrim(strtr($this->filename, '\\', '/'), '/'));
					}

					break;

				// Is the file in the custom_avatar directory?
				case 3:
					if (
						!empty($this->filename)
						&& !empty(Config::$modSettings['custom_avatar_url'])
						&& !empty(Config::$modSettings['custom_avatar_dir'])
						&& is_file(Config::$modSettings['custom_avatar_dir'] . '/' . ltrim($this->filename, '\\/'))
						&& Utils::checkMimeType(
							Config::$modSettings['custom_avatar_dir'] . '/' . ltrim($this->filename, '\\/'),
							'image/',
							true,
						)
					) {
						$url = new Url(Config::$modSettings['custom_avatar_url'] . '/' . ltrim(strtr($this->filename, '\\', '/'), '/'));
					}

					break;

				// Can we use the URL path to find the file?
				case 4:
					if (
						// Only do this step if we weren't given a filename.
						empty($this->filename)
						// Only do this step if the URL has a path component.
						&& !empty($url->path)
						// Only do this step once.
						&& !isset($abs_path)
					) {
						// Resolving the canonical path of both the file and the
						// directories is important in order to ensure that we
						// don't end up navigating outside the avatar dirs.
						$abs_path = Sapi::canonicalPath(
							preg_replace('~^' . preg_quote(Url::create(Config::$boardurl)->path ?? '', '~') . '~u', '', $url->path),
							base_dir: Config::$boarddir,
						);

						$dir_paths = array_map(
							fn($path) => Sapi::canonicalPath($path) . DIRECTORY_SEPARATOR,
							array_filter([
								Config::$modSettings['custom_avatar_dir'] ?? '',
								self::getGalleryDir(),
							]),
						);

						foreach ($dir_paths as $dir_path) {
							if (str_starts_with($abs_path, $dir_path)) {
								// We have successfully extracted the filename from the path.
								$this->filename = substr($abs_path, \strlen($dir_path));
								// Reset $attempt so we can retry with our new filename.
								$attempt = 0;
								break;
							}
						}
					}

					break;

				// Right... no avatar... use our default image.
				case 5:
					if (
						!empty(Config::$modSettings['avatar_url'])
						&& is_file(self::getGalleryDir() . '/default.png')
					) {
						$url = new Url(Config::$modSettings['avatar_url'] . '/default.png');
					}

					break;

				// Last ditch fallback is a transparent 1x1 GIF.
				//
				// This leaves the loop rather than the switch, because there is
				// nothing left to try and because a data URI is not a URL that
				// Url::isValid() will accept, so the condition the loop tests
				// can never become false from here.
				default:
					$url = new Url('data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
					break 2;
			}
		}

		$this->url = $url;

		$this->integrateConstructAvatar();
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Return a Gravatar URL based on
	 * - the supplied email address,
	 * - the global maximum rating,
	 * - the global default fallback,
	 * - maximum sizes as set in the admin panel.
	 *
	 * It is SSL aware, and caches most of the parameters.
	 *
	 * @param string $email The user's email address
	 * @return string The gravatar URL
	 */
	public static function getGravatarUrl(string $email): string
	{
		if (!isset(self::$gravatar_params)) {
			$ratings = ['G', 'PG', 'R', 'X'];
			$defaults = ['mm', 'identicon', 'monsterid', 'wavatar', 'retro', 'blank'];

			$params = [
				'rating' => \in_array(Config::$modSettings['gravatarMaxRating'] ?? null, $ratings) ? Config::$modSettings['gravatarMaxRating'] : 'PG',
				'default' => \in_array(Config::$modSettings['gravatarDefault'] ?? null, $defaults) ? Config::$modSettings['gravatarDefault'] : 'mm',
			];

			$size = min(
				(int) (Config::$modSettings['avatar_max_width_external'] ?? 0) > 0 ? (int) Config::$modSettings['avatar_max_width_external'] : INF,
				(int) (Config::$modSettings['avatar_max_height_external'] ?? 0) > 0 ? (int) Config::$modSettings['avatar_max_height_external'] : INF,
			);

			if ($size < INF) {
				$params['s'] = $size;
			}

			self::$gravatar_params = http_build_query($params, arg_separator: '&');
		}

		return 'https://secure.gravatar.com/avatar/' . md5(Utils::strtolower($email)) . '?' . self::$gravatar_params;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Helper for constructor that handles Gravatar URLs.
	 *
	 * @param Url $url The hypothetical URL for the avatar.
	 * @return bool Whether $this->url was successfully set to a Gravatar URL.
	 */
	protected function constructGravatar(Url $url): bool
	{
		// Gravatar is disabled.
		if (empty(Config::$modSettings['gravatarEnabled'])) {
			return false;
		}

		// If it's already a *finalized* Gravatar URL, we're done.
		if ($url->isWebsite() && $url->isGravatar()) {
			$this->url = $url;
			$this->integrateConstructAvatar();

			return true;
		}

		// If this isn't a Gravatar and Gravatars are not mandatory, bail out.
		if (
			($url->scheme ?? null) !== 'gravatar'
			&& empty(Config::$modSettings['gravatarOverride'])
		) {
			return false;
		}

		// Ensure we are working with a well-formed gravatar://... URL.
		if (
			($url->scheme ?? null) !== 'gravatar'
			// Do we need to override the embedded email address?
			|| empty(Config::$modSettings['gravatarAllowExtraEmail'])
			|| !isset($url->user, $url->host)
			|| !EmailAddress::create($url->user . '@' . $url->host)->isValid()
		) {
			$url = new Url('gravatar://' . ($this->email ?? 'invalid'), true);
		}

		// If the Gravatar URL contains a well formed email address, use it.
		if (isset($url->user, $url->host)) {
			$this->url = new Url(self::getGravatarUrl($url->user . '@' . $url->host));

			// If we didn't already have an email address, we do now.
			$this->email ??= $url->user . '@' . $url->host;
		}
		// Next try our default image.
		elseif (isset(Config::$modSettings['avatar_url'])) {
			$this->url = new Url(Config::$modSettings['avatar_url'] . '/default.png');
		}
		// Last ditch fallback is a transparent 1x1 GIF.
		else {
			$this->url = new Url('data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
		}

		$this->integrateConstructAvatar();

		return true;
	}

	/**
	 * Calls the integrate_construct_avatar hook and, if necessary, the
	 * deprecated integrate_set_avatar_data hook.
	 */
	protected function integrateConstructAvatar(): void
	{
		// This is the preferred hook.
		IntegrationHook::call('integrate_construct_avatar', [$this]);

		// Don't bother with the deprecated hook unless we must.
		if (empty(Config::$backward_compatibility) || empty(Config::$modSettings['integrate_set_avatar_data'])) {
			return;
		}

		// Need to do some silliness for backward compatibility in this hook.
		$url = (string) $this->url;

		$map = [
			'avatar' => 'original_url',
			'email' => 'email',
			'filename' => 'filename',
		];

		foreach ($map as $key => $prop) {
			if (isset($this->{$prop})) {
				$data[$key] = $this->{$prop};
			}
		}

		IntegrationHook::call('integrate_set_avatar_data', [&$url, &$data]);

		$this->url = new Url((string) $url, true);

		foreach ($map as $key => $prop) {
			if (!empty($data[$key]) && $data[$key] !== ($this->{$prop} ?? null)) {
				$this->{$prop} = (string) $data[$key];
			}
		}
	}

	/**
	 * Callback for $this->choice::get
	 *
	 * @return string
	 */
	private function getChoice(): string
	{
		if ($this->allow_gravatar && (!empty(Config::$modSettings['gravatarOverride']) || $this->url->isGravatar())) {
			return 'gravatar';
		}

		if ($this->allow_upload && $this->url->isValid() && $this->id_attach > 0) {
			return 'upload';
		}

		if (
			$this->allow_external
			&& $this->url->isWebsite()
			&& stripos($this->original_url, 'http') === 0
		) {
			return 'external';
		}

		if (
			$this->allow_server_stored
			&& $this->url->isValid()
			&& stripos($this->original_url, 'http') === false
			&& $this->original_url !== ''
			&& file_exists(Config::$modSettings['avatar_directory'] . '/' . $this->original_url)
		) {
			return 'server_stored';
		}

		return 'none';
	}

	/**
	 * Callback for $this->external::get
	 *
	 * @return string
	 */
	private function getExternal(): string
	{
		return match ($this->choice) {
			'gravatar' => (
				(
					empty(Config::$modSettings['gravatarAllowExtraEmail'])
					|| (
						!empty(Config::$modSettings['gravatarOverride'])
						&& !str_starts_with($this->original_url, 'gravatar://')
					)
				)
				? (User::$loaded[$this->id_member]?->email ?? $this->email ?? '')
				: substr($this->original_url, 11)
			),
			'external' => $this->original_url,
			default => 'http://',
		};
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * The directory that holds the gallery of avatars members can choose from.
	 *
	 * This is the directory the admin nominated, which is the one the gallery
	 * is listed from and the one a chosen avatar is checked against before it
	 * is saved. Config::$modSettings['avatar_url'] is the address of the same
	 * directory, so the two have to name the same place or a member's choice
	 * resolves to an address with nothing behind it.
	 *
	 * @return string Path to the avatar gallery directory.
	 */
	private static function getGalleryDir(): string
	{
		return !empty(Config::$modSettings['avatar_directory'])
			? Config::$modSettings['avatar_directory']
			: Config::$boarddir . '/avatars';
	}
}
