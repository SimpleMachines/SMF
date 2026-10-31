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
 * Template for adding/editing a rule.
 */
?>
	<script>
		var criteriaNum = 0;
		var actionNum = 0;
		var groups = new Array()
		var labels = new Array()<?php foreach (Utils::$context['groups'] as $id => $title): ?>

		groups[<?= $id ?>] = "<?= addslashes($title) ?>";<?php endforeach; ?><?php foreach (Utils::$context['labels'] as $label): ?><?php if ($label['id'] != -1): ?>

		labels[<?= ($label['id']) ?>] = "<?= addslashes($label['name']) ?>";<?php endif; ?><?php endforeach; ?>

		function addCriteriaOption()
		{
			if (criteriaNum == 0)
			{
				for (var i = 0; i < document.forms.addrule.elements.length; i++)
					if (document.forms.addrule.elements[i].id.substr(0, 8) == "ruletype")
						criteriaNum++;
			}

			if (criteriaNum++ >= <?= Utils::$context['rule_limiters']['criteria'] ?>)
				return false;

			setOuterHTML(document.getElementById("criteriaAddHere"), '<br><select name="ruletype[' + criteriaNum + ']" id="ruletype' + criteriaNum + '" onchange="updateRuleDef(' + criteriaNum + '); rebuildRuleDesc();"><option value=""><' + '/option><option value="mid"><?= addslashes(Lang::getTxt('pm_rule_mid', file: 'PersonalMessage')) ?><' + '/option><option value="gid"><?= addslashes(Lang::getTxt('pm_rule_gid', file: 'PersonalMessage')) ?><' + '/option><option value="sub"><?= addslashes(Lang::getTxt('pm_rule_sub', file: 'PersonalMessage')) ?><' + '/option><option value="msg"><?= addslashes(Lang::getTxt('pm_rule_msg', file: 'PersonalMessage')) ?><' + '/option><option value="bud"><?= addslashes(Lang::getTxt('pm_rule_bud', file: 'PersonalMessage')) ?><' + '/option><' + '/select>&nbsp;<span id="defdiv' + criteriaNum + '" style="display: none;"><input type="text" name="ruledef[' + criteriaNum + ']" id="ruledef' + criteriaNum + '" onkeyup="rebuildRuleDesc();" value=""><' + '/span><span id="defseldiv' + criteriaNum + '" style="display: none;"><select name="ruledefgroup[' + criteriaNum + ']" id="ruledefgroup' + criteriaNum + '" onchange="rebuildRuleDesc();"><option value=""><?= addslashes(Lang::getTxt('pm_rule_sel_group', file: 'PersonalMessage')) ?><' + '/option><?php foreach (Utils::$context['groups'] as $id => $group): ?><option value="<?= $id ?>"><?= strtr($group, ["'" => "\'"]) ?><' + '/option><?php endforeach; ?><' + '/select><' + '/span><span id="criteriaAddHere"><' + '/span>');

				if (criteriaNum + 1 > <?= Utils::$context['rule_limiters']['criteria'] ?>)
					document.getElementById('addonjs1').style.display = 'none';
			}

			function addActionOption()
			{
				if (actionNum == 0)
				{
					for (var i = 0; i < document.forms.addrule.elements.length; i++)
						if (document.forms.addrule.elements[i].id.substr(0, 7) == "acttype")
							actionNum++;
				}
				if (actionNum++ >= <?= Utils::$context['rule_limiters']['actions'] ?>)
					return false;

				setOuterHTML(document.getElementById("actionAddHere"), '<br><select name="acttype[' + actionNum + ']" id="acttype' + actionNum + '" onchange="updateActionDef(' + actionNum + '); rebuildRuleDesc();"><option value=""><?= addslashes(Lang::getTxt('pm_rule_do_nothing', file: 'PersonalMessage')) ?>:<' + '/option><option value="lab"><?= addslashes(Lang::getTxt('pm_rule_label', file: 'PersonalMessage')) ?><' + '/option><option value="del"><?= addslashes(Lang::getTxt('pm_rule_delete', file: 'PersonalMessage')) ?><' + '/option><' + '/select>&nbsp;<span id="labdiv' + actionNum + '" style="display: none;"><select name="labdef[' + actionNum + ']" id="labdef' + actionNum + '" onchange="rebuildRuleDesc();"><option value=""><?= addslashes(Lang::getTxt('pm_rule_sel_label', file: 'PersonalMessage')) ?><' + '/option><?php foreach (Utils::$context['labels'] as $label): ?><?php if ($label['id'] != -1): ?><option value="<?= ($label['id']) ?>"><?= addslashes($label['name']) ?><' + '/option><?php endif; ?><?php endforeach; ?><' + '/select><' + '/span><span id="actionAddHere"><' + '/span>');

				if (actionNum + 1 > <?= Utils::$context['rule_limiters']['actions'] ?>)
					document.getElementById('addonjs2').style.display = 'none';
			}

			// Rebuild the rule description!
			function rebuildRuleDesc()
			{
				// Start with nothing.
				var text = "";
				var joinText = "";
				var actionText = "";
				var hadBuddy = false;
				var foundCriteria = false;
				var foundAction = false;
				var curNum, curVal, curDef;

				for (var i = 0; i < document.forms.addrule.elements.length; i++)
				{
					if (document.forms.addrule.elements[i].id.substr(0, 8) == "ruletype")
					{
						if (foundCriteria)
							joinText = document.getElementById("logic").value == 'and' ? <?= Utils::escapeJavaScript(' <em>' . Lang::getTxt('pm_readable_and', file: 'PersonalMessage') . '</em> ') ?> : <?= Utils::escapeJavaScript(' <em>' . Lang::getTxt('pm_readable_or', file: 'PersonalMessage') . '</em> ') ?>;
						else
							joinText = '';
						foundCriteria = true;

						curNum = document.forms.addrule.elements[i].id.match(/\d+/);
						curVal = document.forms.addrule.elements[i].value;
						if (curVal == "gid")
							curDef = document.getElementById("ruledefgroup" + curNum).value.php_htmlspecialchars();
						else if (curVal != "bud")
							curDef = document.getElementById("ruledef" + curNum).value.php_htmlspecialchars();
						else
							curDef = "";

						// What type of test is this?
						if (curVal == "mid" && curDef)
							text += joinText + <?= Utils::escapeJavaScript(Lang::getTxt('pm_readable_member', file: 'PersonalMessage')) ?>.replace("{MEMBER}", curDef);
						else if (curVal == "gid" && curDef && groups[curDef])
							text += joinText + <?= Utils::escapeJavaScript(Lang::getTxt('pm_readable_group', file: 'PersonalMessage')) ?>.replace("{GROUP}", groups[curDef]);
						else if (curVal == "sub" && curDef)
							text += joinText + <?= Utils::escapeJavaScript(Lang::getTxt('pm_readable_subject', file: 'PersonalMessage')) ?>.replace("{SUBJECT}", curDef);
						else if (curVal == "msg" && curDef)
							text += joinText + <?= Utils::escapeJavaScript(Lang::getTxt('pm_readable_body', file: 'PersonalMessage')) ?>.replace("{BODY}", curDef);
						else if (curVal == "bud" && !hadBuddy)
						{
							text += joinText + <?= Utils::escapeJavaScript(Lang::getTxt('pm_readable_buddy', file: 'PersonalMessage')) ?>;
							hadBuddy = true;
						}
					}
					if (document.forms.addrule.elements[i].id.substr(0, 7) == "acttype")
					{
						if (foundAction)
							joinText = <?= Utils::escapeJavaScript(' <em>' . Lang::getTxt('pm_readable_and', file: 'PersonalMessage') . '</em> ') ?>;
						else
							joinText = "";
						foundAction = true;

						curNum = document.forms.addrule.elements[i].id.match(/\d+/);
						curVal = document.forms.addrule.elements[i].value;
						if (curVal == "lab")
							curDef = document.getElementById("labdef" + curNum).value.php_htmlspecialchars();
						else
							curDef = "";

						// Now pick the actions.
						if (curVal == "lab" && curDef && labels[curDef])
							actionText += joinText + <?= Utils::escapeJavaScript(Lang::getTxt('pm_readable_label', file: 'PersonalMessage')) ?>.replace("{LABEL}", labels[curDef]);
						else if (curVal == "del")
							actionText += joinText + <?= Utils::escapeJavaScript(Lang::getTxt('pm_readable_delete', file: 'PersonalMessage')) ?>;
					}
				}

				// If still nothing make it default!
				if (text == "" || !foundCriteria)
					text = "<?= Lang::getTxt('pm_rule_not_defined', file: 'PersonalMessage') ?>";
				else
				{
					if (actionText != "")
						text += <?= Utils::escapeJavaScript(' <strong>' . Lang::getTxt('pm_readable_then', file: 'PersonalMessage') . '</strong> ') ?> + actionText;
					text = <?= Utils::escapeJavaScript(Lang::getTxt('pm_readable_start', file: 'PersonalMessage')) ?> + text + <?= Utils::escapeJavaScript(Lang::getTxt('pm_readable_end', file: 'PersonalMessage')) ?>;
				}

				// Set the actual HTML!
				setInnerHTML(document.getElementById("ruletext"), text);
			}
	</script>
	<form action="<?= Config::$scripturl ?>?action=pm;sa=manrules;save;rid=<?= Utils::$context['rid'] ?>" method="post" accept-charset="UTF-8" name="addrule" id="addrule" class="flow_hidden">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt(Utils::$context['rid'] == 0 ? 'pm_add_rule' : 'pm_edit_rule', file: 'PersonalMessage') ?></h3>
		</div>
		<div class="windowbg">
			<dl class="addrules">
				<dt class="floatleft">
					<strong><?= Lang::getTxt('pm_rule_name', file: 'PersonalMessage') ?></strong><br>
					<span class="smalltext"><?= Lang::getTxt('pm_rule_name_desc', file: 'PersonalMessage') ?></span>
				</dt>
				<dd class="floatleft">
					<input type="text" name="rule_name" value="<?= empty(Utils::$context['rule']->name) ? '' : Utils::$context['rule']->name ?>" size="50" required>
				</dd>
			</dl>
			<fieldset>
				<legend><?= Lang::getTxt('pm_rule_criteria', file: 'PersonalMessage') ?></legend><?php
