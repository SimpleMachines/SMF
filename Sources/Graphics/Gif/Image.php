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

class Image
{
	public $m_disp;
	public $m_bUser;
	public $m_bTrans;
	public $m_nDelay;
	public $m_nTrans;
	public $m_lpComm;
	public $m_gih;
	public $m_data;
	public $m_lzw;

	public function __construct()
	{
		unset($this->m_disp, $this->m_bUser, $this->m_nDelay, $this->m_nTrans, $this->m_lpComm, $this->m_data);
		$this->m_gih = new ImageHeader();
		$this->m_lzw = new LzwCompression();
	}

	public function load(string $data, int &$datLen): bool
	{
		$datLen = 0;

		while (true) {
			$b = ord($data[0]);
			$data = substr($data, 1);
			$datLen++;

			switch ($b) {
				// Extension...
				case 0x21:
					$len = 0;

					if (!$this->skipExt($data, $len)) {
						return false;
					}

					$datLen += $len;

					break;

				// Image...
				case 0x2C:
					// Load the header and color table.
					$len = 0;

					if (!$this->m_gih->load($data, $len)) {
						return false;
					}

					$data = substr($data, $len);
					$datLen += $len;

					// Decompress the data, and ride on home ;).
					$len = 0;

					if (!($this->m_data = $this->m_lzw->decompress($data, $len))) {
						return false;
					}

					$datLen += $len;

					if ($this->m_gih->m_bInterlace) {
						$this->deInterlace();
					}

					return true;

				case 0x3B: // EOF
				default:
					return false;
			}
		}
	}

	public function skipExt(string &$data, int &$extLen): bool
	{
		$extLen = 0;

		$b = ord($data[0]);
		$data = substr($data, 1);
		$extLen++;

		switch ($b) {
			// Graphic Control...
			case 0xF9:
				$b = ord($data[1]);
				$this->m_disp = ($b & 0x1C) >> 2;
				$this->m_bUser = ($b & 0x02) ? true : false;
				$this->m_bTrans = ($b & 0x01) ? true : false;
				list($this->m_nDelay) = array_values(unpack('v', substr($data, 2, 2)));
				$this->m_nTrans = ord($data[4]);
				break;

			// Comment...
			case 0xFE:
				$this->m_lpComm = substr($data, 1, ord($data[0]));
				break;

			// Plain text...
			case 0x01:
				break;

			// Application...
			case 0xFF:
				break;
		}

		// Skip default as defs may change.
		$b = ord($data[0]);
		$data = substr($data, 1);
		$extLen++;

		while ($b > 0) {
			$data = substr($data, $b);
			$extLen += $b;
			$b = ord($data[0]);
			$data = substr($data, 1);
			$extLen++;
		}

		return true;
	}

	public function deInterlace(): void
	{
		$data = $this->m_data;

		for ($i = 0; $i < 4; $i++) {
			switch ($i) {
				case 0:
					$s = 8;
					$y = 0;
					break;

				case 1:
					$s = 8;
					$y = 4;
					break;

				case 2:
					$s = 4;
					$y = 2;
					break;

				case 3:
					$s = 2;
					$y = 1;
					break;
			}

			for (; $y < $this->m_gih->m_nHeight; $y += $s) {
				$lne = substr($this->m_data, 0, $this->m_gih->m_nWidth);
				$this->m_data = substr($this->m_data, $this->m_gih->m_nWidth);

				$data =
					substr($data, 0, $y * $this->m_gih->m_nWidth) .
					$lne .
					substr($data, ($y + 1) * $this->m_gih->m_nWidth);
			}
		}

		$this->m_data = $data;
	}
}

?>