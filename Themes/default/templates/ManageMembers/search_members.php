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
 * The admin member search form
 */
?>

		<form action="<?= Config::$scripturl ?>?action=admin;area=viewmembers" method="post" accept-charset="UTF-8" id="admin_form_wrapper">
			<input type="hidden" name="sa" value="query">
			<div class="cat_bar">
				<h3 class="catbg">
					<span class="floatleft"><?= Lang::getTxt('search_for', file: 'General') ?></span>
					<span class="smalltext floatright"><?= Lang::getTxt('wild_cards_allowed', file: 'Admin') ?></span>
				</h3>
			</div>
			<div class="windowbg">
				<div class="flow_hidden">
					<div class="msearch_details floatleft">
						<dl class="settings right">
							<dt class="righttext">
								<strong><label for="mem_id"><?= Lang::getTxt('member_id', file: 'Admin') ?></label></strong>
								<select name="types[mem_id]">
									<option value="--">&lt;</option>
									<option value="-">&lt;=</option>
									<option value="=" selected>=</option>
									<option value="+">&gt;=</option>
									<option value="++">&gt;</option>
								</select>
							</dt>
							<dd>
								<input type="number" name="mem_id" id="mem_id" value="" size="6">
							</dd>
							<dt class="righttext">
								<strong><label for="age"><?= Lang::getTxt('age', file: 'Admin') ?></label></strong>
								<select name="types[age]">
									<option value="--">&lt;</option>
									<option value="-">&lt;=</option>
									<option value="=" selected>=</option>
									<option value="+">&gt;=</option>
									<option value="++">&gt;</option>
								</select>
							</dt>
							<dd>
								<input type="number" name="age" id="age" value="" size="6">
							</dd>
							<dt class="righttext">
								<strong><label for="posts"><?= Lang::getTxt('member_postcount', file: 'General') ?></label></strong>
								<select name="types[posts]">
									<option value="--">&lt;</option>
									<option value="-">&lt;=</option>
									<option value="=" selected>=</option>
									<option value="+">&gt;=</option>
									<option value="++">&gt;</option>
								</select>
							</dt>
							<dd>
								<input type="number" name="posts" id="posts" value="" size="6">
							</dd>
							<dt class="righttext">
								<strong><label for="reg_date"><?= Lang::getTxt('date_registered', file: 'General') ?></label></strong>
								<select name="types[reg_date]">
									<option value="--">&lt;</option>
									<option value="-">&lt;=</option>
									<option value="=" selected>=</option>
									<option value="+">&gt;=</option>
									<option value="++">&gt;</option>
								</select>
							</dt>
							<dd>
								<input type="date" name="reg_date" id="reg_date" value="" size="10"><span class="smalltext"></span>
							</dd>
							<dt class="righttext">
								<strong><label for="last_online"><?= Lang::getTxt('viewmembers_online', file: 'Admin') ?></label></strong>
								<select name="types[last_online]">
									<option value="--">&lt;</option>
									<option value="-">&lt;=</option>
									<option value="=" selected>=</option>
									<option value="+">&gt;=</option>
									<option value="++">&gt;</option>
								</select>
							</dt>
							<dd>
								<input type="date" name="last_online" id="last_online" value="" size="10"><span class="smalltext"></span>
							</dd>
						</dl>
					</div><!-- .msearch_details -->
					<div class="msearch_details floatright">
						<dl class="settings right">
							<dt class="righttext">
								<strong><label for="membername"><?= Lang::getTxt('username', file: 'General') ?></label></strong>
							</dt>
							<dd>
								<input type="text" name="membername" id="membername" value="">
							</dd>
							<dt class="righttext">
								<strong><label for="email"><?= Lang::getTxt('email_address', file: 'Admin') ?></label></strong>
							</dt>
							<dd>
								<input type="email" name="email" id="email" value="">
							</dd>
							<dt class="righttext">
								<strong><label for="website"><?= Lang::getTxt('website', file: 'General') ?></label></strong>
							</dt>
							<dd>
								<input type="url" name="website" id="website" value="">
							</dd>
							<dt class="righttext">
								<strong><label for="ip"><?= Lang::getTxt('ip_address', file: 'General') ?></label></strong>
							</dt>
							<dd>
								<input type="text" name="ip" id="ip" value="">
							</dd>
						</dl>
					</div><!-- .msearch_details -->
					<div class="msearch_details floatright">
						<fieldset>
							<legend><?= Lang::getTxt('activation_status', file: 'Admin') ?></legend>
							<label for="activated-0"><input type="checkbox" name="activated[]" value="1" id="activated-0" checked> <?= Lang::getTxt('activated', file: 'Admin') ?></label>&nbsp;&nbsp;
							<label for="activated-1"><input type="checkbox" name="activated[]" value="0" id="activated-1" checked> <?= Lang::getTxt('not_activated', file: 'Admin') ?></label>
						</fieldset>
					</div>
				</div><!-- .flow_hidden -->
			</div><!-- ..windowbg -->
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('member_part_of_these_membergroups', file: 'Admin') ?></h3>
			</div>
			<table class="table_grid">
				<thead>
					<tr class="title_bar">
						<th scope="col"><?= Lang::getTxt('membergroups', file: 'Admin') ?></th>
						<th scope="col" class="centercol quarter_table"><?= Lang::getTxt('primary', file: 'Admin') ?></th>
						<th scope="col" class="centercol quarter_table"><?= Lang::getTxt('additional', file: 'Admin') ?></th>
					</tr>
				</thead>
				<tbody>
<?php foreach (Utils::$context['membergroups'] as $membergroup): ?>
					<tr class="windowbg">
						<td><?= $membergroup['name'] ?></td>
						<td class="centercol">
							<input type="checkbox" name="membergroups[1][]" value="<?= $membergroup['id'] ?>" checked>
						</td>
						<td class="centercol">
							<?= $membergroup['can_be_additional'] ? '<input type="checkbox" name="membergroups[2][]" value="' . $membergroup['id'] . '" checked>' : '' ?>

						</td>
					</tr>
<?php endforeach; ?>
					<tr class="windowbg">
						<td>
							<em><?= Lang::getTxt('check_all', file: 'General') ?></em>
						</td>
						<td class="centercol">
							<input type="checkbox" onclick="invertAll(this, this.form, 'membergroups[1]');" checked>
						</td>
						<td class="centercol">
							<input type="checkbox" onclick="invertAll(this, this.form, 'membergroups[2]');" checked>
						</td>
					</tr>
				</tbody>
			</table>
			<table class="table_grid">
				<thead>
					<tr class="title_bar">
						<th scope="col">
							<?= Lang::getTxt('membergroups_postgroups', file: 'ManageMembers') ?>

						</th>
						<th class="quarter_table"></th>
					</tr>
				</thead>
				<tbody>
<?php foreach (Utils::$context['postgroups'] as $postgroup): ?>
					<tr class="windowbg">
						<td>
							<?= $postgroup['name'] ?>

						</td>
						<td class="centercol">
							<input type="checkbox" name="postgroups[]" value="<?= $postgroup['id'] ?>" checked>
						</td>
					</tr>
<?php endforeach; ?>
					<tr class="windowbg">
						<td>
							<em><?= Lang::getTxt('check_all', file: 'General') ?></em>
						</td>
						<td class="centercol">
							<input type="checkbox" onclick="invertAll(this, this.form, 'postgroups[]');" checked>
						</td>
					</tr>
				</tbody>
			</table>
			<br>
			<input type="submit" value="<?= Lang::getTxt('search', file: 'General') ?>" class="button">
		</form>