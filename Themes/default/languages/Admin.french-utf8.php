<?php
// Version: 2.1 Beta 2; Admin

global $settings, $scripturl;

$txt['settings_saved']     = 'Les réglages ont étés enregistrés avec succès';
$txt['settings_not_saved'] = 'Vos changements n\'ont pas étés enregistrés parce que: %1$s';

$txt['admin_boards']                           = 'Sections et Catégories';
$txt['admin_users']                            = 'Membres';
$txt['admin_newsletters']                      = 'Infolettre';
$txt['admin_edit_news']                        = 'Nouvelles';
$txt['admin_groups']                           = 'Groupes de membres';
$txt['admin_members']                          = 'Gestion des membres';
$txt['admin_members_list']                     = 'Ci-dessous une liste de tous les membres actuellement inscrits sur votre forum.';
$txt['admin_next']                             = 'Suivant';
$txt['admin_censored_words']                   = 'Mots censurés';
$txt['admin_censored_where']                   = 'Écrivez le mot à censurer à gauche, et ce par quoi il est remplacé à droite.';
$txt['admin_censored_desc']                    = 'Etant donné la nature publique des forums, vous souhaitez peut-être censurer certains mots.  Ci-dessous, vous pouvez entrer n\'importe quel mot que vous voudriez voir censuré à chaque fois qu\'un membre l\'utilise.<br>Videz une boîte du mot qu\'elle contient pour enlever celui-ci.';
$txt['admin_reserved_names']                   = 'Noms réservés';
$txt['admin_template_edit']                    = 'Modifier le modèle (template) de votre forum';
$txt['admin_modifications']                    = 'Options des Paquets';
$txt['admin_server_settings']                  = 'Paramètres du Serveur';
$txt['admin_reserved_set']                     = 'Choix des noms réservés';
$txt['admin_reserved_line']                    = 'Un seul mot réservé par ligne.';
$txt['admin_basic_settings']                   = 'Cette page vous permet de modifier les réglages de base de votre forum. Soyez très vigilant avec ces réglages, puisqu\'ils peuvent rendre votre forum non fonctionnel.';
$txt['admin_maintain']                         = 'Activer le Mode Maintenance';
$txt['admin_title']                            = 'Nom du forum';
$txt['cookie_name']                            = 'Nom du témoin (cookie)';
$txt['admin_webmaster_email']                  = 'Adresse e-mail du webmestre';
$txt['cachedir']                               = 'Répertoire Cache';
$txt['admin_news']                             = 'Afficher la barre de nouvelles';
$txt['admin_guest_post']                       = 'Permettre aux invités de poster';
$txt['admin_manage_members']                   = 'Membres';
$txt['admin_main']                             = 'Contrôles';
$txt['admin_config']                           = 'Configuration';
$txt['admin_version_check']                    = 'Vérification détaillée de la version';
$txt['admin_smffile']                          = 'Fichiers SMF';
$txt['admin_smfpackage']                       = 'Paquets SMF';
$txt['admin_logoff']                           = 'Fin de session d\'administration';
$txt['admin_maintenance']                      = 'Maintenance';
$txt['admin_image_text']                       = 'Montrer les boutons en tant qu\'images plutôt qu\'en textes';
$txt['admin_credits']                          = 'Crédits';
$txt['admin_agreement']                        = 'Afficher et exiger l\'accord des conditions lors de l\'inscription&nbsp;?';
$txt['admin_agreement_default']                = 'Défaut';
$txt['admin_agreement_select_language']        = 'Langue à modifier';
$txt['admin_agreement_select_language_change'] = 'Changer';
$txt['admin_agreement_not_saved']              = 'Les modifications des accords d\'enregistrement n\'ont pas pu être enregistrées. Peut être que les permissions du fichier ne sont pas réglées correctement.';
$txt['admin_delete_members']                   = 'Supprimer les membres sélectionnés';
$txt['admin_repair']                           = 'Réparer tous les sujets et sections';
$txt['admin_main_welcome']                     = 'Ceci est votre &quot;%1$s&quot;. À partir d\'ici, vous pouvez modifier vos préférences, faire des opérations de maintenance sur votre forum, voir les journaux (<em>journaux</em>), installer des paquets, gérer les thèmes et bien plus encore.<br><br>Si vous avez un problème, veuillez consulter la page &quot;Support et crédits&quot;.  Si l\'information fournie ne vous aide pas, n\'hésitez pas à <a href="http://www.simplemachines.org/community/index.php" target="_blank" class="new_win">nous contacter pour de l\'aide</a> à propos de votre problème. (Pour de l\'aide en français, allez sur le <a href="http://www.simplemachines.org/community/index.php?board=14.0" hreflang="fr" target="_blank" title="Aide en français pour SMF">Support francophone</a>.)<br>Vous pouvez aussi trouver des réponses à vos questions en cliquant sur les symboles <span class="generic_icons help" title="%3$s"></span> pour voir comment fonctionnent certaines options.';
$txt['admin_news_desc']                        = 'SVP ne placez qu\'une seule nouvelle par zone de texte. Quelques balises BBC, comme <span title="Êtes-vous gras ?">[b]</span>, <span title="I tall icks!!">[i]</span> et <span title="Les supports sont top, non ?">[u]</span> sont autorisées dans vos nouvelles, ainsi que les smileys et le codes HTML. Enlevez tout le texte d\'une zone de texte pour la désactiver.';
$txt['administrators']                         = 'Administrateurs du forum';
$txt['admin_reserved_desc']                    = 'Les noms réservés vont empêcher les utilisateurs de s\'inscrire sous certains identifiants ou d\'utiliser certains mots dans leur pseudonyme.  Choisissez les options que vous souhaitez utiliser ci-dessous avant de soumettre la liste.';
$txt['admin_activation_email']                 = 'Envoyer un e-mail d\'activation aux nouveaux membres lors de l\'inscription';
$txt['admin_match_whole']                      = 'Concordance avec le seul nom complet. Décoché, la recherche s\'effectuera à l\'intérieur des pseudos.';
$txt['admin_match_case']                       = 'Concordance avec la casse. Si décoché, recherchera sans porter attention à la casse.';
$txt['admin_check_user']                       = 'Vérifier les identifiants.';
$txt['admin_check_display']                    = 'Vérifier les pseudonymes.';
$txt['admin_newsletter_send']                  = 'Vous pouvez envoyer un e-mail à n\'importe qui à partir de cette page.  Les adresses e-mail des groupes de membres sélectionnés devraient apparaîre ci-dessous, mais vous pouvez enlever ou ajouter n\'importe quelle adresse de votre choix.  Veillez à vérifiez de séparer chaque adresse selon ce format&nbsp;: \'adresse1@qqch.com; adresse2@qqch.com\'.';
$txt['admin_fader_delay']                      = 'Durée du fondu entre les items, dans les nouvelles rotatives';
$txt['additional_options_collapsable']         = 'Enable collapsible additional post options';
$txt['zero_for_no_limit']                      = '(0 pour pas de limite) ';
$txt['zero_to_disable']                        = '(0 pour désactiver)';

