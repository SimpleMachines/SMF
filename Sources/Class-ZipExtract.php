<?php
/**
 * Zip extraction class.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 1
 * @author see contributors.txt
 */

/**
 * Note: this file was authored entirely by Peter Spicer,
 * who chose to make it available under the 3-clause BSD license.
 */

class ZipExtract
{
	public $zipname = '';
	public $zip_fd = 0; // The file descriptor for file operations on the current zip file
	private $_content = false;

	public function __construct($zipname)
	{
		if (!is_callable('gzopen'))
			throw new ZipExtract_NoGzip_Exception();

		$this->zipname = $zipname;
		$this->zip_fd = 0;
	}

	public function __destruct()
	{
		$this->closeFile();
	}

	/**
	 * Lists the contents of the current ZIP archive.
	 *
	 * @param bool $refresh Once invoked, this function caches the zip file's contents locally. Use this to force it to be updated.
	 * @return array An array of entries from the current ZIP, key/value pair where the key is the index in the listing and the value is an array with the following keys:
	 * - flag - some general flags attached to this compression
	 * - compression - compression method indicator, see the specification
	 * - mtime - packed 16 bit file's time
	 * - mdate - packed 16 bit file's date
	 * - crc - file's CRC-32
	 * - compressed_size - the file's size while compressed
	 * - uncompressed_size - the file's original size
	 * - internal - a value indicated internal file attributes (see specification)
	 * - external - a value indicated external file attributes (see specification)
	 * - offset - offset to find more data for this file
	 * - is_folder - boolean as to whether the file entry indicates a folder or not
	 */
	public function list_contents($refresh = false)
	{
		if (!empty($this->_content) && !$refresh)
			return $this->_content;

		$this->openFile();
		$ecd = $this->_getEndCentralDir();
		if (@fseek($this->zip_fd, $ecd['starting_offset']))
			throw new ZipExtract_InvalidSize_Exception(); // Remember, fseek returns non-zero for error. Bizarrely.

		$this->_content = array();
		for ($i = 0; $i < $ecd['entries']; $i++)
			$this->_content[$i] = $this->_getNextCentralDirEntry();

		$this->closeFile();
		return $this->_content;
	}

	/**
	 * Extracts files that match the name(s) given.
	 *
	 * For more details on matching, see {@link _findFiles()}.
	 *
	 * @param mixed $files Either a string or an array of strings representing a filename (or names) to be extracted.
	 * @param bool $regex Whether the filenames are actually regular expressions to match. Either way, will be performed against the base filename not the entire path.
	 * @return array Filenames that have been extracted and where extracted to. See {@link extractByIndex()} for more details.
	 */
	public function extractByName($files_to_match, $regex = false)
	{
		return $this->extractByIndex($this->_findFiles($files_to_match, $regex));
	}

	/**
	 * Extracts files with the supplied indexes.
	 *
	 * @param array $indexes An array of indexes to extract from the ZIP file.
	 * @return array Files that have been extracted, primarily using the same content as list_contents(), but with an additional field:
	 * - content - This will contain the actual content of the uncompressed file.
	 */
	public function extractByIndex($indexes)
	{
		if (empty($indexes))
			return array();

		$new_indexes = array();
		// We need the list of what's in the zip file.
		if (empty($this->_content))
			$this->list_contents();

		$this->openFile();
		foreach ($this->_content as $id => $entry)
		{
			if ($entry['is_folder'] || !in_array($id, $indexes))
				continue;

			// Can we unpack this? First of all, check for either no compression or standard compression.
			if ($entry['compression'] != 8 && $entry['compression'] != 0)
				throw new ZipExtract_UnsupportedCompress_Exception($entry['filename']);
			// Is it encrypted?
			if ($entry['flag'] & 1 == 1)
				throw new ZipExtract_Encrypted_Exception($entry['filename']);

			// OK, let's go get this beast. Let's set the file pointer first.
			// Remember fseek is ass-backward and returns 0 for success and -1 for failure.
			if (@fseek($this->zip_fd, $entry['offset']))
				throw new ZipExtract_InvalidZip_Exception($entry['filename']);
			$this->_getNextFileEntry();

			$new_indexes[$id] = $entry;
			$new_indexes[$id]['content'] = $entry['compressed_size'] > 0 ? fread($this->zip_fd, $entry['compressed_size']) : '';
			if ($entry['compression'] == 8)
				$new_indexes[$id]['content'] = gzinflate($new_indexes[$id]['content']);
			if (strlen($new_indexes[$id]['content']) != $entry['uncompressed_size'])
				throw new ZipExtract_InvalidExtraction_Exception($entry['filename']);
		}
		$this->closeFile();

		return $new_indexes;
	}

