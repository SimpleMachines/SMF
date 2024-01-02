<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF;

use SMF\Actions\Admin\ACP;
use SMF\Db\DatabaseApi as Db;
use SMF\Graphics\Image;

/**
 * This class represents a file attachment.
 *
 * Also provides methods to handle the uploading and creation of attachments
 * as well as the auto-management of the attachment directories.
 */
class Attachment implements \ArrayAccess
{
	use BackwardCompatibility;
	use ArrayAccessHelper;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'automanageCheckDirectory' => 'automanage_attachments_check_directory',
			'automanageCreateDirectory' => 'automanage_attachments_create_directory',
			'automanageBySpace' => 'automanage_attachments_by_space',
			'process' => 'processAttachments',
			'check' => 'attachmentChecks',
			'create' => 'createAttachment',
			'assign' => 'assignAttachments',
			'approve' => 'ApproveAttachments',
			'remove' => 'removeAttachments',
			'parseAttachBBC' => 'parseAttachBBC',
			'getAttachMsgInfo' => 'getAttachMsgInfo',
			'loadAttachmentContext' => 'loadAttachmentContext',
			'prepareByMsg' => 'prepareAttachsByMsg',
			'createHash' => 'createHash',
			'getFilePath' => 'getFilePath',
			'getAttachmentFilename' => 'getAttachmentFilename',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	public const APPROVED_ANY = -1;
	public const APPROVED_FALSE = 0;
	public const APPROVED_TRUE = 1;

	public const TYPE_ANY = -1;
	public const TYPE_STANDARD = 0;
	public const TYPE_AVATAR = 1;
	public const TYPE_THUMB = 3;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * This attachment's ID number.
	 */
	public int $id;

	/**
	 * @var int
	 *
	 * ID number of this attachment's thumbnail.
	 */
	public int $thumb = 0;

	/**
	 * @var int
	 *
	 * ID number of the message that this attachment is attached to.
	 */
	public int $msg = 0;

	/**
	 * @var int
	 *
	 * ID number of the member that this attachment is an avatar for.
	 */
	public int $member = 0;

	/**
	 * @var int
	 *
	 * ID number of the folder that this attachment's file is stored in.
	 */
	public int $folder = 1;

	/**
	 * @var int
	 *
	 * The type of this attachment.
	 */
	public int $type = 0;

	/**
	 * @var string
	 *
	 * The unique hash for this attachment.
	 */
	public string $file_hash = '';

	/**
	 * @var string
	 *
	 * The name of this attachment.
	 */
	public string $filename = '';

	/**
	 * @var string
	 *
	 * The file extension of this attachment.
	 */
	public string $fileext = '';

	/**
	 * @var int
	 *
	 * The file size of this attachment.
	 */
	public int $size = 0;

	/**
	 * @var int
	 *
	 * How many times this attachment has been downloaded.
	 */
	public int $downloads = 0;

	/**
	 * @var int
	 *
	 * The image width of this attachment.
	 */
	public int $width = 0;

	/**
	 * @var int
	 *
	 * The image height of this attachment.
	 */
	public int $height = 0;

	/**
	 * @var string
	 *
	 * The MIME type of this attachment.
	 */
	public string $mime_type = '';

	/**
	 * @var bool
	 *
	 * Whether this attachment has been approved.
	 */
	public bool $approved = true;

	/**
	 * @var int
	 *
	 * ID number of the topic that this attachment's message is in.
	 */
	public int $topic = 0;

	/**
	 * @var int
	 *
	 * ID number of the board that this attachment's message is in.
	 */
	public int $board = 0;

	/**
	 * @var string
	 *
	 * Version of $this->filename with escaped HTML special characters.
	 */
	public string $name = '';

	/**
	 * @var string
	 *
	 * Path to the attachment file on disk.
	 */
	public string $path = '';

	/**
	 * @var string
	 *
	 * Download URL for this attachment.
	 */
	public string $href = '';

	/**
	 * @var string
	 *
	 * HTML link to download this attachment.
	 */
	public string $link = '';

	/**
	 * @var bool
	 *
	 * Whether this attachment is an image.
	 */
	public bool $is_image = false;

	/**
	 * @var bool
	 *
	 * Whether this attachment is an image with an embedded thumbnail.
	 */
	public bool $embedded_thumb = false;

	/**
	 * @var int
	 *
	 * Width of the thumbnail image, if applicable.
	 */
	public int $thumb_width = 0;

	/**
	 * @var int
	 *
	 * Height of the thumbnail image, if applicable.
	 */
	public int $thumb_height = 0;

	/**
	 * @var string
	 *
	 * Human-friendly representation of the file size.
	 */
	public string $formatted_size = '';

	/**
	 * @var bool
	 *
	 * Whether the attachment's file exists.
	 */
	public bool $exists = false;

	/**
	 * @var int
	 *
	 * Last modification time of the attachment's file.
	 */
	public int $mtime = 0;

	/**
	 * @var string
	 *
	 * The attachment's entity tag.
	 */
	public string $etag = '';

	/**
	 * @var string
	 *
	 * Indicates whether the data was loaded by SMF's native attachments
	 * system or by an alternative system.
	 */
	public string $source = '';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * All loaded instances of this class.
	 */
	public static array $loaded = [];

	/**
	 * @var array
	 *
	 * Loaded instances of this class, grouped by message.
	 */
	public static array $loadedByMsg = [];

