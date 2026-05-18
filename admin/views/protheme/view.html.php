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

require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/models/protheme.php';

/**
 * Pro Theme View — handles two layouts:
 *   - default (theme editor)
 *   - choose  (preset gallery)
 */
#[AllowDynamicProperties]
class FlexicontentViewProtheme extends HtmlView
{
	/* Edit mode props */
	public mixed $item = null;
	public mixed $form = null;

	/* Choose mode props */
	public mixed $presets = null;
	public mixed $groups  = null;

	public function display($tpl = null)
	{
		$app    = Factory::getApplication();
		$jinput = $app->input;
		$layout = (string) $jinput->getCmd('layout', '');

		if ($layout === 'choose') {
			$this->displayChooser($app, $jinput, $tpl);
			return;
		}

		$this->displayEditor($app, $jinput, $tpl);
	}

	protected function displayEditor($app, $jinput, $tpl): void
	{
		$id = (int) $jinput->getInt('id', 0);

		/** @var FlexicontentModelProtheme $model */
		$model = new FlexicontentModelProtheme();
		$model->setState('protheme.id', $id);

		$this->item = $model->getItem($id);
		$this->form = $model->getForm([], true);

		if ($this->item && $this->form) {
			$this->form->bind((array) $this->item);
		}

		$isNew = ($id === 0);
		ToolbarHelper::title(
			'<span class="fc-pro-badge">⭐</span> ' .
			Text::_($isNew ? 'FLEXI_PROTHEME_NEW' : 'FLEXI_PROTHEME_EDIT'),
			'paintbrush'
		);
		ToolbarHelper::apply('prothemes.apply');
		ToolbarHelper::save('prothemes.save');
		ToolbarHelper::cancel('prothemes.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');

		parent::display($tpl);
	}

	protected function displayChooser($app, $jinput, $tpl): void
	{
		require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/protemplate/PresetLibrary.php';

		$this->presets = FlexicontentProTemplatePresetLibrary::getThemePresets();
		$this->groups  = FlexicontentProTemplatePresetLibrary::getThemeGroups();

		ToolbarHelper::title(
			'<span class="fc-pro-badge">⭐</span> ' . Text::_('FLEXI_PROTHEME_CHOOSE_PRESET_TITLE'),
			'paintbrush'
		);
		ToolbarHelper::cancel('prothemes.cancel', 'JTOOLBAR_CLOSE');

		$this->setLayout('choose');
		parent::display($tpl);
	}
}
