<?php
// Version: 2.1 Beta 2; ManageBoards

$txt['boards_and_cats'] = 'Gestion des Sections et des Cat�gories';
$txt['order'] = 'Ordre';
$txt['full_name'] = 'Nom complet';
$txt['name_on_display'] = 'Ceci est le nom qui sera affich�.';
$txt['boards_and_cats_desc'] = 'Modifiez vos cat�gories et sections ici. Listez les mod�rateurs comme ceci <em>&quot;identifiant&quot;, &quot;identifiant&quot;</em>. (Utilisez les identifiants des membres, *pas* leurs noms affich�s)<br>Pour cr�er une nouvelle section, cliquez sur le bouton Ajouter une section. Pour faire une sous-section, choisissez "Sous-section de..." dans la liste d�roulante lors de la cr�ation.';
$txt['parent_members_only'] = 'Membres inscrits';
$txt['parent_guests_only'] = 'Invit�s';
$txt['catConfirm'] = 'Voulez-vous r�ellement supprimer cette cat�gorie&nbsp;?';
$txt['boardConfirm'] = 'Voulez-vous r�ellement supprimer cette section&nbsp;?';

$txt['catEdit'] = 'Modifier la cat�gorie';
$txt['collapse_enable'] = 'R�tractable';
$txt['collapse_desc'] = 'Permet aux membres de r�duire cette cat�gorie';
$txt['catModify'] = '(modifier)';

$txt['mboards_order_after'] = 'Apr�s ';
$txt['mboards_order_inside'] = 'Dans ';
$txt['mboards_order_first'] = 'En premier';

$txt['mboards_new_board'] = 'Ajouter une section';
$txt['mboards_new_cat_name'] = 'Nouvelle cat�gorie';
$txt['mboards_add_cat_button'] = 'Ajouter la cat�gorie';
$txt['mboards_new_board_name'] = 'Nouvelle section';

$txt['mboards_name'] = 'Nom';
$txt['mboards_modify'] = 'modifier';
$txt['mboards_permissions'] = 'permissions';
// Don't use entities in the below string.
$txt['mboards_permissions_confirm'] = 'Etes-vous s�r de vouloir changer le fonctionnement de cette section pour utiliser des permissions locales ?';

$txt['mboards_delete_cat'] = 'Supprimer cette cat�gorie';
$txt['mboards_delete_board'] = 'Supprimer cette section';

$txt['mboards_delete_cat_contains'] = 'Supprimer cette cat�gorie effacera aussi les sections suivantes, ainsi que leurs sujets, messages et fichiers joints&nbsp;';
$txt['mboards_delete_option1'] = 'Supprimer la cat�gorie et toutes les sections qu\'elle contient.';
$txt['mboards_delete_option2'] = 'Supprimer la cat�gorie et transf�rer toutes ses sections vers&nbsp;';
$txt['mboards_delete_board_contains'] = 'Supprimer cette section d�placera les sous-sections suivantes, ainsi que tous les sujets, messages et fichiers joints qu\'elles contiennent';
$txt['mboards_delete_board_option1'] = 'Supprimer la section et d�placer les sous-sections contenus dans la cat�gorie.';
$txt['mboards_delete_board_option2'] = 'Supprimer la section et d�placer ses sous-sections vers';
$txt['mboards_delete_what_do'] = 'Veuillez s�lectionner ce que vous d�sirez faire avec ces sections&nbsp;';
$txt['mboards_delete_confirm'] = 'Confirmer';
$txt['mboards_delete_cancel'] = 'Annuler';

$txt['mboards_category'] = 'Cat�gorie';
$txt['mboards_description'] = 'Description';
$txt['mboards_description_desc'] = 'Une courte description de votre section.';
$txt['mboards_cat_description_desc'] = 'Une courte description de votre cat�gorie.';
$txt['mboards_groups'] = 'Groupes autoris�s';
$txt['mboards_groups_desc'] = 'Groupes autoris�s � acc�der � cette section.<br><em>Note : Si un membre est dans un des groupes permanents ou posteurs s�lectionn�, il aura acc�s � la section.</em>';
$txt['mboards_groups_regular_members'] = 'Ce groupe contient tous les membres � qui aucun groupe principal n\'a �t� attribu�.';
$txt['mboards_groups_post_group'] = 'Ce groupe est attribu� en fonction du nombre de messages post�s.';
$txt['mboards_moderators'] = 'Mod�rateurs';
$txt['mboards_moderators_desc'] = 'Membres ayant des privil�ges de mod�rateur dans cette section. Notez que les administrateurs n\'ont pas � �tre list�s ici.';
$txt['mboards_moderator_groups'] = 'Groupes mod�rateurs';
$txt['mboards_moderator_groups_desc'] = 'Groupes dont les membres ont des droits de mod�ration dans cette section. Notez que cela est limit� aux groupes non reli�s au nombre de messages, et non cach�s.';
$txt['mboards_count_posts'] = 'Comptabiliser les messages';
$txt['mboards_count_posts_desc'] = 'Les nouveaux messages et sujets font augmenter le compteur de messages des membres.';
$txt['mboards_unchanged'] = 'Inchang�';
$txt['mboards_theme'] = 'Th�me de la section';
$txt['mboards_theme_desc'] = 'Permet de donner un aspect visuel diff�rent � cette section.';
$txt['mboards_theme_default'] = '(Th�me par d�faut)';
$txt['mboards_override_theme'] = 'Outrepasser le th�me choisi par le membre';
$txt['mboards_override_theme_desc'] = 'Force le changement du th�me de cette section pour celui sp�cifi� pr�c�demment, m�me si le membre a choisi de ne pas utiliser les r�glages par d�faut du forum.';

