<?php
/**
 * @package         FLEXIcontent
 * @subpackage      Pro Templates — Resolver
 *
 * @author          FLEXIcontent Team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * Given a render context (item id, item's category, item's type, current
 * menu item), pick the highest-priority Pro Layout assigned to it.
 *
 * Priority order (first match wins):
 *   1. item     — assignment_type='item'     AND assignment_value=<itemId>
 *   2. menu     — assignment_type='menu'     AND assignment_value=<menuId>
 *   3. category — assignment_type='category' AND catid=<catId>
 *   4. type     — assignment_type='type'     AND type_id=<typeId>
 *   5. global   — assignment_type='global'
 *
 * Within the same priority tier, the lowest `ordering` value wins
 * (stable tie-break by id ASC). Only state=1 (published) layouts
 * are considered.
 */

defined('_JEXEC') or die('Restricted access');

class FlexicontentProTemplateResolver
{
	/**
	 * Per-request cache keyed by serialized context to avoid repeat queries
	 * when the same item is resolved multiple times in a single page render.
	 */
	protected static $cache = [];

	/**
	 * Debug payload from the most recent resolve() call. Hookups read this
	 * via getLastDebug() when ?proDebug=1 is on the URL so editors can see
	 * why a saved layout did or did not apply. Production callers ignore it.
	 */
	protected static $lastDebug = null;

	/**
	 * Resolve the Pro Layout (or null if no match) for a render context.
	 *
	 * @param array $context [
	 *     'item_id'  => int,      // 0 if not applicable (e.g. category view)
	 *     'cat_id'   => int,      // 0 if not applicable
	 *     'type_id'  => int,      // 0 if not applicable
	 *     'menu_id'  => int,      // 0 if no active menu
	 *     'view'     => string,   // 'item' | 'category' | ...  (informational)
	 * ]
	 *
	 * @return object|null  Loaded row from #__flexicontent_pro_layouts, or null
	 *                      if no published assignment matches.
	 */
	public static function resolve(array $context)
	{
		$context = array_merge([
			'item_id' => 0,
			'cat_id'  => 0,
			'type_id' => 0,
			'menu_id' => 0,
			'view'    => '',
		], $context);

		// view_scope enum allowlist — never bind a user-controllable string
		// into SQL without proof it matches a known value (defense in depth
		// even though contextFrom* helpers already constrain the value).
		$viewScope = in_array($context['view'], ['item', 'category'], true)
			? $context['view']
			: 'item';

		$cacheKey = $context['view'] . ':'
			. (int) $context['item_id'] . ':'
			. (int) $context['cat_id']  . ':'
			. (int) $context['type_id'] . ':'
			. (int) $context['menu_id'];

		if (array_key_exists($cacheKey, self::$cache)) {
			return self::$cache[$cacheKey];
		}

		$db    = \Joomla\CMS\Factory::getDbo();
		$qn    = static function (string $col) use ($db) {
			return $db->quoteName($col);
		};

		// Pull all candidate layouts for this context in one query, then
		// score them in PHP. One DB hit per render context (cached after).
		// view_scope must match the rendering view — an item-scope layout
		// must not bleed into the category page and vice versa.
		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__flexicontent_pro_layouts'))
			->where($qn('state') . ' = 1')
			->where($qn('view_scope') . ' = ' . $db->quote($viewScope))
			->where(
				'(' .
					'(' . $qn('assignment_type') . ' = ' . $db->quote('item')     . ' AND ' . $qn('assignment_value') . ' = ' . $db->quote((string) $context['item_id']) . ')'
				. ' OR (' . $qn('assignment_type') . ' = ' . $db->quote('menu')     . ' AND ' . $qn('assignment_value') . ' = ' . $db->quote((string) $context['menu_id']) . ')'
				. ' OR (' . $qn('assignment_type') . ' = ' . $db->quote('category') . ' AND ' . $qn('catid')            . ' = ' . (int) $context['cat_id']  . ')'
				. ' OR (' . $qn('assignment_type') . ' = ' . $db->quote('type')     . ' AND ' . $qn('type_id')          . ' = ' . (int) $context['type_id'] . ')'
				. ' OR  ' . $qn('assignment_type') . ' = ' . $db->quote('global')
				. ')'
			)
			->order($qn('ordering') . ' ASC, ' . $qn('id') . ' ASC');

		$sqlText    = (string) $query;
		$candidates = $db->setQuery($query)->loadObjectList() ?: [];

		// Priority weights — lower = wins. Tied candidates fall back to
		// ordering (already in result order from SQL).
		$priority = [
			'item'     => 1,
			'menu'     => 2,
			'category' => 3,
			'type'     => 4,
			'global'   => 5,
		];

		$best     = null;
		$bestRank = PHP_INT_MAX;
		$skipped  = [];

		foreach ($candidates as $row) {
			// Defensive: confirm the assignment is actually applicable.
			// (The SQL prefilter is broad — exclude rows that match by
			// SQL but not by intent: e.g. category row pointing at a
			// different catid that happens to share OR-matching.)
			if (!self::isApplicable($row, $context)) {
				$skipped[] = ['id' => (int) $row->id, 'reason' => 'isApplicable=false'];
				continue;
			}

			$rank = $priority[$row->assignment_type] ?? PHP_INT_MAX;
			if ($rank < $bestRank) {
				$best     = $row;
				$bestRank = $rank;
			}
		}

		// Decode JSON once and attach for caller convenience.
		if ($best) {
			$best->layout_decoded = json_decode($best->layout_data ?? '{}', true);
			if (!is_array($best->layout_decoded)) {
				$best->layout_decoded = ['version' => 2, 'sections' => []];
			}
		}

		// Capture a debug snapshot for the ?proDebug=1 admin diagnostic.
		// Trimmed to keep memory footprint modest on pages with many rows.
		self::$lastDebug = [
			'context'    => $context,
			'view_scope' => $viewScope,
			'sql'        => $sqlText,
			'candidates' => array_map(static function ($r) {
				return [
					'id'               => (int) $r->id,
					'title'            => (string) ($r->title ?? ''),
					'assignment_type'  => (string) ($r->assignment_type ?? ''),
					'assignment_value' => (string) ($r->assignment_value ?? ''),
					'catid'            => (int) ($r->catid ?? 0),
					'type_id'          => (int) ($r->type_id ?? 0),
					'view_scope'       => (string) ($r->view_scope ?? ''),
					'state'            => (int) ($r->state ?? 0),
					'ordering'         => (int) ($r->ordering ?? 0),
					'layout_bytes'     => strlen((string) ($r->layout_data ?? '')),
				];
			}, $candidates),
			'skipped'    => $skipped,
			'picked'     => $best ? ['id' => (int) $best->id, 'assignment_type' => $best->assignment_type, 'rank' => $bestRank] : null,
			'reason'     => $best
				? 'matched'
				: (empty($candidates) ? 'no rows matched SQL (check state=1, view_scope, assignment columns)' : 'all candidates failed isApplicable check'),
		];

		self::$cache[$cacheKey] = $best;

		return $best;
	}