	/**
	 * @var array
	 *
	 * Loaded instances of this class, grouped by member.
	 */
	public static array $loadedByMember = [];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Alternate names for some object properties.
	 */
	protected array $prop_aliases = [
		'id_attach' => 'id',
		'attachID' => 'id',
		'id_thumb' => 'thumb',
		'id_msg' => 'msg',
		'id_member' => 'member',
		'id_folder' => 'folder',
		'id_topic' => 'topic',
		'id_board' => 'board',
		'attachment_type' => 'type',
		'attachment_approved' => 'approved',
		'is_approved' => 'approved',
		'filesize' => 'size',
		'byte_size' => 'size',
		'filePath' => 'path',
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param int $id The ID number of the attachment.
	 * @param array $props Properties to set for this attachment.
	 * @return object An instance of this class.
	 */
	public function __construct(int $id = 0, array $props = [])
	{
		if (empty($id) && empty($props)) {
			return;
		}

		// Given an ID but no properties, so query for the data.
		if (!empty($id) && empty($props)) {
			$request = Db::$db->query(
				'',
				'SELECT *
				FROM {db_prefix}attachments
				WHERE id_attach = {int:id}
				LIMIT 1',
				[
					'id' => $this->id,
				],
			);

			if (Db::$db->num_rows($request) !== 1) {
				Db::$db->free_result($request);

				return;
			}
			$props = Db::$db->fetch_assoc($request);
			Db::$db->free_result($request);
		}

		$this->id = $id;
		$this->set($props);

		// Is this an image attachment?
		$image = new Image($this->path);

		if (!empty($image->source)) {
			$this->is_image = true;
			$this->mime_type = $image->mime_type;
			$this->width = $image->width;
			$this->height = $image->height;
			$this->embedded_thumb = $image->embedded_thumb;
		}

		unset($image);

		// If we still don't have a MIME type, get it now.
		if (empty($this->mime_type)) {
			$this->mime_type = Utils::getMimeType($this->path, true);
		}

		// SVGs are special.
		if ($this->mime_type === 'image/svg+xml') {
			// SVG is its own thumbnail.
			$this->thumb = $id;

			// For SVGs, we don't need to calculate thumbnail size precisely.
			$this->thumb_width = min($this->width, !empty(Config::$modSettings['attachmentThumbWidth']) ? Config::$modSettings['attachmentThumbWidth'] : 1000);
			$this->thumb_height = min($this->height, !empty(Config::$modSettings['attachmentThumbHeight']) ? Config::$modSettings['attachmentThumbHeight'] : 1000);

			// Must set the thumbnail's CSS dimensions manually.
			Theme::addInlineCss('img#thumb_' . $this->thumb . ':not(.original_size) {width: ' . $this->thumb_width . 'px; height: ' . $this->thumb_height . 'px;}');
		}

		self::$loaded[$id] = $this;

		if (!empty($this->msg)) {
			self::$loadedByMsg[$this->msg][$id] = $this;
		}

		if (!empty($this->member)) {
			self::$loadedByMember[$this->member][$id] = $this;
		}
	}

	/**
	 * Sets $this->exists, $this->mtime, $this->size, and $this->etag.
	 */
	public function setFileProperties(): void
	{
		if (!isset($this->path)) {
			$this->setPath();
		}

		if (!isset($this->path)) {
			return;
		}

		$this->exists = file_exists($this->path);

		// Ensure variant attachment compatibility.
		if (!$this->exists) {
			$pathinfo = pathinfo($this->path);

			if (isset($pathinfo['extension'])) {
				$this->path = substr($this->path, 0, -(strlen($pathinfo['extension']) + 1));
			}

			$this->exists = file_exists($this->path);
		}

		$this->mtime = $this->exists ? filemtime($this->path) : 0;
		$this->size = $this->exists ? filesize($this->path) : 0;
		$this->etag = $this->exists ? sha1_file($this->path) : '';
	}

	/**
	 * Sets custom properties.
	 *
	 * @param string $prop The property name.
	 * @param mixed $value The value to set.
	 */
	public function __set(string $prop, $value): void
	{
		$this->customPropertySet($prop, $value);

		$this->href = Config::$scripturl . '?action=dlattach;attach=' . $this->id;

		if (in_array($this->prop_aliases[$prop] ?? $prop, ['id', 'file_hash', 'folder'])) {
			$this->setPath();
		}

		if (($this->prop_aliases[$prop] ?? $prop) === 'filename') {
			$this->name = Utils::htmlspecialchars($value);
			$this->link = '<a href="' . $this->href . '" class="bbc_link">' . $this->name . '</a>';
		}

		if (($this->prop_aliases[$prop] ?? $prop) === 'size') {
			Lang::load('index');

			$this->formatted_size = ($this->size < 1024000) ? round($this->size / 1024, 2) . ' ' . Lang::$txt['kilobyte'] : round($this->size / 1024 / 1024, 2) . ' ' . Lang::$txt['megabyte'];
		}
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Loads existing attachments by ID number.
	 *
	 * @param array $ids The ID numbers of one or more attachments.
	 * @param int $approval_status One of this class's APPROVED_* constants.
	 *     Default: self::APPROVED_ANY.
	 * @param int $type One of this class's TYPE_* constants.
	 *     Default: self::TYPE_ANY.
	 * @param bool $get_thumbs Whether to get the thumbnail image dimensions.
	 *     Default: true.
	 * @return array Instances of this class for the loaded attachments.
	 */
	public static function load(array|int $ids, int $approval_status = self::APPROVED_ANY, int $type = self::TYPE_ANY, bool $get_thumbs = true): array
	{
		// Keep track of the ones we load during this call.
		$loaded = [];

		$ids = array_filter(array_map('intval', (array) $ids));
		$approval_status = !in_array($approval_status, [self::APPROVED_TRUE, self::APPROVED_FALSE]) ? self::APPROVED_ANY : $approval_status;

		if (empty($ids)) {
			return $loaded;
		}

		// Don't reload unnecessarily.
		foreach ($ids as $key => $id) {
			if (isset(self::$loaded[$id])) {
				$loaded[$id] = self::$loaded[$id];
				unset($ids[$key]);
			}
		}

		// Load whatever hasn't been loaded already.
		if (!empty($ids)) {
			$selects = [
				'a.*',
				'COALESCE (m.id_topic, 0) AS id_topic',
				'COALESCE (m.id_board, 0) AS id_board',
			];

			$from = '{db_prefix}attachments AS a';
			$joins = ['LEFT JOIN {db_prefix}messages AS m ON (a.id_msg = m.id_msg)'];
			$where = ['a.id_attach IN ({array_int:ids})'];
			$order = [];
			$limit = 0;
			$params = ['ids' => $ids];

			if ($type !== self::TYPE_ANY) {
				$where[] = 'a.attachment_type = {int:type}';
				$params['type'] = $type;
			}

			if ($approval_status !== self::APPROVED_ANY) {
				$where[] = 'a.approved = {int:approved}';
				$params['approved'] = $approval_status;
			}

			if ($get_thumbs) {
				$selects[] = 'thumb.width AS thumb_width';
				$selects[] = 'thumb.height AS thumb_height';
				$joins[] = 'LEFT JOIN {db_prefix}attachments AS thumb ON (a.id_thumb = thumb.id_attach)';
			}

			IntegrationHook::call('integrate_attachment_load', [&$selects, &$params, &$from, &$joins, &$where, &$order, &$limit]);

			foreach (self::queryData($selects, $params, $from, $joins, $where, $order, $limit) as $props) {
				$id = (int) $props['id_attach'];

				$props = array_filter($props, fn ($prop) => !is_null($prop));

				$loaded[$id] = new self($id, $props);
			}
		}

		// For convenience, sort by ID number.
		ksort($loaded);

		// Return the instances we just loaded.
		return $loaded;
	}

	/**
	 * Loads existing attachments by message ID.
	 *
	 * @param array|int $msgs The ID numbers of one or more messages.
	 * @param int $approval_status One of this class's APPROVED_* constants.
	 *     Default: self::APPROVED_TRUE.
	 * @param int $type One of this class's TYPE_* constants.
	 *     Default: self::TYPE_STANDARD.
	 * @param bool $get_thumbs Whether to get the thumbnail image dimensions.
	 *     Default: true.
	 * @return array Instances of this class for the loaded attachments.
	 */
	public static function loadByMsg(array|int $msgs, int $approval_status = self::APPROVED_TRUE, int $type = self::TYPE_STANDARD, bool $get_thumbs = true): array
	{
		$loaded = [];

		$msgs = array_filter(array_map('intval', (array) $msgs));
		$approval_status = !in_array($approval_status, [self::APPROVED_TRUE, self::APPROVED_FALSE]) ? self::APPROVED_ANY : $approval_status;

		if (empty($msgs)) {
			return $loaded;
		}

		$selects = [
			'a.*',
			'm.id_topic',
			'm.id_board',
		];

		$from = '{db_prefix}attachments AS a';
		$joins = ['INNER JOIN {db_prefix}messages AS m ON (a.id_msg = m.id_msg)'];
		$where = ['a.id_msg IN ({array_int:msgs})'];
		$order = [];
		$limit = 0;
		$params = ['msgs' => $msgs];

		if ($type !== self::TYPE_ANY) {
			$where[] = 'a.attachment_type = {int:type}';
			$params['type'] = $type;
		}

		if ($approval_status !== self::APPROVED_ANY) {
			$where[] = 'a.approved = {int:approved}';
			$params['approved'] = $approval_status;
		}

		if ($get_thumbs) {
			$selects[] = 'thumb.width AS thumb_width';
			$selects[] = 'thumb.height AS thumb_height';
			$joins[] = 'LEFT JOIN {db_prefix}attachments AS thumb ON (a.id_thumb = thumb.id_attach)';
		}

		IntegrationHook::call('integrate_attachment_loadbymsg', [&$selects, &$params, &$from, &$joins, &$where, &$order, &$limit]);

		foreach (self::queryData($selects, $params, $from, $joins, $where, $order, $limit) as $props) {
			$id = (int) $props['id_attach'];

			$props = array_filter($props, fn ($prop) => !is_null($prop));

			// Don't reload unnecessarily.
			if (isset(self::$loaded[$id])) {
				$loaded[$id] = self::$loaded[$id];
			} else {
				$loaded[$id] = new self($id, $props);
			}
		}

		ksort($loaded);

		return $loaded;
	}

	/**
	 * Loads existing attachments by member ID.
	 *
	 * @param int $approval_status One of this class's APPROVED_* constants.
	 *     Default: self::APPROVED_TRUE.
	 * @param bool $get_thumbs Whether to get the thumbnail image dimensions.
	 *     Default: true.
	 * @param array|int $ids The ID numbers of one or more members.
	 * @return array Instances of this class for the loaded attachments.
	 */
	public static function loadByMember(array|int $members, int $approval_status = self::APPROVED_TRUE, bool $get_thumbs = true): array
	{
		$loaded = [];

		$members = array_filter(array_map('intval', (array) $members));
		$approval_status = !in_array($approval_status, [self::APPROVED_TRUE, self::APPROVED_FALSE]) ? self::APPROVED_ANY : $approval_status;

		if (empty($members)) {
			return $loaded;
		}

		$selects = ['a.*'];
		$from = '{db_prefix}attachments AS a';
		$joins = [];
		$where = ['a.id_member IN ({array_int:members})'];
		$order = [];
		$limit = 0;
		$params = ['members' => $members];

		if ($approval_status !== self::APPROVED_ANY) {
			$where[] = 'a.approved = {int:approved}';
			$params['approved'] = $approval_status;
		}

		if ($get_thumbs) {
			$selects[] = 'thumb.width AS thumb_width';
			$selects[] = 'thumb.height AS thumb_height';
			$joins[] = 'LEFT JOIN {db_prefix}attachments AS thumb ON (a.id_thumb = thumb.id_attach)';
		}

		IntegrationHook::call('integrate_attachment_loadbymember', [&$selects, &$params, &$from, &$joins, &$where, &$order, &$limit]);

		foreach (self::queryData($selects, $params, $from, $joins, $where, $order, $limit) as $props) {
			$id = (int) $props['id_attach'];

			$props = array_filter($props, fn ($prop) => !is_null($prop));

			// Don't reload unnecessarily.
			if (isset(self::$loaded[$id])) {
				$loaded[$id] = self::$loaded[$id];
			} else {
				$loaded[$id] = new self($id, $props);
			}
		}

		ksort($loaded);

		return $loaded;
	}

	/**
	 * Check if the current directory is still valid or not.
	 * If not creates the new directory
	 *
	 * @return void|bool False if any error occurred
	 */
	public static function automanageCheckDirectory()
	{
		// Not pretty, but since we don't want folders created for every post. It'll do unless a better solution can be found.
		if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'admin') {
			$doit = true;
		} elseif (empty(Config::$modSettings['automanage_attachments']) || !isset($_FILES)) {
			$doit = false;
		} elseif (isset($_FILES['attachment'])) {
			$doit = false;

			foreach ($_FILES['attachment']['tmp_name'] as $dummy) {
				if (!empty($dummy)) {
					$doit = true;
					break;
				}
			}
		}

		if (empty($doit)) {
			return;
		}

		$year = date('Y');
		$month = date('m');

		$rand = bin2hex(random_bytes(1));
		$rand1 = $rand[1];
		$rand = $rand[0];

		if (!empty(Config::$modSettings['attachment_basedirectories']) && !empty(Config::$modSettings['use_subdirectories_for_attachments'])) {
			if (!is_array(Config::$modSettings['attachment_basedirectories'])) {
				Config::$modSettings['attachment_basedirectories'] = Utils::jsonDecode(Config::$modSettings['attachment_basedirectories'], true);
			}

			$base_dir = array_search(Config::$modSettings['basedirectory_for_attachments'], Config::$modSettings['attachment_basedirectories']);
		} else {
			$base_dir = 0;
		}

		if (Config::$modSettings['automanage_attachments'] == 1) {
			if (!isset(Config::$modSettings['last_attachments_directory'])) {
				Config::$modSettings['last_attachments_directory'] = [];
			}

			if (!is_array(Config::$modSettings['last_attachments_directory'])) {
				Config::$modSettings['last_attachments_directory'] = Utils::jsonDecode(Config::$modSettings['last_attachments_directory'], true);
			}

			if (!isset(Config::$modSettings['last_attachments_directory'][$base_dir])) {
				Config::$modSettings['last_attachments_directory'][$base_dir] = 0;
			}
		}

		$basedirectory = (!empty(Config::$modSettings['use_subdirectories_for_attachments']) ? (Config::$modSettings['basedirectory_for_attachments']) : Config::$boarddir);

		// Just to be sure: I don't want directory separators at the end
		$sep = (DIRECTORY_SEPARATOR === '\\') ? '\\/' : DIRECTORY_SEPARATOR;
		$basedirectory = rtrim($basedirectory, $sep);

		switch (Config::$modSettings['automanage_attachments']) {
			case 1:
				$updir = $basedirectory . DIRECTORY_SEPARATOR . 'attachments_' . (Config::$modSettings['last_attachments_directory'][$base_dir] ?? 0);
				break;

			case 2:
				$updir = $basedirectory . DIRECTORY_SEPARATOR . $year;
				break;

			case 3:
				$updir = $basedirectory . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month;
				break;

			case 4:
				$updir = $basedirectory . DIRECTORY_SEPARATOR . (empty(Config::$modSettings['use_subdirectories_for_attachments']) ? 'attachments-' : 'random_') . $rand;
				break;

			case 5:
				$updir = $basedirectory . DIRECTORY_SEPARATOR . (empty(Config::$modSettings['use_subdirectories_for_attachments']) ? 'attachments-' : 'random_') . $rand . DIRECTORY_SEPARATOR . $rand1;
				break;

			default:
				$updir = '';
		}

		if (!is_array(Config::$modSettings['attachmentUploadDir'])) {
			Config::$modSettings['attachmentUploadDir'] = Utils::jsonDecode(Config::$modSettings['attachmentUploadDir'], true);
		}

		if (!in_array($updir, Config::$modSettings['attachmentUploadDir']) && !empty($updir)) {
			$outputCreation = self::automanageCreateDirectory($updir);
		} elseif (in_array($updir, Config::$modSettings['attachmentUploadDir'])) {
			$outputCreation = true;
		}

		if ($outputCreation) {
			Config::$modSettings['currentAttachmentUploadDir'] = array_search($updir, Config::$modSettings['attachmentUploadDir']);
			Utils::$context['attach_dir'] = Config::$modSettings['attachmentUploadDir'][Config::$modSettings['currentAttachmentUploadDir']];

			Config::updateModSettings([
				'currentAttachmentUploadDir' => Config::$modSettings['currentAttachmentUploadDir'],
			]);
		}

		return $outputCreation;
	}

	/**
	 * Creates a directory
	 *
	 * @param string $updir The directory to be created
	 *
	 * @return bool False on errors
	 */
	public static function automanageCreateDirectory($updir)
	{
		$tree = self::getDirectoryTreeElements($updir);
		$count = count($tree);

		$directory = self::initDir($tree, $count);

		if ($directory === false) {
			// Maybe it's just the folder name
			$tree = self::getDirectoryTreeElements(Config::$boarddir . DIRECTORY_SEPARATOR . $updir);
			$count = count($tree);

			$directory = self::initDir($tree, $count);

			if ($directory === false) {
				return false;
			}
		}

		$directory .= DIRECTORY_SEPARATOR . array_shift($tree);

		while ($count != -1) {
			if (self::isPathAllowed($directory) && !@is_dir($directory)) {
				if (!@mkdir($directory, 0755)) {
					Utils::$context['dir_creation_error'] = 'attachments_no_create';

					return false;
				}
			}

			$directory .= DIRECTORY_SEPARATOR . array_shift($tree);
			$count--;
		}

		// Check if the dir is writable.
		if (!Utils::makeWritable($directory)) {
			Utils::$context['dir_creation_error'] = 'attachments_no_write';

			return false;
		}

		// Everything seems fine...let's create the .htaccess
		if (!file_exists($directory . DIRECTORY_SEPARATOR . '.htaccess')) {
			Security::secureDirectory($updir, true);
		}

		$sep = (DIRECTORY_SEPARATOR === '\\') ? '\\/' : DIRECTORY_SEPARATOR;
		$updir = rtrim($updir, $sep);

		// Only update if it's a new directory
		if (!in_array($updir, Config::$modSettings['attachmentUploadDir'])) {
			Config::$modSettings['currentAttachmentUploadDir'] = max(array_keys(Config::$modSettings['attachmentUploadDir'])) + 1;

			Config::$modSettings['attachmentUploadDir'][Config::$modSettings['currentAttachmentUploadDir']] = $updir;

			Config::updateModSettings([
				'attachmentUploadDir' => Utils::jsonEncode(Config::$modSettings['attachmentUploadDir']),
				'currentAttachmentUploadDir' => Config::$modSettings['currentAttachmentUploadDir'],
			], true);

			Config::$modSettings['attachmentUploadDir'] = Utils::jsonDecode(Config::$modSettings['attachmentUploadDir'], true);
		}

		Utils::$context['attach_dir'] = Config::$modSettings['attachmentUploadDir'][Config::$modSettings['currentAttachmentUploadDir']];

		return true;
	}

	/**
	 * Called when a directory space limit is reached.
	 * Creates a new directory and increments the directory suffix number.
	 *
	 * @return void|bool False on errors, true if successful, nothing if auto-management of attachments is disabled
	 */
	public static function automanageBySpace()
	{
		if (!isset(Config::$modSettings['automanage_attachments']) || (!empty(Config::$modSettings['automanage_attachments']) && Config::$modSettings['automanage_attachments'] != 1)) {
			return;
		}

		$basedirectory = !empty(Config::$modSettings['use_subdirectories_for_attachments']) ? Config::$modSettings['basedirectory_for_attachments'] : Config::$boarddir;

		// Just to be sure: I don't want directory separators at the end
		$sep = (DIRECTORY_SEPARATOR === '\\') ? '\\/' : DIRECTORY_SEPARATOR;
		$basedirectory = rtrim($basedirectory, $sep);

		// Get the current base directory
		if (!empty(Config::$modSettings['use_subdirectories_for_attachments']) && !empty(Config::$modSettings['attachment_basedirectories'])) {
			$base_dir = array_search(Config::$modSettings['basedirectory_for_attachments'], Config::$modSettings['attachment_basedirectories']);
			$base_dir = !empty(Config::$modSettings['automanage_attachments']) ? $base_dir : 0;
		} else {
			$base_dir = 0;
		}

		// Get the last attachment directory for that base directory
		if (empty(Config::$modSettings['last_attachments_directory'][$base_dir])) {
			Config::$modSettings['last_attachments_directory'][$base_dir] = 0;
		}

		// And increment it.
		Config::$modSettings['last_attachments_directory'][$base_dir]++;

		$updir = $basedirectory . DIRECTORY_SEPARATOR . 'attachments_' . Config::$modSettings['last_attachments_directory'][$base_dir];

		if (self::automanageCreateDirectory($updir)) {
			Config::$modSettings['currentAttachmentUploadDir'] = array_search($updir, Config::$modSettings['attachmentUploadDir']);

			Config::updateModSettings([
				'last_attachments_directory' => Utils::jsonEncode(Config::$modSettings['last_attachments_directory']),
				'currentAttachmentUploadDir' => Config::$modSettings['currentAttachmentUploadDir'],
			]);

			Config::$modSettings['last_attachments_directory'] = Utils::jsonDecode(Config::$modSettings['last_attachments_directory'], true);

			return true;
		}

		return false;
	}

	/**
	 * Moves an attachment to the proper directory and set the relevant data into $_SESSION['temp_attachments']
	 */
	public static function process()
	{
		// Make sure we're uploading to the right place.
		if (!empty(Config::$modSettings['automanage_attachments'])) {
			self::automanageCheckDirectory();
		}

		if (!is_array(Config::$modSettings['attachmentUploadDir'])) {
			Config::$modSettings['attachmentUploadDir'] = Utils::jsonDecode(Config::$modSettings['attachmentUploadDir'], true);
		}

		Utils::$context['attach_dir'] = Config::$modSettings['attachmentUploadDir'][Config::$modSettings['currentAttachmentUploadDir']];

		// Is the attachments folder actually there?
		if (!empty(Utils::$context['dir_creation_error'])) {
			$initial_error = Utils::$context['dir_creation_error'];
		} elseif (!is_dir(Utils::$context['attach_dir'])) {
			$initial_error = 'attach_folder_warning';
			ErrorHandler::log(sprintf(Lang::$txt['attach_folder_admin_warning'], Utils::$context['attach_dir']), 'critical');
		}

		if (!isset($initial_error) && !isset(Utils::$context['attachments'])) {
			// If this isn't a new post, check the current attachments.
			if (isset($_REQUEST['msg'])) {
				$request = Db::$db->query(
					'',
					'SELECT COUNT(*), SUM(size)
					FROM {db_prefix}attachments
					WHERE id_msg = {int:id_msg}
						AND attachment_type = {int:attachment_type}',
					[
						'id_msg' => (int) $_REQUEST['msg'],
						'attachment_type' => 0,
					],
				);
				list(Utils::$context['attachments']['quantity'], Utils::$context['attachments']['total_size']) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);
			} else {
				Utils::$context['attachments'] = [
					'quantity' => 0,
					'total_size' => 0,
				];
			}
		}

		// Hmm. There are still files in session.
		$ignore_temp = false;

		if (!empty($_SESSION['temp_attachments']['post']['files']) && count($_SESSION['temp_attachments']) > 1) {
			// Let's try to keep them. But...
			$ignore_temp = true;

			// If new files are being added. We can't ignore those
			foreach ($_FILES['attachment']['tmp_name'] as $dummy) {
				if (!empty($dummy)) {
					$ignore_temp = false;
					break;
				}
			}

			// Need to make space for the new files. So, bye bye.
			if (!$ignore_temp) {
				foreach ($_SESSION['temp_attachments'] as $attachID => $attachment) {
					if (strpos($attachID, 'post_tmp_' . User::$me->id) !== false) {
						unlink($attachment['tmp_name']);
					}
				}

				Utils::$context['we_are_history'] = Lang::$txt['error_temp_attachments_flushed'];
				$_SESSION['temp_attachments'] = [];
			}
		}

		if (!isset($_FILES['attachment']['name'])) {
			$_FILES['attachment']['tmp_name'] = [];
		}

		if (!isset($_SESSION['temp_attachments'])) {
			$_SESSION['temp_attachments'] = [];
		}

		// Remember where we are at. If it's anywhere at all.
		if (!$ignore_temp) {
			$_SESSION['temp_attachments']['post'] = [
				'msg' => !empty($_REQUEST['msg']) ? $_REQUEST['msg'] : 0,
				'last_msg' => !empty($_REQUEST['last_msg']) ? $_REQUEST['last_msg'] : 0,
				'topic' => !empty($topic) ? $topic : 0,
				'board' => !empty($board) ? $board : 0,
			];
		}

		// If we have an initial error, lets just display it.
		if (!empty($initial_error)) {
			$_SESSION['temp_attachments']['initial_error'] = $initial_error;

			// And delete the files 'cos they ain't going nowhere.
			foreach ($_FILES['attachment']['tmp_name'] as $n => $dummy) {
				if (file_exists($_FILES['attachment']['tmp_name'][$n])) {
					unlink($_FILES['attachment']['tmp_name'][$n]);
				}
			}

			$_FILES['attachment']['tmp_name'] = [];
		}

		// Loop through $_FILES['attachment'] array and move each file to the current attachments folder.
		foreach ($_FILES['attachment']['tmp_name'] as $n => $dummy) {
			if ($_FILES['attachment']['name'][$n] == '') {
				continue;
			}

			// First, let's first check for PHP upload errors.
			$errors = [];

			if (!empty($_FILES['attachment']['error'][$n])) {
				if ($_FILES['attachment']['error'][$n] == 2) {
					$errors[] = ['file_too_big', [Config::$modSettings['attachmentSizeLimit']]];
				} elseif ($_FILES['attachment']['error'][$n] == 6) {
					ErrorHandler::log($_FILES['attachment']['name'][$n] . ': ' . Lang::$txt['php_upload_error_6'], 'critical');
				} else {
					ErrorHandler::log($_FILES['attachment']['name'][$n] . ': ' . Lang::$txt['php_upload_error_' . $_FILES['attachment']['error'][$n]]);
				}

				if (empty($errors)) {
					$errors[] = 'attach_php_error';
				}
			}

			// Try to move and rename the file before doing any more checks on it.
			$attachID = 'post_tmp_' . User::$me->id . '_' . bin2hex(random_bytes(16));
			$destName = Utils::$context['attach_dir'] . '/' . $attachID;

			if (empty($errors)) {
				// The reported MIME type of the attachment might not be reliable.
				$detected_mime_type = Utils::getMimeType($_FILES['attachment']['tmp_name'][$n], true);

				if ($detected_mime_type !== false) {
					$_FILES['attachment']['type'][$n] = $detected_mime_type;
				}

				$_SESSION['temp_attachments'][$attachID] = [
					'name' => Utils::htmlspecialchars(basename($_FILES['attachment']['name'][$n])),
					'tmp_name' => $destName,
					'size' => $_FILES['attachment']['size'][$n],
					'type' => $_FILES['attachment']['type'][$n],
					'id_folder' => Config::$modSettings['currentAttachmentUploadDir'],
					'errors' => [],
				];

				// Move the file to the attachments folder with a temp name for now.
				if (@move_uploaded_file($_FILES['attachment']['tmp_name'][$n], $destName)) {
					Utils::makeWritable($destName, 0644);
				} else {
					$_SESSION['temp_attachments'][$attachID]['errors'][] = 'attach_timeout';

					if (file_exists($_FILES['attachment']['tmp_name'][$n])) {
						unlink($_FILES['attachment']['tmp_name'][$n]);
					}
				}
			} else {
				$_SESSION['temp_attachments'][$attachID] = [
					'name' => Utils::htmlspecialchars(basename($_FILES['attachment']['name'][$n])),
					'tmp_name' => $destName,
					'errors' => $errors,
				];

				if (file_exists($_FILES['attachment']['tmp_name'][$n])) {
					unlink($_FILES['attachment']['tmp_name'][$n]);
				}
			}

			// If there's no errors to this point. We still do need to apply some additional checks before we are finished.
			if (empty($_SESSION['temp_attachments'][$attachID]['errors'])) {
				self::check($attachID);
			}
		}
		// Mod authors, finally a hook to hang an alternate attachment upload system upon
		// Upload to the current attachment folder with the file name $attachID or 'post_tmp_' . User::$me->id . '_' . bin2hex(random_bytes(16))
		// Populate $_SESSION['temp_attachments'][$attachID] with the following:
		//   name => The file name
		//   tmp_name => Path to the temp file (Utils::$context['attach_dir'] . '/' . $attachID).
		//   size => File size (required).
		//   type => MIME type (optional if not available on upload).
		//   id_folder => Config::$modSettings['currentAttachmentUploadDir']
		//   errors => An array of errors (use the index of the Lang::$txt variable for that error).
		// Template changes can be done using "integrate_upload_template".
		IntegrationHook::call('integrate_attachment_upload', []);
	}