// Add a dummy criteria to allow expansion for none js users.
Utils::$context['rule']->criteria[] = ['t' => '', 'v' => ''];
?><?php /* For each criteria print it out. */ ?><?php $isFirst = true; ?><?php foreach (Utils::$context['rule']->criteria as $k => $criteria): ?><?php if (!$isFirst && $criteria['t'] == ''): ?><div id="removeonjs1"><?php elseif (!$isFirst): ?><br><?php endif; ?>
				<select name="ruletype[<?= $k ?>]" id="ruletype<?= $k ?>" onchange="updateRuleDef(<?= $k ?>); rebuildRuleDesc();">
					<option value="" selected></option><?php foreach (['mid', 'gid', 'sub', 'msg', 'bud'] as $cr): ?>
					<option value="<?= $cr ?>"<?= $criteria['t'] == $cr ? ' selected' : '' ?>><?= Lang::getTxt('pm_rule_' . $cr, file: 'PersonalMessage') ?></option><?php endforeach; ?>
				</select>
				<span id="defdiv<?= $k ?>" <?= !in_array($criteria['t'], ['gid', 'bud']) ? '' : 'style="display: none;"' ?>>
					<input type="text" name="ruledef[<?= $k ?>]" id="ruledef<?= $k ?>" onkeyup="rebuildRuleDesc();" value="<?= in_array($criteria['t'], ['mid', 'sub', 'msg']) ? $criteria['v'] : '' ?>">
				</span>
				<span id="defseldiv<?= $k ?>" <?= $criteria['t'] == 'gid' ? '' : 'style="display: none;"' ?>>
					<select name="ruledefgroup[<?= $k ?>]" id="ruledefgroup<?= $k ?>" onchange="rebuildRuleDesc();">
						<option value=""><?= Lang::getTxt('pm_rule_sel_group', file: 'PersonalMessage') ?></option><?php foreach (Utils::$context['groups'] as $id => $group): ?>
						<option value="<?= $id ?>"<?= $criteria['t'] == 'gid' && $criteria['v'] == $id ? ' selected' : '' ?>><?= $group ?></option><?php endforeach; ?>
					</select>
				</span><?php /* If this is the dummy we add a means to hide for non js users. */ ?><?php if ($isFirst): ?><?php $isFirst = false; ?><?php elseif ($criteria['t'] == ''): ?></div><!-- .removeonjs1 --><?php endif; ?><?php endforeach; ?>
				<span id="criteriaAddHere"></span><br>
				<a href="#" onclick="addCriteriaOption(); return false;" id="addonjs1" style="display: none;">(<?= Lang::getTxt('pm_rule_criteria_add', file: 'PersonalMessage') ?>)</a>
				<br><br>
				<?= Lang::getTxt('pm_rule_logic', file: 'PersonalMessage') ?>
				<select name="rule_logic" id="logic" onchange="rebuildRuleDesc();">
					<option value="and"<?= Utils::$context['rule']->logic == 'and' ? ' selected' : '' ?>><?= Lang::getTxt('pm_rule_logic_and', file: 'PersonalMessage') ?></option>
					<option value="or"<?= Utils::$context['rule']->logic == 'or' ? ' selected' : '' ?>><?= Lang::getTxt('pm_rule_logic_or', file: 'PersonalMessage') ?></option>
				</select>
			</fieldset>
			<fieldset>
				<legend><?= Lang::getTxt('pm_rule_actions', file: 'PersonalMessage') ?></legend><?php
