<?php

/**
 * This file deals with low-level graphics operations performed on images,
 * specially as needed for avatars (uploaded avatars), attachments, or
 * visual verification images.
 * It uses, for gifs at least, Gif Util. For more information on that,
 * please see its website.
 * TrueType fonts supplied by www.LarabieFonts.com
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\Attachment;
use SMF\Config;
use SMF\ErrorHandler;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Db\DatabaseApi as Db;
use SMF\Graphics\Gif;

if (!defined('SMF'))
	die('No direct access...');

class_exists('SMF\\Profile');

/**
 * Create a thumbnail of the given source.
 *
 * @uses resizeImageFile() function to achieve the resize.
 *
 * @param string $source The name of the source image
 * @param int $max_width The maximum allowed width
 * @param int $max_height The maximum allowed height
 * @return boolean Whether the thumbnail creation was successful.
 */
function createThumbnail($source, $max_width, $max_height)
{
	$destName = $source . '_thumb.tmp';

	// Do the actual resize.
	if (!empty(Config::$modSettings['attachment_thumb_png']))
		$success = resizeImageFile($source, $destName, $max_width, $max_height, 3);
	else
		$success = resizeImageFile($source, $destName, $max_width, $max_height);

	// Okay, we're done with the temporary stuff.
	$destName = substr($destName, 0, -4);

	if ($success && @rename($destName . '.tmp', $destName))
		return true;
	else
	{
		@unlink($destName . '.tmp');
		@touch($destName);
		return false;
	}
}

/**
 * Used to re-econodes an image to a specified image format
 * - creates a copy of the file at the same location as fileName.
 * - the file would have the format preferred_format if possible, otherwise the default format is jpeg.
 * - the function makes sure that all non-essential image contents are disposed.
 *
 * @param string $fileName The path to the file
 * @param int $preferred_format The preferred format - 0 to automatically determine, 1 for gif, 2 for jpg, 3 for png, 6 for bmp and 15 for wbmp
 * @return boolean Whether the reencoding was successful
 */
function reencodeImage($fileName, $preferred_format = 0)
{
	if (!resizeImageFile($fileName, $fileName . '.tmp', null, null, $preferred_format))
	{
		if (file_exists($fileName . '.tmp'))
			unlink($fileName . '.tmp');

		return false;
	}

	if (!unlink($fileName))
		return false;

	if (!rename($fileName . '.tmp', $fileName))
		return false;

	return true;
}

/**
 * Searches through the file to see if there's potentially harmful non-binary content.
 * - if extensiveCheck is true, searches for asp/php short tags as well.
 *
 * @param string $fileName The path to the file
 * @param bool $extensiveCheck Whether to perform extensive checks
 * @return bool Whether the image appears to be safe
 */
