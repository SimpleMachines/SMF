<?php
// Version: 2.1 Beta 2; PersonalMessage

global $context;

// Things for the popup
$txt['pm_unread'] = 'Non lu';
$txt['pm_sent_short'] = 'Envoyé';
$txt['pm_new_short'] = 'Nouveau';
$txt['pm_drafts_short'] = 'Brouillons';
$txt['pm_settings_short'] = 'Réglages';
$txt['pm_no_unread'] = 'Aucun message non lu.';
$txt['pm_was_sent_to_you'] = 'Vous avez reçu un message.';
$txt['pm_you_were_replied_to'] = 'Une réponse à un de vos message a été envoyée.';

$txt['pm_inbox'] = 'Accueil des messages personnels';
$txt['send_message'] = 'Envoyer un message';
$txt['pm_add'] = 'Ajouter';
$txt['make_bcc'] = 'Ajouter un BCC';
$txt['pm_to'] = 'À&nbsp;';
$txt['pm_bcc'] = 'Bcc&nbsp;';
$txt['inbox'] = 'Réception';
$txt['conversation'] = 'Conversation';
$txt['messages'] = 'Messages';
$txt['sent_items'] = 'Messages envoyés';
$txt['new_message'] = 'Nouveau message';
$txt['delete_message'] = 'Effacer des messages';
// Don't translate "PMBOX" in this string.
$txt['delete_all'] = 'Effacer tous les messages dans votre PMBOX';
$txt['delete_all_confirm'] = 'Êtes vous sûr de vouloir effacer tous les messages&nbsp;?';
$txt['recipient'] = 'Destinataire';

$txt['delete_selected_confirm'] = 'Êtes-vous sûr de vouloir effacer tous les messages personnels sélectionnés&nbsp;?';

$txt['sent_to'] = 'Envoyé à';
$txt['reply_to_all'] = 'Répondre à tous';
$txt['delete_conversation'] = 'Supprimer Conversation';
$txt['remove_conversation'] = 'Supprimer tous les messages dans cette conversation?';

$txt['pm_capacity'] = 'Capacité';
$txt['pm_currently_using'] = '%1$s messages, %2$s%% pleine.';
$txt['pm_sent'] = 'Votre message a été envoyé.';

$txt['pm_error_user_not_found'] = 'Impossible de trouver le membre \'%1$s\'.';
$txt['pm_error_ignored_by_user'] = 'Le membre \'%1$s\' a bloqué votre message personnel.';
$txt['pm_error_data_limit_reached'] = 'Le message n\'a pas pu être envoyé à \'%1$s\' car sa boîte de réception est pleine&nbsp;!';
$txt['pm_error_user_cannot_read'] = 'L\'utilisateur \'%1$s\' ne peut pas recevoir de messages personnels.';
$txt['pm_successfully_sent'] = 'Le message a été envoyé à \'%1$s\'.';
$txt['pm_send_report'] = 'Rapport d\'envoi';
$txt['pm_undisclosed_recipients'] = 'Destinataires non révélés';
$txt['pm_too_many_recipients'] = 'Vous ne pouvez pas envoyer de messages personnels à plus de %1$d destinataire(s) à la fois.';

$txt['pm_read'] = 'Lu';
$txt['pm_replied'] = 'Répondu à';

// Message Pruning.
$txt['pm_prune'] = 'Purger la boîte';
$txt['pm_prune_desc1'] = 'Effacer tous les messages personnels antérieurs à';
$txt['pm_prune_desc2'] = 'jours.';
$txt['pm_prune_warning'] = 'Êtes-vous certain de vouloir purger vos messages personnels? Ils ne pourront plus être restaurés.';
$txt['pm_remove_all'] = 'Supprimer tous vos messages personnels (ceci videra votre boite de réception et d\'envois). ';
$txt['pm_remove_all_warning'] = 'Êtes vous absolument certain de vouloir supprimer tous vos messages personnels? Ils ne pourront plus être restaurés.';
$txt['delete_all_prune'] = 'Tout supprimer';

// Actions Drop Down.
$txt['pm_actions_title'] = 'Actions additionnelles';
$txt['pm_actions_delete_selected'] = 'Effacer la sélection';
$txt['pm_actions_filter_by_label'] = 'Filtrer par label';
$txt['pm_actions_go'] = 'Exécuter';

// Manage Labels Screen.
$txt['pm_apply'] = 'Appliquer';
$txt['pm_manage_labels'] = 'Gérer les labels';
$txt['pm_labels_delete'] = 'Êtes-vous sûr de vouloir effacer les labels sélectionnés&nbsp;?';
$txt['pm_labels_desc'] = 'Ici vous pouvez ajouter, modifier et supprimer les labels utilisés dans votre centre de messagerie personnelle.';
$txt['pm_label_add_new'] = 'Ajouter un nouveau label';
$txt['pm_label_name'] = 'Nom du label';
$txt['pm_labels_no_exist'] = 'Vous n\'avez actuellement aucun label paramétré&nbsp;!';

