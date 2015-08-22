<?php
// Version: 2.1 Beta 2; ManagePermissions

$txt['permissions_title'] = 'Gestion des Permissions';
$txt['permissions_modify'] = 'Modifier';
$txt['permissions_view'] = 'Voir';
$txt['permissions_allowed'] = 'Autorisées';
$txt['permissions_denied'] = 'Refusées';
$txt['permission_cannot_edit'] = '<strong>Note</strong>&nbsp;: vous ne pouvez pas modifier ce profil de permission car c\'est un profil-type prédéfini par SMF. Si vous voulez changer les permissions de ce profil, vous devez d\'abord créer une copie du profil en cliquant <a href="%1$s">ici</a>.';

$txt['permissions_for_profile'] = 'Permissions pour le Profil&nbsp;';
$txt['permissions_boards_desc'] = 'La liste ci-dessous montre le jeu de permissions qui a été assigné à chaque section de votre forum. Vous pouvez modifier les permissions de profil assignées en cliquant sur le nom de la section, ou en sélectionnant &quot;Tout modifier&quot; en bas de la page. Pour modifier le profil lui-même, cliquez simplement sur le nom du profil.';
$txt['permissions_board_all'] = 'Tout modifier';
$txt['permission_profile'] = 'Profil de Permission';
$txt['permission_profile_desc'] = 'Quel <a href="%1$s">jeu de permissions</a> la section doit utiliser.';
$txt['permission_profile_inherit'] = 'Hériter de la section parente';

$txt['permissions_profile'] = 'Profil';
$txt['permissions_profiles_desc'] = 'Les profils de permissions sont assignés à des sections individuelles pour vous permettre de gérer facilement vos paramètres de sécurité. D\'ici, vous pouvez créer, modifier et supprimer des profils de permissions.';
$txt['permissions_profiles_change_for_board'] = 'Modifier le profil de permissions pour&nbsp;: &quot;%1$s&quot;';
$txt['permissions_profile_default'] = 'Par défaut';
$txt['permissions_profile_no_polls'] = 'Pas de Sondages';
$txt['permissions_profile_reply_only'] = 'Réponses uniquement';
$txt['permissions_profile_read_only'] = 'Lecture uniquement';

$txt['permissions_profile_rename'] = 'Renommer';
$txt['permissions_profile_edit'] = 'Modifier les Profils';
$txt['permissions_profile_new'] = 'Nouveau Profil';
$txt['permissions_profile_new_create'] = 'Créer';
$txt['permissions_profile_name'] = 'Nom du Profil';
$txt['permissions_profile_used_by'] = 'Utilisé par';
$txt['permissions_profile_used_by_one'] = '1 section';
$txt['permissions_profile_used_by_many'] = '%1$d sections';
$txt['permissions_profile_used_by_none'] = 'Aucune section';
$txt['permissions_profile_do_edit'] = 'Modifier';
$txt['permissions_profile_do_delete'] = 'Supprimer';

$txt['permissionname_profile_signature'] = 'Edit signature';
$txt['permissionhelp_profile_signature'] = 'Allow the member to edit the signature field in their profile';
$txt['permissionname_profile_signature_own'] = 'Own signature';
$txt['permissionname_profile_signature_any'] = 'Any signature';
$txt['permissionname_profile_forum'] = 'Allow Forum Profile edits';
$txt['permissionhelp_profile_forum'] = 'This option will allow a member to edit their Forum Profile';
$txt['permissionname_profile_forum_own'] = 'Own profile';
$txt['permissionname_profile_forum_any'] = 'Any profile';
$txt['permissionname_profile_other'] = 'Edit website';
$txt['permissionhelp_profile_other'] = 'Allow the member to edit the website fields in their profile';
$txt['permissionname_profile_other_own'] = 'Own profile';
$txt['permissionname_profile_other_any'] = 'Any profile';
$txt['permissionname_profile_blurb'] = 'Edit personal text';
$txt['permissionhelp_profile_blurb'] = 'Allow the member to edit the personal text field in their profile';
$txt['permissionname_profile_blurb_own'] = 'Own profile';
$txt['permissionname_profile_blurb_any'] = 'Any profile';
$txt['permissions_profile_copy_from'] = 'Copier les permissions à partir de';