	/**
	 * Helper function to find files in the zip and return their indexes for later use, based on filename and index.
	 *
	 * Matching is done against the filename unit rather than the entire path, e.g. requesting filename.txt to be extracted will match /filename.txt and path/filename.txt but not path/filename2.txt
	 *
	 * @param mixed $files Either a string or an array of strings representing a filename (or names) to be extracted.
	 * @param bool $regex Whether the filenames are actually regular expressions to match. Either way, will be performed against the base filename not the entire path.
	 * @return array A list of indexes within the zip file that have been matched.
	 */
	private function _findFiles($files_to_match, $regex)
	{
		if (empty($files_to_match))
			return array();
		elseif (!is_array($files_to_match))
			$files_to_match = (array) $files_to_match;

		// Now trim the list we're dealing with
		foreach ($files_to_match as $k => $v)
			$files_to_match[$k] = trim($v);

		// Then, get the list in this zip file.
		if (empty($this->_content))
			$this->list_contents();
		// Still empty? If so, crap.
		if (empty($this->_content))
			throw new ZipExtract_InvalidZip_Exception();

		$indexes = array();
		foreach ($this->_content as $index => $zipped_file)
		{
			if ($zipped_file['is_folder'])
				continue;
			if (strpos($zipped_file['filename'], '/') !== false)
				$zipped_file['filename'] = substr(strrchr($zipped_file['filename'], '/'), 1);

			$zipped_file['filename'] = trim($zipped_file['filename']);
			// So, did we match the file?
			if ($regex)
			{
				foreach ($files_to_match as $file)
					if (preg_match($zipped_file['filename'], $file))
					{
						$indexes[] = $index;
						break;
					}
			}
			else
			{
				foreach ($files_to_match as $file)
					if (strcasecmp($zipped_file['filename'], $file))
					{
						$indexes[] = $index;
						break;
					}
			}
		}
		return $indexes;
	}

	/**
	 * Opens the file handle associated with this zip file.
	 *
	 * @param bool $writable Whether the file is being opened for writing or not. Defaults to read only.
	 */
	public function openFile()
	{
		if ($this->zip_fd != 0 || !($this->zip_fd = @fopen($this->zipname, 'rb')))
			throw new ZipExtract_UnableRead_Exception($this->zipname);
	}

	/**
	 * Closes the file handle associated with this zip file.
	 */
	public function closeFile()
	{
		if (!empty($this->zip_fd))
			@fclose($this->zip_fd);
		$this->zip_fd = 0;
	}

	/**
	 * Read the end of the central directory of the ZIP archive.
	 *
	 * More details in 4.3.16 of http://www.pkware.com/documents/casestudies/APPNOTE.TXT
	 *
	 * Also note that the header information for multi-volume archives are completely ignored.
	 *
	 * @return array An array containing the following keys:
	 * - entries - the number of entries in the ZIP file
	 * - size - size of the files in the central directory
	 * - starting_offset - the starting point of the central directory
	 * - comment - the ZIP file comment, if present
	 */
	private function _getEndCentralDir()
	{
		// Try to seek to file end, since that's where it is. Fail with exception if not.
		$file_size = filesize($this->zipname);
		@fseek($this->zip_fd, $file_size);
		if (@ftell($this->zip_fd) != $file_size)
			throw new ZipExtract_UnableRead_Exception($this->zipname);

		// The file needs to be at least 26 bytes. If not, there's a huge problem.
		if ($file_size < 26)
			throw new ZipExtract_InvalidZip_Exception();

		// Now, most files don't have a comment, so let's look for the ending where it would be if there were no comment.
		@fseek($this->zip_fd, $file_size - 22);
		$ecd_pos = @ftell($this->zip_fd);
		if ($ecd_pos != $file_size - 22)
			throw new ZipExtract_UnableRead_Exception($this->zipname); // in case for some reason we seeked but did not find

		$block = @fread($this->zip_fd, 4);
		$data = @unpack('Vid', $block);

		// So we read the last 22 bytes. Hopefully the ZIP ident is the first 4.
		if ($data['id'] != 0x06054b50)
		{
			// It wasn't. Now we have to search through byte by byte to find the ZIP signature.
			$new_read_size = min($file_size, 65537); // The largest possible is 65535 bytes of comment plus 22 bytes of header.
			@fseek($this->zip_fd, $file_size - $new_read_size);
			$ecd_pos = @ftell($this->zip_fd);
			if ($ecd_pos != $file_size - $new_read_size)
				throw new ZipExtract_UnableRead_Exception($this->zipname);

			// But even if we do so byte by byte, we shouldn't be *reading* it byte by byte.
			$buffer = '';
			while (!feof($this->zip_fd))
				$buffer .= @fread($this->zip, 8192);
			// Try to find it.
			$pos = strrpos($buffer, "\x06\x05\x4b\x50");
			if ($pos === false)
				throw new ZipExtract_InvalidZip_Exception();
			// Goody, we found it, clean up and get everything ready.
			$ecd_pos += $pos + 4; // Remember, we need the point *after* the 4 byte header.
			if ($ecd_pos > $file_size - 22)
				throw new ZipExtract_InvalidZip_Exception();
			@fseek($this->zip_fd, $ecd_pos);
			$post_comment_header = substr($buffer, $pos);
		}

		// At this point, the file pointer should be in the right position for where the header is, whether we had to go on an expedition or not.
		$header = fread($this->zip_fd, 18);
		if (strlen($header) != 18)
			throw new ZipExtract_InvalidRecord_Exception(strlen($header));

		// Now extract the data out of the ZIP header.
		$data = unpack('vdisk_num/vdisk_start/vdisk_entries/ventries/Vsize/Voffset/vcomment_len', $header);
		$data['comment'] = $data['comment_len'] == 0 ? '' : substr($buffer, 0, $data['comment_len']); // If we did go on an expedition, we would have the full comment.

		return array(
			'entries' => $data['entries'],
			'size' => $data['size'],
			'starting_offset' => $data['offset'],
			'comment' => $data['comment'],
		);
	}

