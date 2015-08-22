<?php
// Version: 2.1 Beta 2; Help

global $helptxt;

$txt['close_window'] = 'Fermer la fenêtre';

$helptxt['manage_boards'] = '
	<strong>Gestion des Sections et des Catégories</strong><br />
	Dans ce menu, vous pouvez créer/réorganiser/supprimer des sections et les catégories
	les concernant. Par exemple, si vous avez un gros site offrant des informations variées
	sur plusieurs sujets tels que &quot;Sports&quot; et &quot;Voitures&quot; et &quot;Musique&quot;, ces
	titres seraient ceux des catégories que vous créeriez. Sous chacune de ces catégories vous voudriez assurément insérer, de manière hiérarchique, des <em>sous-catégories</em>,
	ou &quot;sections&quot;, pour des sujets les concernant. C\'est une simple hiérarchie, avec cette structure&nbsp;: <br />
	<ul class="normallist">
		<li>
			<strong>Sports</strong>
			&nbsp;- Une &quot;catégorie&quot;
		</li>
		<ul class="normallist">
			<li>
				<strong>Baseball</strong>
				&nbsp;- Une section de la catégorie &quot;Sports&quot;
			</li>
			<ul class="normallist">
				<li>
					<strong>Stats</strong>
					&nbsp;- Une sous-section de la section &quot;Baseball&quot;
				</li>
			</ul>
			<li><strong>Football</strong>
			&nbsp;- Une section de la catégorie &quot;Sports&quot;</li>
		</ul>
	</ul>
	Les catégories vous permettent de séparer votre forum en différents sujets (&quot;Voitures,
	Sports&quot;), et les &quot;sections&quot; en dessous sont les sujets dans lesquels
	vos membres peuvent poster. Un utilisateur intéressé par les Twingo
	voudra poster un message dans &quot;Voitures->Twingo&quot;. Les catégories permettent aux gens
	de rapidement trouver ce qui les intéresse&nbsp;: au lieu d\'un &quot;Magasin&quot;, vous avez
	un &quot;Magasin d\'informatique&quot; et un &quot;Magasin de chaussures&quot; où vous pouvez aller. Cela simplifie
	votre recherche d\'un &quot;disque dur&quot;, parce que vous pouvez aller directement au &quot;Magasin d\'informatique&quot;
	plutôt qu\'au &quot;Magasin de chaussures&quot; (où vous ne trouverez sans doute pas votre disque dur ;) ).
	<br />
	Comme précisé plus haut, une section est un sujet clé sous une catégorie mère.
	Si vous voulez discuter de &quot;Twingo&quot;, vous irez à la catégorie &quot;Voitures&quot; et
	irez à la section &quot;Twingo&quot; pour y poster votre avis à propos de cette automobile.<br />
	Les fonctions administratives possibles ici sont de créer des nouvelles sections
	sous chaque catégorie, réordonner les sections (placer &quot;Twingo&quot; sous &quot;Renault&quot;), ou
	supprimer une section entièrement.';

$helptxt['edit_news'] = '
	<ul class="normallist">
		<li>
			<strong>Nouvelles</strong><br />
			Cette partie vous permet de définir du contenu pour les news de la page d\'accueil.
			Mettez-y ce que vous voulez (par ex., &quot;Ne manquez pas la conférence de mardi prochain&quot;). Les news sont affichées de manière aléatoire et doivent être placées dans des boîtes séparées.
		</li>
		<li>
			<strong>Infolettres</strong><br />
			Cette partie vous permet d\'envoyer des newsletters (infolettres) aux membres du forum par message personnel ou e-mail. Choisissez d\'abord les groupes auxquels envoyer l\'infolettre, puis ceux dont vous ne voulez pas qu\'ils la reçoivent. Si vous le désirez, vous pouvez ajouter des membres et adresses e-mail individuellement. Enfin, mettez le contenu du message à envoyer, et choisissez le type d\'envoi (message personnel sur le forum, ou e-mail).
		</li>
		<li>
			<strong>Paramètres</strong><br />
				Cette partie contient des réglages liés aux news et aux infolettres, par exemple le choix des groupes qui peuvent modifier les news ou envoyer des infolettres. Vous pouvez également paramétrer l\'activation des flux RSS sur le forum, mais aussi choisir la longueur maximale des messages (en caractères) dans ces mêmes flux RSS.
		</li>
	</ul>';

$helptxt['view_members'] = '
	<ul class="normallist">
		<li>
			<strong>Voir tous les membres</strong><br />
			Voir tous les membres dans votre forum. Il vous est présenté une liste d\'hyperliens avec les pseudos des membres. Vous pouvez cliquer
			sur n\'importe lequel de ces pseudos pour trouver plus de détails à propos du membre (site web, âge, etc.), et en tant qu\'administrateur
			vous avez la possibilité de modifier ces paramêtres. Vous avez un contrôle sur vos membres, incluant la possibilité
			de les supprimer de votre forum.<br /><br />
		</li>
		<li>
			<strong>En attente d\'approbation</strong><br />
			Cette rubrique n\'est affiché que si vous avez activé l\'approbation par un administrateur des nouvelles inscriptions à votre forum. Peu importe qui s\'inscrit pour rejoindre votre
			forum, il ne sera un membre complet qu\'une fois son compte approuvé par un admin. La rubrique liste tous ces membres qui
			sont encore en attente d\'approbation, de même que leur adresse e-mail et leur adresse IP. Vous pouvez choisir d\'accepter ou de rejeter (supprimer)
			n\'importe quel membre dans la liste en cochant la case suivant le nom du membre et en choisissant l\'action correcte à appliquer dans le menu déroulant au bas
			de l\'écran. Lorsque vous rejetez un membre, vous pouvez choisir de le supprimer en l\'avertissant ou non de votre décision.<br /><br />
		</li>
		<li>
			<strong>En attente d\'activation</strong><br />
			Cette rubrique n\'est visible que si vous avez choisi l\'activation des comptes des membres sur votre forum. Cette section liste tous
			les membres qui n\'ont pas encore activé leur nouveau compte. Depuis cet écran, vous pouvez choisir de les accepter, de les rejeter ou de leur rappeler
			l\'activation de leur compte. Comme pour le paramètre précédent, vous avez la possibilité d\'informer ou non le membre
			des actions que vous avez effectuées.<br /><br />
		</li>
	</ul>';

$helptxt['ban_members'] = '<strong>Bannir des membres</strong><br />
	SMF offre la possibilité de &quot;bannir&quot; des utilisateurs, afin d\'empêcher le retour de personnes ayant dérangé
	l\'atmosphère de votre forum par du pollupostage (spamming), des déviations de sujets (trolling), etc. En tant qu\'administrateur,
	lorsque vous voyez un message, vous pouvez voir l\'adresse IP du posteur au moment de l\'envoi du message incriminé. Dans la liste de bannissement,
	vous entrez simplement cette adresse IP, sauvegardez, et l\'utilisateur banni ne pourra plus poster depuis son ordinateur. <br />Vous pouvez aussi
	bannir des gens par leur adresse e-mail.';

$helptxt['featuresettings'] = '<strong>Modifier les Options et Fonctionnalités</strong><br />
	Il y a plusieurs fonctionnalités dans cette section qui peuvent être changées à votre préférence.';

$helptxt['modsettings'] = '<strong>Modifier les Caractéristiques et les Options</strong><br />
	Plusieurs options peuvent être modifiées ici selon vos préférences. Les options pour les modifications (mods) installées vont généralement apparaître ici.';

$helptxt['time_format'] = '<strong>Format de l\'heure</strong><br />
	Vous avez la possibilité d\'ajuster la manière dont le temps et les dates seront affichés sur votre forum. Il y a beaucoup de lettres, mais c\'est relativement simple. La convention d\'écriture s\'accorde avec celle de la fonction <tt>strftime</tt> de PHP et est décrite ci-dessous (plus de détails peuvent être trouvés sur <a href="http://www.php.net/manual/fr/function.strftime.php" target="_blank">php.net</a>).<br />
	<br />
	Les caractères suivants sont reconnus en tant qu\'entrées dans la chaîne du format de l\'heure&nbsp;: <br />
	<span class="smalltext">
	&nbsp;&nbsp;%a - Nom du jour (abrégé)<br />
	&nbsp;&nbsp;%A - Nom du jour (complet)<br />
	&nbsp;&nbsp;%b - Nom du mois (abrégé)<br />
	&nbsp;&nbsp;%B - Nom du mois (complet)<br />
	&nbsp;&nbsp;%d - Jour du mois (01 à 31)<br />
	&nbsp;&nbsp;%D - La même chose que %m/%d/%y *<br />
	&nbsp;&nbsp;%e - Jour du mois (1 à 31) *<br />
	&nbsp;&nbsp;%H - Heure au format 24 heures (de 00 à 23)<br />
	&nbsp;&nbsp;%I - Heure au format 12 heures (de 01 à 12)<br />
	&nbsp;&nbsp;%m - Numéro du mois (01 à 12)<br />
	&nbsp;&nbsp;%M - Minutes en chiffres<br />
	&nbsp;&nbsp;%p - Met &quot;am&quot; ou &quot;pm&quot; selon la période de la journée<br />
	&nbsp;&nbsp;%R - Heure au format 24 heures *<br />
	&nbsp;&nbsp;%S - Secondes en chiffres<br />
	&nbsp;&nbsp;%T - Temps en ce moment, la même chose que %H:%M:%S *<br />
	&nbsp;&nbsp;%y - Année au format 2 chiffres (00 to 99)<br />
	&nbsp;&nbsp;%Y - Année au format 4 chiffres<br />
	&nbsp;&nbsp;%% - Le symbole \'%\' en lui-même<br />
	<br />
	<em>* Ne fonctionnent pas sur les serveurs Windows.</em></span>';

$helptxt['live_news'] = '<strong>En direct de Simple Machines...</strong><br />
	Cette boîte affiche les dernières dépêches en provenance de <a href="http://www.simplemachines.org/" target="_blank">www.simplemachines.org</a>.
	Vous devriez y surveiller les annonces concernant les mises à jour, nouvelles versions de SMF et informations importantes de Simple Machines.';