$txt['permissions_includes_inherited'] = 'Groupes hérités';

$txt['permissions_all'] = 'tous';
$txt['permissions_none'] = 'aucun';
$txt['permissions_set_permissions'] = 'Choisir les permissions';

$txt['permissions_advanced_options'] = 'Options avancées';
$txt['permissions_with_selection'] = 'Pour la sélection';
$txt['permissions_apply_pre_defined'] = 'Appliquer le profil de permissions prédéfini';
$txt['permissions_select_pre_defined'] = 'Choisir un profil prédéfini';
$txt['permissions_copy_from_board'] = 'Copier les permissions de cette section';
$txt['permissions_select_board'] = 'Sélectionnez une section';
$txt['permissions_like_group'] = 'Donner les permissions comme ce groupe';
$txt['permissions_select_membergroup'] = 'Choisir un groupe de membres';
$txt['permissions_add'] = 'Ajouter une permission';
$txt['permissions_remove'] = 'Refuser une permission';
$txt['permissions_deny'] = 'Interdire la permission';
$txt['permissions_select_permission'] = 'Choisir une permission';

// All of the following block of strings should not use entities, instead use \\" for &quot; etc.
$txt['permissions_only_one_option'] = 'Vous ne pouvez choisir qu\'une seule action pour modifier les permissions';
$txt['permissions_no_action'] = 'Aucune action choisie';
$txt['permissions_deny_dangerous'] = 'Vous êtes sur le point d\'interdire une ou plusieurs permissions.\\nCeci peut être dangereux et causer des résultats inattendus si vous ne vous êtes pas assuré que personne n\'est \\"accidentellement\\" dans le ou les groupes auxquels vous interdisez les permissions.\\n\\nÊtes-vous sûr de vouloir continuer?';

$txt['permissions_modify_group'] = 'Modifier un Groupe';
$txt['permissions_general'] = 'Permissions Générales';
$txt['permissions_board'] = 'Permissions par Défaut pour les Sections';
$txt['permissions_board_desc'] = '<strong>Note</strong>&nbsp;: changer ces permissions affectera toutes les sections utilisant actuellement le profil de permissions &quot;par défaut&quot;. Les sections n\'utilisant pas le profil &quot;par défaut&quot; ne seront pas affectées par les changements de cette page.';
$txt['permissions_commit'] = 'Sauver les Changements';
$txt['permissions_on'] = 'du profil';
$txt['permissions_local_for'] = 'Permissions pour le groupe';
$txt['permissions_option_own'] = 'Own';
$txt['permissions_option_any'] = 'Any';
$txt['permissions_option_on'] = 'A';
$txt['permissions_option_off'] = 'R';
$txt['permissions_option_deny'] = 'I';
$txt['permissions_option_desc'] = 'Pour chaque groupe, vous pouvez choisir soit \'Autoriser\' (A), \'Refuser\' (R), ou <span style="color: red;">\'Interdire\' (I)</span>.<br /><br />Rappelez-vous que si vous interdisez une permission, tous les membres - qu\'ils soient modérateurs ou autres - présents dans ce groupe se verront refuser la permission aussi.<br />Pour cette raison, vous devriez interdire avec précaution, et seulement lorsque <strong>nécessaire</strong>. \'Refuser\', de son côté, n\'interdit l\'accès que si rien d\'autre ne vient le contredire.';

