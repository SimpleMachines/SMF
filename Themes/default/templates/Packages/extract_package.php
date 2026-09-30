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

use SMF\Config;
use SMF\Lang;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Extract package contents
 */
?>

		<div class="cat_bar">
			<h3 class="catbg"><?php if (empty(Utils::$context['redirect_url'])): ?><?= Lang::getTxt(Utils::$context['uninstalling'] ? 'uninstall' : 'extracting', file: 'Packages') ?><?php else: ?><?= Lang::getTxt('package_installed_redirecting', file: 'Packages') ?><?php endif; ?></h3>
		</div>
		<div class="windowbg"><?php /* If we are going to redirect we have a slightly different agenda. */ ?><?php if (!empty(Utils::$context['redirect_url'])): ?>

			<?= Utils::$context['redirect_text'] ?><br><br>
			<a href="<?= Utils::$context['redirect_url'] ?>"><?= Lang::getTxt('package_installed_redirect_go_now', file: 'Packages') ?></a><span id="countdown" class="hidden"> (5) </span> | <a href="<?= Config::$scripturl ?>?action=admin;area=packages;sa=browse"><?= Lang::getTxt('package_installed_redirect_cancel', file: 'Packages') ?></a>
			<script>
				var countdown = <?= Utils::$context['redirect_timeout'] ?>;
				var el = document.getElementById('countdown');
				var loop = setInterval(doCountdown, 1000);

				function doCountdown()
				{
					countdown--;
					el.textContent = " (" + countdown + ") ";

					if (countdown == 0)
					{
						clearInterval(loop);
						window.location = "<?= Utils::$context['redirect_url'] ?>";
					}
				}
				el.classList.remove('hidden');
				el.value = " (" + countdown + ") ";
			</script><?php elseif (Utils::$context['uninstalling']): ?>

			<?= Lang::getTxt('package_uninstall_done', file: 'Packages') ?> <br>
			<a href="<?= Utils::$context['keep_url'] ?>" class="button"><?= Lang::getTxt('package_keep', file: 'Packages') ?></a><a href="<?= Utils::$context['remove_url'] ?>" class="button"><?= Lang::getTxt('package_delete2', file: 'Packages') ?></a><?php elseif (Utils::$context['install_finished']): ?><?php if (Utils::$context['extract_type'] == 'avatar'): ?>

			<?= Lang::getTxt('avatars_extracted', file: 'Packages') ?><?php elseif (Utils::$context['extract_type'] == 'language'): ?>

			<?= Lang::getTxt('language_extracted', file: 'Packages') ?><?php else: ?>

			<?= Lang::getTxt('package_installed_done', file: 'Packages') ?><?php endif; ?><?php else: ?>

			<?= Lang::getTxt('corrupt_compatible', file: 'Packages') ?><?php endif; ?>

		</div><!-- .windowbg --><?php /* Show the "restore permissions" screen? */ ?><?php if ($this->hasSubTemplate('show_list') && !empty(Utils::$context['restore_file_permissions']['rows'])): ?><br><?php $this->subTemplate('show_list', ['list_id' => 'restore_file_permissions']); ?><?php endif; ?>