$helptxt['registrations'] = '<strong>Gestion des inscriptions</strong><br />
	Cette section contient toutes les fonctions nécessaires pour la gestion des nouvelles inscriptions sur votre forum. Elle peut contenir jusqu\'à quatre
	rubriques, visibles selon vos paramètres de forum. Celles-ci sont détaillés ci-dessous&nbsp;:<br /><br />
	<ul class="normallist">
		<li>
			<strong>Inscrire un nouveau membre</strong><br />
			À partir de cet écran, vous pouvez inscrire un nouveau membre à sa place. Cette option peut être utile lorsque les nouvelles inscriptions sur le forum sont désactivées,
			ou lorsque l\'administrateur souhaite se créer un compte de test. Si l\'activation du nouveau compte par le membre est sélectionnée,
			le nouveau membre recevra un e-mail contenant un lien d\'activation, sur lequel il devra cliquer avant de pouvoir utiliser son compte. De même, vous pouvez choisir d\'envoyer
			le nouveau mot de passe à l\'adresse e-mail spécifiée.
		</li>
			<strong>Modifier l\'accord d\'inscription</strong><br />
			Ceci vous permet de spécifier le texte de l\'accord d\'inscription affiché lors de l\'inscription d\'un membre sur votre forum.
			Vous pouvez ajouter ou enlever ce que vous souhaitez au texte d\'accord inclus par déavec SMF.<br /><br />
		</li>
		<li>
			<strong>Choisir les noms réservés</strong><br />
			En utilisant cette interface, vous pouvez spécifier des mots ou des noms qui ne seront pas utilisés librement par vos membres comme identifiants ou pseudonymes.<br /><br />
		</li>
		<li>
			<strong>Paramètres</strong><br />
			Cette section ne sera visible que si vous avez la permission d\'administrer le forum. Depuis cette interface, vous pouvez choisir la méthode d\'inscription
			en vigueur sur votre forum et configurer quelques autres réglages relatifs à l\'inscription.
		</li>
	</ul>';

$helptxt['modlog'] = '<strong>Journal de Modération</strong><br />
	Cette section permet à l\'équipe des administrateurs de conserver des traces de chaque action de modération effectuée sur le forum par un modérateur ou un administrateur (voire même par un membre). Afin que
	les modérateurs ne puissent enlever les références aux actions entreprises, les entrées ne pourront être supprimées que 24 heures après leur application.
	La colonne \'Objet\' liste les variables associées à l\'action.';
$helptxt['adminlog'] = '<strong>Journal d\'Administration</strong><br />
	Cette section permet aux membres de l\'équipe d\'administration de pister les actions effectuées par tout administrateur sur le forum. Afin que les administrateurs ne puissent enlever les références aux actions entreprises, les entrées ne pourront être supprimées que 24 heures après leur application.';
$helptxt['userlog'] = '<strong>Profile Edits Log</strong><br>
	This page allows members of the admin team to view changes users make to their profiles, and is available from inside a user\'s profile area.';
$helptxt['warning_enable'] = '<strong>Système d\'avertissement utilisateur</strong><br />
	Cette fonctionnalité permet aux membres des équipes d\'administration et de modération d\'envoyer des avertissements aux utilisateurs, et d\'utiliser un niveau d\'avertissement pour déterminer leurs actions possibles au niveau du forum. Après avoir activé cette fonctionnalité, un nouveau paramètre sera disponible dans les permissions par section pour définir quels groupes pourront assigner des avertissements aux utilisateurs. Les niveaux d\'avertissement pourront être ajustés à partir du profil des utilisateurs. Les options suivantes sont disponibles :
	<ul class="normallist">
		<li>
			<strong>Niveau d\'avertissement pour la mise sous surveillance d\'un utilisateur</strong><br />
			Ce réglage définit le pourcentage de niveau d\'avertissement qu\'un utilisateur doit atteindre pour être automatiquement mis &quot;sous surveillance&quot;.
			Tous les utilisateurs qui sont &quot;sous surveillance&quot; apparaitront dans l\'endroit adéquat du centre de modération.
		</li>
		<li>
			<strong>Niveau d\'avertissement pour la modération de messages</strong><br />
			Si ce niveau d\'avertissement est atteint par un utilisateur, ces messages devront être validés par un modérateur pour apparaître sur le forum. Cela écrasera toutes les permissions par section qui pourront exister en relation avec la modération des messages.
		</li>
		<li>
			<strong>Niveau d\'avertissement pour rendre muet un utilisateur</strong><br />
			Si ce niveau d\'avertissement est atteint par un utilisateur, il lui sera impossible d\'envoyer des messages. L\'utilisateur perdra ainsi tous ses droits pour poster.
		</li>
		<li>
			<strong>Points d\'avertissement maximum reçus d\'un utilisateur par jour</strong><br />
			Ce réglage limite le nombre de points qu\'un modérateur peut ajouter/retirer à un utilisateur particulier sur une période de vingt-quatre heures. Cela pourra être utile pour limiter ce que peut faire un modérateur sur une courte période de temps. Ce réglage peut être désactivé en mettant cette valeur à zéro. Notez que tout utilisateur avec des permissions d\'administration n\'est pas affecté par cette valeur.
		</li>
	</ul>';
$helptxt['warning_watch'] = 'This setting defines the percentage warning level a member must reach to automatically assign a &quot;watch&quot; to the member. Any member who is being &quot;watched&quot; will appear in the watched members list in the moderation center.';
$helptxt['warning_moderate'] = 'Any member passing the value of this setting will find all their posts require moderator approval before they appear to the forum community. This will override any local board permissions which may exist related to post moderation.';
$helptxt['warning_mute'] = 'If this warning level is passed by a member they will find themselves under a post ban. The member will lose all posting rights.';
$helptxt['user_limit'] = 'This setting limits the amount of points a moderator may add/remove to any particular member in a twenty four hour period. This
			can be used to limit what a moderator can do in a small period of time. This can be disabled by setting it to a value of zero. Note that
			any members with administrator permissions are not affected by this value.';

$helptxt['error_log'] = '<strong>Journal d\'Erreurs</strong><br />
	Le journal d\'erreurs conserve des traces de toutes les erreurs sérieuses rencontrées lors de l\'utilisation de votre forum. Il liste toutes les erreurs par date, qui peuvent être récupérées
	en cliquant sur la flèche noire accompagnant chaque date. De plus, vous pouvez filtrer les erreurs en sélectionnant l\'image accompagnant les statistiques des erreurs. Ceci
	vous permet, par exemple, de filtrer les erreurs par nom de membre. Lorsqu\'un filtre est actif les seuls résultats affichés seront ceux correspondants aux critères du filtre.';
$helptxt['theme_settings'] = '<strong>Réglages du Thème</strong><br />
	L\'écran des réglages vous permet de modifier certains réglages spécifiques à un thème. Ces réglages incluent des options telles que le répertoire du thàme et l\'URL du thème, mais
	aussi des options affectant le rendu à l\'écran de votre forum. La plupart des thèmes possédent une variété d\'options configurables par l\'utilisateur, vous permettant d\'adapter un thème
	à vos besoins individuels.';
$helptxt['smileys'] = '<strong>Gestionnaire de smileys</strong><br />
	Ici, vous pouvez ajouter et supprimer des smileys et des jeux de smileys. Note importante&nbsp;: si un smiley est présent dans un jeu, il l\'est aussi dans tous les autres - autrement, cela pourrait prêter à
	confusion pour les utilisateurs utilisant des jeux différents.<br /><br />

	Vous pouvez aussi modifier les icônes de message depuis cette interface, si vous les avez activés sur la page des paramètres.';
$helptxt['calendar'] = '<strong>Gérer le calendrier</strong><br />
	Ici vous pouvez modifier les réglages courants du calendrier, ou ajouter et supprimer des fêtes qui apparaissent dans le calendrier.';
$helptxt['cal_export'] = 'Exporte un fichier texte au format iCal pour importer vers d\'autres applications de calendrier';
$helptxt['cal_highlight_events'] = 'This setting allows you to highlight events on the Mini Calendars, Main Calendar, both places, or disable event highlighting.';
$helptxt['cal_highlight_holidays'] = 'This setting allows you to highlight holidays on the Mini Calendars, Main Calendar, both places, or disable event highlighting.';
$helptxt['cal_highlight_birthdays'] = 'This setting allows you to highlight birthdays on the Mini Calendars, Main Calendar, both places, or disable event highlighting.';
$helptxt['cal_disable_prev_next'] = 'If this setting is checked, the three month blocks on the left hand side of the page will be disabled.';
$helptxt['cal_display_type'] = 'This setting allows you to change the display type of the calendar.<br><br><strong>Comfortable:</strong> makes the rows of the calendar big.<br><strong>Compact:</strong> makes the rows of the calendar small.';
$helptxt['cal_week_links'] = 'If this setting is checked, links will be added alongside each week in the calendar.';
$helptxt['cal_prev_next_links'] = 'If this setting is checked, previous month and next month links will be added to the top of each month for easy navigation.';
$helptxt['cal_short_months'] = 'If this setting is checked, month names within the calendar will be shortened.<br><br><strong>Enabled:</strong> ' . $txt['months_short'][1] . ' 1<br><strong>Disabled:</strong> ' . $txt['months_titles'][1] . ' 1';
$helptxt['cal_short_days'] = 'If this setting is checked, day names within the calendar will be shortened.<br><br><strong>Enabled:</strong> ' . $txt['days_short'][1] . '<br><strong>Disbaled:</strong> ' . $txt['days'][1];

$helptxt['serversettings'] = '<strong>Paramètres Serveur</strong><br />
	Ici, vous pouvez régler la configuration de votre serveur. Cette section comprend la base de données et les chemins des dossiers, ainsi que d\'autres
	options de configuration importantes tels que les paramètres d\'e-mail et de cache. Faites attention lors de la modification de ces paramètres,
	ils pourraient rendre le forum inaccessible';
$helptxt['manage_files'] = '
	<ul class="normallist">
		<li>
			<strong>Parcourir les Fichiers</strong><br />
			Parcourir à travers tous les fichiers joints, avatars et miniatures stockés par SMF.<br /><br />
		</li><li>
			<strong>Réglages des Fichiers Joints</strong><br />
			Configurer où sont stockés les fichiers joints et mettre les restrictions sur les types de fichiers joints.<br /><br />
		</li><li>
			<strong>Réglages des Avatars</strong><br />
			Configurer où sont stockés les avatars et gérer le redimensionnement des avatars.<br /><br />
		</li><li>
			<strong>Maintenance des Fichiers</strong><br />
			Contrôler et réparer toute erreur dans le répertoire des fichiers joints et effacer les fichiers joints sélectionnés.<br /><br />
		</li>
	</ul>';

