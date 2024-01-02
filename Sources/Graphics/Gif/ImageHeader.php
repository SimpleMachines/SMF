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

class ImageHeader
{
	public $m_nLeft;
	public $m_nTop;
	public $m_nWidth;
	public $m_nHeight;
	public $m_bLocalClr;
	public $m_bInterlace;
	public $m_bSorted;
	public $m_nTableSize;
	public $m_colorTable;

	public function __construct()
	{
		unset($this->m_nLeft, $this->m_nTop, $this->m_nWidth, $this->m_nHeight, $this->m_bLocalClr, $this->m_bInterlace, $this->m_bSorted, $this->m_nTableSize, $this->m_colorTable);
	}

	public function load(string $lpData, int &$hdrLen): bool
	{
		$hdrLen = 0;

		// Get the width/height/etc. from the header.
		list($this->m_nLeft, $this->m_nTop, $this->m_nWidth, $this->m_nHeight) = array_values(unpack('v4', substr($lpData, 0, 8)));

		if (!$this->m_nWidth || !$this->m_nHeight) {
			return false;
		}

		$b = ord($lpData[8]);
		$this->m_bLocalClr = ($b & 0x80) ? true : false;
		$this->m_bInterlace = ($b & 0x40) ? true : false;
		$this->m_bSorted = ($b & 0x20) ? true : false;
		$this->m_nTableSize = 2 << ($b & 0x07);
		$hdrLen = 9;

		if ($this->m_bLocalClr) {
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