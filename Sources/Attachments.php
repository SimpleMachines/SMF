<?php

/**
 * This file contains handling attachments.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 1
 */

if (!defined('SMF'))
	die('No direct access...');

class Attachments
{
	protected $_msg = 0;
	protected $_attachmentUploadDir = false;
	protected $_attchDir = '';
	protected $_currentAttachmentUploadDir;
	protected $_subActions = array(
		'add',
		'delete',
	);
	protected $_sa = false;

	public function __construct()
	{
		global $modSettings;

		$this->_msg = $_REQUEST['msg']) ? $_REQUEST['msg'] : 0;

		$this->_currentAttachmentUploadDir = !empty($modSettings['currentAttachmentUploadDir']) ? $modSettings['currentAttachmentUploadDir'] : '';

		if (!is_array($modSettings['attachmentUploadDir']))
			$this->_attachmentUploadDir = unserialize($modSettings['attachmentUploadDir']);

		$this->_attchDir = $context['attach_dir'] = $modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']];
	}

	public function call()
	{
			global $smcFunc;

		$this->_sa = !empty($_REQUEST['sa']) ? $smcFunc['htmlspecialchars']($smcFunc['htmltrim']($_REQUEST['sa'])) : false;

		if ($this->_sa && in_array($this->_sa, $this->_subActions))
			$this->{$this->_sa}();
	}
}

?>
