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

/*	Gif Util copyright 2003 by Yamasoft (S/C). All rights reserved.
	Do not remove this portion of the header, or use these functions except
	from the original author. To get it, please navigate to:
	http://www.yamasoft.com/php-gif.zip
*/

if (!defined('SMF'))
	die('Hacking attempt...');

/*	Classes used for reading gif files (in case PHP's GD doesn't provide the
	proper gif-functions).
*/

class gif_lzw_compression
{
	public $MAX_LZW_BITS;
	public $Fresh, $CodeSize, $SetCodeSize, $MaxCode, $MaxCodeSize, $FirstCode, $OldCode;
	public $ClearCode, $EndCode, $Next, $Vals, $Stack, $sp, $Buf, $CurBit, $LastBit, $Done, $LastByte;

	public function __construct()
	{
		$this->MAX_LZW_BITS = 12;
		unset($this->Next, $this->Vals, $this->Stack, $this->Buf);

		$this->Next  = range(0, (1 << $this->MAX_LZW_BITS)       - 1);
		$this->Vals  = range(0, (1 << $this->MAX_LZW_BITS)       - 1);
		$this->Stack = range(0, (1 << ($this->MAX_LZW_BITS + 1)) - 1);
		$this->Buf   = range(0, 279);
	}

	public function decompress($data, &$datLen)
	{
		$stLen  = strlen($data);
		$datLen = 0;
		$ret    = '';

		$this->LZWCommand($data, true);

		while (($iIndex = $this->LZWCommand($data, false)) >= 0)
			$ret .= chr($iIndex);

		$datLen = $stLen - strlen($data);

		if ($iIndex != -2)
			return false;

		return $ret;
	}

	public function LZWCommand(&$data, $bInit)
	{
		if ($bInit)
		{
			$this->SetCodeSize = ord($data[0]);
			$data = substr($data, 1);

			$this->CodeSize    = $this->SetCodeSize + 1;
			$this->ClearCode   = 1 << $this->SetCodeSize;
			$this->EndCode     = $this->ClearCode + 1;
			$this->MaxCode     = $this->ClearCode + 2;
			$this->MaxCodeSize = $this->ClearCode << 1;

			$this->GetCode($data, $bInit);

			$this->Fresh = 1;
			for ($i = 0; $i < $this->ClearCode; $i++)
			{
				$this->Next[$i] = 0;
				$this->Vals[$i] = $i;
			}

			for (; $i < (1 << $this->MAX_LZW_BITS); $i++)
			{
				$this->Next[$i] = 0;
				$this->Vals[$i] = 0;
			}

			$this->sp = 0;
			return 1;
		}

		if ($this->Fresh)
		{
			$this->Fresh = 0;
			do
			{
				$this->FirstCode = $this->GetCode($data, $bInit);
				$this->OldCode   = $this->FirstCode;
			}
			while ($this->FirstCode == $this->ClearCode);

			return $this->FirstCode;
		}

		if ($this->sp > 0)
		{
			$this->sp--;
			return $this->Stack[$this->sp];
		}

		while (($Code = $this->GetCode($data, $bInit)) >= 0)
		{
			if ($Code == $this->ClearCode)
			{
				for ($i = 0; $i < $this->ClearCode; $i++)
				{
					$this->Next[$i] = 0;
					$this->Vals[$i] = $i;
				}

				for (; $i < (1 << $this->MAX_LZW_BITS); $i++)
				{
					$this->Next[$i] = 0;
					$this->Vals[$i] = 0;
				}

				$this->CodeSize    = $this->SetCodeSize + 1;
				$this->MaxCodeSize = $this->ClearCode << 1;
				$this->MaxCode     = $this->ClearCode + 2;
				$this->sp          = 0;
				$this->FirstCode   = $this->GetCode($data, $bInit);
				$this->OldCode     = $this->FirstCode;

				return $this->FirstCode;
			}

			if ($Code == $this->EndCode)
				return -2;

			$InCode = $Code;
			if ($Code >= $this->MaxCode)
			{
				$this->Stack[$this->sp] = $this->FirstCode;
				$this->sp++;
				$Code = $this->OldCode;
			}

			while ($Code >= $this->ClearCode)
			{
				$this->Stack[$this->sp] = $this->Vals[$Code];
				$this->sp++;

				if ($Code == $this->Next[$Code]) // Circular table entry, big GIF Error!
					return -1;

				$Code = $this->Next[$Code];
			}

			$this->FirstCode = $this->Vals[$Code];
			$this->Stack[$this->sp] = $this->FirstCode;
			$this->sp++;

			if (($Code = $this->MaxCode) < (1 << $this->MAX_LZW_BITS))
			{
				$this->Next[$Code] = $this->OldCode;
				$this->Vals[$Code] = $this->FirstCode;
				$this->MaxCode++;

				if (($this->MaxCode >= $this->MaxCodeSize) && ($this->MaxCodeSize < (1 << $this->MAX_LZW_BITS)))
				{
					$this->MaxCodeSize *= 2;
					$this->CodeSize++;
				}
			}

			$this->OldCode = $InCode;
			if ($this->sp > 0)
			{
				$this->sp--;
				return $this->Stack[$this->sp];
			}
		}

		return $Code;
	}

