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
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * downloads a file from a url and stores it locally for avatar use by id_member.
 * - supports GIF, JPG, PNG, BMP and WBMP formats.
 * - detects if GD2 is available.
 * - uses resizeImageFile() to resize to max_width by max_height, and saves the result to a file.
 * - updates the database info for the member's avatar.
 * - returns whether the download and resize was successful.
 *
 * @param string $url The full path to the temporary file
 * @param int $memID The member ID
 * @param int $max_width The maximum allowed width for the avatar
 * @param int $max_height The maximum allowed height for the avatar
 * @return boolean Whether the download and resize was successful.
 *
 */
function downloadAvatar($url, $memID, $max_width, $max_height)
{
	global $modSettings, $sourcedir, $smcFunc;

	$ext = !empty($modSettings['avatar_download_png']) ? 'png' : 'jpeg';
	$destName = 'avatar_' . $memID . '_' . time() . '.' . $ext;

	// Just making sure there is a non-zero member.
	if (empty($memID))
		return false;

	require_once($sourcedir . '/ManageAttachments.php');
	removeAttachments(array('id_member' => $memID));

	$id_folder = 1;
	$avatar_hash = '';
	$attachID = $smcFunc['db_insert']('',
		'{db_prefix}attachments',
		array(
			'id_member' => 'int', 'attachment_type' => 'int', 'filename' => 'string-255', 'file_hash' => 'string-255', 'fileext' => 'string-8', 'size' => 'int',
			'id_folder' => 'int',
		),
		array(
			$memID, 1, $destName, $avatar_hash, $ext, 1,
			$id_folder,
		),
		array('id_attach'),
		1
	);

	// Retain this globally in case the script wants it.
	$modSettings['new_avatar_data'] = array(
		'id' => $attachID,
		'filename' => $destName,
		'type' => 1,
	);

	$destName = $modSettings['custom_avatar_dir'] . '/' . $destName . '.tmp';

	// Resize it.
	if (!empty($modSettings['avatar_download_png']))
		$success = resizeImageFile($url, $destName, $max_width, $max_height, 3);
	else
		$success = resizeImageFile($url, $destName, $max_width, $max_height);

	// Remove the .tmp extension.
	$destName = substr($destName, 0, -4);

	if ($success)
	{
		// Remove the .tmp extension from the attachment.
		if (rename($destName . '.tmp', $destName))
		{
			list ($width, $height) = getimagesize($destName);
			$mime_type = 'image/' . $ext;

			// Write filesize in the database.
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}attachments
				SET size = {int:filesize}, width = {int:width}, height = {int:height},
					mime_type = {string:mime_type}
				WHERE id_attach = {int:current_attachment}',
				array(
					'filesize' => filesize($destName),
					'width' => (int) $width,
					'height' => (int) $height,
					'current_attachment' => $attachID,
					'mime_type' => $mime_type,
				)
			);
			return true;
		}
		else
			return false;
	}
	else
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}attachments
			WHERE id_attach = {int:current_attachment}',
			array(
				'current_attachment' => $attachID,
			)
		);

		@unlink($destName . '.tmp');
		return false;
	}
}

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
	global $modSettings;

	$destName = $source . '_thumb.tmp';

	// Do the actual resize.
	if (!empty($modSettings['attachment_thumb_png']))
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
		fatal_lang_error('attach_timeout');

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
	global $modSettings;

	// doing the old 'set it and hope' way?
	if (empty($modSettings['attachment_thumb_memory']))
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
	global $sourcedir;

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

		fwrite($fp_destination, $fileContents);
		fclose($fp_destination);

		$sizes = @getimagesize($destination);
	}
	elseif ($fp_destination)
	{
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
	// We can't get to the file.
	else
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
 * @uses Imagemagick (IMagick or MagickWand extension) or GD
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
	global $gd2, $modSettings;

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
				$imagick->setCompressionQuality(!empty($modSettings['avatar_jpeg_quality']) ? $modSettings['avatar_jpeg_quality'] : 82);

			$imagick->setImageFormat($default_formats[$preferred_format]);
			$imagick->resizeImage($dest_width, $dest_height, Imagick::FILTER_LANCZOS, 1, true);
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
				MagickSetCompressionQuality($magick_wand, !empty($modSettings['avatar_jpeg_quality']) ? $modSettings['avatar_jpeg_quality'] : 82);

			MagickSetImageFormat($magick_wand, $default_formats[$preferred_format]);
			MagickResizeImage($magick_wand, $dest_width, $dest_height, MW_LanczosFilter, 1, true);
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

		// Save the image as ...
		if (!empty($preferred_format) && ($preferred_format == 3) && function_exists('imagepng'))
			$success = imagepng($dst_img, $destName);
		elseif (!empty($preferred_format) && ($preferred_format == 1) && function_exists('imagegif'))
			$success = imagegif($dst_img, $destName);
		elseif (function_exists('imagejpeg'))
			$success = imagejpeg($dst_img, $destName, !empty($modSettings['avatar_jpeg_quality']) ? $modSettings['avatar_jpeg_quality'] : 82);

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
	 * It is set only if it doesn't already exist (for forwards compatiblity.)
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
			$b = ord($palettedata{$j++});
			$g = ord($palettedata{$j++});
			$r = ord($palettedata{$j++});

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
					$b = ord($scan_line{$j++});
					$g = ord($scan_line{$j++});
					$r = ord($scan_line{$j++});
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
					$b = ord($scan_line{$j++});
					$g = ord($scan_line{$j++});
					$r = ord($scan_line{$j++});

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
					$b1 = ord($scan_line{$j++});
					$b2 = ord($scan_line{$j++});

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
					imagesetpixel($dst_img, $x, $y, $palette[ord($scan_line{$j++})]);
			}
			elseif ($info['bits'] == 4)
			{
				$x = 0;
				for ($j = 0; $j < $scan_line_size; $x++)
				{
					$byte = ord($scan_line{$j++});

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
					$byte = ord($scan_line{$j++});

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
 * @param resource $gif A gif image resource
 * @param string $lpszFileName The name of the file
 * @param int $background_color The background color
 * @return boolean Whether the operation was successful
 */
function gif_outputAsPng($gif, $lpszFileName, $background_color = -1)
{
	if (!isset($gif) || @get_class($gif) != 'cgif' || !$gif->loaded || $lpszFileName == '')
		return false;

	$fd = $gif->get_png_data($background_color);
	if (strlen($fd) <= 0)
		return false;

	if (!($fh = @fopen($lpszFileName, 'wb')))
		return false;

	@fwrite($fh, $fd, strlen($fd));
	@fflush($fh);
	@fclose($fh);

	return true;
}

/**
 * Show an image containing the visual verification code for registration.
 * Requires the GD extension.
 * Uses a random font for each letter from default_theme_dir/fonts.
 * Outputs a gif or a png (depending on whether gif ix supported).
 *
 * @param string $code The code to display
 * @return void|false False if something goes wrong.
 */
function showCodeImage($code)
{
	global $gd2, $settings, $user_info, $modSettings;

	// Note: The higher the value of visual_verification_type the harder the verification is - from 0 as disabled through to 4 as "Very hard".

	// What type are we going to be doing?
	$imageType = $modSettings['visual_verification_type'];
	// Special case to allow the admin center to show samples.
	if ($user_info['is_admin'] && isset($_GET['type']))
		$imageType = (int) $_GET['type'];

	// Some quick references for what we do.
	// Do we show no, low or high noise?
	$noiseType = $imageType == 3 ? 'low' : ($imageType == 4 ? 'high' : ($imageType == 5 ? 'extreme' : 'none'));
	// Can we have more than one font in use?
	$varyFonts = $imageType > 3 ? true : false;
	// Just a plain white background?
	$simpleBGColor = $imageType < 3 ? true : false;
	// Plain black foreground?
	$simpleFGColor = $imageType == 0 ? true : false;
	// High much to rotate each character.
	$rotationType = $imageType == 1 ? 'none' : ($imageType > 3 ? 'low' : 'high');
	// Do we show some characters inversed?
	$showReverseChars = $imageType > 3 ? true : false;
	// Special case for not showing any characters.
	$disableChars = $imageType == 0 ? true : false;
	// What do we do with the font colors. Are they one color, close to one color or random?
	$fontColorType = $imageType == 1 ? 'plain' : ($imageType > 3 ? 'random' : 'cyclic');
	// Are the fonts random sizes?
	$fontSizeRandom = $imageType > 3 ? true : false;
	// How much space between characters?
	$fontHorSpace = $imageType > 3 ? 'high' : ($imageType == 1 ? 'medium' : 'minus');
	// Where do characters sit on the image? (Fixed position or random/very random)
	$fontVerPos = $imageType == 1 ? 'fixed' : ($imageType > 3 ? 'vrandom' : 'random');
	// Make font semi-transparent?
	$fontTrans = $imageType == 2 || $imageType == 3 ? true : false;
	// Give the image a border?
	$hasBorder = $simpleBGColor;

	// The amount of pixels inbetween characters.
	$character_spacing = 1;

	// What color is the background - generally white unless we're on "hard".
	if ($simpleBGColor)
		$background_color = array(255, 255, 255);
	else
		$background_color = isset($settings['verification_background']) ? $settings['verification_background'] : array(236, 237, 243);

	// The color of the characters shown (red, green, blue).
	if ($simpleFGColor)
		$foreground_color = array(0, 0, 0);
	else
	{
		$foreground_color = array(64, 101, 136);

		// Has the theme author requested a custom color?
		if (isset($settings['verification_foreground']))
			$foreground_color = $settings['verification_foreground'];
	}

	if (!is_dir($settings['default_theme_dir'] . '/fonts'))
		return false;

	// Get a list of the available fonts.
	$font_dir = dir($settings['default_theme_dir'] . '/fonts');
	$font_list = array();
	$ttfont_list = array();
	$endian = unpack('v', pack('S', 0x00FF)) === 0x00FF;
	while ($entry = $font_dir->read())
	{
		if (preg_match('~^(.+)\.gdf$~', $entry, $matches) === 1)
		{
			if ($endian ^ (strpos($entry, '_end.gdf') === false))
				$font_list[] = $entry;
		}
		elseif (preg_match('~^(.+)\.ttf$~', $entry, $matches) === 1)
			$ttfont_list[] = $entry;
	}

	if (empty($font_list))
		return false;

	// For non-hard things don't even change fonts.
	if (!$varyFonts)
	{
		$font_list = array($font_list[0]);
		// Try use Screenge if we can - it looks good!
		if (in_array('AnonymousPro.ttf', $ttfont_list))
			$ttfont_list = array('AnonymousPro.ttf');
		else
			$ttfont_list = empty($ttfont_list) ? array() : array($ttfont_list[0]);
	}

	// Create a list of characters to be shown.
	$characters = array();
	$loaded_fonts = array();
	for ($i = 0; $i < strlen($code); $i++)
	{
		$characters[$i] = array(
			'id' => $code{$i},
			'font' => array_rand($font_list),
		);

		$loaded_fonts[$characters[$i]['font']] = null;
	}

	// Load all fonts and determine the maximum font height.
	foreach ($loaded_fonts as $font_index => $dummy)
		$loaded_fonts[$font_index] = imageloadfont($settings['default_theme_dir'] . '/fonts/' . $font_list[$font_index]);

	// Determine the dimensions of each character.
	if ($imageType == 4 || $imageType == 5)
		$extra = 80;
	else
		$extra = 45;

	$total_width = $character_spacing * strlen($code) + $extra;
	$max_height = 0;
	foreach ($characters as $char_index => $character)
	{
		$characters[$char_index]['width'] = imagefontwidth($loaded_fonts[$character['font']]);
		$characters[$char_index]['height'] = imagefontheight($loaded_fonts[$character['font']]);

		$max_height = max($characters[$char_index]['height'] + 5, $max_height);
		$total_width += $characters[$char_index]['width'];
	}

	// Create an image.
	$code_image = $gd2 ? imagecreatetruecolor($total_width, $max_height) : imagecreate($total_width, $max_height);

	// Draw the background.
	$bg_color = imagecolorallocate($code_image, $background_color[0], $background_color[1], $background_color[2]);
	imagefilledrectangle($code_image, 0, 0, $total_width - 1, $max_height - 1, $bg_color);

	// Randomize the foreground color a little.
	for ($i = 0; $i < 3; $i++)
		$foreground_color[$i] = mt_rand(max($foreground_color[$i] - 3, 0), min($foreground_color[$i] + 3, 255));
	$fg_color = imagecolorallocate($code_image, $foreground_color[0], $foreground_color[1], $foreground_color[2]);

	// Color for the dots.
	for ($i = 0; $i < 3; $i++)
		$dotbgcolor[$i] = $background_color[$i] < $foreground_color[$i] ? mt_rand(0, max($foreground_color[$i] - 20, 0)) : mt_rand(min($foreground_color[$i] + 20, 255), 255);
	$randomness_color = imagecolorallocate($code_image, $dotbgcolor[0], $dotbgcolor[1], $dotbgcolor[2]);

	// Some squares/rectanges for new extreme level
	if ($noiseType == 'extreme')
	{
		for ($i = 0; $i < mt_rand(1, 5); $i++)
		{
			$x1 = mt_rand(0, $total_width / 4);
			$x2 = $x1 + round(rand($total_width / 4, $total_width));
			$y1 = mt_rand(0, $max_height);
			$y2 = $y1 + round(rand(0, $max_height / 3));
			imagefilledrectangle($code_image, $x1, $y1, $x2, $y2, mt_rand(0, 1) ? $fg_color : $randomness_color);
		}
	}

	// Fill in the characters.
	if (!$disableChars)
	{
		$cur_x = 0;
		foreach ($characters as $char_index => $character)
		{
			// Can we use true type fonts?
			$can_do_ttf = function_exists('imagettftext');

			// How much rotation will we give?
			if ($rotationType == 'none')
				$angle = 0;
			else
				$angle = mt_rand(-100, 100) / ($rotationType == 'high' ? 6 : 10);

			// What color shall we do it?
			if ($fontColorType == 'cyclic')
			{
				// Here we'll pick from a set of acceptance types.
				$colors = array(
					array(10, 120, 95),
					array(46, 81, 29),
					array(4, 22, 154),
					array(131, 9, 130),
					array(0, 0, 0),
					array(143, 39, 31),
				);
				if (!isset($last_index))
					$last_index = -1;
				$new_index = $last_index;
				while ($last_index == $new_index)
					$new_index = mt_rand(0, count($colors) - 1);
				$char_fg_color = $colors[$new_index];
				$last_index = $new_index;
			}
			elseif ($fontColorType == 'random')
				$char_fg_color = array(mt_rand(max($foreground_color[0] - 2, 0), $foreground_color[0]), mt_rand(max($foreground_color[1] - 2, 0), $foreground_color[1]), mt_rand(max($foreground_color[2] - 2, 0), $foreground_color[2]));
			else
				$char_fg_color = array($foreground_color[0], $foreground_color[1], $foreground_color[2]);

			if (!empty($can_do_ttf))
			{
				// GD2 handles font size differently.
				if ($fontSizeRandom)
					$font_size = $gd2 ? mt_rand(17, 19) : mt_rand(18, 25);
				else
					$font_size = $gd2 ? 18 : 24;

				// Work out the sizes - also fix the character width cause TTF not quite so wide!
				$font_x = $fontHorSpace == 'minus' && $cur_x > 0 ? $cur_x - 3 : $cur_x + 5;
				$font_y = $max_height - ($fontVerPos == 'vrandom' ? mt_rand(2, 8) : ($fontVerPos == 'random' ? mt_rand(3, 5) : 5));

				// What font face?
				if (!empty($ttfont_list))
					$fontface = $settings['default_theme_dir'] . '/fonts/' . $ttfont_list[mt_rand(0, count($ttfont_list) - 1)];

				// What color are we to do it in?
				$is_reverse = $showReverseChars ? mt_rand(0, 1) : false;
				$char_color = function_exists('imagecolorallocatealpha') && $fontTrans ? imagecolorallocatealpha($code_image, $char_fg_color[0], $char_fg_color[1], $char_fg_color[2], 50) : imagecolorallocate($code_image, $char_fg_color[0], $char_fg_color[1], $char_fg_color[2]);

				$fontcord = @imagettftext($code_image, $font_size, $angle, $font_x, $font_y, $char_color, $fontface, $character['id']);
				if (empty($fontcord))
					$can_do_ttf = false;
				elseif ($is_reverse)
				{
					imagefilledpolygon($code_image, $fontcord, 4, $fg_color);
					// Put the character back!
					imagettftext($code_image, $font_size, $angle, $font_x, $font_y, $randomness_color, $fontface, $character['id']);
				}

				if ($can_do_ttf)
					$cur_x = max($fontcord[2], $fontcord[4]) + ($angle == 0 ? 0 : 3);
			}

			if (!$can_do_ttf)
			{
				// Rotating the characters a little...
				if (function_exists('imagerotate'))
				{
					$char_image = $gd2 ? imagecreatetruecolor($character['width'], $character['height']) : imagecreate($character['width'], $character['height']);
					$char_bgcolor = imagecolorallocate($char_image, $background_color[0], $background_color[1], $background_color[2]);
					imagefilledrectangle($char_image, 0, 0, $character['width'] - 1, $character['height'] - 1, $char_bgcolor);
					imagechar($char_image, $loaded_fonts[$character['font']], 0, 0, $character['id'], imagecolorallocate($char_image, $char_fg_color[0], $char_fg_color[1], $char_fg_color[2]));
					$rotated_char = imagerotate($char_image, mt_rand(-100, 100) / 10, $char_bgcolor);
					imagecopy($code_image, $rotated_char, $cur_x, 0, 0, 0, $character['width'], $character['height']);
					imagedestroy($rotated_char);
					imagedestroy($char_image);
				}

				// Sorry, no rotation available.
				else
					imagechar($code_image, $loaded_fonts[$character['font']], $cur_x, floor(($max_height - $character['height']) / 2), $character['id'], imagecolorallocate($code_image, $char_fg_color[0], $char_fg_color[1], $char_fg_color[2]));
				$cur_x += $character['width'] + $character_spacing;
			}
		}
	}
	// If disabled just show a cross.
	else
	{
		imageline($code_image, 0, 0, $total_width, $max_height, $fg_color);
		imageline($code_image, 0, $max_height, $total_width, 0, $fg_color);
	}

	// Make the background color transparent on the hard image.
	if (!$simpleBGColor)
		imagecolortransparent($code_image, $bg_color);
	if ($hasBorder)
		imagerectangle($code_image, 0, 0, $total_width - 1, $max_height - 1, $fg_color);

	// Add some noise to the background?
	if ($noiseType != 'none')
	{
		for ($i = mt_rand(0, 2); $i < $max_height; $i += mt_rand(1, 2))
			for ($j = mt_rand(0, 10); $j < $total_width; $j += mt_rand(1, 10))
				imagesetpixel($code_image, $j, $i, mt_rand(0, 1) ? $fg_color : $randomness_color);

		// Put in some lines too?
		if ($noiseType != 'extreme')
		{
			$num_lines = $noiseType == 'high' ? mt_rand(3, 7) : mt_rand(2, 5);
			for ($i = 0; $i < $num_lines; $i++)
			{
				if (mt_rand(0, 1))
				{
					$x1 = mt_rand(0, $total_width);
					$x2 = mt_rand(0, $total_width);
					$y1 = 0;
					$y2 = $max_height;
				}
				else
				{
					$y1 = mt_rand(0, $max_height);
					$y2 = mt_rand(0, $max_height);
					$x1 = 0;
					$x2 = $total_width;
				}
				imagesetthickness($code_image, mt_rand(1, 2));
				imageline($code_image, $x1, $y1, $x2, $y2, mt_rand(0, 1) ? $fg_color : $randomness_color);
			}
		}
		else
		{
			// Put in some ellipse
			$num_ellipse = $noiseType == 'extreme' ? mt_rand(6, 12) : mt_rand(2, 6);
			for ($i = 0; $i < $num_ellipse; $i++)
			{
				$x1 = round(rand(($total_width / 4) * -1, $total_width + ($total_width / 4)));
				$x2 = round(rand($total_width / 2, 2 * $total_width));
				$y1 = round(rand(($max_height / 4) * -1, $max_height + ($max_height / 4)));
				$y2 = round(rand($max_height / 2, 2 * $max_height));
				imageellipse($code_image, $x1, $y1, $x2, $y2, mt_rand(0, 1) ? $fg_color : $randomness_color);
			}
		}
	}

	// Show the image.
	if (function_exists('imagegif'))
	{
		header('content-type: image/gif');
		imagegif($code_image);
	}
	else
	{
		header('content-type: image/png');
		imagepng($code_image);
	}

	// Bail out.
	imagedestroy($code_image);
	die();
}

/**
 * Show a letter for the visual verification code.
 * Alternative function for showCodeImage() in case GD is missing.
 * Includes an image from a random sub directory of default_theme_dir/fonts.
 *
 * @param string $letter A letter to show as an image
 * @return void|false False if something went wrong
 */
function showLetterImage($letter)
{
	global $settings;

	if (!is_dir($settings['default_theme_dir'] . '/fonts'))
		return false;

	// Get a list of the available font directories.
	$font_dir = dir($settings['default_theme_dir'] . '/fonts');
	$font_list = array();
	while ($entry = $font_dir->read())
		if ($entry[0] !== '.' && is_dir($settings['default_theme_dir'] . '/fonts/' . $entry) && file_exists($settings['default_theme_dir'] . '/fonts/' . $entry . '.gdf'))
			$font_list[] = $entry;

	if (empty($font_list))
		return false;

	// Pick a random font.
	$random_font = $font_list[array_rand($font_list)];

	// Check if the given letter exists.
	if (!file_exists($settings['default_theme_dir'] . '/fonts/' . $random_font . '/' . $letter . '.png'))
		return false;

	// Include it!
	header('content-type: image/png');
	include($settings['default_theme_dir'] . '/fonts/' . $random_font . '/' . $letter . '.png');

	// Nothing more to come.
	die();
}

?>