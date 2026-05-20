<?php
/**
 * Tests for FlexicontentProTemplateRevisions — the revision-store
 * helper behind Pro Template / Pro Theme autosave.
 *
 * Pure helpers (encodeLayout / encodeTheme / normalizeNote /
 * normalizeParentType) are tested directly. DB methods are
 * exercised via an in-memory fake driver (RevisionsFakeDb) —
 * no real Joomla DB connection.
 *
 * @package  FLEXIcontent
 * @since    6.1.0-beta.12
 */

namespace FLEXIcontent\Tests\Unit\ProTemplate;

use PHPUnit\Framework\TestCase;

class RevisionsHelperTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		require_once dirname(__DIR__, 3) . '/admin/helpers/protemplate/Revisions.php';
	}

	/* ── Pure helpers ────────────────────────────────────────────── */

	public function testNormalizeParentType(): void
	{
		$this->assertSame('template', \FlexicontentProTemplateRevisions::normalizeParentType('TEMPLATE'));
		$this->assertSame('theme',    \FlexicontentProTemplateRevisions::normalizeParentType(' theme '));
		$this->assertSame('template', \FlexicontentProTemplateRevisions::normalizeParentType('floppy'));
		$this->assertSame('template', \FlexicontentProTemplateRevisions::normalizeParentType(''));
	}

	public function testEncodeLayoutHappyPath(): void
	{
		$json = \FlexicontentProTemplateRevisions::encodeLayout([
			'sections' => [
				['key' => 's1', 'rows' => []],
			],
		]);
		$decoded = json_decode($json, true);
		$this->assertSame('s1', $decoded['sections'][0]['key']);
	}

	public function testEncodeLayoutRejectsMissingSections(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		\FlexicontentProTemplateRevisions::encodeLayout(['foo' => 'bar']);
	}

	public function testEncodeLayoutRejectsNonArraySections(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		\FlexicontentProTemplateRevisions::encodeLayout(['sections' => 'oops']);
	}

	public function testEncodeThemeNullForEmpty(): void
	{
		$this->assertNull(\FlexicontentProTemplateRevisions::encodeTheme(null));
		$this->assertNull(\FlexicontentProTemplateRevisions::encodeTheme([]));
	}

	public function testEncodeThemeHappyPath(): void
	{
		$json = \FlexicontentProTemplateRevisions::encodeTheme([
			'colors' => ['accent' => '#ff0080'],
		]);
		$this->assertSame('{"colors":{"accent":"#ff0080"}}', $json);
	}

	public function testNormalizeNoteHandling(): void
	{
		$this->assertNull(\FlexicontentProTemplateRevisions::normalizeNote(null));
		$this->assertNull(\FlexicontentProTemplateRevisions::normalizeNote('   '));
		$this->assertSame('autosave', \FlexicontentProTemplateRevisions::normalizeNote('  autosave '));

		// Truncation at 255 chars.
		$long = str_repeat('x', 300);
		$norm = \FlexicontentProTemplateRevisions::normalizeNote($long);
		$this->assertSame(255, mb_strlen($norm));
	}

	public function testDefaultKeepConstant(): void
	{
		// Pruning policy contract — code that depends on the value
		// (controllers, JS autosave UI) reads it via the constant.
		$this->assertSame(20, \FlexicontentProTemplateRevisions::DEFAULT_KEEP);
	}

	/* ── DB methods via fake driver ──────────────────────────────── */

	public function testRecordInsertsAndPrunes(): void
	{
		$fake = new RevisionsFakeDb();
		// Pre-seed 22 existing rows so record() forces 3 deletions
		// (22 existing + 1 new = 23; keep top 20 → delete 3).
		for ($i = 1; $i <= 22; $i++) {
			$fake->seedRow($i);
		}

		$rev = new \FlexicontentProTemplateRevisions($fake);
		$newId = $rev->record(
			7,
			'template',
			['sections' => [['key' => 's1', 'rows' => []]]],
			['colors' => ['accent' => '#abcdef']],
			'autosave',
			42
		);

		$this->assertSame(23, $newId, 'insertid should match the auto-increment fake');
		$this->assertCount(1, $fake->inserts, 'exactly one insertObject call');
		$insert = $fake->inserts[0];
		$this->assertSame('#__flexicontent_pro_layout_revisions', $insert['table']);
		$this->assertSame(7,          $insert['row']->parent_id);
		$this->assertSame('template', $insert['row']->parent_type);
		$this->assertSame('autosave', $insert['row']->note);
		$this->assertSame(42,         $insert['row']->created_by);
		$this->assertStringContainsString('"sections"', $insert['row']->layout_json);
		$this->assertStringContainsString('"#abcdef"',   $insert['row']->theme_json);

		// Prune ran and deleted exactly 3 IDs (the oldest).
		$this->assertCount(1, $fake->deletes);
		$deletedIds = $fake->deletes[0]['ids'];
		$this->assertCount(3, $deletedIds);
		// Seeded ids 1..22; after newId=23 the helper loads ALL ids
		// in DESC order (23..1), keeps top 20 (23..4), deletes bottom
		// 3 (3,2,1). The fake's loadColumn() returns seeded ids only
		// (1..22) sorted DESC = 22..1, so prune deletes ids 3,2,1.
		sort($deletedIds);
		$this->assertSame([1, 2, 3], $deletedIds);
	}

	public function testListForOrdersDescAndLimits(): void
	{
		$fake = new RevisionsFakeDb();
		$rev  = new \FlexicontentProTemplateRevisions($fake);

		$rev->listFor(7, 'template', 5);

		$q = $fake->lastQuery();
		$this->assertStringContainsString('parent_type', $q);
		$this->assertStringContainsString("'template'",  $q);
		$this->assertStringContainsString('parent_id',   $q);
		$this->assertStringContainsString('DESC',        $q);
		$this->assertStringContainsString('LIMIT 5',     $q);
	}

	public function testListForClampsLimit(): void
	{
		$fake = new RevisionsFakeDb();
		$rev  = new \FlexicontentProTemplateRevisions($fake);

		// 999 → clamps to 100.
		$rev->listFor(7, 'template', 999);
		$this->assertStringContainsString('LIMIT 100', $fake->lastQuery());

		// 0 → clamps to 1.
		$rev->listFor(7, 'template', 0);
		$this->assertStringContainsString('LIMIT 1', $fake->lastQuery());
	}
}

