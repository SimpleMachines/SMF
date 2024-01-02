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

class ColorTable
{
	public $m_nColors;
	public $m_arColors;

	public function __construct()
	{
		unset($this->m_nColors, $this->m_arColors);
	}

	public function load(string $lpData, int $num): bool
	{
		$this->m_nColors = 0;
		$this->m_arColors = [];

		for ($i = 0; $i < $num; $i++) {
			$rgb = substr($lpData, $i * 3, 3);

			if (strlen($rgb) < 3) {
				return false;
			}

			$this->m_arColors[] = (ord($rgb[2]) << 16) + (ord($rgb[1]) << 8) + ord($rgb[0]);
			$this->m_nColors++;
		}

		return true;
	}

	public function toString(): string
	{
		$ret = '';

		for ($i = 0; $i < $this->m_nColors; $i++) {
			$ret .=
				chr(($this->m_arColors[$i] & 0x000000FF)) . // R
				chr(($this->m_arColors[$i] & 0x0000FF00) >> 8) . // G
				chr(($this->m_arColors[$i] & 0x00FF0000) >> 16);  // B
		}

		return $ret;
	}

	public function colorIndex(string $rgb): int
	{
		$dif = 0;
		$rgb = intval($rgb) & 0xFFFFFF;
		$r1 = ($rgb & 0x0000FF);
		$g1 = ($rgb & 0x00FF00) >> 8;
		$b1 = ($rgb & 0xFF0000) >> 16;
		$idx = -1;

		for ($i = 0; $i < $this->m_nColors; $i++) {
			$r2 = ($this->m_arColors[$i] & 0x000000FF);
			$g2 = ($this->m_arColors[$i] & 0x0000FF00) >> 8;
			$b2 = ($this->m_arColors[$i] & 0x00FF0000) >> 16;
			$d = abs($r2 - $r1) + abs($g2 - $g1) + abs($b2 - $b1);

			if (($idx == -1) || ($d < $dif)) {
				$idx = $i;
				$dif = $d;
			}
		}

		return $idx;
	}
}

?>