$txt['admin_backup_fail']               = 'Impossible de créer une copie de secours (backup) de Settings.php.  Assurez-vous que Settings_bak.php existe et possède les bons droits d\'accès.';
$txt['registration_agreement']          = 'Accord d\'inscription';
$txt['registration_agreement_desc']     = 'L\'accord d\'inscription est affiché lorsqu\'une personne crée un nouveau compte membre sur le forum et doit être accepté pour que l\'inscription soit validée.';
$txt['errors_list']                     = 'Liste des erreurs du forum';
$txt['errors_found']                    = 'Les erreurs suivantes affectent votre forum (vide si aucune)';
$txt['errors_fix']                      = 'Voulez-vous essayer de corriger ces erreurs&nbsp;?';
$txt['errors_do_recount']               = 'Toutes les erreurs ont été corrigées. Une section de sauvetage a été créée&nbsp;! Cliquez sur le bouton ci-dessous pour recalculer quelques statistiques importantes.';
$txt['errors_recount_now']              = 'Recalculer les Statistiques';
$txt['errors_fixing']                   = 'Règle les erreurs du forum';
$txt['errors_fixed']                    = 'Toutes les erreurs sont réglées&nbsp;! Regardez toutes les catégories, les sections et sujets existants et choisissez ce que vous voulez en faire.';
$txt['attachments_avatars']             = 'Fichiers joints et avatars';
$txt['attachments_desc']                = 'À partir d\'ici, vous pouvez administrer les fichiers joints à votre forum par vos utilisateurs.  Vous pouvez supprimer les fichiers joints par taille et par date de votre système.  Les statistiques concernant les fichiers attachés sont présentées ci-dessous.';
$txt['attachment_stats']                = 'Statistiques des fichiers joints';
$txt['attachment_integrity_check']      = 'Vérification d\'intégrité des Fichiers joints';
$txt['attachment_integrity_check_desc'] = 'Cette opération vérifiera l\'intégrité et la taille des fichiers joints listés dans la base de données, et corrigera les disparités si nécessaire.';
$txt['attachment_check_now']            = 'Vérifier maintenant';
$txt['attachment_pruning']              = 'Suppression de fichiers joints';
$txt['attachment_pruning_message']      = 'Messages à ajouter';
$txt['attachment_pruning_warning']      = 'Êtes-vous sûr de vouloir supprimer ces fichiers joints ?\\nL\\\'opération est irréversible !';

$txt['attachment_total']            = 'Fichiers joints au total';
$txt['attachmentdir_size']          = 'Taille totale du répertoire des fichiers joints';
$txt['attachmentdir_size_current']  = 'Taille totale du répertoire des fichiers joints actuel';
$txt['attachmentdir_files_current'] = 'Nombre de fichiers total dans le répertoire des fichiers joints actuel';
$txt['attachment_space']            = 'Espace total disponible dans le répertoire des fichiers joints';
$txt['attachment_files']            = 'Nombre de fichiers restant';

$txt['attachment_options']          = 'Options des Fichiers joints';
$txt['attachment_log']              = 'Journal des Fichiers joints';
$txt['attachment_remove_old']       = 'Supprimer les fichiers joints plus anciens que';
$txt['attachment_remove_size']      = 'Supprimer les fichiers joints plus gros que';
$txt['attachment_name']             = 'Nom du fichier attaché';
$txt['attachment_file_size']        = 'Taille du fichier';
$txt['attachmentdir_size_not_set']  = 'Aucune taille maximale n\'est actuellement fixée';
$txt['attachmentdir_files_not_set'] = 'Aucune limite de fichiers n\'est actuellement fixée';
$txt['attachment_delete_admin']     = '[Fichier joint supprimé par l\'administrateur]';
$txt['live']                        = 'En direct du site SimpleMachines&#133;';
$txt['remove_all']                  = 'Supprimer tout';
$txt['approve_new_members']         = 'Les admins doivent approuver tous les nouveaux membres';
$txt['agreement_not_writable']      = 'Attention - agreement.txt n\'est PAS accessible en écriture.  Les changements effectués ne seront PAS sauvegardés';

$txt['version_check_desc'] = 'Ceci vous montre la version de vos fichiers installés comparés à ceux de la dernière version.  Si un de ces fichiers n\'est pas à jour, vous devriez télécharger et installer la dernière version sur <a href="http://www.simplemachines.org/" target="_blank">www.simplemachines.org</a>.';
$txt['version_check_more'] = '(plus de détails)';

$txt['lfyi'] = 'Il vous est impossible de vous connecter au fichier d\'infos de simplemachines.org .';

$txt['manage_calendar'] = 'Calendrier';
$txt['manage_search']   = 'Recherche';

$txt['smileys_manage']      = 'Smileys et icônes';
$txt['theme_admin']         = 'Thèmes et disposition';
$txt['registration_center'] = 'Inscriptions';

$txt['viewmembers_name']     = 'Pseudonyme';
$txt['viewmembers_online']   = 'Dernière connexion';
$txt['viewmembers_today']    = 'Aujourd\'hui';
$txt['viewmembers_day_ago']  = 'jour';
$txt['viewmembers_days_ago'] = 'jours';

$txt['display_name']  = 'Pseudonyme';
$txt['email_address'] = 'Adresse e-mail';
$txt['ip_address']    = 'Adresse IP';
$txt['member_id']     = 'ID';

$txt['unknown']        = 'inconnu';
$txt['security_wrong'] = 'Tentative de connexion à l\'administration&nbsp;!' . "\n" . 'Référant : %1$s' . "\n" . 'User-Agent : %2$s' . "\n" . 'IP : %3$s';

$txt['email_preview_warning'] = 'The preview is not 100% accurate. In order to preserve the functionality of the page only the basic html tags are represented';
$txt['email_as_html']         = 'Envoyer au format HTML. (Vous pouvez utiliser du HTML normal dans cet e-mail.)';
$txt['email_parsed_html']     = 'Ajouter les balises &lt;br /&gt; et &amp;nbsp; au message.';
$txt['email_variables']       = 'Dans ce message, vous pouvez utiliser quelques "variables". Cliquez <a href="' . $scripturl . '?action=helpadmin;help=email_members" onclick="return reqOverlayDiv(this.href);" class="help">ici</a> pour plus d\'informations.';
$txt['email_force']           = 'Envoyer ce message aux membres même s\'ils ont choisi de ne pas recevoir d\'annonces.';
$txt['email_as_pms']          = 'Envoyer ceci à ces groupes par la messagerie personnelle.';
$txt['email_continue']        = 'Continuer';
$txt['email_done']            = 'terminé.';

$txt['warnings']      = 'Alertes';
$txt['warnings_desc'] = 'This system allows administrators and moderators to issue warnings to users, and can automatically remove user rights as their warning level increases. To take full advantage of this system, &quot;Post Moderation&quot; should be enabled.';

$txt['ban_title']    = 'Bannir des membres';
$txt['ban_ip']       = 'Bannissement d\'IP&nbsp;: (ex. 192.168.12.213 or 128.0.*.*) - une entrée par ligne';
$txt['ban_email']    = 'Bannissement d\'e-mails&nbsp;: (ex. pasbeau@pasgentil.com) - une entrée par ligne';
$txt['ban_username'] = 'Bannissement de membres&nbsp;: (ex. pasbeau_du_75) - une entrée par ligne';