// Labeling Drop Down.
$txt['pm_current_label'] = 'Label';
$txt['pm_msg_label_title'] = 'Attribuer un label au message';
$txt['pm_msg_label_apply'] = 'Ajouter un label';
$txt['pm_msg_label_remove'] = 'Enlever un label';
$txt['pm_msg_label_inbox'] = 'Boîte de réception';
$txt['pm_sel_label_title'] = 'Label sélectionné';

// Menu headings.
$txt['pm_labels'] = 'Labels';
$txt['pm_messages'] = 'Messages';
$txt['pm_actions'] = 'Actions';
$txt['pm_preferences'] = 'Préférences';

$txt['pm_is_replied_to'] = 'Vous avez transféré ou répondu à ce message.';

// Reporting messages.
$txt['pm_report_to_admin'] = 'Rapporter à l\'administrateur';
$txt['pm_report_title'] = 'Rapporter un message personnel';
$txt['pm_report_desc'] = 'Depuis cette page vous pouvez rapporter le message personnel que vous avez reçu à l\'équipe d\'administration du forum. Veuillez vous assurer d\'inclure une description de la raison de ce rapport de message, puisque ça sera envoyé avec le contenu du message original.';
$txt['pm_report_admins'] = 'Administrateur à aviser';
$txt['pm_report_all_admins'] = 'Envoyer à tous les administrateurs';
$txt['pm_report_reason'] = 'Raison du rapport de ce message';
$txt['pm_report_message'] = 'Rapporter le message';

// Important - The following strings should use numeric entities.
$txt['pm_report_pm_subject'] = '[RAPPORT] ';
// In the below string, do not translate "{REPORTER}" or "{SENDER}".
$txt['pm_report_pm_user_sent'] = '{REPORTER} a rapport&#233; le message personnel suivant, envoy&#233; par {SENDER}, pour la raison suivante :';
$txt['pm_report_pm_other_recipients'] = 'Les autres destinataires de ce message sont :';
$txt['pm_report_pm_hidden'] = '%1$d destinataire(s) cach&#233;(s)';
$txt['pm_report_pm_unedited_below'] = 'Ci-dessous se trouve le contenu original du message personnel rapport&#233; :';
$txt['pm_report_pm_sent'] = 'Envoy&#233; :';

$txt['pm_report_done'] = 'Merci d\'avoir soumis ce rapport. Vous devriez recevoir des nouvelles des administrateurs rapidement.';
$txt['pm_report_return'] = 'Retourner à la boîte de réception';

$txt['pm_search_title'] = 'Rechercher dans la boîte de messages personnels';
$txt['pm_search_bar_title'] = 'Rechercher des messages';
$txt['pm_search_text'] = 'Entrez le texte à rechercher&nbsp;';
$txt['pm_search_go'] = 'Rechercher';
$txt['pm_search_advanced'] = 'Recherche avancée';
$txt['pm_search_user'] = 'Par utilisateur';
$txt['pm_search_match_all'] = 'Correspondre tous les mots';
$txt['pm_search_match_any'] = 'Correspondre n\'importe quel mot';
$txt['pm_search_options'] = 'Options';
$txt['pm_search_post_age'] = 'Âge du message';
$txt['pm_search_show_complete'] = 'Montrer tout le message dans les résultats.';
$txt['pm_search_subject_only'] = 'Chercher par titre et auteur seulement.';
$txt['pm_search_between'] = 'entre';
$txt['pm_search_between_and'] = 'et';
$txt['pm_search_between_days'] = 'jours';
$txt['pm_search_order'] = 'Ordre de recherche';
$txt['pm_search_choose_label'] = 'Choisir les labels à rechercher, ou rechercher partout';

$txt['pm_search_results'] = 'Résultats des Recherches';
$txt['pm_search_none_found'] = 'Aucun Message Trouvé';

$txt['pm_search_orderby_relevant_first'] = 'Plus significatif en premier';
$txt['pm_search_orderby_recent_first'] = 'Plus récent en premier';
$txt['pm_search_orderby_old_first'] = 'Plus ancien en premier';

$txt['pm_visual_verification_label'] = 'Vérification';
$txt['pm_visual_verification_desc'] = 'Veuillez saisir le code contenu dans l\'image ci-dessus pour envoyer ce message privé.';

