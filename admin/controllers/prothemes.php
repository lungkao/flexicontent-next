<?php
/**
 * @package         FLEXIcontent
 * @subpackage      Pro Templates
 *
 * @author          FLEXIcontent Team
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2024, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;

JLoader::register('FlexicontentControllerBaseAdmin', JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'controllers' . DS . 'base' . DS . 'baseadmin.php');

require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'protheme.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'prothemes.php';

/**
 * Pro Themes Controller — handles list, CRUD, AJAX save.
 */
#[AllowDynamicProperties]
class FlexicontentControllerProthemes extends FlexicontentControllerBaseAdmin
{
	var $records_dbtbl  = 'flexicontent_pro_themes';
	var $records_jtable = 'flexicontent_pro_themes';

	var $record_name    = 'protheme';
	var $record_name_pl = 'prothemes';

	var $_NAME = 'PROTHEME';

	var $runMode = 'standalone';

	var $exitHttpHead = null;
	var $exitMessages = [];
	var $exitLogTexts = [];
	var $exitSuccess  = true;

	public function __construct($config = [])
	{
		parent::__construct($config);
	}

	// -------------------------------------------------------------------------
	// AJAX: Save theme JSON from the theme editor
	// -------------------------------------------------------------------------

	/**
	 * task=prothemes.saveJson
	 * POST: id, theme_json, {token}=1
	 */
	public function saveJson(): void
	{
		$app    = Factory::getApplication();
		$jinput = $app->input;

		Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

		$id   = (int) $jinput->getInt('id', 0);
		$json = $jinput->getRaw('theme_json', '');

		/** @var FlexicontentModelProtheme $model */
		$model = $this->getModel('protheme', '', []);

		$success = $model->saveThemeData($id, $json);

		echo new JsonResponse(
			$success ? ['id' => $id] : null,
			$success ? '' : $model->getError(),
			!$success
		);

		$app->close();
	}

	// -------------------------------------------------------------------------
	// Create theme from preset (chooser screen submit handler)
	// -------------------------------------------------------------------------

	/**
	 * task=prothemes.createFromPreset
	 *
	 * POST:
	 *   preset_key   — key from PresetLibrary OR 'blank-theme'
	 *   title        — user-entered title
	 *   {token}=1    — CSRF
	 */
	public function createFromPreset(): void
	{
		$app    = Factory::getApplication();
		$jinput = $app->input;

		Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

		$presetKey = (string) $jinput->getCmd('preset_key', '');
		$title     = trim((string) $jinput->getString('title', ''));

		if ($title === '') {
			$title = Text::_('FLEXI_PRESET_DEFAULT_TITLE_THEME');
			if ($title === 'FLEXI_PRESET_DEFAULT_TITLE_THEME') {
				$title = 'New theme';
			}
		}

		require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/protemplate/PresetLibrary.php';

		$validPreset = $presetKey === 'blank-theme';
		if (!$validPreset) {
			$preset = FlexicontentProTemplatePresetLibrary::getThemePreset($presetKey);
			if ($preset) {
				$validPreset = true;
			}
		}

		if (!$validPreset) {
			$app->enqueueMessage(Text::_('FLEXI_PRESET_INVALID'), 'error');
			$app->redirect('index.php?option=com_flexicontent&view=protheme&layout=choose');
			return;
		}

		/** @var FlexicontentModelProtheme $model */
		$model = $this->getModel('protheme', '', []);
		$id    = $model->createFromPreset($presetKey, $title);

		if ($id <= 0) {
			$app->enqueueMessage($model->getError() ?: Text::_('FLEXI_PRESET_CREATE_FAILED'), 'error');
			$app->redirect('index.php?option=com_flexicontent&view=protheme&layout=choose');
			return;
		}

		$app->enqueueMessage(Text::_('FLEXI_PRESET_CREATED_OK'), 'message');
		$app->redirect('index.php?option=com_flexicontent&view=protheme&layout=edit&id=' . $id);
	}

	// -------------------------------------------------------------------------
	// Delete — bypass Joomla's trash-first workflow (no trash UI for themes).
	// -------------------------------------------------------------------------

	public function remove()
	{
		$app = Factory::getApplication();
		Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

		$cids = (array) $app->input->get('cid', [], 'array');
		$cids = array_values(array_filter(array_map('intval', $cids)));

		if (empty($cids)) {
			$app->enqueueMessage(Text::_('JERROR_NO_ITEMS_SELECTED'), 'warning');
			$this->setRedirect('index.php?option=com_flexicontent&view=prothemes');
			return false;
		}

		try {
			$db  = Factory::getDbo();
			$ids = implode(',', $cids);
			$db->setQuery("DELETE FROM `#__flexicontent_pro_themes` WHERE id IN ($ids)")->execute();
			$app->enqueueMessage(Text::sprintf('JLIB_APPLICATION_N_ITEMS_DELETED', count($cids)));
		} catch (\Throwable $e) {
			$app->enqueueMessage($e->getMessage(), 'error');
		}

		$this->setRedirect('index.php?option=com_flexicontent&view=prothemes');
		return true;
	}

	public function getModel($name = 'protheme', $prefix = '', $config = [])
	{
		$name = strtolower($name);

		if ($name === 'prothemes') {
			return new FlexicontentModelProthemes($config);
		}

		return new FlexicontentModelProtheme($config);
	}
}