	/**
	 * Performs various checks on an uploaded file.
	 * - Requires that $_SESSION['temp_attachments'][$attachID] be properly populated.
	 *
	 * @param int $attachID The ID of the attachment
	 * @return bool Whether the attachment is OK
	 */
	public static function check($attachID)
	{
		// No data or missing data .... Not necessarily needed, but in case a mod author missed something.
		if (empty($_SESSION['temp_attachments'][$attachID])) {
			$error = '$_SESSION[\'temp_attachments\'][$attachID]';
		} elseif (empty($attachID)) {
			$error = '$attachID';
		} elseif (empty(Utils::$context['attachments'])) {
			$error = 'Utils::$context[\'attachments\']';
		} elseif (empty(Utils::$context['attach_dir'])) {
			$error = 'Utils::$context[\'attach_dir\']';
		}

		// Let's get their attention.
		if (!empty($error)) {
			ErrorHandler::fatalLang('attach_check_nag', 'debug', [$error]);
		}

		// Just in case this slipped by the first checks, we stop it here and now
		if ($_SESSION['temp_attachments'][$attachID]['size'] == 0) {
			$_SESSION['temp_attachments'][$attachID]['errors'][] = 'attach_0_byte_file';

			return false;
		}

		// First, the dreaded security check. Sorry folks, but this shouldn't be avoided.
		$image = new Image($_SESSION['temp_attachments'][$attachID]['tmp_name']);

		// Source will be empty if this attachment is not an image.
		if (!empty($image->source) && !$image->check(!empty(Config::$modSettings['attachment_image_paranoid']))) {
			// It's bad. Last chance, maybe we can re-encode it?
			if (empty(Config::$modSettings['attachment_image_reencode']) || !$image->reencode()) {
				// Nothing to do: not allowed or not successful re-encoding it.
				$_SESSION['temp_attachments'][$attachID]['errors'][] = 'bad_attachment';

				return false;
			}

			// Success! However, successes usually come for a price:
			// we might get a new format for our image...
			$_SESSION['temp_attachments'][$attachID]['type'] = $image->mime_type;
		}

		unset($image);

		// Is there room for this sucker?
		if (!empty(Config::$modSettings['attachmentDirSizeLimit']) || !empty(Config::$modSettings['attachmentDirFileLimit'])) {
			// Check the folder size and count. If it hasn't been done already.
			if (empty(Utils::$context['dir_size']) || empty(Utils::$context['dir_files'])) {
				$request = Db::$db->query(
					'',
					'SELECT COUNT(*), SUM(size)
					FROM {db_prefix}attachments
					WHERE id_folder = {int:folder_id}
						AND attachment_type != {int:type}',
					[
						'folder_id' => Config::$modSettings['currentAttachmentUploadDir'],
						'type' => 1,
					],
				);
				list(Utils::$context['dir_files'], Utils::$context['dir_size']) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);
			}
			Utils::$context['dir_size'] += $_SESSION['temp_attachments'][$attachID]['size'];
			Utils::$context['dir_files']++;

			// Are we about to run out of room? Let's notify the admin then.
			if (
				empty(Config::$modSettings['attachment_full_notified'])
				&& !empty(Config::$modSettings['attachmentDirSizeLimit'])
				&& Config::$modSettings['attachmentDirSizeLimit'] > 4000
				&& Utils::$context['dir_size'] > (Config::$modSettings['attachmentDirSizeLimit'] - 2000) * 1024
				|| (
					!empty(Config::$modSettings['attachmentDirFileLimit'])
					&& Config::$modSettings['attachmentDirFileLimit'] * .95 < Utils::$context['dir_files']
					&& Config::$modSettings['attachmentDirFileLimit'] > 500
				)
			) {
				ACP::emailAdmins('admin_attachments_full');
				Config::updateModSettings(['attachment_full_notified' => 1]);
			}

			// // No room left.... What to do now???
			if (
				!empty(Config::$modSettings['attachmentDirFileLimit'])
				&& Utils::$context['dir_files'] > Config::$modSettings['attachmentDirFileLimit']
				|| (
					!empty(Config::$modSettings['attachmentDirSizeLimit'])
					&& Utils::$context['dir_size'] > Config::$modSettings['attachmentDirSizeLimit'] * 1024
				)
			) {
				if (!empty(Config::$modSettings['automanage_attachments']) && Config::$modSettings['automanage_attachments'] == 1) {
					// Move it to the new folder if we can.
					if (self::automanageBySpace()) {
						rename($_SESSION['temp_attachments'][$attachID]['tmp_name'], Utils::$context['attach_dir'] . '/' . $attachID);
						$_SESSION['temp_attachments'][$attachID]['tmp_name'] = Utils::$context['attach_dir'] . '/' . $attachID;
						$_SESSION['temp_attachments'][$attachID]['id_folder'] = Config::$modSettings['currentAttachmentUploadDir'];
						Utils::$context['dir_size'] = 0;
						Utils::$context['dir_files'] = 0;
					}
					// Or, let the user know that it ain't gonna happen.
					else {
						if (isset(Utils::$context['dir_creation_error'])) {
							$_SESSION['temp_attachments'][$attachID]['errors'][] = Utils::$context['dir_creation_error'];
						} else {
							$_SESSION['temp_attachments'][$attachID]['errors'][] = 'ran_out_of_space';
						}
					}
				} else {
					$_SESSION['temp_attachments'][$attachID]['errors'][] = 'ran_out_of_space';
				}
			}
		}

