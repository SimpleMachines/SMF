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

class FileHeader
{
	public $m_lpVer;
	public $m_nWidth;
	public $m_nHeight;
	public $m_bGlobalClr;
	public $m_nColorRes;
	public $m_bSorted;
	public $m_nTableSize;
	public $m_nBgColor;
	public $m_nPixelRatio;
	public $m_colorTable;

	public function __construct()
	{
		unset($this->m_lpVer, $this->m_nWidth, $this->m_nHeight, $this->m_bGlobalClr, $this->m_nColorRes, $this->m_bSorted, $this->m_nTableSize, $this->m_nBgColor, $this->m_nPixelRatio, $this->m_colorTable);
	}

	public function load(string $lpData, int &$hdrLen): bool
	{
		$hdrLen = 0;

		$this->m_lpVer = substr($lpData, 0, 6);

		if (($this->m_lpVer != 'GIF87a') && ($this->m_lpVer != 'GIF89a')) {
			return false;
		}

		list($this->m_nWidth, $this->m_nHeight) = array_values(unpack('v2', substr($lpData, 6, 4)));

		if (!$this->m_nWidth || !$this->m_nHeight) {
			return false;
		}

		$b = ord(substr($lpData, 10, 1));
		$this->m_bGlobalClr = ($b & 0x80) ? true : false;
		$this->m_nColorRes = ($b & 0x70) >> 4;
		$this->m_bSorted = ($b & 0x08) ? true : false;
		$this->m_nTableSize = 2 << ($b & 0x07);
		$this->m_nBgColor = ord(substr($lpData, 11, 1));
		$this->m_nPixelRatio = ord(substr($lpData, 12, 1));
		$hdrLen = 13;

		if ($this->m_bGlobalClr) {
			$this->m_colorTable = new ColorTable();

			if (!$this->m_colorTable->load(substr($lpData, $hdrLen), $this->m_nTableSize)) {
				return false;
			}

			$hdrLen += 3 * $this->m_nTableSize;
		}

		return true;
	}
}

?>