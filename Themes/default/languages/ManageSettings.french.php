<?php
// Version: 2.1 Beta 2; ManageSettings

global $scripturl;

$txt['modSettings_desc'] = 'Cette page vous permet de param�trer les caract�ristiques et options de base de votre forum. Veuillez voir les <a href="' . $scripturl . '?action=admin;area=theme;sa=settings;th=%1$s;%3$s=%2$s">R�glages du th�me</a> pour plus d\'options. Cliquez sur l\'ic�ne d\'aide pour plus d\'informations � propos d\'un param�tre.';
$txt['modification_settings_desc'] = 'Cette page contient les r�glages ajout�s par les paquets � votre forum';

$txt['modification_no_misc_settings'] = 'Aucun des paquets install�s n\'a encore ajout� de r�glages � cet endroit.';

$txt['pollMode'] = 'Mode de sondage';
$txt['disable_polls'] = 'D�sactiver les sondages';
$txt['enable_polls'] = 'Activer les sondages';
$txt['polls_as_topics'] = 'Montrer les sondages existants comme des sujets';
$txt['allow_guestAccess'] = 'Permettre aux invit�s de parcourir le forum';
$txt['userLanguage'] = 'Permettre aux utilisateurs de choisir la langue';
$txt['allow_hideOnline'] = 'Permettre aux non-administrateurs de cacher leur pr�sence en ligne';
$txt['titlesEnable'] = 'Activer les titres personnalis�s';
$txt['enable_buddylist'] = 'Activer les listes d\'amis/ignor�s';
$txt['default_personal_text'] = 'Texte personnel par d�faut';
$txt['default_personal_text_note'] = 'Personal text to assign to newly registered members.';
$txt['time_format'] = 'Format d\'heure par d�faut';
$txt['setting_time_offset'] = 'D�calage horaire g�n�ral<div class="smalltext">(ajout� aux options sp�cifiques des membres.)</div>';
$txt['setting_time_offset_note'] = '(added to the member specific option)';
$txt['setting_default_timezone'] = 'Fuseau horaire du serveur';
$txt['failed_login_threshold'] = 'Seuil de connexions d�pass�';
$txt['loginHistoryDays'] = 'Days to keep login history';
$txt['lastActive'] = 'Seuil de temps de connexion en ligne';
$txt['trackStats'] = 'Suivi des statistiques au quotidien';
$txt['hitStats'] = 'Suivi des pages vues au quotidien (les stats doivent �tre activ�es)';
$txt['enableCompressedOutput'] = 'Activer la compression des pages';
$txt['disableTemplateEval'] = 'D�sactiver l\'�valuation des mod�les';
$txt['databaseSession_enable'] = 'Faire g�rer les sessions par la base de donn�es';
$txt['databaseSession_loose'] = 'Permettre aux navigateurs de revenir sur les pages en cache';
$txt['databaseSession_lifetime'] = 'Secondes avant la fin d\'une session pour inutilisation';
$txt['error_log_desc'] = 'The error log, if enabled, will log every error encountered by users using your forum. This can be an invaluable aid to identifying forum problems.';
$txt['enableErrorLogging'] = 'Enregistrer les erreurs dans le journal';
$txt['enableErrorQueryLogging'] = 'Inclure la requ�te de base de donn�es dans le journal d\'erreurs';
$txt['pruningOptions'] = 'Activer le d�lestage des entr�es de journal';
$txt['pruneErrorLog'] = 'Supprimer les entr�es du journal d\'erreurs plus anciennes que<div class="smalltext">(0 pour d�sactiver)</div>';
$txt['pruneModLog'] = 'Supprimer les entr�es du journal de mod�ration plus anciennes que<div class="smalltext">(0 pour d�sactiver)</div>';
$txt['pruneBanLog'] = 'Supprimer les entr�es du journal d\'occurrences de bannissement plus anciennes que<div class="smalltext">(0 pour d�sactiver)</div>';
$txt['pruneReportLog'] = 'Supprimer les entr�es du journal de rapports aux mod�rateurs plus anciennes que<div class="smalltext">(0 pour d�sactiver)</div>';
$txt['pruneScheduledTaskLog'] = 'Supprimer les entr�es du journal de t�ches programm�es plus anciennes que<div class="smalltext">(0 pour d�sactiver)</div>';
$txt['pruneSpiderHitLog'] = 'Supprimer les occurrences des moteurs de recherche plus anciennes que<div class="smalltext">(0 pour d�sactiver)</div>';
$txt['cookieTime'] = 'Dur�e par d�faut des t�moins (<em>cookies</em>) - en minutes';
$txt['localCookies'] = 'Activer l\'archivage local des t�moins<div class="smalltext">(SSI ne fonctionnera pas bien si ceci est activ�.)</div>';
$txt['globalCookies'] = 'Utiliser des t�moins valides pour tous les sous-domaines<div class="smalltext">(d�sactivez d\'abord l\'archivage local des cookies&nbsp;!)</div>';
$txt['globalCookiesDomain'] = 'Main domain used for subdomain independent cookies';
$txt['invalid_cookie_domain'] = 'The domain introduced seems to be invalid, please check it and save again.';
$txt['secureCookies'] = 'Forcer la s�curisation des t�moins<div class="smalltext">(Attention, � n\'utiliser que si votre site est en HTTPS&nbsp;!)</div>';
$txt['httponlyCookies'] = 'Force cookies to be made accessible only through the HTTP protocol';
$txt['securityDisable'] = 'D�sactiver la protection d\'administration';
$txt['securityDisable_moderate'] = 'Disable moderation security';
$txt['send_validation_onChange'] = 'Demander une r�activation apr�s un changement d\'adresse e-mail';
$txt['approveAccountDeletion'] = 'N�cessiter l\'approbation d\'un admin quand un membre supprime son compte';
$txt['autoFixDatabase'] = 'R�parer automatiquement les tables contenant des erreurs';
$txt['allow_disableAnnounce'] = 'Permettre aux utilisateurs de d�sactiver les annonces';
$txt['disallow_sendBody'] = 'Ne pas inclure le corps des messages dans les notifications';
$txt['enable_ajax_alerts'] = 'Allow AJAX Desktop Notifications for Alerts';
$txt['jquery_source'] = 'Source for the jQuery Library';
$txt['jquery_custom_label'] = 'Custom';
$txt['jquery_custom'] = 'Custom url to the jQuery Library';
$txt['jquery_local'] = 'Local';
$txt['jquery_cdn'] = 'Google CDN';
$txt['jquery_auto'] = 'Auto';
$txt['queryless_urls'] = 'URLs plus compr�hensibles pour les moteurs de recherche<div class="smalltext"><strong>Serveurs Apache/Lighttpd seulement&nbsp;!</strong></div>';
$txt['queryless_urls_note'] = 'Apache/Lighttpd uniquement';
$txt['enableReportPM'] = 'Permettre de signaler des messages personnels aux mod�rateurs';
$txt['max_pm_recipients'] = 'Nombre maximal autoris� de destinataires dans un message personnel<div class="smalltext">(0 pour illimit&eacute&nbsp;; les admins sont exempt�s)</div>';
$txt['max_pm_recipients_note'] = '(0 for no limit, admins are exempt)';
$txt['pm_posts_verification'] = 'Nombre de messages de forum en dessous duquel les utilisateurs doivent entrer un code lorsqu\'ils envoient des messages personnels<div class="smalltext">(0 pour pas de limite, les admins sont exempt�s)</div>';
$txt['pm_posts_verification_note'] = '(0 for no limit, admins are exempt)';
$txt['pm_posts_per_hour'] = 'Nombre de messages personnels qu\'un utilisateur peut envoyer en une heure<div class="smalltext">(0 pour pas de limite, les mod�rateurs sont exempt�s)</div>';
$txt['pm_posts_per_hour_note'] = '(0 for no limit, moderators are exempt)';
$txt['compactTopicPagesEnable'] = 'Limiter le nombre de liens pointant vers les pages suivantes';
$txt['contiguous_page_display'] = 'Pages adjacentes � afficher';
$txt['to_display'] = '� afficher';
$txt['todayMod'] = 'Activer les dates abr�g�es';
$txt['today_disabled'] = 'D�sactiv�';
$txt['today_only'] = 'Seulement Aujourd\'hui';
$txt['yesterday_today'] = 'Aujourd\'hui &amp; Hier';
$txt['onlineEnable'] = 'Afficher \'En ligne\'/\'Hors ligne\' dans les messages et les MP';
$txt['defaultMaxMembers'] = 'Membres par page dans la liste des membres';
$txt['timeLoadPageEnable'] = 'Afficher le temps pris pour g�n�rer la page';
$txt['disableHostnameLookup'] = 'D�sactiver la recherche du nom d\'h�te';
$txt['who_enabled'] = 'Activer &quot;Qui est en ligne&nbsp;?&quot;';
$txt['meta_keywords'] = 'Mots-cl� Meta associ�s au forum<div class="smalltext">Pour les moteurs de recherche. Laisser vide pour les mots-cl� par d�faut.</div>';
$txt['meta_keywords_note'] = 'For search engines. Leave blank for default.';
$txt['settings_error'] = 'Warning: Updating of Settings.php failed, the settings cannot be saved.';
$txt['image_proxy_enabled'] = 'Enable Image Proxy';
$txt['image_proxy_secret'] = 'Image Proxy Secret';
$txt['image_proxy_maxsize'] = 'Maximum file size of images to cache (in KB)';
$txt['force_ssl'] = 'Forum SSL mode';
$txt['force_ssl_off'] = 'Disable SSL';
$txt['force_ssl_auth'] = 'Enable SSL for Authentication (Login and Register)';
$txt['force_ssl_complete'] = 'Force SSL throughout the forum';