function checkImageContents($fileName, $extensiveCheck = false)
{
	$fp = fopen($fileName, 'rb');

	if (!$fp)
		ErrorHandler::fatalLang('attach_timeout');

	$prev_chunk = '';
	while (!feof($fp))
	{
		$cur_chunk = fread($fp, 8192);

		// Though not exhaustive lists, better safe than sorry.
		if (!empty($extensiveCheck))
		{
			// Paranoid check.  Use this if you have reason to distrust your host's security config.
			// Will result in MANY false positives, and is not suitable for photography sites.
			if (preg_match('~(iframe|\\<\\?|\\<%|html|eval|body|script\W|(?-i)[CFZ]WS[\x01-\x0E])~i', $prev_chunk . $cur_chunk) === 1)
			{
				fclose($fp);
				return false;
			}
		}
		else
		{
			// Check for potential infection - focus on clues for inline php & flash.
			// Will result in significantly fewer false positives than the paranoid check.
			if (preg_match('~(\\<\\?php\s|(?-i)[CFZ]WS[\x01-\x0E])~i', $prev_chunk . $cur_chunk) === 1)
			{
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
 * Searches through an SVG file to see if there's potentially harmful content.
 *
 * @param string $fileName The path to the file.
 * @return bool Whether the image appears to be safe.
 */
function checkSvgContents($fileName)
{
	$fp = fopen($fileName, 'rb');

	if (!$fp)
		ErrorHandler::fatalLang('attach_timeout');

	$patterns = array(
		// No external or embedded scripts allowed.
		'/<(\S*:)?script\b/i',
		'/\b(\S:)?href\s*=\s*["\']\s*javascript:/i',

		// No SVG event attributes allowed, since they execute scripts.
		'/\bon\w+\s*=\s*["\']/',
		'/<(\S*:)?set\b[^>]*\battributeName\s*=\s*(["\'])\s*on\w+\\1/i',

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
		// Harmless if the SVG is just the src of an img element, but very bad
		// if the SVG is embedded inline into the HTML document.
		'/<(php)?[?]|[?]>/i',
	);

	$prev_chunk = '';
	while (!feof($fp))
	{
		$cur_chunk = fread($fp, 8192);

		foreach ($patterns as $pattern)
		{
			if (preg_match($pattern, $prev_chunk . $cur_chunk))
			{
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
 * Sets a global $gd2 variable needed by some functions to determine
 * whether the GD2 library is present.
 *
 * @return bool Whether or not GD1 is available.
 */
function checkGD()
{
	global $gd2;

	// Check to see if GD is installed and what version.
	if (($extensionFunctions = get_extension_funcs('gd')) === false)
		return false;

	// Also determine if GD2 is installed and store it in a global.
	$gd2 = in_array('imagecreatetruecolor', $extensionFunctions) && function_exists('imagecreatetruecolor');

	return true;
}

/**
 * Checks whether the Imagick class is present.
 *
 * @return bool Whether or not the Imagick extension is available.
 */
function checkImagick()
{
	return class_exists('Imagick', false);
}

/**
 * Checks whether the MagickWand extension is present.
 *
 * @return bool Whether or not the MagickWand extension is available.
 */
function checkMagickWand()
{
	return function_exists('newMagickWand');
}

/**
 * See if we have enough memory to thumbnail an image
 *
 * @param array $sizes image size
 * @return bool Whether we do
 */
function imageMemoryCheck($sizes)
{
	// doing the old 'set it and hope' way?
	if (empty(Config::$modSettings['attachment_thumb_memory']))
	{
		setMemoryLimit('128M');
		return true;
	}

	// Determine the memory requirements for this image, note: if you want to use an image formula W x H x bits/8 x channels x Overhead factor
	// you will need to account for single bit images as GD expands them to an 8 bit and will greatly overun the calculated value.  The 5 is
	// simply a shortcut of 8bpp, 3 channels, 1.66 overhead
	$needed_memory = ($sizes[0] * $sizes[1] * 5);

	// if we need more, lets try to get it
	return setMemoryLimit($needed_memory, true);
}

/**
 * Resizes an image from a remote location or a local file.
 * Puts the resized image at the destination location.
 * The file would have the format preferred_format if possible,
 * otherwise the default format is jpeg.
 *
 * @param string $source The path to the source image
 * @param string $destination The path to the destination image
 * @param int $max_width The maximum allowed width
 * @param int $max_height The maximum allowed height
 * @param int $preferred_format - The preferred format (0 to use jpeg, 1 for gif, 2 to force jpeg, 3 for png, 6 for bmp and 15 for wbmp)
 * @return bool Whether it succeeded.
 */
function resizeImageFile($source, $destination, $max_width, $max_height, $preferred_format = 0)
{
	// Nothing to do without GD or IM/MW
	if (!checkGD() && !checkImagick() && !checkMagickWand())
		return false;

	static $default_formats = array(
		'1' => 'gif',
		'2' => 'jpeg',
		'3' => 'png',
		'6' => 'bmp',
		'15' => 'wbmp'
	);

	// Get the image file, we have to work with something after all
	$fp_destination = fopen($destination, 'wb');
	if ($fp_destination && (substr($source, 0, 7) == 'http://' || substr($source, 0, 8) == 'https://'))
	{
		$fileContents = fetch_web_data($source);

		$mime_valid = check_mime_type($fileContents, implode('|', array_map('image_type_to_mime_type', array_keys($default_formats))));
		if (empty($mime_valid))
			return false;

		fwrite($fp_destination, $fileContents);
		fclose($fp_destination);

		$sizes = @getimagesize($destination);
	}
	elseif ($fp_destination)
	{
		$mime_valid = check_mime_type($source, implode('|', array_map('image_type_to_mime_type', array_keys($default_formats))), true);
		if (empty($mime_valid))
			return false;

		$sizes = @getimagesize($source);

		$fp_source = fopen($source, 'rb');
		if ($fp_source !== false)
		{
			while (!feof($fp_source))
				fwrite($fp_destination, fread($fp_source, 8192));
			fclose($fp_source);
		}
		else
			$sizes = array(-1, -1, -1);
		fclose($fp_destination);
	}

	// We can't get to the file. or a previous getimagesize failed.
	if (empty($sizes))
		$sizes = array(-1, -1, -1);

	// See if we have -or- can get the needed memory for this operation
	// ImageMagick isn't subject to PHP's memory limits :)
	if (!(checkIMagick() || checkMagickWand()) && checkGD() && !imageMemoryCheck($sizes))
		return false;

	// A known and supported format?
	// @todo test PSD and gif.
	if ((checkImagick() || checkMagickWand()) && isset($default_formats[$sizes[2]]))
	{
		return resizeImage(null, $destination, null, null, $max_width, $max_height, true, $preferred_format);
	}
	elseif (checkGD() && isset($default_formats[$sizes[2]]) && function_exists('imagecreatefrom' . $default_formats[$sizes[2]]))
	{
		$imagecreatefrom = 'imagecreatefrom' . $default_formats[$sizes[2]];
		if ($src_img = @$imagecreatefrom($destination))
		{
			return resizeImage($src_img, $destination, imagesx($src_img), imagesy($src_img), $max_width === null ? imagesx($src_img) : $max_width, $max_height === null ? imagesy($src_img) : $max_height, true, $preferred_format);
		}
	}

	return false;
}

/**
 * Resizes src_img proportionally to fit within max_width and max_height limits
 * if it is too large.
 * If GD2 is present, it'll use it to achieve better quality.
 * It saves the new image to destination_filename, as preferred_format
 * if possible, default is jpeg.
 *
 * Uses Imagemagick (IMagick or MagickWand extension) or GD
 *
 * @param resource $src_img The source image
 * @param string $destName The path to the destination image
 * @param int $src_width The width of the source image
 * @param int $src_height The height of the source image
 * @param int $max_width The maximum allowed width
 * @param int $max_height The maximum allowed height
 * @param bool $force_resize = false Whether to forcibly resize it
 * @param int $preferred_format - 1 for gif, 2 for jpeg, 3 for png, 6 for bmp or 15 for wbmp
 * @return bool Whether the resize was successful
 */
function resizeImage($src_img, $destName, $src_width, $src_height, $max_width, $max_height, $force_resize = false, $preferred_format = 0)
{
	global $gd2;

	$orientation = 0;
	if (function_exists('exif_read_data') && ($exif_data = @exif_read_data($destName)) !== false && !empty($exif_data['Orientation']))
		$orientation = $exif_data['Orientation'];

	if (checkImagick() || checkMagickWand())
	{
		static $default_formats = array(
			'1' => 'gif',
			'2' => 'jpeg',
			'3' => 'png',
			'6' => 'bmp',
			'15' => 'wbmp'
		);
		$preferred_format = empty($preferred_format) || !isset($default_formats[$preferred_format]) ? 2 : $preferred_format;

		if (checkImagick())
		{
			$imagick = New Imagick($destName);
			$src_width = empty($src_width) ? $imagick->getImageWidth() : $src_width;
			$src_height = empty($src_height) ? $imagick->getImageHeight() : $src_height;
			$dest_width = empty($max_width) ? $src_width : $max_width;
			$dest_height = empty($max_height) ? $src_height : $max_height;

			if ($default_formats[$preferred_format] == 'jpeg')
				$imagick->setCompressionQuality(!empty(Config::$modSettings['avatar_jpeg_quality']) ? Config::$modSettings['avatar_jpeg_quality'] : 82);

			$imagick->setImageFormat($default_formats[$preferred_format]);
			$imagick->resizeImage($dest_width, $dest_height, Imagick::FILTER_LANCZOS, 1, true);

			if ($orientation > 1 && $preferred_format == 3)
			{
				if (in_array($orientation, [3, 4]))
					$imagick->rotateImage('#00000000', 180);
				elseif (in_array($orientation, [5, 6]))
					$imagick->rotateImage('#00000000', 90);
				elseif (in_array($orientation, [7, 8]))
					$imagick->rotateImage('#00000000', 270);

				if (in_array($orientation, [2, 4, 5, 7]))
					$imagick->flopImage();
			}
			$success = $imagick->writeImage($destName);
		}
		else
		{
			$magick_wand = newMagickWand();
			MagickReadImage($magick_wand, $destName);
			$src_width = empty($src_width) ? MagickGetImageWidth($magick_wand) : $src_width;
			$src_height = empty($src_height) ? MagickGetImageSize($magick_wand) : $src_height;
			$dest_width = empty($max_width) ? $src_width : $max_width;
			$dest_height = empty($max_height) ? $src_height : $max_height;

			if ($default_formats[$preferred_format] == 'jpeg')
				MagickSetCompressionQuality($magick_wand, !empty(Config::$modSettings['avatar_jpeg_quality']) ? Config::$modSettings['avatar_jpeg_quality'] : 82);

			MagickSetImageFormat($magick_wand, $default_formats[$preferred_format]);
			MagickResizeImage($magick_wand, $dest_width, $dest_height, MW_LanczosFilter, 1, true);

			if ($orientation > 1)
			{
				if (in_array($orientation, [3, 4]))
					MagickResizeImage($magick_wand, NewPixelWand('white'), 180);
				elseif (in_array($orientation, [5, 6]))
					MagickResizeImage($magick_wand, NewPixelWand('white'), 90);
				elseif (in_array($orientation, [7, 8]))
					MagickResizeImage($magick_wand, NewPixelWand('white'), 270);

				if (in_array($orientation, [2, 4, 5, 7]))
					MagickFlopImage($magick_wand);
			}
			$success = MagickWriteImage($magick_wand, $destName);
		}

		return !empty($success);
	}
	elseif (checkGD())
	{
		$success = false;

		// Determine whether to resize to max width or to max height (depending on the limits.)
		if (!empty($max_width) || !empty($max_height))
		{
			if (!empty($max_width) && (empty($max_height) || round($src_height * $max_width / $src_width) <= $max_height))
			{
				$dst_width = $max_width;
				$dst_height = round($src_height * $max_width / $src_width);
			}
			elseif (!empty($max_height))
			{
				$dst_width = round($src_width * $max_height / $src_height);
				$dst_height = $max_height;
			}

			// Don't bother resizing if it's already smaller...
			if (!empty($dst_width) && !empty($dst_height) && ($dst_width < $src_width || $dst_height < $src_height || $force_resize))
			{
				// (make a true color image, because it just looks better for resizing.)
				if ($gd2)
				{
					$dst_img = imagecreatetruecolor($dst_width, $dst_height);

					// Deal nicely with a PNG - because we can.
					if ((!empty($preferred_format)) && ($preferred_format == 3))
					{
						imagealphablending($dst_img, false);
						if (function_exists('imagesavealpha'))
							imagesavealpha($dst_img, true);
					}
				}
				else
					$dst_img = imagecreate($dst_width, $dst_height);

				// Resize it!
				if ($gd2)
					imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $dst_width, $dst_height, $src_width, $src_height);
				else
					imagecopyresamplebicubic($dst_img, $src_img, 0, 0, 0, 0, $dst_width, $dst_height, $src_width, $src_height);
			}
			else
				$dst_img = $src_img;
		}
		else
			$dst_img = $src_img;

		if ($orientation > 1)
		{
			if (in_array($orientation, [3, 4]))
				$dst_img = imagerotate($dst_img, 180, 0);
			elseif (in_array($orientation, [5, 6]))
				$dst_img = imagerotate($dst_img, 270, 0);
			elseif (in_array($orientation, [7, 8]))
				$dst_img = imagerotate($dst_img, 90, 0);

			if (in_array($orientation, [2, 4, 5, 7]))
				imageflip($dst_img, IMG_FLIP_HORIZONTAL);
		}

		// Save the image as ...
		if (!empty($preferred_format) && ($preferred_format == 3) && function_exists('imagepng'))
			$success = imagepng($dst_img, $destName);
		elseif (!empty($preferred_format) && ($preferred_format == 1) && function_exists('imagegif'))
			$success = imagegif($dst_img, $destName);
		elseif (!empty($preferred_format) && ($preferred_format == 6) && function_exists('imagebmp'))
			$success = imagebmp($dst_img, $destName);
		elseif (!empty($preferred_format) && ($preferred_format == 15) && function_exists('imagewbmp'))
			$success = imagewbmp($dst_img, $destName);
		elseif (function_exists('imagejpeg'))
			$success = imagejpeg($dst_img, $destName, !empty(Config::$modSettings['avatar_jpeg_quality']) ? Config::$modSettings['avatar_jpeg_quality'] : 82);

		// Free the memory.
		imagedestroy($src_img);
		if ($dst_img != $src_img)
			imagedestroy($dst_img);

		return $success;
	}
	else
		// Without GD, no image resizing at all.
		return false;
}

/**
 * Copy image.
 * Used when imagecopyresample() is not available.
 *
 * @param resource $dst_img The destination image - a GD image resource
 * @param resource $src_img The source image - a GD image resource
 * @param int $dst_x The "x" coordinate of the destination image
 * @param int $dst_y The "y" coordinate of the destination image
 * @param int $src_x The "x" coordinate of the source image
 * @param int $src_y The "y" coordinate of the source image
 * @param int $dst_w The width of the destination image
 * @param int $dst_h The height of the destination image
 * @param int $src_w The width of the destination image
 * @param int $src_h The height of the destination image
 */
function imagecopyresamplebicubic($dst_img, $src_img, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h)
{
	$palsize = imagecolorstotal($src_img);
	for ($i = 0; $i < $palsize; $i++)
	{
		$colors = imagecolorsforindex($src_img, $i);
		imagecolorallocate($dst_img, $colors['red'], $colors['green'], $colors['blue']);
	}

	$scaleX = ($src_w - 1) / $dst_w;
	$scaleY = ($src_h - 1) / $dst_h;

	$scaleX2 = (int) $scaleX / 2;
	$scaleY2 = (int) $scaleY / 2;

	for ($j = $src_y; $j < $dst_h; $j++)
	{
		$sY = (int) $j * $scaleY;
		$y13 = $sY + $scaleY2;

		for ($i = $src_x; $i < $dst_w; $i++)
		{
			$sX = (int) $i * $scaleX;
			$x34 = $sX + $scaleX2;

			$color1 = imagecolorsforindex($src_img, imagecolorat($src_img, $sX, $y13));
			$color2 = imagecolorsforindex($src_img, imagecolorat($src_img, $sX, $sY));
			$color3 = imagecolorsforindex($src_img, imagecolorat($src_img, $x34, $y13));
			$color4 = imagecolorsforindex($src_img, imagecolorat($src_img, $x34, $sY));

			$red = ($color1['red'] + $color2['red'] + $color3['red'] + $color4['red']) / 4;
			$green = ($color1['green'] + $color2['green'] + $color3['green'] + $color4['green']) / 4;
			$blue = ($color1['blue'] + $color2['blue'] + $color3['blue'] + $color4['blue']) / 4;

			$color = imagecolorresolve($dst_img, $red, $green, $blue);
			if ($color == -1)
			{
				if ($palsize++ < 256)
					imagecolorallocate($dst_img, $red, $green, $blue);
				$color = imagecolorclosest($dst_img, $red, $green, $blue);
			}

			imagesetpixel($dst_img, $i + $dst_x - $src_x, $j + $dst_y - $src_y, $color);
		}
	}
}

if (!function_exists('imagecreatefrombmp'))
{
	/**
	 * It is set only if it doesn't already exist (for forwards compatibility.)
	 * It only supports uncompressed bitmaps.
	 *
	 * @param string $filename The name of the file
	 * @return resource An image identifier representing the bitmap image
	 * obtained from the given filename.
	 */
	function imagecreatefrombmp($filename)
	{
		global $gd2;

		$fp = fopen($filename, 'rb');

		$errors = error_reporting(0);

		$header = unpack('vtype/Vsize/Vreserved/Voffset', fread($fp, 14));
		$info = unpack('Vsize/Vwidth/Vheight/vplanes/vbits/Vcompression/Vimagesize/Vxres/Vyres/Vncolor/Vcolorimportant', fread($fp, 40));

		if ($header['type'] != 0x4D42)
			return false;

		if ($gd2)
			$dst_img = imagecreatetruecolor($info['width'], $info['height']);
		else
			$dst_img = imagecreate($info['width'], $info['height']);

		$palette_size = $header['offset'] - 54;
		$info['ncolor'] = $palette_size / 4;

		$palette = array();

		$palettedata = fread($fp, $palette_size);
		$n = 0;
		for ($j = 0; $j < $palette_size; $j++)
		{
			$b = ord($palettedata[$j++]);
			$g = ord($palettedata[$j++]);
			$r = ord($palettedata[$j++]);

			$palette[$n++] = imagecolorallocate($dst_img, $r, $g, $b);
		}

		$scan_line_size = ($info['bits'] * $info['width'] + 7) >> 3;
		$scan_line_align = $scan_line_size & 3 ? 4 - ($scan_line_size & 3) : 0;

		for ($y = 0, $l = $info['height'] - 1; $y < $info['height']; $y++, $l--)
		{
			fseek($fp, $header['offset'] + ($scan_line_size + $scan_line_align) * $l);
			$scan_line = fread($fp, $scan_line_size);

			if (strlen($scan_line) < $scan_line_size)
				continue;

			if ($info['bits'] == 32)
			{
				$x = 0;
				for ($j = 0; $j < $scan_line_size; $x++)
				{
					$b = ord($scan_line[$j++]);
					$g = ord($scan_line[$j++]);
					$r = ord($scan_line[$j++]);
					$j++;

					$color = imagecolorexact($dst_img, $r, $g, $b);
					if ($color == -1)
					{
						$color = imagecolorallocate($dst_img, $r, $g, $b);

						// Gah!  Out of colors?  Stupid GD 1... try anyhow.
						if ($color == -1)
							$color = imagecolorclosest($dst_img, $r, $g, $b);
					}

					imagesetpixel($dst_img, $x, $y, $color);
				}
			}
			elseif ($info['bits'] == 24)
			{
				$x = 0;
				for ($j = 0; $j < $scan_line_size; $x++)
				{
					$b = ord($scan_line[$j++]);
					$g = ord($scan_line[$j++]);
					$r = ord($scan_line[$j++]);

					$color = imagecolorexact($dst_img, $r, $g, $b);
					if ($color == -1)
					{
						$color = imagecolorallocate($dst_img, $r, $g, $b);

						// Gah!  Out of colors?  Stupid GD 1... try anyhow.
						if ($color == -1)
							$color = imagecolorclosest($dst_img, $r, $g, $b);
					}

					imagesetpixel($dst_img, $x, $y, $color);
				}
			}
			elseif ($info['bits'] == 16)
			{
				$x = 0;
				for ($j = 0; $j < $scan_line_size; $x++)
				{
					$b1 = ord($scan_line[$j++]);
					$b2 = ord($scan_line[$j++]);

					$word = $b2 * 256 + $b1;

					$b = (($word & 31) * 255) / 31;
					$g = ((($word >> 5) & 31) * 255) / 31;
					$r = ((($word >> 10) & 31) * 255) / 31;

					// Scale the image colors up properly.
					$color = imagecolorexact($dst_img, $r, $g, $b);
					if ($color == -1)
					{
						$color = imagecolorallocate($dst_img, $r, $g, $b);

						// Gah!  Out of colors?  Stupid GD 1... try anyhow.
						if ($color == -1)
							$color = imagecolorclosest($dst_img, $r, $g, $b);
					}

					imagesetpixel($dst_img, $x, $y, $color);
				}
			}
			elseif ($info['bits'] == 8)
			{
				$x = 0;
				for ($j = 0; $j < $scan_line_size; $x++)
					imagesetpixel($dst_img, $x, $y, $palette[ord($scan_line[$j++])]);
			}
			elseif ($info['bits'] == 4)
			{
				$x = 0;
				for ($j = 0; $j < $scan_line_size; $x++)
				{
					$byte = ord($scan_line[$j++]);

					imagesetpixel($dst_img, $x, $y, $palette[(int) ($byte / 16)]);

					if (++$x < $info['width'])
						imagesetpixel($dst_img, $x, $y, $palette[$byte & 15]);
				}
			}
			elseif ($info['bits'] == 1)
			{
				$x = 0;
				for ($j = 0; $j < $scan_line_size; $x++)
				{
					$byte = ord($scan_line[$j++]);

					imagesetpixel($dst_img, $x, $y, $palette[(($byte) & 128) != 0]);

					for ($shift = 1; $shift < 8; $shift++)
					{
						if (++$x < $info['width'])
							imagesetpixel($dst_img, $x, $y, $palette[(($byte << $shift) & 128) != 0]);
					}
				}
			}
		}

		fclose($fp);

		error_reporting($errors);

		return $dst_img;
	}
}

/**
 * Writes a gif file to disk as a png file.
 *
 * @param gif_file $gif A gif image resource
 * @param string $lpszFileName The name of the file
 * @param int $background_color The background color
 * @return bool Whether the operation was successful
 */
function gif_outputAsPng($gif, $lpszFileName, $background_color = -1)
{
	if (!is_a($gif, Gif\File::class) || $lpszFileName == '')
		return false;

	if (($fd = $gif->get_png_data($background_color)) === false)
		return false;

	if (($fh = @fopen($lpszFileName, 'wb')) === false)
		return false;

	@fwrite($fh, $fd, strlen($fd));
	@fflush($fh);
	@fclose($fh);

	return true;
}

/**
 * Gets the dimensions of an SVG image (specifically, of its viewport).
 *
 * See https://www.w3.org/TR/SVG11/coords.html#IntrinsicSizing
 *
 * @param string $filepath The path to the SVG file.
 * @return array The width and height of the SVG image in pixels.
 */
function getSvgSize($filepath)
{
	preg_match('/<svg\b[^>]*>/', file_get_contents($filepath, false, null, 0, 480), $matches);
	$svg = $matches[0];

	// If the SVG has width and height attributes, use those.
	// If attribute is missing, SVG spec says the default is '100%'.
	// If no unit is supplied, spec says unit defaults to px.
	foreach (array('width', 'height') as $dimension)
	{
		if (preg_match("/\b$dimension\s*=\s*([\"'])\s*([\d.]+)([\D\S]*)\s*\\1/", $svg, $matches))
		{
			$$dimension = $matches[2];
			$unit = !empty($matches[3]) ? $matches[3] : 'px';
		}
		else
		{
			$$dimension = 100;
			$unit = '%';
		}

		// Resolve unit.
		switch ($unit)
		{
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
	if ((!isset($width) || !isset($height)) && preg_match('/\bviewBox\s*=\s*(["\'])\s*[\d.]+[,\s]+[\d.]+[,\s]+([\d.]+)[,\s]+([\d.]+)\s*\\1/', $svg, $matches))
	{
		$vb_width = $matches[2];
		$vb_height = $matches[3];

		// No dimensions given, so use viewBox dimensions.
		if (!isset($width) && !isset($height))
		{
			$width = $vb_width;
			$height = $vb_height;
		}
		// Width but no height, so calculate height.
		elseif (isset($width))
		{
			$height = $width * $vb_height / $vb_width;
		}
		// Height but no width, so calculate width.
		elseif (isset($height))
		{
			$width = $height * $vb_width / $vb_height;
		}
	}

	// Viewport undefined, so call it infinite.
	if (!isset($width) && !isset($height))
	{
		$width = INF;
		$height = INF;
	}

	return array('width' => round($width), 'height' => round($height));
}

?>