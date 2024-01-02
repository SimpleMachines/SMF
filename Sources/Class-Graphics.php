<?php

/**
 * Backward compatibility file.
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

if (!defined('SMF')) {
	die('No direct access...');
}

class_alias('SMF\\Graphics\\Gif\\ColorTable', '\\gif_color_table');
class_alias('SMF\\Graphics\\Gif\\File', '\\gif_file');
class_alias('SMF\\Graphics\\Gif\\FileHeader', '\\gif_file_header');
class_alias('SMF\\Graphics\\Gif\\Image', '\\gif_image');
class_alias('SMF\\Graphics\\Gif\\ImageHeader', '\\gif_image_header');
class_alias('SMF\\Graphics\\Gif\\LzwCompression', '\\gif_lzw_compression');

?>