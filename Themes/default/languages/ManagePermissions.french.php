<?php
// Version: 2.1 Beta 2; ManagePermissions

$txt['permissions_title'] = 'Gestion des Permissions';
$txt['permissions_modify'] = 'Modifier';
$txt['permissions_view'] = 'Voir';
$txt['permissions_allowed'] = 'Autoris�es';
$txt['permissions_denied'] = 'Refus�es';
$txt['permission_cannot_edit'] = '<strong>Note</strong>&nbsp;: vous ne pouvez pas modifier ce profil de permission car c\'est un profil-type pr�d�fini par SMF. Si vous voulez changer les permissions de ce profil, vous devez d\'abord cr�er une copie du profil en cliquant <a href="%1$s">ici</a>.';

$txt['permissions_for_profile'] = 'Permissions pour le Profil&nbsp;';
$txt['permissions_boards_desc'] = 'La liste ci-dessous montre le jeu de permissions qui a �t� assign� � chaque section de votre forum. Vous pouvez modifier les permissions de profil assign�es en cliquant sur le nom de la section, ou en s�lectionnant &quot;Tout modifier&quot; en bas de la page. Pour modifier le profil lui-m�me, cliquez simplement sur le nom du profil.';
$txt['permissions_board_all'] = 'Tout modifier';
$txt['permission_profile'] = 'Profil de Permission';
$txt['permission_profile_desc'] = 'Quel <a href="%1$s">jeu de permissions</a> la section doit utiliser.';
$txt['permission_profile_inherit'] = 'H�riter de la section parente';

$txt['permissions_profile'] = 'Profil';
$txt['permissions_profiles_desc'] = 'Les profils de permissions sont assign�s � des sections individuelles pour vous permettre de g�rer facilement vos param�tres de s�curit�. D\'ici, vous pouvez cr�er, modifier et supprimer des profils de permissions.';
$txt['permissions_profiles_change_for_board'] = 'Modifier le profil de permissions pour&nbsp;: &quot;%1$s&quot;';
$txt['permissions_profile_default'] = 'Par d�faut';
$txt['permissions_profile_no_polls'] = 'Pas de Sondages';
$txt['permissions_profile_reply_only'] = 'R�ponses uniquement';
$txt['permissions_profile_read_only'] = 'Lecture uniquement';

$txt['permissions_profile_rename'] = 'Renommer';
$txt['permissions_profile_edit'] = 'Modifier les Profils';
$txt['permissions_profile_new'] = 'Nouveau Profil';
$txt['permissions_profile_new_create'] = 'Cr�er';
$txt['permissions_profile_name'] = 'Nom du Profil';
$txt['permissions_profile_used_by'] = 'Utilis� par';
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
$txt['permissions_profile_copy_from'] = 'Copier les permissions � partir de';

$txt['permissions_includes_inherited'] = 'Groupes h�rit�s';

$txt['permissions_all'] = 'tous';
$txt['permissions_none'] = 'aucun';
$txt['permissions_set_permissions'] = 'Choisir les permissions';

$txt['permissions_advanced_options'] = 'Options avanc�es';
$txt['permissions_with_selection'] = 'Pour la s�lection';
$txt['permissions_apply_pre_defined'] = 'Appliquer le profil de permissions pr�d�fini';
$txt['permissions_select_pre_defined'] = 'Choisir un profil pr�d�fini';
$txt['permissions_copy_from_board'] = 'Copier les permissions de cette section';
$txt['permissions_select_board'] = 'S�lectionnez une section';
$txt['permissions_like_group'] = 'Donner les permissions comme ce groupe';
$txt['permissions_select_membergroup'] = 'Choisir un groupe de membres';
$txt['permissions_add'] = 'Ajouter une permission';
$txt['permissions_remove'] = 'Refuser une permission';
$txt['permissions_deny'] = 'Interdire la permission';
$txt['permissions_select_permission'] = 'Choisir une permission';

