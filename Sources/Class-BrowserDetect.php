<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Class browser_detector
 *  This class is an experiment for the job of correctly detecting browsers and settings needed for them.
 * - Detects the following browsers
 * - Opera, Webkit, Firefox, Web_tv, Konqueror, IE, Gecko
 * - Webkit variants: Chrome, iphone, blackberry, android, safari, ipad, ipod
 * - Opera Versions: 6, 7, 8 ... 10 ... and mobile mini and mobi
 * - Firefox Versions: 1, 2, 3 .... 11 ...
 * - Chrome Versions: 1 ... 18 ...
 * - IE Versions: 4, 5, 5.5, 6, 7, 8, 9, 10 ... mobile and Mac
 * - MS Edge
 * - Nokia
 */
class browser_detector
{
	/**
	 * @var array Holds all the browser information. Its contents will be placed into $context['browser']
	 */
	private $_browsers = null;

	/**
	 * @var boolean Whether or not this might be a mobile device
	 */
	private $_is_mobile = null;

	/**
	 * The main method of this class, you know the one that does the job: detect the thing.
	 *  - determines the user agent (browser) as best it can.
	 */
	function detectBrowser()
	{
		global $context, $user_info;

		// Init
		$this->_browsers = array();
		$this->_is_mobile = false;

		// Initialize some values we'll set differently if necessary...
		$this->_browsers['needs_size_fix'] = false;

		// One at a time, one at a time, and in this order too
		if ($this->isOpera())
			$this->setupOpera();
		// Meh...
		elseif ($this->isEdge())
			$this->setupEdge();
		// Them webkits need to be set up too
		elseif ($this->isWebkit())
			$this->setupWebkit();
		// We may have work to do on Firefox...
		elseif ($this->isFirefox())
			$this->setupFirefox();
		// Old friend, old frenemy
		elseif ($this->isIe())
			$this->setupIe();

		// Just a few mobile checks
		$this->isOperaMini();
		$this->isOperaMobi();

		// IE11 seems to be fine by itself without being lumped into the "is_ie" category
		$this->isIe11();

		// Be you robot or human?
		if ($user_info['possibly_robot'])
		{
			// This isn't meant to be reliable, it's just meant to catch most bots to prevent PHPSESSID from showing up.
			$this->_browsers['possibly_robot'] = !empty($user_info['possibly_robot']);

			// Robots shouldn't be logging in or registering.  So, they aren't a bot.  Better to be wrong than sorry (or people won't be able to log in!), anyway.
			if ((isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('login', 'login2', 'register', 'signup'))) || !$user_info['is_guest'])
				$this->_browsers['possibly_robot'] = false;
		}
		else
			$this->_browsers['possibly_robot'] = false;

		// Fill out the historical array as needed to support old mods that don't use isBrowser
		$this->fillInformation();

		// Last step ...
		$this->setupBrowserPriority();

