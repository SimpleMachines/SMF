<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\BBCode;

use SMF\Attachment;
use SMF\Config;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Utils;

/**
 * Represents the attach BBCode.
 */
class Attach extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'attach';

	/**
	 *
	 */
	public ?string $type = BBCode::TYPE_UNPARSED_CONTENT;

	/**
	 *
	 */
	public ?array $parameters = [
		'id' => ['match' => '(\d+)'],
		'alt' => ['optional' => true],
		'width' => ['optional' => true, 'match' => '(\d+)'],
		'height' => ['optional' => true, 'match' => '(\d+)'],
		'display' => ['optional' => true, 'match' => '(link|embed)'],
	];

	/**
	 *
	 */
	public ?string $content = '$1';

	/**
	 *
	 */
	public ?string $disabled_content = '$1';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function validate(BBCodeInterface &$bbc, array|string &$data, array $disabled, array $params): void
	{
		$return_context = '';

		// BBC or the entire attachments feature is disabled
		if (empty(Config::$modSettings['attachmentEnable']) || !empty($disabled['attach'])) {
			return;
		}

		// Save the attach ID.
		$attach_id = $params['{id}'];

		$current_attachment = Attachment::parseAttachBBC($attach_id);

		// parseAttachBBC will return a string (Lang::$txt key) rather than dying with a fatal_error. Up to you to decide what to do.
		if (\is_string($current_attachment)) {
			$data = '<span style="display:inline-block" class="errorbox">' . (Lang::txtExists($current_attachment, file: 'General') ? Lang::getTxt($current_attachment, file: 'General') : $current_attachment) . '</span>';

			return;
		}

		// We need a display mode.
		if (empty($params['{display}'])) {
			// Images, video, and audio are embedded by default.
			if (!empty($current_attachment['is_image']) || str_starts_with($current_attachment['mime_type'], 'video/') || str_starts_with($current_attachment['mime_type'], 'audio/')) {
				$params['{display}'] = 'embed';
			}
			// Anything else shows a link by default.
			else {
				$params['{display}'] = 'link';
			}
		}

		// Embedded file.
		if ($params['{display}'] == 'embed') {
			$alt = ' alt="' . (!empty($params['{alt}']) ? $params['{alt}'] : $current_attachment['name']) . '"';
			$title = !empty($data) ? ' title="' . Utils::htmlspecialchars($data) . '"' : '';

			// Image.
			if (!empty($current_attachment['is_image'])) {
				// Just viewing the page shouldn't increase the download count for embedded images.
				$current_attachment['href'] .= ';preview';

				if (empty($params['{width}']) && empty($params['{height}'])) {
					$return_context .= '<img src="' . $current_attachment['href'] . '"' . $alt . $title . ' class="bbc_img">';
				} else {
					$width = !empty($params['{width}']) ? ' width="' . $params['{width}'] . '"' : '';
					$height = !empty($params['{height}']) ? 'height="' . $params['{height}'] . '"' : '';
					$return_context .= '<img src="' . $current_attachment['href'] . ';image"' . $alt . $title . $width . $height . ' class="bbc_img resized"/>';
				}
			}
			// Video.
			elseif (str_starts_with($current_attachment['mime_type'], 'video/')) {
				$width = !empty($params['{width}']) ? ' width="' . $params['{width}'] . '"' : '';
				$height = !empty($params['{height}']) ? ' height="' . $params['{height}'] . '"' : '';

				$return_context .= '<div class="videocontainer"><video controls preload="metadata" src="' . $current_attachment['href'] . '" playsinline' . $width . $height . '><a href="' . $current_attachment['href'] . '" class="bbc_link">' . Utils::htmlspecialchars(!empty($data) ? $data : $current_attachment['name']) . '</a></video></div>' . (!empty($data) && $data != $current_attachment['name'] ? '<div class="smalltext">' . $data . '</div>' : '');
			}
			// Audio.
			elseif (str_starts_with($current_attachment['mime_type'], 'audio/')) {
				$width = 'max-width:100%; width: ' . (!empty($params['{width}']) ? $params['{width}'] : '400') . 'px;';
				$height = !empty($params['{height}']) ? 'height: ' . $params['{height}'] . 'px;' : '';

				$return_context .= (!empty($data) && $data != $current_attachment['name'] ? $data . ' ' : '') . '<audio controls preload="none" src="' . $current_attachment['href'] . '" class="bbc_audio" style="vertical-align:middle;' . $width . $height . '"><a href="' . $current_attachment['href'] . '" class="bbc_link">' . Utils::htmlspecialchars(!empty($data) ? $data : $current_attachment['name']) . '</a></audio>';
			}
			// Anything else.
			else {
				$width = !empty($params['{width}']) ? ' width="' . $params['{width}'] . '"' : '';
				$height = !empty($params['{height}']) ? ' height="' . $params['{height}'] . '"' : '';

				$return_context .= '<object type="' . $current_attachment['mime_type'] . '" data="' . $current_attachment['href'] . '"' . $width . $height . ' typemustmatch><a href="' . $current_attachment['href'] . '" class="bbc_link">' . Utils::htmlspecialchars(!empty($data) ? $data : $current_attachment['name']) . '</a></object>';
			}
		}
		// No image. Show a link.
		else {
			$return_context .= '<a href="' . $current_attachment['href'] . '" class="bbc_link">' . Utils::htmlspecialchars(!empty($data) ? $data : $current_attachment['name']) . '</a>';
		}

		// Use this hook to adjust the HTML output of the attach BBCode.
		// If you want to work with the attachment data itself, use one of these:
		// - integrate_pre_parseAttachBBC
		// - integrate_post_parseAttachBBC
		IntegrationHook::call('integrate_attach_bbc_validate', [&$return_context, $current_attachment, $bbc, $data, $disabled, $params]);

		// Gotta append what we just did.
		$data = $return_context;
	}
}