// All of the following block of strings should not use entities, instead use \\" for &quot; etc.
$txt['permissions_only_one_option'] = 'Vous ne pouvez choisir qu\'une seule action pour modifier les permissions';
$txt['permissions_no_action'] = 'Aucune action choisie';
$txt['permissions_deny_dangerous'] = 'Vous �tes sur le point d\'interdire une ou plusieurs permissions.\\nCeci peut �tre dangereux et causer des r�sultats inattendus si vous ne vous �tes pas assur� que personne n\'est \\"accidentellement\\" dans le ou les groupes auxquels vous interdisez les permissions.\\n\\n�tes-vous s�r de vouloir continuer?';

$txt['permissions_modify_group'] = 'Modifier un Groupe';
$txt['permissions_general'] = 'Permissions G�n�rales';
$txt['permissions_board'] = 'Permissions par D�faut pour les Sections';
$txt['permissions_board_desc'] = '<strong>Note</strong>&nbsp;: changer ces permissions affectera toutes les sections utilisant actuellement le profil de permissions &quot;par d�faut&quot;. Les sections n\'utilisant pas le profil &quot;par d�faut&quot; ne seront pas affect�es par les changements de cette page.';
$txt['permissions_commit'] = 'Sauver les Changements';
$txt['permissions_on'] = 'du profil';
$txt['permissions_local_for'] = 'Permissions pour le groupe';
$txt['permissions_option_own'] = 'Own';
$txt['permissions_option_any'] = 'Any';
$txt['permissions_option_on'] = 'A';
$txt['permissions_option_off'] = 'R';
$txt['permissions_option_deny'] = 'I';
$txt['permissions_option_desc'] = 'Pour chaque groupe, vous pouvez choisir soit \'Autoriser\' (A), \'Refuser\' (R), ou <span style="color: red;">\'Interdire\' (I)</span>.<br /><br />Rappelez-vous que si vous interdisez une permission, tous les membres - qu\'ils soient mod�rateurs ou autres - pr�sents dans ce groupe se verront refuser la permission aussi.<br />Pour cette raison, vous devriez interdire avec pr�caution, et seulement lorsque <strong>n�cessaire</strong>. \'Refuser\', de son c�t�, n\'interdit l\'acc�s que si rien d\'autre ne vient le contredire.';

$txt['permissiongroup_general'] = 'G�n�ral';
$txt['permissionname_view_stats'] = 'Voir les stats du forum';
$txt['permissionhelp_view_stats'] = 'Les stats du forum sont une page r�sumant toutes les statistiques du forum&nbsp;: nombre de membres, nombre  de messages par jour et plusieurs Top 10. Autoriser cette permission ajoute un lien en bas de l\'accueil du forum (\'[+ de Stats]\').';
$txt['permissionname_view_mlist'] = 'Voir la liste des membres et groupes';
$txt['permissionhelp_view_mlist'] = 'La liste des membres affiche tous les membres qui se sont inscrits sur votre forum. La liste peut �tre class�e et scrut�e. La liste des membres est accessible depuis l\'accueil du forum et la page de stats, en cliquant sur le nombre de membres. Cela s\'applique �galement � la page des groupes, une mini-liste des membres pr�sents dans un groupe sp�cifique.';
$txt['permissionname_who_view'] = 'Voir la page <em>Qui est en ligne&nbsp;?</em>';
$txt['permissionhelp_who_view'] = '<em>Qui est en ligne&nbsp;?</em> affiche tous les membres qui sont actuellement connect�s et ce qu\'ils font en ce moment. Cette permission ne fonctionnera que si vous l\'avez aussi valid�e dans \'R�glages et options\'. Vous pouvez acc�der � la page <em>Qui est en ligne&nbsp;?</em> en cliquant sur le lien dans la section <em>Membres en ligne</em> sur l\'accueil du forum. M�me si ce n\'est pas permis, les membres pourront tout de m�me voir qui est en ligne, mais ne pourront pas voir o� ils sont.';
$txt['permissionname_search_posts'] = 'Rechercher des messages ou des sujets';
$txt['permissionhelp_search_posts'] = 'La permission de recherche autorise l\'utilisateur � rechercher dans toutes les sections auxquels il peut acc�der. Quand la permission de recherche est activ�e, un bouton \'Recherche\' sera ajout� � la barre de menu principal du forum.';