$txt['permissiongroup_general'] = 'Général';
$txt['permissionname_view_stats'] = 'Voir les stats du forum';
$txt['permissionhelp_view_stats'] = 'Les stats du forum sont une page résumant toutes les statistiques du forum&nbsp;: nombre de membres, nombre  de messages par jour et plusieurs Top 10. Autoriser cette permission ajoute un lien en bas de l\'accueil du forum (\'[+ de Stats]\').';
$txt['permissionname_view_mlist'] = 'Voir la liste des membres et groupes';
$txt['permissionhelp_view_mlist'] = 'La liste des membres affiche tous les membres qui se sont inscrits sur votre forum. La liste peut être classée et scrutée. La liste des membres est accessible depuis l\'accueil du forum et la page de stats, en cliquant sur le nombre de membres. Cela s\'applique également à la page des groupes, une mini-liste des membres présents dans un groupe spécifique.';
$txt['permissionname_who_view'] = 'Voir la page <em>Qui est en ligne&nbsp;?</em>';
$txt['permissionhelp_who_view'] = '<em>Qui est en ligne&nbsp;?</em> affiche tous les membres qui sont actuellement connectés et ce qu\'ils font en ce moment. Cette permission ne fonctionnera que si vous l\'avez aussi validée dans \'Réglages et options\'. Vous pouvez accéder à la page <em>Qui est en ligne&nbsp;?</em> en cliquant sur le lien dans la section <em>Membres en ligne</em> sur l\'accueil du forum. Même si ce n\'est pas permis, les membres pourront tout de même voir qui est en ligne, mais ne pourront pas voir où ils sont.';
$txt['permissionname_search_posts'] = 'Rechercher des messages ou des sujets';
$txt['permissionhelp_search_posts'] = 'La permission de recherche autorise l\'utilisateur à rechercher dans toutes les sections auxquels il peut accéder. Quand la permission de recherche est activée, un bouton \'Recherche\' sera ajouté à la barre de menu principal du forum.';

$txt['permissiongroup_pm'] = 'Messagerie personnelle';
$txt['permissionname_pm_read'] = 'Lire les messages personnels';
$txt['permissionhelp_pm_read'] = 'Cette permission autorise les membres à accéder à la messagerie personnelle et à lire leurs messages personnels. Sans cette permission, un membre ne peut pas envoyer de messages personnels.';
$txt['permissionname_pm_send'] = 'Envoyer des messages personnels';
$txt['permissionhelp_pm_send'] = 'Envoyer des messages personnels à d\'autres membres inscrits. Nécessite la permission \'Lire des messages personnels\'.';

$txt['permissiongroup_calendar'] = 'Calendrier';
$txt['permissionname_calendar_view'] = 'Voir le calendrier';
$txt['permissionhelp_calendar_view'] = 'Le calendrier affiche pour chaque mois les anniversaires, les événements et les jours fériés. Cette permission autorise l\'accès à ce calendrier. Quand cette permission est validée, un bouton est ajouté à la barre de menu principal et une liste est affichée au bas de l\'acceuil du forum avec les anniversaires courants et à venir, événements et fêtes. Le calendrier doit être activé depuis \'Configuration - Options principales\'.';
$txt['permissionname_calendar_post'] = 'Créer des événements dans le calendrier';
$txt['permissionhelp_calendar_post'] = 'Un événement est un sujet lié à une certaine date ou plage de dates. Vous pouvez créer des événements depuis le calendrier. Un événement ne peut être créé que par un utilisateur qui a la permission de poster des nouveaux sujets.';
$txt['permissionname_calendar_edit'] = 'Modifier les événements du calendrier';
$txt['permissionhelp_calendar_edit'] = 'Un événement est un sujet lié à une certaine date ou plage de dates. Il peut être modifié en cliquant l\'astérisque rouge (<span style="color: red;">*</span>) à coté de l\'évènement sur la page du calendrier. Pour pouvoir modifier un événement, l\'utilisateur doit avoir les permissions suffisantes pour modifier le premier message du sujet lié à cet événement.';
$txt['permissionname_calendar_edit_own'] = 'Événements personnels';
$txt['permissionname_calendar_edit_any'] = 'Tous les événements';