// Like settings.
$txt['enable_likes'] = 'Enable likes';

// Mention settings.
$txt['enable_mentions'] = 'Enable mentions';

$txt['caching_information'] = '<div align="center"><strong><span class="underline">Important&nbsp;! Veuillez lire ce qui suit avant d\'activer ces options.</strong></span></div><br />
	SMF peut mettre des donn�es en cache en utilisant des acc�l�rateurs. Les acc�l�rateurs actuellement support�s sont les suivants&nbsp;:<br />
	<ul class="normallist">
		<li>APC</li>
		<li>eAccelerator</li>
		<li>Turck MMCache</li>
		<li>Memcached</li>
		<li>Zend Platform/Performance Suite (pas Zend Optimizer)</li>
	</ul>
	Le syst�me de cache fonctionnera mieux si PHP est compil� avec l\'un des acc�l�rateurs susmentionn�s, ou si memcache est disponible.
	<strong>Si aucun acc�l�rateur n\'est install�, SMF fera du cache fichier.</strong><br /><br />
	SMF g�re plusieurs niveaux de cache. Plus le niveau est haut, plus le processeur de votre serveur sera utilis�
	pour r�cup�rer les informations. Si un syst�me de cache est disponible sur votre serveur, il est recommand� de le tester au niveau 1 avant tout.
	<br /><br />
	Veuillez noter que l\'utilisation de memcache n�cessite que vous donniez quelques indications sur votre serveur dans les r�glages � effectuer ci-dessous. Elles doivent �tre entr�es sous forme de liste, dont les �l�ments sont s�par�s par une virgule,
	comme dans l\'exemple suivant&nbsp;:<br />
	&quot;serveur1,serveur2,serveur3:port,serveur4&quot;<br />
	Si aucun port n\'est sp�cifi�, SMF utilisera le port 11211 par d�faut. SMF �quilibrera de mani�re al�atoire la charge sur les serveurs.
	<br /><br />
	%1$s';