$helptxt['topicSummaryPosts'] = 'Ceci vous permet de régler le nombre de messages précédemment postés affichés dans le sommaire du sujet sur l\'écran de réponse à un sujet.';
$helptxt['enableAllMessages'] = 'Mettez ici le nombre <em>maximum</em> de messages qu\'un sujet aura lors de l\'affichage par le lien &quot;Tous&quot;. Le régler au-dessous du &quot;Nombre de messages à afficher lors du visionnement d\'un sujet:&quot; signifiera simplement que le lien ne sera jamais affiché, et indiquer une valeur trop élevée peut ralentir votre forum.';
$helptxt['allow_guestAccess'] = 'Décocher cette option limitera les actions possibles des invités aux seules opérations de base - connexion, inscription, rappel du mot de passe, etc. - sur votre forum. Ce n\'est pas comme désactiver l\'accès aux sections pour les invités.';
$helptxt['userLanguage'] = 'Activer cette option permettra aux utilisateurs de sélectionner la langue dans laquelle le forum leur sera affiché.
	Cela n\'affectera pas la langue par défaut.';
$helptxt['trackStats'] = 'Stats&nbsp;:<br />Ceci permettra aux visiteurs de voir les derniers messages postés et les sujets les plus populaires sur votre forum.
	Cela affichera aussi plusieurs autres statistiques, comme le record d\'utilisateurs en ligne au même moment, les nouveaux membres et les nouveaux sujets.<hr />
	Pages vues&nbsp;:<br />Ajoute une autre colonne à la page des statistiques contenant le nombre de pages vues sur votre forum.';
$helptxt['titlesEnable'] = 'Activer les titres personnels permettra aux membres possédant les permissions suffisantes de s\'attribuer un titre spécial pour eux-mêmes.
		Il sera affiché sous leur pseudonyme.<br /><em>Par exemple :</em><br />Loulou<br />Oui, c\'est moi';
$helptxt['onlineEnable'] = 'Ceci affichera une image indiquant si l\'utilisateur est connecté ou non en ce moment.';
$helptxt['todayMod'] = 'Cette option affichera &quot;Aujourd\'hui&quot; ou &quot;Hier&quot; à la place de la date.<br /><br />
		<strong>Exemples&nbsp;:</strong><br /><br />
		<dt>
			<dt>Désactivé</dt>
			<dd>3 Octobre 2009 à 00:59:18</dd>
			<dt>Seulement Aujourd\'hui</dt>
			<dd>Aujourd\'hui à 00:59:18</dd>
			<dt>Aujourd\'hui &amp; Hier</dt>
			<dd>Hier à 21:36:55</dd>
		</dt>';
$helptxt['disableCustomPerPage'] = 'Cocher cette option pour empêcher les utilisateurs de personnaliser le nombre de messages et de sujets par page à afficher, respectivement sur l\'index des messages et la page d\'affichage du sujet.';
$helptxt['enablePreviousNext'] = 'Cette option affichera un lien vers le sujet précédent et le sujet suivant.';
$helptxt['pollMode'] = 'Ceci détermine si les sondages sont activés ou non. Si les sondages sont désactivés, tous les sondages actuels sont cachés sur la liste des sujets. Vous pouvez choisir de continuer à afficher la partie sujet des sondages en sélectionnant &quot;Montrer les sondages existants comme des sujets&quot;.<br /><br />Pour choisir qui peut poster et voir des sondages (et similaires), vous pouvez autoriser ou refuser ces permissions. Rappelez-vous de ceci si les sondages sont désactivés.';
$helptxt['enableCompressedOutput'] = 'Cette option compressera les données envoyées, afin de diminuer la consommation de bande passante, mais requiert que zlib soit installé sur le serveur.';
$helptxt['disableTemplateEval'] = 'Par défaut, les modèles de thème sont évalués au lieu d\'être simplement inclus, afin de pouvoir afficher plus d\'informations en cas d\'erreur du traitement.<br /><br />Toutefois, sur des forums de grande taille, ce processus peut ralentir sensiblement le traitement. Les utilisateurs aguerris peuvent donc préférer le désactiver.';
$helptxt['httponlyCookies'] = 'Cookies won\'t be accessible by scripting languages, such as JavaScript. This setting can help to reduce identity theft through XSS attacks. This can cause issues with third party scripts but should be on wherever possible.';
$helptxt['databaseSession_enable'] = 'Cette fonction utilise la base de données pour le stockage des sessions - c\'est mieux pour des serveurs à charge balancée, mais aide à régler tous les problèmes de fin de session indésirée et peut aider le forum à fonctionner plus rapidement.';
$helptxt['databaseSession_loose'] = 'Activer cette option diminuera la bande passante utilisée par le forum, et fait en sorte que lorsque l\'utilisateur revient sur ses pas, la page n\'est pas rechargée - le point négatif de cette option est que les (nouvelles) icônes ne seront pas mises à jour, ainsi que quelques autres choses. (Sauf si vous rechargez cette page plutôt que de retourner sur vos pas.)';
$helptxt['databaseSession_lifetime'] = 'Ceci est le temps en secondes au bout duquel la session se termine automatiquement après le dernier accès de l\'utilisateur. Si une session n\'a pas été accédée depuis trop longtemps, un message &quot;Session terminée&quot; est affiché. Tout ce qui est au-dessus de 2400 secondes est recommandé.';
$helptxt['tfa_mode'] = 'You can add a second level of security to your forum by enabling <a href="http://en.wikipedia.org/wiki/Two_factor_authentication">Two Factor Authentication</a>. 2FA forces your users to add a enter a machine-generated code after the regular login. You need to configure 2FA to yourself before you are able to force it to other users!';
$helptxt['frame_security'] = 'Modern browsers now understand a security header presented by servers called X-Frame-Options. By setting this option you specify how you want to allow your site to be framed inside a frameset or a iframe. Disable will not send any header and is the most unsecure, however allows the most freedom. Deny will prevent all frames completely and is the most restrictive and secure. Allowing the Same Origin will only allow your domain to issue any frames and provides a middle ground for the previous two options.';
$helptxt['cache_enable'] = 'SMF gère plusieurs niveaux de cache. Plus le niveau de cache activé est élevé, plus le CPU prendra de temps pour récupérer les informations cachées. Si le cache est disponible sur votre machine, il est recommandé que vous essayiez le niveau 1 en premier.';
$helptxt['cache_memcached'] = 'Veuillez noter que l\'utilisation de memcache nécessite que vous donniez quelques indications sur votre serveur dans les réglages à effectuer ci-dessous. Elles doivent être entrées sous forme de liste, dont les éléments sont séparés par une virgule, comme dans l\'exemple suivant :
<br /><br/> &quot;"serveur1,serveur2,serveur3:port,serveur4"&quot;<br /><br />
Si aucun port n\'est spécifié, SMF utilisera le port 11211 par défaut. SMF équilibrera de manière aléatoire la charge sur les serveurs. 

';
$helptxt['cache_cachedir'] = 'This setting is only for the smf file-based cache system. It specifies the path to the cache directory. It is recommended that you place this in /tmp/ if you are going to use this, although it will work in any directory';
$helptxt['enableErrorLogging'] = 'Ceci indexera toutes les erreurs rencontrées, comme les connexions non réussies, afin que vous puissiez les consulter lorsque quelque chose ne va pas.';
$helptxt['enableErrorQueryLogging'] = 'Ceci incluera la requête complète envoyée à la base de données lors d\'une erreur de cette dernière, dans le journal d\'erreurs. Requiert l\'activation du journal d\'erreurs.<br /><br /><strong>Attention, cela modifiera la capacité de filtrage du journal d\'erreurs par message d\'erreur.</strong>';
$helptxt['log_ban_hits'] = 'If enabled, every time a banned user tries to access the site, this will be logged in the error log. If you do not care whether, or how often, banned users attempt to access the site, you can turn this off for a performance boost.';
$helptxt['allow_disableAnnounce'] = 'Ceci permettra aux utilisateurs de désélectionner la réception des annonces du forum que vous envoyez en cochant &quot;Annoncer le sujet&quot; lorsque vous postez un message.';
$helptxt['disallow_sendBody'] = 'Cette option supprime l\'option permettant de recevoir le texte des réponses et les messages dans les e-mails de notification.<br /><br />Souvent, les membres vont répondre à l\'e-mail de notification, ce qui peut saturer, dans bien des cas, la boîte e-mail du webmestre.';
$helptxt['enable_ajax_alerts'] = 'This option allows your members to receive AJAX notifications. This means that members don\'t need to refresh the page to get new notifications.<br><b>DO NOTE:</b> This option might cause a severe load at your server with many users online.';
$helptxt['jquery_source'] = 'This will determine the source used to load the jQuery Library. <em>Auto</em> will use the CDN first and if not available fall back to the local source. <em>Local</em> will only use the local source. <em>CDN</em> will only load it from Google CDN network';
$helptxt['compactTopicPagesEnable'] = 'Ceci est le nombre de pages intermédiaires à afficher lors du visionnement d\'un sujet.<br /><em>Exemple&nbsp;:</em>
		&quot;3&quot; pour afficher&nbsp;: 1 ... 4 [5] 6 ... 9 <br />
		&quot;5&quot; pour afficher&nbsp;: 1 ... 3 4 [5] 6 7 ... 9';
$helptxt['timeLoadPageEnable'] = 'Ceci affichera au bas du forum le temps en secondes utilisé par SMF pour générer la page en cours.';
$helptxt['removeNestedQuotes'] = 'Ceci effacera les citations imbriquées dans les messages que vous citez en cliquant sur le bouton Citer.';
$helptxt['max_image_width'] = 'Cette option vous permet de spécifier une taille maximale pour les images postées. Les images plus petites ne seront pas affectées.';
$helptxt['mail_type'] = 'Cette option vous permet d\'utiliser soit le réglage par défaut de PHP ou de l\'outrepasser en utilisant le protocole SMTP. PHP ne supporte pas l\'authentification (que plusieurs FAI requièrent maintenant) donc vous devriez vous renseigner avant d\'utiliser cette option. Notez que SMTP peut être plus lent que sendmail et que certains serveurs ne prendront pas en compte les identifiants et mot de passe.<br /><br />Vous n\'avez pas à renseigner les informations SMTP si vous utilisez la configuration par défaut de PHP.';
$helptxt['attachment_manager_settings'] = 'Les fichiers joints sont des fichiers que les membres peuvent uploader, et attacher à un message.<br /><br />
		<strong>Contrôler les extensions de fichier joint</strong>:<br /> Voulez vous contrôler l\'extension des fichiers&nbsp;?<br />
		<strong>Extensions de fichier autorisées</strong>:<br /> Vous pouvez mettre les extensions de fichiers joints autorisées.<br />
		<strong>Répertoire des fichiers joints</strong>:<br /> Le chemin vers le dossier de fichiers joints<br />(exemple: /home/sites/yoursite/www/forum/attachments)<br />
		<strong>Espace Max dossier fichiers joints</strong> (en Ko):<br /> Sélectionnez de quelle taille le dossier de fichiers joints peut t\'il être, en incluant tous les fichiers contenus.<br />
		<strong>Taille Max de fichiers joints par message</strong> (en Ko):<br /> Sélectionnez la taille de fichier maximum de tous les fichiers joints d\'un même message. Si elle est inférieure à la limite de taille de fichier joint, cela sera la limite.<br />
		<strong>Taille maximum par fichier joint</strong> (en Ko):<br /> Sélectionnez la taille de fichier maximum de chaque fichier joint.<br />
		<strong>Nombre maximum de fichiers joints par message</strong>:<br /> Sélectionnez le nombre de fichiers joints qu\'une personne peut mettre par message.<br />
		<strong>Afficher un fichier joint comme une image dans les messages</strong>:<br /> Si le fichier uploadé est une image, elle sera affichée sous le message.<br />
		<strong>Redimensionner les images quand affichées sous les messages</strong>:<br /> Si l\'option au-dessus est sélectionnée, cela sauvegardera une copie (plus petite) du fichier joint pour la miniature afin d\'économiser la bande passante.<br />
		<strong>Taille et hauteur maximum des miniatures</strong>:<br /> Seulement utilisé avec l\'option &quot;Redimensionner les images quand affichées sous les messages&quot;, spécifie la taille et la hauteur maximales des miniatures créées pour les fichiers joints. Elles seront redimensionnées proportionnellement.';