/* ─────────────────────────────────────────────────────────────────
 * Minimal fake DB driver — implements only the methods the helper
 * touches (insertObject, insertid, setQuery, execute, loadColumn,
 * getQuery, quoteName, quote).
 * ───────────────────────────────────────────────────────────────── */

class RevisionsFakeDb
{
	public array $inserts = [];
	public array $deletes = [];
	public array $queries = [];

	private array $seededIds = [];
	private int $nextId = 1;
	private $lastQuery;
	private int $lastInsertId = 0;

	public function seedRow(int $id): void
	{
		$this->seededIds[] = $id;
		$this->nextId = max($this->nextId, $id + 1);
	}

	public function insertObject(string $table, object $row): void
	{
		$row->id = $this->nextId++;
		$this->inserts[] = ['table' => $table, 'row' => $row];
		$this->lastInsertId = $row->id;
		// New row participates in subsequent prune() loadColumn() so
		// the test models real DB ordering (newest id included).
		$this->seededIds[] = $row->id;
	}

	public function insertid(): int
	{
		return $this->lastInsertId;
	}

	public function quote(string $v): string
	{
		return "'" . addslashes($v) . "'";
	}

	public function quoteName(string $name): string
	{
		return '`' . $name . '`';
	}

	public function getQuery(bool $new): RevisionsFakeQuery
	{
		return new RevisionsFakeQuery();
	}

	public function setQuery($q): self
	{
		$this->lastQuery = (string) $q;
		$this->queries[] = $this->lastQuery;
		return $this;
	}

	public function lastQuery(): string
	{
		return (string) $this->lastQuery;
	}

	public function execute(): void
	{
		if (stripos($this->lastQuery, 'DELETE') === 0
			&& preg_match('/IN \(([\d,]+)\)/', $this->lastQuery, $m)) {
			$ids = array_map('intval', explode(',', $m[1]));
			sort($ids);
			$this->deletes[] = ['ids' => $ids];
		}
	}

	public function loadColumn(): array
	{
		$ids = $this->seededIds;
		rsort($ids);
		return $ids;
	}

	public function loadObject()
	{
		return null;
	}

	public function loadObjectList(): array
	{
		return [];
	}
}

class RevisionsFakeQuery
{
	private string $type = '';
	private array  $parts = [];

	public function select($cols): self
	{
		$this->type = 'SELECT';
		$cols = is_array($cols) ? implode(', ', $cols) : (string) $cols;
		$this->parts['cols'] = $cols;
		return $this;
	}

	public function delete(string $table): self
	{
		$this->type = 'DELETE';
		$this->parts['table'] = $table;
		return $this;
	}

	public function from(string $table): self
	{
		$this->parts['table'] = $table;
		return $this;
	}

	public function where(string $cond): self
	{
		$this->parts['where'][] = $cond;
		return $this;
	}

	public function order(string $by): self
	{
		$this->parts['order'] = $by;
		return $this;
	}

	public function setLimit(int $n, int $offset = 0): self
	{
		$this->parts['limit'] = $n;
		return $this;
	}

	public function __toString(): string
	{
		if ($this->type === 'DELETE') {
			$sql = 'DELETE FROM ' . $this->parts['table'];
		} else {
			$sql = 'SELECT ' . ($this->parts['cols'] ?? '*')
				. ' FROM ' . ($this->parts['table'] ?? '');
		}
		if (!empty($this->parts['where'])) {
			$sql .= ' WHERE ' . implode(' AND ', $this->parts['where']);
		}
		if (!empty($this->parts['order'])) {
			$sql .= ' ORDER BY ' . $this->parts['order'];
		}
		if (isset($this->parts['limit'])) {
			$sql .= ' LIMIT ' . $this->parts['limit'];
		}
		return $sql;
	}
}
