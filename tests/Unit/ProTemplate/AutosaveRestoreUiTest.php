<?php
/**
 * Regression guards for the Pro Template builder's autosave +
 * restore UI introduced in 6.1.0-beta.13.
 *
 * Four surfaces under test:
 *   1. admin/views/protemplate/tmpl/default.php — ARIA markup,
 *      restore panel, alertdialog modal, Alpine state hooks.
 *   2. admin/controllers/protemplates.php — new JSON endpoints
 *      listRevisionsJson() + restoreRevisionJson().
 *   3. admin/assets/css/protemplate_builder.css — .visually-hidden
 *      utility + .fcpt-revisions-panel rules used by the markup.
 *   4. admin/language/en-GB + th-TH INI files — required keys
 *      so Text::_() never falls back to the raw constant.
 *
 * All tests are static (file_get_contents + regex/string).
 * No Joomla bootstrap or DB required.
 *
 * @package  FLEXIcontent
 * @since    6.1.0-beta.13
 */

namespace FLEXIcontent\Tests\Unit\ProTemplate;

use PHPUnit\Framework\TestCase;

class AutosaveRestoreUiTest extends TestCase
{
	private function template(): string
	{
		$src = file_get_contents(
			dirname(__DIR__, 3) . '/admin/views/protemplate/tmpl/default.php'
		);
		$this->assertNotFalse($src);
		return $src;
	}

	private function controller(): string
	{
		$src = file_get_contents(
			dirname(__DIR__, 3) . '/admin/controllers/protemplates.php'
		);
		$this->assertNotFalse($src);
		return $src;
	}

	private function css(): string
	{
		$src = file_get_contents(
			dirname(__DIR__, 3) . '/admin/assets/css/protemplate_builder.css'
		);
		$this->assertNotFalse($src);
		return $src;
	}

	private function ini(string $lang): string
	{
		$src = file_get_contents(
			dirname(__DIR__, 3) . '/admin/language/' . $lang . '/'
			. $lang . '.com_flexicontent.ini'
		);
		$this->assertNotFalse($src);
		return $src;
	}

	/* ── 1. Live regions — WCAG 4.1.3 ─────────────────────────── */

	public function testAutosaveStatusUsesRoleStatusOnly(): void
	{
		$tpl = $this->template();
		$this->assertMatchesRegularExpression(
			'#<span\s+class="fcpt-autosave-status"\s+role="status"\s+x-text="autosave\.status"\s*></span>#',
			$tpl,
			'autosave status must be role="status" with no redundant aria-live/aria-atomic'
		);
		$this->assertDoesNotMatchRegularExpression(
			'#class="fcpt-autosave-status"[^>]*aria-live=#',
			$tpl,
			'role="status" already implies polite live region — do not add aria-live'
		);
	}

	public function testRestoreHasSeparateLiveRegion(): void
	{
		$tpl = $this->template();
		$this->assertMatchesRegularExpression(
			'#<span\s+class="fcpt-revisions-status visually-hidden"\s+role="status"\s+x-text="revisions\.status"\s*></span>#',
			$tpl,
			'restore announcements must use a separate sr-only role="status" region'
		);
	}

	/* ── 2. Restore panel disclosure + accessible name ────────── */

	public function testRevisionsPanelDetailsSummaryStructure(): void
	{
		$tpl = $this->template();
		$this->assertStringContainsString('<details class="fcpt-revisions-panel"', $tpl);
		$this->assertStringContainsString(
			"<?= Text::_('FLEXI_PROTEMPLATE_EARLIER_VERSIONS') ?>",
			$tpl
		);
		$this->assertMatchesRegularExpression(
			'#fcpt-revisions-count[^>]*aria-hidden="true"#',
			$tpl
		);
		$this->assertStringContainsString(
			"x-text=\"revisions.list.length + ' saved versions'\"",
			$tpl
		);
	}

