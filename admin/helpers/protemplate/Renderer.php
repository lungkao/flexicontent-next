<?php
/**
 * @package         FLEXIcontent
 * @subpackage      Pro Templates — Renderer
 *
 * @author          FLEXIcontent Team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * Render a Pro Layout JSON (sections → rows → cols → elements) into
 * accessible semantic HTML for a FLEXIcontent item.
 *
 * Output spec (see B-final accessibility review):
 *   - One <h1> per render in 'item' context (the item title), enforced
 *     by clampHeading() — user-authored heading levels in JSON are
 *     clamped down if they try to outrank the title or skip levels
 *   - <article> wraps the whole item (single landmark)
 *   - <figure> for image_intro / image_full; <figcaption> only when
 *     a non-empty caption exists; alt always emitted (decorative=='')
 *   - <time datetime> for created / modified / publish_up
 *   - Tags as <ul>, not divs
 *   - Read-more link carries item title via visually-hidden span
 *   - Custom fields delegated to FlexicontentFields::getFieldDisplay()
 *   - User-authored 'html' blocks pass through InputFilter with a
 *     tight whitelist; <h1>, event handlers, script-like content blocked
 */

defined('_JEXEC') or die('Restricted access');

\Joomla\CMS\HTML\HTMLHelper::addIncludePath(JPATH_ROOT . '/components/com_flexicontent/helpers');

class FlexicontentProTemplateRenderer
{
	/** Whitelisted tags for user-authored <html> blocks. <h1> intentionally absent. */
	protected const HTML_BLOCK_TAGS = [
		'p','br','strong','em','b','i','u','s','span','div','a',
		'ul','ol','li','dl','dt','dd',
		'h2','h3','h4','h5','h6',
		'blockquote','cite','code','pre','kbd','samp','var',
		'figure','figcaption','img','picture','source',
		'small','sub','sup','mark','time','abbr',
	];

	/** Whitelisted attributes for user-authored <html> blocks. */
	protected const HTML_BLOCK_ATTRS = [
		'id','class','href','title','src','srcset','sizes',
		'alt','width','height','loading','decoding',
		'lang','dir','datetime','cite','rel','target',
		'aria-label','aria-labelledby','aria-describedby','aria-hidden','role',
	];

	/** Tags valid as heading containers (clamped by clampHeading). */
	protected const HEADING_TAGS = ['h1','h2','h3','h4','h5','h6'];

	/** Tags valid for plain text blocks. */
	protected const TEXT_TAGS = ['p','div','span','small','blockquote','h1','h2','h3','h4','h5','h6'];

	/** Per-render state: heading depth + first-image flag for LCP. */
	protected $headingDepth = 1;
	protected $h1Consumed = false;   // tracks whether the single item-view h1 has been emitted
	protected $firstImageEmitted = false;
	protected $context = 'item';
	protected $item;
	protected $itemFieldCache = []; // field-id => row from #__flexicontent_fields