$txt['permissiongroup_pm'] = 'Messagerie personnelle';
$txt['permissionname_pm_read'] = 'Lire les messages personnels';
$txt['permissionhelp_pm_read'] = 'Cette permission autorise les membres � acc�der � la messagerie personnelle et � lire leurs messages personnels. Sans cette permission, un membre ne peut pas envoyer de messages personnels.';
$txt['permissionname_pm_send'] = 'Envoyer des messages personnels';
$txt['permissionhelp_pm_send'] = 'Envoyer des messages personnels � d\'autres membres inscrits. N�cessite la permission \'Lire des messages personnels\'.';

$txt['permissiongroup_calendar'] = 'Calendrier';
$txt['permissionname_calendar_view'] = 'Voir le calendrier';
$txt['permissionhelp_calendar_view'] = 'Le calendrier affiche pour chaque mois les anniversaires, les �v�nements et les jours f�ri�s. Cette permission autorise l\'acc�s � ce calendrier. Quand cette permission est valid�e, un bouton est ajout� � la barre de menu principal et une liste est affich�e au bas de l\'acceuil du forum avec les anniversaires courants et � venir, �v�nements et f�tes. Le calendrier doit �tre activ� depuis \'Configuration - Options principales\'.';
$txt['permissionname_calendar_post'] = 'Cr�er des �v�nements dans le calendrier';
$txt['permissionhelp_calendar_post'] = 'Un �v�nement est un sujet li� � une certaine date ou plage de dates. Vous pouvez cr�er des �v�nements depuis le calendrier. Un �v�nement ne peut �tre cr�� que par un utilisateur qui a la permission de poster des nouveaux sujets.';
$txt['permissionname_calendar_edit'] = 'Modifier les �v�nements du calendrier';
$txt['permissionhelp_calendar_edit'] = 'Un �v�nement est un sujet li� � une certaine date ou plage de dates. Il peut �tre modifi� en cliquant l\'ast�risque rouge (<span style="color: red;">*</span>) � cot� de l\'�v�nement sur la page du calendrier. Pour pouvoir modifier un �v�nement, l\'utilisateur doit avoir les permissions suffisantes pour modifier le premier message du sujet li� � cet �v�nement.';
$txt['permissionname_calendar_edit_own'] = '�v�nements personnels';
$txt['permissionname_calendar_edit_any'] = 'Tous les �v�nements';

$txt['permissiongroup_maintenance'] = 'Administration du forum';
$txt['permissionname_admin_forum'] = 'Administrer le forum et la base de donn�es';
$txt['permissionhelp_admin_forum'] = 'Cette permission autorise un utilisateur �&nbsp;:<ul class="normallist"><li>modifier les param�tres du forum, de la base de donn�es et du th�me</li><li>g�rer les paquets</li><li>utiliser les outils de maintenance du forum et de la base de donn�es</li><li>voir le Journal de Mod�ration et d\'Erreurs.</li></ul> Utilisez cette permission avec pr�caution, elle est tr�s puissante.';
$txt['permissionname_manage_boards'] = 'Gestion des sections et cat�gories';
$txt['permissionhelp_manage_boards'] = 'Cette permission autorise la cr�ation, la modification et la suppression des sections et cat�gories.';
$txt['permissionname_manage_attachments'] = 'Gestion des fichiers joints et avatars';
$txt['permissionhelp_manage_attachments'] = 'Cette permission autorise l\'acc�s au gestionnaire de fichiers joints, o� tous les fichiers attach�s et avatars sont list�s et peuvent �tre supprim�s.';
$txt['permissionname_manage_smileys'] = 'Gestion des smileys et ic�nes de messages';
$txt['permissionhelp_manage_smileys'] = 'Ceci permet l\'acc�s au gestionnaire des smileys et ic�nes de messages. Dans le centre de gestion des smileys, vous pouvez ajouter, modifier et supprimer des smileys et jeux de smileys. Si vous avez activ� les ic�nes de messages personnalis�es, vous pourrez les modifier et en ajouter avec cette permission.';
$txt['permissionname_edit_news'] = 'Modifier les nouvelles';
$txt['permissionhelp_edit_news'] = 'La fonction \'Nouvelles\' affiche une ligne d\'informations al�atoire sur chaque page. Pour l\'utiliser, activez la dans les param�tres du forum.';
$txt['permissionname_access_mod_center'] = 'Acc�s au Centre de Mod�ration';
$txt['permissionhelp_access_mod_center'] = 'Avec cette permission, tous les membres de ce groupe peuvent acc�der au Centre de Mod�ration d�s lors qu\'ils ont acc�s � des fonctionnalit�s de mod�ration. Notez que cela ne donne pas de privil�ges de mod�ration.';

