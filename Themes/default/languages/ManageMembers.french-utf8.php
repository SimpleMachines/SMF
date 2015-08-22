<?php
// Version: 2.1 Beta 2; ManageMembers

global $context;

$txt['groups'] = 'Groupes';
$txt['viewing_groups'] = 'Affichage des groupes de membres';

$txt['membergroups_title'] = 'Gestion des Groupes de Membres';
$txt['membergroups_description'] = 'Ce sont des ensembles de membres qui ont les mêmes paramètres de permissions, apparence et droits d\'accès. Certains groupes sont basés sur le nombre de messages postés par le membre. Vous pouvez assigner quelqu\'un à un groupe en sélectionnant son profil et en modifiant le paramètre correspondant de son compte.';
$txt['membergroups_modify'] = 'Modifier';

$txt['membergroups_add_group'] = 'Ajouter un groupe de membres';
$txt['membergroups_regular'] = 'Groupes permanents';
$txt['membergroups_post'] = 'Groupes posteurs';
$txt['membergroups_guests_na'] = 'n/a';

$txt['membergroups_group_name'] = 'Nom du groupe';
$txt['membergroups_new_board'] = 'Sections visibles';
$txt['membergroups_new_board_desc'] = 'Les sections que le groupe de membres peut voir';
$txt['membergroups_new_board_post_groups'] = '<em>Note&nbsp;: normalement, les groupes posteurs n\'ont pas besoin d\'un accés parce que le groupe dans lequel le membre est inclus lui donnera les autorisations nécessaires.</em>';
$txt['membergroups_new_as_inherit'] = 'hérité de';
$txt['membergroups_new_as_type'] = 'par type';
$txt['membergroups_new_as_copy'] = 'basé sur';
$txt['membergroups_new_copy_none'] = '(aucun)';
$txt['membergroups_can_edit_later'] = 'Vous pourrez les modifier plus tard.';
$txt['membergroups_can_manage_access'] = 'This group can see all boards because they have the power to manage boards.';

$txt['membergroups_cannot_delete_paid'] = 'This group cannot be deleted, it is currently in use by the following paid subscription(s): %1$s';

$txt['membergroups_edit_group'] = 'Modifier le groupe de membres';
$txt['membergroups_edit_name'] = 'Nom du groupe';
$txt['membergroups_edit_inherit_permissions'] = 'Héritage des Permissions';
$txt['membergroups_edit_inherit_permissions_desc'] = 'Sélectionnez &quot;Aucun&quot; pour permettre à un groupe d\'avoir ses propres permissions.';
$txt['membergroups_edit_inherit_permissions_no'] = 'Aucun - Utiliser des permissions uniques';
$txt['membergroups_edit_inherit_permissions_from'] = 'Hériter de';
$txt['membergroups_edit_hidden'] = 'Visibilité';
$txt['membergroups_edit_hidden_no'] = 'Visible';
$txt['membergroups_edit_hidden_boardindex'] = 'Visible - Sauf dans la liste de la page d\'accueil';
$txt['membergroups_edit_hidden_all'] = 'Invisible';
// Do not use numeric entities in the below string.
$txt['membergroups_edit_hidden_warning'] = '&#202;tes-vous s&#252;r de vouloir interdire l\\\'assignation de ce groupe en tant que groupe principal&nbsp;?\\n\\nIl ne pourra plus être affecté qu\\\'en tant que groupe additionnel, et pour tous les membres l\\\'utilisant comme groupe principal, il sera converti en groupe additionnel.';
$txt['membergroups_edit_desc'] = 'Description du Groupe';
$txt['membergroups_edit_group_type'] = 'Type de Groupe';
$txt['membergroups_edit_select_group_type'] = 'Choisissez l\'un de ces types de groupe';
$txt['membergroups_group_type_private'] = 'Privé <span class="smalltext">(Les membres sont ajoutés manuellement)</span>';
$txt['membergroups_group_type_protected'] = 'Protégé <span class="smalltext">(Seuls les administrateurs peuvent le gérer)</span>';
$txt['membergroups_group_type_request'] = 'Sur demande <span class="smalltext">(Tout le monde peut demander à être membre du groupe)</span>';
$txt['membergroups_group_type_free'] = 'Libre <span class="smalltext">(Tout le monde peut rejoindre et quitter le groupe à tout moment)</span>';
$txt['membergroups_group_type_post'] = 'Basé sur les messages <span class="smalltext">(L\'appartenance au groupe dépend du nombre de messages postés)</span>';
$txt['membergroups_min_posts'] = 'Nombre de messages requis';
$txt['membergroups_online_color'] = 'Couleur dans la liste des membres connectés';
$txt['membergroups_icon_count'] = 'Nombre d\'icônes';
$txt['membergroups_icon_image'] = 'Nom de fichier de l\'icône';
$txt['membergroups_icon_image_note'] = 'Vous pouvez charger des images personnalisées dans le dossier du thème par défaut pour avoir la possibilité de les sélectionner ici.';
$txt['membergroups_max_messages'] = 'Nombre de MP maximum';
$txt['membergroups_max_messages_note'] = '0 = illimité';
$txt['membergroups_tfa_force'] = 'Force Two-Factor-Authentication (2FA) for this membergroup';
$txt['membergroups_tfa_force_note'] = 'Be sure to warn your users before you activate this!';
$txt['membergroups_edit_save'] = 'Sauvegarder';
$txt['membergroups_delete'] = 'Effacer';
$txt['membergroups_confirm_delete'] = 'Êtes-vous sûr de vouloir effacer ce groupe&nbsp;?!';
$txt['membergroups_confirm_delete_mod'] = 'This group is assigned to moderate one or more boards. Are you sure you want to delete it?';
$txt['membergroups_swap_mod'] = 'This group is assigned to moderate one or more boards. Changing it to a post group will result in that group being dropped as moderator of those boards.';

