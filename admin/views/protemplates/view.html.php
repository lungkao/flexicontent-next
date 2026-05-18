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

require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/protemplate/LicenseManager.php';

JLoader::register('FlexicontentViewBaseRecords', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/base/view_records.php');

/**
 * Pro Templates — List View.
 *
 * Wires filter dropdowns (scope / type / category / assignment / state),
 * stat strip data, and a "Create from preset" toolbar button that takes
 * the user to the chooser screen instead of dropping them into an empty
 * builder.
 */
#[AllowDynamicProperties]
class FlexicontentViewProtemplates extends FlexicontentViewBaseRecords
{
	public mixed $rows        = null;
	public mixed $pagination  = null;
	public mixed $state       = null;
	public mixed $lists       = null;
	public mixed $stats       = null;
	public mixed $typeOptions = null;
	public mixed $catOptions  = null;

	var $title_propname = 'title';
	var $state_propname = 'state';
	var $db_tbl         = 'flexicontent_pro_layouts';
	var $name_singular  = 'protemplate';

	public function display($tpl = null)
	{
		$app = Factory::getApplication();

		if (!FlexicontentProLicenseManager::isLicensed()) {
			$app->enqueueMessage(Text::_('FLEXI_PROTEMPLATE_LICENSE_REQUIRED'), 'error');
			$app->redirect('index.php?option=com_flexicontent');
			return;
		}

		/** @var FlexicontentModelProtemplates $model */
		$model = $this->getModel();

		$this->rows        = $model->getData();
		$this->pagination  = $model->getPagination();
		$this->state       = $model->getState();
		$this->stats       = $model->getStats();
		$this->typeOptions = $model->getTypeOptions();
		$this->catOptions  = $model->getCategoryOptions();

		$this->lists = [];

		$this->lists['state_filter'] = HTMLHelper::_(
			'select.genericlist',
			[
				HTMLHelper::_('select.option', '',  Text::_('JOPTION_SELECT_PUBLISHED')),
				HTMLHelper::_('select.option', '1', Text::_('JPUBLISHED')),
				HTMLHelper::_('select.option', '0', Text::_('JUNPUBLISHED')),
			],
			'filter_state',
			'class="form-select"',
			'value', 'text',
			$this->state->get('filter.state', '')
		);

		$this->lists['view_scope_filter'] = HTMLHelper::_(
			'select.genericlist',
			[
				HTMLHelper::_('select.option', '',         Text::_('FLEXI_PROTEMPLATE_FILTER_SCOPE_ANY')),
				HTMLHelper::_('select.option', 'item',     Text::_('FLEXI_PROTEMPLATE_VIEW_SCOPE_ITEM')),
				HTMLHelper::_('select.option', 'category', Text::_('FLEXI_PROTEMPLATE_VIEW_SCOPE_CATEGORY')),
			],
			'filter_view_scope',
			'class="form-select"',
			'value', 'text',
			$this->state->get('filter.view_scope', '')
		);

		$typeOpts = [HTMLHelper::_('select.option', 0, Text::_('FLEXI_PROTEMPLATE_FILTER_TYPE_ANY'))];
		foreach ($this->typeOptions as $t) {
			$typeOpts[] = HTMLHelper::_('select.option', (int) $t->id, $t->title);
		}
		$this->lists['type_filter'] = HTMLHelper::_(
			'select.genericlist',
			$typeOpts,
			'filter_type_id',
			'class="form-select"',
			'value', 'text',
			(int) $this->state->get('filter.type_id', 0)
		);

		$catOpts = [HTMLHelper::_('select.option', 0, Text::_('FLEXI_PROTEMPLATE_FILTER_CAT_ANY'))];
		foreach ($this->catOptions as $c) {
			$catOpts[] = HTMLHelper::_('select.option', (int) $c->id, $c->title);
		}
		$this->lists['cat_filter'] = HTMLHelper::_(
			'select.genericlist',
			$catOpts,
			'filter_catid',
			'class="form-select"',
			'value', 'text',
			(int) $this->state->get('filter.catid', 0)
		);

		$this->lists['assignment_filter'] = HTMLHelper::_(
			'select.genericlist',
			[
				HTMLHelper::_('select.option', '',         Text::_('FLEXI_PROTEMPLATE_FILTER_ASSIGN_ANY')),
				HTMLHelper::_('select.option', 'global',   Text::_('FLEXI_PROTEMPLATE_ASSIGN_GLOBAL')),
				HTMLHelper::_('select.option', 'type',     Text::_('FLEXI_PROTEMPLATE_ASSIGN_TYPE')),
				HTMLHelper::_('select.option', 'category', Text::_('FLEXI_PROTEMPLATE_ASSIGN_CATEGORY')),
				HTMLHelper::_('select.option', 'item',     Text::_('FLEXI_PROTEMPLATE_ASSIGN_ITEM')),
				HTMLHelper::_('select.option', 'menu',     Text::_('FLEXI_PROTEMPLATE_ASSIGN_MENU')),
			],
			'filter_assignment_type',
			'class="form-select"',
			'value', 'text',
			$this->state->get('filter.assignment_type', '')
		);

		// Toolbar
		ToolbarHelper::title(
			'<span class="fc-pro-badge">⭐</span> ' . Text::_('FLEXI_PROTEMPLATE_MANAGER'),
			'stack'
		);

		// Primary CTA lives on the stat-strip in the list template — keep
		// the toolbar focused on bulk operations against selected rows so
		// it doesn't render a half-styled link next to the row actions.

		ToolbarHelper::editList('protemplates.edit');
		ToolbarHelper::divider();
		ToolbarHelper::publishList('protemplates.publish');
		ToolbarHelper::unpublishList('protemplates.unpublish');
		ToolbarHelper::divider();
		ToolbarHelper::deleteList(Text::_('FLEXI_CONFIRM_DELETE'), 'protemplates.remove');

		parent::display($tpl);
	}
}