$txt['permissiongroup_member_admin'] = 'Administration des membres';
$txt['permissionname_moderate_forum'] = 'Gestion des membres du forum';
$txt['permissionhelp_moderate_forum'] = 'Cette permission inclut toutes les fonctions importantes de mod�ration des membres&nbsp;:<ul class="normallist"><li>acc�s aux inscriptions</li><li>acc�s au panneau de gestion des membres</li><li>acc�s aux informations de profil �tendu, ainsi qu\'� la traque des adresses IP et utilisateurs et au statut invisible</li><li>activation de comptes</li><li>r�ception des notifications d\'inscription et approbation des inscriptions</li><li>immunit� contre le rejet des MP</li><li>plusieurs autres caract�ristiques.</li></ul>';
$txt['permissionname_manage_membergroups'] = 'Gestion et assignation des groupes de membres';
$txt['permissionhelp_manage_membergroups'] = 'Cette permission permet � l\'utilisateur de modifier les groupes de membres et d\'assigner des membres � certains groupes.';
$txt['permissionname_manage_permissions'] = 'Gestion des permissions';
$txt['permissionhelp_manage_permissions'] = 'Cette permission permet � un utilisateur de modifier toutes les permissions d\'un groupe de membres, globalement ou pour des sections individuelles.';
$txt['permissionname_manage_bans'] = 'Gestion de la liste des bannissements';
$txt['permissionhelp_manage_bans'] = 'Cette permission autorise un utilisateur d\'ajouter ou d\'enlever des utilisateurs, adresses IP, h�tes et adresses e-mail de la liste des bannissements.  Elle permet aussi de voir et enlever des entr�es d\'utilisateurs bannis qui tentent de se connecter au forum.';
$txt['permissionname_send_mail'] = 'Envoyer un e-mail du forum aux membres';
$txt['permissionhelp_send_mail'] = 'Envoi massif d\'un e-mail � tous les membres du forum ou juste quelques groupes de membres par e-mail ou message personnel (ce dernier n�cessite la permission \'Envoyer un message personnel\'.)';
$txt['permissionname_issue_warning'] = 'Donner des avertissements aux membres';
$txt['permissionhelp_issue_warning'] = 'Donner un avertissement aux membres du forum et changer leur niveau d\'avertissement. N�cessite que le syst�me d\'avertissement soit activ�.';