$txt['detected_no_caching'] = '<strong class="alert">SMF n\'a pas pu d�tecter d\'acc�l�rateur compatible sur votre serveur.</strong>';
$txt['detected_accelerators'] = '<strong class="success">SMF has detected the following accelerators: %1$s</strong>';

$txt['cache_enable'] = 'Niveau de cache';
$txt['cache_off'] = 'Aucun cache';
$txt['cache_level1'] = 'Cache de niveau 1 (Recommand�)';
$txt['cache_level2'] = 'Cache de niveau 2';
$txt['cache_level3'] = 'Cache de niveau 3 (Non recommand�)';
$txt['cache_memcached'] = 'R�glages de Memcache';
$txt['cache_accelerator'] = 'Acc�l�rateur de cache';
$txt['default_cache'] = 'Cache fichier SMF de base';
$txt['apc_cache'] = 'APC';
$txt['memcached_cache'] = 'Memcached';
$txt['zend_cache'] = 'Zend Platform/Performance Suite';
$txt['xcache_cache'] = 'XCache';

$txt['loadavg_warning'] = '<span class="error">Attention, les param�tres ci-dessous doivent �tre modifi�s avec prudence. Un param�tre trop bas pourrait rendre votre forum <strong>inutilisable</strong>&nbsp;! La charge moyenne actuelle est de <strong>%01.2f</strong></span>';
$txt['loadavg_enable'] = 'Activer la r�partition de charge par moyennes';
$txt['loadavg_auto_opt'] = 'Limite pour d�sactiver l\'optimisation automatique de la base de donn�es';
$txt['loadavg_search'] = 'Limite pour d�sactiver la recherche';
$txt['loadavg_allunread'] = 'Limite pour d�sactiver la liste des sujets non lus';
$txt['loadavg_unreadreplies'] = 'Limite pour d�sactiver la liste des r�ponses non lues';
$txt['loadavg_show_posts'] = 'Limite pour d�sactiver l\'affichage des messages';
$txt['loadavg_userstats'] = 'Threshold to disabling showing user statistics';
$txt['loadavg_bbc'] = 'Threshold to disabling BBC formatting when showing posts';
$txt['loadavg_forum'] = 'Limite pour d�sactiver <strong>compl�tement</strong> le forum';
$txt['loadavg_disabled_windows'] = '<span class="error">Les serveurs Windows ne supportent pas la r�partition de charge.</span>';
$txt['loadavg_disabled_osx'] = '<span class="error">Load balancing support is not available on OS:X.</span>';
$txt['loadavg_disabled_conf'] = '<span class="error">La configuration de votre serveur ne supporte pas la r�partition de charge.</span>';

