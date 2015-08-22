<?php
// Version: 2.1 Beta 2; ManageBoards

$txt['boards_and_cats'] = 'Gestion des Sections et des Catégories';
$txt['order'] = 'Ordre';
$txt['full_name'] = 'Nom complet';
$txt['name_on_display'] = 'Ceci est le nom qui sera affiché.';
$txt['boards_and_cats_desc'] = 'Modifiez vos catégories et sections ici. Listez les modérateurs comme ceci <em>&quot;identifiant&quot;, &quot;identifiant&quot;</em>. (Utilisez les identifiants des membres, *pas* leurs noms affichés)<br>Pour créer une nouvelle section, cliquez sur le bouton Ajouter une section. Pour faire une sous-section, choisissez "Sous-section de..." dans la liste déroulante lors de la création.';
$txt['parent_members_only'] = 'Membres inscrits';
$txt['parent_guests_only'] = 'Invités';
$txt['catConfirm'] = 'Voulez-vous réellement supprimer cette catégorie&nbsp;?';
$txt['boardConfirm'] = 'Voulez-vous réellement supprimer cette section&nbsp;?';

$txt['catEdit'] = 'Modifier la catégorie';
$txt['collapse_enable'] = 'Rétractable';
$txt['collapse_desc'] = 'Permet aux membres de réduire cette catégorie';
$txt['catModify'] = '(modifier)';

$txt['mboards_order_after'] = 'Après ';
$txt['mboards_order_inside'] = 'Dans ';
$txt['mboards_order_first'] = 'En premier';

$txt['mboards_new_board'] = 'Ajouter une section';
$txt['mboards_new_cat_name'] = 'Nouvelle catégorie';
$txt['mboards_add_cat_button'] = 'Ajouter la catégorie';
$txt['mboards_new_board_name'] = 'Nouvelle section';

$txt['mboards_name'] = 'Nom';
$txt['mboards_modify'] = 'modifier';
$txt['mboards_permissions'] = 'permissions';
// Don't use entities in the below string.
$txt['mboards_permissions_confirm'] = 'Etes-vous sûr de vouloir changer le fonctionnement de cette section pour utiliser des permissions locales ?';

$txt['mboards_delete_cat'] = 'Supprimer cette catégorie';
$txt['mboards_delete_board'] = 'Supprimer cette section';

$txt['mboards_delete_cat_contains'] = 'Supprimer cette catégorie effacera aussi les sections suivantes, ainsi que leurs sujets, messages et fichiers joints&nbsp;';
$txt['mboards_delete_option1'] = 'Supprimer la catégorie et toutes les sections qu\'elle contient.';
$txt['mboards_delete_option2'] = 'Supprimer la catégorie et transférer toutes ses sections vers&nbsp;';
$txt['mboards_delete_board_contains'] = 'Supprimer cette section déplacera les sous-sections suivantes, ainsi que tous les sujets, messages et fichiers joints qu\'elles contiennent';
$txt['mboards_delete_board_option1'] = 'Supprimer la section et déplacer les sous-sections contenus dans la catégorie.';
$txt['mboards_delete_board_option2'] = 'Supprimer la section et déplacer ses sous-sections vers';
$txt['mboards_delete_what_do'] = 'Veuillez sélectionner ce que vous désirez faire avec ces sections&nbsp;';
$txt['mboards_delete_confirm'] = 'Confirmer';
$txt['mboards_delete_cancel'] = 'Annuler';

$txt['mboards_category'] = 'Catégorie';
$txt['mboards_description'] = 'Description';
$txt['mboards_description_desc'] = 'Une courte description de votre section.';
$txt['mboards_cat_description_desc'] = 'Une courte description de votre catégorie.';
$txt['mboards_groups'] = 'Groupes autorisés';
$txt['mboards_groups_desc'] = 'Groupes autorisés à accéder à cette section.<br><em>Note : Si un membre est dans un des groupes permanents ou posteurs sélectionné, il aura accès à la section.</em>';
$txt['mboards_groups_regular_members'] = 'Ce groupe contient tous les membres à qui aucun groupe principal n\'a été attribué.';
$txt['mboards_groups_post_group'] = 'Ce groupe est attribué en fonction du nombre de messages postés.';
$txt['mboards_moderators'] = 'Modérateurs';
$txt['mboards_moderators_desc'] = 'Membres ayant des privilèges de modérateur dans cette section. Notez que les administrateurs n\'ont pas à être listés ici.';
$txt['mboards_moderator_groups'] = 'Groupes modérateurs';
$txt['mboards_moderator_groups_desc'] = 'Groupes dont les membres ont des droits de modération dans cette section. Notez que cela est limité aux groupes non reliés au nombre de messages, et non cachés.';
$txt['mboards_count_posts'] = 'Comptabiliser les messages';
$txt['mboards_count_posts_desc'] = 'Les nouveaux messages et sujets font augmenter le compteur de messages des membres.';
$txt['mboards_unchanged'] = 'Inchangé';
$txt['mboards_theme'] = 'Thème de la section';
$txt['mboards_theme_desc'] = 'Permet de donner un aspect visuel différent à cette section.';
$txt['mboards_theme_default'] = '(Thème par défaut)';
$txt['mboards_override_theme'] = 'Outrepasser le thème choisi par le membre';
$txt['mboards_override_theme_desc'] = 'Force le changement du thème de cette section pour celui spécifié précédemment, même si le membre a choisi de ne pas utiliser les réglages par défaut du forum.';