	/**
	 * Entry point.
	 *
	 * @param  array|object  $layout    Decoded layout_data JSON
	 * @param  object        $item      FLEXIcontent item (must have id, title, etc.)
	 * @param  string        $context   'item' | 'category' | 'module'
	 *
	 * @return string                   Rendered HTML
	 */
	public function render($layout, $item, string $context = 'item'): string
	{
		if (is_string($layout)) {
			$layout = json_decode($layout, true);
		}
		if (!is_array($layout)) {
			return '';
		}

		$this->item              = $item;
		$this->context           = in_array($context, ['item','category','module'], true) ? $context : 'item';
		$this->firstImageEmitted = false;
		$this->h1Consumed        = $this->context !== 'item';   // outside 'item' context, no h1 is ever emitted
		$this->headingDepth      = $this->context === 'item' ? 0 : 2;

		// Prefetch all custom field metadata referenced in this layout in
		// a single query to avoid N+1.
		$this->prefetchFieldMeta($layout);

		$settings = $layout['settings'] ?? [];
		$theme    = $this->escAttr($settings['theme']   ?? 'clean');
		$width    = $this->escAttr($settings['width']   ?? 'default');
		$spacing  = $this->escAttr($settings['spacing'] ?? 'normal');

		$sectionsHtml = '';
		foreach (($layout['sections'] ?? []) as $section) {
			$sectionsHtml .= $this->renderSection($section);
		}

		// Wrap selection:
		//   item     → <article> (single full-page landmark)
		//   category → <article> (per-item teaser in category/mcats list;
		//              valid to nest <article> inside <li>; surfaces item
		//              boundaries to AT without role override)
		//   module   → <div>     (module callers may already sit inside an
		//              <article>; avoid nested-landmark double-announce)
		$contentWrapTag = $this->context === 'module' ? 'div' : 'article';
		$contentWrapAttribs = $this->context === 'item'
			? ' class="fcpt-item" data-item-id="' . (int) ($item->id ?? 0) . '"'
			: ' class="fcpt-item-fragment" data-item-id="' . (int) ($item->id ?? 0) . '"';

		return '<div class="fcpt-layout"'
			. ' data-fcpt-theme="'   . $theme   . '"'
			. ' data-fcpt-width="'   . $width   . '"'
			. ' data-fcpt-spacing="' . $spacing . '">'
			. '<' . $contentWrapTag . $contentWrapAttribs . '>'
			. $sectionsHtml
			. '</' . $contentWrapTag . '>'
			. '</div>';
	}

	// ─────────────────────────────────────────────────────────────────
	// Section / Row / Col
	// ─────────────────────────────────────────────────────────────────

	protected function renderSection(array $section): string
	{
		$appearance = $this->escAttr($section['appearance'] ?? 'plain');
		$class      = $this->escAttr($section['class']      ?? '');
		$sectionId  = $this->escAttr($section['id']         ?? '');

		$rowsHtml = '';
		foreach (($section['rows'] ?? []) as $row) {
			$rowsHtml .= $this->renderRow($row);
		}
		if ($rowsHtml === '') {
			return '';
		}

		return '<section class="fcpt-section fcpt-appearance-' . $appearance . ' ' . $class . '"'
			. ($sectionId ? ' data-fcpt-section-id="' . $sectionId . '"' : '')
			. '><div class="container">' . $rowsHtml . '</div></section>';
	}

	protected function renderRow(array $row): string
	{
		$appearance = $this->escAttr($row['appearance'] ?? 'default');
		$class      = $this->escAttr($row['class']      ?? '');
		$rowId      = $this->escAttr($row['id']         ?? '');

		$colsHtml = '';
		foreach (($row['cols'] ?? []) as $col) {
			$colsHtml .= $this->renderCol($col);
		}
		if ($colsHtml === '') {
			return '';
		}

		return '<div class="row fcpt-row fcpt-appearance-' . $appearance . ' ' . $class . '"'
			. ($rowId ? ' data-fcpt-row-id="' . $rowId . '"' : '')
			. '>' . $colsHtml . '</div>';
	}

	protected function renderCol(array $col): string
	{
		$width      = (int) ($col['width'] ?? 12);
		$width      = max(1, min(12, $width));
		$appearance = $this->escAttr($col['appearance'] ?? 'default');
		$class      = $this->escAttr($col['class']      ?? '');
		$colId      = $this->escAttr($col['id']         ?? '');

		$elementsHtml = '';
		foreach (($col['elements'] ?? []) as $el) {
			$elementsHtml .= $this->renderElement($el);
		}
		if ($elementsHtml === '') {
			return '';
		}

		return '<div class="col-md-' . $width . ' fcpt-col fcpt-appearance-' . $appearance . ' ' . $class . '"'
			. ($colId ? ' data-fcpt-col-id="' . $colId . '"' : '')
			. '>' . $elementsHtml . '</div>';
	}

	// ─────────────────────────────────────────────────────────────────
	// Element dispatcher
	// ─────────────────────────────────────────────────────────────────