$txt['setting_password_strength'] = 'Protection requise pour les mots de passe utilisateur';
$txt['setting_password_strength_low'] = 'Basse - 4 caract�res minimum';
$txt['setting_password_strength_medium'] = 'Moyenne - Ne doit pas contenir l\'identifiant';
$txt['setting_password_strength_high'] = 'Haute - m�lange de diff�rents caract�res';
$txt['setting_enable_password_conversion'] = 'Allow password hash conversion';

$txt['antispam_Settings'] = 'V�rification Anti-Spam';
$txt['antispam_Settings_desc'] = 'Cette section vous permet de r�gler les contr�les de v�rification pour vous assurer que l\'utilisateur est un humain (et non un robot), et peaufiner comment et o� ils doivent s\'appliquer.';
$txt['setting_reg_verification'] = 'Demander une v�rification au moment de l\'inscription';
$txt['posts_require_captcha'] = 'Nombre de messages de forum en dessous duquel les utilisateurs doivent entrer un code pour poster un message';
$txt['posts_require_captcha_desc'] = '(0 pour pas de limite, les mod�rateurs sont exempt�s)';
$txt['search_enable_captcha'] = 'Demander une v�rification sur toutes les recherches des invit�s';
$txt['setting_guests_require_captcha'] = 'Les invit�s doivent passer la v�rification pour envoyer un message';
$txt['setting_guests_require_captcha_desc'] = '(Activ� d\'office si vous sp�cifiez un nombre minimum de messages ci-dessous)';
$txt['question_not_defined'] = 'You need to add a question and answer for your forum\'s default language (%1$s) otherwise users will not be able to fill in a CAPTCHA, meaning no registration.';

$txt['configure_verification_means'] = 'Configurer les m�thodes de v�rification';
$txt['setting_qa_verification_number'] = 'Nombre de questions de v�rification auquel un utilisateur doit r�pondre';
$txt['setting_qa_verification_number_desc'] = '(0 pour d�sactiver; les questions sont r�gl�es ci-dessous)';
$txt['configure_verification_means_desc'] = '<span class="smalltext">Ci-dessous, vous pouvez sp�cifier quelles fonctionnalit�s anti-spam activer lorsqu\'un utilisateur doit certifier qu\'il est humain. Notez que l\'utilisateur devra r�ussir <em>toutes</em> les v�rifications. Si vous activez en m�me temps une image de v�rification et un test question/r�ponse, ils devront les passer tous les deux pour continuer.</span>';
$txt['setting_visual_verification_type'] = 'Image de v�rification visuelle � afficher';
$txt['setting_visual_verification_type_desc'] = 'Plus l\'image est complexe, plus il sera difficile aux robots de la contourner';
$txt['setting_image_verification_off'] = 'Aucune';
$txt['setting_image_verification_vsimple'] = 'Tr�s Simple - Texte normal sur une image';
$txt['setting_image_verification_simple'] = 'Simple - Lettres color�es se chevauchant, pas de bruit';
$txt['setting_image_verification_medium'] = 'Moyenne - Lettres color�es se chevauchant, avec bruit et lignes';
$txt['setting_image_verification_high'] = 'Haute - Lettres en rotation, avec un bruit consid�rable et lignes';
$txt['setting_image_verification_extreme'] = 'Extr�me - Lettres en rotation, bruit, lignes et carr�s';
$txt['setting_image_verification_sample'] = 'Exemple';
$txt['setting_image_verification_nogd'] = '<strong>Note</strong>&nbsp;: ce serveur n\'ayant pas la librairie GD install�e, les r�glages de complexit� n\'auront aucun effet.';
$txt['setup_verification_questions'] = 'Questions de V�rification';
$txt['setup_verification_questions_desc'] = '<span class="smalltext">Si vous voulez que les utilisateurs r�pondent � des questions de v�rification dans le but de stopper les robots spammeurs, vous pouvez mettre en place des questions dans la table ci-dessous. Choisissez des questions relativement simples; les r�ponses ne sont pas sensibles � la casse. Vous pouvez utiliser du BBCode dans les questions pour le formatage. Pour supprimer une question, effacez simplement le contenu de cette ligne.</span>';
$txt['setup_verification_question'] = 'Question';
$txt['setup_verification_answer'] = 'R�ponse';
$txt['setup_verification_add_more'] = 'Ajouter une autre question';
$txt['setup_verification_add_answer'] = 'Add another answer';

