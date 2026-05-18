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

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\MVC\View\HtmlView;

require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/protemplate/LicenseManager.php';
require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/models/protemplate.php';

/**
 * Pro Template View — handles two layouts:
 *   - default (builder editor)
 *   - choose  (scope picker + preset gallery for new records)
 */
#[AllowDynamicProperties]
class FlexicontentViewProtemplate extends HtmlView
{
	/* Edit mode props */
	public mixed $item   = null;
	public mixed $fields = null;
	public mixed $themes = null;
	public mixed $form   = null;

	/* Choose mode props */
	public mixed $scope         = null;   // 'item' | 'category' | '' (step 1)
	public mixed $presets       = null;   // array of preset definitions for $scope
	public mixed $groups        = null;   // filter group registry
	public mixed $itemCount     = null;   // count of item presets (step 1 card)
	public mixed $categoryCount = null;   // count of category presets (step 1 card)

	public function display($tpl = null)
	{
		$app    = Factory::getApplication();
		$jinput = $app->input;
		$layout = (string) $jinput->getCmd('layout', '');

		if (!FlexicontentProLicenseManager::isLicensed()) {
			$app->enqueueMessage(Text::_('FLEXI_PROTEMPLATE_LICENSE_REQUIRED'), 'error');
			$app->redirect('index.php?option=com_flexicontent');
			return;
		}

		if ($layout === 'choose') {
			$this->displayChooser($app, $jinput, $tpl);
			return;
		}

		$this->displayEditor($app, $jinput, $tpl);
	}

	/**
	 * Builder/editor view — existing behavior preserved verbatim.
	 */
	protected function displayEditor($app, $jinput, $tpl): void
	{
		$id = (int) $jinput->getInt('id', 0);

		/** @var FlexicontentModelProtemplate $model */
		$model = new FlexicontentModelProtemplate();
		$model->setState('protemplate.id', $id);

		$this->item   = $model->getItem($id);
		$this->form   = $model->getForm([], true);
		$this->themes = $model->getThemes();

		$type_id      = (int) ($this->item->type_id ?? 0);
		$this->fields = $model->getFieldsForType($type_id);

		if ($this->item && $this->form) {
			$this->form->bind((array) $this->item);
		}

		$isNew = ($id === 0);
		ToolbarHelper::title(
			'<span class="fc-pro-badge">⭐</span> ' .
			Text::_($isNew ? 'FLEXI_PROTEMPLATE_NEW' : 'FLEXI_PROTEMPLATE_EDIT'),
			'stack'
		);
		ToolbarHelper::apply('protemplates.apply');
		ToolbarHelper::save('protemplates.save');
		ToolbarHelper::cancel('protemplates.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');

		parent::display($tpl);
	}

	/**
	 * Chooser view — two-step preset gallery.
	 *   Step 1 (no scope param): scope picker (item / category cards)
	 *   Step 2 (scope set):      preset card grid + blank starter
	 */
	protected function displayChooser($app, $jinput, $tpl): void
	{
		require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/protemplate/PresetLibrary.php';

		$scope = (string) $jinput->getCmd('scope', '');
		$scope = in_array($scope, ['item', 'category'], true) ? $scope : '';

		$this->scope         = $scope;
		$this->groups        = FlexicontentProTemplatePresetLibrary::getLayoutGroups();
		$this->itemCount     = count(FlexicontentProTemplatePresetLibrary::getLayoutPresets('item'));
		$this->categoryCount = count(FlexicontentProTemplatePresetLibrary::getLayoutPresets('category'));

		if ($scope !== '') {
			$this->presets = FlexicontentProTemplatePresetLibrary::getLayoutPresets($scope);
		}

		ToolbarHelper::title(
			'<span class="fc-pro-badge">⭐</span> ' .
			Text::_($scope === ''
				? 'FLEXI_PROTEMPLATE_CHOOSE_SCOPE_TITLE'
				: 'FLEXI_PROTEMPLATE_CHOOSE_PRESET_TITLE'),
			'stack'
		);
		ToolbarHelper::cancel('protemplates.cancel', 'JTOOLBAR_CLOSE');

		$this->setLayout('choose');
		parent::display($tpl);
	}
}
