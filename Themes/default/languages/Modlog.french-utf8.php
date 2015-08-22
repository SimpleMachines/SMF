<?php
// Version: 2.1 Beta 2; Modlog

$txt['modlog_date'] = 'Date';
$txt['modlog_member'] = 'Membre';
$txt['modlog_position'] = 'Rang';
$txt['modlog_action'] = 'Action';
$txt['modlog_ip'] = 'IP';
$txt['modlog_search_result'] = 'Résultats de la recherche';
$txt['modlog_total_entries'] = 'Total des actions';
$txt['modlog_ac_approve_topic'] = 'Sujet &quot;{topic}&quot; approuvé par &quot;{member}&quot;';
$txt['modlog_ac_unapprove_topic'] = 'Sujet non approuvé &quot;{topic}&quot; par &quot;{member}&quot;';
$txt['modlog_ac_approve'] = 'Message &quot;{subject}&quot; approuvé dans &quot;{topic}&quot; par &quot;{member}&quot;';
$txt['modlog_ac_unapprove'] = 'Message non approuvé &quot;{subject}&quot; dans &quot;{topic}&quot; par &quot;{member}&quot;';
$txt['modlog_ac_lock'] = '&quot;{topic}&quot; verrouillé';
$txt['modlog_ac_warning'] = '{member} averti concernant &quot;{message}&quot;';
$txt['modlog_ac_unlock'] = '&quot;{topic}&quot; débloqué';
$txt['modlog_ac_sticky'] = '&quot;{topic}&quot; épinglé';
$txt['modlog_ac_unsticky'] = '&quot;{topic}&quot; dépinglé';
$txt['modlog_ac_delete'] = '&quot;{subject}&quot; effacé de &quot;{topic}&quot; par &quot;{member}&quot;';
$txt['modlog_ac_delete_member'] = 'Membre &quot;{name}&quot; supprimé';
$txt['modlog_ac_remove'] = 'Sujet &quot;{topic}&quot; effacé de &quot;{board}&quot;';
$txt['modlog_ac_modify'] = '&quot;{message}&quot; modifié par &quot;{member}&quot;';
$txt['modlog_ac_merge'] = 'Sujets fusionnés pour créer &quot;{topic}&quot;';
$txt['modlog_ac_split'] = '&quot;{topic}&quot; séparé en deux pour créer &quot;{new_topic}&quot;';
$txt['modlog_ac_move'] = '&quot;{topic}&quot; déplacé de &quot;{board_from}&quot; à &quot;{board_to}&quot;';
$txt['modlog_ac_profile'] = 'Profil de &quot;{member}&quot; modifié';
$txt['modlog_ac_pruned'] = 'Messages vieux de plus de {days} jours purgés';
$txt['modlog_ac_news'] = 'Nouvelles mises à jour';
$txt['modlog_ac_clearlog_moderate'] = 'Cleared the moderation log';
$txt['modlog_ac_clearlog_admin'] = 'Cleared the administration log';
$txt['modlog_enter_comment'] = 'Entrer un commentaire de modération';
$txt['modlog_moderation_log'] = 'Journal de Modération';
$txt['modlog_moderation_log_desc'] = 'Voici la liste des actions de modération faites par les modérateurs du forum.';
$txt['modlog_no_entries_found'] = 'Aucune entrée pour le moment dans le journal de modération.';
$txt['modlog_remove'] = 'Supprimer';
$txt['modlog_removeall'] = 'Tout supprimer';
$txt['modlog_remove_selected_confirm'] = 'Êtes vous certain de vouloir supprimer les entrées de journal sélectionnées?';
$txt['modlog_remove_all_confirm'] = 'Êtes vous certain de vouloir supprimer complètement le journal?';
$txt['modlog_go'] = 'Allons-y';
$txt['modlog_add'] = 'Ajouter';
$txt['modlog_search'] = 'Recherche Rapide';
$txt['modlog_by'] = 'Par';
$txt['modlog_id'] = '<em>Supprimé - (ID:%1$d)</em>';

$txt['modlog_ac_add_warn_template'] = 'Modèle d\'avertissement ajouté&nbsp;: &quot;{template}&quot;';
$txt['modlog_ac_modify_warn_template'] = 'Modèle d\'avertissement modifié&nbsp;: &quot;{template}&quot;';
$txt['modlog_ac_delete_warn_template'] = 'Modèle d\'avertissement supprimé&nbsp;: &quot;{template}&quot;';

$txt['modlog_ac_ban'] = 'Critères de bannissement ajoutés&nbsp;:';
$txt['modlog_ac_ban_remove'] = 'Removed ban triggers:';
$txt['modlog_ac_ban_trigger_member'] = ' <em>Membre</em>&nbsp;: {member}';
$txt['modlog_ac_ban_trigger_email'] = ' <em>E-mail</em>&nbsp;: {email}';
$txt['modlog_ac_ban_trigger_ip_range'] = ' <em>IP</em>&nbsp;: {ip_range}';
$txt['modlog_ac_ban_trigger_hostname'] = ' <em>Nom d\'hôte</em>&nbsp;: {hostname}';