$txt['moderation_settings'] = 'R�glages de Mod�ration';
$txt['setting_warning_enable'] = 'Activer le syst�me d\'avertissement utilisateur';
$txt['setting_warning_watch'] = 'Niveau d\'avertissement pour la mise sous surveillance d\'un utilisateur<div class="smalltext">Le niveau d\'avertissement utilisateur apr�s lequel une surveillance utilisateur est mise en place - 0 pour d�sactiver.</div>';
$txt['setting_warning_watch_note'] = 'The user warning level after which a user watch is put in place.';
$txt['setting_warning_moderate'] = 'Niveau d\'avertissement pour la mod�ration de messages<div class="smalltext">Le niveau d\'avertissement utilisateur apr�s lequel un utilisateur voit tous ses messages mod�r�s - 0 pour d�sactiver.</div>';
$txt['setting_warning_moderate_note'] = 'The user warning level after which a user has all posts moderated.';
$txt['setting_warning_mute'] = 'Niveau d\'avertissement pour rendre muet un utilisateur<div class="smalltext">Le niveau d\'avertissement utilisateur apr�s lequel un utilisateur ne peut plus poster - 0 pour d�sactiver.</div>';
$txt['setting_warning_mute_note'] = 'The user warning level after which a user cannot post any further.';
$txt['setting_user_limit'] = 'Points d\'avertissement maximum re�us d\'un utilisateur par jour<div class="smalltext">Cette valeur est la quantit� maximale de points d\'avertissement qu\'un m�me mod�rateur peut assigner � un utilisateur en 24 heures - 0 pour pas de limite.</div>';
$txt['setting_user_limit_note'] = 'This value is the maximum amount of warning points a single moderator can assign to a user in a 24 hour period - 0 for no limit.';
$txt['setting_warning_decrement'] = 'Points d\'avertissement � diminuer des utilisateurs toutes les 24 heures<div class="smalltext">S\'applique seulement aux utilisateurs non avertis depuis les derni�res 24 heures - 0 pour d�sactiver.</div>';
$txt['setting_warning_decrement_note'] = 'Only applies to users not warned within last 24 hours.';
$txt['setting_warning_show'] = 'Utilisateurs qui peuvent voir les �tats d\'avertissement<div class="smalltext">D�termine qui peut voir le niveau d\'avertissement des utilisateurs du forum.</div>';
$txt['setting_warning_show_note'] = 'Determines who can see the warning level of users on the forum.';
$txt['setting_warning_show_mods'] = 'Mod�rateurs seulement';
$txt['setting_warning_show_user'] = 'Mod�rateurs et utilisateurs avertis';
$txt['setting_warning_show_all'] = 'Tout le monde';

$txt['signature_settings'] = 'Param�tres de Signature';
$txt['signature_settings_desc'] = 'Utiliser les param�tres de cette page pour d�cider comment les signatures de membre doivent �tre trait�es dans SMF.';
$txt['signature_settings_warning'] = 'Notez que ces param�tres ne sont pas appliqu�s sur les signatures existantes par d�faut. Cliquez <a href="' . $scripturl . '?action=admin;area=featuresettings;sa=sig;apply;%2$s=%1$s">ici</a> pour appliquer les r�gles � toutes les signatures existantes.';
$txt['signature_settings_applied'] = 'The updated rules have been applied to the existing signatures.';
$txt['signature_enable'] = 'Activer les signatures';
$txt['signature_max_length'] = 'Nombre maximal de caract�res autoris�<div class="smalltext">(0 pour pas de max.)</div>';
$txt['signature_max_lines'] = 'Nombre maximal de lignes<div class="smalltext">(0 pour pas de max)</div>';
$txt['signature_max_images'] = 'Nombre maximal d\'images<div class="smalltext">(0 pour pas de max - � l\'exception des smileys)</div>';
$txt['signature_max_images_note'] = '(0 for no max - excludes smileys)';
$txt['signature_allow_smileys'] = 'Autoriser les smileys dans les signatures';
$txt['signature_max_smileys'] = 'Nombre maximal de smileys<div class="smalltext">(0 pour pas de max)</div>';
$txt['signature_max_image_width'] = 'Largeur maximale des images de signature (pixels)<div class="smalltext">(0 pour pas de max)</div>';
$txt['signature_max_image_height'] = 'Hauteur maximale des images de signature (pixels)<div class="smalltext">(0 pour pas de max)</div>';
$txt['signature_max_font_size'] = 'Taille de police maximale autoris�e dans les signatures<div class="smalltext">(en pixels, 0 pour pas de max)</div>';
$txt['signature_bbc'] = 'Balises BBCode autoris�es';