		// Now see what you've done!
		$context['browser'] = $this->_browsers;
	}

	/**
	 * Determine if the browser is Opera or not
	 *
	 * @return boolean Whether or not this is Opera
	 */
	function isOpera()
	{
		if (!isset($this->_browsers['is_opera']))
			$this->_browsers['is_opera'] = strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') !== false;
		return $this->_browsers['is_opera'];
	}

	/**
	 * Determine if the browser is IE or not
	 *
	 * @return boolean true Whether or not the browser is IE
	 */
	function isIe()
	{
		// I'm IE, Yes I'm the real IE; All you other IEs are just imitating.
		if (!isset($this->_browsers['is_ie']))
			$this->_browsers['is_ie'] = !$this->isOpera() && !$this->isGecko() && !$this->isWebTv() && preg_match('~MSIE \d+~', $_SERVER['HTTP_USER_AGENT']) === 1;
		return $this->_browsers['is_ie'];
	}

	/**
	 * Determine if the browser is IE11 or not
	 *
	 * @return boolean Whether or not the browser is IE11
	 */
	function isIe11()
	{
		// IE11 is a bit different than earlier versions
		// The isGecko() part is to ensure we get this right...
		if (!isset($this->_browsers['is_ie11']))
			$this->_browsers['is_ie11'] = strpos($_SERVER['HTTP_USER_AGENT'], 'Trident') !== false && $this->isGecko();
		return $this->_browsers['is_ie11'];
	}

	/**
	 * Determine if the browser is Edge or not
	 *
	 * @return boolean Whether or not the browser is Edge
	 */
	function isEdge()
	{
		if (!isset($this->_browsers['is_edge']))
			$this->_browsers['is_edge'] = strpos($_SERVER['HTTP_USER_AGENT'], 'Edge') !== false;
		return $this->_browsers['is_edge'];
	}

	/**
	 * Determine if the browser is a Webkit based one or not
	 *
	 * @return boolean Whether or not this is a Webkit-based browser
	 */
	function isWebkit()
	{
		if (!isset($this->_browsers['is_webkit']))
			$this->_browsers['is_webkit'] = strpos($_SERVER['HTTP_USER_AGENT'], 'AppleWebKit') !== false;
		return $this->_browsers['is_webkit'];
	}

	/**
	 * Determine if the browser is Firefox or one of its variants
	 *
	 * @return boolean Whether or not this is Firefox (or one of its variants)
	 */
	function isFirefox()
	{
		if (!isset($this->_browsers['is_firefox']))
			$this->_browsers['is_firefox'] = preg_match('~(?:Firefox|Ice[wW]easel|IceCat|Shiretoko|Minefield)/~', $_SERVER['HTTP_USER_AGENT']) === 1 && $this->isGecko();
		return $this->_browsers['is_firefox'];
	}

	/**
	 * Determine if the browser is WebTv or not
	 *
	 * @return boolean Whether or not this is WebTV
	 */
	function isWebTv()
	{
		if (!isset($this->_browsers['is_web_tv']))
			$this->_browsers['is_web_tv'] = strpos($_SERVER['HTTP_USER_AGENT'], 'WebTV') !== false;
		return $this->_browsers['is_web_tv'];
	}

	/**
	 * Determine if the browser is konqueror or not
	 *
	 * @return boolean Whether or not this is Konqueror
	 */
	function isKonqueror()
	{
		if (!isset($this->_browsers['is_konqueror']))
			$this->_browsers['is_konqueror'] = strpos($_SERVER['HTTP_USER_AGENT'], 'Konqueror') !== false;
		return $this->_browsers['is_konqueror'];
	}

	/**
	 * Determine if the browser is Gecko or not
	 *
	 * @return boolean Whether or not this is a Gecko-based browser
	 */
	function isGecko()
	{
		if (!isset($this->_browsers['is_gecko']))
			$this->_browsers['is_gecko'] = strpos($_SERVER['HTTP_USER_AGENT'], 'Gecko') !== false && !$this->isWebkit() && !$this->isKonqueror();
		return $this->_browsers['is_gecko'];
	}

	/**
	 * Determine if the browser is Opera Mini or not
	 *
	 * @return boolean Whether or not this is Opera Mini
	 */
	function isOperaMini()
	{
		if (!isset($this->_browsers['is_opera_mini']))
			$this->_browsers['is_opera_mini'] = (isset($_SERVER['HTTP_X_OPERAMINI_PHONE_UA']) || stripos($_SERVER['HTTP_USER_AGENT'], 'opera mini') !== false);
		if ($this->_browsers['is_opera_mini'])
			$this->_is_mobile = true;
		return $this->_browsers['is_opera_mini'];
	}

	/**
	 * Determine if the browser is Opera Mobile or not
	 *
	 * @return boolean Whether or not this is Opera Mobile
	 */
	function isOperaMobi()
	{
		if (!isset($this->_browsers['is_opera_mobi']))
			$this->_browsers['is_opera_mobi'] = stripos($_SERVER['HTTP_USER_AGENT'], 'opera mobi') !== false;
		if ($this->_browsers['is_opera_mobi'])
			$this->_is_mobile = true;
		return $this->_browsers['is_opera_mini'];
	}

	/**
	 * Detect Safari / Chrome / iP[ao]d / iPhone / Android / Blackberry from webkit.
	 *  - set the browser version for Safari and Chrome
	 *  - set the mobile flag for mobile based useragents
	 */
	private function setupWebkit()
	{
		$this->_browsers += array(
			'is_chrome' => strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome') !== false,
			'is_iphone' => (strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone') !== false || strpos($_SERVER['HTTP_USER_AGENT'], 'iPod') !== false) && strpos($_SERVER['HTTP_USER_AGENT'], 'iPad') === false,
			'is_blackberry' => stripos($_SERVER['HTTP_USER_AGENT'], 'BlackBerry') !== false || strpos($_SERVER['HTTP_USER_AGENT'], 'PlayBook') !== false,
			'is_android' => strpos($_SERVER['HTTP_USER_AGENT'], 'Android') !== false,
			'is_nokia' => strpos($_SERVER['HTTP_USER_AGENT'], 'SymbianOS') !== false,
		);

		// blackberry, playbook, iphone, nokia, android and ipods set a mobile flag
		if ($this->_browsers['is_iphone'] || $this->_browsers['is_blackberry'] || $this->_browsers['is_android'] || $this->_browsers['is_nokia'])
			$this->_is_mobile = true;

		// @todo what to do with the blaPad? ... for now leave it detected as Safari ...
		$this->_browsers['is_safari'] = strpos($_SERVER['HTTP_USER_AGENT'], 'Safari') !== false && !$this->_browsers['is_chrome'] && !$this->_browsers['is_iphone'];
		$this->_browsers['is_ipad'] = strpos($_SERVER['HTTP_USER_AGENT'], 'iPad') !== false;

		// if Chrome, get the major version
		if ($this->_browsers['is_chrome'])
		{
			if (preg_match('~chrome[/]([0-9][0-9]?[.])~i', $_SERVER['HTTP_USER_AGENT'], $match) === 1)
				$this->_browsers['is_chrome' . (int) $match[1]] = true;
		}

		// or if Safari get its major version
		if ($this->_browsers['is_safari'])
		{
			if (preg_match('~version/?(.*)safari.*~i', $_SERVER['HTTP_USER_AGENT'], $match) === 1)
				$this->_browsers['is_safari' . (int) trim($match[1])] = true;
		}
	}

	/**
	 * Additional IE checks and settings.
	 *  - determines the version of the IE browser in use
	 *  - detects ie4 onward
	 *  - attempts to distinguish between IE and IE in compatibility view
	 *  - checks for old IE on macs as well, since we can
	 */
	private function setupIe()
	{
		$this->_browsers['is_ie_compat_view'] = false;

		// get the version of the browser from the msie tag
		if (preg_match('~MSIE\s?([0-9][0-9]?.[0-9])~i', $_SERVER['HTTP_USER_AGENT'], $msie_match) === 1)
		{
			$msie_match[1] = trim($msie_match[1]);
			$msie_match[1] = (($msie_match[1] - (int) $msie_match[1]) == 0) ? (int) $msie_match[1] : $msie_match[1];
			$this->_browsers['is_ie' . $msie_match[1]] = true;
		}

		// "modern" ie uses trident 4=ie8, 5=ie9, 6=ie10, 7=ie11 even in compatibility view
		if (preg_match('~Trident/([0-9.])~i', $_SERVER['HTTP_USER_AGENT'], $trident_match) === 1)
		{
			$this->_browsers['is_ie' . ((int) $trident_match[1] + 4)] = true;

			// If trident is set, see the (if any) msie tag in the user agent matches ... if not its in some compatibility view
			if (isset($msie_match[1]) && ($msie_match[1] < $trident_match[1] + 4))
				$this->_browsers['is_ie_compat_view'] = true;
		}

		// Detect true IE6 and IE7 and not IE in compat mode.
		$this->_browsers['is_ie7'] = !empty($this->_browsers['is_ie7']) && ($this->_browsers['is_ie_compat_view'] === false);
		$this->_browsers['is_ie6'] = !empty($this->_browsers['is_ie6']) && ($this->_browsers['is_ie_compat_view'] === false);

		// IE mobile 7 or 9, ... shucks why not
		if ((!empty($this->_browsers['is_ie7']) && strpos($_SERVER['HTTP_USER_AGENT'], 'IEMobile/7') !== false) || (!empty($this->_browsers['is_ie9']) && strpos($_SERVER['HTTP_USER_AGENT'], 'IEMobile/9') !== false))
		{
			$this->_browsers['is_ie_mobi'] = true;
			$this->_is_mobile = true;
		}

		// And some throwbacks to a bygone era, deposited here like cholesterol in your arteries
		$this->_browsers += array(
			'is_ie4' => !empty($this->_browsers['is_ie4']) && !$this->_browsers['is_web_tv'],
			'is_mac_ie' => strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 5.') !== false && strpos($_SERVER['HTTP_USER_AGENT'], 'Mac') !== false
		);

		// Before IE8 we need to fix IE... lots!
		$this->_browsers['ie_standards_fix'] = (($this->_browsers['is_ie6'] === true) || ($this->_browsers['is_ie7'] === true)) ? true : false;

		// We may even need a size fix...
		$this->_browsers['needs_size_fix'] = (!empty($this->_browsers['is_ie5']) || !empty($this->_browsers['is_ie5.5']) || !empty($this->_browsers['is_ie4'])) && !$this->_browsers['is_mac_ie'];
	}

	/**
	 * Additional firefox checks.
	 * - Gets the version of the FF browser in use
	 * - Considers all FF variants as FF including IceWeasel, IceCat, Shiretoko and Minefiled
	 */
	private function setupFirefox()
	{
		if (preg_match('~(?:Firefox|Ice[wW]easel|IceCat|Shiretoko|Minefield)[\/ \(]([^ ;\)]+)~', $_SERVER['HTTP_USER_AGENT'], $match) === 1)
			$this->_browsers['is_firefox' . (int) $match[1]] = true;
	}

	/**
	 * More Opera checks if we are opera.
	 *  - checks for the version of Opera in use
	 *  - uses checks for 10 first and falls through to <9
	 */
	private function setupOpera()
	{
		// Opera 10+ uses the version tag at the end of the string
		if (preg_match('~\sVersion/([0-9]+)\.[0-9]+(?:\s*|$)~', $_SERVER['HTTP_USER_AGENT'], $match))
			$this->_browsers['is_opera' . (int) $match[1]] = true;
		// Opera pre 10 is supposed to uses the Opera tag alone, as do some spoofers
		elseif (preg_match('~Opera[ /]([0-9]+)(?!\\.[89])~', $_SERVER['HTTP_USER_AGENT'], $match))
			$this->_browsers['is_opera' . (int) $match[1]] = true;

		// Needs size fix?
		$this->_browsers['needs_size_fix'] = !empty($this->_browsers['is_opera6']);
	}

	/**
	 * Sets the version number for MS edge.
	 */
	private function setupEdge()
	{
		if (preg_match('~Edge[\/]([0-9][0-9]?[\.][0-9][0-9])~i', $_SERVER['HTTP_USER_AGENT'], $match) === 1)
			$this->_browsers['is_edge' . (int) $match[1]] = true;
	}

	/**
	 * Get the browser name that we will use in the <body id="this_browser">
	 *  - The order of each browser in $browser_priority is important
	 *  - if you want to have id='ie6' and not id='ie' then it must appear first in the list of ie browsers
	 *  - only sets browsers that may need some help via css for compatibility
	 */
	private function setupBrowserPriority()
	{
		global $context;

		if ($this->_is_mobile)
			$context['browser_body_id'] = 'mobile';
		else
		{
			// add in any specific detection conversions here if you want a special body id e.g. 'is_opera9' => 'opera9'
			$browser_priority = array(
				'is_ie6' => 'ie6',
				'is_ie7' => 'ie7',
				'is_ie8' => 'ie8',
				'is_ie9' => 'ie9',
				'is_ie10' => 'ie10',
				'is_ie11' => 'ie11',
				'is_ie' => 'ie',
				'is_edge' => 'edge',
				'is_firefox' => 'firefox',
				'is_chrome' => 'chrome',
				'is_safari' => 'safari',
				'is_opera10' => 'opera10',
				'is_opera11' => 'opera11',
				'is_opera12' => 'opera12',
				'is_opera' => 'opera',
				'is_konqueror' => 'konqueror',
			);

			$context['browser_body_id'] = 'smf';
			$active = array_reverse(array_keys($this->_browsers, true));
			foreach ($active as $browser)
			{
				if (array_key_exists($browser, $browser_priority))
				{
					$context['browser_body_id'] = $browser_priority[$browser];
					break;
				}
			}
		}
	}

	/**
	 * Fill out the historical array
	 *  - needed to support old mods that don't use isBrowser
	 */
	function fillInformation()
	{
		$this->_browsers += array(
			'is_opera' => false,
			'is_opera6' => false,
			'is_opera7' => false,
			'is_opera8' => false,
			'is_opera9' => false,
			'is_opera10' => false,
			'is_webkit' => false,
			'is_mac_ie' => false,
			'is_web_tv' => false,
			'is_konqueror' => false,
			'is_firefox' => false,
			'is_firefox1' => false,
			'is_firefox2' => false,
			'is_firefox3' => false,
			'is_iphone' => false,
			'is_android' => false,
			'is_chrome' => false,
			'is_safari' => false,
			'is_gecko' => false,
			'is_edge' => false,
			'is_ie8' => false,
			'is_ie7' => false,
			'is_ie6' => false,
			'is_ie5.5' => false,
			'is_ie5' => false,
			'is_ie' => false,
			'is_ie4' => false,
			'ie_standards_fix' => false,
			'needs_size_fix' => false,
			'possibly_robot' => false,
		);
	}
}

?>