$txt['modlog_admin_log'] = 'Journal d\'Administration';
$txt['modlog_admin_log_desc'] = 'Voici la liste des actions d\'administration effectuées sur votre forum.';
$txt['modlog_admin_log_no_entries_found'] = 'Aucune entrée pour le moment dans le journal d\'administration.';

// Admin type strings.
$txt['modlog_ac_upgrade'] = 'Forum mis à jour en version {version}';
$txt['modlog_ac_install'] = 'Version {version} installée';
$txt['modlog_ac_add_board'] = 'Nouvelle section créée&nbsp;: &quot;{board}&quot;';
$txt['modlog_ac_edit_board'] = 'Section &quot;{board}&quot; modifiée';
$txt['modlog_ac_delete_board'] = 'Section &quot;{boardname}&quot; supprimée';
$txt['modlog_ac_add_cat'] = 'Nouvelle catégorie créée&nbsp;: &quot;{catname}&quot;';
$txt['modlog_ac_edit_cat'] = 'Catégorie &quot;{catname}&quot; modifiée';
$txt['modlog_ac_delete_cat'] = 'Catégorie &quot;{catname}&quot; supprimée';

$txt['modlog_ac_delete_group'] = 'Groupe &quot;{group}&quot; supprimé';
$txt['modlog_ac_add_group'] = 'Groupe &quot;{group}&quot; créé';
$txt['modlog_ac_edited_group'] = 'Groupe &quot;{group}&quot; modifié';
$txt['modlog_ac_added_to_group'] = '&quot;{member}&quot; ajouté au groupe &quot;{group}&quot;';
$txt['modlog_ac_removed_from_group'] = '&quot;{member}&quot; retiré du groupe &quot;{group}&quot;';
$txt['modlog_ac_removed_all_groups'] = '&quot;{member}&quot; retiré de tous les groupes';

$txt['modlog_ac_remind_member'] = 'Rappel envoyé à &quot;{member}&quot; pour l\'activation de son compte';
$txt['modlog_ac_approve_member'] = 'Compte de &quot;{member}&quot; approuvé/activé';
$txt['modlog_ac_newsletter'] = 'Infolettre envoyée';

$txt['modlog_ac_install_package'] = 'Nouveau paquet installé&nbsp;: &quot;{package}&quot;, version {version} ';
$txt['modlog_ac_upgrade_package'] = 'Paquet mis à jour&nbsp;: &quot;{package}&quot; à la version {version} ';
$txt['modlog_ac_uninstall_package'] = 'Paquet désinstallé&nbsp;: &quot;{package}&quot;, version {version} ';

// Restore topic.
$txt['modlog_ac_restore_topic'] = 'Sujet &quot;{topic}&quot; restauré à partir de &quot;{board}&quot; vers la section &quot;{board_to}&quot;';
$txt['modlog_ac_restore_posts'] = 'Messages restaurés à partir de &quot;{subject}&quot; vers le sujet &quot;{topic}&quot; dans la section &quot;{board}&quot;';

$txt['modlog_parameter_guest'] = '<em>Invité</em>';

$txt['modlog_ac_approve_attach'] = 'Approuvé &quot;{filename}&quot; dans &quot;{message}&quot;';
$txt['modlog_ac_remove_attach'] = 'Supprimer &quot;{filename}&quot; non approuvé dans &quot;{message}&quot;';

// Handling reports on posts
$txt['modlog_report'] = 'report';
$txt['modlog_ac_close_report'] = 'Closed {report} on &quot;{message}&quot;';
$txt['modlog_ac_ignore_report'] = 'Disregarded {report} on &quot;{message}&quot;';
$txt['modlog_ac_open_report'] = 'Reopened {report} on &quot;{message}&quot;';
$txt['modlog_ac_unignore_report'] = 'Undone disregard of {report} on &quot;{message}&quot;';

// Handling reports on users
$txt['modlog_ac_close_user_report'] = 'Closed {report} on profile of {member}';
$txt['modlog_ac_ignore_user_report'] = 'Disregarded {report} on profile of {member}';
$txt['modlog_ac_open_user_report'] = 'Reopened {report} on profile of {member}';
$txt['modlog_ac_unignore_user_report'] = 'Undone disregard of {report} on profile of {member}';

// Poll stuff
$txt['modlog_ac_add_poll'] = 'Added a poll to &quot;{topic}&quot;';
$txt['modlog_ac_edit_poll'] = 'Edited the poll in &quot;{topic}&quot;';
$txt['modlog_ac_lock_poll'] = 'Locked voting in the poll in &quot;{topic}&quot;';
$txt['modlog_ac_remove_poll'] = 'Removed the poll from &quot;{topic}&quot;';
$txt['modlog_ac_reset_poll'] = 'Reset votes in the poll in &quot;{topic}&quot;';
$txt['modlog_ac_unlock_poll'] = 'Unlocked voting in the poll in &quot;{topic}&quot;';

?>