$txt['custom_profile_title'] = 'Champs de profil personnalis�s';
$txt['custom_profile_desc'] = 'Sur cette page, vous pouvez cr�er vos propres champs de profil correspondant aux besoins de votre forum';
$txt['custom_profile_active'] = 'Actif';
$txt['custom_profile_fieldname'] = 'Nom du champ';
$txt['custom_profile_fieldtype'] = 'Type de champ';
$txt['custom_profile_fieldorder'] = 'Field Order';
$txt['custom_profile_make_new'] = 'Nouveau champ';
$txt['custom_profile_none'] = 'Vous n\'avez pas encore cr�� de champ de profil personnalis�&nbsp;!';
$txt['custom_profile_icon'] = 'Ic�ne';

$txt['custom_profile_type_text'] = 'Texte';
$txt['custom_profile_type_textarea'] = 'Zone de Texte';
$txt['custom_profile_type_select'] = 'Bo�te de S�lection';
$txt['custom_profile_type_radio'] = 'Bouton Radio';
$txt['custom_profile_type_check'] = 'Case � Cocher';

$txt['custom_add_title'] = 'Ajouter un champ de profil';
$txt['custom_edit_title'] = 'Modifier un champ de profil';
$txt['custom_edit_general'] = 'Param�tres d\'affichage';
$txt['custom_edit_input'] = 'Param�tres d\'entr�e';
$txt['custom_edit_advanced'] = 'Param�tres avanc�s';
$txt['custom_edit_name'] = 'Nom';
$txt['custom_edit_desc'] = 'Description';
$txt['custom_edit_profile'] = 'Section du Profil';
$txt['custom_edit_profile_desc'] = 'Section du profil dans laquelle on trouvera ce champ.';
$txt['custom_edit_profile_none'] = 'Aucun';
$txt['custom_edit_registration'] = 'Afficher � l\'inscription';
$txt['custom_edit_registration_disable'] = 'Non';
$txt['custom_edit_registration_allow'] = 'Oui';
$txt['custom_edit_registration_require'] = 'Oui, et r�ponse obligatoire';
$txt['custom_edit_display'] = 'Montrer dans les sujets';
$txt['custom_edit_mlist'] = 'Show on memberlist';
$txt['custom_edit_picktype'] = 'Type de champ';

$txt['custom_edit_max_length'] = 'Longueur maximale';
$txt['custom_edit_max_length_desc'] = '(0 pour pas de limite)';
$txt['custom_edit_dimension'] = 'Dimensions';
$txt['custom_edit_dimension_row'] = 'Lignes';
$txt['custom_edit_dimension_col'] = 'Colonnes';
$txt['custom_edit_bbc'] = 'Autoriser les balises BBCode';
$txt['custom_edit_options'] = 'Options';
$txt['custom_edit_options_desc'] = 'Laisser vide la case � cocher de cette option pour la supprimer. Le bouton radio s�lectionne l\'option par d�faut.';
$txt['custom_edit_options_more'] = 'Plus';
$txt['custom_edit_default'] = '�tat par d�faut';
$txt['custom_edit_active'] = 'Actif';
$txt['custom_edit_active_desc'] = 'S\'il n\'est pas s�lectionn�, ce champ ne sera montr� � personne.';
$txt['custom_edit_privacy'] = 'Accessibilit�';
$txt['custom_edit_privacy_desc'] = 'Qui peut voir et modifier ce champ.';
$txt['custom_edit_privacy_all'] = 'Les utilisateurs peuvent voir ce champ; le propri�taire peut le modifier';
$txt['custom_edit_privacy_see'] = 'Les utilisateurs peuvent voir ce champ; seuls les admins peuvent le modifier';
$txt['custom_edit_privacy_owner'] = 'Les utilisateurs ne peuvent pas voir ce champ; le propri�taire et les admins peuvent le modifier.';
$txt['custom_edit_privacy_none'] = 'Ce champ n\'est visible que pour les admins';
$txt['custom_edit_can_search'] = 'Cherchable';
$txt['custom_edit_can_search_desc'] = 'Ce champ peut-il �tre utilis� dans les recherches sur la liste des membres&nbsp;?';
$txt['custom_edit_mask'] = 'Masque d\'entr�e';
$txt['custom_edit_mask_desc'] = 'Pour les champs de texte, un masque d\'entr�e peut �tre s�lectionn� pour valider les donn�es.';
$txt['custom_edit_mask_email'] = 'Adresse e-mail valide';
$txt['custom_edit_mask_number'] = 'Num�rique';
$txt['custom_edit_mask_nohtml'] = 'Pas de HTML';
$txt['custom_edit_mask_regex'] = 'Regex (Avanc�)';
$txt['custom_edit_enclose'] = 'Montrer ins�r� dans du texte (Optionnel)';
$txt['custom_edit_enclose_desc'] = 'Nous vous recommandons <strong>fortement</strong> d\'utiliser un masque d\'entr�e pour valider le contenu fourni par l\'utilisateur.';