	/**
	 * Read the next file entry from the central directory, using the current file's current position.
	 *
	 * More details in 4.3.12 of http://www.pkware.com/documents/casestudies/APPNOTE.TXT
	 *
	 * Returns an array with the following keys:
	 * - flag - some general flags attached to this compression
	 * - compression - compression method indicator, see the specification
	 * - crc - file's CRC-32
	 * - compressed_size - the file's size while compressed
	 * - uncompressed_size - the file's original size
	 * - internal - a value indicated internal file attributes (see specification)
	 * - external - a value indicated external file attributes (see specification)
	 * - filename - the filename
	 * - timestamp - the last-modified time of this file, in Unix timestamp format
	 * - offset - offset to find more data for this file
	 * - is_folder - boolean as to whether the file entry indicates a folder or not
	 */
	private function _getNextCentralDirEntry()
	{
		// Start by reading the first 46 bytes, which should be our local header code and some stuff.
		$block = @fread($this->zip_fd, 46);
		if (strlen($block) != 46)
			throw new ZipExtract_InvalidBlock_Exception();
		$data = unpack('Vid/vversion/vversion_extracted/vflag/vcompression/vmtime/vmdate/Vcrc/Vcompressed_size/Vuncompressed_size/vfilename_len/vextra_len/vcomment_len/vdisk/vinternal/Vexternal/Voffset', $block);
		if ($data['id'] != 0x02014b50)
			throw new ZipExtract_InvalidZip_Exception();

		// Then there are some extra bits we can get.
		foreach (array('filename', 'extra', 'comment') as $part)
			$data[$part] = $data[$part . '_len'] == 0 ? '' : fread($this->zip_fd, $data[$part . '_len']);

		$data['timestamp'] = !empty($data['mdate']) ? $this->_convert_dos_to_timestamp($data['mdate'], $data['mtime']) : time();

		// If it's a folder, override the external value with a known value for folders.
		if (substr($data['filename'], -1) == '/')
			$data['external'] = 0x00000010;

		$data['is_folder'] = ($data['external'] & 0x00000010) == 0x00000010;

		// Things we don't need to send back up the chain: block signature, version numbers (we don't need them)
		// the original date format (we're not going to use it now), string lengths, disk number (we aren't doing multi-volume archives)
		unset($data['id'], $data['version'], $data['version_extracted'], $data['mtime'], $data['mdate'], $data['filename_len'], $data['extra_len'], $data['comment_len'], $data['disk']);
		return $data;
	}