	protected function renderElement(array $el): string
	{
		switch ($el['type'] ?? '') {
			case 'article':   return $this->renderArticle($el);
			case 'field':     return $this->renderField($el);
			case 'text':      return $this->renderText($el);
			case 'heading':   return $this->renderHeading($el);
			case 'separator': return '<hr class="fcpt-separator" role="presentation">';
			case 'html':      return $this->renderHtml($el);
		}
		return '';
	}

	// ─────────────────────────────────────────────────────────────────
	// Article blocks (core item properties)
	// ─────────────────────────────────────────────────────────────────

	protected function renderArticle(array $el): string
	{
		$name    = (string) ($el['name']    ?? '');
		$variant = $this->escAttr($el['variant'] ?? 'default');
		$class   = $this->escAttr($el['class']   ?? '');

		switch ($name) {
			case 'title':       return $this->renderTitle($el, $variant, $class);
			case 'introtext':   return $this->renderProperty('introtext', $el);
			case 'fulltext':    return $this->renderProperty('fulltext',  $el);
			case 'image_intro': return $this->renderImage('image_intro', $el);
			case 'image_full':  return $this->renderImage('image_fulltext', $el);
			case 'author':      return $this->renderAuthor($el);
			case 'created':     return $this->renderDate('created',    $el);
			case 'modified':    return $this->renderDate('modified',   $el);
			case 'publish_up':  return $this->renderDate('publish_up', $el);
			case 'category':    return $this->renderCategoryLink($el);
			case 'tags':        return $this->renderTags($el);
			case 'rating':      return $this->renderRating($el);
			case 'hits':        return $this->renderHits($el);
			case 'readmore':    return $this->renderReadmore($el);
		}
		return '';
	}

	protected function renderTitle(array $el, string $variant, string $class): string
	{
		$title = (string) ($this->item->title ?? '');
		if (trim($title) === '') {
			return '';
		}
		$tag = $this->clampHeading('h1');
		return '<' . $tag . ' class="fcpt-title fcpt-el-' . $variant . ' ' . $class . '">'
			. $this->escHtml($title) . '</' . $tag . '>';
	}

	protected function renderProperty(string $prop, array $el): string
	{
		$value = (string) ($this->item->{$prop} ?? '');
		if (trim($value) === '') {
			return '';
		}
		// FLEXIcontent items pre-render introtext/fulltext via content plugins
		// upstream of the renderer; here we trust the HTML.
		$variant = $this->escAttr($el['variant'] ?? 'default');
		return '<div class="fcpt-' . $this->escAttr($prop) . ' fcpt-el-' . $variant . '">' . $value . '</div>';
	}

	protected function renderImage(string $variant, array $el): string
	{
		$images = $this->normalizeImages($this->item->images ?? null);
		$src    = (string) ($images[$variant] ?? '');

		// Fallback: items relying on the FlexicontentFields 'image'
		// custom field don't populate com_content's $item->images JSON.
		// Try extracting the first <img src> from introtext / fulltext
		// so the teaser still shows a thumbnail. Empty content → no image.
		if ($src === '') {
			$src = $this->extractImageFromContent();
		}
		if ($src === '') {
			return '';
		}

		$altKey     = $variant . '_alt';
		$captionKey = $variant . '_caption';
		$widthKey   = $variant . '_width';
		$heightKey  = $variant . '_height';

		// Alt source priority by context:
		//   item     → explicit alt → fallback to item title (avoid empty
		//              alt on a single hero image — title is the only name
		//              source the page provides for the image).
		//   category/module → explicit alt → empty alt (decorative). The
		//              teaser's adjacent <h2 class="fcpt-title"> already
		//              names the card to AT; reusing the title as alt would
		//              double-announce ("Article Title, Article Title").
		$alt = $images[$altKey] ?? null;
		if ($alt === null || $alt === '') {
			$alt = $this->context === 'item'
				? (string) ($this->item->title ?? '')
				: '';
		}
		$caption = trim((string) ($images[$captionKey] ?? ''));
		$width   = isset($images[$widthKey])  ? (int) $images[$widthKey]  : null;
		$height  = isset($images[$heightKey]) ? (int) $images[$heightKey] : null;

		// LCP-aware loading: first image in 'item' context is eager.
		$loading       = (!$this->firstImageEmitted && $this->context === 'item') ? 'eager' : 'lazy';
		$fetchPriority = (!$this->firstImageEmitted && $this->context === 'item') ? ' fetchpriority="high"' : '';
		$this->firstImageEmitted = true;

		$dims = ($width && $height) ? ' width="' . $width . '" height="' . $height . '"' : '';

		$figCaption = $caption !== ''
			? '<figcaption class="fcpt-image-caption">' . $this->escHtml($caption) . '</figcaption>'
			: '';

		$figClass = $variant === 'image_intro' ? 'fcpt-image-intro' : 'fcpt-image-full';

		return '<figure class="' . $figClass . '">'
			. '<img src="' . $this->escAttr($src) . '"'
			. ' alt="' . $this->escAttr($alt) . '"'
			. ' loading="' . $loading . '"' . $fetchPriority
			. ' decoding="async"'
			. $dims
			. '>' . $figCaption . '</figure>';
	}

