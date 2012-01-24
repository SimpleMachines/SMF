<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

// TrueType fonts supplied by www.LarabieFonts.com

if (!defined('SMF'))
	die('Hacking attempt...');

/*	This whole file deals almost exclusively with handling avatars,
	specifically uploaded ones.  It uses, for gifs at least, Gif Util... for
	more information on that, please see its website, shown above.  The other
	functions are as follows:

	bool downloadAvatar(string url, int id_member, int max_width,
			int max_height)
		- downloads file from url and stores it locally for avatar use
		  by id_member.
		- supports GIF, JPG, PNG, BMP and WBMP formats.
		- detects if GD2 is available.
		- if GIF support isn't present in GD, handles GIFs with gif_loadFile()
		  and gif_outputAsPng().
		- uses resizeImageFile() to resize to max_width by max_height,
		  and saves the result to a file.
		- updates the database info for the member's avatar.
		- returns whether the download and resize was successful.

	bool createThumbnail(string source, int max_width, int max_height)
		- create a thumbnail of the given source.
		- uses the resizeImageFile function to achieve the resize.
		- returns whether the thumbnail creation was successful.

	bool reencodeImage(string fileName, int preferred_format = 0)
		- creates a copy of the file at the same location as fileName.
		- the file would have the format preferred_format if possible,
		  otherwise the default format is jpeg.
		- makes sure that all non-essential image contents are disposed.
		- returns true on success, false on failure.

	bool checkImageContents(string fileName, bool extensiveCheck = false)
		- searches through the file to see if there's non-binary content.
		- if extensiveCheck is true, searches for asp/php short tags as well.
		- returns true on success, false on failure.

	bool checkGD()
		- sets a global $gd2 variable needed by some functions to determine
		  whetehr the GD2 library is present.
		- returns whether or not GD1 is available.

	void resizeImageFile(string source, string destination,
			int max_width, int max_height, int preferred_format = 0)
		- resizes an image from a remote location or a local file.
		- puts the resized image at the destination location.
		- the file would have the format preferred_format if possible,
		  otherwise the default format is jpeg.
		- returns whether it succeeded.

	void resizeImage(resource src_img, string destination_filename,
			int src_width, int src_height, int max_width, int max_height,
			int preferred_format)
		- resizes src_img proportionally to fit within max_width and
		  max_height limits if it is too large.
		- if GD2 is present, it'll use it to achieve better quality.
		- saves the new image to destination_filename.
		- saves as preferred_format if possible, default is jpeg.

	void imagecopyresamplebicubic(resource dest_img, resource src_img,
			int dest_x, int dest_y, int src_x, int src_y, int dest_w,
			int dest_h, int src_w, int src_h)
		- used when imagecopyresample() is not available.

	resource gif_loadFile(string filename, int animation_index)
		- loads a gif file with the Yamasoft GIF utility class.
		- returns a new GD image.

	bool gif_outputAsPng(resource gif, string destination_filename,
			int bgColor = -1)
		- writes a gif file to disk as a png file.
		- returns whether it was successful or not.

	bool imagecreatefrombmp(string filename)
		- is set only if it doesn't already exist (for forwards compatiblity.)
		- only supports uncompressed bitmaps.
		- returns an image identifier representing the bitmap image obtained
		  from the given filename.

	bool showCodeImage(string code)
		- show an image containing the visual verification code for registration.
		- requires the GD extension.
		- uses a random font for each letter from default_theme_dir/fonts.
		- outputs a gif or a png (depending on whether gif ix supported).
		- returns false if something goes wrong.

	bool showLetterImage(string letter)
		- show a letter for the visual verification code.
		- alternative function for showCodeImage() in case GD is missing.
		- includes an image from a random sub directory of
		  default_theme_dir/fonts.
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

	$id_folder = !empty($modSettings['currentAttachmentUploadDir']) ? $modSettings['currentAttachmentUploadDir'] : 1;
	$avatar_hash = empty($modSettings['custom_avatar_enabled']) ? getAttachmentFilename($destName, false, null, true) : '';
	$smcFunc['db_insert']('',
		'{db_prefix}attachments',
		array(
			'id_member' => 'int', 'attachment_type' => 'int', 'filename' => 'string-255', 'file_hash' => 'string-255', 'fileext' => 'string-8', 'size' => 'int',
			'id_folder' => 'int',
		),
		array(
			$memID, empty($modSettings['custom_avatar_enabled']) ? 0 : 1, $destName, $avatar_hash, $ext, 1,
			$id_folder,
		),
		array('id_attach')
	);
	$attachID = $smcFunc['db_insert_id']('{db_prefix}attachments', 'id_attach');
	// Retain this globally in case the script wants it.
	$modSettings['new_avatar_data'] = array(
		'id' => $attachID,
		'filename' => $destName,
		'type' => empty($modSettings['custom_avatar_enabled']) ? 0 : 1,
	);

	$destName = (empty($modSettings['custom_avatar_enabled']) ? (is_array($modSettings['attachmentUploadDir']) ? $modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']] : $modSettings['attachmentUploadDir']) : $modSettings['custom_avatar_dir']) . '/' . $destName . '.tmp';

	// Resize it.
	if (!empty($modSettings['avatar_download_png']))
		$success = resizeImageFile($url, $destName, $max_width, $max_height, 3);
	else
		$success = resizeImageFile($url, $destName, $max_width, $max_height);

	// Remove the .tmp extension.
	$destName = substr($destName, 0, -4);

	if ($success)
	{
		// Walk the right path.
		if (!empty($modSettings['currentAttachmentUploadDir']))
		{
			if (!is_array($modSettings['attachmentUploadDir']))
				$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);
			$path = $modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']];
		}
		else
			$path = $modSettings['attachmentUploadDir'];

		// Remove the .tmp extension from the attachment.
		if (rename($destName . '.tmp', empty($avatar_hash) ? $destName : $path . '/' . $attachID . '_' . $avatar_hash))
		{
			$destName = empty($avatar_hash) ? $destName : $path . '/' . $attachID . '_' . $avatar_hash;
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

function reencodeImage($fileName, $preferred_format = 0)
{
	// There is nothing we can do without GD, sorry!
	if (!checkGD())
		return false;

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
			// Paranoid check. Some like it that way.
			if (preg_match('~(iframe|\\<\\?|\\<%|html|eval|body|script\W|[CF]WS[\x01-\x0C])~i', $prev_chunk . $cur_chunk) === 1)
			{
				fclose($fp);
				return false;
			}
		}
		else
		{
			// Check for potential infection
			if (preg_match('~(iframe|html|eval|body|script\W|[CF]WS[\x01-\x0C])~i', $prev_chunk . $cur_chunk) === 1)
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

function resizeImageFile($source, $destination, $max_width, $max_height, $preferred_format = 0)
{
	global $sourcedir;

	// Nothing to do without GD
	if (!checkGD())
		return false;

	static $default_formats = array(
		'1' => 'gif',
		'2' => 'jpeg',
		'3' => 'png',
		'6' => 'bmp',
		'15' => 'wbmp'
	);

	require_once($sourcedir . '/Subs-Package.php');
	@ini_set('memory_limit', '90M');

	$success = false;

	// Get the image file, we have to work with something after all
	$fp_destination = fopen($destination, 'wb');
	if ($fp_destination && substr($source, 0, 7) == 'http://')
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

	// Gif? That might mean trouble if gif support is not available.
	if ($sizes[2] == 1 && !function_exists('imagecreatefromgif') && function_exists('imagecreatefrompng'))
	{
		// Download it to the temporary file... use the special gif library... and save as png.
		if ($img = @gif_loadFile($destination) && gif_outputAsPng($img, $destination))
			$sizes[2] = 3;
	}

	// A known and supported format?
	if (isset($default_formats[$sizes[2]]) && function_exists('imagecreatefrom' . $default_formats[$sizes[2]]))
	{
		$imagecreatefrom = 'imagecreatefrom' . $default_formats[$sizes[2]];
		if ($src_img = @$imagecreatefrom($destination))
		{
			resizeImage($src_img, $destination, imagesx($src_img), imagesy($src_img), $max_width === null ? imagesx($src_img) : $max_width, $max_height === null ? imagesy($src_img) : $max_height, true, $preferred_format);
			$success = true;
		}
	}

	return $success;
}

function resizeImage($src_img, $destName, $src_width, $src_height, $max_width, $max_height, $force_resize = false, $preferred_format = 0)
{
	global $gd2, $modSettings;

	// Without GD, no image resizing at all.
	if (!checkGD())
		return false;

	$success = false;

	// Determine whether to resize to max width or to max height (depending on the limits.)
	if (!empty($max_width) || !empty($max_height))
	{
		if (!empty($max_width) && (empty($max_height) || $src_height * $max_width / $src_width <= $max_height))
		{
			$dst_width = $max_width;
			$dst_height = floor($src_height * $max_width / $src_width);
		}
		elseif (!empty($max_height))
		{
			$dst_width = floor($src_width * $max_height / $src_height);
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
		$success = imagejpeg($dst_img, $destName);

	// Free the memory.
	imagedestroy($src_img);
	if ($dst_img != $src_img)
		imagedestroy($dst_img);

	return $success;
}

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
	function imagecreatefrombmp($filename)
	{
		global $gd2;

		$fp = fopen($filename, 'rb');

		$errors = error_reporting(0);

		$header = unpack('vtype/Vsize/Vreserved/Voffset', fread($fp, 14));
		$info = unpack('Vsize/Vwidth/Vheight/vplanes/vbits/Vcompression/Vimagesize/Vxres/Vyres/Vncolor/Vcolorimportant', fread($fp, 40));

		if ($header['type'] != 0x4D42)
			false;

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
			else
			{
				// Sorry, I'm just not going to do monochrome :P.
			}
		}

		fclose($fp);

		error_reporting($errors);

		return $dst_img;
	}
}

function gif_loadFile($lpszFileName, $iIndex = 0)
{
	// The classes needed are in this file.
	loadClassFile('Class-Graphics.php');
	$gif = new gif_file();

	if (!$gif->loadFile($lpszFileName, $iIndex))
		return false;

	return $gif;
}

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

// Create the image for the visual verification code.
function showCodeImage($code)
{
	global $settings, $user_info, $modSettings;

	/*
		Note: The higher the value of visual_verification_type the harder the verification is - from 0 as disabled through to 4 as "Very hard".
	*/

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

	// Is this GD2? Needed for pixel size.
	$testGD = get_extension_funcs('gd');
	$gd2 = in_array('imagecreatetruecolor', $testGD) && function_exists('imagecreatetruecolor');
	unset($testGD);

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
	while ($entry = $font_dir->read())
	{
		if (preg_match('~^(.+)\.gdf$~', $entry, $matches) === 1)
			$font_list[] = $entry;
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
		if (in_array('Screenge.ttf', $ttfont_list))
			$ttfont_list = array('Screenge.ttf');
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
	$total_width = $character_spacing * strlen($code) + 20;
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
		for ($i = 0; $i < rand(1, 5); $i++)
		{
			$x1 = rand(0, $total_width / 4);
			$x2 = $x1 + round(rand($total_width / 4, $total_width));
			$y1 = rand(0, $max_height);
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
					$y1 = 0; $y2 = $max_height;
				}
				else
				{
					$y1 = mt_rand(0, $max_height);
					$y2 = mt_rand(0, $max_height);
					$x1 = 0; $x2 = $total_width;
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
		header('Content-type: image/gif');
		imagegif($code_image);
	}
	else
	{
		header('Content-type: image/png');
		imagepng($code_image);
	}

	// Bail out.
	imagedestroy($code_image);
	die();
}

// Create a letter for the visual verification code.
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
	if (!file_exists($settings['default_theme_dir'] . '/fonts/' . $random_font . '/' . $letter . '.gif'))
		return false;

	// Include it!
	header('Content-type: image/gif');
	include($settings['default_theme_dir'] . '/fonts/' . $random_font . '/' . $letter . '.gif');

	// Nothing more to come.
	die();
}

?>