$txt['permissiongroup_maintenance'] = 'Administration du forum';
$txt['permissionname_admin_forum'] = 'Administrer le forum et la base de données';
$txt['permissionhelp_admin_forum'] = 'Cette permission autorise un utilisateur à&nbsp;:<ul class="normallist"><li>modifier les paramètres du forum, de la base de données et du thème</li><li>gérer les paquets</li><li>utiliser les outils de maintenance du forum et de la base de données</li><li>voir le Journal de Modération et d\'Erreurs.</li></ul> Utilisez cette permission avec précaution, elle est très puissante.';
$txt['permissionname_manage_boards'] = 'Gestion des sections et catégories';
$txt['permissionhelp_manage_boards'] = 'Cette permission autorise la création, la modification et la suppression des sections et catégories.';
$txt['permissionname_manage_attachments'] = 'Gestion des fichiers joints et avatars';
$txt['permissionhelp_manage_attachments'] = 'Cette permission autorise l\'accès au gestionnaire de fichiers joints, où tous les fichiers attachés et avatars sont listés et peuvent être supprimés.';
$txt['permissionname_manage_smileys'] = 'Gestion des smileys et icônes de messages';
$txt['permissionhelp_manage_smileys'] = 'Ceci permet l\'accès au gestionnaire des smileys et icônes de messages. Dans le centre de gestion des smileys, vous pouvez ajouter, modifier et supprimer des smileys et jeux de smileys. Si vous avez activé les icônes de messages personnalisées, vous pourrez les modifier et en ajouter avec cette permission.';
$txt['permissionname_edit_news'] = 'Modifier les nouvelles';
$txt['permissionhelp_edit_news'] = 'La fonction \'Nouvelles\' affiche une ligne d\'informations aléatoire sur chaque page. Pour l\'utiliser, activez la dans les paramètres du forum.';
$txt['permissionname_access_mod_center'] = 'Accès au Centre de Modération';
$txt['permissionhelp_access_mod_center'] = 'Avec cette permission, tous les membres de ce groupe peuvent accéder au Centre de Modération dès lors qu\'ils ont accès à des fonctionnalités de modération. Notez que cela ne donne pas de privilèges de modération.';

$txt['permissiongroup_member_admin'] = 'Administration des membres';
$txt['permissionname_moderate_forum'] = 'Gestion des membres du forum';
$txt['permissionhelp_moderate_forum'] = 'Cette permission inclut toutes les fonctions importantes de modération des membres&nbsp;:<ul class="normallist"><li>accès aux inscriptions</li><li>accès au panneau de gestion des membres</li><li>accès aux informations de profil étendu, ainsi qu\'à la traque des adresses IP et utilisateurs et au statut invisible</li><li>activation de comptes</li><li>réception des notifications d\'inscription et approbation des inscriptions</li><li>immunité contre le rejet des MP</li><li>plusieurs autres caractéristiques.</li></ul>';
$txt['permissionname_manage_membergroups'] = 'Gestion et assignation des groupes de membres';
$txt['permissionhelp_manage_membergroups'] = 'Cette permission permet à l\'utilisateur de modifier les groupes de membres et d\'assigner des membres à certains groupes.';
$txt['permissionname_manage_permissions'] = 'Gestion des permissions';
$txt['permissionhelp_manage_permissions'] = 'Cette permission permet à un utilisateur de modifier toutes les permissions d\'un groupe de membres, globalement ou pour des sections individuelles.';
$txt['permissionname_manage_bans'] = 'Gestion de la liste des bannissements';
$txt['permissionhelp_manage_bans'] = 'Cette permission autorise un utilisateur d\'ajouter ou d\'enlever des utilisateurs, adresses IP, hôtes et adresses e-mail de la liste des bannissements.  Elle permet aussi de voir et enlever des entrées d\'utilisateurs bannis qui tentent de se connecter au forum.';
$txt['permissionname_send_mail'] = 'Envoyer un e-mail du forum aux membres';
$txt['permissionhelp_send_mail'] = 'Envoi massif d\'un e-mail à tous les membres du forum ou juste quelques groupes de membres par e-mail ou message personnel (ce dernier nécessite la permission \'Envoyer un message personnel\'.)';
$txt['permissionname_issue_warning'] = 'Donner des avertissements aux membres';
$txt['permissionhelp_issue_warning'] = 'Donner un avertissement aux membres du forum et changer leur niveau d\'avertissement. Nécessite que le système d\'avertissement soit activé.';

