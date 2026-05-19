<?php
/**
 * @package         FLEXIcontent
 * @subpackage      Pro Templates — Google Fonts loader
 *
 * @author          FLEXIcontent Team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * Extracts the primary font family from a Pro Layout's typography
 * settings and emits the necessary <link rel="preconnect"> + <link
 * rel="stylesheet"> tags to load it from Google Fonts with
 * `display=swap` (FOUT — text is always visible).
 *
 * The catalog is shipped offline (admin/helpers/protemplate/data/google-fonts.php).
 * No runtime API calls, no Google Fonts API key required. Adding a new
 * font = append a row to the catalog file.
 *
 * Accessibility (WCAG 2.2 AA, cleared by accessibility-lead 2026-05-19):
 *   - display=swap so fallback shows immediately (1.4.4 Resize Text)
 *   - full font stack preserved in CSS — if Google Fonts CDN fails the
 *     stack's last system-ui / serif keyword still renders legible text
 *   - Thai subset auto-detected from the catalog metadata (no naive
 *     lang-attribute scan that misses mixed-language pages)
 *
 * Privacy: Google Fonts CDN logs IPs. Sites with strict GDPR posture
 * may self-host instead. Point FONT_HOST to fonts.bunny.net or a local
 * CDN — the catalog data is unchanged.
 */

defined('_JEXEC') or die('Restricted access');

class FlexicontentProTemplateFonts
{
	/** Google Fonts CDN host. Change to fonts.bunny.net for GDPR-safe alt. */
	protected const FONT_HOST = 'https://fonts.googleapis.com';

	/** Pre-connect target for the font-files CDN (not the CSS host). */
	protected const FONT_CDN_HOST = 'https://fonts.gstatic.com';

	/** Default weights when the theme does not declare specific weights. */
	protected const DEFAULT_WEIGHTS = ['400', '700'];

	/** In-process cache of the catalog so we don't re-include per call. */
	protected static $catalog = null;

	/**
	 * Load and cache the curated Google Fonts catalog.
	 *
	 * @return array<string, array{weights:array<int,string>, subsets:array<int,string>, thai:bool, category:string}>
	 */
	public static function catalog(): array
	{
		if (self::$catalog === null) {
			$file = __DIR__ . '/data/google-fonts.php';
			self::$catalog = is_file($file) ? (array) require $file : [];
		}
		return self::$catalog;
	}

	/**
	 * Strip surrounding quotes + whitespace from a CSS font name token.
	 */
	protected static function normalizeFamily(string $name): string
	{
		$name = trim($name);
		$name = trim($name, "\"' \t");
		return $name;
	}