$txt['custom_edit_order_move'] = 'Move ';
$txt['custom_edit_order_up'] = 'Up';
$txt['custom_edit_order_down'] = 'Down';
$txt['custom_edit_placement'] = 'Choose Placement';
$txt['custom_profile_placement'] = 'Placement';
$txt['custom_profile_placement_standard'] = 'Standard';
$txt['custom_profile_placement_icons'] = 'With Icons';
$txt['custom_profile_placement_above_signature'] = 'Above Signature';
$txt['custom_profile_placement_below_signature'] = 'Below Signature';
$txt['custom_profile_placement_below_avatar'] = 'Below Avatar';
$txt['custom_profile_placement_above_member'] = 'Above Username';
$txt['custom_profile_placement_bottom_poster'] = 'Bottom poster info';

// Use numeric entities in the string below!
$txt['custom_edit_delete_sure'] = '&#202;tes-vous s&#369;r de vouloir supprimer ce champ ? Toutes les donn&#233;es utilisateur li&#233;es seront perdues !';

$txt['standard_profile_title'] = 'Champs de profil Standards';
$txt['standard_profile_field'] = 'Champ';
$txt['standard_profile_field_timezone'] = 'Timezone';

$txt['languages_lang_name'] = 'Langue';
$txt['languages_locale'] = 'Param�tres r�gionaux';
$txt['languages_default'] = 'Par d�faut';
$txt['languages_character_set'] = 'Jeu de caract�res';
$txt['languages_users'] = 'Utilisateurs';
$txt['language_settings_writable'] = 'Attention&nbsp;: Settings.php n\'est pas inscriptible. Par cons�quent, le r�glage de la langue par d�faut ne peut �tre sauvegard�.';
$txt['edit_languages'] = 'Modifier les Langues';
$txt['lang_file_not_writable'] = '<strong>Attention</strong>&nbsp;: le fichier de langue de base (%1$s) est prot�g� en �criture. Vous devez le rendre inscriptible avant de pouvoir faire des changements.';
$txt['lang_entries_not_writable'] = '<strong>Attention</strong>&nbsp;: le fichier langue que vous souhaitez modifier (%1$s) est prot�g� en �criture. Vous devez le rendre inscriptible avant de pouvoir faire des changements.';
$txt['languages_ltr'] = 'De droite � gauche';

$txt['add_language'] = 'Ajouter une Langue';
$txt['add_language_smf'] = 'T�l�charger � partir de Simple Machines';
$txt['add_language_smf_browse'] = 'Nom de la langue � rechercher, ou laisser vide pour rechercher tout.';
$txt['add_language_smf_install'] = 'Installer';
$txt['add_language_found_title'] = 'Found Languages';
$txt['add_language_smf_found'] = 'Les langues suivantes ont �t� trouv�es. Cliquez sur le lien d\'installation � c�t� de la langue que vous souhaitez installer, vous serez redirig� vers le gestionnaire de paquets pour l\'installer.';
$txt['add_language_error_no_response'] = 'Le site de Simple Machines ne r�pond pas. Veuillez r�essayer plus tard.';
$txt['add_language_error_no_files'] = 'Aucun fichier n\'a �t� trouv�.';
$txt['add_language_smf_desc'] = 'Description';
$txt['add_language_smf_utf8'] = 'UTF-8';
$txt['add_language_smf_version'] = 'Version';

$txt['edit_language_entries_primary'] = 'Ci-dessous se trouvent les param�tres de base de langue pour ce pack de Langue.';
$txt['edit_language_entries'] = 'Modifier les entr�es de langue';
$txt['edit_language_entries_file'] = 'S�lectionnez le fichier � modifier';
$txt['languages_dictionary'] = 'Dictionnaire';
$txt['languages_spelling'] = 'Orthographe';
$txt['languages_for_pspell'] = 'N�cessite l\'emploi de <a href="http://www.php.net/function.pspell-new" target="_blank" class="new_win">pSpell</a> - s\'il est install�';
$txt['languages_rtl'] = 'Activer le mode &quot;De gauche � droite&quot;';