$txt['permissiongroup_profile'] = 'Profils des membres';
$txt['permissionname_profile_view'] = 'Voir le sommaire du profil et les stats';
$txt['permissionhelp_profile_view'] = 'Cette permission autorise les utilisateurs cliquant sur un pseudonyme � voir le sommaire des param�tres de profil, quelques statistiques et tous les messages de ce membre.';
$txt['permissionname_profile_extra'] = 'Modifier les param�tres additionnels du profil';
$txt['permissionhelp_profile_extra'] = 'Les param�tre additionnels incluent les avatars, th�me pr�f�r�, notifications et messages personnels.';
$txt['permissionname_profile_extra_own'] = 'Profil personnel';
$txt['permissionname_profile_extra_any'] = 'Tous les profils';
$txt['permissionname_profile_title'] = 'Modifier le texte personnel';
$txt['permissionhelp_profile_title'] = 'Le texte personnel; est affich� sur la page du sujet, sous le profil de chaque membre qui a un titre personnel.';
$txt['permissionname_profile_title_own'] = 'Profil personnalis�';
$txt['permissionname_profile_title_any'] = 'Tous les profils';
$txt['permissionname_profile_server_avatar'] = 'S�lectionner un avatar � partir du serveur';
$txt['permissionhelp_profile_server_avatar'] = 'Si vous l\'activez, ceci permettra � un utilisateur de S�lectionner un avatar � partir des collections install�es sur le serveur.';
$txt['permissionname_profile_upload_avatar'] = 'T�l�chargez un avatar sur le serveur';
$txt['permissionhelp_profile_upload_avatar'] = 'Cette permission permettra auc utilisateurs de t�l�charger leurs avatars personnels sur le serveur.';
$txt['permissionname_profile_remote_avatar'] = 'Choisir un avatar externe';
$txt['permissionhelp_profile_remote_avatar'] = 'Comme les avatars peuvent influencer n�gativement le temps de cr�ation de page, il est possible d\'interdire � certains groupes de membres � l\'utilisation d\'avatars de serveurs externes. ';

$txt['permissiongroup_profile_account'] = 'Member Accounts';
$txt['permissionname_profile_identity'] = 'Modifier les param�tres du compte';
$txt['permissionhelp_profile_identity'] = 'Les param�tres de compte sont les param�tres de base du profil, comme mot de passe, adresse e-mail, groupe de membres et langue pr�f�r�e.';
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
$txt['permissionhelp_profile_remove'] = 'Cette permission autorise un membre � effacer son compte, quand elle est r�gl�e sur \'Compte personnel\'.';
$txt['permissionname_profile_remove_own'] = 'Compte personnel';
$txt['permissionname_profile_remove_any'] = 'Tous les comptes';
$txt['permissionname_view_warning'] = 'View warning status';
$txt['permissionname_view_warning_own'] = 'Own account';
$txt['permissionname_view_warning_any'] = 'Any account';
$txt['permissionhelp_view_warning'] = 'Allows users to view their own warning status and history (\'Own account\') or that of any user (\'Any account\')';

$txt['permissionname_report_user'] = 'Report users\' profiles';
$txt['permissionhelp_report_user'] = 'This permission will allow members to report other users\' profiles to the admins to alert them of spam or other inappropriate content in the profile.';

$txt['permissiongroup_general_board'] = 'G�n�ral';
$txt['permissionname_moderate_board'] = 'Mod�rer la section';
$txt['permissionhelp_moderate_board'] = 'La permission de mod�rer une section ajoute quelques petites permissions qui font du mod�rateur un r�el mod�rateur. Inclut r�ponse aux sujets bloqu�s, changer la date d\'expiration d\'un sondage et voir les r�sultats d\'un sondage.';