	public function GetCode(&$data, $bInit)
	{
		if ($bInit)
		{
			$this->CurBit   = 0;
			$this->LastBit  = 0;
			$this->Done     = 0;
			$this->LastByte = 2;

			return 1;
		}

		if (($this->CurBit + $this->CodeSize) >= $this->LastBit)
		{
			if ($this->Done)
			{
				// Ran off the end of my bits...
				if ($this->CurBit >= $this->LastBit)
					return 0;

				return -1;
			}

			$this->Buf[0] = $this->Buf[$this->LastByte - 2];
			$this->Buf[1] = $this->Buf[$this->LastByte - 1];

			$count = ord($data[0]);
			$data  = substr($data, 1);

			if ($count)
			{
				for ($i = 0; $i < $count; $i++)
					$this->Buf[2 + $i] = ord($data{$i});

				$data = substr($data, $count);
			}
			else
				$this->Done = 1;

			$this->LastByte = 2 + $count;
			$this->CurBit = ($this->CurBit - $this->LastBit) + 16;
			$this->LastBit  = (2 + $count) << 3;
		}

		$iRet = 0;
		for ($i = $this->CurBit, $j = 0; $j < $this->CodeSize; $i++, $j++)
			$iRet |= (($this->Buf[intval($i / 8)] & (1 << ($i % 8))) != 0) << $j;

		$this->CurBit += $this->CodeSize;
		return $iRet;
	}
}

class gif_color_table
{
	public $m_nColors;
	public $m_arColors;

	public function __construct()
	{
		unset($this->m_nColors, $this->m_arColors);
	}

	public function load($lpData, $num)
	{
		$this->m_nColors  = 0;
		$this->m_arColors = array();

		for ($i = 0; $i < $num; $i++)
		{
			$rgb = substr($lpData, $i * 3, 3);
			if (strlen($rgb) < 3)
				return false;

			$this->m_arColors[] = (ord($rgb[2]) << 16) + (ord($rgb[1]) << 8) + ord($rgb[0]);
			$this->m_nColors++;
		}

		return true;
	}

	public function toString()
	{
		$ret = '';

		for ($i = 0; $i < $this->m_nColors; $i++)
		{
			$ret .=
				chr(($this->m_arColors[$i] & 0x000000FF))       . // R
				chr(($this->m_arColors[$i] & 0x0000FF00) >>  8) . // G
				chr(($this->m_arColors[$i] & 0x00FF0000) >> 16);  // B
		}

		return $ret;
	}

	public function colorIndex($rgb)
	{
		$rgb  = intval($rgb) & 0xFFFFFF;
		$r1   = ($rgb & 0x0000FF);
		$g1   = ($rgb & 0x00FF00) >>  8;
		$b1   = ($rgb & 0xFF0000) >> 16;
		$idx  = -1;

		for ($i = 0; $i < $this->m_nColors; $i++)
		{
			$r2 = ($this->m_arColors[$i] & 0x000000FF);
			$g2 = ($this->m_arColors[$i] & 0x0000FF00) >>  8;
			$b2 = ($this->m_arColors[$i] & 0x00FF0000) >> 16;
			$d  = abs($r2 - $r1) + abs($g2 - $g1) + abs($b2 - $b1);

			if (($idx == -1) || ($d < $dif))
			{
				$idx = $i;
				$dif = $d;
			}
		}

		return $idx;
	}
}

class gif_file_header
{
	public $m_lpVer, $m_nWidth, $m_nHeight, $m_bGlobalClr, $m_nColorRes;
	public $m_bSorted, $m_nTableSize, $m_nBgColor, $m_nPixelRatio;
	public $m_colorTable;

