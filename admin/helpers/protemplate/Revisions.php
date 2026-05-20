<?php
/**
 * @package         FLEXIcontent
 * @subpackage      Pro Templates — Revisions store
 * @copyright       Copyright (c) FLEXIcontent
 * @license         GNU/GPL v3
 *
 * Read/write helper for the `#__flexicontent_pro_layout_revisions`
 * table. Backs the Layout editor autosave loop and the "restore
 * earlier version" panel.
 *
 * Construction: pass a Joomla\Database\DatabaseDriver to the
 * constructor for testability; production callers should use the
 * lazy resolver which calls Joomla\CMS\Factory::getDbo(). Pure
 * helpers (validation, JSON encoding, parent-type normalization)
 * are static and require no DB.
 *
 * @since  6.1.0-beta.12
 */

defined('_JEXEC') || die;

class FlexicontentProTemplateRevisions
{
	public const TYPE_TEMPLATE = 'template';
	public const TYPE_THEME    = 'theme';

	/** Maximum revisions kept per (parent_type, parent_id) tuple. */
	public const DEFAULT_KEEP = 20;

	/** @var object|null Joomla DatabaseDriver instance (or compatible). */
	protected $db;

	public function __construct($db = null)
	{
		$this->db = $db;
	}

	/* -----------------------------------------------------------------
	 * Pure helpers — no DB, fully testable.
	 * --------------------------------------------------------------- */

	public static function normalizeParentType(string $type): string
	{
		$type = strtolower(trim($type));
		return $type === self::TYPE_THEME ? self::TYPE_THEME : self::TYPE_TEMPLATE;
	}

	/**
	 * Validate + JSON-encode the layout payload. Throws on bad shape so
	 * callers cannot persist garbage that would later crash the editor.
	 */
	public static function encodeLayout(array $layout): string
	{
		if (!isset($layout['sections']) || !is_array($layout['sections'])) {
			throw new \InvalidArgumentException(
				'Layout payload must contain a sections array'
			);
		}
		$json = json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			throw new \InvalidArgumentException(
				'Layout payload is not JSON-encodable: ' . json_last_error_msg()
			);
		}
		return $json;
	}

	/**
	 * Optional theme snapshot — null/empty arrays become NULL so the
	 * column stays sparse for template-only revisions.
	 */
	public static function encodeTheme(?array $themeData): ?string
	{
		if ($themeData === null || $themeData === []) {
			return null;
		}
		$json = json_encode($themeData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		return $json === false ? null : $json;
	}

	/**
	 * Note column accepts arbitrary user-supplied label ("autosave",
	 * "before publish", etc.). Truncate + trim so DB constraint never
	 * raises.
	 */
	public static function normalizeNote(?string $note): ?string
	{
		if ($note === null) return null;
		$note = trim($note);
		if ($note === '') return null;
		return mb_substr($note, 0, 255);
	}

	/* -----------------------------------------------------------------
	 * DB methods — require an injected (or lazily-resolved) driver.
	 * --------------------------------------------------------------- */

	protected function db()
	{
		if ($this->db !== null) {
			return $this->db;
		}
		if (class_exists('Joomla\\CMS\\Factory')) {
			$this->db = \Joomla\CMS\Factory::getDbo();
			return $this->db;
		}
		throw new \RuntimeException('No database driver available for Revisions helper');
	}

	/**
	 * Insert a revision row. Returns the new id. Auto-prunes the tuple
	 * to `DEFAULT_KEEP` rows.
	 */
	public function record(int $parentId, string $parentType, array $layout, ?array $themeData = null, ?string $note = null, int $userId = 0): int
	{
		$parentType = self::normalizeParentType($parentType);
		$layoutJson = self::encodeLayout($layout);
		$themeJson  = self::encodeTheme($themeData);
		$note       = self::normalizeNote($note);

		$db = $this->db();
		$now = class_exists('Joomla\\CMS\\Factory')
			? \Joomla\CMS\Factory::getDate()->toSql()
			: gmdate('Y-m-d H:i:s');

		$row = (object) [
			'parent_id'   => $parentId,
			'parent_type' => $parentType,
			'layout_json' => $layoutJson,
			'theme_json'  => $themeJson,
			'note'        => $note,
			'created'     => $now,
			'created_by'  => $userId,
		];
		$db->insertObject('#__flexicontent_pro_layout_revisions', $row);
		$newId = (int) $db->insertid();

		$this->prune($parentId, $parentType, self::DEFAULT_KEEP);

		return $newId;
	}

	/**
	 * Recent revisions for a parent. Most-recent first.
	 *
	 * @return array<int, object>
	 */
	public function listFor(int $parentId, string $parentType, int $limit = self::DEFAULT_KEEP): array
	{
		$parentType = self::normalizeParentType($parentType);
		$limit      = max(1, min(100, $limit));

		$db = $this->db();
		$q  = $db->getQuery(true)
			->select(['id', 'note', 'created', 'created_by'])
			->from($db->quoteName('#__flexicontent_pro_layout_revisions'))
			->where($db->quoteName('parent_type') . ' = ' . $db->quote($parentType))
			->where($db->quoteName('parent_id') . ' = ' . (int) $parentId)
			->order($db->quoteName('created') . ' DESC, ' . $db->quoteName('id') . ' DESC')
			->setLimit($limit);

		return (array) $db->setQuery($q)->loadObjectList();
	}

	/**
	 * Full revision row (with JSON columns). Null if not found.
	 */
	public function get(int $revisionId): ?object
	{
		$db = $this->db();
		$q  = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__flexicontent_pro_layout_revisions'))
			->where($db->quoteName('id') . ' = ' . (int) $revisionId);

		$row = $db->setQuery($q)->loadObject();
		return $row ?: null;
	}

	/**
	 * Drop everything beyond the most-recent $keep rows for a tuple.
	 * Returns the number of rows deleted.
	 */
	public function prune(int $parentId, string $parentType, int $keep = self::DEFAULT_KEEP): int
	{
		$parentType = self::normalizeParentType($parentType);
		$keep       = max(1, $keep);

		$db = $this->db();
		$q  = $db->getQuery(true)
			->select('id')
			->from($db->quoteName('#__flexicontent_pro_layout_revisions'))
			->where($db->quoteName('parent_type') . ' = ' . $db->quote($parentType))
			->where($db->quoteName('parent_id') . ' = ' . (int) $parentId)
			->order($db->quoteName('created') . ' DESC, ' . $db->quoteName('id') . ' DESC');
		$ids = (array) $db->setQuery($q)->loadColumn();

		$toDelete = array_slice($ids, $keep);
		if (!$toDelete) return 0;

		$del = $db->getQuery(true)
			->delete($db->quoteName('#__flexicontent_pro_layout_revisions'))
			->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $toDelete)) . ')');
		$db->setQuery($del)->execute();

		return count($toDelete);
	}
}
