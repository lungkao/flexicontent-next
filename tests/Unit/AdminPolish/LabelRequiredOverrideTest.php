<?php
/**
 * Guards the global admin label.required color override shipped via
 * plg_system_flexisystem.onBeforeCompileHead.
 *
 * Regression history:
 *
 * 1. Joomla 6 Atum admin template colors `label.required` red across
 *    every admin form (com_users edit user, com_modules, com_content,
 *    etc.). This was visually inconsistent with the FLEXIcontent admin
 *    polish work and violated WCAG 1.4.1 (color used as sole indicator
 *    of required state).
 *
 * 2. Earlier override in admin/assets/css/j4x_modern.css was scoped only
 *    to com_flexicontent + com_menus, so com_users still rendered red
 *    labels on Name / Login Name / Email.
 *
 * The fix injects a small inline `<style>` declaration on every admin
 * page (via the existing onBeforeCompileHead admin guard) scoped to
 * `body[class*="com_"]`. The required state is preserved through:
 *   - native `required` attribute exposed to AT
 *   - visible asterisk via `::after` content
 *   - bold weight
 *
 * A11y-cleared (accessibility-lead) — WCAG 1.4.1 PASS, 1.4.3 PASS
 * (#c9302c on white = 4.5:1+).
 *
 * @package  FLEXIcontent
 * @since    6.1.0-beta.4
 */

namespace FLEXIcontent\Tests\Unit\AdminPolish;

use PHPUnit\Framework\TestCase;

class LabelRequiredOverrideTest extends TestCase
{
	private function pluginFile(): string
	{
		return dirname(__DIR__, 3) . '/plugins/system/flexisystem/flexisystem.php';
	}

	private function pluginSource(): string
	{
		$src = file_get_contents($this->pluginFile());
		$this->assertNotFalse($src, 'flexisystem.php must exist and be readable.');
		return $src;
	}

	public function testStyleDeclarationIsAddedInsideOnBeforeCompileHead(): void
	{
		$src = $this->pluginSource();

		$this->assertMatchesRegularExpression(
			'/public function onBeforeCompileHead\(\).*addStyleDeclaration\([^)]*<<<.CSS/s',
			$src,
			'addStyleDeclaration heredoc must be inside onBeforeCompileHead so it inherits the admin+logged-in guard.'
		);
	}

	public function testOverrideTargetsAllAdminComponents(): void
	{
		$src = $this->pluginSource();

		$this->assertStringContainsString(
			'body[class*="com_"] label.required',
			$src,
			'Override must use body[class*="com_"] so it applies to every Joomla admin component, not just com_flexicontent.'
		);
	}

	public function testOverrideClearsLabelColor(): void
	{
		$src = $this->pluginSource();

		$this->assertMatchesRegularExpression(
			'/body\[class\*="com_"\] label\.required[^{]*\{[^}]*color\s*:\s*inherit/s',
			$src,
			'label.required color must be reset to inherit (neutralize Atum red).'
		);
	}

	public function testAsteriskPseudoIsKeptForRedundancyWcag141(): void
	{
		$src = $this->pluginSource();

		$this->assertMatchesRegularExpression(
			'/body\[class\*="com_"\] label\.required::after[^{]*\{[^}]*content\s*:\s*" \*"/s',
			$src,
			'Required asterisk must remain via ::after content so required state is not conveyed by color alone (WCAG 1.4.1).'
		);

		$this->assertMatchesRegularExpression(
			'/label\.required::after[^{]*\{[^}]*color\s*:\s*#c9302c/s',
			$src,
			'Asterisk red must use #c9302c (4.5:1 contrast on white) for the redundant color signal.'
		);
	}

	public function testOverrideIsScopedToAdmin(): void
	{
		$src = $this->pluginSource();

		$this->assertMatchesRegularExpression(
			'/onBeforeCompileHead.*isClient\(.administrator.\)/s',
			$src,
			'onBeforeCompileHead must keep the admin guard so the style declaration does not bleed to frontend.'
		);
	}
}
