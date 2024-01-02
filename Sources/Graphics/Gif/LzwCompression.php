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

/**
 * Class LzwCompression
 *
 * An implementation of the LZW compression algorithm
 */
class LzwCompression
{
	public $MAX_LZW_BITS;
	public $Fresh;
	public $CodeSize;
	public $SetCodeSize;
	public $MaxCode;
	public $MaxCodeSize;
	public $FirstCode;
	public $OldCode;
	public $ClearCode;
	public $EndCode;
	public $Next;
	public $Vals;
	public $Stack;
	public $sp;
	public $Buf;
	public $CurBit;
	public $LastBit;
	public $Done;
	public $LastByte;

	public function __construct()
	{
		$this->MAX_LZW_BITS = 12;
		unset($this->Next, $this->Vals, $this->Stack, $this->Buf);

		$this->Next = range(0, (1 << $this->MAX_LZW_BITS) - 1);
		$this->Vals = range(0, (1 << $this->MAX_LZW_BITS) - 1);
		$this->Stack = range(0, (1 << ($this->MAX_LZW_BITS + 1)) - 1);
		$this->Buf = range(0, 279);
	}

	public function decompress(string $data, int &$datLen): string|bool
	{
		$stLen = strlen($data);
		$datLen = 0;
		$ret = '';

		$this->LZWCommand($data, true);

		while (($iIndex = $this->LZWCommand($data, false)) >= 0) {
			$ret .= chr($iIndex);
		}

		$datLen = $stLen - strlen($data);

		if ($iIndex != -2) {
			return false;
		}

		return $ret;
	}

	public function LZWCommand(string &$data, int|bool $bInit): int
	{
		if ($bInit) {
			$this->SetCodeSize = ord($data[0]);
			$data = substr($data, 1);

			$this->CodeSize = $this->SetCodeSize + 1;
			$this->ClearCode = 1 << $this->SetCodeSize;
			$this->EndCode = $this->ClearCode + 1;
			$this->MaxCode = $this->ClearCode + 2;
			$this->MaxCodeSize = $this->ClearCode << 1;

			$this->GetCode($data, $bInit);

			$this->Fresh = 1;

			for ($i = 0; $i < $this->ClearCode; $i++) {
				$this->Next[$i] = 0;
				$this->Vals[$i] = $i;
			}

			for (; $i < (1 << $this->MAX_LZW_BITS); $i++) {
				$this->Next[$i] = 0;
				$this->Vals[$i] = 0;
			}

			$this->sp = 0;

			return 1;
		}

		if ($this->Fresh) {
			$this->Fresh = 0;

			do {
				$this->FirstCode = $this->GetCode($data, $bInit);
				$this->OldCode = $this->FirstCode;
			} while ($this->FirstCode == $this->ClearCode);

			return $this->FirstCode;
		}

		if ($this->sp > 0) {
			$this->sp--;

			return $this->Stack[$this->sp];
		}

		while (($Code = $this->GetCode($data, $bInit)) >= 0) {
			if ($Code == $this->ClearCode) {
				for ($i = 0; $i < $this->ClearCode; $i++) {
					$this->Next[$i] = 0;
					$this->Vals[$i] = $i;
				}

				for (; $i < (1 << $this->MAX_LZW_BITS); $i++) {
					$this->Next[$i] = 0;
					$this->Vals[$i] = 0;
				}

				$this->CodeSize = $this->SetCodeSize + 1;
				$this->MaxCodeSize = $this->ClearCode << 1;
				$this->MaxCode = $this->ClearCode + 2;
				$this->sp = 0;
				$this->FirstCode = $this->GetCode($data, $bInit);
				$this->OldCode = $this->FirstCode;

				return $this->FirstCode;
			}

			if ($Code == $this->EndCode) {
				return -2;
			}

			$InCode = $Code;

			if ($Code >= $this->MaxCode) {
				$this->Stack[$this->sp] = $this->FirstCode;
				$this->sp++;
				$Code = $this->OldCode;
			}

			while ($Code >= $this->ClearCode) {
				$this->Stack[$this->sp] = $this->Vals[$Code];
				$this->sp++;

				if ($Code == $this->Next[$Code]) { // Circular table entry, big GIF Error!
					return -1;
				}

				$Code = $this->Next[$Code];
			}

			$this->FirstCode = $this->Vals[$Code];
			$this->Stack[$this->sp] = $this->FirstCode;
			$this->sp++;

			if (($Code = $this->MaxCode) < (1 << $this->MAX_LZW_BITS)) {
				$this->Next[$Code] = $this->OldCode;
				$this->Vals[$Code] = $this->FirstCode;
				$this->MaxCode++;

				if (($this->MaxCode >= $this->MaxCodeSize) && ($this->MaxCodeSize < (1 << $this->MAX_LZW_BITS))) {
					$this->MaxCodeSize *= 2;
					$this->CodeSize++;
				}
			}

			$this->OldCode = $InCode;

			if ($this->sp > 0) {
				$this->sp--;

				return $this->Stack[$this->sp];
			}
		}

		return $Code;
	}

	public function GetCode(string &$data, int $bInit): int
	{
		if ($bInit) {
			$this->CurBit = 0;
			$this->LastBit = 0;
			$this->Done = 0;
			$this->LastByte = 2;

			return 1;
		}

		if (($this->CurBit + $this->CodeSize) >= $this->LastBit) {
			if ($this->Done) {
				// Ran off the end of my bits...
				if ($this->CurBit >= $this->LastBit) {
					return 0;
				}

				return -1;
			}

			$this->Buf[0] = $this->Buf[$this->LastByte - 2];
			$this->Buf[1] = $this->Buf[$this->LastByte - 1];

			$count = ord($data[0]);
			$data = substr($data, 1);

			if ($count) {
				for ($i = 0; $i < $count; $i++) {
					$this->Buf[2 + $i] = ord($data[$i]);
				}

				$data = substr($data, $count);
			} else {
				$this->Done = 1;
			}

			$this->LastByte = 2 + $count;
			$this->CurBit = ($this->CurBit - $this->LastBit) + 16;
			$this->LastBit = (2 + $count) << 3;
		}

		$iRet = 0;

		for ($i = $this->CurBit, $j = 0; $j < $this->CodeSize; $i++, $j++) {
			$iRet |= (($this->Buf[intval($i / 8)] & (1 << ($i % 8))) != 0) << $j;
		}

		$this->CurBit += $this->CodeSize;

		return $iRet;
	}
}

?>