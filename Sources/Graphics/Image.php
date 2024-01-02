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

declare(strict_types=1);

namespace SMF\Graphics;

use SMF\BackwardCompatibility;
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\ErrorHandler;
use SMF\Url;
use SMF\Utils;
use SMF\WebFetch\WebFetchApi;

// IMAGETYPE_AVIF was added in PHP 8.1
if (!defined('IMAGETYPE_AVIF')) {
	define('IMAGETYPE_AVIF', 19);
}

/**
 * Represents an image and allows low-level graphics operations to be performed,
 * specially as needed for avatars, attachments, etc.
 */
class Image
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'getImageTypes' => 'getImageTypes',
			'getSupportedFormats' => 'getSupportedFormats',
			'checkMemory' => 'imageMemoryCheck',
			'getSizeExternal' => 'url_image_size',
			'gifOutputAsPng' => 'gif_outputAsPng',
			'getSvgSize' => 'getSvgSize',
			'makeThumbnail' => 'createThumbnail',
			'reencodeImage' => 'reencodeImage',
			'checkImageContents' => 'checkImageContents',
			'checkSvgContents' => 'checkSvgContents',
			'resizeImageFile' => 'resizeImageFile',
			'resizeImage' => 'resizeImage',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	// Default IMAGETYPE_*
	public const DEFAULT_IMAGETYPE = IMAGETYPE_JPEG;

	// Maps certain IMAGETYPE_* constants to ImageMagick formats.
	public const IMAGETYPE_TO_IMAGICK = [
		IMAGETYPE_BMP => 'bmp',
		IMAGETYPE_GIF => 'gif',
		IMAGETYPE_ICO => 'ico',
		IMAGETYPE_JP2 => 'jp2',
		IMAGETYPE_JPEG => 'jpeg',
		IMAGETYPE_JPEG2000 => 'jp2',
		IMAGETYPE_PNG => 'png',
		IMAGETYPE_PSD => 'psd',
		IMAGETYPE_TIFF_II => 'tiff',
		IMAGETYPE_TIFF_MM => 'tiff',
		IMAGETYPE_WBMP => 'wbmp',
		IMAGETYPE_WEBP => 'webp',
		IMAGETYPE_XBM => 'xbm',
		IMAGETYPE_AVIF => 'avif',
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Path to the source file.
	 */
	public string $source;

	/**
	 * @var string
	 *
	 * Path or URL to the original source file.
	 *
	 * Stays the same even when $source changes due to a resize, reencode, etc.
	 *
	 * If the object was constructed from raw image data, this will be the path
	 * to the temporary file that was initially created for it.
	 */
	public string $original;

	/**
	 * @var bool
	 *
	 * Whether the source file is a temporary file.
	 */
	public bool $is_temp;

	/**
	 * @var array
	 *
	 * Path info for the source file.
	 */
	public array $pathinfo;

	/**
	 * @var string
	 *
	 * MIME type of the image.
	 */
	public string $mime_type;

	/**
	 * @var int
	 *
	 * IMAGETYPE_* value for the image.
	 */
	public int $type;

	/**
	 * @var int|float
	 *
	 * Width of the image in pixels.
	 *
	 * Can be a float if an SVG has undefined (i.e. infinite) width.
	 */
	public int|float $width;

	/**
	 * @var int
	 *
	 * Height of the image in pixels.
	 *
	 * Can be a float if an SVG has undefined (i.e. infinite) height.
	 */
	public int|float $height;

	/**
	 * @var int
	 *
	 * Orientation of the image.
	 */
	public int $orientation = 0;

	/**
	 * @var int
	 *
	 * Size of the image file, in bytes.
	 */
	public int $filesize = 0;

	/**
	 * @var bool
	 *
	 * Whether this image has an embedded thumbnail.
	 */
	public bool $embedded_thumb = false;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * All IMAGETYPE_* constants known by this version of PHP.
	 *
	 * Keys are the string names of the constants.
	 * Values are the literal integer values of those constants.
	 */
	public static array $image_types;

	/**
	 * @var array
	 *
	 * Raster image types that are fully supported both by this class and by the
	 * installed graphics library.
	 *
	 * Values are the integer values of IMAGETYPE_* constants.
	 */
	public static array $supported;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var bool
	 *
	 * Whether to force resizing even if the image is already small enough.
	 * This is used by the reencode() method.
	 */
	protected bool $force_resize = false;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * If $source is a local file, it will be used as-is.
	 *
	 * If $source is a URL, it will be downloaded, validated, and saved to a
	 * temporary local file.
	 *
	 * If $source contains raw image data, it will be validated and then saved
	 * to a temporary local file.
	 *
	 * @param $source Either the path or URL of an image, or raw image data.
	 * @param $strict If true, die with error if $source is not a valid image.
	 *    If false, silently set properties to empty values. Default: false.
	 */
	public function __construct(string $source, bool $strict = false)
	{
		if (is_file($source)) {
			$this->source = realpath($source);
			$this->original = $this->source;
			$this->is_temp = false;
		} else {
			// External file.
			if (Url::create($source)->isValid()) {
				// Remember the URL as the original source.
				$this->original = $source;

				// Fetch the raw image data from the URL. On failure, bail out.
				if (!is_string($source = WebFetchApi::fetch($source))) {
					return;
				}
			}

			// At this point, $source contains raw image data. Save to a temp file.
			$this->source = tempnam(Config::getTempDir(), '');
			file_put_contents($this->source, $source);

			$this->is_temp = true;

			if (!isset($this->original)) {
				$this->original = $this->source;
			}
		}

		// Get the MIME type of the source file.
		$mime_type = Utils::getMimeType($this->source, true);

		// Not an image? Error and bail out.
		if (!is_string($mime_type) || strpos($mime_type, 'image/') !== 0) {
			if ($this->is_temp) {
				@unlink($this->source);
			}

			unset($this->source);

			if ($strict) {
				ErrorHandler::fatalLang('smileys_upload_error_illegal', false);
			}

			return;
		}

		$this->mime_type = $mime_type;
		$this->pathinfo = pathinfo($this->source);
		$this->getImageType();
		$this->getDimensionsAndOrientation();
		$this->filesize = filesize($this->source);
		$this->checkForEmbeddedThumb();
	}

	/**
	 * Searches through the file to see if there's potentially harmful content.
	 *
	 * @param bool $extensive Whether to perform extensive checks.
	 * @return bool Whether the image appears to be safe.
	 */
	public function check(bool $extensive = false): bool
	{
		return $this->mime_type === 'image/svg+xml' ? $this->checkSvg() : $this->checkRaster();
	}

	/**
	 * Checks whether the image should be resized in order to fit the specified
	 * dimensions.
	 *
	 * Zeroes in either argument will be treated as INF.
	 *
	 * @param int $max_width Maximum allowed width.
	 * @param int $max_height Maximum allowed height.
	 * @return bool Whether the image fits within the specified dimensions.
	 */
	public function shouldResize(int $max_width, int $max_height): bool
	{
		// Always false for SVGs.
		if ($this->mime_type === 'image/svg+xml') {
			return false;
		}

		if ($this->force_resize) {
			return true;
		}

		$max_width = empty($max_width) ? INF : round($max_width);
		$max_height = empty($max_height) ? INF : round($max_height);

		return $this->width > $max_width || $this->height > $max_height;
	}

	/**
	 * Create a thumbnail of the given source file.
	 *
	 * @param int $max_width The maximum allowed width.
	 * @param int $max_height The maximum allowed height.
	 * @return object|bool An instance of this class for the thumbnail image, or
	 *    false on failure.
	 */
	public function createThumbnail(int $max_width, int $max_height): object|bool
	{
		// This is inapplicable to SVGs.
		if ($this->mime_type === 'image/svg+xml') {
			return false;
		}

		// We don't need to create a thumbnail if one is already embedded.
		if (function_exists('exif_thumbnail') && exif_thumbnail($this->source) !== false) {
			return false;
		}

		$dst_name = $this->source . '_thumb.tmp';

		$preferred_type = !empty(Config::$modSettings['attachment_thumb_png']) ? IMAGETYPE_PNG : $this->type;

		// Do the actual resize.
		$success = $this->resize($dst_name, $max_width, $max_height, $preferred_type);

		// Okay, we're done with the temporary stuff.
		$dst_name = substr($dst_name, 0, -4);

		if ($success && @rename($dst_name . '.tmp', $dst_name)) {
			return new self($dst_name);
		}

		@unlink($dst_name . '.tmp');

		return false;
	}

	/**
	 * Re-encodes an image to a specified image format.
	 *
	 * Creates a copy of the file at the same location as the original, except
	 * with the appropriate file extension for the new format, and then removes
	 * the original file.
	 *
	 * @param int $preferred_type And IMAGETYPE_* constant, or 0 for automatic.
	 * @return bool Whether the reencoding operation was successful.
	 */
	public function reencode(int $preferred_type = 0): bool
	{
		// This is inapplicable to SVGs.
		if ($this->mime_type === 'image/svg+xml') {
			return false;
		}

		if ($preferred_type === 0) {
			$preferred_type = $this->type ?? self::DEFAULT_IMAGETYPE;
		}

		$source = is_file($this->original) ? $this->original : $this->source;

		$this->force_resize = true;
		$success = $this->resize($source . '.tmp', 0, 0, $preferred_type);
		$this->force_resize = false;

		if (!$success) {
			if (file_exists($source . '.tmp')) {
				unlink($source . '.tmp');
			}

			return false;
		}

		// If we're working on a temporary file, just replace it.
		// Otherwise, update the file extension.
		$destination = empty($this->pathinfo['extension']) ? $source : substr($source, 0, -(strlen($this->pathinfo['extension']) + 1)) . image_type_to_extension($preferred_type);

		if (!@rename($source . '.tmp', $destination)) {
			return false;
		}

		// Now get rid of the original.
		if ($destination !== $source && !@unlink($source)) {
			return false;
		}

		// Update properties to refer to the new image.
		$this->source = realpath($destination);
		$this->mime_type = mime_content_type($this->source);
		$this->pathinfo = pathinfo($this->source);
		$this->type = $preferred_type;
		$this->getDimensionsAndOrientation();
		$this->filesize = filesize($this->source);
		$this->checkForEmbeddedThumb();

		return true;
	}

	/**
	 * Resizes an image from a remote location or a local file.
	 *
	 * Puts the resized image at the destination location.
	 *
	 * $preferred_type is passed by reference. If the preferred type is not a
	 * supported image type, it will be changed to reflect the IMAGETYPE_*
	 * value that was actually used.
	 *
	 * @param string $destination The path to the destination image.
	 * @param int $max_width The maximum allowed width.
	 * @param int $max_height The maximum allowed height.
	 * @param int &$preferred_type And IMAGETYPE_* constant, or 0 for automatic.
	 * @return bool Whether it succeeded.
	 */
	public function resize(string $destination, int $max_width, int $max_height, int &$preferred_type = 0): bool
	{
		// Check whether the destination directory exists.
		if (!is_dir(dirname($destination))) {
			return false;
		}

		// Ensure the destination is writable.
		if (!Utils::makeWritable(file_exists($destination) ? $destination : dirname($destination))) {
			return false;
		}

		// If it doesn't need to be resized, just copy it to the destination.
		if (!$this->shouldResize($max_width, $max_height)) {
			if ($this->source !== $destination) {
				copy($this->source, $destination);
				$this->source = $destination;
			}

			return true;
		}

		// Nothing to do without GD or Imagick.
		if (!extension_loaded('gd') && !extension_loaded('imagick')) {
			return false;
		}

		// Is this image currently in a supported format?
		if (!in_array($this->type, self::getSupportedFormats())) {
			return false;
		}

		// What destination format do we want?
		if ($preferred_type === 0 || !in_array($preferred_type, self::$supported)) {
			$preferred_type = $this->type ?? self::DEFAULT_IMAGETYPE;
		}

		$max_width = (int) round($max_width);
		$max_height = (int) round($max_height);

		// Do the job using ImageMagick.
		if (extension_loaded('imagick') && isset(self::IMAGETYPE_TO_IMAGICK[$preferred_type])) {
			$success = $this->resizeUsingImagick($destination, $max_width, $max_height, $preferred_type);
		}
		// Do the job using GD.
		elseif (extension_loaded('gd')) {
			$success = $this->resizeUsingGD($destination, $max_width, $max_height, $preferred_type);
		}

		// Update properties to refer to the new image.
		$this->source = realpath($destination);
		$this->mime_type = mime_content_type($this->source);
		$this->pathinfo = pathinfo($this->source);
		$this->type = $preferred_type;
		$this->getDimensionsAndOrientation();
		$this->filesize = filesize($this->source);
		$this->checkForEmbeddedThumb();

		return !empty($success);
	}

	/**
	 * Moves the image file to a new location and updates properties to match.
	 *
	 * @param string $destination Path to new file location.
	 * @return bool Whether the operation was successful.
	 */
	public function move(string $destination): bool
	{
		if ($destination === $this->source) {
			return true;
		}

		if (!rename($this->source, $destination)) {
			return false;
		}

		$this->source = realpath($destination);
		$this->pathinfo = pathinfo($this->source);

		// Attempt to chmod it.
		@Utils::makeWritable($this->source);

		return true;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Get all the IMAGETYPE_* constants defined by this version of PHP.
	 *
	 * @return array List of IMAGETYPE_* constant names and values.
	 */
	public static function getImageTypes(): array
	{
		if (!isset(self::$image_types)) {
			self::$image_types = array_filter(
				get_defined_constants(),
				function ($constant_name) {
					if (strpos($constant_name, 'IMAGETYPE_') !== 0) {
						return false;
					}

					return !($constant_name === 'IMAGETYPE_UNKNOWN' || $constant_name === 'IMAGETYPE_COUNT');
				},
				ARRAY_FILTER_USE_KEY,
			);
		}

		return self::$image_types;
	}

	/**
	 * Get all the image formats supported by the installed graphics library.
	 *
	 * @return array List of supported image formats.
	 */
	public static function getSupportedFormats(): array
	{
		if (!isset(self::$supported)) {
			self::$supported = [];

			if (extension_loaded('imagick')) {
				foreach (self::getImageTypes() as $name => $int) {
					if (isset(self::IMAGETYPE_TO_IMAGICK[$int])) {
						self::$supported[$name] = $int;
					}
				}
			} elseif (extension_loaded('gd')) {
				foreach (self::getImageTypes() as $name => $int) {
					if (imagetypes() & $int) {
						self::$supported[$name] = $int;
					}
				}
			}
		}

		return self::$supported;
	}

	/**
	 * Check whether we have enough memory to make a thumbnail.
	 *
	 * @param array $sizes Image size.
	 * @return bool Whether we do.
	 */
	public static function checkMemory(array $sizes): bool
	{
		// doing the old 'set it and hope' way?
		if (empty(Config::$modSettings['attachment_thumb_memory'])) {
			Config::setMemoryLimit('128M');

			return true;
		}

		// Determine the memory requirements for this image. If you want to use
		// an image formula W x H x bits/8 x channels x Overhead factor you will
		// need to account for single bit images as GD expands them to an 8 bit
		// and will greatly overun the calculated value.  The 5 is simply a
		// shortcut of 8bpp, 3 channels, 1.66 overhead.
		$needed_memory = ($sizes[0] * $sizes[1] * 5);

		// if we need more, lets try to get it
		return Config::setMemoryLimit((string) $needed_memory, true);
	}

	/**
	 * Get the size of an external image.
	 *
	 * @param string $url The URL of the image.
	 * @return array|false Width and height, or false on failure.
	 */
	public static function getSizeExternal(string $url): array|false
	{
		// Make sure it is a proper URL.
		$url = str_replace(' ', '%20', $url);

		// Can we pull this from the cache... please please?
		if (($temp = CacheApi::get('url_image_size-' . md5($url), 240)) !== null) {
			return $temp;
		}

		$image = new self($url);

		if (!isset($image->width) || !isset($image->width)) {
			return false;
		}

		// If this took a long time, we may never have to do it again, but then again we might...
		if (microtime(true) - TIME_START > 0.8) {
			CacheApi::put('url_image_size-' . md5($url), [$image->width, $image->height], 240);
		}

		return [$image->width, $image->height];
	}

	/**
	 * Writes a GIF file to disk as a PNG file.
	 *
	 * This is unused by SMF itself, but retained for compatibility with any
	 * mods that use it.
	 *
	 * @param Gif\File $gif A GIF image resource.
	 * @param string $lpszFileName The name of the file.
	 * @param string $background_color The background color.
	 * @return bool Whether the operation was successful.
	 */
	public static function gifOutputAsPng(Gif\File $gif, string $lpszFileName, string $background_color = '-1'): bool
	{
		if (!is_a($gif, Gif\File::class) || $lpszFileName == '') {
			return false;
		}

		if (($fd = $gif->get_png_data($background_color)) === false) {
			return false;
		}

		if (($fh = @fopen($lpszFileName, 'wb')) === false) {
			return false;
		}

		@fwrite($fh, $fd, strlen($fd));
		@fflush($fh);
		@fclose($fh);

		return true;
	}

	/**
	 * Gets the dimensions of an SVG image (specifically, of its viewport).
	 *
	 * If $filepath is not the path to a valid SVG file, the returned width and
	 * height will both be null.
	 *
	 * See https://www.w3.org/TR/SVG11/coords.html#IntrinsicSizing
	 *
	 * This method only exists for backward compatibility purposes. New code
	 * should just create a new instance of this class for the SVG and then get
	 * its width and height properties directly.
	 *
	 * @param string $filepath The path to the SVG file.
	 * @return array The width and height of the SVG image in pixels.
	 */
	public static function getSvgSize(string $filepath): array
	{
		$image = new self($filepath);

		if ($image->mime_type !== 'image/svg+xml') {
			return ['width' => null, 'height' => null];
		}

		return ['width' => $image->width, 'height' => $image->height];
	}

	/**
	 * Backward compatibility wrapper for the createThumbnail() method.
	 *
	 * @param string $source The path to the source image.
	 * @param int $max_width The maximum allowed width.
	 * @param int $max_height The maximum allowed height.
	 * @return bool Whether the thumbnail creation was successful.
	 */
	public static function makeThumbnail(string $source, int $max_width, int $max_height): bool
	{
		$img = new self($source);

		return ($img->createThumbnail($max_width, $max_height) !== false);
	}

	/**
	 * Backward compatibility wrapper for the reencode() method.
	 *
	 * @param string $source The path to the source image.
	 * @param int $preferred_type And IMAGETYPE_* constant, or 0 for automatic.
	 * @return bool Whether the operation was successful.
	 */
	public static function reencodeImage(string $source, int $preferred_type = 0): bool
	{
		$img = new self($source);

		return $img->reencode($preferred_type);
	}

	/**
	 * Backward compatibility wrapper for the check() method.
	 *
	 * @param string $source The path to the source image.
	 * @param bool $extensive Whether to perform extensive checks.
	 * @return bool Whether the image appears to be safe.
	 */
	public static function checkImageContents(string $source, bool $extensive = false): bool
	{
		$img = new self($source);

		return $img->check($extensive);
	}

	/**
	 * Another backward compatibility wrapper for the check() method.
	 *
	 * @param string $source The path to the source image.
	 * @return bool Whether the image appears to be safe.
	 */
	public static function checkSvgContents(string $source): bool
	{
		$img = new self($source);

		return $img->check();
	}

	/**
	 * Backward compatibility wrapper for the resize() method.
	 *
	 * @param string $source The path to the source image.
	 * @param string $destination The path to the destination image.
	 * @param int $max_width The maximum allowed width.
	 * @param int $max_height The maximum allowed height.
	 * @param int $preferred_type And IMAGETYPE_* constant, or 0 for automatic.
	 * @return bool Whether the operation was successful.
	 */
	public static function resizeImageFile(string $source, string $destination, int $max_width, int $max_height, int $preferred_type = 0): bool
	{
		$img = new self($source);

		return $img->resize($destination, $max_width, $max_height, $preferred_type);
	}

	/**
	 * Another backward compatibility wrapper for the resize() method.
	 *
	 * @param string $source The source image data as a string.
	 * @param string $destination The path to the destination image.
	 * @param int $src_width The width of the source image. (Ignored.)
	 * @param int $src_height The height of the source image. (Ignored.)
	 * @param int $max_width The maximum allowed width.
	 * @param int $max_height The maximum allowed height.
	 * @param int $preferred_type And IMAGETYPE_* constant, or 0 for automatic.
	 * @return bool Whether the operation was successful.
	 */
	public static function resizeImage(string $source, string $destination, int $src_width, int $src_height, int $max_width, int $max_height, int $preferred_type = 0): bool
	{
		$img = new self($source);

		return $img->resize($destination, $max_width, $max_height, $preferred_type);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Sets $this->type to the value of an IMAGETYPE_* constant.
	 */
	protected function getImageType(): void
	{
		// Avoid unnecessary repetition.
		if (isset($this->type)) {
			return;
		}

		// SVGs don't have an IMAGETYPE_*.
		if ($this->mime_type === 'image/svg+xml') {
			return;
		}

		// First try exif_imagetype().
		if (function_exists('exif_imagetype') && ($type = exif_imagetype($this->source)) !== false) {
			$this->type = $type;

			return;
		}

		// Next try getimagesize().
		if (function_exists('getimagesize') && ($sizes = @getimagesize($this->source)) !== false) {
			list($this->width, $this->height, $this->type) = $sizes;

			return;
		}

		// If all else fails, see if we can guess from the MIME type.
		if (strpos($this->mime_type, 'image/') === 0) {
			// Unfortunately, 'image/tiff' could be two different things,
			// and if we got here, we have no way to guess which one.
			if ($this->mime_type === 'image/tiff') {
				return;
			}

			foreach (self::getImageTypes() as $type) {
				if (image_type_to_mime_type($type) === $this->mime_type) {
					$this->type = $type;

					return;
				}
			}
		}
	}

	/**
	 * Sets $this->embedded_thumb to true if there is an embedded thumbnail in
	 * this image, or false if there isn't.
	 */
	protected function checkForEmbeddedThumb(): void
	{
		$this->embedded_thumb = $this->mime_type !== 'image/svg+xml' && function_exists('exif_read_data') && @exif_read_data($this->source, 'THUMBNAIL') !== false;
	}

	/**
	 * Sets $this->width, $this->height, and $this->orientation.
	 */
	protected function getDimensionsAndOrientation(): void
	{
		// SVGs are special.
		if ($this->mime_type === 'image/svg+xml') {
			$this->getSvgDimensions();

			return;
		}

		// First try exif_read_data().
		if (function_exists('exif_read_data') && ($exif_data = @exif_read_data($this->source)) !== false) {
			if (isset($exif_data['Orientation'])) {
				$this->orientation = $exif_data['Orientation'];
			}

			if (isset($exif_data['COMPUTED']['Width'], $exif_data['COMPUTED']['Height'])) {
				$this->width = $exif_data['COMPUTED']['Width'];
				$this->height = $exif_data['COMPUTED']['Height'];

				return;
			}
		}

		// Next try ImageMagick.
		if (extension_loaded('imagick')) {
			$imagick = new \Imagick($this->source);

			try {
				$this->orientation = $imagick->getImageOrientation();
			} catch (\Throwable $e) {
			}

			try {
				$this->width = $imagick->getImageWidth();
				$this->height = $imagick->getImageHeight();

				return;
			} catch (\Throwable $e) {
			}
		}

		// Finally, try getimagesize(). This can't tell us orientation.
		if (function_exists('getimagesize') && ($sizes = @getimagesize($this->source)) !== false) {
			list($this->width, $this->height, $this->type) = $sizes;
		}
	}

	/**
	 * Sets $this->width and $this->height for SVG files.
	 *
	 * See https://www.w3.org/TR/SVG11/coords.html#IntrinsicSizing
	 */
	protected function getSvgDimensions(): void
	{
		preg_match('/<svg\b[^>]*>/', file_get_contents($this->source, false, null, 0, 480), $matches);

		if (!isset($matches[0])) {
			return;
		}

		$svg = $matches[0];

		// If the SVG has width and height attributes, use those.
		// If attribute is missing, SVG spec says the default is '100%'.
		// If no unit is supplied, spec says unit defaults to px.
		foreach (['width', 'height'] as $dimension) {
			if (preg_match("/\b{$dimension}\s*=\s*([\"'])\s*([\d.]+)([\D\S]*)\s*\1/", $svg, $matches)) {
				$$dimension = $matches[2];
				$unit = !empty($matches[3]) ? $matches[3] : 'px';
			} else {
				$$dimension = 100;
				$unit = '%';
			}

			// Resolve unit.
			switch ($unit) {
				// Already pixels, so do nothing.
				case 'px':
					break;

				// Points.
				case 'pt':
					$$dimension *= 0.75;
					break;

				// Picas.
				case 'pc':
					$$dimension *= 16;
					break;

				// Inches.
				case 'in':
					$$dimension *= 96;
					break;

				// Centimetres.
				case 'cm':
					$$dimension *= 37.8;
					break;

				// Millimetres.
				case 'mm':
					$$dimension *= 3.78;
					break;

				// Font height.
				// Assume browser default of 1em = 1pc.
				case 'em':
					$$dimension *= 16;
					break;

				// Font x-height.
				// Assume half of font height.
				case 'ex':
					$$dimension *= 8;
					break;

				// Font '0' character width.
				// Assume a typical monospace font at 1em = 1pc.
				case 'ch':
					$$dimension *= 9.6;
					break;

				// Percentage.
				// SVG spec says to use viewBox dimensions in this case.
				default:
					unset($$dimension);
					break;
			}
		}

		// Width and/or height is missing or a percentage, so try the viewBox attribute.
		if ((!isset($width) || !isset($height)) && preg_match('/\bviewBox\s*=\s*(["\'])\s*[\d.]+[,\s]+[\d.]+[,\s]+([\d.]+)[,\s]+([\d.]+)\s*\1/', $svg, $matches)) {
			$vb_width = $matches[2];
			$vb_height = $matches[3];

			// No dimensions given, so use viewBox dimensions.
			if (!empty($width) && !empty($height)) {
				$width = $vb_width;
				$height = $vb_height;
			}
			// Width but no height, so calculate height.
			elseif (!empty($width)) {
				$height = $width * $vb_height / $vb_width;
			}
			// Height but no width, so calculate width.
			elseif (!empty($height)) {
				$width = $height * $vb_width / $vb_height;
			}
		}

		// Viewport undefined, so call it infinite.
		if (!isset($width) && !isset($height)) {
			$width = INF;
			$height = INF;
		}

		$this->width = round($width);
		$this->height = round($height);
	}

	/**
	 * Searches through a raster image to see if it contains potentially harmful
	 * content.
	 *
	 * @param bool $extensive Whether to perform extensive checks.
	 * @return bool Whether the image appears to be safe.
	 */
	protected function checkRaster(bool $extensive = false): bool
	{
		$fp = fopen($this->source, 'rb');

		if (!$fp) {
			ErrorHandler::fatalLang('attach_timeout');
		}

		$prev_chunk = '';

		while (!feof($fp)) {
			$cur_chunk = fread($fp, 8192);

			// Though not exhaustive lists, better safe than sorry.
			if (!empty($extensive)) {
				// Paranoid check.
				// Will result in MANY false positives, and is not suitable for photography sites.
				if (preg_match('~(iframe|\\<\\?|\\<%|html|eval|body|script\W|(?-i)[CFZ]WS[\x01-\x0E])~i', $prev_chunk . $cur_chunk) === 1) {
					fclose($fp);

					return false;
				}
			} else {
				// Check for potential infection - focus on clues for inline PHP & flash.
				// Will result in significantly fewer false positives than the paranoid check.
				if (preg_match('~(\\<\\?php\s|(?-i)[CFZ]WS[\x01-\x0E])~i', $prev_chunk . $cur_chunk) === 1) {
					fclose($fp);

					return false;
				}
			}

			$prev_chunk = $cur_chunk;
		}

		fclose($fp);

		return true;
	}

	/**
	 * Searches through an SVG image to see if it contains potentially harmful
	 * content.
	 *
	 * @return bool Whether the image appears to be safe.
	 */
	protected function checkSvg(): bool
	{
		$fp = fopen($this->source, 'rb');

		if (!$fp) {
			ErrorHandler::fatalLang('attach_timeout');
		}

		$patterns = [
			// No external or embedded scripts allowed.
			'/<(\S*:)?script\b/i',
			'/\b(\S:)?href\s*=\s*["\']\s*javascript:/i',

			// No SVG event attributes allowed, since they execute scripts.
			'/\bon\w+\s*=\s*["\']/',
			'/<(\S*:)?set\b[^>]*\battributeName\s*=\s*(["\'])\s*on\w+\1/i',

			// No XML Events allowed, since they execute scripts.
			'~\bhttp://www\.w3\.org/2001/xml-events\b~i',

			// No data URIs allowed, since they contain arbitrary data.
			'/\b(\S*:)?href\s*=\s*["\']\s*data:/i',

			// No foreignObjects allowed, since they allow embedded HTML.
			'/<(\S*:)?foreignObject\b/i',

			// No custom entities allowed, since they can be used for entity
			// recursion attacks.
			'/<!ENTITY\b/',

			// Embedded external images can't have custom cross-origin rules.
			'/<\b(\S*:)?image\b[^>]*\bcrossorigin\s*=/',

			// No embedded PHP tags allowed.
			// Harmless if the SVG is just the src of an img element, but very
			// bad if the SVG is embedded inline into the HTML document.
			'/<(php)?[?]|[?]>/i',
		];

		$prev_chunk = '';

		while (!feof($fp)) {
			$cur_chunk = fread($fp, 8192);

			foreach ($patterns as $pattern) {
				if (preg_match($pattern, $prev_chunk . $cur_chunk)) {
					fclose($fp);

					return false;
				}
			}

			$prev_chunk = $cur_chunk;
		}
		fclose($fp);

		return true;
	}

	/**
	 * Resizes an image using the GD extesion.
	 *
	 * @param string $destination The path to the destination image.
	 * @param int $max_width The maximum allowed width.
	 * @param int $max_height The maximum allowed height.
	 * @param int $preferred_type And IMAGETYPE_* constant.
	 * @return bool Whether the operation was successful.
	 */
	protected function resizeUsingGD(string $destination, int $max_width, int $max_height, int $preferred_type): bool
	{
		// If the image is already small enough and in the desired format,
		// just write to the destination and return.
		if (!$this->shouldResize($max_width, $max_height) && $preferred_type === $this->type) {
			if ($this->source !== $destination) {
				copy($this->source, $destination);
			}

			return true;
		}

		// Figure out the functions we need.
		$imagecreatefrom = 'imagecreatefrom' . strtolower(substr(array_search($this->type, self::$supported), 10));

		$imagesave = 'image' . strtolower(substr(array_search($preferred_type, self::$supported), 10));

		// Do the functions exist?
		if (!function_exists($imagecreatefrom) || !function_exists($imagesave)) {
			return false;
		}

		// See if we have or can get the needed memory for this operation.
		if (!self::checkMemory([$this->width, $this->height])) {
			return false;
		}

		if (($src_img = @$imagecreatefrom($this->source)) === false) {
			return false;
		}

		$success = false;

		// Determine whether to resize to max width or to max height (depending on the limits.)
		if (!empty($max_width) && (empty($max_height) || round($this->height * $max_width / $this->width) <= $max_height)) {
			$dst_width = $max_width;
			$dst_height = round($this->height * $max_width / $this->width);
		} elseif (!empty($max_height)) {
			$dst_width = round($this->width * $max_height / $this->height);
			$dst_height = $max_height;
		}

		// Don't bother resizing if it's already smaller...
		if (!$this->shouldResize($dst_width, $dst_height) && $preferred_type === $this->type) {
			$dst_img = $src_img;
		} else {
			// (make a true color image, because it just looks better for resizing.)
			$dst_img = imagecreatetruecolor($dst_width, $dst_height);

			// Deal nicely with a PNG - because we can.
			if ($preferred_type == IMAGETYPE_PNG) {
				imagealphablending($dst_img, false);

				if (function_exists('imagesavealpha')) {
					imagesavealpha($dst_img, true);
				}
			}

			// Resize it!
			imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $dst_width, $dst_height, $this->width, $this->height);
		}

		// Should we adjust the orientation of the resized image?
		if ($this->orientation > 1) {
			switch ($this->orientation) {
				case 3:
				case 4:
					$dst_img = imagerotate($dst_img, 180, 0);
					break;

				case 5:
				case 6:
					$dst_img = imagerotate($dst_img, 270, 0);
					break;

				case 7:
				case 8:
					$dst_img = imagerotate($dst_img, 90, 0);
					break;
			}

			if (in_array($this->orientation, [2, 4, 5, 7])) {
				imageflip($dst_img, IMG_FLIP_HORIZONTAL);
			}
		}

		// Save the image as...
		if ($preferred_type == IMAGETYPE_JPEG) {
			return imagejpeg($dst_img, $destination, !empty(Config::$modSettings['avatar_jpeg_quality']) ? Config::$modSettings['avatar_jpeg_quality'] : 82);
		}

		return $imagesave($dst_img, $destination);
	}

	/**
	 * Resizes an image using the imagick extesion.
	 *
	 * @param string $destination The path to the destination image.
	 * @param int $max_width The maximum allowed width.
	 * @param int $max_height The maximum allowed height.
	 * @param int $preferred_type And IMAGETYPE_* constant.
	 * @return bool Whether the operation was successful.
	 */
	protected function resizeUsingImagick(string $destination, int $max_width, int $max_height, int $preferred_type): bool
	{
		$imagick = new \Imagick($this->source);

		$dst_width = empty($max_width) ? $this->width : $max_width;
		$dst_height = empty($max_height) ? $this->height : $max_height;

		// If the image is already small enough and in the desired format,
		// just write to the destination and return.
		if (!$this->shouldResize($dst_width, $dst_height) && $preferred_type === $this->type) {
			return $imagick->writeImage($destination);
		}

		if (self::IMAGETYPE_TO_IMAGICK[$preferred_type] == 'jpeg') {
			$imagick->setCompressionQuality(!empty(Config::$modSettings['avatar_jpeg_quality']) ? Config::$modSettings['avatar_jpeg_quality'] : 82);
		}

		$imagick->setImageFormat(self::IMAGETYPE_TO_IMAGICK[$preferred_type]);
		$imagick->resizeImage($dst_width, $dst_height, \Imagick::FILTER_LANCZOS, 1, true);

		// Should we adjust the orientation of the resized image?
		if ($this->orientation > 1) {
			switch ($this->orientation) {
				case 3:
				case 4:
					$imagick->rotateImage('#00000000', 180);
					break;

				case 5:
				case 6:
					$imagick->rotateImage('#00000000', 90);
					break;

				case 7:
				case 8:
					$imagick->rotateImage('#00000000', 270);
					break;
			}

			if (in_array($this->orientation, [2, 4, 5, 7])) {
				$imagick->flopImage();
			}
		}

		return $imagick->writeImage($destination);
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Gets the IMAGETYPE_* constant corresponding to the passed MIME type.
	 *
	 * This doesn't work for all cases, but it does for the most common ones.
	 *
	 * @return int An IMAGETYPE_* constant, or 0 if no match was found.
	 */
	protected static function mimeTypeToImageType(string $mime_type): int
	{
		// We can't do anything useful with 'application/octet-stream', etc.
		if (strpos($mime_type, 'image/') !== 0) {
			return 0;
		}

		// Unfortunately, 'image/tiff' could be two different things.
		if ($mime_type === 'image/tiff') {
			return 0;
		}

		foreach (self::getImageTypes() as $type) {
			if (image_type_to_mime_type($type) === $mime_type) {
				return $type;
			}
		}

		return 0;
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Image::exportStatic')) {
	Image::exportStatic();
}

?>