$txt['ban_errors_detected']    = 'The following error or errors occurred while saving or editing the ban';
$txt['ban_description']        = 'Ici vous pouvez bannir les personnes problématiques par IP, par domaine, par membre ou par adresse e-mail.';
$txt['ban_add_new']            = 'Ajouter un nouveau bannissement';
$txt['ban_banned_entity']      = 'Type de bannissement';
$txt['ban_on_ip']              = 'Bannissement par IP (ex. 192.168.10-20.*)';
$txt['ban_on_hostname']        = 'Bannissement par nom d\'hôte (ex. *.mil)';
$txt['ban_on_email']           = 'Bannissement par e-mail (ex. *@sitepasbien.com)';
$txt['ban_on_username']        = 'Bannissement par nom';
$txt['ban_notes']              = 'Notes';
$txt['ban_restriction']        = 'Restriction';
$txt['ban_full_ban']           = 'Bannissement complet';
$txt['ban_partial_ban']        = 'Bannissement partiel';
$txt['ban_cannot_post']        = 'Ne peut pas poster';
$txt['ban_cannot_register']    = 'Ne peut pas s\'inscrire';
$txt['ban_cannot_login']       = 'Ne peut pas se connecter';
$txt['ban_add']                = 'Ajouter';
$txt['ban_edit_list']          = 'Liste des bannissements';
$txt['ban_type']               = 'Type';
$txt['ban_days']               = 'jour(s)';
$txt['ban_will_expire_within'] = 'Le bannissement se terminera après';
$txt['ban_added']              = 'Ajouté';
$txt['ban_expires']            = 'Expiration';
$txt['ban_hits']               = 'Hits';
$txt['ban_actions']            = 'Actions';
$txt['ban_expiration']         = 'Expiration';
$txt['ban_reason_desc']        = 'Raison du bannissement, à afficher au membre banni.';
$txt['ban_notes_desc']         = 'Notes pouvant informer les autres membres du staff.';
$txt['ban_remove_selected']    = 'Supprimer la sélection';
// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['ban_remove_selected_confirm'] = 'Voulez-vous vraiment supprimer les bannissements sélectionnés&nbsp;?';
$txt['ban_modify']                  = 'Modifier';
$txt['ban_name']                    = 'Nom du bannissement';
// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['ban_edit']      = 'Modifier les bannissements';
$txt['ban_add_notes'] = '<strong>Note</strong>&nbsp;: après la création du bannissement ci-dessus, vous pourrez ajouter des entrées additionnelles qui déclenchent le bannissement, comme les adresses IP, les noms d\'hôtes et les adresses e-mail.';
$txt['ban_expired']   = 'Expiré / désactivé';
// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['ban_restriction_empty'] = 'Aucune restriction sélectionnée.';

$txt['ban_triggers']                 = 'Déclencheurs';
$txt['ban_add_trigger']              = 'Ajouter un déclencheur de bannissement';
$txt['ban_add_trigger_submit']       = 'Ajouter';
$txt['ban_edit_trigger']             = 'Modifier';
$txt['ban_edit_trigger_title']       = 'Modifier les déclencheurs de bannissement';
$txt['ban_edit_trigger_submit']      = 'Modifier';
$txt['ban_remove_selected_triggers'] = 'Supprimer les déclencheurs sélectionnés';
$txt['ban_no_entries']               = 'Aucun bannissement n\'est actuellement actif.';

// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['ban_remove_selected_triggers_confirm'] = 'Êtes-vous sûr de vouloir supprimer les déclencheurs de bannissement sélectionnés&nbsp;?';
$txt['ban_trigger_browse']                   = 'Voir les déclencheurs de bannissement';
$txt['ban_trigger_browse_description']       = 'Cette interface montre toutes les entrées de bannissements groupées selon l\'adresse IP, nom hôte, adresse e-mail et nom d\'utilisateur.';

$txt['ban_log']                         = 'Journal de Bannissements';
$txt['ban_log_description']             = 'Le journal de bannissements montre toutes les tentatives d\'accès au forum par les utilisateurs bannis (\'ban. complet\' and \'inscr. interdite\' seulement).';
$txt['ban_log_no_entries']              = 'Aucune entrée pour le moment dans le journal de bannissements.';
$txt['ban_log_ip']                      = 'IP';
$txt['ban_log_email']                   = 'Adresse e-mail';
$txt['ban_log_member']                  = 'Membre';
$txt['ban_log_date']                    = 'Date';
$txt['ban_log_remove_all']              = 'Supprimer tout';
$txt['ban_log_remove_all_confirm']      = 'Voulez-vous vraiment supprimer toutes les entrées&nbsp;?';
$txt['ban_log_remove_selected']         = 'Supprimer la sélection';
$txt['ban_log_remove_selected_confirm'] = 'Voulez-vous vraiment supprimer les entrées sélectionnées&nbsp;?';
$txt['ban_no_triggers']                 = 'Aucun déclencheur de bannissement pour le moment.';

$txt['settings_not_writable'] = 'Ces réglages ne peuvent pas être changés car Settings.php est accessible en lecture seulement.';

$txt['maintain_title']        = 'Maintenance du forum';
$txt['maintain_info']         = 'Optimisez les tables, effectuez des copies de sauvegarde, recherchez les erreurs et réparez le forum avec ces outils.';
$txt['maintain_sub_database'] = 'Base de données';
$txt['maintain_sub_routine']  = 'Routinières';
$txt['maintain_sub_members']  = 'Membres';
$txt['maintain_sub_topics']   = 'Sujets';
$txt['maintain_done']         = 'La tâche de maintenance \'%1$s\' a été accomplie avec succès.';
$txt['maintain_no_errors']    = 'Félicitations, aucune erreur n\'a été trouvée. Merci pour cette vérification.';

$txt['maintain_tasks']      = 'Taches programmées';
$txt['maintain_tasks_desc'] = 'Gérer toutes les tâches programmées par SMF.';

$txt['scheduled_log']       = 'Journal des Tâches';
$txt['scheduled_log_desc']  = 'Liste les tâches programmées et exécutées sur votre forum.';
$txt['admin_log']           = 'Journal d\'Administration';
$txt['admin_log_desc']      = 'Liste les tâches administratives ayant été exécutées par les admins de votre forum.';
$txt['moderation_log']      = 'Journal de Modération';
$txt['moderation_log_desc'] = 'Liste les activités de modération ayant été exécutées par les modérateurs de votre forum.';
$txt['spider_log_desc']     = 'Voir les entrées correspondant à l\'activité des moteurs de recherche sur votre forum.';
$txt['log_settings_desc']   = 'Use these options to configure how logging works on your forum.';
$txt['modlog_enabled']      = 'Activer le journal des modérations';
$txt['adminlog_enabled']    = 'Activer le journal d\'administration';
$txt['userlog_enabled']     = 'Activer le journal de modifications de profil';

$txt['mailqueue_title'] = 'E-mail';

$txt['db_error_send'] = 'Envoyer un e-mail lors d\'une erreur de connexion à la base de données';
$txt['db_persist']    = 'Utiliser une connexion permanente';
$txt['ssi_db_user']   = 'Nom d\'utilisateur de la Base de données à utiliser en mode SSI';
$txt['ssi_db_passwd'] = 'Mot de passe de la Base de données à utiliser en mode SSI';

$txt['default_language'] = 'Langue par défaut du forum';

$txt['maintenance_subject'] = 'Sujet à afficher';
$txt['maintenance_message'] = 'Message à afficher';

$txt['errlog_desc']       = 'Le Journal d\'erreurs traque toutes les erreurs rencontrées sur votre forum. Pour supprimer une erreur de la base de données, cochez le champ et cliquez sur le bouton %1$s au bas de la page.';
$txt['errlog_no_entries'] = 'Aucune erreur à signaler dans le journal.';

$txt['theme_settings']         = 'Réglages du thème';
$txt['theme_current_settings'] = 'Thème en cours';

$txt['dvc_your']      = 'Votre version';
$txt['dvc_current']   = 'Version courante';
$txt['dvc_sources']   = 'Sources';
$txt['dvc_default']   = 'Par défaut';
$txt['dvc_templates'] = 'Modèles actuels';
$txt['dvc_languages'] = 'Fichiers de langue';
$txt['dvc_tasks']     = 'Background Tasks';

$txt['smileys_default_set_for_theme'] = 'Sélectionner le jeu de smileys pour ce thème';
$txt['smileys_no_default']            = '(utiliser le jeu de smileys global par défaut)';

$txt['censor_test']        = 'Tester les mots censurés';
$txt['censor_test_save']   = 'Test';
$txt['censor_case']        = 'Ignorer la casse lors de la censure';
$txt['censor_whole_words'] = 'Vérifier les mots entiers seulement';

$txt['admin_confirm_password']   = '(confirmer)';
$txt['admin_incorrect_password'] = 'Mot de passe Incorrect';