$txt['pm_settings'] = 'Changer les réglages';
$txt['pm_change_view'] = 'Changer d\'Affichage';

$txt['pm_manage_rules'] = 'Gérer les règles';
$txt['pm_manage_rules_desc'] = 'Les règles de message vous permettent de traiter automatiquement les messages personnels selon des critères que vous aurez définis. Ci-dessous, la liste des règles que vous avez actuellement. Pour modifier une règle, cliquez simplement sur son nom.';
$txt['pm_rules_none'] = 'Vous n\'avez pas encore créé de règle de message.';
$txt['pm_rule_title'] = 'Règle';
$txt['pm_add_rule'] = 'Ajouter une Nouvelle Règle';
$txt['pm_apply_rules'] = 'Appliquer les Règles maintenant';
// Use entities in the below string.
$txt['pm_js_apply_rules_confirm'] = 'Etes-vous sûr de vouloir appliquer les règles actuelles à tous les messages personnels&nbsp;?';
$txt['pm_edit_rule'] = 'Modifier la Règle';
$txt['pm_rule_save'] = 'Sauvegarder la Règle';
$txt['pm_delete_selected_rule'] = 'Effacer les règles sélectionnées';
// Use entities in the below string.
$txt['pm_js_delete_rule_confirm'] = 'Etes-vous sûr de vouloir effacer les règles sélectionnées&nbsp;?';
$txt['pm_rule_name'] = 'Nom';
$txt['pm_rule_name_desc'] = 'Le nom de la règle, pour s\'en souvenir plus facilement';
$txt['pm_rule_name_default'] = '[NOM]';
$txt['pm_rule_description'] = 'Description';
$txt['pm_rule_not_defined'] = 'Ajoutez un critère pour commencer à mettre en place la description de cette règle.';
$txt['pm_rule_js_disabled'] = '<span class="alert"><strong>Attention</strong>, il semblerait que Javascript soit désactivé. Nous vous recommandons fortement d\'activer Javascript pour utiliser cette fonctionnalité.</span>';
$txt['pm_rule_criteria'] = 'Critères';
$txt['pm_rule_criteria_add'] = 'Ajouter un critère';
$txt['pm_rule_criteria_pick'] = 'Choisissez un critère';
$txt['pm_rule_mid'] = 'Nom de l\'expéditeur';
$txt['pm_rule_gid'] = 'Groupe de l\'expéditeur';
$txt['pm_rule_sub'] = 'Le titre du message contient';
$txt['pm_rule_msg'] = 'Le corps du message contient';
$txt['pm_rule_bud'] = 'L\'expéditeur est un ami';
$txt['pm_rule_sel_group'] = 'Sélectionner un groupe';
$txt['pm_rule_logic'] = 'Lors de la vérification des critères&nbsp;';
$txt['pm_rule_logic_and'] = 'Tous les critères doivent être satisfaits';
$txt['pm_rule_logic_or'] = 'Au moins un critère doit être satisfait';
$txt['pm_rule_actions'] = 'Actions';
$txt['pm_rule_sel_action'] = 'Choisissez une action';
$txt['pm_rule_add_action'] = 'Ajouter une action';
$txt['pm_rule_label'] = 'Mettre ce label sur le message';
$txt['pm_rule_sel_label'] = 'Choisissez le label';
$txt['pm_rule_delete'] = 'Effacer le Message';
$txt['pm_rule_no_name'] = 'Vous avez oublié d\'entrer un nom pour la règle.';
$txt['pm_rule_no_criteria'] = 'Une règle doit avoir au moins un critère et une action.';
$txt['pm_rule_too_complex'] = 'La règle que vous êtes en train de créer est trop longue à enregistrer pour SMF. Essayez de la diviser en règles plus petites.';

$txt['pm_readable_and'] = '<em>et</em>';
$txt['pm_readable_or'] = '<em>ou</em>';
$txt['pm_readable_start'] = 'Si ';
$txt['pm_readable_end'] = '.';
$txt['pm_readable_member'] = 'message vient de &quot;{MEMBER}&quot;';
$txt['pm_readable_group'] = 'l\'expéditeur est du groupe &quot;{GROUP}&quot;';
$txt['pm_readable_subject'] = 'le titre du message contient &quot;{SUBJECT}&quot;';
$txt['pm_readable_body'] = 'le corps du message contient &quot;{BODY}&quot;';
$txt['pm_readable_buddy'] = 'l\'expéditeur est un ami';
$txt['pm_readable_label'] = 'appliquer le label &quot;{LABEL}&quot;';
$txt['pm_readable_delete'] = 'effacer le message';
$txt['pm_readable_then'] = '<strong>puis</strong>';
$txt['pm_remove_message'] = 'Supprimer ce message';

?>