	public function testRestoreButtonUsesAriaDescribedbyNotAriaLabel(): void
	{
		$tpl = $this->template();
		$this->assertMatchesRegularExpression(
			'#:aria-describedby="\'fcpt-rev-time-\'\s*\+\s*rev\.id"#',
			$tpl,
			'restore button must use aria-describedby pointing at the row time'
		);
		$this->assertStringContainsString(
			"<?= Text::_('FLEXI_PROTEMPLATE_RESTORE') ?>",
			$tpl
		);
		$this->assertDoesNotMatchRegularExpression(
			'#aria-label=["\']Restore version saved#',
			$tpl
		);
	}

	public function testTimeElementHasDatetimeAndTitle(): void
	{
		$tpl = $this->template();
		$this->assertMatchesRegularExpression(
			'#<time\s+:datetime="rev\.created"\s+:title="rev\.created"#',
			$tpl
		);
	}

	public function testEmptyStateLivesOutsideList(): void
	{
		$tpl = $this->template();
		$this->assertMatchesRegularExpression(
			'#</template>\s*</ul>\s*<p class="fcpt-revisions-empty"#s',
			$tpl,
			'empty-state message must NOT live inside <ul> (preserves list count announcements)'
		);
	}

	/* ── 3. Restore confirm dialog — role="alertdialog" ────────── */

	public function testRestoreDialogIsAlertdialog(): void
	{
		$tpl = $this->template();
		$this->assertMatchesRegularExpression(
			'#role="alertdialog"\s+aria-modal="true"\s+aria-labelledby="fcpt-restore-title"\s+aria-describedby="fcpt-restore-desc"#',
			$tpl
		);
		$this->assertStringContainsString('id="fcpt-restore-title"', $tpl);
		$this->assertStringContainsString('id="fcpt-restore-desc"', $tpl);
	}

	public function testCancelIsDefaultFocusTarget(): void
	{
		$tpl = $this->template();
		$this->assertMatchesRegularExpression('#x-ref="restoreCancelBtn"#', $tpl);
		$this->assertMatchesRegularExpression(
			'#\$refs\.restoreCancelBtn\?\.focus\(\)#',
			$tpl,
			'askRestore() must move focus to the Cancel button by default'
		);
	}

	public function testFocusReturnsToOpener(): void
	{
		$tpl = $this->template();
		$this->assertStringContainsString('opener:  opener || null', $tpl);
		$this->assertMatchesRegularExpression(
			'#opener\?\.focus\(\)#',
			$tpl,
			'closing the restore dialog must return focus to the originating Restore button'
		);
	}

	public function testEscapeAndBackdropClosesDialog(): void
	{
		$tpl = $this->template();
		$this->assertStringContainsString('@keydown.escape.window="cancelRestore()"', $tpl);
		$this->assertStringContainsString('@click.self="cancelRestore()"', $tpl);
	}

	/* ── 4. Alpine state + fetch wiring ───────────────────────── */

	public function testRevisionsAlpineStatePresent(): void
	{
		$tpl = $this->template();
		$this->assertStringContainsString('revisions: {', $tpl);
		$this->assertStringContainsString('listUrl:', $tpl);
		$this->assertStringContainsString('restoreUrl:', $tpl);
		$this->assertStringContainsString('task=protemplates.listRevisionsJson', $tpl);
		$this->assertStringContainsString('task=protemplates.restoreRevisionJson', $tpl);
	}

	public function testRevisionsLoadOnPanelToggle(): void
	{
		$tpl = $this->template();
		$this->assertStringContainsString('@toggle="onRevisionsToggle($event)"', $tpl);
		$this->assertStringContainsString('onRevisionsToggle(event)', $tpl);
		$this->assertStringContainsString('loadRevisions()', $tpl);
	}

	public function testAutosaveSuccessInvalidatesRevisionsCache(): void
	{
		$tpl = $this->template();
		$this->assertStringContainsString('this.revisions.loaded = false;', $tpl);
	}

	/* ── 5. Controller endpoints ──────────────────────────────── */