	protected function renderAuthor(array $el): string
	{
		$author = trim((string) ($this->item->author ?? $this->item->creator ?? ''));
		if ($author === '') {
			return '';
		}
		return '<p class="fcpt-author">'
			. '<span class="fcpt-label">' . $this->escHtml(\Joomla\CMS\Language\Text::_('FLEXI_AUTHOR')) . ':</span> '
			. '<span class="fcpt-value">' . $this->escHtml($author) . '</span>'
			. '</p>';
	}

	protected function renderDate(string $prop, array $el): string
	{
		$raw = (string) ($this->item->{$prop} ?? '');
		if ($raw === '' || strpos($raw, '0000-00-00') === 0) {
			return '';
		}
		try {
			$date = \Joomla\CMS\Factory::getDate($raw);
			$iso  = $date->toISO8601();
			$human = \Joomla\CMS\HTML\HTMLHelper::_('date', $raw, \Joomla\CMS\Language\Text::_('DATE_FORMAT_LC2'));
		} catch (\Throwable $e) {
			return '';
		}
		$cssClass = 'fcpt-' . str_replace('_', '-', $prop);
		return '<time class="' . $cssClass . '" datetime="' . $this->escAttr($iso) . '">'
			. $this->escHtml($human) . '</time>';
	}

	protected function renderCategoryLink(array $el): string
	{
		$title = trim((string) ($this->item->category_title ?? $this->item->cat_title ?? ''));
		$route = (string) ($this->item->category_route ?? $this->item->cat_route ?? '');
		if ($title === '') {
			return '';
		}
		if ($route !== '') {
			return '<a class="fcpt-category" href="' . $this->escAttr($route) . '" rel="category">'
				. $this->escHtml($title) . '</a>';
		}
		return '<span class="fcpt-category">' . $this->escHtml($title) . '</span>';
	}

	protected function renderTags(array $el): string
	{
		$tags = $this->item->tags ?? [];
		if (!is_array($tags) || count($tags) === 0) {
			return '';
		}
		$items = '';
		foreach ($tags as $t) {
			$name = trim((string) ($t->name ?? ($t['name'] ?? '')));
			if ($name === '') continue;
			$link = (string) ($t->link ?? ($t['link'] ?? ''));
			$items .= '<li>'
				. ($link !== ''
					? '<a href="' . $this->escAttr($link) . '" rel="tag">' . $this->escHtml($name) . '</a>'
					: '<span>' . $this->escHtml($name) . '</span>')
				. '</li>';
		}
		return $items !== '' ? '<ul class="fcpt-tags">' . $items . '</ul>' : '';
	}