$txt['permissiongroup_topic'] = 'Sujets';
$txt['permissionname_post_new'] = 'Poster des nouveaux sujets';
$txt['permissionhelp_post_new'] = 'Cette permission autorise les utilisateurs � poster de nouveaux sujets. Elle n\'autorise pas � poster des r�ponses aux sujets.';
$txt['permissionname_merge_any'] = 'Fusionner un sujet';
$txt['permissionhelp_merge_any'] = 'Fusionner deux sujets ou plus en un seul. L\'ordre des messages dans le sujet final sera bas� sur la date de cr�ation des messages. Un utilisateur ne peut fusionner les sujets que sur un forum o� il est autoris� � fusionner. Pour fusionner plusieurs sujets � la fois, cet utilisateur doit activer les options de mod�ration rapide dans son profil.';
$txt['permissionname_split_any'] = 'S�parer un sujet';
$txt['permissionhelp_split_any'] = 'S�parer un sujet en deux sujets distincts.';
$txt['permissionname_make_sticky'] = '�pingler des sujets';
$txt['permissionhelp_make_sticky'] = 'Les sujets �pingl�s sont affich�s en haut des sections. Ils sont utiles pour fournir des informations ou autres messages importants.';
$txt['permissionname_move'] = 'D�placer un sujet';
$txt['permissionhelp_move'] = 'D�placer un sujet depuis une section vers une autre. Les utilisateurs ne peuvent choisir comme destination que les sections o� ils ont acc�s.';
$txt['permissionname_move_own'] = 'Sujets personnels';
$txt['permissionname_move_any'] = 'Tous les sujets';
$txt['permissionname_lock'] = 'Bloquer des sujets';
$txt['permissionhelp_lock'] = 'Cette permission autorise un utilisateur � bloquer un sujet. Cela emp�che quiconque de r�pondre � ce sujet. Seuls les membres ayant la permission \'Mod�rer un Forum\' peuvent encore poster dans un sujet bloqu�.';
$txt['permissionname_lock_own'] = 'Sujets personnels';
$txt['permissionname_lock_any'] = 'Tous les sujets';
$txt['permissionname_remove'] = 'Effacer des sujets';
$txt['permissionhelp_remove'] = 'Efface les sujets. Notez que cette permission ne permet pas d\'effacer des messages sp�cifiques dans le sujet&nbsp;!';
$txt['permissionname_remove_own'] = 'Sujets personnels';
$txt['permissionname_remove_any'] = 'Tous les sujets';
$txt['permissionname_post_reply'] = 'R�pondre aux sujets';
$txt['permissionhelp_post_reply'] = 'Cette permission autorise � r�pondre aux sujets.';
$txt['permissionname_post_reply_own'] = 'Sujets personnels';
$txt['permissionname_post_reply_any'] = 'Tous les sujets';
$txt['permissionname_modify_replies'] = 'Modifier les r�ponses aux sujets personnels';
$txt['permissionhelp_modify_replies'] = 'Cette permission autorise le membre ayant d�marr� un sujet � modifier toutes les r�ponses � ce sujet.';
$txt['permissionname_delete_replies'] = 'Effacer les r�ponses aux sujets personnels';
$txt['permissionhelp_delete_replies'] = 'Cette permission autorise le membre ayant d�marr� un sujet � effacer toutes les r�ponses � ce sujet.';
$txt['permissionname_announce_topic'] = 'Annoncer un sujet';
$txt['permissionhelp_announce_topic'] = 'Ceci permet d\'envoyer un e-mail d\'annonce � propos d\'un sujet � tous les membres ou � quelques groupes de membres seulement.';