	/**
	 * Debug accessor — returns context, SQL, candidates, picked row, and a
	 * human-readable reason from the last resolve() call. Hookups call this
	 * when ?proDebug=1 is on the URL to emit a diagnostic comment.
	 *
	 * @return array|null  null if resolve() has not run yet
	 */
	public static function getLastDebug(): ?array
	{
		return self::$lastDebug;
	}

	/**
	 * Strict applicability check (belt and braces over the SQL prefilter).
	 */
	protected static function isApplicable($row, array $context): bool
	{
		switch ($row->assignment_type) {
			case 'item':
				return (string) $row->assignment_value === (string) $context['item_id']
					&& (int) $context['item_id'] > 0;

			case 'menu':
				return (string) $row->assignment_value === (string) $context['menu_id']
					&& (int) $context['menu_id'] > 0;

			case 'category':
				return (int) $row->catid === (int) $context['cat_id']
					&& (int) $context['cat_id'] > 0;

			case 'type':
				return (int) $row->type_id === (int) $context['type_id']
					&& (int) $context['type_id'] > 0;

			case 'global':
				return true;
		}

		return false;
	}

	/**
	 * Build a render context from a FLEXIcontent item object + Joomla app.
	 * Convenience helper for site/views to keep call sites tiny.
	 */
	public static function contextFromItem($item, string $view = 'item'): array
	{
		$app    = \Joomla\CMS\Factory::getApplication();
		$active = method_exists($app, 'getMenu') && $app->getMenu()
			? $app->getMenu()->getActive()
			: null;

		return [
			'item_id' => (int) ($item->id ?? 0),
			'cat_id'  => (int) ($item->catid ?? $item->parentid ?? 0),
			'type_id' => (int) ($item->type_id ?? 0),
			'menu_id' => $active ? (int) $active->id : 0,
			'view'    => $view,
		];
	}

	/**
	 * Build a render context for a FLEXIcontent category view.
	 * Category-level layouts have no item_id, so the priority chain
	 * collapses to: menu > category > global. Type is not used at
	 * category scope.
	 *
	 * @param  object|int  $category  Category object (must have ->id) or raw id
	 * @param  string      $view      View name (informational, default 'category')
	 *
	 * @return array                  Context array suitable for resolve()
	 */
	public static function contextFromCategory($category, string $view = 'category'): array
	{
		$app    = \Joomla\CMS\Factory::getApplication();
		$active = method_exists($app, 'getMenu') && $app->getMenu()
			? $app->getMenu()->getActive()
			: null;

		$catId = is_object($category)
			? (int) ($category->id ?? 0)
			: (int) $category;

		return [
			'item_id' => 0,
			'cat_id'  => $catId,
			'type_id' => 0,
			'menu_id' => $active ? (int) $active->id : 0,
			'view'    => $view,
		];
	}

	/**
	 * Test-only: clear in-process cache. Production code does not need this.
	 */
	public static function clearCache(): void
	{
		self::$cache     = [];
		self::$lastDebug = null;
	}
}