$txt['permissiongroup_profile'] = 'Profils des membres';
$txt['permissionname_profile_view'] = 'Voir le sommaire du profil et les stats';
$txt['permissionhelp_profile_view'] = 'Cette permission autorise les utilisateurs cliquant sur un pseudonyme à voir le sommaire des paramètres de profil, quelques statistiques et tous les messages de ce membre.';
$txt['permissionname_profile_extra'] = 'Modifier les paramètres additionnels du profil';
$txt['permissionhelp_profile_extra'] = 'Les paramètre additionnels incluent les avatars, thème préféré, notifications et messages personnels.';
$txt['permissionname_profile_extra_own'] = 'Profil personnel';
$txt['permissionname_profile_extra_any'] = 'Tous les profils';
$txt['permissionname_profile_title'] = 'Modifier le texte personnel';
$txt['permissionhelp_profile_title'] = 'Le texte personnel; est affiché sur la page du sujet, sous le profil de chaque membre qui a un titre personnel.';
$txt['permissionname_profile_title_own'] = 'Profil personnalisé';
$txt['permissionname_profile_title_any'] = 'Tous les profils';
$txt['permissionname_profile_server_avatar'] = 'Sélectionner un avatar à partir du serveur';
$txt['permissionhelp_profile_server_avatar'] = 'Si vous l\'activez, ceci permettra à un utilisateur de Sélectionner un avatar à partir des collections installées sur le serveur.';
$txt['permissionname_profile_upload_avatar'] = 'Téléchargez un avatar sur le serveur';
$txt['permissionhelp_profile_upload_avatar'] = 'Cette permission permettra auc utilisateurs de télécharger leurs avatars personnels sur le serveur.';
$txt['permissionname_profile_remote_avatar'] = 'Choisir un avatar externe';
$txt['permissionhelp_profile_remote_avatar'] = 'Comme les avatars peuvent influencer négativement le temps de création de page, il est possible d\'interdire à certains groupes de membres à l\'utilisation d\'avatars de serveurs externes. ';

$txt['permissiongroup_profile_account'] = 'Member Accounts';
$txt['permissionname_profile_identity'] = 'Modifier les paramètres du compte';
$txt['permissionhelp_profile_identity'] = 'Les paramètres de compte sont les paramètres de base du profil, comme mot de passe, adresse e-mail, groupe de membres et langue préférée.';
$txt['permissionname_profile_identity_own'] = 'Profil personnel';
$txt['permissionname_profile_identity_any'] = 'Tous les profils';
$txt['permissionname_profile_displayed_name'] = 'Edit displayed name';
$txt['permissionhelp_profile_displayed_name'] = 'Allow the member to edit the displayed name field in their profile';
$txt['permissionname_profile_displayed_name_own'] = 'Own displayed name';
$txt['permissionname_profile_displayed_name_any'] = 'Any displayed name';
$txt['permissionname_profile_password'] = 'Change password';
$txt['permissionhelp_profile_password'] = 'Allow the member to change the password or the secret question fields';
$txt['permissionname_profile_password_own'] = 'Own profile';
$txt['permissionname_profile_password_any'] = 'Any profile';
$txt['permissionname_profile_remove'] = 'Effacer le compte';
$txt['permissionhelp_profile_remove'] = 'Cette permission autorise un membre à effacer son compte, quand elle est réglée sur \'Compte personnel\'.';
$txt['permissionname_profile_remove_own'] = 'Compte personnel';
$txt['permissionname_profile_remove_any'] = 'Tous les comptes';
$txt['permissionname_view_warning'] = 'View warning status';
$txt['permissionname_view_warning_own'] = 'Own account';
$txt['permissionname_view_warning_any'] = 'Any account';
$txt['permissionhelp_view_warning'] = 'Allows users to view their own warning status and history (\'Own account\') or that of any user (\'Any account\')';

$txt['permissionname_report_user'] = 'Report users\' profiles';
$txt['permissionhelp_report_user'] = 'This permission will allow members to report other users\' profiles to the admins to alert them of spam or other inappropriate content in the profile.';

$txt['permissiongroup_general_board'] = 'Général';
$txt['permissionname_moderate_board'] = 'Modérer la section';
$txt['permissionhelp_moderate_board'] = 'La permission de modérer une section ajoute quelques petites permissions qui font du modérateur un réel modérateur. Inclut réponse aux sujets bloqués, changer la date d\'expiration d\'un sondage et voir les résultats d\'un sondage.';