$txt['permissiongroup_post'] = 'Messages';
$txt['permissionname_delete'] = 'Effacer les messages';
$txt['permissionhelp_delete'] = 'Retire les messages. Cela ne permet pas au membre d\'effacer le premier message d\'un sujet.';
$txt['permissionname_delete_own'] = 'Messages personnels';
$txt['permissionname_delete_any'] = 'Tous les messages';
$txt['permissionname_modify'] = 'Modifier les messages';
$txt['permissionhelp_modify'] = 'Permet de modifier le contenu des messages.';
$txt['permissionname_modify_own'] = 'Messages personnels';
$txt['permissionname_modify_any'] = 'Tous les messages';
$txt['permissionname_report_any'] = 'Signaler les messages aux mod�rateurs';
$txt['permissionhelp_report_any'] = 'Cette permission ajoute un lien � chaque message, autorisant � rapporter un message suspect � un mod�rateur. Tous les mod�rateurs de cette recevront un e-mail avec un lien vers le message rapport� et une description du probl�me (comme indiqu� par l\'utilisateur rapportant).';

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
$txt['permissionhelp_poll_view'] = 'Cette permission autorise un utilisateur � voir un sondage. Sans elle, il ne verra que le sujet.';
$txt['permissionname_poll_vote'] = 'Voter dans les sondages';
$txt['permissionhelp_poll_vote'] = 'Cette permission autorise un membre (inscrit) � voter. Invit�s exclus.';
$txt['permissionname_poll_post'] = 'Poster des sondages';
$txt['permissionhelp_poll_post'] = 'Cette permission autorise un membre � poster un nouveau sondage.';
$txt['permissionname_poll_add'] = 'Ajouter un sondage au sujet';
$txt['permissionhelp_poll_add'] = 'Autorise un utilisateur � ajouter un sondage apr�s la cr�ation du sujet. Cette permission n�cessite les droits suffisants pour modifier le premier message du sujet.';
$txt['permissionname_poll_add_own'] = 'Sujets personnels';
$txt['permissionname_poll_add_any'] = 'Tous les sujets';
$txt['permissionname_poll_edit'] = 'Modifier les sondages';
$txt['permissionhelp_poll_edit'] = 'Cette permission autorise l\'utilisateur � modifier les options du sondage et la remise � z�ro. Pour modifier le nombre de votes maximum et la date de fin, la permission de \'Mod�rer une section\' est requise.';
$txt['permissionname_poll_edit_own'] = 'Sondage personnel';
$txt['permissionname_poll_edit_any'] = 'Tous les sondages';
$txt['permissionname_poll_lock'] = 'Verrouiller les sondages';
$txt['permissionhelp_poll_lock'] = 'Le bloquage des sondages bloque l\'arriv�e de nouveaux votes.';
$txt['permissionname_poll_lock_own'] = 'Sondage personnel';
$txt['permissionname_poll_lock_any'] = 'Tous les sondages';
$txt['permissionname_poll_remove'] = 'Effacer les sondages';
$txt['permissionhelp_poll_remove'] = 'Cette permission autorise le retrait des sondages.';
$txt['permissionname_poll_remove_own'] = 'Sondage personnel';
$txt['permissionname_poll_remove_any'] = 'Tous les sondages';

$txt['permissionname_post_draft'] = 'Sauvegarder des brouillons des nouveaux messages';
$txt['permissionhelp_post_draft'] = 'Cette permission autorise les utilisateurs � sauvegarder des brouillons de leurs messages ce qui leur permet de les compl�ter plus tard.';
$txt['permissionname_pm_draft'] = 'Sauvegarder des brouillons des messages personnels';
$txt['permissionhelp_pm_draft'] = 'Cette permission autorise les utilisateurs � sauvegarder des brouillons de leurs messages personnels ce qui leur permet de les compl�ter plus tard.';

$txt['permissiongroup_approval'] = 'Mod�ration des messages';
$txt['permissionname_approve_posts'] = 'Approuver les �l�ments en attente de mod�ration';
$txt['permissionhelp_approve_posts'] = 'Cette permission permet � un utilisateur d\'approuver tous les �l�ments non approuv�s sur une section.';
$txt['permissionname_post_unapproved_replies'] = 'Poster des r�ponses � faire approuver';
$txt['permissionhelp_post_unapproved_replies'] = 'Cette permission permet � un utilisateur de poster des r�ponses sur un sujet, mais qui ne seront pas affich�es avant l\'approbation d\'un mod�rateur si la pr�mod�ration est activ�e.';
$txt['permissionname_post_unapproved_replies_own'] = 'Sujets personnels';
$txt['permissionname_post_unapproved_replies_any'] = 'Tous les sujets';
$txt['permissionname_post_unapproved_topics'] = 'Poster des sujets � faire approuver';
$txt['permissionhelp_post_unapproved_topics'] = 'Cette permission permet � un utilisateur de poster de nouveaux sujets, mais qui ne seront pas affich�s avant l\'approbation d\'un mod�rateur si la pr�mod�ration est activ�e.';
$txt['permissionname_post_unapproved_attachments'] = 'Poster des fichiers joints � faire approuver';
$txt['permissionhelp_post_unapproved_attachments'] = 'Cette permission permet � un utilisateur de joindre des fichiers � ses messages. Les fichiers joints n�cessiteront une approbation avant d\'�tre disponible pour les autres utilisateurs.';