$txt['lang_file_desc_index'] = 'Termes g�n�raux';
$txt['lang_file_desc_EmailTemplates'] = 'Mod�les d\'e-mail';

$txt['languages_download'] = 'T�l�chargement de packs de Langue';
$txt['languages_download_note'] = 'Cette page recense tous les fichiers contenus dans le pack de Langue et des informations utiles � propos de chacun d\'entre eux. Tous les fichiers qui ont leur case associ�e coch�e seront copi�s.';
$txt['languages_download_info'] = '<strong>Note</strong>
	<ul class="normallist">
		<li>Si des fichiers ont le statut &quot;Non inscriptible&quot;, SMF ne sera pas capable de les copier dans le r�pertoire actuel et vous devrez rendre la destination inscriptible en utilisant un client FTP ou en remplissant les renseignements en bas de page.</li>
		<li>L\'information de version pour un fichier indique la version d\'SMF pour laquelle il a �t� mis � jour. Si elle est indiqu�e en vert, c\'est qu\'il existe une version plus r�cente que celle que vous avez actuellement. La couleur ambr�e indique que c\'est le m�me num�ro de version que l\'actuelle, et la couleur rouge signifie que vous avez d�j� une version plus r�cente que celle contenue dans le pack.</li>
		<li>Si un fichier existe d�j� sur votre forum, la colonne &quot;Existe d�j�&quot; aura une de ces deux valeurs. &quot;Identique&quot; indique que le fichier existe d�j� sous une forme identique et n\'a pas besoin d\'�tre �cras�. &quot;Diff�rent&quot; veut dire que le contenu varie un peu et que l\'�crasement est probablement la meilleure solution.</li>
	</ul>';

$txt['languages_download_main_files'] = 'Fichiers de base';
$txt['languages_download_theme_files'] = 'Fichiers li�s aux th�mes';
$txt['languages_download_filename'] = 'Nom du fichier';
$txt['languages_download_dest'] = 'Destination';
$txt['languages_download_writable'] = 'Inscriptible';
$txt['languages_download_version'] = 'Version';
$txt['languages_download_older'] = 'Vous avez une nouvelle version de ce fichier en place, l\'�crasement n\'est pas recommand�.';
$txt['languages_download_exists'] = 'Existe d�j�';
$txt['languages_download_exists_same'] = 'Identique';
$txt['languages_download_exists_different'] = 'Diff�rent';
$txt['languages_download_copy'] = 'Copier';
$txt['languages_download_not_chmod'] = 'Vous ne pouvez pas effectuer l\'installation tant que tous les fichiers � copier s�lectionn�s ne sont pas inscriptibles.';
$txt['languages_download_illegal_paths'] = 'Le paquet contient des chemins ill�gaux - veuillez contacter Simple Machines';
$txt['languages_download_complete'] = 'Installation termin�e';
$txt['languages_download_complete_desc'] = 'Le pack de Langue a �t� install� avec succ�s. Veuillez cliquer <a href="%1$s">ici</a> pour retourner � la page de gestion des langues';
$txt['languages_delete_confirm'] = '�tes-vous s�r de vouloir supprimer cette langue ?';

$txt['setting_frame_security'] = 'Frame Security Options';
$txt['setting_frame_security_SAMEORIGIN'] = 'Allow Same Origin';
$txt['setting_frame_security_DENY'] = 'Deny all frames';
$txt['setting_frame_security_DISABLE'] = 'Disabled';

$txt['setting_proxy_ip_header'] = 'Reverse Proxy IP Header';
$txt['setting_proxy_ip_header_disabled'] = 'Do not allow any Proxy IP Headers';
$txt['setting_proxy_ip_header_autodetect'] = 'Auto-detect Proxy IP header';
$txt['setting_proxy_ip_servers'] = 'Reverse Proxy Servers IPs';

$txt['select_boards_from_list'] = 'Select boards which apply';

$txt['topic_move_any'] = 'Allow moving of topics to read-only boards';

$txt['defaultMaxListItems'] = 'Maximum number of items per page in lists';

$txt['tfa_mode'] = 'Two-Factor Authentication';
$txt['tfa_mode_forced'] = 'Force on selected membergroups';
$txt['tfa_mode_forcedall'] = 'Force for ALL users';
$txt['tfa_mode_forced_help'] = 'Please enable 2FA in your account in order to be able to force 2FA on other users!';
$txt['tfa_mode_enabled'] = 'Enabled';
$txt['tfa_mode_disabled'] = 'Disabled';
$txt['tfa_mode_subtext'] = 'Allows users to have a second layer of security while logging in, users would need an app like Google Authenticator paired with their account';

?>