<?php
// Version: 2.1 Beta 2; Login

global $context;

// Registration agreement page.
$txt['registration_agreement'] = 'Accord d\'inscription';
$txt['agreement_agree'] = 'J\'accepte les conditions de ce document.';
$txt['agreement_agree_coppa_above'] = 'J\'accepte les conditions de ce document et j\'ai au moins %1$d ans.';
$txt['agreement_agree_coppa_below'] = 'J\'accepte les conditions de ce document et j\'ai moins de %1$d ans.';
$txt['agree_coppa_above'] = 'J\'ai au moins %1$d ans.';
$txt['agree_coppa_below'] = 'Je suis plus jeune que %1$d ans.';

// Registration form.
$txt['registration_form'] = 'Formulaire d\'inscription';
$txt['need_username'] = 'Vous devez mettre un nom d\'utilisateur.';
$txt['no_password'] = 'Vous n\'avez entré aucun mot de passe';
$txt['incorrect_password'] = 'Mot de passe incorrect';
$txt['choose_username'] = 'Choisissez un identifiant';
$txt['maintain_mode'] = 'Mode Maintenance';
$txt['registration_successful'] = 'Inscription réussie';
$txt['now_a_member'] = 'Vous êtes maintenant un membre du forum.';
// Use numeric entities in the below string.
$txt['your_password'] = 'et votre mot de passe est';
$txt['valid_email_needed'] = 'Merci d\'entrer une adresse e-mail valide, %1$s.';
$txt['required_info'] = 'Informations Requises';
$txt['identification_by_smf'] = 'Utilisé uniquement pour la connexion à SMF.';
$txt['additional_information'] = 'Informations supplémentaires';
$txt['warning'] = 'Attention&nbsp;!';
$txt['only_members_can_access'] = 'Seuls les membres inscrits sont autorisés à accéder à cette section.';
$txt['login_below'] = 'Merci de vous connecter ci-dessous ou';
$txt['login_below_or_register'] = 'Veuillez vous identifier ci-dessous ou <a href="%1$s">vous inscrire pour un compte</a> avec %2$s';

// Use numeric entities in the below two strings.
$txt['may_change_in_profile'] = 'Vous pouvez le changer apr&#232;s vous &#234;tre connect&#233; en allant sur votre page de profil, ou en visitant cette page apr&#232;s identification&#160;:';
$txt['your_username_is'] = 'Votre identifiant est';

$txt['login_hash_error'] = 'La sécurité des mots de passe a récemment été accrue.  Veuillez entrer de nouveau votre mot de passe.';

$txt['ban_register_prohibited'] = 'Désolé, vous n\'êtes pas autorisé à vous inscrire sur ce forum';

$txt['activate_account'] = 'Activation de Compte';
$txt['activate_success'] = 'Votre compte a été activé avec succès. Vous pouvez maintenant vous connecter.';
$txt['activate_not_completed1'] = 'Votre adresse e-mail doit être validée avant que vous puissiez vous connecter.';
$txt['activate_not_completed2'] = 'Un autre e-mail d\'activation&nbsp;?';
$txt['activate_after_registration'] = 'Merci de vous être inscrit. Vous recevrez rapidement un e-mail contenant un lien pour activer votre compte. Si vous ne recevez rien après un certain temps, vérifiez votre dossier de courriers indésirables (spams).';
$txt['invalid_userid'] = 'L\'utilisateur n\'existe pas';
$txt['invalid_activation_code'] = 'Code d\'activation invalide';
$txt['invalid_activation_username'] = 'Identifiant ou adresse e-mail';
$txt['invalid_activation_new'] = 'Si vous vous êtes inscrit avec une adresse e-mail incorrecte, entrez-en une nouvelle ainsi que votre mot de passe ici.';
$txt['invalid_activation_new_email'] = 'Nouvelle adresse e-mail';
$txt['invalid_activation_password'] = 'Ancien mot de passe';
$txt['invalid_activation_resend'] = 'Envoyez de nouveau le code d\'activation';
$txt['invalid_activation_known'] = 'Si vous connaissez déjà votre code d\'activation, tapez-le ici.';
$txt['invalid_activation_retry'] = 'Code d\'activation';
$txt['invalid_activation_submit'] = 'Activer';

