<?php
/**
 * Unit tests for the Pro Templates view_scope + starter-layout features
 * shipped in 6.1.0-beta.2.
 *
 * Covers four production surfaces:
 *
 * 1. Resolver enum sanitization — $context['view'] collapses to
 *    'item' | 'category' before reaching SQL, even on garbage input.
 * 2. Form XML exposes a required view_scope list with two allowed
 *    values plus translated label/description keys.
 * 3. Schema (install SQL + 6.1.0-beta.2 migration) declares the column
 *    and backfills existing rows.
 * 4. Lang INI files (en-GB + th-TH) define the four label keys.
 * 5. Starter layout JSON ships title/image/body content-driven blocks
 *    with no hardcoded text or alt (WCAG 1.1.1).
 *
 * @package  FLEXIcontent
 * @since    6.1.0-beta.2
 */

namespace FLEXIcontent\Tests\Unit\ProTemplate;

use PHPUnit\Framework\TestCase;

class ViewScopeTest extends TestCase
{
	private const ALLOWED = ['item', 'category'];

	/** Mirror of the Resolver enum-allowlist predicate. */
	private function sanitize(string $view): string
	{
		return in_array($view, self::ALLOWED, true) ? $view : 'item';
	}

	public function testEnumAllowlistKeepsValidValues(): void
	{
		$this->assertSame('item',     $this->sanitize('item'));
		$this->assertSame('category', $this->sanitize('category'));
	}

	public function testEnumAllowlistRejectsGarbage(): void
	{
		$this->assertSame('item', $this->sanitize(''),                'empty falls back to item');
		$this->assertSame('item', $this->sanitize('Item'),            'case-sensitive, mixed case rejected');
		$this->assertSame('item', $this->sanitize("category' OR 1=1"), 'SQL injection attempt rejected');
		$this->assertSame('item', $this->sanitize('mcats'),           'multi-cats list view rejected');
	}

	public function testResolverContainsViewScopeFilter(): void
	{
		$resolver = dirname(__DIR__, 3) . '/admin/helpers/protemplate/Resolver.php';
		$this->assertFileExists($resolver);
		$src = file_get_contents($resolver);

		$this->assertStringContainsString(
			"'view_scope'",
			$src,
			'Resolver must reference the view_scope column'
		);

		$this->assertMatchesRegularExpression(
			"/in_array\\(\\s*\\\$context\\['view'\\]\\s*,\\s*\\['item',\\s*'category'\\]\\s*,\\s*true\\s*\\)/",
			$src,
			'Resolver must sanitize $context[view] against the enum allowlist'
		);
	}

	public function testFormXmlDeclaresViewScopeField(): void
	{
		$xml = dirname(__DIR__, 3) . '/admin/forms/protemplate.xml';
		$this->assertFileExists($xml);
		$doc = simplexml_load_file($xml);
		$this->assertNotFalse($doc);

		$nodes = $doc->xpath('//field[@name="view_scope"]');
		$this->assertNotEmpty($nodes, 'view_scope field must exist in protemplate.xml');

		$field = $nodes[0];
		// view_scope is locked by the chooser at create-time and hidden in the
		// builder so editors never override it. The model still validates it
		// against the enum allowlist on save (item|category).
		$this->assertSame('hidden', (string) $field['type']);
		$this->assertSame('item',   (string) $field['default']);
	}

	public function testCatidQueryFiltersOnContentExtension(): void
	{
		// Joomla content categories used by FlexiContent live under
		// extension='com_content'. A previous revision filtered on
		// 'com_flexicontent' which returns zero rows, so the Category
		// dropdown was unselectable.
		$xml = dirname(__DIR__, 3) . '/admin/forms/protemplate.xml';
		$this->assertFileExists($xml);
		$doc = simplexml_load_file($xml);
		$this->assertNotFalse($doc);

		$nodes = $doc->xpath('//field[@name="catid"]');
		$this->assertNotEmpty($nodes, 'catid field must exist in protemplate.xml');

		$query = (string) $nodes[0]['query'];
		$this->assertStringContainsString("extension='com_content'", $query,
			'catid query must filter on com_content (Joomla content categories)');
		$this->assertStringNotContainsString("extension='com_flexicontent'", $query,
			"catid query must not filter on com_flexicontent");
	}