	public function __construct()
	{
		unset($this->m_lpVer, $this->m_nWidth, $this->m_nHeight, $this->m_bGlobalClr, $this->m_nColorRes);
		unset($this->m_bSorted, $this->m_nTableSize, $this->m_nBgColor, $this->m_nPixelRatio, $this->m_colorTable);
	}

	public function load($lpData, &$hdrLen)
	{
		$hdrLen = 0;

		$this->m_lpVer = substr($lpData, 0, 6);
		if (($this->m_lpVer != 'GIF87a') && ($this->m_lpVer != 'GIF89a'))
			return false;

		list ($this->m_nWidth, $this->m_nHeight) = array_values(unpack('v2', substr($lpData, 6, 4)));

		if (!$this->m_nWidth || !$this->m_nHeight)
			return false;

		$b = ord(substr($lpData, 10, 1));
		$this->m_bGlobalClr  = ($b & 0x80) ? true : false;
		$this->m_nColorRes   = ($b & 0x70) >> 4;
		$this->m_bSorted     = ($b & 0x08) ? true : false;
		$this->m_nTableSize  = 2 << ($b & 0x07);
		$this->m_nBgColor    = ord(substr($lpData, 11, 1));
		$this->m_nPixelRatio = ord(substr($lpData, 12, 1));
		$hdrLen = 13;

		if ($this->m_bGlobalClr)
		{
			$this->m_colorTable = new gif_color_table();
			if (!$this->m_colorTable->load(substr($lpData, $hdrLen), $this->m_nTableSize))
				return false;

			$hdrLen += 3 * $this->m_nTableSize;
		}

		return true;
	}
}

class gif_image_header
{
	public $m_nLeft, $m_nTop, $m_nWidth, $m_nHeight, $m_bLocalClr;
	public $m_bInterlace, $m_bSorted, $m_nTableSize, $m_colorTable;

	public function __construct()
	{
		unset($this->m_nLeft, $this->m_nTop, $this->m_nWidth, $this->m_nHeight, $this->m_bLocalClr);
		unset($this->m_bInterlace, $this->m_bSorted, $this->m_nTableSize, $this->m_colorTable);
	}

	public function load($lpData, &$hdrLen)
	{
		$hdrLen = 0;

		// Get the width/height/etc. from the header.
		list ($this->m_nLeft, $this->m_nTop, $this->m_nWidth, $this->m_nHeight) = array_values(unpack('v4', substr($lpData, 0, 8)));

		if (!$this->m_nWidth || !$this->m_nHeight)
			return false;

		$b = ord($lpData[8]);
		$this->m_bLocalClr  = ($b & 0x80) ? true : false;
		$this->m_bInterlace = ($b & 0x40) ? true : false;
		$this->m_bSorted    = ($b & 0x20) ? true : false;
		$this->m_nTableSize = 2 << ($b & 0x07);
		$hdrLen = 9;

		if ($this->m_bLocalClr)
		{
			$this->m_colorTable = new gif_color_table();
			if (!$this->m_colorTable->load(substr($lpData, $hdrLen), $this->m_nTableSize))
				return false;

			$hdrLen += 3 * $this->m_nTableSize;
		}

		return true;
	}
}

class gif_image
{
	public $m_disp, $m_bUser, $m_bTrans, $m_nDelay, $m_nTrans, $m_lpComm;
	public $m_gih, $m_data, $m_lzw;

	public function __construct()
	{
		unset($this->m_disp, $this->m_bUser, $this->m_nDelay, $this->m_nTrans, $this->m_lpComm, $this->m_data);
		$this->m_gih = new gif_image_header();
		$this->m_lzw = new gif_lzw_compression();
	}

	public function load($data, &$datLen)
	{
		$datLen = 0;

		while (true)
		{
			$b = ord($data[0]);
			$data = substr($data, 1);
			$datLen++;

			switch ($b)
			{
			// Extension...
			case 0x21:
				$len = 0;
				if (!$this->skipExt($data, $len))
					return false;

				$datLen += $len;
				break;

			// Image...
			case 0x2C:
				// Load the header and color table.
				$len = 0;
				if (!$this->m_gih->load($data, $len))
					return false;

				$data = substr($data, $len);
				$datLen += $len;

				// Decompress the data, and ride on home ;).
				$len = 0;
				if (!($this->m_data = $this->m_lzw->decompress($data, $len)))
					return false;

				$data = substr($data, $len);
				$datLen += $len;

				if ($this->m_gih->m_bInterlace)
					$this->deInterlace();

				return true;

			case 0x3B: // EOF
			default:
				return false;
			}
		}
		return false;
	}