		// Is the file too big?
		Utils::$context['attachments']['total_size'] += $_SESSION['temp_attachments'][$attachID]['size'];

		if (!empty(Config::$modSettings['attachmentSizeLimit']) && $_SESSION['temp_attachments'][$attachID]['size'] > Config::$modSettings['attachmentSizeLimit'] * 1024) {
			$_SESSION['temp_attachments'][$attachID]['errors'][] = ['file_too_big', [Lang::numberFormat(Config::$modSettings['attachmentSizeLimit'], 0)]];
		}

		// Check the total upload size for this post...
		if (!empty(Config::$modSettings['attachmentPostLimit']) && Utils::$context['attachments']['total_size'] > Config::$modSettings['attachmentPostLimit'] * 1024) {
			$_SESSION['temp_attachments'][$attachID]['errors'][] = ['attach_max_total_file_size', [Lang::numberFormat(Config::$modSettings['attachmentPostLimit'], 0), Lang::numberFormat(Config::$modSettings['attachmentPostLimit'] - ((Utils::$context['attachments']['total_size'] - $_SESSION['temp_attachments'][$attachID]['size']) / 1024), 0)]];
		}

		// Have we reached the maximum number of files we are allowed?
		Utils::$context['attachments']['quantity']++;

		// Set a max limit if none exists
		if (empty(Config::$modSettings['attachmentNumPerPostLimit']) && Utils::$context['attachments']['quantity'] >= 50) {
			Config::$modSettings['attachmentNumPerPostLimit'] = 50;
		}

		if (!empty(Config::$modSettings['attachmentNumPerPostLimit']) && Utils::$context['attachments']['quantity'] > Config::$modSettings['attachmentNumPerPostLimit']) {
			$_SESSION['temp_attachments'][$attachID]['errors'][] = ['attachments_limit_per_post', [Config::$modSettings['attachmentNumPerPostLimit']]];
		}

		// File extension check
		if (!empty(Config::$modSettings['attachmentCheckExtensions'])) {
			$allowed = explode(',', strtolower(Config::$modSettings['attachmentExtensions']));

			foreach ($allowed as $k => $dummy) {
				$allowed[$k] = trim($dummy);
			}

			if (!in_array(strtolower(substr(strrchr($_SESSION['temp_attachments'][$attachID]['name'], '.'), 1)), $allowed)) {
				$allowed_extensions = strtr(strtolower(Config::$modSettings['attachmentExtensions']), [',' => ', ']);
				$_SESSION['temp_attachments'][$attachID]['errors'][] = ['cant_upload_type', [$allowed_extensions]];
			}
		}

		// Undo the math if there's an error
		if (!empty($_SESSION['temp_attachments'][$attachID]['errors'])) {
			if (isset(Utils::$context['dir_size'])) {
				Utils::$context['dir_size'] -= $_SESSION['temp_attachments'][$attachID]['size'];
			}

			if (isset(Utils::$context['dir_files'])) {
				Utils::$context['dir_files']--;
			}

			Utils::$context['attachments']['total_size'] -= $_SESSION['temp_attachments'][$attachID]['size'];

			Utils::$context['attachments']['quantity']--;

			return false;
		}

