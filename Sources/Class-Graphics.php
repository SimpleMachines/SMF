<?php

/**
 * Backward compatibility file.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

if (!defined('SMF')) {
	die('No direct access...');
}

class_alias(\SMF\Graphics\Gif\ColorTable::class, '\\gif_color_table');
class_alias(\SMF\Graphics\Gif\File::class, '\\gif_file');
class_alias(\SMF\Graphics\Gif\FileHeader::class, '\\gif_file_header');
class_alias(\SMF\Graphics\Gif\Image::class, '\\gif_image');
class_alias(\SMF\Graphics\Gif\ImageHeader::class, '\\gif_image_header');
class_alias(\SMF\Graphics\Gif\LzwCompression::class, '\\gif_lzw_compression');