	public function skipExt(&$data, &$extLen)
	{
		$extLen = 0;

		$b = ord($data[0]);
		$data = substr($data, 1);
		$extLen++;

		switch ($b)
		{
		// Graphic Control...
		case 0xF9:
			$b = ord($data[1]);
			$this->m_disp   = ($b & 0x1C) >> 2;
			$this->m_bUser  = ($b & 0x02) ? true : false;
			$this->m_bTrans = ($b & 0x01) ? true : false;
			list ($this->m_nDelay) = array_values(unpack('v', substr($data, 2, 2)));
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
		while ($b > 0)
		{
			$data = substr($data, $b);
			$extLen += $b;
			$b    = ord($data[0]);
			$data = substr($data, 1);
			$extLen++;
		}
		return true;
	}

	public function deInterlace()
	{
		$data = $this->m_data;

		for ($i = 0; $i < 4; $i++)
		{
			switch ($i)
			{
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

			for (; $y < $this->m_gih->m_nHeight; $y += $s)
			{
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

class gif_file
{
	public $header, $image, $data, $loaded;

	public function __construct()
	{
		$this->data = '';
		$this->loaded = false;
		$this->header = new gif_file_header();
		$this->image = new gif_image();
	}

	public function loadFile($filename, $iIndex)
	{
		if ($iIndex < 0)
			return false;

		$this->data = @file_get_contents($filename);
		if ($this->data === false)
			return false;

		// Tell the header to load up....
		$len = 0;
		if (!$this->header->load($this->data, $len))
			return false;

		$this->data = substr($this->data, $len);

		// Keep reading (at least once) so we get to the actual image we're looking for.
		for ($j = 0; $j <= $iIndex; $j++)
		{
			$imgLen = 0;
			if (!$this->image->load($this->data, $imgLen))
				return false;

			$this->data = substr($this->data, $imgLen);
		}

		$this->loaded = true;
		return true;
	}

	public function get_png_data($background_color)
	{
		if (!$this->loaded)
			return false;

		// Prepare the color table.
		if ($this->image->m_gih->m_bLocalClr)
		{
			$colors = $this->image->m_gih->m_nTableSize;
			$pal = $this->image->m_gih->m_colorTable->toString();

			if ($background_color != -1)
				$background_color = $this->image->m_gih->m_colorTable->colorIndex($background_color);
		}
		elseif ($this->header->m_bGlobalClr)
		{
			$colors = $this->header->m_nTableSize;
			$pal = $this->header->m_colorTable->toString();

			if ($background_color != -1)
				$background_color = $this->header->m_colorTable->colorIndex($background_color);
		}
		else
		{
			$colors = 0;
			$background_color = -1;
		}

		if ($background_color == -1)
			$background_color = $this->header->m_nBgColor;

		$data = &$this->image->m_data;
		$header = &$this->image->m_gih;

		$i = 0;
		$bmp = '';

		// Prepare the bitmap itself.
		for ($y = 0; $y < $this->header->m_nHeight; $y++)
		{
			$bmp .= "\x00";

			for ($x = 0; $x < $this->header->m_nWidth; $x++, $i++)
			{
				// Is this in the proper range?  If so, get the specific pixel data...
				if ($x >= $header->m_nLeft && $y >= $header->m_nTop && $x < ($header->m_nLeft + $header->m_nWidth) && $y < ($header->m_nTop + $header->m_nHeight))
					$bmp .= $data{$i};
				// Otherwise, this is background...
				else
					$bmp .= chr($background_color);
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
		if ($colors > 0)
		{
			$out .= pack('N', (int) $colors * 3);
			$tmp = 'PLTE' . $pal;
			$out .= $tmp . pack('N', smf_crc32($tmp));
		}

		// Do we have any transparency we want to make available?
		if ($this->image->m_bTrans && $colors > 0)
		{
			$out .= pack('N', (int) $colors);
			$tmp = 'tRNS';

			// Stick each color on - full transparency or none.
			for ($i = 0; $i < $colors; $i++)
				$tmp .= $i == $this->image->m_nTrans ? "\x00" : "\xFF";

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

// crc32 doesn't work as expected on 64-bit functions - make our own.
// http://www.php.net/crc32#79567
if (!function_exists('smf_crc32'))
{
	function smf_crc32($number)
	{
		$crc = crc32($number);

		if ($crc & 0x80000000)
		{
			$crc ^= 0xffffffff;
			$crc += 1;
			$crc = -$crc;
		}

		return $crc;
	}
}

?>