$helptxt['attachmentCheckExtensions'] = 'For some communities, you may wish to limit the types of files that users can upload by checking the extension: e.g. myphoto.jpg has an extension of jpg.';
$helptxt['attachmentExtensions'] = 'If "check attachment\'s extension" above is ticked, these are the extensions that will be permitted for new attachments.';
$helptxt['attachmentUploadDir'] = 'The path to your attachment folder on the server<br>(example: /home/sites/yoursite/www/forum/attachments)';
$helptxt['attachmentDirSizeLimit'] = ' Select how large the attachment folder can be, including all files within it.';
$helptxt['attachmentPostLimit'] = ' Select the maximum filesize (in KB) of all attachments made per post. If this is lower than the per-attachment limit, this will be the limit.';
$helptxt['attachmentSizeLimit'] = 'Select the maximum filesize of each separate attachment.';
$helptxt['attachmentNumPerPostLimit'] = ' Select the number of attachments a person can make per post.';
$helptxt['attachmentShowImages'] = 'If the uploaded file is a picture, it will be displayed underneath the post.';
$helptxt['attachmentThumbnails'] = 'If the above setting is selected, this will save a separate (smaller) attachment for the thumbnail to decrease bandwidth.';
$helptxt['attachmentThumbWidth'] = 'Only used with the &quot;Resize images when showing under posts&quot; setting the maximum width to resize attachments down from. They will be resized proportionally.';
$helptxt['attachmentThumbHeight'] = 'Only used with the &quot;Resize images when showing under posts&quot; setting the maximum height to resize attachments down from. They will be resized proportionally.';
$helptxt['attachmentDirFileLimit'] = 'Max number of files per directory';
$helptxt['attachmentEnable'] = 'This setting enables you to configure how attachments can be made.<br><br>
	<ul class="normallist">
		<li>
			<strong>Disable all attachments</strong><br>
			All attachments are disabled. Existing attachments are not deleted, but they are hidden from view (even administrators cannot see them). New attachments cannot be made either, regardless of permissions.<br><br>
		</li>
		<li>
			<strong>Enable all attachments</strong><br>
			Everything behaves as normal, users who are permitted to view attachments can do so, users who are permitted to upload can do so.<br><br>
		</li>
		<li>
			<strong>Disable new attachments</strong><br>
			Existing attachments are still accessible, but no new attachments can be added, regardless of permission.
		</li>
	</ul>';
$helptxt['attachment_image_paranoid'] = 'Choisissez cette option pour mettre en place des contrôles de sécurité très stricts sur les images envoyées en fichier joint. Attention, ces contrôles peuvent parfois échouer sur des images sans danger. Nous vous recommandons de ne l\'utiliser qu\'en association avec l\'option de réencodage, auquel cas SMF essaiera de recréer et de mettre en ligne des images saines si le contrôle échoue. Si le réencodage n\'est pas activé, les fichiers joints échouant au contrôle seront rejetés.';
$helptxt['attachment_image_reencode'] = 'Choisissez cette option pour permettre le réencodage des fichier joints envoyés par les utilisateurs. Le réencodage vous garantit une meilleure sécurité, mais il supprime également les animations des images animées.<br />Cette fonctionnalité n\'est disponible que si le module GD est installé sur votre serveur.';
$helptxt['attachment_thumb_memory'] = 'The larger the source image (size & width x height), the higher the memory requirements are for the system to successfully create a thumbnail image.<br>Checking this setting, the system will estimate the required memory and will then request that amount. If successful, only then will it attempt to create the thumbnail.<br>This will result in fewer white page errors but may result in fewer thumbnails being created. Leaving this unchecked will always cause the system to try to create the thumbnail (with a fixed amount of memory). This may result in more white page errors.';
$helptxt['attachmentRecodeLineEndings'] = 'The script will re-code line endings according to your server.';
$helptxt['automanage_attachments'] = 'By default, SMF puts new attachments into a single folder. For most sites this is not a problem, but as a site grows it can be useful to have multiple folders to store attachments in.<br><br>This setting allows you to set whether you manage these folders yourself (e.g. creating a second folder and moving to it when you are ready) or whether you let SMF do it, based on criteria, such as when the current directory reaches a given size, or breaking down folders by years or even months on very busy sites.';
$helptxt['use_subdirectories_for_attachments'] = 'Create new directories.';
$helptxt['max_image_height'] = 'As with the maximum width, this setting indicates the maximum height a posted image can be.';
$helptxt['avatar_paranoid'] = 'Choisissez cette option pour mettre en place des contrôles de sécurité très stricts sur les avatars au moment où les utilisateurs les envoient. Attention, ces contrôles peuvent parfois échouer sur des images sans danger. Nous vous recommandons de ne l\'utiliser qu\'en association avec l\'option de réencodage, auquel cas SMF essaiera de recréer et de mettre en ligne des images saines si le contrôle échoue. Si le réencodage n\'est pas activé, les avatars échouant au contrôle seront rejetés.';
$helptxt['avatar_reencode'] = 'Choisissez cette option pour permettre le réencodage des avatars envoyés par les utilisateurs. Le réencodage vous garantit une meilleure sécurité, mais il supprime également les animations des images animées.<br />Cette option n\'est disponible que si le module GD est installé sur votre serveur.';
$helptxt['cal_enabled'] = 'Le calendrier peut êre utilisé afin d\'afficher les anniversaires et des dates importantes à votre communauté.<br /><br />
		<strong>Montrer les jours en tant que liens vers \'Poster un Événement\'</strong>&nbsp;:<br />Ceci permettra à vos membres de poster des événements pour ce jour, lorsqu\'ils cliquent sur la date.<br />
		<strong>Jours d\'avance max. sur l\'accueil</strong>:<br />Si cette option est mise à 7, tous les événements de la semaine à venir seront montrés.<br />
		<strong>Montrer les jours de fête sur l\'accueil du forum</strong>&nbsp;:<br />Montre les jours de fête dans une barre sur l\'accueil du forum.<br />
		<strong>Afficher les anniversaires sur l\'accueil du forum</strong>&nbsp;:<br />Montre les anniversaires du jour dans une barre sur l\'accueil du forum.<br />
		<strong>Montrer les événements sur l\'accueil du forum</strong>&nbsp;:<br />Affiche les événements du jour dans une barre sur l\'accueil du forum.<br />
		<strong>Section où Poster par Défaut</strong>:<br />Quelle est la section par défaut pour poster les événements&nbsp;?<br />
		<strong>Permettre les événements qui ne sont liés à aucun message</strong>&nbsp;:<br />Permet aux membres de poster des événements sans nécessiter la création d\'un nouveau sujet dans le forum.<br />
		<strong>Année minimale</strong>&nbsp;:<br />Sélectionne la &quot;première&quot; année dans la liste du calendrier.<br />
		<strong>Année maximale</strong>&nbsp;:<br />Sélectionne la &quot;dernière&quot; année dans la liste du calendrier<br />
		<strong>Permettre aux événements de durer plusieurs jours</strong>&nbsp;:<br />Sélectionnez pour permettre aux événements de durer plusieurs jours.<br />
		<strong>Durée maximale (en jours) d\'un événement</strong>&nbsp;:<br />Sélectionnez le nombre maximal de jours pour la duré d\'un événement.<br /><br />
		Rappelez-vous que l\'usage du calendrier (poster des événements, voir des événements, etc.) est contrôlable par les réglages des permissions à partir de l\'écran de gestion des permissions.';
$helptxt['localCookies'] = 'SMF utilise des témoins (&quot;cookies&quot;) pour conserver les informations de connexion d\'un membre. Les témoins peuvent être stockés dans un dossier global (monserveur.com) ou localement (monserveur.com/chemin/vers/mon/forum).<br />
	Cochez cette option si vous constatez certains problèmes avec des utilisateurs déconnectés automatiquement.<hr />
	Les témoins stockés dans un dossier global sont moins sécurisés lorsqu\'ils sont utilisés sur un serveur mutualisé (comme Multimania/Lycos, Free, OVH, ...).<hr />
	Les témoins stockés localement ne fonctionnent pas à l\'extérieur du dossier du forum. Donc, si votre forum est installé dans le répertoire www.monserveur.com/forum, les pages telles que www.monserveur.com/index.php ne pourront pas accéder aux témoins. Lors de l\'utilisation de SSI.php, il est recommandé de stocker les témoins dans un dossier global.';
$helptxt['enableBBC'] = 'Activer cette fonction autorisera vos membres à utiliser les balises (BBCodes) sur votre forum, afin de permettre la mise en forme du texte, l\'insertion d\'images et plus.';
$helptxt['time_offset'] = 'Tous les propriétaires de forums ne souhaitent pas forcément utiliser le fuseau horaire du serveur sur lequel ils sont hébergés. Utilisez cette fonction pour spécifier un temps de décalage (en heures) sur lequel le forum devrait se baser pour les dates et heures. Les temps négatifs et décimaux sont permis.';
$helptxt['default_timezone'] = 'La zone horaire du serveur indique à PHP où il se trouve. Assurez-vous que ladite zone soit correctement renseignée, notamment le pays voire la ville. Vous trouverez plus d\'informations sur <a href="http://www.php.net/manual/fr/timezones.php" target="_blank">le site de PHP</a>.';
$helptxt['spamWaitTime'] = 'Ici vous pouvez spécifier le temps minimal requis entre deux envois de messages en provenance d\'un même utilisateur. Cette option peut être utilisée afin de contrer le pollupostage (&quot;spamming&quot;).';

