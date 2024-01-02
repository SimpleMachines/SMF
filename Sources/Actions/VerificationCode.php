<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\Actions;

use SMF\BackwardCompatibility;
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Shows the verification code or let it be heard.
 *
 * TrueType fonts supplied by www.LarabieFonts.com.
 */
class VerificationCode implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'VerificationCode',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Identifier passed in 'vid' URL parameter.
	 */
	public string $verification_id;

	/**
	 * @var string
	 *
	 * The verification code.
	 */
	public string $code;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var object
	 *
	 * An instance of this class.
	 * This is used by the load() method to prevent mulitple instantiations.
	 */
	protected static object $obj;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Do the job.
	 */
	public function execute(): void
	{
		// Somehow no code was generated or the session was lost.
		if (empty($this->code)) {
			header('content-type: image/gif');

			die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
		}

		// Show a window that will play the verification code.
		if (isset($_REQUEST['sound'])) {
			Lang::load('Login');
			Theme::loadTemplate('Register');

			Utils::$context['verification_sound_href'] = Config::$scripturl . '?action=verificationcode;rand=' . bin2hex(random_bytes(16)) . ($this->verification_id ? ';vid=' . $this->verification_id : '') . ';format=.wav';
			Utils::$context['sub_template'] = 'verification_sound';
			Utils::$context['template_layers'] = [];

			Utils::obExit();
		}
		// If we have GD, try the nice code.
		elseif (empty($_REQUEST['format'])) {
			if (extension_loaded('gd') && !$this->showCodeImage($this->code)) {
				Utils::sendHttpStatus(400);
			}
			// Otherwise just show a pre-defined letter.
			elseif (isset($_REQUEST['letter'])) {
				$_REQUEST['letter'] = (int) $_REQUEST['letter'];

				if ($_REQUEST['letter'] > 0 && $_REQUEST['letter'] <= strlen($this->code) && !$this->showLetterImage(strtolower($this->code[$_REQUEST['letter'] - 1]))) {
					header('content-type: image/gif');

					die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
				}
			}
			// You must be up to no good.
			else {
				header('content-type: image/gif');

				die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
			}
		} elseif ($_REQUEST['format'] === '.wav') {
			if (!$this->createWaveFile($this->code)) {
				Utils::sendHttpStatus(400);
			}
		}

		// We all die one day...
		die();
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @return object An instance of this class.
	 */
	public static function load(): object
	{
		if (!isset(self::$obj)) {
			self::$obj = new self();
		}

		return self::$obj;
	}

	/**
	 * Convenience method to load() and execute() an instance of this class.
	 */
	public static function call(): void
	{
		self::load()->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		$this->verification_id = $_GET['vid'] ?? '';

		$this->code = $this->verification_id && isset($_SESSION[$this->verification_id . '_vv']) ? $_SESSION[$this->verification_id . '_vv']['code'] : ($_SESSION['visual_verification_code'] ?? '');
	}

	/**
	 * Show an image containing the visual verification code for registration.
	 *
	 * Requires the GD extension.
	 * Uses a random font for each letter from default_theme_dir/fonts.
	 * Outputs a gif or a png (depending on whether gif ix supported).
	 *
	 * @param string $code The code to display
	 * @return bool False if something goes wrong. Otherwise, dies.
	 */
	protected function showCodeImage($code): bool
	{
		if (!extension_loaded('gd')) {
			return false;
		}

		// Note: The higher the value of visual_verification_type the harder the verification is - from 0 as disabled through to 4 as "Very hard".

		// What type are we going to be doing?
		$image_type = Config::$modSettings['visual_verification_type'];

		// Special case to allow the admin center to show samples.
		if (User::$me->is_admin && isset($_GET['type'])) {
			$image_type = (int) $_GET['type'];
		}

		// Some quick references for what we do.
		// Do we show no, low or high noise?
		$noise_type = $image_type == 3 ? 'low' : ($image_type == 4 ? 'high' : ($image_type == 5 ? 'extreme' : 'none'));

		// Can we have more than one font in use?
		$vary_fonts = $image_type > 3 ? true : false;

		// Just a plain white background?
		$simple_bg_color = $image_type < 3 ? true : false;

		// Plain black foreground?
		$simple_fg_color = $image_type == 0 ? true : false;

		// High much to rotate each character.
		$rotation_type = $image_type == 1 ? 'none' : ($image_type > 3 ? 'low' : 'high');

		// Do we show some characters inversed?
		$show_reverse_chars = $image_type > 3 ? true : false;

		// Special case for not showing any characters.
		$disable_chars = $image_type == 0 ? true : false;

		// What do we do with the font colors. Are they one color, close to one color or random?
		$font_color_type = $image_type == 1 ? 'plain' : ($image_type > 3 ? 'random' : 'cyclic');

		// Are the fonts random sizes?
		$font_size_random = $image_type > 3 ? true : false;

		// How much space between characters?
		$font_horz_space = $image_type > 3 ? 'high' : ($image_type == 1 ? 'medium' : 'minus');

		// Where do characters sit on the image? (Fixed position or random/very random)
		$font_vert_pos = $image_type == 1 ? 'fixed' : ($image_type > 3 ? 'vrandom' : 'random');

		// Make font semi-transparent?
		$font_transparent = $image_type == 2 || $image_type == 3 ? true : false;

		// Give the image a border?
		$has_border = $simple_bg_color;

		// The amount of pixels inbetween characters.
		$character_spacing = 1;

		// What color is the background - generally white unless we're on "hard".
		if ($simple_bg_color) {
			$background_color = [255, 255, 255];
		} else {
			$background_color = Theme::$current->settings['verification_background'] ?? [236, 237, 243];
		}

		// The color of the characters shown (red, green, blue).
		if ($simple_fg_color) {
			$foreground_color = [0, 0, 0];
		} else {
			$foreground_color = [64, 101, 136];

			// Has the theme author requested a custom color?
			if (isset(Theme::$current->settings['verification_foreground'])) {
				$foreground_color = Theme::$current->settings['verification_foreground'];
			}
		}

		if (!is_dir(Theme::$current->settings['default_theme_dir'] . '/fonts')) {
			return false;
		}

		// Get a list of the available fonts.
		$font_list = [];
		$ttfont_list = [];
		$endian = unpack('v', pack('S', 0x00FF)) === 0x00FF;

		$font_dir = dir(Theme::$current->settings['default_theme_dir'] . '/fonts');

		while ($entry = $font_dir->read()) {
			if (preg_match('~^(.+)\.gdf$~', $entry, $matches) === 1) {
				if ($endian ^ (strpos($entry, '_end.gdf') === false)) {
					$font_list[] = $entry;
				}
			} elseif (preg_match('~^(.+)\.ttf$~', $entry, $matches) === 1) {
				$ttfont_list[] = $entry;
			}
		}

		if (empty($font_list)) {
			return false;
		}

		// For non-hard things don't even change fonts.
		if (!$vary_fonts) {
			$font_list = [$font_list[0]];

			if (in_array('AnonymousPro.ttf', $ttfont_list)) {
				$ttfont_list = ['AnonymousPro.ttf'];
			} else {
				$ttfont_list = empty($ttfont_list) ? [] : [$ttfont_list[0]];
			}
		}

		// Create a list of characters to be shown.
		$characters = [];
		$loaded_fonts = [];

		for ($i = 0; $i < strlen($code); $i++) {
			$characters[$i] = [
				'id' => $code[$i],
				'font' => array_rand($font_list),
			];

			$loaded_fonts[$characters[$i]['font']] = null;
		}

		// Load all fonts and determine the maximum font height.
		foreach ($loaded_fonts as $font_index => $dummy) {
			$loaded_fonts[$font_index] = imageloadfont(Theme::$current->settings['default_theme_dir'] . '/fonts/' . $font_list[$font_index]);
		}

		// Determine the dimensions of each character.
		$extra = $image_type == 4 || $image_type == 5 ? 80 : 45;

		$total_width = $character_spacing * strlen($code) + $extra;
		$max_height = 0;

		foreach ($characters as $char_index => $character) {
			$characters[$char_index]['width'] = imagefontwidth($loaded_fonts[$character['font']]);
			$characters[$char_index]['height'] = imagefontheight($loaded_fonts[$character['font']]);

			$max_height = max($characters[$char_index]['height'] + 5, $max_height);
			$total_width += $characters[$char_index]['width'];
		}

		// Create an image.
		$code_image = imagecreatetruecolor($total_width, $max_height);

		// Draw the background.
		$bg_color = imagecolorallocate(
			$code_image,
			$background_color[0],
			$background_color[1],
			$background_color[2],
		);

		imagefilledrectangle(
			$code_image,
			0,
			0,
			$total_width - 1,
			$max_height - 1,
			$bg_color,
		);

		// Randomize the foreground color a little.
		for ($i = 0; $i < 3; $i++) {
			$foreground_color[$i] = random_int(max($foreground_color[$i] - 3, 0), min($foreground_color[$i] + 3, 255));
		}

		$fg_color = imagecolorallocate(
			$code_image,
			$foreground_color[0],
			$foreground_color[1],
			$foreground_color[2],
		);

		// Color for the dots.
		for ($i = 0; $i < 3; $i++) {
			if ($background_color[$i] < $foreground_color[$i]) {
				$dotbgcolor[$i] = random_int(0, max($foreground_color[$i] - 20, 0));
			} else {
				$dotbgcolor[$i] = random_int(min($foreground_color[$i] + 20, 255), 255);
			}
		}

		$randomness_color = imagecolorallocate(
			$code_image,
			$dotbgcolor[0],
			$dotbgcolor[1],
			$dotbgcolor[2],
		);

		// Some squares/rectangles for new extreme level
		if ($noise_type == 'extreme') {
			for ($i = 0; $i < random_int(1, 5); $i++) {
				$x1 = random_int(0, $total_width / 4);
				$x2 = $x1 + round(rand($total_width / 4, $total_width));
				$y1 = random_int(0, $max_height);
				$y2 = $y1 + round(rand(0, $max_height / 3));

				imagefilledrectangle(
					$code_image,
					$x1,
					$y1,
					$x2,
					$y2,
					random_int(0, 1) ? $fg_color : $randomness_color,
				);
			}
		}

		// Fill in the characters.
		if (!$disable_chars) {
			$cur_x = 0;

			foreach ($characters as $char_index => $character) {
				// Can we use true type fonts?
				$can_do_ttf = function_exists('imagettftext');

				// How much rotation will we give?
				if ($rotation_type == 'none') {
					$angle = 0;
				} else {
					$angle = random_int(-100, 100) / ($rotation_type == 'high' ? 6 : 10);
				}

				// What color shall we do it?
				if ($font_color_type == 'cyclic') {
					// Here we'll pick from a set of acceptance types.
					$colors = [
						[10, 120, 95],
						[46, 81, 29],
						[4, 22, 154],
						[131, 9, 130],
						[0, 0, 0],
						[143, 39, 31],
					];

					if (!isset($last_index)) {
						$last_index = -1;
					}

					$new_index = $last_index;

					while ($last_index == $new_index) {
						$new_index = random_int(0, count($colors) - 1);
					}

					$char_fg_color = $colors[$new_index];
					$last_index = $new_index;
				} elseif ($font_color_type == 'random') {
					$char_fg_color = [
						random_int(max($foreground_color[0] - 2, 0), $foreground_color[0]),
						random_int(max($foreground_color[1] - 2, 0), $foreground_color[1]),
						random_int(max($foreground_color[2] - 2, 0), $foreground_color[2]),
					];
				} else {
					$char_fg_color = [
						$foreground_color[0],
						$foreground_color[1],
						$foreground_color[2],
					];
				}

				if (!empty($can_do_ttf)) {
					$font_size = $font_size_random ? random_int(17, 19) : 18;

					// Work out the sizes - also fix the character width cause TTF not quite so wide!
					$font_x = $font_horz_space == 'minus' && $cur_x > 0 ? $cur_x - 3 : $cur_x + 5;
					$font_y = $max_height - ($font_vert_pos == 'vrandom' ? random_int(2, 8) : ($font_vert_pos == 'random' ? random_int(3, 5) : 5));

					// What font face?
					if (!empty($ttfont_list)) {
						$fontface = Theme::$current->settings['default_theme_dir'] . '/fonts/' . $ttfont_list[random_int(0, count($ttfont_list) - 1)];
					}

					// What color are we to do it in?
					$is_reverse = $show_reverse_chars ? random_int(0, 1) : false;

					if (function_exists('imagecolorallocatealpha') && $font_transparent) {
						$char_color = imagecolorallocatealpha(
							$code_image,
							$char_fg_color[0],
							$char_fg_color[1],
							$char_fg_color[2],
							50,
						);
					} else {
						$char_color = imagecolorallocate(
							$code_image,
							$char_fg_color[0],
							$char_fg_color[1],
							$char_fg_color[2],
						);
					}

					$fontcord = @imagettftext(
						$code_image,
						$font_size,
						$angle,
						$font_x,
						$font_y,
						$char_color,
						$fontface,
						$character['id'],
					);

					if (empty($fontcord)) {
						$can_do_ttf = false;
					} elseif ($is_reverse) {
						imagefilledpolygon($code_image, $fontcord, $fg_color);

						// Put the character back!
						imagettftext(
							$code_image,
							$font_size,
							$angle,
							$font_x,
							$font_y,
							$randomness_color,
							$fontface,
							$character['id'],
						);
					}

					if ($can_do_ttf) {
						$cur_x = max($fontcord[2], $fontcord[4]) + ($angle == 0 ? 0 : 3);
					}
				}

				if (!$can_do_ttf) {
					// Rotating the characters a little...
					if (function_exists('imagerotate')) {
						$char_image = imagecreatetruecolor(
							$character['width'],
							$character['height'],
						);

						$char_bgcolor = imagecolorallocate(
							$char_image,
							$background_color[0],
							$background_color[1],
							$background_color[2],
						);

						imagefilledrectangle(
							$char_image,
							0,
							0,
							$character['width'] - 1,
							$character['height'] - 1,
							$char_bgcolor,
						);

						imagechar(
							$char_image,
							$loaded_fonts[$character['font']],
							0,
							0,
							$character['id'],
							imagecolorallocate(
								$char_image,
								$char_fg_color[0],
								$char_fg_color[1],
								$char_fg_color[2],
							),
						);

						$rotated_char = imagerotate(
							$char_image,
							random_int(-100, 100) / 10,
							$char_bgcolor,
						);

						imagecopy(
							$code_image,
							$rotated_char,
							$cur_x,
							0,
							0,
							0,
							$character['width'],
							$character['height'],
						);
					}

					// Sorry, no rotation available.
					else {
						imagechar(
							$code_image,
							$loaded_fonts[$character['font']],
							$cur_x,
							floor(($max_height - $character['height']) / 2),
							$character['id'],
							imagecolorallocate(
								$code_image,
								$char_fg_color[0],
								$char_fg_color[1],
								$char_fg_color[2],
							),
						);
					}

					$cur_x += $character['width'] + $character_spacing;
				}
			}
		}
		// If disabled just show a cross.
		else {
			imageline($code_image, 0, 0, $total_width, $max_height, $fg_color);
			imageline($code_image, 0, $max_height, $total_width, 0, $fg_color);
		}

		// Make the background color transparent on the hard image.
		if (!$simple_bg_color) {
			imagecolortransparent($code_image, $bg_color);
		}

		if ($has_border) {
			imagerectangle($code_image, 0, 0, $total_width - 1, $max_height - 1, $fg_color);
		}

		// Add some noise to the background?
		if ($noise_type != 'none') {
			for ($i = random_int(0, 2); $i < $max_height; $i += random_int(1, 2)) {
				for ($j = random_int(0, 10); $j < $total_width; $j += random_int(1, 10)) {
					imagesetpixel($code_image, $j, $i, random_int(0, 1) ? $fg_color : $randomness_color);
				}
			}

			// Put in some lines too?
			if ($noise_type != 'extreme') {
				$num_lines = $noise_type == 'high' ? random_int(3, 7) : random_int(2, 5);

				for ($i = 0; $i < $num_lines; $i++) {
					if (random_int(0, 1)) {
						$x1 = random_int(0, $total_width);
						$x2 = random_int(0, $total_width);
						$y1 = 0;
						$y2 = $max_height;
					} else {
						$y1 = random_int(0, $max_height);
						$y2 = random_int(0, $max_height);
						$x1 = 0;
						$x2 = $total_width;
					}

					imagesetthickness($code_image, random_int(1, 2));

					imageline(
						$code_image,
						$x1,
						$y1,
						$x2,
						$y2,
						random_int(0, 1) ? $fg_color : $randomness_color,
					);
				}
			} else {
				// Put in some ellipse
				$num_ellipse = $noise_type == 'extreme' ? random_int(6, 12) : random_int(2, 6);

				for ($i = 0; $i < $num_ellipse; $i++) {
					$x1 = round(rand(($total_width / 4) * -1, $total_width + ($total_width / 4)));
					$x2 = round(rand($total_width / 2, 2 * $total_width));
					$y1 = round(rand(($max_height / 4) * -1, $max_height + ($max_height / 4)));
					$y2 = round(rand($max_height / 2, 2 * $max_height));

					imageellipse(
						$code_image,
						$x1,
						$y1,
						$x2,
						$y2,
						random_int(0, 1) ? $fg_color : $randomness_color,
					);
				}
			}
		}

		// Show the image.
		if (function_exists('imagegif')) {
			header('content-type: image/gif');
			imagegif($code_image);
		} else {
			header('content-type: image/png');
			imagepng($code_image);
		}

		// Bail out.
		die();
	}

	/**
	 * Show a letter for the visual verification code.
	 *
	 * Alternative function for showCodeImage() in case GD is missing.
	 * Includes an image from a random sub directory of default_theme_dir/fonts.
	 *
	 * @param string $letter A letter to show as an image
	 * @return bool False if something went wrong. Otherwise, dies.
	 */
	protected function showLetterImage($letter): bool
	{
		if (!is_dir(Theme::$current->settings['default_theme_dir'] . '/fonts')) {
			return false;
		}

		// Get a list of the available font directories.
		$font_dir = dir(Theme::$current->settings['default_theme_dir'] . '/fonts');
		$font_list = [];

		while ($entry = $font_dir->read()) {
			if (
				$entry[0] !== '.'
				&& is_dir(Theme::$current->settings['default_theme_dir'] . '/fonts/' . $entry)
				&& file_exists(Theme::$current->settings['default_theme_dir'] . '/fonts/' . $entry . '.gdf')
			) {
				$font_list[] = $entry;
			}
		}

		if (empty($font_list)) {
			return false;
		}

		// Pick a random font.
		$random_font = $font_list[array_rand($font_list)];

		// Check if the given letter exists.
		if (!file_exists(Theme::$current->settings['default_theme_dir'] . '/fonts/' . $random_font . '/' . strtoupper($letter) . '.png')) {
			return false;
		}

		// Include it!
		header('content-type: image/png');

		include Theme::$current->settings['default_theme_dir'] . '/fonts/' . $random_font . '/' . strtoupper($letter) . '.png';

		// Nothing more to come.
		die();
	}

	/**
	 * Creates a wave file that spells the letters of $word.
	 * Tries the user's language first, and defaults to english.
	 * Used by VerificationCode() (Register.php).
	 *
	 * @param string $word
	 * @return bool false on failure
	 */
	protected function createWaveFile($word)
	{
		// Allow max 2 requests per 20 seconds.
		if (($ip = CacheApi::get('wave_file/' . User::$me->ip, 20)) > 2 || ($ip2 = CacheApi::get('wave_file/' . User::$me->ip2, 20)) > 2) {
			Utils::sendHttpStatus(400);

			die();
		}

		CacheApi::put('wave_file/' . User::$me->ip, $ip ? $ip + 1 : 1, 20);
		CacheApi::put('wave_file/' . User::$me->ip2, $ip2 ? $ip2 + 1 : 1, 20);

		// Fixate randomization for this word.
		$tmp = unpack('n', md5($word . session_id()));
		mt_srand(end($tmp));

		// Try to see if there's a sound font in the user's language.
		if (file_exists(Theme::$current->settings['default_theme_dir'] . '/fonts/sound/a.' . User::$me->language . '.wav')) {
			$sound_language = User::$me->language;
		}
		// English should be there.
		elseif (file_exists(Theme::$current->settings['default_theme_dir'] . '/fonts/sound/a.english.wav')) {
			$sound_language = 'english';
		}
		// Guess not...
		else {
			return false;
		}

		// File names are in lower case so lets make sure that we are only using a lower case string
		$word = Utils::strtolower($word);

		$chars = preg_split('/(.)/su', $word, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		// Loop through all letters of the word $word.
		$sound_word = '';

		for ($i = 0; $i < count($chars); $i++) {
			$sound_letter = implode('', file(Theme::$current->settings['default_theme_dir'] . '/fonts/sound/' . $chars[$i] . '.' . $sound_language . '.wav'));

			if (strpos($sound_letter, 'data') === false) {
				return false;
			}

			$sound_letter = substr($sound_letter, strpos($sound_letter, 'data') + 8);

			switch ($chars[$i] === 's' ? 0 : mt_rand(0, 2)) {
				case 0:
					for ($j = 0, $n = strlen($sound_letter); $j < $n; $j++) {
						for ($k = 0, $m = round(mt_rand(15, 25) / 10); $k < $m; $k++) {
							$sound_word .= $chars[$i] === 's' ? $sound_letter[$j] : chr(mt_rand(max(ord($sound_letter[$j]) - 1, 0x00), min(ord($sound_letter[$j]) + 1, 0xFF)));
						}
					}
					break;

				case 1:
					for ($j = 0, $n = strlen($sound_letter) - 1; $j < $n; $j += 2) {
						$sound_word .= (mt_rand(0, 3) == 0 ? '' : $sound_letter[$j]) . (mt_rand(0, 3) === 0 ? $sound_letter[$j + 1] : $sound_letter[$j]) . (mt_rand(0, 3) === 0 ? $sound_letter[$j] : $sound_letter[$j + 1]) . $sound_letter[$j + 1] . (mt_rand(0, 3) == 0 ? $sound_letter[$j + 1] : '');
					}
					$sound_word .= str_repeat($sound_letter[$n], 2);
					break;

				case 2:
					$shift = 0;

					for ($j = 0, $n = strlen($sound_letter); $j < $n; $j++) {
						if (mt_rand(0, 10) === 0) {
							$shift += mt_rand(-3, 3);
						}

						for ($k = 0, $m = round(mt_rand(15, 25) / 10); $k < $m; $k++) {
							$sound_word .= chr(min(max(ord($sound_letter[$j]) + $shift, 0x00), 0xFF));
						}
					}
					break;
			}

			$sound_word .= str_repeat(chr(0x80), mt_rand(10000, 10500));
		}

		$data_size = strlen($sound_word);
		$file_size = $data_size + 0x24;
		$content_length = $file_size + 0x08;
		$sample_rate = 16000;

		// Disable compression.
		ob_end_clean();
		header_remove('content-encoding');
		header('content-encoding: none');
		header('accept-ranges: bytes');
		header('connection: close');
		header('cache-control: no-cache');

		// Output the wav.
		header('content-type: audio/x-wav');
		header('expires: ' . gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT');

		if (isset($_SERVER['HTTP_RANGE'])) {
			list($a, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
			list($range) = explode(',', $range, 2);
			list($range, $range_end) = explode('-', $range);
			$range = intval($range);
			$range_end = !$range_end ? $content_length - 1 : intval($range_end);
			$new_length = $range_end - $range + 1;

			Utils::sendHttpStatus(206);
			header("content-length: {$new_length}");
			header("content-range: bytes {$range}-{$range_end}/{$content_length}");
		} else {
			header('content-length: ' . $content_length);
		}

		echo pack('nnVnnnnnnnnVVnnnnV', 0x5249, 0x4646, $file_size, 0x5741, 0x5645, 0x666D, 0x7420, 0x1000, 0x0000, 0x0100, 0x0100, $sample_rate, $sample_rate, 0x0100, 0x0800, 0x6461, 0x7461, $data_size), $sound_word;

		// Nothing more to add.
		die();
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\VerificationCode::exportStatic')) {
	VerificationCode::exportStatic();
}

?>