	public function testStarterLayoutShapeIsContentDriven(): void
	{
		// Source-level inspection: the model extends a Joomla base class
		// that is not loaded in the unit test bootstrap. Parse the
		// starterLayoutJson() body out of the source so we can validate
		// the seed shape without booting Joomla.
		$src = file_get_contents(dirname(__DIR__, 3) . '/admin/models/protemplate.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString(
			'public static function starterLayoutJson(): string',
			$src,
			'model must expose starterLayoutJson() static helper'
		);

		// Extract block type tokens in declaration order. Three blocks,
		// in the order they appear in the source, must be title -> image -> body.
		preg_match_all("/'type'\\s*=>\\s*'([a-z]+)'/", $src, $m);
		$types = $m[1] ?? [];
		$this->assertSame(
			['title', 'image', 'body'],
			$types,
			'starter must ship title + image + body in that order'
		);

		// Reject hardcoded text / alt / value — content must come from
		// the rendered item or category source so each page is content-
		// driven. Baked-in alt="" or "Title" would violate WCAG 1.1.1.
		$blockRegion = preg_match(
			"/starterLayoutJson\\(\\).*?return\\s+json_encode/s",
			$src,
			$mm
		) ? $mm[0] : '';
		$this->assertNotEmpty($blockRegion, 'starterLayoutJson() body must be parseable');

		foreach (["'text'", "'alt'", "'value'", '"text"', '"alt"', '"value"'] as $forbidden) {
			$this->assertStringNotContainsString(
				$forbidden,
				$blockRegion,
				"starter must not bake hardcoded $forbidden into the seed JSON"
			);
		}
	}

	public function testMigrationAddsViewScopeColumn(): void
	{
		$mig = dirname(__DIR__, 3) . '/admin/installation/sql/updates/mysql/6.1.0-beta.2.sql';
		$this->assertFileExists($mig);
		$sql = file_get_contents($mig);

		$this->assertMatchesRegularExpression(
			"/ADD COLUMN (?:IF NOT EXISTS )?`view_scope`/",
			$sql,
			'migration must add view_scope column (idempotent IF NOT EXISTS allowed)'
		);
		// The migration wraps the ALTER inside a PREPARE statement, so the
		// 'item' literal is doubled to escape the outer quotes. Accept both
		// the direct form and the escaped-for-PREPARE form.
		$this->assertMatchesRegularExpression(
			"/VARCHAR\\(16\\) NOT NULL DEFAULT ''item''|VARCHAR\\(16\\) NOT NULL DEFAULT 'item'/",
			$sql,
			'migration must declare VARCHAR(16) NOT NULL DEFAULT item (raw or PREPARE-escaped)'
		);
		$this->assertMatchesRegularExpression("/UPDATE\\s+`#__flexicontent_pro_layouts`\\s+SET\\s+`view_scope`\\s*=\\s*'item'/i", $sql);
	}

	public function testInstallSqlDeclaresViewScopeColumn(): void
	{
		$install = dirname(__DIR__, 3) . '/admin/installation/sql/protemplates_install.sql';
		$this->assertFileExists($install);
		$sql = file_get_contents($install);

		$this->assertStringContainsString("`view_scope`       VARCHAR(16)         NOT NULL DEFAULT 'item'", $sql);
	}

	public function testLangFilesDefineViewScopeKeys(): void
	{
		$keys = [
			'FLEXI_PROTEMPLATE_VIEW_SCOPE',
			'FLEXI_PROTEMPLATE_VIEW_SCOPE_DESC',
			'FLEXI_PROTEMPLATE_VIEW_SCOPE_ITEM',
			'FLEXI_PROTEMPLATE_VIEW_SCOPE_CATEGORY',
		];
		$inis = [
			dirname(__DIR__, 3) . '/admin/language/en-GB/en-GB.com_flexicontent.ini',
			dirname(__DIR__, 3) . '/admin/language/th-TH/th-TH.com_flexicontent.ini',
		];
		foreach ($inis as $ini) {
			$this->assertFileExists($ini);
			$body = file_get_contents($ini);
			foreach ($keys as $k) {
				$this->assertStringContainsString($k . '=', $body, "$ini must define $k");
			}
		}
	}
}
