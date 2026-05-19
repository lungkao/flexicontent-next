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
use Joomla\CMS\Table\Table;

require_once __DIR__ . '/base/base.php';

/**
 * Pro Templates — single layout model (CRUD + builder helpers)
 */
#[AllowDynamicProperties]
class FlexicontentModelProtemplate extends FCModelAdmin
{
	/** @var string Record name — used to find XML form file */
	protected $name = 'protemplate';

	/** @var string DB table */
	var $records_dbtbl  = 'flexicontent_pro_layouts';

	/** @var string JTable class name */
	var $records_jtable = 'flexicontent_pro_layouts';

	/** @var string State column */
	var $state_col = 'state';

	/** @var string Name/label column */
	var $name_col  = 'title';

	/** @var null No parent column */
	var $parent_col = null;

	/** @var int Current record id */
	var $_id = null;

	/** @var object|null Loaded record */
	var $_record = null;

	// -------------------------------------------------------------------------
	// Overrides
	// -------------------------------------------------------------------------

	/**
	 * Get form — load admin/forms/protemplate.xml
	 *
	 * @param array $data
	 * @param bool  $loadData
	 * @return \Joomla\CMS\Form\Form|false
	 */
	public function getForm($data = [], $loadData = true)
	{
		$form = $this->loadForm(
			'com_flexicontent.protemplate',
			JPATH_ADMINISTRATOR . '/components/com_flexicontent/forms/protemplate.xml',
			['control' => 'jform', 'load_data' => $loadData]
		);

		return $form ?: false;
	}

	/**
	 * Load data for the form (from session or DB).
	 *
	 * @return object
	 */
	protected function loadFormData()
	{
		$app  = Factory::getApplication();
		$data = $app->getUserState('com_flexicontent.edit.protemplate.data', []);

		if (empty($data)) {
			$data = $this->getItem();
		}

		return $data;
	}

	/**
	 * Get one layout record.
	 *
	 * @param int|null $pk
	 * @return \Joomla\CMS\Table\Table|false
	 */
	public function getItem($pk = null)
	{
		$pk = $pk ?? (int) $this->getState($this->getName() . '.id');
		$table = $this->getTable();

		if ($pk > 0) {
			if (!$table->load($pk)) {
				$this->setError($table->getError());
				return false;
			}
		}

		$properties = $table->getProperties(1);
		$record     = \Joomla\Utilities\ArrayHelper::toObject($properties, \stdClass::class);

		// Starter layout for newly-created records: ship 3 commonly-used
		// blocks (title, intro image, body) so editors land on a usable
		// canvas instead of an empty drop zone. Renderer resolves the
		// content tokens at render time — no hardcoded text/alt is baked
		// into the seed so each instance picks up its own title/alt/body.
		if ($pk <= 0 && empty($record->layout_data)) {
			$record->layout_data = self::starterLayoutJson();
		}

		return $record;
	}