	public function testControllerDeclaresListRevisionsJson(): void
	{
		$src = $this->controller();
		$this->assertMatchesRegularExpression(
			'#public function listRevisionsJson\s*\(\s*\)\s*:\s*void#',
			$src
		);
		$this->assertStringContainsString("Session::checkToken('request')", $src);
		$this->assertStringContainsString(
			'FlexicontentProTemplateRevisions::TYPE_TEMPLATE',
			$src
		);
	}

	public function testControllerDeclaresRestoreRevisionJson(): void
	{
		$src = $this->controller();
		$this->assertMatchesRegularExpression(
			'#public function restoreRevisionJson\s*\(\s*\)\s*:\s*void#',
			$src
		);
		$this->assertMatchesRegularExpression(
			'#restoreRevisionJson.*?Session::checkToken\(\)#s',
			$src
		);
	}

	public function testRestoreEnforcesParentScope(): void
	{
		$src = $this->controller();
		$this->assertMatchesRegularExpression(
			'#\(int\)\s*\$row->parent_id\s*!==\s*\$id#',
			$src,
			'restoreRevisionJson must reject revisions belonging to a different layout'
		);
	}

	public function testControllerLoadsRevisionsHelper(): void
	{
		$src = $this->controller();
		$this->assertStringContainsString(
			"helpers' . DS . 'protemplate' . DS . 'Revisions.php'",
			$src
		);
	}

	/* ── 6. CSS hookup ────────────────────────────────────────── */

	public function testCssDeclaresVisuallyHiddenUtility(): void
	{
		$css = $this->css();
		$this->assertMatchesRegularExpression(
			'#\.visually-hidden\s*\{[^}]*position:\s*absolute[^}]*\}#s',
			$css
		);
	}

	public function testCssDeclaresRevisionsPanelRules(): void
	{
		$css = $this->css();
		$this->assertStringContainsString('.fcpt-revisions-panel', $css);
		$this->assertStringContainsString('.fcpt-revisions-item', $css);
		$this->assertStringContainsString('.fcpt-restore-warn', $css);
		$this->assertStringContainsString('.fcpt-modal-actions', $css);
	}

	public function testCssForcedColorsFallback(): void
	{
		$css = $this->css();
		$this->assertMatchesRegularExpression(
			'#@media\s*\(forced-colors:\s*active\)\s*\{[^}]*\.fcpt-revisions-panel#s',
			$css
		);
	}

	/* ── 7. i18n strings present in both languages ───────────── */

	/**
	 * @dataProvider requiredKeys
	 */
	public function testEnGbHasKey(string $key): void
	{
		$ini = $this->ini('en-GB');
		$this->assertMatchesRegularExpression(
			'#^' . preg_quote($key, '#') . '="[^"]+"#m',
			$ini,
			"en-GB INI must declare $key"
		);
	}

	/**
	 * @dataProvider requiredKeys
	 */
	public function testThThHasKey(string $key): void
	{
		$ini = $this->ini('th-TH');
		$this->assertMatchesRegularExpression(
			'#^' . preg_quote($key, '#') . '="[^"]+"#m',
			$ini,
			"th-TH INI must declare $key"
		);
	}

	public static function requiredKeys(): array
	{
		return [
			['FLEXI_PROTEMPLATE_EARLIER_VERSIONS'],
			['FLEXI_PROTEMPLATE_REVISIONS_HELP'],
			['FLEXI_PROTEMPLATE_NO_REVISIONS'],
			['FLEXI_PROTEMPLATE_RESTORE'],
			['FLEXI_PROTEMPLATE_RESTORE_CONFIRM_TITLE'],
			['FLEXI_PROTEMPLATE_RESTORE_CONFIRM_BODY'],
			['FLEXI_PROTEMPLATE_RESTORE_CONFIRM_WARN'],
			['FLEXI_PROTEMPLATE_RESTORE_REPLACE'],
		];
	}
}