$txt['date_format']                       = '(AAAA-MM-JJ)';
$txt['undefined_gender']                  = 'Non défini';
$txt['age']                               = 'Âge';
$txt['activation_status']                 = 'Statut d\'activation';
$txt['activated']                         = 'Activé';
$txt['not_activated']                     = 'Non activé';
$txt['primary']                           = 'Primaire';
$txt['additional']                        = 'Additionnelles';
$txt['wild_cards_allowed']                = 'les &quot;joker&quot; * et ? sont permis';
$txt['search_for']                        = 'Chercher pour';
$txt['search_match']                      = 'Correspondre';
$txt['member_part_of_these_membergroups'] = 'Le membre fait partie de ces groupes de membres';
$txt['membergroups']                      = 'Groupes de membres';
$txt['confirm_delete_members']            = 'Êtes-vous sûr de vouloir supprimer les membres sélectionnés&nbsp;?';

$txt['support_credits_title']        = 'Support et crédits';
$txt['support_title']                = 'Informations de support';
$txt['support_versions_current']     = 'Version courante de SMF';
$txt['support_versions_forum']       = 'Votre version du forum';
$txt['support_versions_php']         = 'Version de PHP';
$txt['support_versions_db']          = 'Version %1$s';
$txt['support_versions_server']      = 'Version du serveur';
$txt['support_versions_gd']          = 'Version de GD';
$txt['support_versions_imagemagick'] = 'Version de ImageMagick';
$txt['support_versions']             = 'Infos sur la version';
$txt['support_resources']            = 'Ressources de support';
$txt['support_resources_p1']         = 'Notre <a href="%1$s">documentation en ligne</a> (Online Manual), en anglais uniquement, est la principale source d\'informations sur SMF. Cette documentation propose de nombreuses pages destinées à résoudre les problèmes techniques, et à expliquer les <a href="%2$s">Fonctionnalités</a>, <a href="%3$s">Réglages</a>, <a href="%4$s">Thèmes</a>, <a href="%5$s">Paquets</a>, etc. Tous les aspects de SMF sont documentés de façon approfondie et résoudront la plupart de vos problèmes rapidement et sans laisser de taches.';
$txt['support_resources_p2']         = 'Si vous ne trouvez pas de réponse à vos questions dans la documentation en ligne, vous pouvez lancer une recherche sur la <a href="%1$s">Communauté de Support</a> ou demander de l\'aide sur un de nos forums en <a href="%2$s">anglais</a> ou dans de nombreuses <a href="%3$s">autres langues</a> (dont le <a href="http://www.simplemachines.org/community/index.php?board=14.0">français</a>). La Communauté de Support SMF propose de l\'aide pour le <a href="%4$s">support</a> ou la <a href="%5$s">personnalisation</a>, mais vous permet aussi de discuter de SMF en général, de trouver un hébergeur ou de parler de problèmes administratifs avec d\'autres responsables de forums.';

$txt['membergroups_members']     = 'Membres inscrits';
$txt['membergroups_guests']      = 'Invités';
$txt['membergroups_add_group']   = 'Ajouter un groupe';
$txt['membergroups_permissions'] = 'Permissions';

$txt['permitgroups_restrict']    = 'Restreint';
$txt['permitgroups_standard']    = 'Standard';
$txt['permitgroups_moderator']   = 'Modérateur';
$txt['permitgroups_maintenance'] = 'Maintenance';
$txt['permitgroups_inherit']     = 'Acquérir';

$txt['confirm_delete_attachments_all']   = 'Êtes-vous sûr de vouloir supprimer tous les fichiers joints&nbsp;?';
$txt['confirm_delete_attachments']       = 'Êtes-vous sûr de vouloir supprimer les fichiers joints sélectionnés&nbsp;?';
$txt['attachment_manager_browse_files']  = 'Parcourir les fichiers';
$txt['attachment_manager_repair']        = 'Maintenance';
$txt['attachment_manager_avatars']       = 'Avatars';
$txt['attachment_manager_attachments']   = 'Fichiers joints';
$txt['attachment_manager_thumbs']        = 'Vignettes';
$txt['attachment_manager_last_active']   = 'Dernière connexion';
$txt['attachment_manager_member']        = 'Membre';
$txt['attachment_manager_avatars_older'] = 'Supprimer les avatars des membres inactifs depuis plus de';
$txt['attachment_manager_total_avatars'] = 'Avatars au total';

$txt['attachment_manager_avatars_no_entries']     = 'Aucun avatar pour le moment.';
$txt['attachment_manager_attachments_no_entries'] = 'Aucune pièce jointe pour le moment.';
$txt['attachment_manager_thumbs_no_entries']      = 'Aucune vignette pour le moment.';

$txt['attachment_manager_settings']        = 'Paramètres des Fichiers joints';
$txt['attachment_manager_avatar_settings'] = 'Paramètres des Avatars';
$txt['attachment_manager_browse']          = 'Voir les fichiers';
$txt['attachment_manager_maintenance']     = 'Maintenance des Fichiers';
$txt['attachment_manager_save']            = 'Sauvegarder';

$txt['attachmentEnable']                       = 'Mode Fichiers joints';
$txt['attachmentEnable_deactivate']            = 'Désactiver les fichiers joints';
$txt['attachmentEnable_enable_all']            = 'Activer tous les fichiers joints';
$txt['attachmentEnable_disable_new']           = 'Désactiver les nouveaux fichiers joints';
$txt['attachmentCheckExtensions']              = 'Vérifier l\'extension des fichiers joints';
$txt['attachmentExtensions']                   = 'Extensions autorisées';
$txt['attachmentShowImages']                   = 'Afficher les images jointes sous les messages';
$txt['attachmentUploadDir']                    = 'Répertoire des fichiers joints<div class="smalltext"><a href="' . $scripturl . '?action=admin;area=manageattachments;sa=attachpaths">[Configurer plusieurs répertoires pour fichiers joints]</a></div>';
$txt['attachmentUploadDir_multiple_configure'] = '<a href="' . $scripturl . '?action=admin;area=manageattachments;sa=attachpaths">[Configurer plusieurs répertoires pour fichiers joints]</a>';
$txt['attachmentDirSizeLimit']                 = 'Taille maximale du répertoire des fichiers joints<div class="smalltext">(0 pour pas de limite)</div>';
$txt['attachmentPostLimit']                    = 'Taille totale maximale des fichiers joints par message<div class="smalltext">(0 pour pas de limite)</div>';
$txt['attachmentSizeLimit']                    = 'Taille maximale de chaque fichier joint<div class="smalltext">(0 pour pas de limite)</div>';
$txt['attachmentNumPerPostLimit']              = 'Nombre maximum de fichiers joints par message<div class="smalltext">(0 pour pas de limite)</div>';
$txt['attachment_img_enc_warning']             = 'Ni le module GD ni le module IMagick ou l\'extension MagickWand ne sont installés actuellement. Le réencodage des images n\'est pas possible.';
$txt['attachment_postsize_warning']            = 'The current php.ini setting \'post_max_size\' may not support this.';
$txt['attachment_filesize_warning']            = 'The current php.ini setting \'upload_max_filesize\' may not support this.';
$txt['attachment_image_reencode']              = 'Réencoder les images potentiellement dangereuses envoyées en fichier joint';
$txt['attachment_image_reencode_note']         = '(Le module GD ou ImageMagick avec l\'extension IMagick ou MagickWand est requis)';
$txt['attachment_image_paranoid_warning']      = 'Cette fonctionnalité peut donner lieu à des faux positifs (fichiers sains rejetés).';
$txt['attachment_image_paranoid']              = 'Effectuer un maximum de tests de sécurité sur les images envoyées en fichier joint';
$txt['attachmentThumbnails']                   = 'Montrer les images jointes sous forme de vignettes sous les messages';
$txt['attachment_thumb_png']                   = 'Sauvegarder les vignettes au format PNG';
$txt['attachment_thumb_memory']                = 'Mémoire adaptative des vignettes';
$txt['attachmentThumbWidth']                   = 'Largeur maximale des vignettes';
$txt['attachmentThumbHeight']                  = 'Hauteur maximale des vignettes';
$txt['attachment_thumbnail_settings']          = 'Réglages vignettes';
$txt['attachment_security_settings']           = 'Réglage de sécurité des fichiers joints';