	protected function renderRating(array $el): string
	{
		$score = (float) ($this->item->rating_score ?? $this->item->score ?? 0);
		$count = (int)   ($this->item->rating_count ?? 0);
		if ($score <= 0 && $count <= 0) {
			return '';
		}
		$label = \Joomla\CMS\Language\Text::sprintf('FLEXI_RATING_SCORE_COUNT', number_format($score, 1), $count);
		// Visual stars (out of 5) generated server-side; hidden from AT.
		$filled  = (int) round($score);
		$filled  = max(0, min(5, $filled));
		$stars   = str_repeat('★', $filled) . str_repeat('☆', 5 - $filled);
		return '<div class="fcpt-rating" role="img" aria-label="' . $this->escAttr($label) . '">'
			. '<span aria-hidden="true">' . $stars . '</span></div>';
	}

	protected function renderHits(array $el): string
	{
		if (!isset($this->item->hits)) {
			return '';
		}
		$hits = (int) $this->item->hits;
		return '<p class="fcpt-hits">'
			. '<span class="fcpt-label">' . $this->escHtml(\Joomla\CMS\Language\Text::_('FLEXI_HITS')) . ':</span> '
			. '<span class="fcpt-value">' . $hits . '</span></p>';
	}

	protected function renderReadmore(array $el): string
	{
		$link = (string) ($this->item->readmore_link ?? $this->item->link ?? '');
		if ($link === '') {
			return '';
		}
		$title = (string) ($this->item->title ?? '');
		$ariaLabel = \Joomla\CMS\Language\Text::sprintf('FLEXI_READ_MORE_ABOUT', $title);
		return '<a class="fcpt-readmore" href="' . $this->escAttr($link) . '"'
			. ' aria-label="' . $this->escAttr($ariaLabel) . '">'
			. '<span aria-hidden="true">' . $this->escHtml(\Joomla\CMS\Language\Text::_('FLEXI_READ_MORE')) . '</span>'
			. '</a>';
	}

	// ─────────────────────────────────────────────────────────────────
	// Custom field block
	// ─────────────────────────────────────────────────────────────────

	protected function renderField(array $el): string
	{
		$fieldId = (int) ($el['id'] ?? 0);
		if ($fieldId <= 0 || empty($this->itemFieldCache[$fieldId])) {
			return '';
		}
		$meta = $this->itemFieldCache[$fieldId];

		// Delegate to FLEXIcontent canonical field display helper.
		if (!class_exists('FlexicontentFields')) {
			\JLoader::register('FlexicontentFields', JPATH_ROOT . '/components/com_flexicontent/classes/flexicontent.fields.php');
		}
		if (!class_exists('FlexicontentFields')) {
			return '';
		}

		$view  = defined('FLEXI_ITEMVIEW') ? FLEXI_ITEMVIEW : 'item';
		$html  = \FlexicontentFields::getFieldDisplay($this->item, $meta->name, null, 'display', $view);
		$html  = is_string($html) ? trim($html) : '';
		if ($html === '') {
			return '';
		}

		$showLabel = !empty($el['showLabel']);
		$variant   = $this->escAttr($el['variant'] ?? 'default');
		$class     = $this->escAttr($el['class']   ?? '');
		$nameAttr  = $this->escAttr($meta->name ?? '');
		$labelId   = 'fcpt-field-' . $fieldId . '-label';

		if ($showLabel && trim((string) ($meta->label ?? '')) !== '') {
			return '<div class="fcpt-field fcpt-field--' . $nameAttr . ' fcpt-el-' . $variant . ' ' . $class . '"'
				. ' data-field-id="' . $fieldId . '">'
				. '<div class="fcpt-field__label" id="' . $labelId . '">'
				. $this->escHtml(\Joomla\CMS\Language\Text::_($meta->label)) . '</div>'
				. '<div class="fcpt-field__value" aria-labelledby="' . $labelId . '">' . $html . '</div>'
				. '</div>';
		}

		return '<div class="fcpt-field fcpt-field--' . $nameAttr . ' fcpt-el-' . $variant . ' ' . $class . '"'
			. ' data-field-id="' . $fieldId . '">'
			. '<div class="fcpt-field__value">' . $html . '</div>'
			. '</div>';
	}

