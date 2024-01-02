<?php

/**
 * Classes used for reading gif files (in case PHP's GD doesn't provide the
 * proper gif-functions).
 *
 * Gif Util copyright 2003 by Yamasoft (S/C). All rights reserved.
 * Do not remove this portion of the header, or use these functions except
 * from the original author. To get it, please navigate to:
 * http://www.yamasoft.com/php-gif.zip
 *
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

namespace SMF\Graphics\Gif;

use SMF\Config;

class File
{
	public $header;
	public $image;
	public $data;
	public $loaded;

	public function __construct()
	{
		$this->data = '';
		$this->loaded = false;
		$this->header = new FileHeader();
		$this->image = new Image();
	}

	public function loadFile(string $filename, int $iIndex): bool
	{
		if ($iIndex < 0) {
			return false;
		}

		$this->data = @file_get_contents($filename);

		if ($this->data === false) {
			return false;
		}

		// Tell the header to load up....
		$len = 0;

		if (!$this->header->load($this->data, $len)) {
			return false;
		}

		$this->data = substr($this->data, $len);

		// Keep reading (at least once) so we get to the actual image we're looking for.
		for ($j = 0; $j <= $iIndex; $j++) {
			$imgLen = 0;

			if (!$this->image->load($this->data, $imgLen)) {
				return false;
			}

			$this->data = substr($this->data, $imgLen);
		}

		$this->loaded = true;

		return true;
	}

	public function get_png_data(string $background_color): string|bool
	{
		if (!$this->loaded) {
			return false;
		}

		// Prepare the color table.
		if ($this->image->m_gih->m_bLocalClr) {
			$colors = $this->image->m_gih->m_nTableSize;
			$pal = $this->image->m_gih->m_colorTable->toString();

			if ($background_color != '-1') {
				$background_color = $this->image->m_gih->m_colorTable->colorIndex($background_color);
			}
		} elseif ($this->header->m_bGlobalClr) {
			$colors = $this->header->m_nTableSize;
			$pal = $this->header->m_colorTable->toString();

			if ($background_color != '-1') {
				$background_color = $this->header->m_colorTable->colorIndex($background_color);
			}
		} else {
			$colors = 0;
			$background_color = '-1';
		}

		if ($background_color == '-1') {
			$background_color = $this->header->m_nBgColor;
		}

		$data = &$this->image->m_data;
		$header = &$this->image->m_gih;

		$i = 0;
		$bmp = '';

		// Prepare the bitmap itself.
		for ($y = 0; $y < $this->header->m_nHeight; $y++) {
			$bmp .= "\x00";

			for ($x = 0; $x < $this->header->m_nWidth; $x++, $i++) {
				// Is this in the proper range?  If so, get the specific pixel data...
				if ($x >= $header->m_nLeft && $y >= $header->m_nTop && $x < ($header->m_nLeft + $header->m_nWidth) && $y < ($header->m_nTop + $header->m_nHeight)) {
					$bmp .= $data[$i];
				}
				// Otherwise, this is background...
				else {
					$bmp .= chr($background_color);
				}
			}
		}

		$bmp = gzcompress($bmp, 9);

		// Output the basic signature first of all.
		$out = "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A";

		// Now, we want the header...
		$out .= "\x00\x00\x00\x0D";
		$tmp = 'IHDR' . pack('N', (int) $this->header->m_nWidth) . pack('N', (int) $this->header->m_nHeight) . "\x08\x03\x00\x00\x00";
		$out .= $tmp . pack('N', smf_crc32($tmp));

		// The palette, assuming we have one to speak of...
		if ($colors > 0) {
			$out .= pack('N', (int) $colors * 3);
			$tmp = 'PLTE' . $pal;
			$out .= $tmp . pack('N', smf_crc32($tmp));
		}

		// Do we have any transparency we want to make available?
		if ($this->image->m_bTrans && $colors > 0) {
			$out .= pack('N', (int) $colors);
			$tmp = 'tRNS';

			// Stick each color on - full transparency or none.
			for ($i = 0; $i < $colors; $i++) {
				$tmp .= $i == $this->image->m_nTrans ? "\x00" : "\xFF";
			}

			$out .= $tmp . pack('N', smf_crc32($tmp));
		}

		// Here's the data itself!
		$out .= pack('N', strlen($bmp));
		$tmp = 'IDAT' . $bmp;
		$out .= $tmp . pack('N', smf_crc32($tmp));

		// EOF marker...
		$out .= "\x00\x00\x00\x00" . 'IEND' . "\xAE\x42\x60\x82";

		return $out;
	}
}

// 64-bit only functions?
if (!function_exists('smf_crc32')) {
	require_once Config::$sourcedir . '/Subs-Compat.php';
}

?>