$txt['coppa_no_concent'] = 'L\'administrateur n\'a toujours reçu aucune autorisation parentale pour votre compte.';
$txt['coppa_need_more_details'] = 'Plus de détails&nbsp;?';

$txt['awaiting_delete_account'] = 'Votre compte a été marqué pour une suppression!<br>Si vous voulez restaurer votre compte, veuillez cocher la case &quot;Réactiver mon compte&quot;, et connectez-vous à nouveau.';
$txt['undelete_account'] = 'Réactiver mon compte';

// Use numeric entities in the below three strings.
$txt['change_password'] = 'Nouveaux d&#233;tails de connexion';
$txt['change_password_login'] = 'Vos informations de connexion sur ';
$txt['change_password_new'] = 'ont &#233;t&#233; chang&#233;es et votre mot de passe chang&#233; Voici vos nouveaux d&#233;tails de connexion.';

$txt['in_maintain_mode'] = 'Ce forum est en Mode Maintenance.';

// These two are used as a javascript alert; please use international characters directly, not as entities.
$txt['register_agree'] = 'Merci de lire et accepter les termes de l\'accord avant de vous inscrire.';
$txt['register_passwords_differ_js'] = 'Les deux mots de passe entrés sont différents !';

$txt['approval_after_registration'] = 'Merci de vous être inscrit. L\'administrateur doit approuver votre inscription avant que vous puissiez commencer à utiliser votre compte. Vous allez bientôt recevoir un e-mail vous informant de la décision de l\'administrateur.';

$txt['admin_settings_desc'] = 'Ici vous pouvez changer une variété de paramètres concernant l\'inscription des nouveaux membres.';

$txt['setting_registration_method'] = 'Méthode d\'inscription employée pour les futurs membres';
$txt['setting_registration_disabled'] = 'Inscription désactivée';
$txt['setting_registration_standard'] = 'Inscription immédiate';
$txt['setting_registration_activate'] = 'Activation par e-mail';
$txt['setting_registration_approval'] = 'Approbation par un admin';
$txt['setting_send_welcomeEmail'] = 'Envoyer un e-mail de bienvenue aux nouveaux membres';

$txt['setting_coppaAge'] = 'Âge en dessous duquel appliquer des restrictions à l\'inscription';
$txt['setting_coppaType'] = 'Action à faire lorsqu\'un utilisateur en dessous de l\'âge minimum s\'inscrit';
$txt['setting_coppaType_reject'] = 'Rejeter son inscription';
$txt['setting_coppaType_approval'] = 'Requérir une autorisation parentale';
$txt['setting_coppaPost'] = 'Adresse postale à laquelle le formulaire d\'autorisation doit être envoyé';
$txt['setting_coppaPost_desc'] = 'Ne s\'applique que si la restriction d\'âge est en place';
$txt['setting_coppaFax'] = 'Numéro de fax auquel le formulaire d\'autorisation doit être faxé';
$txt['setting_coppaPhone'] = 'Numéro de téléphone à appeler pour les parents ayant des questions sur les restrictions d\'âge';

$txt['admin_register'] = 'Inscription d\'un nouveau membre';
$txt['admin_register_desc'] = 'D\'ici vous pouvez inscrire des nouveaux membres sur votre forum, et si vous le désirez, leur envoyer leurs informations de connexion par e-mail.';
$txt['admin_register_username'] = 'Nouvel identifiant';
$txt['admin_register_email'] = 'Adresse e-mail';
$txt['admin_register_password'] = 'Mot de passe';
$txt['admin_register_username_desc'] = 'Identifiant pour le nouveau membre';
$txt['admin_register_email_desc'] = 'Adresse e-mail associée à ce compte membre';
$txt['admin_register_password_desc'] = 'Nouveau mot de passe du membre';
$txt['admin_register_email_detail'] = 'Envoyer le mot de passe par e-mail';
$txt['admin_register_email_detail_desc'] = 'Adresse e-mail requise même si décoché';
$txt['admin_register_email_activate'] = 'Nécessite l\'activation du compte par le membre';
$txt['admin_register_group'] = 'Groupe principal';
$txt['admin_register_group_desc'] = 'Groupe de membre principal auquel le nouveau membre appartiendra';
$txt['admin_register_group_none'] = '(pas de groupe principal)';
$txt['admin_register_done'] = 'Le membre %1$s s\'est inscrit avec succès&nbsp;!';