$txt['permissiongroup_topic'] = 'Sujets';
$txt['permissionname_post_new'] = 'Poster des nouveaux sujets';
$txt['permissionhelp_post_new'] = 'Cette permission autorise les utilisateurs à poster de nouveaux sujets. Elle n\'autorise pas à poster des réponses aux sujets.';
$txt['permissionname_merge_any'] = 'Fusionner un sujet';
$txt['permissionhelp_merge_any'] = 'Fusionner deux sujets ou plus en un seul. L\'ordre des messages dans le sujet final sera basé sur la date de création des messages. Un utilisateur ne peut fusionner les sujets que sur un forum où il est autorisé à fusionner. Pour fusionner plusieurs sujets à la fois, cet utilisateur doit activer les options de modération rapide dans son profil.';
$txt['permissionname_split_any'] = 'Séparer un sujet';
$txt['permissionhelp_split_any'] = 'Séparer un sujet en deux sujets distincts.';
$txt['permissionname_make_sticky'] = 'Épingler des sujets';
$txt['permissionhelp_make_sticky'] = 'Les sujets épinglés sont affichés en haut des sections. Ils sont utiles pour fournir des informations ou autres messages importants.';
$txt['permissionname_move'] = 'Déplacer un sujet';
$txt['permissionhelp_move'] = 'Déplacer un sujet depuis une section vers une autre. Les utilisateurs ne peuvent choisir comme destination que les sections où ils ont accès.';
$txt['permissionname_move_own'] = 'Sujets personnels';
$txt['permissionname_move_any'] = 'Tous les sujets';
$txt['permissionname_lock'] = 'Bloquer des sujets';
$txt['permissionhelp_lock'] = 'Cette permission autorise un utilisateur à bloquer un sujet. Cela empèche quiconque de répondre à ce sujet. Seuls les membres ayant la permission \'Modérer un Forum\' peuvent encore poster dans un sujet bloqué.';
$txt['permissionname_lock_own'] = 'Sujets personnels';
$txt['permissionname_lock_any'] = 'Tous les sujets';
$txt['permissionname_remove'] = 'Effacer des sujets';
$txt['permissionhelp_remove'] = 'Efface les sujets. Notez que cette permission ne permet pas d\'effacer des messages spécifiques dans le sujet&nbsp;!';
$txt['permissionname_remove_own'] = 'Sujets personnels';
$txt['permissionname_remove_any'] = 'Tous les sujets';
$txt['permissionname_post_reply'] = 'Répondre aux sujets';
$txt['permissionhelp_post_reply'] = 'Cette permission autorise à répondre aux sujets.';
$txt['permissionname_post_reply_own'] = 'Sujets personnels';
$txt['permissionname_post_reply_any'] = 'Tous les sujets';
$txt['permissionname_modify_replies'] = 'Modifier les réponses aux sujets personnels';
$txt['permissionhelp_modify_replies'] = 'Cette permission autorise le membre ayant démarré un sujet à modifier toutes les réponses à ce sujet.';
$txt['permissionname_delete_replies'] = 'Effacer les réponses aux sujets personnels';
$txt['permissionhelp_delete_replies'] = 'Cette permission autorise le membre ayant démarré un sujet à effacer toutes les réponses à ce sujet.';
$txt['permissionname_announce_topic'] = 'Annoncer un sujet';
$txt['permissionhelp_announce_topic'] = 'Ceci permet d\'envoyer un e-mail d\'annonce à propos d\'un sujet à tous les membres ou à quelques groupes de membres seulement.';

$txt['permissiongroup_post'] = 'Messages';
$txt['permissionname_delete'] = 'Effacer les messages';
$txt['permissionhelp_delete'] = 'Retire les messages. Cela ne permet pas au membre d\'effacer le premier message d\'un sujet.';
$txt['permissionname_delete_own'] = 'Messages personnels';
$txt['permissionname_delete_any'] = 'Tous les messages';
$txt['permissionname_modify'] = 'Modifier les messages';
$txt['permissionhelp_modify'] = 'Permet de modifier le contenu des messages.';
$txt['permissionname_modify_own'] = 'Messages personnels';
$txt['permissionname_modify_any'] = 'Tous les messages';
$txt['permissionname_report_any'] = 'Signaler les messages aux modérateurs';
$txt['permissionhelp_report_any'] = 'Cette permission ajoute un lien à chaque message, autorisant à rapporter un message suspect à un modérateur. Tous les modérateurs de cette recevront un e-mail avec un lien vers le message rapporté et une description du problème (comme indiqué par l\'utilisateur rapportant).';