$txt['attach_dir_does_not_exist'] = 'N\'existe pas';
$txt['attach_dir_not_writable']   = 'Non Inscriptible';
$txt['attach_dir_files_missing']  = 'Fichiers Manquants (<a href="' . $scripturl . '?action=admin;area=manageattachments;sa=repair;%2$s=%1$s">Réparer</a>)';
$txt['attach_dir_unused']         = 'Inutilisé';
$txt['attach_dir_empty']          = 'Vide';
$txt['attach_dir_ok']             = 'OK';
$txt['attach_dir_basedir']        = 'Dossier de base';
$txt['attach_dir_desc']           = 'Create new directories or change the current directory below. <br>To create a new directory within the forum directory structure, use just the directory name. <br>To remove a directory, blank the path input field. Only empty directories can be removed. To see if a directory is empty, check for files or sub-directories in brackets next to the file count. <br> To rename a directory, simply change its name in the input field. Only directories without sub-directories may be renamed. Directories can be renamed as long as they do not contain a sub-directory.';
$txt['attach_dir_base_desc']      = 'You may use below to change the current base directory or create a new one. New base directories are also added to the Attachment Directory list. You may also designate an existing directory to be a base directory.';
$txt['attach_dir_save_problem']   = 'Oups. Il semble qu\'il y ait un problème.';
$txt['attachments_no_create']     = 'Unable to create a new attachment directory. Please do so using a FTP client or your site file manager.';
$txt['attachments_no_write']      = 'This directory has been created but is not writable. Please attempt to do so using a FTP client or your site file manager.';
$txt['attach_dir_duplicate_msg']  = 'Unable to add. This directory already exists.';
$txt['attach_dir_exists_msg']     = 'Unable to move. A directory already exists at that path.';
$txt['attach_dir_base_dupe_msg']  = 'Unable to add. This base directory has already been created.';
$txt['attach_dir_base_no_create'] = 'Unable to create. Please verify the path input. Or create this directory using an FTP client or site file manager and re-try.';
$txt['attach_dir_no_rename']      = 'Unable to move or rename. Please verify that the path is correct or that this directory does not contain any sub-directories.';
$txt['attach_dir_no_delete']      = 'Is not empty and can not be deleted. Please do so using a FTP client or site file manager.';
$txt['attach_dir_no_remove']      = 'Still contains files or is a base directory and can not be deleted.';
$txt['attach_dir_is_current']     = 'Unable to remove while it is selected as the current directory.';
$txt['attach_dir_is_current_bd']  = 'Unable to remove while it is selected as the current base directory.';
$txt['attach_dir_invalid']        = 'Dossier invalide';
$txt['attach_last_dir']           = 'Dernier dossier de fichier joints actif';
$txt['attach_current_dir']        = 'Répertoire Actuel';
$txt['attach_current']            = 'Actuel';
$txt['attach_path_manage']        = 'Gérer les Chemins des Fichiers joints';
$txt['attach_directories']        = 'Dossiers des fichiers joints';
$txt['attach_paths']              = 'Chemins des Fichiers joints';
$txt['attach_path']               = 'Chemin';
$txt['attach_current_size']       = 'Taille Actuelle (Ko)';
$txt['attach_num_files']          = 'Fichiers';
$txt['attach_dir_status']         = 'Statut';
$txt['attach_add_path']           = 'Ajouter un Chemin';
$txt['attach_path_current_bad']   = 'Chemin actuel des fichiers joints invalide.';
$txt['attachmentDirFileLimit']    = 'Nombre maximum de fichiers par dossier';

$txt['attach_base_paths'] = 'Chemin de base du dossier';
$txt['attach_num_dirs']   = 'Dossiers';
$txt['max_image_width']   = 'Max display width of posted or attached images';
$txt['max_image_height']  = 'Max display height of posted or attached images';

$txt['automanage_attachments']  = 'Choose the method for the management of the attachment directories';
$txt['attachments_normal']      = '(Manual) SMF default behavior';
$txt['attachments_auto_years']  = '(Auto) Divise par années';
$txt['attachments_auto_months'] = '(Auto) Divise par années et mois';
$txt['attachments_auto_days']   = '(Auto) Divise par années, mois et jours';
$txt['attachments_auto_16']     = '(Auto) 16 dossiers au hasard';
$txt['attachments_auto_16x16']  = '(Auto) 16 dossiers au hasard avec 16 sous dossiers au hasard';
$txt['attachments_auto_space']  = '(Auto) Quand une limite d\'espace de dossier est atteinte';

$txt['use_subdirectories_for_attachments']      = 'Create new directories within a base directory';
$txt['use_subdirectories_for_attachments_note'] = 'Otherwise any new directories will be created within the forum\'s main directory.';
$txt['basedirectory_for_attachments']           = 'Réglez un dossier de base pour les fichiers joints';
$txt['basedirectory_for_attachments_current']   = 'Dossier de base actuel';
$txt['basedirectory_for_attachments_warning']   = '<div class="smalltext">Please note that the directory is wrong. <br>(<a href="' . $scripturl . '?action=admin;area=manageattachments;sa=attachpaths">Attempt to correct</a>)</div>';
$txt['attach_current_dir_warning']              = '<div class="smalltext">There seems to be a problem with this directory. <br>(<a href="' . $scripturl . '?action=admin;area=manageattachments;sa=attachpaths">Attempt to correct</a>)</div>';

$txt['attachment_transfer']             = 'Transfert fichiers joints';
$txt['attachment_transfer_desc']        = 'Transférez les fichiers entre les dossiers.';
$txt['attachment_transfer_select']      = 'Sectionner le dossier';
$txt['attachment_transfer_now']         = 'Transférer';
$txt['attachment_transfer_from']        = 'Transférer les fichiers depuis';
$txt['attachment_transfer_auto']        = 'Automatiquement par espace ou comptage des fichiers';
$txt['attachment_transfer_auto_select'] = 'Sélectionnez le dossier de base';
$txt['attachment_transfer_to']          = 'Ou vers un dossier spécifique.';
$txt['attachment_transfer_empty']       = 'Vider le dossier source';
$txt['attachment_transfer_no_base']     = 'Aucun dossier de base n\'est disponible.';
$txt['attachment_transfer_forum_root']  = 'Dossier racine du forum.';
$txt['attachment_transfer_no_room']     = 'Taille du dossier ou compte de fichiers atteint.';
$txt['attachment_transfer_no_find']     = 'Aucun fichier n\'a été trouvé pour le transfert.';
$txt['attachments_transferred']         = '%1$d files were transferred to %2$s';
$txt['attachments_not_transferred']     = '%1$d files were not transferred.';
$txt['attachment_transfer_no_dir']      = 'Either the source directory or one of the target options were not selected.';
$txt['attachment_transfer_same_dir']    = 'You cannot select the same directory as both the source and target.';
$txt['attachment_transfer_progress']    = 'Veuillez patienter. Le transfert est en cours.';