$txt['coppa_title'] = 'Forum avec restriction d\'âge';
$txt['coppa_after_registration'] = 'Merci de vous être inscrit sur ' . $context['forum_name_html_safe'] . '.<br><br>Parce que vous êtes âgé de moins de {MINIMUM_AGE} ans, il est légalement requis
	que vous obteniez une autorisation de vos parents ou tuteurs légaux avant que vous puissiez utiliser votre compte.  Pour arranger l\'activation de votre compte, veuillez imprimer le formulaire ci-dessous:';
$txt['coppa_form_link_popup'] = 'Charger le formulaire dans une nouvelle fenêtre';
$txt['coppa_form_link_download'] = 'Télécharger le formulaire en tant que fichier texte';
$txt['coppa_send_to_one_option'] = 'Ensuite, demandez à vos parents ou tuteurs de l\'envoyer complété par&nbsp;:';
$txt['coppa_send_to_two_options'] = 'Puis arrangez-vous pour que votre parent ou tuteur envoie le formulaire rempli par&nbsp;:';
$txt['coppa_send_by_post'] = 'Voie postale à l\'adresse suivante&nbsp;:';
$txt['coppa_send_by_fax'] = 'Fax au numéro suivant&nbsp;:';
$txt['coppa_send_by_phone'] = 'Alternativement, demandez-leur de contacter l\'administrateur par téléphone au numéro {PHONE_NUMBER}.';

$txt['coppa_form_title'] = 'Formulaire d\'autorisation d\'inscription au forum ' . $context['forum_name_html_safe'];
$txt['coppa_form_address'] = 'Adresse';
$txt['coppa_form_date'] = 'Date';
$txt['coppa_form_body'] = 'Je soussigné, {PARENT_NAME},<br><br>Donne la permission à {CHILD_NAME} (nom de l\'enfant) de devenir un membre à part entière du forum : ' . $context['forum_name_html_safe'] . ', sous l\'identifiant : {USER_NAME}.<br><br>Je comprends que certaines informations personnelles entrées par {USER_NAME} peuvent êtres affichées à d\'autres visiteurs du forum.<br><br>Signé:<br>{PARENT_NAME} (Parent/Tuteur légal).';

$txt['visual_verification_sound_again'] = 'Recommencer';
$txt['visual_verification_sound_close'] = 'Fermer la fenêtre';
$txt['visual_verification_sound_direct'] = 'Un problème pour écouter ceci? Essayez avec ce lien direct.';

// Use numeric entities in the below.
$txt['registration_username_available'] = 'Ce nom d\'utilisateur est disponible';
$txt['registration_username_unavailable'] = 'Ce nom d\'utilisateur n\'est pas disponible';
$txt['registration_username_check'] = 'Vérifier si le nom d\'utilisateur est disponible';
$txt['registration_password_short'] = 'Le mot de passe est trop court';
$txt['registration_password_reserved'] = 'Le mot de passe contient votre e-mail ou nom d\'utilisateur';
$txt['registration_password_numbercase'] = 'Le mot de passe doit contenir à la fois des majuscules, des minuscules et des chiffres';
$txt['registration_password_no_match'] = 'Les mots de passe ne correspondent pas';
$txt['registration_password_valid'] = 'Le mot de passe est valide';

$txt['registration_errors_occurred'] = 'Les erreurs suivantes ont été détectées lors de votre inscription. Merci de les corriger pour pouvoir continuer&nbsp;:';

?>