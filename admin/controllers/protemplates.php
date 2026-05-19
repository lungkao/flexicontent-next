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

require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'protemplate.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'protemplates.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'helpers' . DS . 'protemplate' . DS . 'LicenseManager.php';

/**
 * Pro Templates Controller — handles list, CRUD, AJAX save / autosave.
 */
#[AllowDynamicProperties]
class FlexicontentControllerProtemplates extends FlexicontentControllerBaseAdmin
{
	var $records_dbtbl  = 'flexicontent_pro_layouts';
	var $records_jtable = 'flexicontent_pro_layouts';

	var $record_name    = 'protemplate';
	var $record_name_pl = 'protemplates';

	var $_NAME = 'PROTEMPLATE';

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
	// AJAX: Save full layout JSON (called on Save button from builder)
	// -------------------------------------------------------------------------

	/**
	 * task=protemplates.saveJson
	 * POST: id, layout_json, {token}=1
	 */
	public function saveJson(): void
	{
		/** @var \Joomla\CMS\Application\AdministratorApplication $app */
		$app    = Factory::getApplication();
		$jinput = $app->input;

		// CSRF check
		Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

		$id   = (int) $jinput->getInt('id', 0);
		$json = $jinput->getRaw('layout_json', '');

		/** @var FlexicontentModelProtemplate $model */
		$model = $this->getModel('protemplate', '', []);

		$success = $model->saveLayoutData($id, $json);

		if ($success) {
			$model->storeRevision($id, $json, 'manual');
		}

		echo new JsonResponse(
			$success ? ['id' => $id] : null,
			$success ? '' : $model->getError(),
			!$success
		);

		$app->close();
	}

	// -------------------------------------------------------------------------
	// AJAX: Autosave draft (called automatically every ~1.2 s after changes)
	// -------------------------------------------------------------------------

	/**
	 * task=protemplates.autosaveJson
	 * POST: id, layout_json, {token}=1
	 */
	public function autosaveJson(): void
	{
		$app    = Factory::getApplication();
		$jinput = $app->input;

		Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

		$id   = (int) $jinput->getInt('id', 0);
		$json = $jinput->getRaw('layout_json', '');

		/** @var FlexicontentModelProtemplate $model */
		$model = $this->getModel('protemplate', '', []);

		$success = $model->storeRevision($id, $json, 'autosave');

		echo new JsonResponse(
			$success ? ['id' => $id] : null,
			$success ? '' : 'Autosave failed',
			!$success
		);

		$app->close();
	}

	// -------------------------------------------------------------------------
	// Create layout from preset (chooser screen submit handler)
	// -------------------------------------------------------------------------