$txt['mods_cat_avatars']            = 'Avatars';
$txt['avatar_directory']            = 'Répertoire des avatars';
$txt['avatar_directory_wrong']      = 'The Avatars directory is not valid. This will cause several issues with your forum.';
$txt['avatar_url']                  = 'URL des avatars';
$txt['avatar_max_width_external']   = 'Largeur maximale d\'un avatar externe';
$txt['avatar_max_height_external']  = 'Hauteur maximale d\'un avatar externe';
$txt['avatar_action_too_large']     = 'Si un avatar est trop grand&hellip;';
$txt['option_refuse']               = 'le refuser';
$txt['option_css_resize']           = 'Le redimensionner dans le navigateur de l\'utilisateur';
$txt['option_download_and_resize']  = 'le télécharger et le redimensionner sur le serveur';
$txt['avatar_max_width_upload']     = 'Largeur maximale d\'un avatar transféré';
$txt['avatar_max_height_upload']    = 'Hauteur maximale d\'un avatar transféré';
$txt['avatar_resize_upload']        = 'Redimensionner les avatars trop grands';
$txt['avatar_resize_upload_note']   = '(requiert le module GD ou ImageMagick avec l\'extension IMagick ou MagickWand)';
$txt['avatar_download_png']         = 'Utiliser le format PNG pour les avatars redimensionnés';
$txt['avatar_img_enc_warning']      = 'Neither the GD module nor the Imagick or MagickWand extensions are currently installed. Some avatar features are disabled.';
$txt['avatar_external']             = 'Avatars externes';
$txt['avatar_upload']               = 'Avatars transférables';
$txt['avatar_server_stored']        = 'Avatars stockés sur le serveur';
$txt['avatar_server_stored_groups'] = 'Groupes de membres autorisés à sélectionner un avatar dans la galerie';
$txt['avatar_upload_groups']        = 'Groupes de membres autorisés à transférer leur propre avatar sur le serveur';
$txt['avatar_external_url_groups']  = 'Groupes de membres autorisés à sélectionner un avatar externe';
$txt['avatar_select_permission']    = 'Sélectionner les permissions pour chaque groupe';
$txt['avatar_download_external']    = 'Télécharger l\'avatar à l\'URL donnée';
$txt['option_attachment_dir']       = 'le répertoire des fichiers joints';
$txt['option_specified_dir']        = 'un répertoire spécifique&hellip;';
$txt['custom_avatar_dir_wrong']     = 'The Attachments directory is not valid. This will prevent attachments from working properly.';
$txt['custom_avatar_dir']           = 'Répertoire de stockage';
$txt['custom_avatar_dir_desc']      = 'Evitez d\'utiliser le répertoire où se situent les avatars fournis par défaut par SMF.';
$txt['custom_avatar_url']           = 'URL des avatars stockés';
$txt['custom_avatar_check_empty']   = 'Le répertoire de stockage des avatars transférés que vous avez spécifié semble être vide ou invalide. Merci de vérifier vos paramètres.';
$txt['avatar_reencode']             = 'Réencoder les avatars potentiellement dangereux';
$txt['avatar_reencode_note']        = '(requiert le module GD ou ImageMagick avec l\'extension IMagick ou MagickWand)';
$txt['avatar_paranoid_warning']     = 'La vérification par la sécurité étendue peut résulter en un grand nombre d\'avatars rejetés.';
$txt['avatar_paranoid']             = 'Exécutez la sécurité étendue pour vérifier les avatars envoyés';
$txt['gravatar_settings']           = 'Gravatars (Globally Recognized Avatars)';
$txt['gravatarEnabled']             = 'Activer les Gravatars pour les utilisateurs du forum?';
$txt['gravatarOverride']            = 'Force Gravatars to be used instead of normal avatars?';
$txt['gravatarAllowExtraEmail']     = 'Allow storing an extra email address for Gravatars?';
$txt['gravatarMaxRating']           = 'Maximum allowed rating?';
$txt['gravatar_maxG']               = 'G rated (Generally acceptable)';
$txt['gravatar_maxPG']              = 'PG rated (Parental Guidance)';
$txt['gravatar_maxR']               = 'R rated (Restricted)';
$txt['gravatar_maxX']               = 'X rated (Explicit)';
$txt['gravatarDefault']             = 'Default image to show when an email address has no matching Gravatar ';
$txt['gravatar_mm']                 = 'A simple, cartoon-style silhouetted outline of a person';
$txt['gravatar_identicon']          = 'A geometric pattern based on an email hash';
$txt['gravatar_monsterid']          = 'A generated \'monster\' with different colors, faces, etc';
$txt['gravatar_wavatar']            = 'Generated faces with differing features and backgrounds';
$txt['gravatar_retro']              = 'Awesome generated, 8-bit arcade-style pixelated faces';
$txt['gravatar_blank']              = 'A transparent PNG image';

$txt['repair_attachments']                     = 'Maintenance des Fichiers joints';
$txt['repair_attachments_complete']            = 'Maintenance complétée';
$txt['repair_attachments_complete_desc']       = 'Toutes les erreurs sélectionnées ont maintenant été corrigées';
$txt['repair_attachments_no_errors']           = 'Aucune erreur n\'a été trouvée&nbsp;!';
$txt['repair_attachments_error_desc']          = 'Les erreurs suivantes ont été rencontrées durant la maintenance. Cochez la boîte accompagnant les erreurs que vous souhaitez corriger et cliquez sur Continuer.';
$txt['repair_attachments_continue']            = 'Continuer';
$txt['repair_attachments_cancel']              = 'Annuler';
$txt['attach_repair_missing_thumbnail_parent'] = '%1$d vignettes où manquent un fichier parent';
$txt['attach_repair_parent_missing_thumbnail'] = '%1$d fichiers parents sont notés comme ayant une vignette mais n\'en ont pas';
$txt['attach_repair_file_missing_on_disk']     = '%1$d fichiers/avatars ont une entrée mais n\'existent plus sur le disque';
$txt['attach_repair_file_wrong_size']          = '%1$d fichiers/avatars sont rapportés comme possédant une mauvaise taille de fichier';
$txt['attach_repair_file_size_of_zero']        = '%1$d fichiers/avatars ont une taille de zéro octet sur le disque. (Ils seront supprimés.)';
$txt['attach_repair_attachment_no_msg']        = '%1$d fichiers n\'ont plus de message auquel ils sont associés';
$txt['attach_repair_avatar_no_member']         = '%1$d avatars n\'ont plus de membre auquel ils sont associés';
$txt['attach_repair_wrong_folder']             = '%1$d fichiers joints sont dans le mauvais dossier';
$txt['attach_repair_files_without_attachment'] = '%1$d files do not have a corresponding entry in the database. (These will be deleted)';

$txt['news_title']               = 'Nouvelles et infolettres';
$txt['news_settings_desc']       = 'Ici vous pouvez changer les réglages et permissions relatifs aux nouvelles et aux infolettres.';
$txt['news_settings_submit']     = 'Enregistrer';
$txt['news_mailing_desc']        = 'Depuis ce menu vous pouvez envoyer des messages à tous les membres qui se sont inscrits et ont spécifié leur adresse e-mail. Vous pouvez modifier la liste de distribution, ou envoyer un message à tous. Utile pour informer des mises à jour et nouvelles importantes.';
$txt['news_error_no_news']       = 'Nothing written';
$txt['groups_edit_news']         = 'Groupes autorisés à modifier les nouvelles';
$txt['groups_send_mail']         = 'Groupes autorisés à envoyer les infolettres du forum';
$txt['xmlnews_enable']           = 'Activer les flux XML/RSS';
$txt['xmlnews_maxlen']           = 'Longueur maximale d\'un message&nbsp;:<div class="smalltext">(0 pour désactiver, mauvaise idée.)</div>';
$txt['xmlnews_maxlen_note']      = '(0 to disable, bad idea.)';
$txt['editnews_clickadd']        = 'Cliquez ici pour ajouter un item.';
$txt['editnews_remove_selected'] = 'Supprimer la sélection';
$txt['editnews_remove_confirm']  = 'Voulez-vous vraiment supprimer les nouvelles sélectionnées&nbsp;?';
$txt['censor_clickadd']          = 'Cliquez ici pour ajouter un autre mot.';

