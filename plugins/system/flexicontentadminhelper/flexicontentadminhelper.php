<?php
/**
 * @package         FLEXIcontent
 * @subpackage      plg_system_flexicontentadminhelper
 * @version         1.0.0
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * Injects a universal search/filter bar into FlexiContent admin config forms
 * to make 300+ field manifests manageable.
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

class PlgSystemFlexicontentadminhelper extends CMSPlugin
{
	protected $autoloadLanguage = true;

	private static $injected = false;

	/**
	 * Inject search bar assets on admin pages that render FlexiContent config forms.
	 */
	public function onBeforeRender()
	{
		if (self::$injected)
		{
			return;
		}

		$app = Factory::getApplication();

		if (!$app->isClient('administrator'))
		{
			return;
		}

		$input  = $app->input;
		$option = $input->getCmd('option', '');

		$enabledRaw = $this->params->get('enabled_contexts', 'com_flexicontent,com_modules,com_config,com_plugins');
		$enabled    = is_array($enabledRaw) ? $enabledRaw : array_filter(array_map('trim', explode(',', (string) $enabledRaw)));

		if (!in_array($option, $enabled, true))
		{
			return;
		}

		if ($option !== 'com_flexicontent' && !$this->isFlexicontentExtension($option, $input))
		{
			return;
		}

		$doc = $app->getDocument();

		if (!method_exists($doc, 'getWebAssetManager'))
		{
			return;
		}

		$wam      = $doc->getWebAssetManager();
		$baseUrl  = Uri::root() . 'plugins/system/flexicontentadminhelper/assets';

		// Auto cache-bust on every file change — uses the larger of the JS/CSS mtimes.
		$pluginDir = __DIR__;
		$mtimeJs   = @filemtime($pluginDir . '/assets/js/fc-config-search.js');
		$mtimeCss  = @filemtime($pluginDir . '/assets/css/fc-config-search.css');
		$mtime     = max((int) $mtimeJs, (int) $mtimeCss);
		$version   = $mtime > 0 ? (string) $mtime : '1.0.4';

		$wam->registerAndUseStyle(
			'plg_system_flexicontentadminhelper.search',
			$baseUrl . '/css/fc-config-search.css',
			['version' => $version]
		);

		$wam->registerAndUseScript(
			'plg_system_flexicontentadminhelper.search',
			$baseUrl . '/js/fc-config-search.js',
			['version' => $version],
			['defer' => true]
		);

		$minChars = (int) $this->params->get('min_chars', 2);
		if ($minChars < 1) { $minChars = 1; }
		if ($minChars > 5) { $minChars = 5; }

		$strings = [
			'searchLabel'       => Text::_('PLG_SYSTEM_FLEXICONTENTADMINHELPER_SEARCH_LABEL'),
			'searchPlaceholder' => Text::_('PLG_SYSTEM_FLEXICONTENTADMINHELPER_SEARCH_PLACEHOLDER'),
			'clearLabel'        => Text::_('PLG_SYSTEM_FLEXICONTENTADMINHELPER_CLEAR_LABEL'),
			'navLabel'          => Text::_('PLG_SYSTEM_FLEXICONTENTADMINHELPER_NAV_LABEL'),
			'matchOne'          => Text::_('PLG_SYSTEM_FLEXICONTENTADMINHELPER_MATCH_ONE'),
			'matchMany'         => Text::_('PLG_SYSTEM_FLEXICONTENTADMINHELPER_MATCH_MANY'),
			'matchNone'         => Text::_('PLG_SYSTEM_FLEXICONTENTADMINHELPER_MATCH_NONE'),
			'matchNoneInTab'    => Text::_('PLG_SYSTEM_FLEXICONTENTADMINHELPER_MATCH_NONE_IN_TAB'),
			'counterTemplate'   => Text::_('PLG_SYSTEM_FLEXICONTENTADMINHELPER_COUNTER'),
			'tabAriaLabel'      => Text::_('PLG_SYSTEM_FLEXICONTENTADMINHELPER_TAB_ARIA'),
		];

		// Enable verbose console logging when ?fcsearchdiag=1 is in URL OR when constant set.
		$diag = (bool) $input->getInt('fcsearchdiag', 0) || (defined('FC_ADMIN_HELPER_DIAG') && FC_ADMIN_HELPER_DIAG);

		$config = [
			'minChars'         => $minChars,
			'inputDebounce'    => 150,
			'announceDelay'    => 600,
			'mutationDebounce' => 300,
			'diag'             => $diag,
			'strings'          => $strings,
		];

		$json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		$wam->addInlineScript(
			'window.FlexicontentAdminHelper = window.FlexicontentAdminHelper || {}; window.FlexicontentAdminHelper.config = ' . $json . ';',
			['name' => 'plg_system_flexicontentadminhelper.config'],
			['position' => 'before'],
			['plg_system_flexicontentadminhelper.search']
		);

		self::$injected = true;
	}

	/**
	 * For com_modules / com_config / com_plugins, only inject when editing a FlexiContent extension.
	 */
	private function isFlexicontentExtension($option, $input)
	{
		switch ($option)
		{
			case 'com_modules':
				$id = (int) $input->getInt('id', 0);
				if ($id <= 0)
				{
					return false;
				}

				try
				{
					$db    = Factory::getDbo();
					$query = $db->getQuery(true)
						->select($db->quoteName('module'))
						->from($db->quoteName('#__modules'))
						->where($db->quoteName('id') . ' = ' . $id);
					$db->setQuery($query);
					$module = (string) $db->loadResult();
				}
				catch (\Throwable $e)
				{
					return false;
				}

				return strpos($module, 'mod_flexicontent') === 0;

			case 'com_config':
				return $input->getCmd('component', '') === 'com_flexicontent';

			case 'com_plugins':
				$extId = (int) $input->getInt('extension_id', 0);
				if ($extId <= 0)
				{
					return false;
				}

				try
				{
					$db    = Factory::getDbo();
					$query = $db->getQuery(true)
						->select($db->quoteName(['element', 'folder']))
						->from($db->quoteName('#__extensions'))
						->where($db->quoteName('extension_id') . ' = ' . $extId);
					$db->setQuery($query);
					$row = $db->loadObject();
				}
				catch (\Throwable $e)
				{
					return false;
				}

				if (!$row)
				{
					return false;
				}

				return strpos((string) $row->folder, 'flexicontent') !== false
					|| strpos((string) $row->element, 'flexi') === 0;

			default:
				return false;
		}
	}
}