	/**
	 * Get the next (local, not central) file entry.
	 *
	 * More details in 4.3.7 of http://www.pkware.com/documents/casestudies/APPNOTE.TXT
	 *
	 * Returns an array with the following keys:
	 * - flag - some general flags attached to this compression
	 * - compression - compression method indicator, see the specification
	 * - crc - file's CRC-32
	 * - compressed_size - the file's size while compressed
	 * - uncompressed_size - the file's original size
	 * - filename - the filename
	 * - timestamp - the last-modified time of this file, in Unix timestamp format
	 */
	private function _getNextFileEntry()
	{
		// Start by reading the first 30 bytes, which should be our local header code and some stuff.
		$block = @fread($this->zip_fd, 30);
		if (strlen($block) != 30)
			throw new ZipExtract_InvalidRecord_Exception();
		$data = unpack('Vid/vversion/vflag/vcompression/vmtime/vmdate/Vcrc/Vcompressed_size/Vuncompressed_size/vfilename_len/vextra_len', $block);
		if ($data['id'] != 0x04034b50)
			throw new ZipExtract_InvalidZip_Exception();

		// Just a couple of extra fields to scoop up.
		foreach (array('filename', 'extra') as $part)
			$data[$part] = $data[$part . '_len'] == 0 ? '' : fread($this->zip_fd, $data[$part . '_len']);

		$data['filename'] = ZipExtract::reduce_path($data['filename']);
		$data['timestamp'] = !empty($data['mdate']) ? $this->_convert_dos_to_timestamp($data['mdate'], $data['mtime']) : time();

		// And some clean-up: don't need the id, version-required-to-extract, original time/date, string lengths
		unset($data['id'], $data['version'], $data['mtime'], $data['mdate'], $data['filename_len'], $data['extra_len']);
		return $data;
	}

	/**
	 * Attempts to create a temporary file for working purposes.
	 *
	 * The location of the temporary file can be set by using temp_dir with setOptions.
	 *
	 * @param bool $use_gzip Whether to open the file as a gzip context or a conventional file handle.
	 * @return resource The temporary file's handle.
	 */
	private function _createTempFile($use_gzip = false)
	{
		$temp_name = $this->_opts['temp_dir'] . uniqid('ZipExtract-') . '.tmp';
		$temp_fd = $use_gzip ? @gzopen($temp_name, 'wb') : @fopen($temp_name, 'wb');
		if (empty($temp_fd))
			throw new ZipExtract_UnableWrite_Exception($temp_name);

		return $temp_fd;
	}

	/**
	 * Convert ZIP file time format to Unix timestamp.
	 *
	 * ZIP files use the same format as MS-DOS originally did, packing the date into two two-byte pairs.
	 * - Date: bits 9-16 = years since 1980, bits 5-8 = month, bits 0-4 = day
	 * - Time: bits 11-15 = hour, bits 5-10 = minute, bits 0-4 = number of even seconds (need to multiply by two)
	 *
	 * @param int $mdate A two-byte packed integer containing the date from the ZIP file
	 * @param int $mtime A two-byte packed integer containing the time from the ZIP file
	 * @return int The timestamp that is referred to by the ZIP file's time.
	 */
	private function _convert_dos_to_timestamp($mdate, $mtime)
	{
		return mktime(($mtime & 0xF800) >> 11, ($mtime & 0x07E0) >> 5, ($mtime & 0x001F) * 2, ($mdate & 0x01E0) >> 5, $mdate & 0x001F, (($mdate & 0xFE00) >> 9) + 1980);
	}

	/**
	 * Reduce a directory to its base form.
	 *
	 * Primarily designed to reduce a folder structure, flattening instances of ./ and ../
	 *
	 * @param string $dir A path e.g. from the ZIP file.
	 * @return string The path reduced without any . or .. entries.
	 */
	public static function reduce_path($dir)
	{
		if (empty($dir))
			return '';

		$dir_list = explode('/', $dir);
		$dir_count = count($dir_list);
		$reduced_path = array();
		foreach ($dir_list as $component)
		{
			if (empty($component))
			{
				$red = count($reduced_path);
				if ($red == 0 || $red == $dir_count)
					$reduced_path[] = '';
			}
			elseif ($component == '.')
				continue;
			elseif ($component == '..')
			{
				$red = count($reduced_path);
				if ($red > 0 && !empty($reduced_path[$red-1]))
					array_pop($reduced_path);
			}
			else
				$reduced_path[] = $component;
		}
		return implode('/', $reduced_path);
	}
}

/*
 * These are used primarily for delineating the type of error that occurred, so the calling context
 * can figure out how much error handling it wants to do.
 * This also removes any need for ZipExtract to care about translations or error messages and such like.
 */
class ZipExtract_NoGzip_Exception extends Exception {}
class ZipExtract_UnableRead_Exception extends Exception {}
class ZipExtract_InvalidExtraction_Exception extends Exception {}
class ZipExtract_InvalidSize_Exception extends Exception {}
class ZipExtract_InvalidZip_Exception extends Exception {}
class ZipExtract_InvalidBlock_Exception extends Exception {}
class ZipExtract_InvalidRecord_Exception extends Exception {}
class ZipExtract_Encrypted_Exception extends Exception {}
class ZipExtract_UnsupportedCompress_Exception extends Exception {}