$txt['mboards_redirect'] = 'Rediriger vers une adresse web';
$txt['mboards_redirect_desc'] = 'Activer cette option pour rediriger la section vers une autre adresse web.';
$txt['mboards_redirect_url'] = 'Adresse de redirection';
$txt['mboards_redirect_url_desc'] = 'Exemple&nbsp;: &quot;http://www.simplemachines.org&quot;.';
$txt['mboards_redirect_reset'] = 'Remettre � z�ro le compteur de redirections';
$txt['mboards_redirect_reset_desc'] = 'S�lectionner ceci remettra � z�ro le compteur de redirections de cette section.';
$txt['mboards_current_redirects'] = 'Actuellement&nbsp;: %1$s';

$txt['mboards_order_before'] = 'Avant';
$txt['mboards_order_child_of'] = 'Sous-section de';
$txt['mboards_order_in_category'] = 'Dans la cat�gorie';
$txt['mboards_current_position'] = 'Position actuelle';
$txt['no_valid_parent'] = 'La section %1$s n\'a pas de section parente valide. Demandez � l\'administrateur d\'utiliser la fonction \'Chercher et r�parer les erreurs\' du panneau <em>Maintenance du forum</em> pour corriger cela.';

$txt['mboards_recycle_disabled_delete'] = 'Note&nbsp;: vous devez choisir une nouvelle section de recyclage, ou d�sactiver le recyclage avant de supprimer cette section.';

$txt['mboards_settings_desc'] = 'Modifier les param�tres g�n�raux des cat�gories et des sections.';
$txt['groups_manage_boards'] = 'Groupes de membres autoris�s � g�rer les sections et cat�gories';
$txt['mboards_settings_submit'] = 'Sauvegarder';
$txt['recycle_enable'] = 'Activer le recyclage des sujets effac�s';
$txt['recycle_board'] = 'Section pour les sujets recycl�s';
$txt['redirect_board_desc'] = 'Une section qui redirigera les utilisateurs s\'ils la visitent';
$txt['recycle_board_unselected_notice'] = 'Vous avez activ� le recyclage de sujets sans sp�cifier la section o� les placer. Cette fonction ne sera pas active tant que vous n\'aurez pas indiqu� la section.';
$txt['countChildPosts'] = 'Compter les messages des sous-sections dans le total de leur section parente';
$txt['allow_ignore_boards'] = 'Permettre aux membres d\'ignorer des sections';
$txt['deny_boards_access'] = 'Activer l\'option pour interdire l\'acc�s au forum selon le groupe';
$txt['boardsaccess_option_desc'] = 'Pour chaque permission vous pouvez choisir \'Autoriser\' (A), \'Ignorer\' (X) ou <span class="alert">\'Refuser\' (D)</span>.<br><br>Si vous refusez l\'acc�s, n\'importe quel membre - (incluant les mod�rateurs) - dans ce groupe se verra l\'acc�s refus�.<br>Pour cette raison, vous devrez choisir Refuser avec attention, seulement quand cela est <strong>n�cessaire</strong>. Ignorer, d\'un autre cot�, refusera l\'acc�s � moins qu\'il n\'en soit sp�cifi� autrement.';

$txt['mboards_select_destination'] = 'S�lectionner la destination pour la section \'<strong>%1$s</strong>\'';
$txt['mboards_cancel_moving'] = 'Annuler le d�placement';
$txt['mboards_move'] = 'd�placer';

$txt['mboards_no_cats'] = 'Il n\'y a actuellement aucune section ou cat�gorie configur�e.';

?>