	/**
	 * JSON seed for a brand-new layout. Three starter blocks rendered by
	 * the existing renderer (admin/helpers/protemplate/Renderer.php):
	 *   - title -> reads $source->title
	 *   - image -> reads $source->images->image_intro (+ image_intro_alt fallback)
	 *   - body  -> reads $source->introtext / fulltext
	 *
	 * No literal "Title" / "Image" / "Body" strings are baked in here.
	 * The renderer pulls real values from the item or category source so
	 * each rendered page is content-driven (WCAG 1.1.1 + 1.3.1).
	 *
	 * @return string  JSON-encoded layout_data
	 */
	public static function starterLayoutJson(): string
	{
		$layout = [
			'version'  => 2,
			'sections' => [
				[
					'id'     => 'sec-1',
					'blocks' => [
						['id' => 'blk-title', 'type' => 'title'],
						['id' => 'blk-image', 'type' => 'image'],
						['id' => 'blk-body',  'type' => 'body'],
					],
				],
			],
		];

		return json_encode($layout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}

	// -------------------------------------------------------------------------
	// Fields & Themes helpers (used by the builder view)
	// -------------------------------------------------------------------------

	/**
	 * Get FLEXIcontent fields available for a given type_id.
	 *
	 * @param  int  $type_id  0 = all fields
	 * @return array
	 */
	public function getFieldsForType(int $type_id = 0): array
	{
		$db    = Factory::getDbo();
		$query = $db->getQuery(true)
			->select('f.id, f.label, f.field_type AS type')
			->from('#__flexicontent_fields AS f');

		if ($type_id > 0) {
			$query->join('INNER', '#__flexicontent_fields_type_relations AS r ON r.field_id = f.id AND r.type_id = ' . (int) $type_id);
		}

		$query->where('f.published = 1')
			->order('f.label ASC');

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	/**
	 * Get all published Pro Themes.
	 *
	 * @return array
	 */
	public function getThemes(): array
	{
		$db = Factory::getDbo();
		$query = $db->getQuery(true)
			->select('id, title, theme_data')
			->from('#__flexicontent_pro_themes')
			->where('state = 1')
			->order('ordering ASC, title ASC');

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	// -------------------------------------------------------------------------
	// JSON save / autosave
	// -------------------------------------------------------------------------

	/**
	 * Create a new layout record from a preset key.
	 *
	 * Pulls the preset definition from PresetLibrary, encodes its layout JSON
	 * and inserts a new row in `#__flexicontent_pro_layouts`. Returns the
	 * new id, or 0 on failure (use getError() for details).
	 *
	 * @param  string  $key    Preset key (eg 'item-magazine', 'cat-grid', or
	 *                         'blank-item' / 'blank-category' for empty starts)
	 * @param  string  $title  Title for the new record
	 * @return int             New record id, or 0 on failure
	 */
	public function createFromPreset(string $key, string $title): int
	{
		$title = trim($title);
		if ($title === '') {
			$this->setError('Title required');
			return 0;
		}

		require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/protemplate/PresetLibrary.php';

		// Blank presets: empty layout, only scope matters
		if ($key === 'blank-item' || $key === 'blank-category') {
			$scope  = $key === 'blank-category' ? 'category' : 'item';
			$layout = ['version' => 2, 'settings' => ['theme' => 'clean', 'width' => 'default', 'spacing' => 'normal', 'themeId' => 0], 'sections' => []];
		} else {
			$preset = FlexicontentProTemplatePresetLibrary::getLayoutPreset($key);
			if (!$preset) {
				$this->setError('Unknown preset: ' . $key);
				return 0;
			}
			$scope  = $preset['scope'];
			$layout = $preset['layout'];
		}

		$json = json_encode($layout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if ($json === false) {
			$this->setError('Failed to encode preset layout');
			return 0;
		}

		$user = Factory::getUser();
		$db   = Factory::getDbo();
		$now  = Factory::getDate()->toSql();

		$obj = (object) [
			'title'            => $title,
			'type_id'          => 0,
			'catid'            => 0,
			'assignment_type'  => 'global',
			'assignment_value' => '',
			'view_scope'       => $scope,
			'layout_data'      => $json,
			'theme_id'         => 0,
			'state'            => 1,
			'ordering'         => 0,
			'note'             => '',
			'created'          => $now,
			'created_by'       => (int) $user->id,
			'modified'         => $now,
			'modified_by'      => (int) $user->id,
		];

		if (!$db->insertObject('#__flexicontent_pro_layouts', $obj)) {
			$this->setError('Failed to insert preset record');
			return 0;
		}

		return (int) $db->insertid();
	}

	/**
	 * Replace the layout JSON on an existing record with a preset's payload.
	 *
	 * Used by the "Change layout" toolbar button: editors who picked the
	 * wrong preset can swap layouts in place without recreating the row
	 * (preserves title, assignment, notes). Title and assignment columns
	 * are NOT touched here — only layout_data, view_scope, modified, and
	 * modified_by.
	 *
	 * @param  int     $id   Existing #__flexicontent_pro_layouts row id
	 * @param  string  $key  Preset key (or 'blank-item' / 'blank-category')
	 * @return bool          True on UPDATE success
	 */
	public function replaceFromPreset(int $id, string $key): bool
	{
		if ($id <= 0) {
			$this->setError('Invalid record id');
			return false;
		}

		require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/protemplate/PresetLibrary.php';

		if ($key === 'blank-item' || $key === 'blank-category') {
			$scope  = $key === 'blank-category' ? 'category' : 'item';
			$layout = ['version' => 2, 'settings' => ['theme' => 'clean', 'width' => 'default', 'spacing' => 'normal', 'themeId' => 0], 'sections' => []];
		} else {
			$preset = FlexicontentProTemplatePresetLibrary::getLayoutPreset($key);
			if (!$preset) {
				$this->setError('Unknown preset: ' . $key);
				return false;
			}
			$scope  = $preset['scope'];
			$layout = $preset['layout'];
		}

		$json = json_encode($layout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if ($json === false) {
			$this->setError('Failed to encode preset layout');
			return false;
		}

		$user = Factory::getUser();
		$db   = Factory::getDbo();
		$now  = Factory::getDate()->toSql();

		$query = $db->getQuery(true)
			->update($db->quoteName('#__flexicontent_pro_layouts'))
			->set($db->quoteName('layout_data')  . ' = ' . $db->quote($json))
			->set($db->quoteName('view_scope')   . ' = ' . $db->quote($scope))
			->set($db->quoteName('modified')     . ' = ' . $db->quote($now))
			->set($db->quoteName('modified_by')  . ' = ' . (int) $user->id)
			->where($db->quoteName('id') . ' = ' . (int) $id);

		try {
			$db->setQuery($query);
			$db->execute();
		} catch (\Throwable $e) {
			$this->setError('Failed to replace preset layout: ' . $e->getMessage());
			return false;
		}

		return true;
	}

	/**
	 * Auto-derive assignment columns before persisting.
	 *
	 * UI exposes only the two filter dimensions an editor actually thinks
	 * about (Category + FLEXIcontent Type). The router-priority column
	 * (assignment_type) is computed from them so editors don't have to
	 * pick a value that duplicates information already given.
	 *
	 *   catid  > 0  → 'category'   (highest router priority that matches)
	 *   type_id> 0  → 'type'
	 *   both = 0    → 'global'     (fallback)
	 *
	 * Category-scope layouts have no FLEXIcontent type by definition —
	 * force type_id to 0 even if the request smuggles in a value.
	 */
	public function save($data)
	{
		if (!is_array($data)) {
			$data = (array) $data;
		}

		$scope  = (isset($data['view_scope']) && $data['view_scope'] === 'category') ? 'category' : 'item';
		$catid  = (int) ($data['catid']   ?? 0);
		$typeId = (int) ($data['type_id'] ?? 0);

		if ($scope === 'category') {
			$data['type_id'] = 0;
			$typeId = 0;
		}
		$data['view_scope'] = $scope;

		if ($catid > 0) {
			$data['assignment_type'] = 'category';
		} elseif ($typeId > 0) {
			$data['assignment_type'] = 'type';
		} else {
			$data['assignment_type'] = 'global';
		}
		$data['assignment_value'] = '';

		return parent::save($data);
	}

	/**
	 * Persist layout_data JSON and update modified stamps.
	 *
	 * @param  int    $id          Layout record id.
	 * @param  string $layoutJson  Raw JSON string from the builder.
	 * @return bool
	 */
	public function saveLayoutData(int $id, string $layoutJson): bool
	{
		if ($id <= 0) {
			$this->setError('Invalid layout id');
			return false;
		}

		// Validate JSON
		json_decode($layoutJson);
		if (json_last_error() !== JSON_ERROR_NONE) {
			$this->setError('Invalid JSON: ' . json_last_error_msg());
			return false;
		}

		$user = Factory::getUser();
		$db   = Factory::getDbo();

		$now = Factory::getDate()->toSql();

		$query = $db->getQuery(true)
			->update('#__flexicontent_pro_layouts')
			->set($db->quoteName('layout_data') . ' = ' . $db->quote($layoutJson))
			->set($db->quoteName('modified')    . ' = ' . $db->quote($now))
			->set($db->quoteName('modified_by') . ' = ' . (int) $user->id)
			->where($db->quoteName('id') . ' = ' . (int) $id);

		$db->setQuery($query)->execute();

		return true;
	}

	/**
	 * Store an autosave revision without updating the main record.
	 *
	 * @param  int    $layoutId    Layout record id.
	 * @param  string $layoutJson  Raw JSON string.
	 * @return bool
	 */
	public function storeRevision(int $layoutId, string $layoutJson, string $type = 'autosave'): bool
	{
		if ($layoutId <= 0) return false;

		$user = Factory::getUser();
		$db   = Factory::getDbo();
		$now  = Factory::getDate()->toSql();

		$obj = (object) [
			'layout_id'     => $layoutId,
			'title'         => ($type === 'autosave') ? 'Autosave ' . $now : 'Manual ' . $now,
			'layout_data'   => $layoutJson,
			'revision_type' => in_array($type, ['manual', 'autosave'], true) ? $type : 'autosave',
			'created'       => $now,
			'created_by'    => (int) $user->id,
		];

		return $db->insertObject('#__flexicontent_pro_revisions', $obj);
	}

	// -------------------------------------------------------------------------
	// ACL
	// -------------------------------------------------------------------------

	/**
	 * Check if current user can create / edit a Pro Template layout.
	 * Reuses the same permission as regular Templates (flexicontent.managetemplates).
	 */
	public function canEdit($record = null, $user = null)
	{
		if ($user) {
			throw new \Exception(__FUNCTION__ . '(): Error model does not support checking ACL of specific user', 500);
		}
		$user = \Joomla\CMS\Factory::getUser();
		return $user->authorise('flexicontent.managetemplates', 'com_flexicontent')
		    || $user->authorise('core.admin', 'com_flexicontent');
	}

	/** @inheritdoc */
	public function canEditState($record = null, $user = null)
	{
		return $this->canEdit($record);
	}

	/** @inheritdoc */
	public function canDelete($record = null)
	{
		return $this->canEdit();
	}

	// -------------------------------------------------------------------------
	// JTable
	// -------------------------------------------------------------------------

	/**
	 * @param string $type
	 * @param string $prefix
	 * @param array  $config
	 * @return \Joomla\CMS\Table\Table
	 */
	public function getTable($type = 'flexicontent_pro_layouts', $prefix = '', $config = [])
	{
		return Table::getInstance($type, '', $config);
	}
}
