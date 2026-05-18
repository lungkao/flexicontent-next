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
 * Pro Themes — single theme model (CRUD)
 */
#[AllowDynamicProperties]
class FlexicontentModelProtheme extends FCModelAdmin
{
	protected $name = 'protheme';

	var $records_dbtbl  = 'flexicontent_pro_themes';
	var $records_jtable = 'flexicontent_pro_themes';
	var $state_col      = 'state';
	var $name_col       = 'title';
	var $parent_col     = null;

	var $_id     = null;
	var $_record = null;

	// -------------------------------------------------------------------------

	public function getForm($data = [], $loadData = true)
	{
		$form = $this->loadForm(
			'com_flexicontent.protheme',
			JPATH_ADMINISTRATOR . '/components/com_flexicontent/forms/protheme.xml',
			['control' => 'jform', 'load_data' => $loadData]
		);

		return $form ?: false;
	}

	protected function loadFormData()
	{
		$app  = Factory::getApplication();
		$data = $app->getUserState('com_flexicontent.edit.protheme.data', []);

		if (empty($data)) {
			$data = $this->getItem();
		}

		return $data;
	}

	public function getItem($pk = null)
	{
		$pk    = $pk ?? (int) $this->getState($this->getName() . '.id');
		$table = $this->getTable();

		if ($pk > 0) {
			if (!$table->load($pk)) {
				$this->setError($table->getError());
				return false;
			}
		}

		$properties = $table->getProperties(1);
		return \Joomla\Utilities\ArrayHelper::toObject($properties, \stdClass::class);
	}

	/**
	 * Create a new theme record from a preset key.
	 *
	 * Pulls the preset definition from PresetLibrary, encodes its theme_data
	 * JSON and inserts a new row. Returns the new id, or 0 on failure.
	 *
	 * @param  string  $key    Preset key (eg 'modern-blue', 'dark-pro', or
	 *                         'blank-theme' for an empty starting point)
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

		if ($key === 'blank-theme') {
			$theme = [
				'colors' => [
					'accent'      => '#2563eb',
					'surface'     => '#ffffff',
					'surface_alt' => '#f8fafc',
					'text'        => '#0f172a',
					'text_muted'  => '#475569',
					'border'      => '#cbd5e1',
				],
				'typography' => [
					'family'         => 'system-ui, sans-serif',
					'family_heading' => 'system-ui, sans-serif',
					'scale'          => 1.0,
					'line_height'    => 1.6,
				],
				'radius' => 'md',
				'mode'   => 'light',
			];
		} else {
			$preset = FlexicontentProTemplatePresetLibrary::getThemePreset($key);
			if (!$preset) {
				$this->setError('Unknown theme preset: ' . $key);
				return 0;
			}
			$theme = $preset['theme_data'];
		}

		$json = json_encode($theme, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if ($json === false) {
			$this->setError('Failed to encode preset theme');
			return 0;
		}

		$user = Factory::getUser();
		$db   = Factory::getDbo();
		$now  = Factory::getDate()->toSql();

		$obj = (object) [
			'title'       => $title,
			'theme_data'  => $json,
			'state'       => 1,
			'ordering'    => 0,
			'created'     => $now,
			'created_by'  => (int) $user->id,
			'modified'    => $now,
			'modified_by' => (int) $user->id,
		];

		if (!$db->insertObject('#__flexicontent_pro_themes', $obj)) {
			$this->setError('Failed to insert theme preset record');
			return 0;
		}

		return (int) $db->insertid();
	}

	/**
	 * Save theme_data JSON directly (from the theme editor).
	 *
	 * @param  int    $id        Theme record id.
	 * @param  string $themeJson Raw JSON string.
	 * @return bool
	 */
	public function saveThemeData(int $id, string $themeJson): bool
	{
		if ($id <= 0) {
			$this->setError('Invalid theme id');
			return false;
		}

		json_decode($themeJson);
		if (json_last_error() !== JSON_ERROR_NONE) {
			$this->setError('Invalid JSON: ' . json_last_error_msg());
			return false;
		}

		$user = Factory::getUser();
		$db   = Factory::getDbo();
		$now  = Factory::getDate()->toSql();

		$query = $db->getQuery(true)
			->update('#__flexicontent_pro_themes')
			->set($db->quoteName('theme_data')  . ' = ' . $db->quote($themeJson))
			->set($db->quoteName('modified')    . ' = ' . $db->quote($now))
			->set($db->quoteName('modified_by') . ' = ' . (int) $user->id)
			->where($db->quoteName('id') . ' = ' . (int) $id);

		$db->setQuery($query)->execute();

		return true;
	}

	public function canEdit($record = null, $user = null)
	{
		if ($user) {
			throw new \Exception(__FUNCTION__ . '(): Error model does not support checking ACL of specific user', 500);
		}
		$user = \Joomla\CMS\Factory::getUser();
		return $user->authorise('flexicontent.managetemplates', 'com_flexicontent')
		    || $user->authorise('core.admin', 'com_flexicontent');
	}

	public function canEditState($record = null, $user = null)
	{
		return $this->canEdit($record);
	}

	public function canDelete($record = null)
	{
		return $this->canEdit();
	}

	public function getTable($type = 'flexicontent_pro_themes', $prefix = '', $config = [])
	{
		return Table::getInstance($type, '', $config);
	}
}