$txt['layout_controls']  = 'Forum';
$txt['logs']             = 'Journaux';
$txt['generate_reports'] = 'Générer des rapports';

$txt['update_available'] = 'Mise à jour disponible&nbsp;!';
$txt['update_message']   = 'Vous utilisez une version périmée de SMF, qui contient quelques bogues qui ont pu être corrigés depuis la dernière révision. Il est très fortement conseillé de <a href="#" id="update-link">mettre à jour votre forum</a> à la dernière version le plus rapidement possible. Cela ne prendra qu\'une minute&nbsp;!';

$txt['manageposts']             = 'Messages et sujets';
$txt['manageposts_title']       = 'Gérer les messages et les sujets';
$txt['manageposts_description'] = 'Ici vous pouvez gérer tous les réglages relatifs aux sujets et aux messages.';

$txt['manageposts_seconds']    = 'secondes';
$txt['manageposts_minutes']    = 'minutes';
$txt['manageposts_characters'] = 'caractères';
$txt['manageposts_days']       = 'jours';
$txt['manageposts_posts']      = 'messages';
$txt['manageposts_topics']     = 'Sujets';

$txt['manageposts_settings']             = 'Paramètres des Messages';
$txt['manageposts_settings_description'] = 'Ici vous pouvez paramétrer tout ce qui est relatif aux messages et à leur envoi.';
$txt['manageposts_settings_submit']      = 'Enregistrer';

$txt['manageposts_bbc_settings']             = 'Table des balises Bulletin Board Code (BBcode)';
$txt['manageposts_bbc_settings_description'] = 'Les <acronym title="Bulletin Board Code">BBCodes</acronym> peuvent être utilisés pour ajouter des mises en forme à vos messages. Par exemple, pour mettre de la force sur le mot \'maison\', vous pouvez taper [b]maison[/b]. Toutes les balises BBCodes sont entourées par des crochets (\'[\' et \']\').';
$txt['manageposts_bbc_settings_title']       = 'Paramètres des BBCodes';
$txt['manageposts_bbc_settings_submit']      = 'Enregistrer';

$txt['manageposts_topic_settings']             = 'Paramètres des Sujets';
$txt['manageposts_topic_settings_description'] = 'Ici vous pouvez paramétrer toutes les options en rapport avec les sujets.';
$txt['manageposts_topic_settings_submit']      = 'Enregistrer';

$txt['managedrafts_settings']             = 'Réglages brouillons';
$txt['managedrafts_settings_description'] = 'Here you can set all settings involving drafts.';
$txt['managedrafts_submit']               = 'Enregistrer';
$txt['manage_drafts']                     = 'Brouillons';

$txt['removeNestedQuotes']               = 'Supprimer les citations imbriquées en citant un message';
$txt['enableEmbeddedFlash']              = 'Inclure des animations Flash dans les messages';
$txt['enableEmbeddedFlash_warning']      = 'peut constituer un risque de sécurité&nbsp;!';
$txt['enableSpellChecking']              = 'Activer le correcteur orthographique';
$txt['enableSpellChecking_warning']      = 'ceci ne fonctionne pas sur tous les serveurs&nbsp;!';
$txt['disable_wysiwyg']                  = 'Désactiver l\'éditeur WYSIWYG';
$txt['max_messageLength']                = 'Longueur maximale des messages';
$txt['max_messageLength_zero']           = '0 pour aucun max.';
$txt['convert_to_mediumtext']            = 'Your database is not setup to accept messages longer than 65535 characters. Please use the <a href="%1$s">database maintenance</a> page to convert the database and then came back to increase the maximum allowed post size.';
$txt['topicSummaryPosts']                = 'Nombre de messages à afficher dans le résumé de la discussion';
$txt['spamWaitTime']                     = 'Temps d\'attente requis entre deux envois en provenance d\'une même adresse IP';
$txt['edit_wait_time']                   = 'Période de révision';
$txt['edit_disable_time']                = 'Temps maximum après l\'envoi pour modifier un message';
$txt['preview_characters']               = 'Maximum length of last/first post preview';
$txt['preview_characters_units']         = 'caractères';
$txt['message_index_preview_first']      = 'When using post previews, show the text of the first post';
$txt['message_index_preview_first_desc'] = 'Leave un-checked to show the text of the last post instead';
$txt['show_user_images']                 = 'Show user avatars in message view';
$txt['show_blurb']                       = 'Show personal text in message view';
$txt['hide_post_group']                  = 'Hide post group titles for grouped members';
$txt['hide_post_group_desc']             = 'Enabling this will not display a member\'s post group title on the message view if they are assigned to a non-post based group.';
$txt['subject_toggle']                   = 'Show subjects in topics.';
$txt['show_profile_buttons']             = 'Show view profile button under post';
$txt['show_modify']                      = 'Show last modification date on modified posts';

$txt['enableBBC']               = 'Activer les BBCodes';
$txt['enablePostHTML']          = 'Permettre l\'utilisation de balises HTML <em>basiques</em> dans les messages';
$txt['autoLinkUrls']            = 'Reconnaissance automatique des URLs';
$txt['disabledBBC']             = 'Balises BBCode autorisées';
$txt['bbcTagsToUse']            = 'Balises BBCodes activées';
$txt['bbcTagsToUse_select']     = 'Sélectionnez toutes les balises pouvant être utilisées';
$txt['bbcTagsToUse_select_all'] = 'Sélectionner toutes les balises';

$txt['enableParticipation']    = 'Activer l\'icône de participation';
$txt['oldTopicDays']           = 'Temps avant qu\'un sujet ne soit mentionné comme ancien lors de l\'écriture d\'une réponse';
$txt['defaultMaxTopics']       = 'Nombre de sujets par page lors du visionnage d\'un site';
$txt['defaultMaxMessages']     = 'Nombre de messages à afficher lors du visionnage d\'un sujet';
$txt['disable_print_topic']    = 'Disable print topic feature';
$txt['enableAllMessages']      = 'Taille maximale d\'un sujet pour afficher &quot;Tous&quot; les messages';
$txt['enableAllMessages_zero'] = '0 pour ne jamais afficher &quot;Tous&quot;';
$txt['disableCustomPerPage']   = 'Désactiver la personnalisation du nombre de sujets/messages par page';
$txt['enablePreviousNext']     = 'Activer les liens &quot;Sujet précédent/suivant&quot;';

$txt['not_done_title']    = 'Pas encore effectué&nbsp;!';
$txt['not_done_reason']   = 'Afin d\'éviter la surcharge de votre serveur, le processus a été interrompu temporairement. Il devrait reprendre automatiquement dans quelques secondes. S\'il ne reprend pas, veuillez cliquer continuer ci-dessous.';
$txt['not_done_continue'] = 'Continuer';

$txt['general_settings']          = 'Réglages';
$txt['database_settings']         = 'Database';
$txt['cookies_sessions_settings'] = 'Cookies et Sessions';
$txt['security_settings']         = 'Security';
$txt['caching_settings']          = 'Configuration du Cache';
$txt['load_balancing_settings']   = 'Répartition de Charge';
$txt['phpinfo_settings']          = 'PHP Info';
$txt['phpinfo_localsettings']     = 'Local Settings';
$txt['phpinfo_defaultsettings']   = 'Default Settings';
$txt['phpinfo_itemsettings']      = 'Réglages';

$txt['language_configuration']    = 'Langues';
$txt['language_description']      = 'Cette section vous permet de modifier les langues installées sur votre forum, et d\'en télécharger de nouvelles sur le site web de Simple Machines. Vous pouvez également modifier les paramètres liés aux langues ici.';
$txt['language_edit']             = 'Modifier Langues';
$txt['language_add']              = 'Ajouter Langue';
$txt['language_settings']         = 'Paramètres';
$txt['could_not_language_backup'] = 'A backup could not be made before removing this language pack. No changes have been made at this time as a result (either change the permissions so Packages/backup can be written to, or turn off backups - not recommended)';