$txt['permissiongroup_likes'] = 'Likes';
$txt['permissionname_likes_view'] = 'View likes';
$txt['permissionhelp_likes_view'] = 'This permission allows an user to view any likes. Without this permission, the user will only see the likes she/he has made.';
$txt['permissionname_likes_like'] = 'Can like any content';
$txt['permissionhelp_likes_like'] = 'This permission allows an user to like any content. Users aren\'t allowed to like their own content.';

$txt['permissiongroup_mentions'] = 'Mentions';
$txt['permissionname_mention'] = 'Mention others via @name';
$txt['permissionhelp_mention'] = 'This permission allows a user to mention other users by @name. For example, user Jack could be mentioned using @Jack by a user when given this permission.';

$txt['permissiongroup_poll'] = 'Sondages';
$txt['permissionname_poll_view'] = 'Voir les sondages';
$txt['permissionhelp_poll_view'] = 'Cette permission autorise un utilisateur à voir un sondage. Sans elle, il ne verra que le sujet.';
$txt['permissionname_poll_vote'] = 'Voter dans les sondages';
$txt['permissionhelp_poll_vote'] = 'Cette permission autorise un membre (inscrit) à voter. Invités exclus.';
$txt['permissionname_poll_post'] = 'Poster des sondages';
$txt['permissionhelp_poll_post'] = 'Cette permission autorise un membre à poster un nouveau sondage.';
$txt['permissionname_poll_add'] = 'Ajouter un sondage au sujet';
$txt['permissionhelp_poll_add'] = 'Autorise un utilisateur à ajouter un sondage après la création du sujet. Cette permission nécessite les droits suffisants pour modifier le premier message du sujet.';
$txt['permissionname_poll_add_own'] = 'Sujets personnels';
$txt['permissionname_poll_add_any'] = 'Tous les sujets';
$txt['permissionname_poll_edit'] = 'Modifier les sondages';
$txt['permissionhelp_poll_edit'] = 'Cette permission autorise l\'utilisateur à modifier les options du sondage et la remise à zéro. Pour modifier le nombre de votes maximum et la date de fin, la permission de \'Modérer une section\' est requise.';
$txt['permissionname_poll_edit_own'] = 'Sondage personnel';
$txt['permissionname_poll_edit_any'] = 'Tous les sondages';
$txt['permissionname_poll_lock'] = 'Verrouiller les sondages';
$txt['permissionhelp_poll_lock'] = 'Le bloquage des sondages bloque l\'arrivée de nouveaux votes.';
$txt['permissionname_poll_lock_own'] = 'Sondage personnel';
$txt['permissionname_poll_lock_any'] = 'Tous les sondages';
$txt['permissionname_poll_remove'] = 'Effacer les sondages';
$txt['permissionhelp_poll_remove'] = 'Cette permission autorise le retrait des sondages.';
$txt['permissionname_poll_remove_own'] = 'Sondage personnel';
$txt['permissionname_poll_remove_any'] = 'Tous les sondages';

$txt['permissionname_post_draft'] = 'Sauvegarder des brouillons des nouveaux messages';
$txt['permissionhelp_post_draft'] = 'Cette permission autorise les utilisateurs à sauvegarder des brouillons de leurs messages ce qui leur permet de les compléter plus tard.';
$txt['permissionname_pm_draft'] = 'Sauvegarder des brouillons des messages personnels';
$txt['permissionhelp_pm_draft'] = 'Cette permission autorise les utilisateurs à sauvegarder des brouillons de leurs messages personnels ce qui leur permet de les compléter plus tard.';

$txt['permissiongroup_approval'] = 'Modération des messages';
$txt['permissionname_approve_posts'] = 'Approuver les éléments en attente de modération';
$txt['permissionhelp_approve_posts'] = 'Cette permission permet à un utilisateur d\'approuver tous les éléments non approuvés sur une section.';
$txt['permissionname_post_unapproved_replies'] = 'Poster des réponses à faire approuver';
$txt['permissionhelp_post_unapproved_replies'] = 'Cette permission permet à un utilisateur de poster des réponses sur un sujet, mais qui ne seront pas affichées avant l\'approbation d\'un modérateur si la prémodération est activée.';
$txt['permissionname_post_unapproved_replies_own'] = 'Sujets personnels';
$txt['permissionname_post_unapproved_replies_any'] = 'Tous les sujets';
$txt['permissionname_post_unapproved_topics'] = 'Poster des sujets à faire approuver';
$txt['permissionhelp_post_unapproved_topics'] = 'Cette permission permet à un utilisateur de poster de nouveaux sujets, mais qui ne seront pas affichés avant l\'approbation d\'un modérateur si la prémodération est activée.';
$txt['permissionname_post_unapproved_attachments'] = 'Poster des fichiers joints à faire approuver';
$txt['permissionhelp_post_unapproved_attachments'] = 'Cette permission permet à un utilisateur de joindre des fichiers à ses messages. Les fichiers joints nécessiteront une approbation avant d\'être disponible pour les autres utilisateurs.';