	/**
	 * task=protemplates.createFromPreset
	 *
	 * POST:
	 *   preset_key   — key from PresetLibrary OR 'blank-item' / 'blank-category'
	 *   scope        — 'item' | 'category' (used to disambiguate 'blank' keys
	 *                  and to validate the picked preset belongs to the
	 *                  scope the user selected in step 1)
	 *   title        — user-entered title for the new record
	 *   {token}=1    — CSRF
	 *
	 * On success: redirect to the builder for the new record.
	 * On failure: redirect back to the chooser with an error message.
	 */
	public function createFromPreset(): void
	{
		$app    = Factory::getApplication();
		$jinput = $app->input;

		Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

		$presetKey = (string) $jinput->getCmd('preset_key', '');
		$scope     = (string) $jinput->getCmd('scope', 'item');
		$title     = trim((string) $jinput->getString('title', ''));
		$replaceId = (int)    $jinput->getInt('replace_id', 0);

		$scope = $scope === 'category' ? 'category' : 'item';

		if ($title === '') {
			$title = $scope === 'category'
				? Text::_('FLEXI_PRESET_DEFAULT_TITLE_CATEGORY')
				: Text::_('FLEXI_PRESET_DEFAULT_TITLE_ITEM');

			if (strpos($title, 'FLEXI_PRESET_DEFAULT_TITLE_') === 0) {
				$title = $scope === 'category' ? 'New category layout' : 'New item layout';
			}
		}

		require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/protemplate/PresetLibrary.php';

		$blankKey    = 'blank-' . $scope;
		$validPreset = $presetKey === $blankKey;

		if (!$validPreset) {
			$preset = FlexicontentProTemplatePresetLibrary::getLayoutPreset($presetKey);
			if ($preset && $preset['scope'] === $scope) {
				$validPreset = true;
			}
		}

		if (!$validPreset) {
			$app->enqueueMessage(Text::_('FLEXI_PRESET_INVALID'), 'error');
			$app->redirect('index.php?option=com_flexicontent&view=protemplate&layout=choose&scope=' . urlencode($scope));
			return;
		}

		/** @var FlexicontentModelProtemplate $model */
		$model = $this->getModel('protemplate', '', []);

		// "Change layout" flow: replace_id > 0 means the editor came from
		// the toolbar "Change layout" button on an existing record. Swap
		// the layout JSON in place so title/assignment/notes are preserved.
		if ($replaceId > 0) {
			$ok = $model->replaceFromPreset($replaceId, $presetKey);
			if (!$ok) {
				$app->enqueueMessage($model->getError() ?: Text::_('FLEXI_PRESET_CREATE_FAILED'), 'error');
				$app->redirect('index.php?option=com_flexicontent&view=protemplate&layout=choose&scope=' . urlencode($scope) . '&replace_id=' . $replaceId);
				return;
			}
			$app->enqueueMessage(Text::_('FLEXI_PRESET_REPLACED_OK'), 'message');
			$app->redirect('index.php?option=com_flexicontent&view=protemplate&layout=edit&id=' . $replaceId);
			return;
		}

		$id = $model->createFromPreset($presetKey, $title);

		if ($id <= 0) {
			$app->enqueueMessage($model->getError() ?: Text::_('FLEXI_PRESET_CREATE_FAILED'), 'error');
			$app->redirect('index.php?option=com_flexicontent&view=protemplate&layout=choose&scope=' . urlencode($scope));
			return;
		}

		$app->enqueueMessage(Text::_('FLEXI_PRESET_CREATED_OK'), 'message');
		$app->redirect('index.php?option=com_flexicontent&view=protemplate&layout=edit&id=' . $id);
	}

	// -------------------------------------------------------------------------
	// Delete — bypass Joomla's trash-first workflow.
	// Pro Layouts have no trash UI, so the standard "must be -2 (trashed)
	// before delete" rule turns into a deadlock. Delete in one step from
	// the list page (Joomla's deleteList already shows a confirm dialog).
	// -------------------------------------------------------------------------

	public function remove()
	{
		$app = Factory::getApplication();
		Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

		$cids = (array) $app->input->get('cid', [], 'array');
		$cids = array_values(array_filter(array_map('intval', $cids)));

		if (empty($cids)) {
			$app->enqueueMessage(Text::_('JERROR_NO_ITEMS_SELECTED'), 'warning');
			$this->setRedirect('index.php?option=com_flexicontent&view=protemplates');
			return false;
		}

		try {
			$db  = Factory::getDbo();
			$ids = implode(',', $cids);
			$db->setQuery("DELETE FROM `#__flexicontent_pro_layouts` WHERE id IN ($ids)")->execute();
			$db->setQuery("DELETE FROM `#__flexicontent_pro_revisions` WHERE layout_id IN ($ids)")->execute();
			$app->enqueueMessage(Text::sprintf('JLIB_APPLICATION_N_ITEMS_DELETED', count($cids)));
		} catch (\Throwable $e) {
			$app->enqueueMessage($e->getMessage(), 'error');
		}

		$this->setRedirect('index.php?option=com_flexicontent&view=protemplates');
		return true;
	}

	// -------------------------------------------------------------------------
	// Standard helpers
	// -------------------------------------------------------------------------

	public function getModel($name = 'protemplate', $prefix = '', $config = [])
	{
		$name = strtolower($name);

		if ($name === 'protemplates') {
			return new FlexicontentModelProtemplates($config);
		}

		return new FlexicontentModelProtemplate($config);
	}
}