		return true;
	}

	/**
	 * Create an attachment, with the given array of parameters.
	 * - Adds any additional or missing parameters to $attachmentOptions.
	 * - Renames the temporary file.
	 * - Creates a thumbnail if the file is an image and the option enabled.
	 *
	 * @param array $attachmentOptions An array of attachment options
	 * @return bool Whether the attachment was created successfully
	 */
	public static function create(&$attachmentOptions)
	{
		// If this is an image we need to set a few additional parameters.
		$image = new Image($attachmentOptions['tmp_name']);
		$is_image = !empty($image);

		// Source is empty for non-images.
		if (!empty($image->source)) {
			// Make sure the MIME type is correct.
			$attachmentOptions['mime_type'] = $image->mime_type;

			// Set the correct width and height.
			$attachmentOptions['width'] = $image->width;
			$attachmentOptions['height'] = $image->height;

			if (in_array($image->orientation, [5, 6, 7, 8])) {
				$attachmentOptions['width'] = $image->height;
				$attachmentOptions['height'] = $image->width;
			}

			// Set the proper file extension for this image type.
			$attachmentOptions['fileext'] = ltrim(image_type_to_extension($image->type), '.');

			// Special exception: allow both 'jpg' and 'jpeg'.
			if ($attachmentOptions['fileext'] === 'jpeg' && strtolower(pathinfo($attachmentOptions['name'], PATHINFO_EXTENSION)) === 'jpg') {
				$attachmentOptions['fileext'] = pathinfo($attachmentOptions['name'], PATHINFO_EXTENSION);
			}
		} else {
			$attachmentOptions['width'] = 0;
			$attachmentOptions['height'] = 0;
		}

		unset($image);

		// Fix up the supplied file name and extension.
		$name_info = array_filter(pathinfo($attachmentOptions['name']), 'strlen');

		if (strlen($attachmentOptions['fileext'] ?? '') > 8) {
			$attachmentOptions['fileext'] = '';
		}

		if (strlen($attachmentOptions['fileext'] ?? '') === 0) {
			$attachmentOptions['fileext'] = isset($name_info['extension']) && strlen($name_info['extension']) <= 8 ? $name_info['extension'] : '';
		}

		$attachmentOptions['name'] = ($name_info['filename'] ?? bin2hex(random_bytes(4))) . (strlen($attachmentOptions['fileext']) > 0 ? '.' . $attachmentOptions['fileext'] : '');

		// Get the hash if no hash has been given yet.
		if (empty($attachmentOptions['file_hash'])) {
			$attachmentOptions['file_hash'] = self::createHash($attachmentOptions['tmp_name']);
		}

		// This defines which options to use for which columns in the insert query.
		// Mods using the hook can add columns and even change the properties of existing columns,
		// but if they delete one of these columns, it will be reset to the default defined here.
		$attachmentStandardInserts = $attachmentInserts = [
			// Format: 'column' => array('type', 'option')
			'id_folder' => ['int', 'id_folder'],
			'id_msg' => ['int', 'post'],
			'filename' => ['string-255', 'name'],
			'file_hash' => ['string-40', 'file_hash'],
			'fileext' => ['string-8', 'fileext'],
			'size' => ['int', 'size'],
			'width' => ['int', 'width'],
			'height' => ['int', 'height'],
			'mime_type' => ['string-20', 'mime_type'],
			'approved' => ['int', 'approved'],
		];

		// Last chance to change stuff!
		IntegrationHook::call('integrate_createAttachment', [&$attachmentOptions, &$attachmentInserts]);

		// Make sure the folder is valid...
		$tmp = is_array(Config::$modSettings['attachmentUploadDir']) ? Config::$modSettings['attachmentUploadDir'] : Utils::jsonDecode(Config::$modSettings['attachmentUploadDir'], true);

		$folders = array_keys($tmp);

		if (empty($attachmentOptions['id_folder']) || !in_array($attachmentOptions['id_folder'], $folders)) {
			$attachmentOptions['id_folder'] = Config::$modSettings['currentAttachmentUploadDir'];
		}

		// Make sure all required columns are present, in case a mod screwed up.
		foreach ($attachmentStandardInserts as $column => $insert_info) {
			if (!isset($attachmentInserts[$column])) {
				$attachmentInserts[$column] = $insert_info;
			}
		}

		// Set up the columns and values to insert, in the correct order.
		$attachmentColumns = [];
		$attachmentValues = [];

		foreach ($attachmentInserts as $column => $insert_info) {
			$attachmentColumns[$column] = $insert_info[0];

			if (!empty($insert_info[0]) && $insert_info[0] == 'int') {
				$attachmentValues[] = (int) $attachmentOptions[$insert_info[1]];
			} else {
				$attachmentValues[] = $attachmentOptions[$insert_info[1]];
			}
		}

		// Create the attachment in the database.
		$attachmentOptions['id'] = Db::$db->insert(
			'',
			'{db_prefix}attachments',
			$attachmentColumns,
			$attachmentValues,
			['id_attach'],
			1,
		);

		// Attachment couldn't be created.
		if (empty($attachmentOptions['id'])) {
			Lang::load('Errors');
			ErrorHandler::log(Lang::$txt['attachment_not_created'], 'general');

			return false;
		}

		// Now that we have the attach id, let's rename this sucker and finish up.
		$attachmentOptions['destination'] = self::getFilePath($attachmentOptions['id']);
		rename($attachmentOptions['tmp_name'], $attachmentOptions['destination']);

		// If it's not approved then add to the approval queue.
		if (!$attachmentOptions['approved']) {
			Db::$db->insert(
				'',
				'{db_prefix}approval_queue',
				[
					'id_attach' => 'int', 'id_msg' => 'int',
				],
				[
					$attachmentOptions['id'], (int) $attachmentOptions['post'],
				],
				[],
			);

			// Queue background notification task.
			Db::$db->insert(
				'insert',
				'{db_prefix}background_tasks',
				[
					'task_class' => 'string',
					'task_data' => 'string',
					'claimed_time' => 'int',
				],
				[
					'SMF\\Tasks\\CreateAttachment_Notify',
					Utils::jsonEncode(['id' => $attachmentOptions['id']]),
					0,
				],
				[
					'id_task',
				],
			);
		}

		if (empty($is_image) || empty(Config::$modSettings['attachmentThumbnails']) || (empty($attachmentOptions['width']) && empty($attachmentOptions['height']))) {
			return true;
		}

		// Like thumbnails, do we?
		if (!empty(Config::$modSettings['attachmentThumbWidth']) && !empty(Config::$modSettings['attachmentThumbHeight']) && ($attachmentOptions['width'] > Config::$modSettings['attachmentThumbWidth'] || $attachmentOptions['height'] > Config::$modSettings['attachmentThumbHeight'])) {
			$image = new Image($attachmentOptions['destination'], true);

			if (($thumb = $image->createThumbnail(Config::$modSettings['attachmentThumbWidth'], Config::$modSettings['attachmentThumbHeight'])) !== false) {
				// Propagate our special handling for JPEGs to the thumbnail's extension, too.
				$thumb_ext = $image->type === $thumb->type ? $attachmentOptions['fileext'] : ltrim(image_type_to_extension($thumb->type), '.');

				// We should check the file size and count here since thumbs are added to the existing totals.
				if (
					!empty(Config::$modSettings['automanage_attachments'])
					&& Config::$modSettings['automanage_attachments'] == 1
					&& !empty(Config::$modSettings['attachmentDirSizeLimit'])
					|| !empty(Config::$modSettings['attachmentDirFileLimit'])
				) {
					Utils::$context['dir_size'] = isset(Utils::$context['dir_size']) ? Utils::$context['dir_size'] += $thumb->filesize : Utils::$context['dir_size'] = 0;

					Utils::$context['dir_files'] = isset(Utils::$context['dir_files']) ? Utils::$context['dir_files']++ : Utils::$context['dir_files'] = 0;

					// If the folder is full, try to create a new one and move the thumb to it.
					if (Utils::$context['dir_size'] > Config::$modSettings['attachmentDirSizeLimit'] * 1024 || Utils::$context['dir_files'] + 2 > Config::$modSettings['attachmentDirFileLimit']) {
						if (self::automanageBySpace()) {
							$thumb->move(Utils::$context['attach_dir'] . '/' . $thumb->pathinfo['basename']);

							Utils::$context['dir_size'] = 0;
							Utils::$context['dir_files'] = 0;
						}
					}
				}

				// If a new folder has been already created. Gotta move this thumb there then.
				if (Config::$modSettings['currentAttachmentUploadDir'] != $attachmentOptions['id_folder']) {
					$thumb->move(Utils::$context['attach_dir'] . '/' . $thumb->pathinfo['basename']);
				}

				// To the database we go!
				$attachmentOptions['thumb'] = Db::$db->insert(
					'',
					'{db_prefix}attachments',
					[
						'id_folder' => 'int',
						'id_msg' => 'int',
						'attachment_type' => 'int',
						'filename' => 'string-255',
						'file_hash' => 'string-40',
						'fileext' => 'string-8',
						'size' => 'int',
						'width' => 'int',
						'height' => 'int',
						'mime_type' => 'string-20',
						'approved' => 'int',
					],
					[
						Config::$modSettings['currentAttachmentUploadDir'],
						(int) $attachmentOptions['post'],
						3,
						$thumb->pathinfo['basename'],
						self::createHash($thumb->source),
						$thumb_ext,
						$thumb->filesize,
						$thumb->width,
						$thumb->height,
						$thumb->mime_type,
						(int) $attachmentOptions['approved'],
					],
					['id_attach'],
					1,
				);

				if (!empty($attachmentOptions['thumb'])) {
					Db::$db->query(
						'',
						'UPDATE {db_prefix}attachments
						SET id_thumb = {int:id_thumb}
						WHERE id_attach = {int:id_attach}',
						[
							'id_thumb' => $attachmentOptions['thumb'],
							'id_attach' => $attachmentOptions['id'],
						],
					);

					$thumb->move(self::getFilePath($attachmentOptions['thumb']));
				}
			}
		}

		return true;
	}

	/**
	 * Assigns the given attachments to the given message ID.
	 *
	 * @param $attachIDs array of attachment IDs to assign.
	 * @param $msgID integer the message ID.
	 *
	 * @return bool false on error or missing params.
	 */
	public static function assign($attachIDs = [], $msgID = 0)
	{
		// Oh, come on!
		if (empty($attachIDs) || empty($msgID)) {
			return false;
		}

		// "I see what is right and approve, but I do what is wrong."
		IntegrationHook::call('integrate_assign_attachments', [&$attachIDs, &$msgID]);

		// One last check
		if (empty($attachIDs)) {
			return false;
		}

		// Perform.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}attachments
			SET id_msg = {int:id_msg}
			WHERE id_attach IN ({array_int:attach_ids})',
			[
				'id_msg' => $msgID,
				'attach_ids' => $attachIDs,
			],
		);

		return true;
	}

	/**
	 * Approve an attachment, or maybe even more - no permission check!
	 *
	 * @param array $attachments The IDs of the attachments to approve.
	 * @return bool Whether the operation was successful.
	 */
	public static function approve($attachments): bool
	{
		if (empty($attachments)) {
			return false;
		}

		// For safety, check for thumbnails...
		$request = Db::$db->query(
			'',
			'SELECT
				a.id_attach, a.id_member, COALESCE(thumb.id_attach, 0) AS id_thumb
			FROM {db_prefix}attachments AS a
				LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = a.id_thumb)
			WHERE a.id_attach IN ({array_int:attachments})
				AND a.attachment_type = {int:attachment_type}',
			[
				'attachments' => $attachments,
				'attachment_type' => 0,
			],
		);
		$attachments = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			// Update the thumbnail too...
			if (!empty($row['id_thumb'])) {
				$attachments[] = $row['id_thumb'];
			}

			$attachments[] = $row['id_attach'];
		}
		Db::$db->free_result($request);

		if (empty($attachments)) {
			return false;
		}

		// Approving an attachment is not hard - it's easy.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}attachments
			SET approved = {int:is_approved}
			WHERE id_attach IN ({array_int:attachments})',
			[
				'attachments' => $attachments,
				'is_approved' => 1,
			],
		);

		// In order to log the attachments, we really need their message and filename
		$request = Db::$db->query(
			'',
			'SELECT m.id_msg, a.filename
			FROM {db_prefix}attachments AS a
				INNER JOIN {db_prefix}messages AS m ON (a.id_msg = m.id_msg)
			WHERE a.id_attach IN ({array_int:attachments})
				AND a.attachment_type = {int:attachment_type}',
			[
				'attachments' => $attachments,
				'attachment_type' => 0,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			Logging::logAction(
				'approve_attach',
				[
					'message' => $row['id_msg'],
					'filename' => preg_replace('~&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#$1;', Utils::htmlspecialchars($row['filename'])),
				],
			);
		}
		Db::$db->free_result($request);

		// Remove from the approval queue.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}approval_queue
			WHERE id_attach IN ({array_int:attachments})',
			[
				'attachments' => $attachments,
			],
		);

		IntegrationHook::call('integrate_approve_attachments', [$attachments]);

		return true;
	}

	/**
	 * Removes attachments or avatars based on a given query condition.
	 * Called by several remove avatar/attachment functions in this file.
	 * It removes attachments based that match the $condition.
	 * It allows query_types 'messages' and 'members', whichever is need by the
	 * $condition parameter.
	 * It does no permissions check.
	 *
	 * @param array $condition An array of conditions
	 * @param string $query_type The query type. Can be 'messages' or 'members'
	 * @param bool $return_affected_messages Whether to return an array with the IDs of affected messages
	 * @param bool $autoThumbRemoval Whether to automatically remove any thumbnails associated with the removed files
	 * @return void|int[] Returns an array containing IDs of affected messages if $return_affected_messages is true
	 */
	public static function remove($condition, $query_type = '', $return_affected_messages = false, $autoThumbRemoval = true)
	{
		// @todo This might need more work!
		$new_condition = [];
		$query_parameter = [
			'thumb_attachment_type' => 3,
		];
		$do_logging = [];

		if (is_array($condition)) {
			foreach ($condition as $real_type => $restriction) {
				// Doing a NOT?
				$is_not = substr($real_type, 0, 4) == 'not_';
				$type = $is_not ? substr($real_type, 4) : $real_type;

				if (in_array($type, ['id_member', 'id_attach', 'id_msg'])) {
					$new_condition[] = 'a.' . $type . ($is_not ? ' NOT' : '') . ' IN (' . (is_array($restriction) ? '{array_int:' . $real_type . '}' : '{int:' . $real_type . '}') . ')';
				} elseif ($type == 'attachment_type') {
					$new_condition[] = 'a.attachment_type = {int:' . $real_type . '}';
				} elseif ($type == 'poster_time') {
					$new_condition[] = 'm.poster_time < {int:' . $real_type . '}';
				} elseif ($type == 'last_login') {
					$new_condition[] = 'mem.last_login < {int:' . $real_type . '}';
				} elseif ($type == 'size') {
					$new_condition[] = 'a.size > {int:' . $real_type . '}';
				} elseif ($type == 'id_topic') {
					$new_condition[] = 'm.id_topic IN (' . (is_array($restriction) ? '{array_int:' . $real_type . '}' : '{int:' . $real_type . '}') . ')';
				}

				// Add the parameter!
				$query_parameter[$real_type] = $restriction;

				if ($type == 'do_logging') {
					$do_logging = $condition['id_attach'];
				}
			}
			$condition = implode(' AND ', $new_condition);
		}

		// Delete it only if it exists...
		$msgs = [];
		$attach = [];
		$parents = [];

		// Get all the attachment names and id_msg's.
		$request = Db::$db->query(
			'',
			'SELECT
				a.id_folder, a.filename, a.file_hash, a.attachment_type, a.id_attach, a.id_member' . ($query_type == 'messages' ? ', m.id_msg' : ', a.id_msg') . ',
				thumb.id_folder AS thumb_folder, COALESCE(thumb.id_attach, 0) AS id_thumb, thumb.filename AS thumb_filename, thumb.file_hash AS thumb_file_hash, thumb_parent.id_attach AS id_parent
			FROM {db_prefix}attachments AS a' . ($query_type == 'members' ? '
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = a.id_member)' : ($query_type == 'messages' ? '
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)' : '')) . '
				LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = a.id_thumb)
				LEFT JOIN {db_prefix}attachments AS thumb_parent ON (thumb.attachment_type = {int:thumb_attachment_type} AND thumb_parent.id_thumb = a.id_attach)
			WHERE ' . $condition,
			$query_parameter,
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// Figure out the "encrypted" filename and unlink it ;).
			if ($row['attachment_type'] == 1) {
				// if attachment_type = 1, it's... an avatar in a custom avatars directory.
				// wasn't it obvious? :P
				// @todo look again at this.
				@unlink(Config::$modSettings['custom_avatar_dir'] . '/' . $row['filename']);
			} else {
				$filename = Attachment::getFilePath($row['id_attach']);
				@unlink($filename);

				// If this was a thumb, the parent attachment should know about it.
				if (!empty($row['id_parent'])) {
					$parents[] = $row['id_parent'];
				}

				// If this attachments has a thumb, remove it as well.
				if (!empty($row['id_thumb']) && $autoThumbRemoval) {
					$thumb_filename = Attachment::getFilePath($row['id_thumb']);
					@unlink($thumb_filename);
					$attach[] = $row['id_thumb'];
				}
			}

			// Make a list.
			if ($return_affected_messages && empty($row['attachment_type'])) {
				$msgs[] = $row['id_msg'];
			}

			$attach[] = $row['id_attach'];
		}
		Db::$db->free_result($request);

		// Removed attachments don't have to be updated anymore.
		$parents = array_diff($parents, $attach);

		if (!empty($parents)) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}attachments
				SET id_thumb = {int:no_thumb}
				WHERE id_attach IN ({array_int:parent_attachments})',
				[
					'parent_attachments' => $parents,
					'no_thumb' => 0,
				],
			);
		}

		if (!empty($do_logging)) {
			// In order to log the attachments, we really need their message and filename
			$request = Db::$db->query(
				'',
				'SELECT m.id_msg, a.filename
				FROM {db_prefix}attachments AS a
					INNER JOIN {db_prefix}messages AS m ON (a.id_msg = m.id_msg)
				WHERE a.id_attach IN ({array_int:attachments})
					AND a.attachment_type = {int:attachment_type}',
				[
					'attachments' => $do_logging,
					'attachment_type' => 0,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				Logging::logAction(
					'remove_attach',
					[
						'message' => $row['id_msg'],
						'filename' => preg_replace('~&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#$1;', Utils::htmlspecialchars($row['filename'])),
					],
				);
			}
			Db::$db->free_result($request);
		}

		if (!empty($attach)) {
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}attachments
				WHERE id_attach IN ({array_int:attachment_list})',
				[
					'attachment_list' => $attach,
				],
			);
		}

		IntegrationHook::call('integrate_remove_attachments', [$attach]);

		if ($return_affected_messages) {
			return array_unique($msgs);
		}
	}

	/**
	 * Gets an attach ID and tries to load all its info.
	 *
	 * @param int $attachID the attachment ID to load info from.
	 *
	 * @return mixed If succesful, it will return an array of loaded data. String, most likely a Lang::$txt key if there was some error.
	 */
	public static function parseAttachBBC($attachID = 0)
	{
		static $view_attachment_boards;

		if (!isset($view_attachment_boards)) {
			$view_attachment_boards = User::$me->boardsAllowedTo('view_attachments');
		}

		// Meh...
		if (empty($attachID)) {
			return 'attachments_no_data_loaded';
		}

		// Make it easy.
		$msgID = !empty($_REQUEST['msg']) ? (int) $_REQUEST['msg'] : 0;

		// Perhaps someone else wants to do the honors? Yes, this also includes dealing with previews ;)
		$externalParse = IntegrationHook::call('integrate_pre_parseAttachBBC', [$attachID, $msgID]);

		// "I am innocent of the blood of this just person: see ye to it."
		if (!empty($externalParse) && (is_string($externalParse) || is_array($externalParse))) {
			return $externalParse;
		}

		// Are attachments enabled?
		if (empty(Config::$modSettings['attachmentEnable'])) {
			return 'attachments_not_enable';
		}

		$check_board_perms = !isset($_SESSION['attachments_can_preview'][$attachID]) && $view_attachment_boards !== [0];

		// There is always the chance someone else has already done our dirty work...
		// If so, all pertinent checks were already done. Hopefully...
		if (!empty(Utils::$context['current_attachments']) && !empty(Utils::$context['current_attachments'][$attachID])) {
			return Utils::$context['current_attachments'][$attachID];
		}

		// Can the user view attachments on this board?
		if ($check_board_perms && !empty(Board::$info->id) && !in_array(Board::$info->id, $view_attachment_boards)) {
			return 'attachments_not_allowed_to_see';
		}

		// Get the message info associated with this particular attach ID.
		$attachInfo = self::getAttachMsgInfo($attachID);

		// There is always the chance this attachment no longer exists or isn't associated to a message anymore...
		if (!isset($attachInfo['id'])) {
			return 'attachments_no_data_loaded';
		}

		if (empty($attachInfo['msg']) && empty(Utils::$context['preview_message'])) {
			return 'attachments_no_msg_associated';
		}

		// Can the user view attachments on the board that holds the attachment's original post?
		// (This matters when one post quotes another on a different board.)
		if ($check_board_perms && !in_array($attachInfo['board'], $view_attachment_boards)) {
			return 'attachments_not_allowed_to_see';
		}

		if (empty(Utils::$context['loaded_attachments'][$attachInfo['msg']])) {
			self::prepareByMsg([$attachInfo['msg']]);
		}

		if (isset(Utils::$context['loaded_attachments'][$attachInfo['msg']][$attachID])) {
			$attachContext = Utils::$context['loaded_attachments'][$attachInfo['msg']][$attachID];
		}

		// In case the user manually typed the thumbnail's ID into the BBC
		elseif (!empty(Utils::$context['loaded_attachments'][$attachInfo['msg']])) {
			foreach (Utils::$context['loaded_attachments'][$attachInfo['msg']] as $foundAttachID => $foundAttach) {
				if (array_key_exists('id_thumb', $foundAttach) && $foundAttach['id_thumb'] == $attachID) {
					$attachContext = Utils::$context['loaded_attachments'][$attachInfo['msg']][$foundAttachID];
					$attachID = $foundAttachID;
					break;
				}
			}
		}

		// Load this particular attach's context.
		if (!empty($attachContext)) {
			// Skip unapproved attachment, unless they belong to the user or the user can approve them.
			if (
				!Utils::$context['loaded_attachments'][$attachInfo['msg']][$attachID]['approved']
				&& Config::$modSettings['postmod_active']
				&& !User::$me->allowedTo('approve_posts')
				&& Utils::$context['loaded_attachments'][$attachInfo['msg']][$attachID]['id_member'] != User::$me->id
			) {
				unset(Utils::$context['loaded_attachments'][$attachInfo['msg']][$attachID]);

				return 'attachments_unapproved';
			}

			$attachLoaded = self::loadAttachmentContext($attachContext['id_msg'], Utils::$context['loaded_attachments']);
		} else {
			return 'attachments_no_data_loaded';
		}

		if (empty($attachLoaded)) {
			return 'attachments_no_data_loaded';
		}

		$attachContext = $attachLoaded[$attachID];

		// It's theoretically possible that prepareByMsg() changed the board id, so check again.
		if ($check_board_perms && !in_array($attachContext['board'], $view_attachment_boards)) {
			return 'attachments_not_allowed_to_see';
		}

		// You may or may not want to show this under the post.
		if (!empty(Config::$modSettings['dont_show_attach_under_post']) && !isset(Utils::$context['show_attach_under_post'][$attachID])) {
			Utils::$context['show_attach_under_post'][$attachID] = $attachID;
		}

		// Last minute changes?
		IntegrationHook::call('integrate_post_parseAttachBBC', [&$attachContext]);

		// Don't do any logic with the loaded data, leave it to whoever called this function.
		return $attachContext;
	}

	/**
	 * Gets all needed message data associated with an attach ID
	 *
	 * @param int $attachID the attachment ID to load info from.
	 * @return mixed An instance of this class, or an empty array on failure.
	 */
	public static function getAttachMsgInfo($attachID)
	{
		if (empty($attachID)) {
			return [];
		}

		if (!isset(Utils::$context['loaded_attachments'])) {
			Utils::$context['loaded_attachments'] = [];
		}

		self::load($attachID);

		return self::$loaded[$attachID] ?? [];
	}

	/**
	 * This loads an attachment's contextual data including, most importantly, its size if it is an image.
	 * It requires the view_attachments permission to calculate image size.
	 * It attempts to keep the "aspect ratio" of the posted image in line, even if it has to be resized by
	 * the max_image_width and max_image_height settings.
	 *
	 * @param int $id_msg ID of the post to load attachments for
	 * @param array $attachments  An array of already loaded attachments. This function no longer depends on having $topic declared, thus, you need to load the actual topic ID for each attachment.
	 * @return array An array of attachment info
	 */
	public static function loadAttachmentContext($id_msg, $attachments)
	{
		if (empty($attachments) || empty($attachments[$id_msg])) {
			return [];
		}

		// Set up the attachment info - based on code by Meriadoc.
		$attachmentData = [];
		$have_unapproved = false;

		if (isset($attachments[$id_msg]) && !empty(Config::$modSettings['attachmentEnable'])) {
			foreach ($attachments[$id_msg] as $i => $attachment) {
				$attachmentData[$i] = [
					'id' => $attachment['id_attach'],
					'name' => Utils::entityFix(Utils::htmlspecialchars(Utils::htmlspecialcharsDecode($attachment['filename']))),
					'downloads' => $attachment['downloads'],
					'formatted_size' => ($attachment['filesize'] < 1024000) ? round($attachment['filesize'] / 1024, 2) . ' ' . Lang::$txt['kilobyte'] : round($attachment['filesize'] / 1024 / 1024, 2) . ' ' . Lang::$txt['megabyte'],
					'byte_size' => $attachment['filesize'],
					'href' => Config::$scripturl . '?action=dlattach;attach=' . $attachment['id_attach'],
					'link' => '<a href="' . Config::$scripturl . '?action=dlattach;attach=' . $attachment['id_attach'] . '" class="bbc_link">' . Utils::htmlspecialchars(Utils::htmlspecialcharsDecode($attachment['filename'])) . '</a>',
					'is_image' => !empty($attachment['width']) && !empty($attachment['height']),
					'is_approved' => $attachment['approved'],
					'topic' => $attachment['topic'],
					'board' => $attachment['board'],
					'mime_type' => $attachment['mime_type'],
				];

				// If something is unapproved we'll note it so we can sort them.
				if (!$attachment['approved']) {
					$have_unapproved = true;
				}

				if (!$attachmentData[$i]['is_image']) {
					continue;
				}

				$attachmentData[$i]['real_width'] = $attachment['width'];
				$attachmentData[$i]['width'] = $attachment['width'];
				$attachmentData[$i]['real_height'] = $attachment['height'];
				$attachmentData[$i]['height'] = $attachment['height'];

				// Let's see, do we want thumbs?
				if (
					!empty(Config::$modSettings['attachmentShowImages'])
					&& !empty(Config::$modSettings['attachmentThumbnails'])
					&& !empty(Config::$modSettings['attachmentThumbWidth'])
					&& !empty(Config::$modSettings['attachmentThumbHeight'])
					&& (
						$attachment['width'] > Config::$modSettings['attachmentThumbWidth']
						|| $attachment['height'] > Config::$modSettings['attachmentThumbHeight']
					)
					&& strlen($attachment['filename']) < 249) {
					// A proper thumb doesn't exist yet? Create one!
					if (
						empty($attachment['id_thumb'])
						|| $attachment['thumb_width'] > Config::$modSettings['attachmentThumbWidth']
						|| $attachment['thumb_height'] > Config::$modSettings['attachmentThumbHeight']
						|| (
							$attachment['thumb_width'] < Config::$modSettings['attachmentThumbWidth']
							&& $attachment['thumb_height'] < Config::$modSettings['attachmentThumbHeight']
						)
					) {
						$filename = self::getFilePath($attachment['id_attach']);

						$image = new Image($filename, true);

						if (!empty($image->source) && ($thumb = $image->createThumbnail(Config::$modSettings['attachmentThumbWidth'], Config::$modSettings['attachmentThumbHeight'])) !== false) {
							// So what folder are we putting this image in?
							if (!empty(Config::$modSettings['currentAttachmentUploadDir'])) {
								if (!is_array(Config::$modSettings['attachmentUploadDir'])) {
									Config::$modSettings['attachmentUploadDir'] = Utils::jsonDecode(Config::$modSettings['attachmentUploadDir'], true);
								}

								$id_folder_thumb = Config::$modSettings['currentAttachmentUploadDir'];
							} else {
								$id_folder_thumb = 1;
							}

							$attachment['thumb_width'] = $thumb->width;
							$attachment['thumb_height'] = $thumb->height;

							$old_id_thumb = $attachment['id_thumb'] ?? 0;

							$thumb_ext = $image->type === $thumb->type ? $attachment['fileext'] : ltrim(image_type_to_extension($thumb->type), '.');

							// Add this beauty to the database.
							$attachment['id_thumb'] = Db::$db->insert(
								'',
								'{db_prefix}attachments',
								[
									'id_folder' => 'int',
									'id_msg' => 'int',
									'attachment_type' => 'int',
									'filename' => 'string',
									'file_hash' => 'string',
									'size' => 'int',
									'width' => 'int',
									'height' => 'int',
									'fileext' => 'string',
									'mime_type' => 'string',
								],
								[
									$id_folder_thumb,
									$id_msg,
									3,
									$thumb->pathinfo['basename'],
									self::createHash($thumb->source),
									$thumb->filesize,
									$thumb->width,
									$thumb->height,
									$thumb_ext,
									$thumb->mime_type,
								],
								['id_attach'],
								1,
							);

							if (!empty($attachment['id_thumb'])) {
								Db::$db->query(
									'',
									'UPDATE {db_prefix}attachments
									SET id_thumb = {int:id_thumb}
									WHERE id_attach = {int:id_attach}',
									[
										'id_thumb' => $attachment['id_thumb'],
										'id_attach' => $attachment['id_attach'],
									],
								);

								$thumb->move(self::getFilePath($attachment['id_thumb']));

								// Do we need to remove an old thumbnail?
								if (!empty($old_id_thumb)) {
									self::remove(['id_attach' => $old_id_thumb], '', false, false);
								}
							}
						}
					}

					// Only adjust dimensions on successful thumbnail creation.
					if (!empty($attachment['thumb_width']) && !empty($attachment['thumb_height'])) {
						$attachmentData[$i]['width'] = $attachment['thumb_width'];
						$attachmentData[$i]['height'] = $attachment['thumb_height'];
					}
				}

				if (!empty($attachment['id_thumb'])) {
					$attachmentData[$i]['thumbnail'] = [
						'id' => $attachment['id_thumb'],
						'href' => Config::$scripturl . '?action=dlattach;attach=' . $attachment['id_thumb'] . ';image;thumb',
					];
				}

				$attachmentData[$i]['thumbnail']['has_thumb'] = !empty($attachment['id_thumb']);

				// If thumbnails are disabled, check the maximum size of the image.
				if (
					!$attachmentData[$i]['thumbnail']['has_thumb']
					&& (
						(
							!empty(Config::$modSettings['max_image_width'])
							&& $attachment['width'] > Config::$modSettings['max_image_width']
						)
						|| (
							!empty(Config::$modSettings['max_image_height'])
							&& $attachment['height'] > Config::$modSettings['max_image_height']
						)
					)
				) {
					if (
						!empty(Config::$modSettings['max_image_width'])
						&& (
							empty(Config::$modSettings['max_image_height'])
							|| $attachment['height'] * Config::$modSettings['max_image_width'] / $attachment['width'] <= Config::$modSettings['max_image_height']
						)
					) {
						$attachmentData[$i]['width'] = Config::$modSettings['max_image_width'];
						$attachmentData[$i]['height'] = floor($attachment['height'] * Config::$modSettings['max_image_width'] / $attachment['width']);
					} elseif (!empty(Config::$modSettings['max_image_width'])) {
						$attachmentData[$i]['width'] = floor($attachment['width'] * Config::$modSettings['max_image_height'] / $attachment['height']);
						$attachmentData[$i]['height'] = Config::$modSettings['max_image_height'];
					}
				} elseif ($attachmentData[$i]['thumbnail']['has_thumb']) {
					// If the image is too large to show inline, make it a popup.
					if (
						(
							!empty(Config::$modSettings['max_image_width'])
							&& $attachmentData[$i]['real_width'] > Config::$modSettings['max_image_width']
						)
						|| (
							!empty(Config::$modSettings['max_image_height'])
							&& $attachmentData[$i]['real_height'] > Config::$modSettings['max_image_height']
						)
					) {
						$attachmentData[$i]['thumbnail']['javascript'] = 'return reqWin(\'' . $attachmentData[$i]['href'] . ';image\', ' . ($attachment['width'] + 20) . ', ' . ($attachment['height'] + 20) . ', true);';
					} else {
						$attachmentData[$i]['thumbnail']['javascript'] = 'return expandThumb(' . $attachment['id_attach'] . ');';
					}
				}

				if (!$attachmentData[$i]['thumbnail']['has_thumb']) {
					$attachmentData[$i]['downloads']++;
				}

				// Describe undefined dimensions as "unknown".
				// This can happen if an uploaded SVG is missing some key data.
				foreach (['real_width', 'real_height'] as $key) {
					if (!isset($attachmentData[$i][$key]) || $attachmentData[$i][$key] === INF) {
						Lang::load('Admin');
						$attachmentData[$i][$key] = ' (' . Lang::$txt['unknown'] . ') ';
					}
				}
			}
		}

		// Do we need to instigate a sort?
		if ($have_unapproved) {
			uasort(
				$attachmentData,
				function ($a, $b) {
					if ($a['is_approved'] == $b['is_approved']) {
						return 0;
					}

					return $a['is_approved'] > $b['is_approved'] ? -1 : 1;
				},
			);
		}

		return $attachmentData;
	}

	/**
	 * prepare the Attachment api for all messages
	 *
	 * @param int array $msgIDs the message ID to load info from.
	 *
	 */
	public static function prepareByMsg($msgIDs)
	{
		if (empty(Utils::$context['loaded_attachments'])) {
			Utils::$context['loaded_attachments'] = [];
		}
		// Remove all $msgIDs that we already processed
		else {
			$msgIDs = array_diff($msgIDs, array_keys(Utils::$context['loaded_attachments']), [0]);
		}

		// Ensure that $msgIDs doesn't contain zero or non-integers.
		$msgIDs = array_filter(array_map('intval', $msgIDs));

		if (!empty($msgIDs) || !empty($_SESSION['attachments_can_preview'])) {
			$get_thumbs = !empty(Config::$modSettings['attachmentShowImages']) && !empty(Config::$modSettings['attachmentThumbnails']);

			if (!empty($msgIDs)) {
				$loaded = self::loadByMsg($msgIDs, self::APPROVED_TRUE, self::TYPE_STANDARD, $get_thumbs);

				foreach ($loaded as $id => $attachment) {
					Utils::$context['loaded_attachments'][$attachment->msg][$id] = $attachment;
				}
			}

			if (!empty($_SESSION['attachments_can_preview'])) {
				$loaded = self::load(array_keys(array_filter($_SESSION['attachments_can_preview'])), self::APPROVED_TRUE, self::TYPE_STANDARD, $get_thumbs);

				foreach ($loaded as $id => $attachment) {
					Utils::$context['loaded_attachments'][$attachment->msg][$id] = $attachment;
				}
			}
		}
	}

	/**
	 * Creates a hash string for a file or string.
	 *
	 * If $input is the path to a file, returns a hash of the file contents.
	 * If $input is an empty string, returns a hash of some random bytes.
	 * Otherwise, returns a hash of the input string itself.
	 *
	 * @param string $input The path to the file on disk.
	 * @return string A hash string.
	 */
	public static function createHash(string $input = ''): string
	{
		if (is_file($input)) {
			$hash = hash_hmac_file('sha1', $input, Config::$image_proxy_secret);
		} elseif (strlen($input) > 0) {
			$hash = hash_hmac('sha1', $input, Config::$image_proxy_secret);
		} else {
			$hash = bin2hex(random_bytes(20));
		}

		return $hash;
	}

	/**
	 * Gets the expected path to an attachment file on disk.
	 *
	 * @param int $id The ID number of an attachment.
	 * @return string The file path, or an empty string on error.
	 */
	public static function getFilePath(int $id): string
	{
		self::load($id, self::APPROVED_ANY, self::TYPE_ANY);

		return self::$loaded[$id]->path;
	}

	/**
	 * Backward compatibility only.
	 *
	 * New code should use Attachment::getFilePath() or Attachment::createHash()
	 * to get whichever type of output is desired for a given situation.
	 *
	 *
	 *
	 * Get an attachment's encrypted filename. If $new is true, won't check for
	 * file existence.
	 *
	 * This currently returns the hash if new, and the full filename otherwise,
	 * which is very messy. And of course everything that calls this function
	 * relies on that behavior and works around it. :P
	 *
	 * @param string $filename The name of the file. (Ignored.)
	 * @param int $attachment_id The ID of the attachment.
	 * @param string|null $dir Which directory it should be in. (Ignored.)
	 * @param bool $new Whether this is a new attachment.
	 * @param string $file_hash The file hash.  (Ignored.)
	 * @return string A hash or the path to the file.
	 */
	public static function getAttachmentFilename($filename, $attachment_id, $dir = null, $new = false, $file_hash = '')
	{
		// Just make up a nice hash...
		if ($new || empty($attachment_id)) {
			return self::createHash();
		}

		return self::getFilePath($attachment_id);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Sets $this->path to the full path to the attachment file on disk.
	 */
	protected function setPath(): void
	{
		if (empty($this->id) || empty($this->file_hash)) {
			return;
		}

		// Decode the JSON string to an array.
		if (!is_array(Config::$modSettings['attachmentUploadDir'])) {
			$temp = Utils::jsonDecode(Config::$modSettings['attachmentUploadDir'], true);

			if (!is_null($temp)) {
				Config::$modSettings['attachmentUploadDir'] = $temp;
			}
		}

		// Are we using multiple directories?
		if (is_array(Config::$modSettings['attachmentUploadDir'])) {
			if (!isset(Config::$modSettings['attachmentUploadDir'][$this->folder])) {
				return;
			}

			$dir = Config::$modSettings['attachmentUploadDir'][$this->folder];
		} else {
			$dir = Config::$modSettings['attachmentUploadDir'];
		}

		$this->path = $dir . '/' . $this->id . '_' . $this->file_hash . '.dat';
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Check if open_basedir restrictions are in effect.
	 * If so check if the path is allowed.
	 *
	 * @param string $path The path to check
	 *
	 * @return bool True if the path is allowed, false otherwise.
	 */
	protected static function isPathAllowed($path)
	{
		$open_basedir = ini_get('open_basedir');

		if (empty($open_basedir)) {
			return true;
		}

		$restricted_paths = explode(PATH_SEPARATOR, $open_basedir);

		foreach ($restricted_paths as $restricted_path) {
			if (mb_strpos($path, $restricted_path) === 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Split a path into a list of all directories and subdirectories
	 *
	 * @param string $directory A path
	 *
	 * @return array|bool An array of all the directories and subdirectories or false on failure
	 */
	protected static function getDirectoryTreeElements($directory)
	{
		/*
			In Windows server both \ and / can be used as directory separators in paths
			In Linux (and presumably *nix) servers \ can be part of the name
			So for this reasons:
				* in Windows we need to explode for both \ and /
				* while in linux should be safe to explode only for / (aka DIRECTORY_SEPARATOR)
		*/
		if (DIRECTORY_SEPARATOR === '\\') {
			$tree = preg_split('#[\\\/]#', $directory);
		} else {
			if (substr($directory, 0, 1) != DIRECTORY_SEPARATOR) {
				return false;
			}

			$tree = explode(DIRECTORY_SEPARATOR, trim($directory, DIRECTORY_SEPARATOR));
		}

		return $tree;
	}

	/**
	 * Return the first part of a path (i.e. c:\ or / + the first directory).
	 * Used by Attachment::automanageCreateDirectory()
	 *
	 * @param array $tree An array
	 * @param int $count The number of elements in $tree
	 *
	 * @return string|bool The first part of the path or false on error
	 */
	protected static function initDir(&$tree, &$count)
	{
		$directory = '';

		// If on Windows servers the first part of the path is the drive (e.g. "C:")
		if (DIRECTORY_SEPARATOR === '\\') {
			// Better be sure that the first part of the path is actually a drive letter...
			// ...even if, I should check this in the admin page...isn't it?
			// ...NHAAA Let's leave space for users' complains! :P
			if (preg_match('/^[a-z]:$/i', $tree[0])) {
				$directory = array_shift($tree);
			} else {
				return false;
			}

			$count--;
		}

		return $directory;
	}

	/**
	 * Generator that runs queries about attachment data and yields the result rows.
	 *
	 * @param array $selects Table columns to select.
	 * @param array $params Parameters to substitute into query text.
	 * @param string $from FROM clause. Default: '{db_prefix}attachments AS a'
	 * @param array $joins Zero or more *complete* JOIN clauses.
	 *    E.g.: 'LEFT JOIN {db_prefix}messages AS m ON (a.id_msg = m.id_msg)'
	 * @param array $where Zero or more conditions for the WHERE clause.
	 *    Conditions will be placed in parentheses and concatenated with AND.
	 *    If this is left empty, no WHERE clause will be used.
	 * @param array $order Zero or more conditions for the ORDER BY clause.
	 *    If this is left empty, no ORDER BY clause will be used.
	 * @param int $limit Maximum number of results to retrieve.
	 *    If this is left empty, all results will be retrieved.
	 *
	 * @return Generator<array> Iterating over the result gives database rows.
	 */
	protected static function queryData(array $selects, array $params = [], string $from = '{db_prefix}attachments AS a', array $joins = [], array $where = [], array $order = [], int $limit = 0)
	{
		$request = Db::$db->query(
			'',
			'SELECT ' . implode(', ', $selects) . '
			FROM ' . implode("\n\t\t\t\t\t", array_merge([$from], $joins)) . (empty($where) ? '' : '
			WHERE (' . implode(') AND (', $where) . ')') . (empty($order) ? '' : '
			ORDER BY ' . implode(', ', $order)) . ($limit > 0 ? '
			LIMIT ' . $limit : ''),
			$params,
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			yield $row;
		}
		Db::$db->free_result($request);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Attachment::exportStatic')) {
	Attachment::exportStatic();
}

?>