$txt['advanced'] = 'Avancé';
$txt['simple']   = 'Simple';

$txt['admin_news_newsletter_queue_done']        = 'The newsletter has been added to the mail queue successfully.';
$txt['admin_news_select_recipients']            = 'Veuillez sélectionner qui doit recevoir une copie de l\'infolettre';
$txt['admin_news_select_group']                 = 'Groupes de Membres';
$txt['admin_news_select_group_desc']            = 'Sélectionnez les groupes qui doivent recevoir cette infolettre.';
$txt['admin_news_select_members']               = 'Membres';
$txt['admin_news_select_members_desc']          = 'Membres supplémentaires qui doivent recevoir l\'infolettre.';
$txt['admin_news_select_excluded_members']      = 'Membres Exclus';
$txt['admin_news_select_excluded_members_desc'] = 'Membres ne devant pas recevoir d\'infolettre.';
$txt['admin_news_select_excluded_groups']       = 'Groupes Exclus';
$txt['admin_news_select_excluded_groups_desc']  = 'Sélectionnez les groupes ne devant absolument pas recevoir l\'infolettre.';
$txt['admin_news_select_email']                 = 'Adresses e-mail';
$txt['admin_news_select_email_desc']            = 'Une liste d\'adresses e-mail séparées par des points-virgules, à laquelle sera envoyée l\'infolettre. (Par ex: adresse1; adresse2) Ceci est en addition aux groupes listés au dessus.';
$txt['admin_news_select_override_notify']       = 'Outrepasser les Paramètres de Notification existants';
// Use entities in below.
$txt['admin_news_cannot_pm_emails_js'] = 'Vous ne pouvez pas envoyer de message personnel &#341; une adresse e-mail. Si vous continuez, toutes les adresses e-mail seront ignor&#233;es.\\n\\n&#202;tes-vous s&#369;r de vouloir faire cela ?';

$txt['mailqueue_browse']   = 'Parcourir la file d\'attente';
$txt['mailqueue_settings'] = 'Paramètres';

$txt['admin_search']               = 'Recherche Rapide';
$txt['admin_search_type_internal'] = 'Tâche/Réglage';
$txt['admin_search_type_member']   = 'Membre';
$txt['admin_search_type_online']   = 'Manuel en ligne';
$txt['admin_search_go']            = 'Aller';
$txt['admin_search_results']       = 'Résultats Recherche';
$txt['admin_search_results_desc']  = 'Résultats de la recherche&nbsp;: &quot;%1$s&quot;';
$txt['admin_search_results_again'] = 'Rechercher à nouveau';
$txt['admin_search_results_none']  = 'Aucun résultat&nbsp;!';

$txt['admin_search_section_sections'] = 'Section';
$txt['admin_search_section_settings'] = 'Paramètres';

$txt['mods_cat_features']           = 'Réglages';
$txt['antispam_title']              = 'Anti-Spam';
$txt['mods_cat_modifications_misc'] = 'Diverses';
$txt['mods_cat_layout']             = 'Apparence';
$txt['moderation_settings_short']   = 'Modération';
$txt['signature_settings_short']    = 'Signatures';
$txt['custom_profile_shorttitle']   = 'Champs de Profil';
$txt['pruning_title']               = 'Délestage de journal';
$txt['pruning_desc']                = 'The following options are useful for keeping your logs from growing too big, because most of the time older entries are not really of that much use.';
$txt['log_settings']                = 'Log Settings';
$txt['log_ban_hits']                = 'Log ban hits in the error log?';

$txt['boardsEdit']        = 'Modifier les sections';
$txt['mboards_new_cat']   = 'Créer une nouvelle catégorie';
$txt['manage_holidays']   = 'Gérer les jours fériés';
$txt['calendar_settings'] = 'Réglages Calendrier';
$txt['search_weights']    = 'Poids';
$txt['search_method']     = 'Méthode de recherche';

$txt['smiley_sets']              = 'Jeux de smileys';
$txt['smileys_add']              = 'Ajouter un smiley';
$txt['smileys_edit']             = 'Modifier les smileys';
$txt['smileys_set_order']        = 'Choisir l\'ordre des smileys';
$txt['icons_edit_message_icons'] = 'Icônes de Message';

$txt['membergroups_new_group']      = 'Ajouter un Groupe de membres';
$txt['membergroups_edit_groups']    = 'Modifier les Groupes de membres';
$txt['permissions_groups']          = 'Permissions par Groupe de membres';
$txt['permissions_boards']          = 'Permissions par Section';
$txt['permissions_profiles']        = 'Modifier les Profils';
$txt['permissions_post_moderation'] = 'Modération des messages';

$txt['browse_packages']           = 'Parcourir les Paquets';
$txt['download_packages']         = 'Rajouter des Paquets';
$txt['installed_packages']        = 'Paquets Installés';
$txt['package_file_perms']        = 'Permissions des Fichiers';
$txt['package_settings']          = 'Options';
$txt['themeadmin_admin_title']    = 'Gérer et Installer';
$txt['themeadmin_list_title']     = 'Réglages des Thèmes';
$txt['themeadmin_reset_title']    = 'Options des Membres';
$txt['themeadmin_edit_title']     = 'Modifier les Thèmes';
$txt['admin_browse_register_new'] = 'Inscrire un nouveau membre';

$txt['search_engines'] = 'Moteurs de Recherche';
$txt['spiders']        = 'Robots';
$txt['spider_logs']    = 'Journal des Robots';
$txt['spider_stats']   = 'Stats';

$txt['paid_subscriptions'] = 'Abonnements payants';
$txt['paid_subs_view']     = 'Voir les Abonnements';

$txt['hooks_title_list']              = 'Integration Hooks';
$txt['hooks_field_hook_name']         = 'Hook Name';
$txt['hooks_field_function_name']     = 'Function Name';
$txt['hooks_field_function_method']   = 'Function is a method and its class is instantiated';
$txt['hooks_field_function']          = 'Fonction';
$txt['hooks_field_included_file']     = 'Fichier inclus';
$txt['hooks_field_file_name']         = 'Nom de fichier';
$txt['hooks_field_hook_exists']       = 'Status';
$txt['hooks_active']                  = 'Existe';
$txt['hooks_disabled']                = 'Désactivé';
$txt['hooks_missing']                 = 'Non trouvé';
$txt['hooks_no_hooks']                = 'There are currently no hooks in the system.';
$txt['hooks_button_remove']           = 'Supprimer';
$txt['hooks_disable_instructions']    = 'Click on the status icon to enable or disable the hook';
$txt['hooks_disable_legend']          = 'Légende';
$txt['hooks_disable_legend_exists']   = 'the hook exists and is active';
$txt['hooks_disable_legend_disabled'] = 'the hook exists but has been disabled';
$txt['hooks_disable_legend_missing']  = 'the hook has not been found';
$txt['hooks_reset_filter']            = 'No filter';

$txt['board_perms_allow']  = 'Autoriser';
$txt['board_perms_ignore'] = 'Ignorer';
$txt['board_perms_deny']   = 'Refuser';
$txt['all_boards_in_cat']  = 'All boards in this category';

$txt['likes_like'] = 'Membergroups allowed to like posts';
$txt['likes_view'] = 'Membergroups allowed to view likes';

$txt['mention'] = 'Membergroups allowed to mention users';

$txt['notifications']      = 'Notifications';
$txt['notify_settings']    = 'Notification Settings';
$txt['notifications_desc'] = 'This page allows you to set the default notification options for users.';

?>