$txt['permissiongroup_attachment'] = 'Fichiers joints';
$txt['permissionname_view_attachments'] = 'Voir les fichiers joints';
$txt['permissionhelp_view_attachments'] = 'Les fichiers joints sont des pièces attachées aux messages postés. Cette fonction peut être activée et configurée dans \'Fichiers joints et avatars\'. Comme les fichiers joints ne sont pas directement accessibles, vous pouvez éviter aux membres non autorisés de les télécharger.';
$txt['permissionname_post_attachment'] = 'Poster des fichiers joints';
$txt['permissionhelp_post_attachment'] = 'Les fichiers joints sont des pièces attachées aux messages postés. Un message peut en contenir plusieurs.';

$txt['permissionicon'] = '';

$txt['permission_settings_title'] = 'Paramètres des permissions';
$txt['groups_manage_permissions'] = 'Groupes de membres autorisés à gérer les permissions';
$txt['permission_settings_submit'] = 'Enregistrer';
$txt['permission_settings_enable_deny'] = 'Activer l\'option pour interdire des permissions';
// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['permission_disable_deny_warning'] = 'Désactiver cette option va mettre à jour tous les permissions interdites \\\'Interdite\\\' vers le statut \\\'Refusée\\\'.';
$txt['permission_by_board_desc'] = 'Ici pous pouvez attribuer un profil de permissions à une section. Vous pouvez créer de nouveaux profils de permissions dans le menu &quot;Modifier les Profils&quot;.';
$txt['permission_settings_desc'] = 'Ici vous pouvez régler qui a la permission de changer les permissions, de même que la complexité que devrait avoir le système de permissions.';
$txt['permission_settings_enable_postgroups'] = 'Activer les permissions pour les groupes posteurs';
// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['permission_disable_postgroups_warning'] = 'Désactiver ce paramètre va enlever les permissions présentement attribuées aux groupes posteurs.';

$txt['permissions_post_moderation_desc'] = 'D\'ici, vous pouvez facilement changer quels groupes voient leurs messages modérés pour un profil de permissions spécifique.';
$txt['permissions_post_moderation_enable'] = 'Enable Post Moderation';
$txt['permissions_post_moderation_deny_note'] = 'Notez que si vous avez activé les permissions avancées, vous ne pourrez pas appliquer la permission &quot;refuser&quot; à partir de cette page. Veuillez modifier les permissions directement si vous voulez appliquer un refus de permission.';
$txt['permissions_post_moderation_select'] = 'Choisissez le Profil&nbsp;';
$txt['permissions_post_moderation_new_topics'] = 'Nouveaux sujets';
$txt['permissions_post_moderation_replies_own'] = 'Réponses sur ses fils';
$txt['permissions_post_moderation_replies_any'] = 'Réponses partout';
$txt['permissions_post_moderation_attachments'] = 'Fichiers joints';
$txt['permissions_post_moderation_legend'] = 'Légende&nbsp;';
$txt['permissions_post_moderation_allow'] = 'Peut créer/envoyer';
$txt['permissions_post_moderation_moderate'] = 'Peut créer/envoyer mais nécessite l\'approbation d\'un modérateur';
$txt['permissions_post_moderation_disallow'] = 'Ne peut pas créer/envoyer';
$txt['permissions_post_moderation_group'] = 'Groupe';

$txt['auto_approve_topics'] = 'Poster des sujets auto-approuvés';
$txt['auto_approve_replies'] = 'Poster des réponses auto-approuvées aux sujets';
$txt['auto_approve_attachments'] = 'Poster des fichiers joints auto-approuvés';

?>