	/**
	 * Split a font-family CSS value into individual names.
	 *   Input:  'Inter, "Helvetica Neue", system-ui, sans-serif'
	 *   Output: ['Inter', 'Helvetica Neue', 'system-ui', 'sans-serif']
	 */
	public static function splitStack(string $cssValue): array
	{
		if ($cssValue === '') return [];
		$parts = [];
		$tokens = preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $cssValue) ?: [];
		foreach ($tokens as $t) {
			$t = self::normalizeFamily($t);
			if ($t !== '') {
				$parts[] = $t;
			}
		}
		return $parts;
	}

	/**
	 * Return the first family in the stack that exists in the Google
	 * Fonts catalog. null if none match.
	 */
	public static function pickGoogleFamily(string $cssValue): ?string
	{
		$catalog = self::catalog();
		foreach (self::splitStack($cssValue) as $family) {
			if (isset($catalog[$family])) {
				return $family;
			}
		}
		return null;
	}

	/**
	 * Build the Google Fonts CSS2 URL.
	 *
	 * @param array<string, array<int,string>> $families  family => weights[]
	 * @param bool $includeThai                            append &subset=thai when any family supports Thai
	 */
	public static function buildCssUrl(array $families, bool $includeThai = false): string
	{
		if (!$families) return '';
		$catalog = self::catalog();
		$params = [];
		foreach ($families as $family => $weights) {
			if (!isset($catalog[$family])) continue;
			$weights = $weights ?: self::DEFAULT_WEIGHTS;
			$weights = array_values(array_unique(array_map('strval', $weights)));
			sort($weights, SORT_NUMERIC);
			$weightSpec = implode(';', $weights);
			$params[] = 'family=' . str_replace(' ', '+', $family)
				. ':wght@' . $weightSpec;
		}
		if (!$params) return '';
		$params[] = 'display=swap';
		if ($includeThai) {
			$params[] = 'subset=thai';
		}
		return self::FONT_HOST . '/css2?' . implode('&', $params);
	}

	/**
	 * Inspect a Pro Layout, extract Google Font families, register the
	 * preconnect + stylesheet tags via WebAssetManager.
	 *
	 * @param  object       $document  Joomla document (\Joomla\CMS\Document\Document)
	 * @param  array|object $layout    Decoded layout JSON or its settings sub-tree
	 *
	 * @return array{body:?string, heading:?string}  the resolved family names for inline binding
	 */
	public static function register($document, $layout): array
	{
		$result = ['body' => null, 'heading' => null];

		$typography = self::extractTypography($layout);
		if (!$typography) return $result;

		$bodyCss     = (string) ($typography['family']         ?? '');
		$headingCss  = (string) ($typography['family_heading'] ?? '');

		$body    = self::pickGoogleFamily($bodyCss);
		$heading = self::pickGoogleFamily($headingCss);

		$families = [];
		$needsThai = false;
		$catalog = self::catalog();

		if ($body) {
			$families[$body] = self::DEFAULT_WEIGHTS;
			if (!empty($catalog[$body]['thai'])) $needsThai = true;
			$result['body'] = $body;
		}
		if ($heading && $heading !== $body) {
			$families[$heading] = self::DEFAULT_WEIGHTS;
			if (!empty($catalog[$heading]['thai'])) $needsThai = true;
			$result['heading'] = $heading;
		} elseif ($heading) {
			$result['heading'] = $heading;
		}

		if (!$families) return $result;

		try {
			$wam = $document->getWebAssetManager();
			$wam->registerAndUseStyle(
				'fc-google-fonts-preconnect',
				self::FONT_HOST,
				[],
				['rel' => 'preconnect']
			);
			$wam->registerAndUseStyle(
				'fc-google-fonts-preconnect-cdn',
				self::FONT_CDN_HOST,
				[],
				['rel' => 'preconnect', 'crossorigin' => 'anonymous']
			);
			$url = self::buildCssUrl($families, $needsThai);
			if ($url !== '') {
				$wam->registerAndUseStyle('fc-google-fonts', $url);
			}
		} catch (\Throwable $e) {
			// Document does not support WebAssetManager (test env). No-op.
		}

		return $result;
	}

	/**
	 * Build a CSS snippet that sets --fc-font-body / --fc-font-heading
	 * on the .fcpt-layout scope so the frontend stylesheet picks up the
	 * theme-chosen fonts WITHOUT touching the saved layout HTML output.
	 *
	 * @param array{body:?string, heading:?string} $resolved  output of register()
	 * @param array                                $typography theme typography block
	 */
	public static function buildScopeCss(array $resolved, array $typography): string
	{
		$bodyStack    = (string) ($typography['family']         ?? '');
		$headingStack = (string) ($typography['family_heading'] ?? '');

		$decls = [];
		if ($bodyStack !== '') {
			$decls[] = '--fc-font-body: ' . self::escapeCssValue($bodyStack) . ';';
		}
		if ($headingStack !== '') {
			$decls[] = '--fc-font-heading: ' . self::escapeCssValue($headingStack) . ';';
		}
		$lineHeight = (string) ($typography['line_height'] ?? '');
		if ($lineHeight !== '') {
			$lineHeightF = (float) $lineHeight;
			if ($lineHeightF >= 1.0 && $lineHeightF <= 3.0) {
				$decls[] = '--fc-line-height: ' . $lineHeightF . ';';
			}
		}
		if (!$decls) return '';

		return '.fcpt-layout { ' . implode(' ', $decls) . ' }';
	}

	/**
	 * CSS-escape a font stack: strip tag injection + CSS-significant chars.
	 */
	protected static function escapeCssValue(string $v): string
	{
		$v = preg_replace('#</?[a-z]#i', '', $v);
		$v = str_replace(['{', '}', ';'], '', $v);
		return $v;
	}

	/**
	 * Pull the typography block out of a layout payload, accepting both
	 * the full layout and a pre-extracted theme_data hand-off.
	 */
	protected static function extractTypography($layout): ?array
	{
		if (is_object($layout)) {
			$layout = json_decode(json_encode($layout), true);
		}
		if (is_string($layout)) {
			$layout = json_decode($layout, true);
		}
		if (!is_array($layout)) return null;

		if (isset($layout['settings']['theme_data']['typography'])
			&& is_array($layout['settings']['theme_data']['typography'])
		) {
			return $layout['settings']['theme_data']['typography'];
		}
		if (isset($layout['typography']) && is_array($layout['typography'])) {
			return $layout['typography'];
		}
		if (isset($layout['family']) || isset($layout['family_heading'])) {
			return $layout;
		}
		return null;
	}
}