$txt['membergroups_members_title'] = 'Montrer tous les membres du groupe';
$txt['membergroups_members_group_members'] = 'Membres du Groupe';
$txt['membergroups_members_no_members'] = 'Ce groupe est actuellement vide';
$txt['membergroups_members_add_title'] = 'Ajouter un membre à ce groupe';
$txt['membergroups_members_add_desc'] = 'Liste des membres à ajouter';
$txt['membergroups_members_add'] = 'Ajouter les membres';
$txt['membergroups_members_remove'] = 'Enlever du groupe';
$txt['membergroups_members_last_active'] = 'Dernière Connexion';
$txt['membergroups_members_additional_only'] = 'Ajouter comme groupe additionnel seulement.';
$txt['membergroups_members_group_moderators'] = 'Modérateurs du Groupe';
$txt['membergroups_members_description'] = 'Description';
// Use javascript escaping in the below.
$txt['membergroups_members_deadmin_confirm'] = 'Êtes-vous sûr de vouloir vous retirer du groupe administrateur ?';

$txt['membergroups_postgroups'] = 'Groupes Posteurs';
$txt['membergroups_settings'] = 'Réglages des groupes de membres';
$txt['groups_manage_membergroups'] = 'Groupes autorisés à modifier les groupes de membres';
$txt['membergroups_select_permission_type'] = 'Sélectionner un profil de permissions';
$txt['membergroups_images_url'] = 'Themes/{theme}/images/membericons/ ';
$txt['membergroups_select_visible_boards'] = 'Montrer les sections';
$txt['membergroups_members_top'] = 'Membres';
$txt['membergroups_name'] = 'Nom';
$txt['membergroups_icons'] = 'Icônes';

$txt['admin_browse_approve'] = 'Membres dont le compte est en attente d\'approbation';
$txt['admin_browse_approve_desc'] = 'Ici vous pouvez gérer tous les membres en attente d\'approbation de leur compte.';
$txt['admin_browse_activate'] = 'Membres dont le compte est en attente d\'activation';
$txt['admin_browse_activate_desc'] = 'Cette interface liste tous les membres qui n\'ont pas encore activé leur compte sur votre forum.';
$txt['admin_browse_awaiting_approval'] = 'En attente d\'approbation (%1$d)';
$txt['admin_browse_awaiting_activate'] = 'En attente d\'activation (%1$d)';

$txt['admin_browse_username'] = 'Identifiant';
$txt['admin_browse_email'] = 'Adresse e-mail';
$txt['admin_browse_ip'] = 'Adresse IP';
$txt['admin_browse_registered'] = 'Inscrit';
$txt['admin_browse_id'] = 'ID';
$txt['admin_browse_with_selected'] = 'Avec la sélection';
$txt['admin_browse_no_members_approval'] = 'Aucun compte n\'est actuellement en attente d\'approbation.';
$txt['admin_browse_no_members_activate'] = 'Aucun compte n\'est actuellement en attente d\'activation.';

// Don't use entities in the below strings, except the main ones. (lt, gt, quot.)
$txt['admin_browse_warn'] = 'tous les membres sélectionnés ?';
$txt['admin_browse_outstanding_warn'] = 'tous les membres affectés ?';
$txt['admin_browse_w_approve'] = 'Approuver';
$txt['admin_browse_w_activate'] = 'Activer';
$txt['admin_browse_w_delete'] = 'Supprimer';
$txt['admin_browse_w_reject'] = 'Rejeter';
$txt['admin_browse_w_remind'] = 'Rappeler';
$txt['admin_browse_w_approve_deletion'] = 'Approuver (Suppression de comptes)';
$txt['admin_browse_w_email'] = 'et envoyer un e-mail';
$txt['admin_browse_w_approve_require_activate'] = 'Approuver et requérir une activation';

$txt['admin_browse_filter_by'] = 'Filtrer par';
$txt['admin_browse_filter_show'] = 'Afficher';
$txt['admin_browse_filter_type_0'] = 'les nouveaux comptes non activés';
$txt['admin_browse_filter_type_2'] = 'les changements d\'adresse e-mail non vérifiés';
$txt['admin_browse_filter_type_3'] = 'les nouveaux comptes non approuvés';
$txt['admin_browse_filter_type_4'] = 'les suppressions de comptes non approuvées';
$txt['admin_browse_filter_type_5'] = 'les comptes non approuvés notés "Sous l\'âge minimum"';

$txt['admin_browse_outstanding'] = 'Membres exceptionnels';
$txt['admin_browse_outstanding_days_1'] = 'Avec tous les membres inscrits depuis plus longtemps que';
$txt['admin_browse_outstanding_days_2'] = 'jours';
$txt['admin_browse_outstanding_perform'] = 'Effectuer l\'action suivante';
$txt['admin_browse_outstanding_go'] = 'Effectuer l\'action';

$txt['check_for_duplicate'] = 'Chercher et afficher les doublons';
$txt['dont_check_for_duplicate'] = 'Ne pas afficher les doublons';
$txt['duplicates'] = 'Doublons';

$txt['not_activated'] = 'Pas activé';

?>