	protected function prefetchFieldMeta(array $layout): void
	{
		$ids = [];
		array_walk_recursive($layout, function ($val, $key) use (&$ids) {
			// We don't have a $parentKey context here, so collect via a
			// secondary walk that knows the structure (see below).
		});
		// Structured walk:
		foreach (($layout['sections'] ?? []) as $section) {
			foreach (($section['rows'] ?? []) as $row) {
				foreach (($row['cols'] ?? []) as $col) {
					foreach (($col['elements'] ?? []) as $el) {
						if (($el['type'] ?? '') === 'field' && !empty($el['id'])) {
							$ids[(int) $el['id']] = true;
						}
					}
				}
			}
		}
		if (!$ids) {
			return;
		}
		$db = \Joomla\CMS\Factory::getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName(['id','name','label','field_type']))
			->from($db->quoteName('#__flexicontent_fields'))
			->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', array_keys($ids))) . ')')
			->where($db->quoteName('published') . ' = 1');
		$rows = $db->setQuery($query)->loadObjectList('id') ?: [];
		$this->itemFieldCache = $rows;
	}

	// ─────────────────────────────────────────────────────────────────
	// Basic blocks
	// ─────────────────────────────────────────────────────────────────

	protected function renderText(array $el): string
	{
		$text = (string) ($el['text'] ?? '');
		if (trim($text) === '') {
			return '';
		}
		$tag = (string) ($el['tag'] ?? 'p');
		if (!in_array($tag, self::TEXT_TAGS, true)) {
			$tag = 'p';
		}
		// If user picked a heading tag in a text block, clamp it.
		if (preg_match('/^h[1-6]$/', $tag)) {
			$tag = $this->clampHeading($tag);
		}
		$variant = $this->escAttr($el['variant'] ?? 'default');
		$class   = $this->escAttr($el['class']   ?? '');
		return '<' . $tag . ' class="fcpt-text fcpt-el-' . $variant . ' ' . $class . '">'
			. $this->escHtml($text) . '</' . $tag . '>';
	}

	protected function renderHeading(array $el): string
	{
		$text = (string) ($el['text'] ?? '');
		if (trim($text) === '') {
			return '';
		}
		$requested = strtolower((string) ($el['level'] ?? 'h3'));
		if (!in_array($requested, self::HEADING_TAGS, true)) {
			$requested = 'h3';
		}
		$tag = $this->clampHeading($requested);
		$variant = $this->escAttr($el['variant'] ?? 'default');
		return '<' . $tag . ' class="fcpt-heading fcpt-el-' . $variant . '">'
			. $this->escHtml($text) . '</' . $tag . '>';
	}

	protected function renderHtml(array $el): string
	{
		$raw = (string) ($el['content'] ?? '');
		if (trim($raw) === '') {
			return '';
		}
		$cleaned = $this->sanitizeHtml($raw);
		if (trim($cleaned) === '') {
			return '';
		}
		return '<div class="fcpt-html">' . $cleaned . '</div>';
	}

	// ─────────────────────────────────────────────────────────────────
	// Helpers
	// ─────────────────────────────────────────────────────────────────

	/**
	 * Enforce heading hierarchy. Updates $this->headingDepth on emit.
	 * Returns the actual tag to use ('h2', 'h3', ...).
	 *
	 * Rule: requested level is clamped down so the heading is never
	 * higher (smaller number) than the current depth. The depth then
	 * becomes the emitted level, so subsequent headings can go deeper
	 * but cannot jump back above the new floor.
	 *
	 * Special: 'h1' is honored exactly once in 'item' context (the item
	 * title). All later 'h1' requests get 'h2' or deeper.
	 */
	protected function clampHeading(string $requested): string
	{
		$reqLevel = (int) substr($requested, 1);
		$reqLevel = max(1, min(6, $reqLevel));

		// In 'item' context, the FIRST h1 request consumes the title slot.
		// All subsequent headings must be h2 or deeper.
		if (!$this->h1Consumed && $reqLevel === 1) {
			$this->h1Consumed   = true;
			$this->headingDepth = 1;
			return 'h1';
		}

		// Floor = max(2, current depth). Never above h2 once h1 used.
		$floor = max(2, $this->headingDepth);
		$out   = max($reqLevel, $floor);
		$out   = max(1, min(6, $out));

		$this->headingDepth = $out;
		return 'h' . $out;
	}

	protected function sanitizeHtml(string $raw): string
	{
		$filter = \Joomla\CMS\Filter\InputFilter::getInstance(
			self::HTML_BLOCK_TAGS,
			self::HTML_BLOCK_ATTRS,
			1, // tagsMethod: ONLY allowed tags
			1, // attrsMethod: ONLY allowed attrs
			1  // xssAuto on
		);
		$cleaned = $filter->clean($raw, 'html');

		// Defence-in-depth: post-process for noopener on target="_blank"
		// and ensure <img> always has alt attribute.
		if (stripos($cleaned, 'target="_blank"') !== false) {
			$cleaned = preg_replace_callback(
				'#<a([^>]*?)>#i',
				static function ($m) {
					$attrs = $m[1];
					if (stripos($attrs, 'target="_blank"') !== false
						&& stripos($attrs, 'rel=') === false) {
						$attrs .= ' rel="noopener"';
					}
					return '<a' . $attrs . '>';
				},
				$cleaned
			);
		}
		// Ensure <img> tags have alt (insert empty if missing — decorative
		// signal; sanitizer cannot guess the intended description).
		$cleaned = preg_replace_callback(
			'#<img(?![^>]*\balt=)([^>]*)>#i',
			static fn($m) => '<img alt=""' . $m[1] . '>',
			$cleaned
		);
		return $cleaned;
	}

	protected function escHtml($v): string
	{
		return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
	}

	protected function escAttr($v): string
	{
		return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Normalize $item->images into an associative array.
	 *
	 * com_content's native images column ships as a JSON string with
	 * keys like image_intro / image_intro_alt / image_fulltext / etc.
	 * Callers occasionally hand it to us pre-decoded as stdClass (e.g.
	 * the image field plugin's onAllFieldsPostDataValidated) or already
	 * cast to an array. Accept all three shapes; return [] for anything
	 * else (null, non-decodable string, scalar).
	 */
	protected function normalizeImages($raw): array
	{
		if (is_array($raw)) {
			return $raw;
		}
		if (is_object($raw)) {
			return (array) $raw;
		}
		if (is_string($raw) && $raw !== '') {
			$decoded = json_decode($raw, true);
			if (is_array($decoded)) {
				return $decoded;
			}
		}
		return [];
	}

	/**
	 * Fallback image source — extract the first <img src> from item
	 * introtext/fulltext. Used when $item->images is empty (typical for
	 * items that store their hero image via a FlexicontentFields custom
	 * 'image' field rather than com_content's native images JSON).
	 *
	 * Returns the raw src URL (resolved by the page; we don't rewrite it)
	 * or '' if no <img> is present. Skips data: URIs to avoid embedding
	 * tracking pixels or oversized inline payloads in teaser cards.
	 */
	protected function extractImageFromContent(): string
	{
		$haystacks = [
			(string) ($this->item->introtext ?? ''),
			(string) ($this->item->fulltext  ?? ''),
		];
		foreach ($haystacks as $html) {
			if ($html === '' || stripos($html, '<img') === false) {
				continue;
			}
			// Scan ALL <img> in the content; skip data: URIs (tracking
			// pixels / inline payloads) and return the first real src.
			if (preg_match_all('#<img[^>]*\bsrc\s*=\s*(["\'])([^"\']+)\1#i', $html, $matches)) {
				foreach ($matches[2] as $src) {
					$src = trim($src);
					if ($src !== '' && stripos($src, 'data:') !== 0) {
						return $src;
					}
				}
			}
		}
		return '';
	}
}