$helptxt['enablePostHTML'] = 'Ceci permet l\'utilisation de quelques balises HTML basiques&nbsp;:
	<ul class="normallist" style="margin-bottom: 0;">
		<li>&lt;b&gt;, &lt;u&gt;, &lt;i&gt;, &lt;s&gt;, &lt;em&gt;, &lt;ins&gt;, &lt;del&gt;</li>
		<li>&lt;a href=&quot;&quot;&gt;</li>
		<li>&lt;img src=&quot;&quot; alt=&quot;&quot; /&gt;</li>
		<li>&lt;br /&gt;, &lt;hr /&gt;</li>
		<li>&lt;pre&gt;, &lt;blockquote&gt;</li>
	</ul>';

$helptxt['themes'] = 'Ici vous pouvez choisir si le thème par défaut peut être utilisé ou non, quel thème les invités verront ainsi que plusieurs autres options. Cliquez sur un thème à droite pour changer ses propriétés spécifiques.';
$helptxt['theme_install'] = 'Ceci vous permet d\'installer des nouveaux thèmes. Vous pouvez procéder en partant d\'un dossier déjà créé, en transférant une archive d\'un thème ou en copiant le thème par défaut.<br /><br />Notez bien que les archives de thèmes doivent contenir un fichier de définition <tt>theme_info.xml</tt>.';
$helptxt['enableEmbeddedFlash'] = 'Cette option permettra à vos visiteurs d\'insérer des animations Flash directement dans leurs messages, comme des images. Ceci peut présenter un sérieux risque de sécurité, bien que peu nombreux soient ceux qui ont réussi l\'exploitation de ce risque.<br /><br />UTILISEZ CETTE OPTION À VOS PROPRES RISQUES&nbsp;!';
$helptxt['xmlnews_enable'] = 'Permet aux gens de faire référence aux <a href="%1$s?action=.xml;sa=news" target="_blank">dernières nouvelles</a>
	et autres données similaires. Il est recommandé de limiter la taille des messages puisque certains clients
	tels que Trillian préfèrent afficher des messages tronqués.';
$helptxt['globalCookies'] = 'Permet l\'utilisation de témoins (<em>cookies</em>) indépendants du sous-domaine. Par exemple, si...<br />
	Votre site est situé sur http://www.simplemachines.org/,<br />
	Et votre forum est situé sur http://forum.simplemachines.org/,<br />
	Activer cette fonction vous permettra d\'utiliser les témoins de votre forum sur votre site (grâce à SSI.php, par exemple).';
$helptxt['globalCookiesDomain'] = 'When using subdomain independent cookies (global cookies), you can specify which domain should be used for them. This should, of course, be set to your main domain - for example, if you are using <em>forum.example.com</em> and <em>www.example.com</em>, the domain is <em>example.com</em> in this case. You should not put the <em>http://</em> part in front of it.';
$helptxt['secureCookies'] = 'Activer cette option forcera la sécurisation des témoins (cookies) créés pour les utilisateurs de votre forum. Ne l\'activez que si vous utilisez le protocole HTTPS sur tout votre site, faute de quoi la gestion des témoins sera fortement perturbée&nbsp;!';
$helptxt['securityDisable'] = 'Ceci <em>désactive</em> la vérification supplémentaire du mot de passe pour accéder à la zone d\'administration. Ça n\'est pas recommandé&nbsp;!';
$helptxt['securityDisable_why'] = 'Ceci est votre mot de passe courant. (Le même que vous utilisez pour vous connecter au forum quoi.)<br /><br />Avoir à le taper de nouveau permet de vérifier que vous voulez bien effectuer quelque opération d\'administration, et que c\'est bien <strong>vous</strong> qui le faites.';
$helptxt['securityDisable_moderate'] = 'This <em>disables</em> the additional password check for the moderation page. This is not recommended!';
$helptxt['securityDisable_moderate_why'] = 'This is your current password. (the same one you use to login).<br><br>The requirement to enter this helps ensure that you want to do whatever moderation you are doing, and that it is <strong>you</strong> doing it.';
$helptxt['proxy_ip_header'] = 'This is the server header that will be trusted by SMF for containing the actual users IP address. Changing this setting can cause unexpected IP results on members. Please check with your server administrator, CDN provider or proxy administrator prior to changing these settings. Most providers will understand and use HTTP_X_FORWARDED_FOR. You should fill out the list of Servers sending the reverse proxy headers for security to ensure these headers only come from valid sources.';
$helptxt['email_members'] = 'In this message you can use a few &quot;variables&quot;. These are:<br>
	{$board_url} - The URL to your forum.<br>
	{$current_time} - The current time.<br>
	{$member.email} - The current member\'s email.<br>
	{$member.link} - The current member\'s link.<br>
	{$member.id} - The current member\'s id.<br>
	{$member.name} - The current member\'s name. (for personalization).<br>
	{$latest_member.link} - The most recently registered member\'s link.<br>
	{$latest_member.id} - The most recently registered member\'s id.<br>
	{$latest_member.name} - The most recently registered member\'s name.';

$helptxt['failed_login_threshold'] = 'Spécifiez le nombre maximal de tentatives de connexion avant de rediriger l\'utilisateur vers la fonction &quot;Rappel de Mot de Passe&quot;.';
$helptxt['loginHistoryDays'] = 'The number of days to keep login history under user profile tracking. The default is 30 days.';
$helptxt['oldTopicDays'] = 'Si cette option est activée, un avertissement sera affiché aux utilisateurs qui tenteront de répondre dans un sujet dans lequel il n\'y a eu aucune intervention après un certain laps de temps, en jours, spécifié par ce paramêtre. Réglez-la à 0 pour désactiver cette fonction.';
$helptxt['edit_wait_time'] = 'Temps en secondes permis pour la modification d\'un message avant que la mention &quot;Dernière édition&quot; apparaisse.';
$helptxt['edit_disable_time'] = 'Nombre de minutes accordées à un utilisateur pour qu\'il puisse modifier ses messages. Mettre sur 0 pour désactiver. <br /><br /><em>Note: Cela n\'affectera pas l\'utilisateur qui a la permission de modifier les messages des autres.</em>';
$helptxt['preview_characters'] = 'This setting sets the number of available characters for the first and last message topic preview.';
$helptxt['posts_require_captcha'] = 'Ce réglage forcera les utilisateurs à rentrer un code affiché sur une image de vérification à chaque fois qu\'ils posteront un message. Seul les utilisateurs avec un compteur de messages en dessous du nombre choisi auront à entrer le code - Cela devrait aider à combattre les scripts automatiques de spam.';
$helptxt['enableSpellChecking'] = 'Active la vérification orthographique. Vous DEVEZ avoir la librairie pspell installée sur votre serveur et PHP doit être configuré de telle sorte qu\'il utilise cette librairie. Votre serveur ' . (function_exists('pspell_new') ? '<span style="color: green">semble</span>' : '<span style="color: red">NE SEMBLE PAS</span>') . ' avoir la librairie pspell.';
$helptxt['disable_wysiwyg'] = 'Ce réglage désactivera l\'utilisation du WYSIWYG (acronyme de la locution anglaise &quot;What you see is what you get&quot;, signifiant littéralement en français &quot;vous aurez ce que vous voyez&quot;») sur la page de rédaction des messages chez tous les utilisateurs.';
$helptxt['lastActive'] = 'Sélectionnez le nombre de minutes à afficher dans &quot;Membres actifs dans les X dernières minutes&quot;, sur l\'accueil du forum. Par défaut, la valeur est 15 minutes.';

$helptxt['customoptions'] = 'Cette section définit les options qu\'un utilisateur peut choisir à partir d\'une liste déroulante. Il y a quelques points clés à noter pour cette section:
	<ul class="normallist">
		<li><strong>Option par défaut:</strong> L\'option que vous aurez choisie ici sera celle définie par défaut pour l\'utilisateur lorsqu\'il enregistrera son profil.</li>
		<li><strong>Options à retirer:</strong> Pour retirer une option, laisser simplement vide la boite de texte de cette option - celle-ci sera automatiquement supprimée pour tous les utilisateurs l\'ayant précédemment sélectionnée.</li>
		<li><strong>Réordonner les Options:</strong> Vous pouvez modifier l\'ordre des options en les déplacant. Remarque importante - Assurez-vous de ne <strong>pas</strong> modifier le texte de ces options lorsque vous en modifiez l\'ordre, sinon vous perdrez les données prélablement enregistrées par vos utilisateurs pour ces options.</li>
	</ul>';

$helptxt['autoFixDatabase'] = 'Ceci réparera automatiquement les tables présentant des erreurs et ainsi, le forum continuera de fonctionner comme si rien ne s\'était produit. Ceci peut être utile, car la seule façon de régler le problème est de RÉPARER la table en question, et grâce à cette option, le forum ne sera pas hors service en attendant que vous preniez les mesures nécessaires. Un e-mail vous est envoyé lorsqu\'un tel problème se présente.';

$helptxt['enableParticipation'] = 'Cette fonction affiche une icône spéciale sur les sujets dans lesquels un utilisateur est précédemment intervenu.';

$helptxt['db_persist'] = 'Conserve une connexion permanente avec la base de données afin d\'accroître les performances du forum. Si vous êtes sur un serveur mutualisé (Lycos, Free / Online, OVH, Celeonet, Lewis Media...), l\'activation de cette fonction peut occasionner des problèmes avec votre hébergeur, car cela consomme beaucoup de ressources système.';
$helptxt['ssi_db_user'] = 'Réglage optionnel pour utiliser un nom d\'utilisateur et un mot de passe de base de données différents quand vous utilisez SSI.php.';

$helptxt['queryless_urls'] = 'Ceci modifie un peu la structure des URLs afin que les moteurs de recherche tels Google et Yahoo! les référencent mieux. Les URLs ressembleront à index.php/topic,1.0.html.<br /><br />Cette option ' . (isset($_SERVER['SERVER_SOFTWARE']) && (strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false || strpos($_SERVER['SERVER_SOFTWARE'], 'lighttpd') !== false) ? '<span style="color: green">est supportée</span>' : '<span style="color: red">n\'est pas supportée</span>') . ' par votre serveur.';
$helptxt['countChildPosts'] = 'Sélectionner cette option signifie que les messages et les sujets dans une section parente seront comptés dans leur totalité sur la page d\'index.<br /><br />Cela rendra les choses notablement plus lentes, mais signifiera qu\'une parente avec aucun message ne montrera pas \'0\'.';
$helptxt['allow_ignore_boards'] = 'Cocher cette option permettra aux utilisateurs de sélectionner les sections qu\'ils veulent ignorer.';
$helptxt['deny_boards_access'] = 'Checking this setting will allow you to deny access to certain boards based on membergroup access';