$txt['mboards_redirect'] = 'Rediriger vers une adresse web';
$txt['mboards_redirect_desc'] = 'Activer cette option pour rediriger la section vers une autre adresse web.';
$txt['mboards_redirect_url'] = 'Adresse de redirection';
$txt['mboards_redirect_url_desc'] = 'Exemple&nbsp;: &quot;http://www.simplemachines.org&quot;.';
$txt['mboards_redirect_reset'] = 'Remettre à zéro le compteur de redirections';
$txt['mboards_redirect_reset_desc'] = 'Sélectionner ceci remettra à zéro le compteur de redirections de cette section.';
$txt['mboards_current_redirects'] = 'Actuellement&nbsp;: %1$s';

$txt['mboards_order_before'] = 'Avant';
$txt['mboards_order_child_of'] = 'Sous-section de';
$txt['mboards_order_in_category'] = 'Dans la catégorie';
$txt['mboards_current_position'] = 'Position actuelle';
$txt['no_valid_parent'] = 'La section %1$s n\'a pas de section parente valide. Demandez à l\'administrateur d\'utiliser la fonction \'Chercher et réparer les erreurs\' du panneau <em>Maintenance du forum</em> pour corriger cela.';

$txt['mboards_recycle_disabled_delete'] = 'Note&nbsp;: vous devez choisir une nouvelle section de recyclage, ou désactiver le recyclage avant de supprimer cette section.';

$txt['mboards_settings_desc'] = 'Modifier les paramètres généraux des catégories et des sections.';
$txt['groups_manage_boards'] = 'Groupes de membres autorisés à gérer les sections et catégories';
$txt['mboards_settings_submit'] = 'Sauvegarder';
$txt['recycle_enable'] = 'Activer le recyclage des sujets effacés';
$txt['recycle_board'] = 'Section pour les sujets recyclés';
$txt['redirect_board_desc'] = 'Une section qui redirigera les utilisateurs s\'ils la visitent';
$txt['recycle_board_unselected_notice'] = 'Vous avez activé le recyclage de sujets sans spécifier la section où les placer. Cette fonction ne sera pas active tant que vous n\'aurez pas indiqué la section.';
$txt['countChildPosts'] = 'Compter les messages des sous-sections dans le total de leur section parente';
$txt['allow_ignore_boards'] = 'Permettre aux membres d\'ignorer des sections';
$txt['deny_boards_access'] = 'Activer l\'option pour interdire l\'accès au forum selon le groupe';
$txt['boardsaccess_option_desc'] = 'Pour chaque permission vous pouvez choisir \'Autoriser\' (A), \'Ignorer\' (X) ou <span class="alert">\'Refuser\' (D)</span>.<br><br>Si vous refusez l\'accès, n\'importe quel membre - (incluant les modérateurs) - dans ce groupe se verra l\'accès refusé.<br>Pour cette raison, vous devrez choisir Refuser avec attention, seulement quand cela est <strong>nécessaire</strong>. Ignorer, d\'un autre coté, refusera l\'accès à moins qu\'il n\'en soit spécifié autrement.';

$txt['mboards_select_destination'] = 'Sélectionner la destination pour la section \'<strong>%1$s</strong>\'';
$txt['mboards_cancel_moving'] = 'Annuler le déplacement';
$txt['mboards_move'] = 'déplacer';

$txt['mboards_no_cats'] = 'Il n\'y a actuellement aucune section ou catégorie configurée.';

?>