// As with criteria - add a dummy action for "expansion".
Utils::$context['rule']->actions[] = ['t' => '', 'v' => ''];
?><?php /* Print each action. */ ?><?php $isFirst = true; ?><?php foreach (Utils::$context['rule']->actions as $k => $action): ?><?php if (!$isFirst && $action['t'] == ''): ?><div id="removeonjs2"><?php elseif (!$isFirst): ?><br><?php endif; ?>
				<select name="acttype[<?= $k ?>]" id="acttype<?= $k ?>" onchange="updateActionDef(<?= $k ?>); rebuildRuleDesc();">
					<option value=""><?= Lang::getTxt('pm_rule_do_nothing', file: 'PersonalMessage') ?></option>
					<option value="lab"<?= $action['t'] == 'lab' ? ' selected' : '' ?>><?= Lang::getTxt('pm_rule_label', file: 'PersonalMessage') ?></option>
					<option value="del"<?= $action['t'] == 'del' ? ' selected' : '' ?>><?= Lang::getTxt('pm_rule_delete', file: 'PersonalMessage') ?></option>
				</select>
				<span id="labdiv<?= $k ?>">
					<select name="labdef[<?= $k ?>]" id="labdef<?= $k ?>" onchange="rebuildRuleDesc();">
						<option value=""><?= Lang::getTxt('pm_rule_sel_label', file: 'PersonalMessage') ?></option><?php foreach (Utils::$context['labels'] as $label): ?><?php if ($label['id'] != -1): ?>
						<option value="<?= ($label['id']) ?>"<?= $action['t'] == 'lab' && $action['v'] == $label['id'] ? ' selected' : '' ?>><?= $label['name'] ?></option><?php endif; ?><?php endforeach; ?>
					</select>
				</span><?php if ($isFirst): ?><?php $isFirst = false; ?><?php elseif ($action['t'] == ''): ?></div><!-- .removeonjs2 --><?php endif; ?><?php endforeach; ?>
				<span id="actionAddHere"></span><br>
				<a href="#" onclick="addActionOption(); return false;" id="addonjs2" style="display: none;">(<?= Lang::getTxt('pm_rule_add_action', file: 'PersonalMessage') ?>)</a>
			</fieldset>
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('pm_rule_description', file: 'PersonalMessage') ?></h3>
			</div>
			<div class="information">
				<div id="ruletext"><?= Lang::getTxt('pm_rule_js_disabled', file: 'PersonalMessage') ?></div>
			</div>
			<div class="righttext">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="submit" name="save" value="<?= Lang::getTxt('pm_rule_save', file: 'PersonalMessage') ?>" class="button">
			</div>
		</div><!-- .windowbg -->
	</form><?php /* Now setup all the bits! */ ?>
	<script><?php foreach (Utils::$context['rule']->criteria as $k => $c): ?>

			updateRuleDef(<?= $k ?>);<?php endforeach; ?><?php foreach (Utils::$context['rule']->actions as $k => $c): ?>

			updateActionDef(<?= $k ?>);<?php endforeach; ?>

			rebuildRuleDesc();<?php /* If this isn't a new rule and we have JS enabled remove the JS compatibility stuff. */ ?><?php if (Utils::$context['rid']): ?>

			document.getElementById("removeonjs1").style.display = "none";
			document.getElementById("removeonjs2").style.display = "none";<?php endif; ?><?php if (count(Utils::$context['rule']->criteria) <= Utils::$context['rule_limiters']['criteria']): ?>

			document.getElementById("addonjs1").style.display = "";<?php endif; ?><?php if (count(Utils::$context['rule']->actions) <= Utils::$context['rule_limiters']['actions']): ?>

			document.getElementById("addonjs2").style.display = "";<?php endif; ?>

		</script>