$helptxt['who_enabled'] = 'Cette option vous permet d\'activer ou non la possibilité de voir qui est en ligne sur le forum et ce qu\'il y fait.';

$helptxt['recycle_enable'] = '&quot;Recycle&quot; les sujets et messages supprimés vers une section spécifique, souvent une section caché aux utilisateurs normaux.';

$helptxt['enableReportPM'] = 'Cette option permet aux utilisateurs de rapporter des messages personnels qu\'ils ont reçus à l\'équipe d\'administration. Ceci peut être pratique pour aider à traquer les abus effectués à l\'aide du système de messagerie personnelle.';
$helptxt['max_pm_recipients'] = 'Cette option vous permet de limiter la quantité maximale de messages privés envoyé par un membre du forum. Cette option permet de lutter contre le pollupostage (&quot;spam&quot;) du système de MP. Notez que les utilisateurs ayant la permission d\'envoyer des bulletins d\'informations ne sont pas concernés par cette restriction. Réglez-la à 0 pour désactiver la fonction.';
$helptxt['pm_posts_verification'] = 'Cette option forcera les utilisateurs à entrer un code affiché sur une image de vérification à chaque fois qu\'ils envoient un message personnel. Seuls les utilisateurs avec un compteur de messages en dessous de l\'ensemble de nombres auront besoin de saisir le code - Cela devrait aider à lutter contre les robots spammeurs.';
$helptxt['pm_posts_per_hour'] = 'Cette option limitera le nombre de messages personnels qui pourront être envoyés par un utilisateur en une heure de temps. Cela n\'affecte pas les admins ou modérateurs.';

$helptxt['default_personal_text'] = 'Choisit le texte personnel qu\'un nouvel utilisateur aura par défaut.';

$helptxt['registration_method'] = 'Cette fonction détermine quelle méthode d\'inscription doit être adoptée pour les gens désirant rejoindre votre forum. Vous pouvez sélectionner un de ces choix&nbsp;:<br /><br />
	<ul class="normallist">
		<li>
			<strong>Inscription désactivée</strong><br />
				Désactive les procédures d\'inscription, ce qui signifie que personne ne peut plus s\'inscrire sur votre forum.<br />
		</li><li>
			<strong>Inscription immédiate</strong><br />
				Les nouveaux membres peuvent se connecter et poster sur votre forum immédiatement après la procédure d\'inscription.<br />
		</li><li>
			<strong>Activation par e-mail</strong><br />
				Lorsque cette option est sélectionnée, tous les membres qui s\'inscrivent au forum recevront un e-mail contenant un lien pour activer leur compte. Ils ne pourront utiliser leur compte que lorsque celui-ci aura été activé.<br />
		</li><li>
			<strong>Approbation par un Admin</strong><br />
				Lorsque cette option est sélectionnée, l\'inscription de tous les nouveaux utilisateurs de votre forum devra d\'abord être approuvée par les administrateurs pour être ensuite effective et leur permettre ainsi de rejoindre votre communauté.
		</li>
	</ul>';

$helptxt['send_validation_onChange'] = 'Lorsque cette option est cochée, tous les membres qui modifient leur adresse e-mail dans leur profil devront réactiver leur compte grâce à un e-mail envoyé à leur nouvelle adresse.';
$helptxt['approveAccountDeletion'] = 'When this setting is checked, any user request to delete his own account has to be approved by an administrator';

$helptxt['send_welcomeEmail'] = 'Lorsque cette option est activée, tous les nouveaux membres recevront un e-mail leur souhaitant la bienvenue sur votre communauté.';
$helptxt['password_strength'] = 'Ce réglage détermine le niveau de sécurité requis pour les mots de passe sélectionnés par les membres de votre forum. Plus ce niveau est &quot;élevé&quot;, plus il devrait être difficile de découvrir le mot de passe et de pirater leurs comptes.
	Les niveaux possibles sont&nbsp;:
	<ul>
		<li><strong>Bas&nbsp;:</strong> Le mot de passe doit être composé d\'au moins quatre caractères.</li>
		<li><strong>Moyen&nbsp;:</strong> Le mot de passe doit être formé d\'au moins huit caractères, et ne peut contenir des parties de l\'identifiant ou de l\'adresse e-mail.</li>
		<li><strong>élevé&nbsp;:</strong> Comme pour le niveau précédent, et le mot de passe doit aussi contenir des lettres majuscules et minuscules et au moins un chiffre.</li>
	</ul>';
$helptxt['enable_password_conversion'] = 'By enabling this setting, SMF will attempt to detect passwords stored in other formats and convert them to the format SMF uses. Typically this is used for forums converted to SMF, but may have other uses as well. Disabling this prevents a user from logging in using their password after a conversion and they would need to reset their password.';

$helptxt['coppaAge'] = 'La valeur spécifiée dans ce champ détermine l\'àge minimum que doit avoir un membre pour avoir un accè immédiat aux sections.
	À l\'inscription, il sera demandé aux membres de confirmer s\'ils ont plus que cet âge. Si ce n\'est pas le cas, leur inscription sera rejetée ou suspendue en attente d\'une autorisation parentale - en fonction des restrictions que vous spécifiez.
	Si la valeur est 0 pour cette option toutes les restrictions d\'âge pour les prochaines inscriptions seront ignorées.';
$helptxt['coppaType'] = 'Si la restriction d\'âge est active, ce paramètre définira ce qui se produit lorsqu\'un membre n\'ayant pas l\'âge minimum requis tente de s\'inscrire sur votre forum. Il existe deux choix possibles&nbsp;:
	<ul class="normallist">
		<li>
			<strong>Rejeter son inscription&nbsp;:</strong><br />
				N\'importe quel nouvel adhérent n\'ayant pas l\'âge requis verra son inscription rejetée immédiatement.<br />
		</li><li>
			<strong>Nécessiter l\'approbation d\'un parent/tuteur légal</strong><br />
				N\'importe quel nouvel adhérent n\'ayant pas l\'âge requis et qui tente de s\'inscrire sur votre forum verra son compte marqué en attente d\'approbation et il lui sera remis un formulaire à faire remplir par ses parents ou tuteurs avant de pouvoir devenir membre de votre forum.
				Il lui sera aussi présenté les informations de contact du forum enregistrées sur la page des paramètres, afin que le formulaire d\'approbation parentale soit envoyée à l\'administrateur par la poste ou par téléfax.
		</li>
	</ul>';
$helptxt['coppaPost'] = 'Les champs de contact doivent être completées afin que les formulaires d\'autorisation parentale pour les membres n\'ayant pas l\'âge requis soient envoyés à l\'administrateur. Ces détails seront affichés à tous les mineurs et il leur est nécessaire d\'obtenir une approbation parentale. Une adresse postale ou un numéro de téléfax est le minimum requis.';

$helptxt['allow_hideOnline'] = 'En activant cette option, les membres peuvent cacher leur statut de connexion au forum aux autres visiteurs (sauf aux administrateurs). Si elle est désactivée, seuls les utilisateurs qui peuvent modérer le forum entier peuvent cacher leur présence. Notez bien que désactiver cette option ne changera rien dans le statut des membres connectés en ce moment - cela ne leur empêchera la manœuvre que pour les futures connexions.';
$helptxt['meta_keywords'] = 'Ces mots-clés sont placés dans les entêtes de chaque page pour indiquer aux robots le type de contenu de votre site (mais cette technique n\'est plus très efficace de nos jours, NDT). Séparez les mots par des virgules, et n\'utilisez pas de HTML.';

$helptxt['latest_themes'] = 'Cette zone vous montre quelques-uns des derniers thèmes et les plus populaires en provenance de <a href="http://www.simplemachines.org/" target="_blank">www.simplemachines.org</a>. Cela peut néanmoins ne pas s\'afficher correctement si votre ordinateur a du mal à se connecter à <a href="http://www.simplemachines.org/" target="_blank">www.simplemachines.org</a>.';

$helptxt['secret_why_blank'] = 'Pour votre sécurité, la réponse à votre question (de même que votre mot de passe) est encryptée de telle manière que SMF ne peut que vérifier si vous entrez la bonne valeur, ainsi il ne peut jamais vous révéler (ni à vous ni à personne d\'autre, heureusement&nbsp;!) quelle est votre réponse ou votre mot de passe.';
$helptxt['moderator_why_missing'] = 'Puisque la modération est définie indépendamment pour chaque section, vous devrez assigner les membres en tant que modérateurs à partir de <a href="javascript:window.open(\'%1$s?action=manageboards\'); self.close();">l\'interface de gestion des sections</a>.';