$txt['permissiongroup_attachment'] = 'Fichiers joints';
$txt['permissionname_view_attachments'] = 'Voir les fichiers joints';
$txt['permissionhelp_view_attachments'] = 'Les fichiers joints sont des pi�ces attach�es aux messages post�s. Cette fonction peut �tre activ�e et configur�e dans \'Fichiers joints et avatars\'. Comme les fichiers joints ne sont pas directement accessibles, vous pouvez �viter aux membres non autoris�s de les t�l�charger.';
$txt['permissionname_post_attachment'] = 'Poster des fichiers joints';
$txt['permissionhelp_post_attachment'] = 'Les fichiers joints sont des pi�ces attach�es aux messages post�s. Un message peut en contenir plusieurs.';

$txt['permissionicon'] = '';

$txt['permission_settings_title'] = 'Param�tres des permissions';
$txt['groups_manage_permissions'] = 'Groupes de membres autoris�s � g�rer les permissions';
$txt['permission_settings_submit'] = 'Enregistrer';
$txt['permission_settings_enable_deny'] = 'Activer l\'option pour interdire des permissions';
// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['permission_disable_deny_warning'] = 'D�sactiver cette option va mettre � jour tous les permissions interdites \\\'Interdite\\\' vers le statut \\\'Refus�e\\\'.';
$txt['permission_by_board_desc'] = 'Ici pous pouvez attribuer un profil de permissions � une section. Vous pouvez cr�er de nouveaux profils de permissions dans le menu &quot;Modifier les Profils&quot;.';
$txt['permission_settings_desc'] = 'Ici vous pouvez r�gler qui a la permission de changer les permissions, de m�me que la complexit� que devrait avoir le syst�me de permissions.';
$txt['permission_settings_enable_postgroups'] = 'Activer les permissions pour les groupes posteurs';
// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['permission_disable_postgroups_warning'] = 'D�sactiver ce param�tre va enlever les permissions pr�sentement attribu�es aux groupes posteurs.';

$txt['permissions_post_moderation_desc'] = 'D\'ici, vous pouvez facilement changer quels groupes voient leurs messages mod�r�s pour un profil de permissions sp�cifique.';
$txt['permissions_post_moderation_enable'] = 'Enable Post Moderation';
$txt['permissions_post_moderation_deny_note'] = 'Notez que si vous avez activ� les permissions avanc�es, vous ne pourrez pas appliquer la permission &quot;refuser&quot; � partir de cette page. Veuillez modifier les permissions directement si vous voulez appliquer un refus de permission.';
$txt['permissions_post_moderation_select'] = 'Choisissez le Profil&nbsp;';
$txt['permissions_post_moderation_new_topics'] = 'Nouveaux sujets';
$txt['permissions_post_moderation_replies_own'] = 'R�ponses sur ses fils';
$txt['permissions_post_moderation_replies_any'] = 'R�ponses partout';
$txt['permissions_post_moderation_attachments'] = 'Fichiers joints';
$txt['permissions_post_moderation_legend'] = 'L�gende&nbsp;';
$txt['permissions_post_moderation_allow'] = 'Peut cr�er/envoyer';
$txt['permissions_post_moderation_moderate'] = 'Peut cr�er/envoyer mais n�cessite l\'approbation d\'un mod�rateur';
$txt['permissions_post_moderation_disallow'] = 'Ne peut pas cr�er/envoyer';
$txt['permissions_post_moderation_group'] = 'Groupe';

$txt['auto_approve_topics'] = 'Poster des sujets auto-approuv�s';
$txt['auto_approve_replies'] = 'Poster des r�ponses auto-approuv�es aux sujets';
$txt['auto_approve_attachments'] = 'Poster des fichiers joints auto-approuv�s';

?>