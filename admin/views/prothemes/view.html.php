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
use Joomla\CMS\HTML\HTMLHelper;

JLoader::register('FlexicontentViewBaseRecords', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/base/view_records.php');

/**
 * Pro Themes — List View
 */
#[AllowDynamicProperties]
class FlexicontentViewProthemes extends FlexicontentViewBaseRecords
{
	public mixed $rows       = null;
	public mixed $pagination = null;
	public mixed $state      = null;
	public mixed $lists      = null;

	var $title_propname = 'title';
	var $state_propname = 'state';
	var $db_tbl         = 'flexicontent_pro_themes';
	var $name_singular  = 'protheme';

	public function display($tpl = null)
	{
		$app = Factory::getApplication();

		/** @var FlexicontentModelProthemes $model */
		$model = $this->getModel();

		$this->rows       = $model->getData();
		$this->pagination = $model->getPagination();
		$this->state      = $model->getState();

		$this->lists['state_filter'] = HTMLHelper::_(
			'select.genericlist',
			[
				HTMLHelper::_('select.option', '', Text::_('JOPTION_SELECT_PUBLISHED')),
				HTMLHelper::_('select.option', '1', Text::_('JPUBLISHED')),
				HTMLHelper::_('select.option', '0', Text::_('JUNPUBLISHED')),
			],
			'filter_state',
			'class="form-select"',
			'value', 'text',
			$this->state->get('filter.state', '')
		);

		ToolbarHelper::title(
			'<span class="fc-pro-badge">⭐</span> ' . Text::_('FLEXI_PROTHEME_MANAGER'),
			'paintbrush'
		);
		// Primary CTA lives on the intro card in the list template — keep
		// the toolbar minimal so it doesn't render a half-styled link.
		ToolbarHelper::editList('prothemes.edit');
		ToolbarHelper::divider();
		ToolbarHelper::publishList('prothemes.publish');
		ToolbarHelper::unpublishList('prothemes.unpublish');
		ToolbarHelper::divider();
		ToolbarHelper::deleteList(Text::_('FLEXI_CONFIRM_DELETE'), 'prothemes.remove');

		parent::display($tpl);
	}
}