$helptxt['permissions'] = 'Les permissions permettent de définir les droits accordés (ou non) aux membres pour effectuer une action particulière. Ces droits sont définis sur la base des groupes de membres <br /><br />Vous pouvez modifier ces droits sur plusieurs sections en même temps en utilisant les cases à cocher, ou modifier les permissions d\'un groupe particulier en cliquant sur le lien \'Modifier\'';
$helptxt['permissions_board'] = 'Si \'Global\' est sélectionné, cela signifie que cette section ne possèdera aucune permission particulière, et aura celles générales de votre forum. \'Local\' signifie qu\'elle aura ses propres permissions - indépendamment des permissions globales. Ceci vous permet d\'avoir des sections avec plus ou moins de permissions que d\'autres, sans navoir à régler toutes les permissions pour chaque section.';
$helptxt['permissions_quickgroups'] = 'Ceci vous permet d\'utiliser les réglages de permissions par &quot;défaut&quot; - standard signifie &quot;rien de spécial&quot;, restreint signifie &quot;comme un invité&quot;, modérateur signifie &quot;les mêmes droits qu\'un modérateur&quot;, et enfin maintenance signifie &quot;des permissions très proches de celles d\'un administrateur&quot;.';
$helptxt['permissions_deny'] = 'Interdire des permissions peut être utile quand vous voulez enlever des permissions à certains membres. Vous pouvez ajouter un groupe de membres avec une permission \'interdite\' pour les membres auxquels vous voulez interdire une permission.<br /><br />À utiliser avec précaution, une permission interdite restera interdite peu importe dans quels autres groupes de membres le membre fait partie.';
$helptxt['permissions_postgroups'] = 'Activer les permissions pour les groupes posteurs vous permettra d\'attribuer des permissions aux membres ayant posté un certain nombre de messages. Les permissions du groupe posteur sont <em>ajoutées</em> aux permissions des membres inscrits.';
$helptxt['membergroup_guests'] = 'Le groupe de membres Invités contient tous les utilisateurs qui ne sont pas connectés à un compte membre sur votre forum.';
$helptxt['membergroup_regular_members'] = 'Les membres inscrits correspondent à tous les utilisateurs ayant un compte membre sur votre forum, mais à qui aucun groupe permanent n\'a été assigné.';
$helptxt['membergroup_administrator'] = 'L\'administrateur peut, par définition, faire tout ce qu\'il veut et voir toutes les sections. Il n\'y a aucun réglage de permissions pour les administrateurs.';
$helptxt['membergroup_moderator'] = 'Le groupe Modérateur est un groupe spécial. Les permissions et réglages pour ce groupe s\'appliquent aux modérateurs mais uniquement <em>dans la (ou les) section(s) qu\'ils modèrent</em>. Au dehors de ces sections, ils sont considérés comme n\'importe quel autre membre régulier.';
$helptxt['membergroups'] = 'Dans SMF il y a deux types de groupes auxquels vos membres peuvent appartenir. Ce sont&nbsp;:
	<ul class="normallist">
		<li><strong>Groupes permanents&nbsp;:</strong> Un groupe permanent est un groupe dans lequel un membre n\'est pas assigné automatiquement. Pour assigner un membre dans un groupe permanent, allez simplement dans son profil et cliquez sur &quot;Paramètres relatifs au compte&quot;. Ici vous pouvez paramétrer les différents groupes permanents auxquels les membres peuvent appartenir.</li>
		<li><strong>Groupes posteurs&nbsp;:</strong> Au contraire des groupes permanents, un membre ne peut être manuellement assigné à un groupe posteur, basé sur le nombre de message. Les membres sont plutôt assignés automatiquement à un groupe posteur lorsqu\'ils ont atteint le nombre minimum de messages requis pour faire partie de ce groupe.</li>
	</ul>';

$helptxt['calendar_how_edit'] = 'Vous pouvez modifier ces événements en cliquant sur l\'astérisque (*) rouge accompagnant leur nom.';

$helptxt['maintenance_backup'] = 'Cette section vous permettra de faire une copie de sauvegarde des messages, des réglages, des membres et autres informations utiles de votre forum dans un gros fichier.<br /><br />Il est recommandé d\'effectuer cette opération souvent, par exemple hebdomadairement, pour plus de sécurité et de protection.';
$helptxt['maintenance_rot'] = 'Ceci vous permet de supprimer <strong>complètement</strong> et <strong>irrévocablement</strong> les vieux sujets. Vous devriez effectuer une copie de sauvegarde de votre base de données avant de procéder à cette action, au cas où vous enleveriez quelque chose que vous ne vouliez pas supprimer.<br /><br />À utiliser avec précaution.';
$helptxt['maintenance_members'] = 'Ceci vous permet d\'effacer <strong>complètement</strong> et <strong>irrévocablement</strong> des comptes de membres de votre forum. Vous devriez <strong>absolument</strong> faire une sauvegarde avant, juste au cas où vous effaceriez quelque chose que vous ne vouliez pas effacer.<br /><br />Utilisez cette option avec précaution.';

$helptxt['avatar_server_stored'] = 'Ceci permet à vos membres de choisir leur avatar parmi ceux préalablement installés sur votre serveur. Ils sont, généralement, au même endroit que votre forum SMF, dans le dossier des avatars.<br />Un conseil, si vous créez des répertoires dans ce dossier, vous pouvez faire des &quot;catégories&quot; d\'avatars.';
$helptxt['avatar_external'] = 'Ceci permet à vos membres d\'insérer l\'adresse URL de leur propre avatar. L\'inconvénient est que, dans certains cas, ils pourraient utiliser des avatars beaucoup trop gros ou des images que vous ne voulez pas voir sur votre forum.';
$helptxt['avatar_download_external'] = 'Ceci permet au forum de télécharger l\'avatar choisi par l\'utilisateur via l\'URL donnée par celui-ci. Si l\'opération réussit, l\'avatar sera traité comme un avatar transféré.';
$helptxt['avatar_action_too_large'] = 'This setting therefore lets you reject images (from other sites) that are too big, or tells the user\'s browser to resize them, or to download them to your server.<br><br>If users put in very large images as their avatars and resize in the browser, it could cause very slow loading for your users - it does not actually resize the file, it just displays it smaller. So a digital photo, for example, would still be loaded in full and then resized only when displayed - so for users this could get quite slow and use a lot of bandwidth.<br><br>On the other hand, downloading them means using your bandwidth and server space, but you also ensure that images are smaller, so it should be faster for users. (Note: downloading and resizing requires either the GD library, or ImageMagick using either the Imagick or MagickWand extensions)';
$helptxt['avatar_upload'] = 'Cette option est pratiquement la même chose que &quot;Permettre aux membres de sélectionner un avatar externe&quot;, sauf que vous avez un meilleur contrôle sur les avatars, plus de facilité pour les redimensionner, et vos membres n\'ont pas à avoir un endroit où mettre leurs avatars.<br /><br />Mais l\'inconvénient est que cela peut prendre beaucoup d\'espace sur votre serveur.';
$helptxt['avatar_download_png'] = 'Les images au format PNG sont plus lourdes, mais offrent un rendu de meilleure qualité. Si la case est décochée, le format JPEG sera utilisé à la place - ce qui donne des fichiers moins lourds, mais de moindre qualité, surtout les dessins, lesquels peuvent devenir assez flous.';

$helptxt['disableHostnameLookup'] = 'Ceci désactive la recherche du nom de l\'hôte, fonction parfois lente sur certains serveurs. Notez que sa désactivation rend le système de bannissement moins efficace.';

$helptxt['search_weight_frequency'] = 'Des facteurs de pertinence sont utilisés pour déterminer l\'intêrêt des résultats de recherche. Changez ces facteurs pour les faire correspondre à des valeurs intéressantes pour votre forum. Par exemple, un forum d\'actualités aura un facteur d\'ancienneté du message relativement bas. Toutes les valeurs sont en relation avec les autres et doivent être des valeurs positives.<br /><br />Ce facteur compte le nombre de messages correspondants et divise ce résultat par le nombre de messages dans un sujet.';
$helptxt['search_weight_age'] = 'Des facteurs de pertinence sont utilisés pour déterminer l\'intêrêt des résultats de recherche. Changez ces facteurs pour les faire correspondre à des valeurs intéressantes pour votre forum. Par exemple, un forum d\'actualités aura un relativement grand facteur de \'Âge du dernier message\'. Toutes les valeurs sont en relation avec les autres et doivent être des valeurs positives.<br /><br />Ce facteur vérifie l\'âge des derniers messages d\'un sujet. Plus récent est le message, le plus haut dans la liste il est positionné.';
$helptxt['search_weight_length'] = 'Des facteurs de pertinence sont utilisés pour déterminer l\'intêrêt des résultats de recherche. Changez ces facteurs pour les faire correspondre à des valeurs intéressantes pour votre forum. Par exemple, un forum d\'actualités aura un relativement grand facteur de \'Âge du dernier message\'. Toutes les valeurs sont en relation avec les autres et doivent être des valeurs positives.<br /><br />Ce facteur est basé sur la longueur du sujet. Plus le sujet contient de réponses, plus le pointage est élevé.';
$helptxt['search_weight_subject'] = 'Des facteurs de pertinence sont utilisés pour déterminer l\'intêrêt des résultats de recherche. Changez ces facteurs pour les faire correspondre à des valeurs intéressantes pour votre forum. Par exemple, un forum d\'actualités aura un relativement grand facteur de \'Âge du dernier message\'. Toutes les valeurs sont en relation avec les autres et doivent être des valeurs positives.<br /><br />Ce facteur vérifie si le terme recherché peut être trouvé ou non dans le titre du sujet.';
$helptxt['search_weight_first_message'] = 'Des facteurs de pertinence sont utilisés pour déterminer l\'intêrêt des résultats de recherche. Changez ces facteurs pour les faire correspondre à des valeurs intéressantes pour votre forum. Par exemple, un forum d\'actualités aura un relativement grand facteur de \'Âge du dernier message\'. Toutes les valeurs sont en relation avec les autres et doivent être des valeurs positives.<br /><br />Ce facteur vérifie si le terme recherché peut être trouvé ou non dans le premier message du sujet.';
$helptxt['search_weight_sticky'] = 'Des facteurs de pertinence sont utilisés pour déterminer l\'intêrêt des résultats de recherche. Changez ces facteurs pour les faire correspondre à des valeurs intéressantes pour votre forum. Par exemple, un forum d\'actualités aura un relativement grand facteur de \'Âge du dernier message\'. Toutes les valeurs sont en relation avec les autres et doivent être des valeurs positives.<br /><br />Ce facteur vérifie si un sujet est populaire et augmente le score de pertinence si il l\'est.';
$helptxt['search'] = 'Ajustez ici tous les réglages de la fonction recherche.';
$helptxt['search_why_use_index'] = 'Un index de recherche peut considérablement améliorer l\'exécution des recherches sur votre forum. En particulier lorsque le nombre de messages sur un forum est de plus en plus grand, la recherche sans index peut prendre un bon moment et augmenter la pression sur votre base de données. Si votre forum a plus de 50.000 messages, vous devriez penser à créer un index de recherche pour assurer un fonctionnement optimal de votre forum.<br /><br />À noter qu\'un index de recherche peut prendre un certain espace... Un index à texte intégral est un index géré par MySQL. C\'est relativement compact (approximativement la même taille que la table message), mais beaucoup de mots ne sont pas indexés et il se peut que quelques recherches s\'avèrent très lentes. L\'index personnalisé est souvent plus grand (selon votre configuration, cela peut ètre plus de 3 fois la taille de la table des messages) mais la performance est meilleure qu\'en texte intégral et relativement stable.';

