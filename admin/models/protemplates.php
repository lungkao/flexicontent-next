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

require_once __DIR__ . '/base/baselist.php';

/**
 * Pro Templates — list model.
 *
 * Filter state is read from URL params via getUserStateFromRequest so the
 * user's selection survives across pagination clicks. State keys follow the
 * dot-namespace ('filter.X') convention to match the template render code.
 */
#[AllowDynamicProperties]
class FlexicontentModelProtemplates extends FCModelAdminList
{
	var $records_dbtbl  = 'flexicontent_pro_layouts';
	var $records_jtable = 'flexicontent_pro_layouts';
	var $state_col      = 'state';
	var $name_col       = 'title';
	var $parent_col     = null;
	var $created_by_col = null;

	protected $listViaAccess = false;
	protected $copyRelations = false;

	var $search_cols = [
		'FLEXI_TITLE' => 'title',
		'FLEXI_NOTE'  => 'note',
	];

	var $default_order     = 'a.ordering';
	var $default_order_dir = 'ASC';

	var $hard_filters = [];

	/**
	 * Pull filter selections from request and stash them in state so the
	 * list query and template render both see the same values.
	 *
	 * Valid keys (all optional):
	 *   filter.search          — free-text against title / note
	 *   filter.state           — 1 / 0 / '' (any)
	 *   filter.view_scope      — 'item' / 'category' / '' (any)
	 *   filter.type_id         — FLEXIcontent type id (int)
	 *   filter.catid           — Joomla category id (int)
	 *   filter.assignment_type — global / type / category / item / menu / ''
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		$app = Factory::getApplication();
		$ctx = 'com_flexicontent.protemplates.';

		$search         = $app->getUserStateFromRequest($ctx . 'filter.search',          'filter_search',          '', 'string');
		$state          = $app->getUserStateFromRequest($ctx . 'filter.state',           'filter_state',           '', 'cmd');
		$viewScope      = $app->getUserStateFromRequest($ctx . 'filter.view_scope',      'filter_view_scope',      '', 'cmd');
		$typeId         = $app->getUserStateFromRequest($ctx . 'filter.type_id',         'filter_type_id',         0,  'int');
		$catId          = $app->getUserStateFromRequest($ctx . 'filter.catid',           'filter_catid',           0,  'int');
		$assignmentType = $app->getUserStateFromRequest($ctx . 'filter.assignment_type', 'filter_assignment_type', '', 'cmd');

		// Clamp enum values
		if (!in_array($viewScope, ['', 'item', 'category'], true)) {
			$viewScope = '';
		}
		if (!in_array($assignmentType, ['', 'global', 'type', 'category', 'item', 'menu'], true)) {
			$assignmentType = '';
		}

		$this->setState('filter.search',          $search);
		$this->setState('filter.state',           $state);
		$this->setState('filter.view_scope',      $viewScope);
		$this->setState('filter.type_id',         (int) $typeId);
		$this->setState('filter.catid',           (int) $catId);
		$this->setState('filter.assignment_type', $assignmentType);

		parent::populateState($ordering, $direction);
	}

	/**
	 * Build the list query.
	 *
	 * @return \Joomla\Database\DatabaseQuery
	 */
	protected function getListQuery()
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true);

		$query
			->select('a.*')
			->select('t.name AS type_name')
			->select('c.title AS cat_name')
			->from('#__flexicontent_pro_layouts AS a')
			->join('LEFT', '#__flexicontent_types AS t ON t.id = a.type_id')
			->join('LEFT', '#__categories AS c ON c.id = a.catid');

		$state = $this->getState('filter.state', '');
		if ($state !== '' && $state !== null) {
			$query->where('a.state = ' . (int) $state);
		}

		$viewScope = (string) $this->getState('filter.view_scope', '');
		if ($viewScope === 'item' || $viewScope === 'category') {
			$query->where('a.view_scope = ' . $db->quote($viewScope));
		}

		$type_id = (int) $this->getState('filter.type_id', 0);
		if ($type_id > 0) {
			$query->where('a.type_id = ' . $type_id);
		}

		$catId = (int) $this->getState('filter.catid', 0);
		if ($catId > 0) {
			$query->where('a.catid = ' . $catId);
		}

		$assignment = (string) $this->getState('filter.assignment_type', '');
		if ($assignment !== '' && in_array($assignment, ['global', 'type', 'category', 'item', 'menu'], true)) {
			$query->where('a.assignment_type = ' . $db->quote($assignment));
		}

		$search = (string) $this->getState('filter.search', '');
		if ($search !== '') {
			$like = $db->quote('%' . $db->escape($search, true) . '%', false);
			$query->where('(a.title LIKE ' . $like . ' OR a.note LIKE ' . $like . ')');
		}

		$orderCol = $this->getState('list.ordering',  $this->default_order);
		$orderDir = $this->getState('list.direction', $this->default_order_dir);

		$allowedCols = ['a.ordering', 'a.title', 'a.state', 'a.type_id', 'a.catid', 'a.modified', 'a.view_scope'];
		$orderCol = in_array($orderCol, $allowedCols, true) ? $orderCol : $this->default_order;
		$orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

		$query->order($orderCol . ' ' . $orderDir);

		return $query;
	}

	/**
	 * Stat strip data for the list page header.
	 *
	 * @return array{total:int,item_scope:int,category_scope:int,published:int,draft:int}
	 */
	public function getStats(): array
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select([
				'COUNT(*) AS total',
				"SUM(CASE WHEN view_scope = 'item' THEN 1 ELSE 0 END) AS item_scope",
				"SUM(CASE WHEN view_scope = 'category' THEN 1 ELSE 0 END) AS category_scope",
				'SUM(CASE WHEN state = 1 THEN 1 ELSE 0 END) AS published',
				'SUM(CASE WHEN state = 0 THEN 1 ELSE 0 END) AS draft',
			])
			->from('#__flexicontent_pro_layouts');

		$row = $db->setQuery($query)->loadAssoc() ?: [];

		return [
			'total'          => (int) ($row['total']          ?? 0),
			'item_scope'     => (int) ($row['item_scope']     ?? 0),
			'category_scope' => (int) ($row['category_scope'] ?? 0),
			'published'      => (int) ($row['published']      ?? 0),
			'draft'          => (int) ($row['draft']          ?? 0),
		];
	}

	/**
	 * FLEXIcontent types for the filter dropdown.
	 *
	 * @return array<int, object>
	 */
	public function getTypeOptions(): array
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select('id, name AS title')
			->from('#__flexicontent_types')
			->where('published = 1')
			->order('name ASC');

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	/**
	 * Joomla categories that already have at least one Pro layout assigned
	 * — keeps the filter dropdown short.
	 *
	 * @return array<int, object>
	 */
	public function getCategoryOptions(): array
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select('DISTINCT c.id, c.title')
			->from('#__flexicontent_pro_layouts AS a')
			->join('INNER', '#__categories AS c ON c.id = a.catid')
			->where('a.catid > 0')
			->order('c.title ASC');

		return $db->setQuery($query)->loadObjectList() ?: [];
	}
}
