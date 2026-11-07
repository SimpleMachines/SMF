<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Recursive function for displaying well-formed XML data.
 *
 * @param array $xml_data An array of XML data
 * @param string $parent_tag The parent tag
 * @param string $child_tag The child tag
 * @param int $level How many levels to indent the code
 * @param array $parent_attributes Attributes for the parent node
 */

// The parameters that were not passed get the defaults they had as arguments.
extract(['child_tag' => '', 'level' => 0, 'parent_attributes' => []], EXTR_SKIP);
?><?php
$level = max(0, $level);
$indent = str_repeat("\t", $level);
?><?= "\n" ?><?= $indent ?><<?= $parent_tag ?><?php foreach ($parent_attributes as $attr_key => $attr_value): ?> <?= htmlspecialchars(Utils::cleanXml($attr_key), ENT_XML1, 'UTF-8') ?>="<?= htmlspecialchars(Utils::cleanXml($attr_value), ENT_XML1, 'UTF-8') ?>"<?php endforeach; ?>><?php foreach ($xml_data as $key => $data): ?><?php /* Handle nested groups */ ?><?php if (is_array($data) && isset($data['identifier'], $data['children'])): ?><?php $node_attributes = $data['attributes'] ?? []; ?><?php $this->subTemplate('generic_xml_recursive', ['xml_data' => $data['children'], 'parent_tag' => $key, 'child_tag' => $data['identifier'] ?? null, 'level' => $level + 1, 'parent_attributes' => $node_attributes]); ?><?php /* Handle individual elements */ ?><?php elseif (is_array($data) && isset($data['value'])): ?><?= "\n" ?><?= $indent ?><?= "\t<" ?><?= ($data['identifier'] ?? $child_tag ?? $key) ?><?php if (isset($data['attributes'])): ?><?php foreach ($data['attributes'] as $attr_key => $attr_value): ?> <?= htmlspecialchars(Utils::cleanXml($attr_key), ENT_XML1, 'UTF-8') ?>="<?= htmlspecialchars(Utils::cleanXml($attr_value), ENT_XML1, 'UTF-8') ?>"<?php endforeach; ?><?php endif; ?><?php
$escaped_value = Utils::cleanXml($data['value']);
// Self-closing tag for empty value
?><?php if ($escaped_value === ''): ?> /><?php else: ?><?php if (preg_match('/[<&>]/', $escaped_value)): ?>><![CDATA[<?= $escaped_value ?>]]><?php else: ?>><?= htmlspecialchars($escaped_value, ENT_XML1, 'UTF-8') ?><?php endif; ?></<?= ($data['identifier'] ?? $child_tag ?? $key) ?>><?php endif; ?><?php endif; ?><?php endforeach; ?><?= "\n" ?><?= $indent ?></<?= $parent_tag ?>>