$helptxt['see_admin_ip'] = 'Les adresses IP sont affichées aux administrateurs et aux modérateurs afin de faciliter la modération et de rendre plus efficace la surveillance des personnes se conduisant mal sur ce forum.  Rappelez-vous que les adresses IP ne peuvent pas toujours être identifiées, et que la plupart des adresses changent périodiquement.<br /><br />Les membres sont aussi autorisés à voir leur adresse IP, mais pas celle des autres.';
$helptxt['see_member_ip'] = 'Votre adresse IP est affichée seulement à vous et aux modérateurs.  Rappelez-vous que cette information ne permet pas de vous identifier en tant qu\'individu, et que la plupart des adresses changent périodiquement.<br /><br />Vous ne pouvez pas voir l\'adresse IP des autres, et les autres ne peuvent pas voir la vôtre.';
$helptxt['whytwoip'] = 'SMF utilise plusieurs méthodes pour détecter les adresses IP d\'un utilisateur. Habituellement ces deux méthodes donnent la même adresse mais dans certains cas plus d\'une adresse peut être détectée. Dans ce cas SMF conserve les adresses, et les utilise par exemple lors des contrôles de bannissement. Vous pouvez cliquer sur chaque adresse pour traquer cette IP et la bannir si nécessaire.';

$helptxt['ban_cannot_post'] = 'La restriction \'Ne peut pas poster\' a pour conséquence que le forum n\'est accessible qu\'en lecture seule pour l\'utilisateur banni. L\'utilisateur ne peut pas créer de nouveaux sujets ou répondre à ceux existants, envoyer des messages personnels ou voter dans les sondages. L\'utilisateur banni peut toutefois encore lire ses messages personnels et les sujets.<br /><br />Un message d\'avertissement est affiché aux utilisateurs qui sont bannis avec cette restriction.';

$helptxt['posts_and_topics'] = '
	<ul>
		<li>
			<strong>Paramètres des messages</strong><br />
			Modifie les paramètres relatifs au postage des messages et la façon dont ceux-ci sont affichés. Vous pouvez aussi activer le correcteur orthographique ici.
		</li><li>
			<strong>Code d\'affichage</strong><br />
			Active le code montrant les messages dans un rendu correct. Ajuste aussi quels codes sont permis et ceux qui sont désactivés.
		</li><li>
			<strong>Mots censurés</strong>
			Afin de conserver un registre de langage convenable sur votre forum, vous pouvez censurer certains mots. Cette fonction vous permet de convertir des mots interdits en d\'autres mots innocents. D\'où une possibilité dérivé de remplacement de termes choisis.
		</li><li>
			<strong>Paramètres des sujets</strong>
			Modifie les paramètres relatifs aux sujets&nbsp;: le nombre de sujets par page, l\'activation ou non des sujets épinglés, le nombre minimal de messages par sujet pour qu\'il soit noté comme populaire, etc.
		</li>
	</ul>';

$helptxt['spider_mode'] = 'Sets the logging level.<br>
Standard - Logs minimal spider activity.<br>
Moderate - Provides more accurate statistics.<br>
Agressive - As for &quot;Moderate&quot; but logs data about each page visited.';

$helptxt['spider_group'] = 'En sélectionnant un groupe restrictif, lorsqu\'un invité est identifié comme moteur de recherche, certaines permissions lui seront niées (autrement dit &quot;Interdites&quot;), par rapport aux permissions normales d\'un invité. Vous pouvez utiliser ceci pour donner moins d\'accès à un moteur de recherche par rapport à un invité normal. Vous pouvez par exemple vouloir créer un nouveau groupe appelé &quot;Robots&quot; et le sélectionner ici. Vous pourriez donc interdire à ce groupe la permission de voir les profils pour empêcher l\'indexation par les robots des profils de vos membres.<br />Note: La détection des robots n\'est pas parfaite et peut être simulée par les utilisateurs, donc cette fonctionnalité n\'est pas garantie pour restreindre le contenu aux seuls moteurs de recherche que vous avez ajoutés.';
$helptxt['show_spider_online'] = 'Ce paramètre vous permet de choisir si les robots seront montrés ou pas sur la liste des utilisateurs en ligne et la page &quot;Qui est en ligne&quot;. Les options&nbsp;:
	<ul class="normallist">
		<li>
			<strong>Pas du tout</strong><br />
			Les robots seront montrés en tant qu\'invités aux autres utilisateurs.
		</li><li>
			<strong>Montrer le nombre de robots</strong><br />
			L\'accueil du forum indiquera le nombre de robots visitant actuellement le forum.
		</li><li>
			<strong>Montrer le nom des robots</strong><br />
			Les noms des robots seront montrés, les utilisateurs sauront ainsi combien de chaque type de robot visite le forum - valable à la fois pour l\'accueil du forum et la page Qui est en ligne.
		</li><li>
			<strong>Montrer le nom des robots, mais juste à l\'administrateur</strong><br />
			Comme ci-dessus, mais seuls les Administrateurs pourront voir le statut des robots - pour les autres utilisateurs, les robots seront affichés comme étant des invités.
		</li>
	</ul>';

$helptxt['birthday_email'] = 'Choisissez le modèle du message d\'anniversaire par e-mail à utiliser. Une prévisualisation sera affichée dans le sujet de l\'e-mail et les champs du corps de l\'e-mail.<br /><strong>Attention</strong>, régler cette option n\'active pas automatiquement les e-mails d\'anniversaire. Pour activer les e-mails d\'anniversaire, utilisez la page <a href="%1$s?action=admin;area=scheduledtasks;%3$s=%2$s" target="_blank" class="new_win">Tâches Programmées</a> et activez la tâche E-mail d\'anniversaire.';
$helptxt['pm_bcc'] = 'Lorsque vous envoyez un message personnel vous pouvez choisir d\'ajouter comme destinataire un BCC (soit &quot;Blind Carbon Copy&quot;). L\'existence et l\'identité des destinataires BCC seront cachées aux autres destinataires du message.';

$helptxt['move_topics_maintenance'] = 'Ceci vous permet de déplacer tous les sujets d\'une section vers une autre.';
$helptxt['maintain_reattribute_posts'] = 'Vous pouvez utiliser cette fonction pour attribuer des messsages d\'invités de votre forum à un membre inscrit. Ceci est très utile par exemple si un utilisateur a effacé son compte, a changé d\'idée et veut récupérer les anciens messages associé à son compte.';
$helptxt['chmod_flags'] = 'Vous pouvez choisir manuellement les permissions que vous voulez appliquer aux fichiers sélectionnés. Pour ce faire, entrez la valeur du chmod en valeur numérique (en base 8). Note - ces indicateurs n\'auront aucun effet sur les systèmes d\'exploitation Microsoft Windows.';

$helptxt['postmod'] = 'Cette section permet aux membres de l\'équipe de modération disposant des permissions nécessaires, d\'approuver les messages et sujets avant leur apparition en ligne.';

$helptxt['field_show_enclosed'] = 'Entoure le texte entré par l\'utilisateur par du texte ou du HTML, vous permettant d\'ajouter des fournisseurs de messagerie instantanée supplémentaires, des images ou intégrations multimédia, etc. Par exemple&nbsp;:<br /><br />
		&lt;a href="http://website.com/{INPUT}"&gt;&lt;img src="{DEFAULT_IMAGES_URL}/icon.gif" alt="{INPUT}" /&gt;&lt;/a&gt;<br /><br />
		À noter que vous pouvez utiliser les variables suivantes&nbsp;:<br />
		<ul class="normallist">
			<li>{INPUT} - Le texte entré par l\'utilisateur.</li>
			<li>{SCRIPTURL} - Adresse web (URL) du forum.</li>
			<li>{IMAGES_URL} - URL du dossier images dans le thème actuel de l\'utilisateur.</li>
			<li>{DEFAULT_IMAGES_URL} - URL du dossier images dans le thème par défaut.</li>
		</ul>';

$helptxt['custom_mask'] = 'Le masque d\'entrée est important pour la sécurité de votre forum. Valider le texte entré par un utilisateur peut vous permettre d\'éviter que ses données ne soient pas utilisées de manière inattendue. Vous pouvez utiliser des expressions régulières pour vous y aider.<br /><br />
	<div class="smalltext" style="margin: 0 2em">
		&quot;[A-Za-z]+&quot; - Correspond à toutes les lettres de l\'alphabet, minuscules et majuscules.<br />
		&quot;[0-9]+&quot; - Correspond à tous les chiffres.<br />
		&quot;[A-Za-z0-9]{7}&quot; - Correspond à une suite de sept chiffres et/ou lettres de l\'alphabet, minuscules ou majuscules.<br />
		&quot;[^0-9]?&quot; - Empêche la présence à cet endroit d\'un chiffre.<br />
		&quot;^([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$&quot; - N\'autoriser que 3 ou 6 caractères hexadécimaux.<br />
	</div><br /><br />
	De plus, vous pouvez utiliser les méta-caractères spéciaux ?+*^$ et {xx}.
	<div class="smalltext" style="margin: 0 2em">
		? - Rien, ou une occurrence de l\'expression qui précède.<br />
		+ - Au moins une occurrence de l\'expression qui précède.<br />
		* - Rien, ou au moins une occurrence de l\'expression qui précède.<br />
		{xx} - xx occurrences de l\'expression qui précède.<br />
		{xx,} - xx occurrences, ou plus, de l\'expression qui précède.<br />
		{,xx} - Jusqu\'à xx occurrences de l\'expression qui précède.<br />
		{xx,yy} - Entre xx et yy occurrences de l\'expression qui précède.<br />
		$ - Début de chaîne.<br />
		^ - Fin de chaîne.<br />
		\\ - Échappe le caractère suivant.<br />
	</div><br /><br />
	Vous pourrez trouver plus d\'informations et d\'exemples sur le Net.';

$helptxt['topic_move_any'] = 'If checked, users will be allowed to move topics to any board they can see. Otherwise, they will only be able to move them to boards where they can post new topics.';

$helptxt['alert_pm_new'] = 'Notifications of new personal messages do not appear in the Alerts pane, but appear in the "My Messages" list instead.';
$helptxt['alert_event_new'] = 'This will send out an alert or email as requested if there is a new calendar event added. However, if that event is posted and a topic is added, you will not get an alert for the event if you\'re already following that board - the alert from following the board would cover this.';

$helptxt['force_ssl'] = '<b>Test SSL and HTTPS on your server properly before enabling this, it may cause your forum to become inaccessible.</b> Enable maintenance mode if you are unable to access the forum after enabling this';
$helptxt['image_proxy_enabled'] = 'Required for embedding external images when in full SSL';
$helptxt['image_proxy_secret'] = 'Keep this a secret, protects your forum from hotlinking images. Change it in order to render current hotlinked images useless';
$helptxt['image_proxy_maxsize'] = 'Maximum image size that the SSL image proxy will cache: bigger images will be not be cached. Cached images are stored in your SMF cache folder, so make sure you have enough free space.';

?>