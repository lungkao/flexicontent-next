<?php
/**
 * @package         FLEXIcontent
 * @subpackage      Pro Templates — Theme Editor (v3 — fieldlayout parity)
 *
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * Modeled on /Users/pisan/Sites/fieldlayout/build/com_fieldlayout/
 * admin/tmpl/theme/edit.php — Modern Presets chip strip + tabbed
 * advanced token panels (colors / typography / links / headings /
 * appearance) + live preview that follows tokens via CSS custom
 * properties.
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var FlexicontentViewProtheme $this */

$document = \Joomla\CMS\Factory::getDocument();
$document->addScript(
	'https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js',
	['version' => 'auto'],
	['defer' => true]
);
$document->addStyleSheet(
	\Joomla\CMS\Uri\Uri::root(true) . '/administrator/components/com_flexicontent/assets/css/protemplate_builder.css',
	['version' => 'auto']
);

$item      = $this->item;
$themeJson = (!empty($item->theme_data)) ? $item->theme_data : '{}';

require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/protemplate/PresetLibrary.php';
$presets = \FlexicontentProTemplatePresetLibrary::getThemePresets();
?>

<form action="<?= Route::_('index.php?option=com_flexicontent&view=prothemes&layout=edit&id=' . (int) ($item->id ?? 0)) ?>"
      method="post" name="adminForm" id="adminForm">

	<input type="hidden" name="jform[id]"         value="<?= (int) ($item->id ?? 0) ?>">
	<input type="hidden" name="jform[theme_data]" id="fcpt_theme_data_input" value="">
	<?= HTMLHelper::_('form.token') ?>

	<div class="fcpt-details-card mb-3">
		<div class="fcpt-details-intro">
			<span class="fcpt-setup-icon" aria-hidden="true">🎨</span>
			<div>
				<p class="fcpt-kicker mb-1"><?= Text::_('FLEXI_PROTHEME_SETUP') ?></p>
				<h3><?= Text::_('FLEXI_PROTHEME_EDITOR_TITLE') ?></h3>
				<p><?= Text::_('FLEXI_PROTHEME_EDITOR_SUBTITLE') ?></p>
			</div>
		</div>
		<div class="fcpt-detail-field is-title">
			<?= $this->form->renderField('title') ?>
		</div>
		<div class="fcpt-details-side">
			<div class="fcpt-detail-field"><?= $this->form->renderField('state') ?></div>
			<div class="fcpt-detail-field"><?= $this->form->renderField('ordering') ?></div>
		</div>
	</div>

	<div id="fcpt-theme-editor"
	     x-data="fcptThemeEditor()"
	     x-init="init()"
	     class="fcpt-theme-editor">

		<!-- ── Modern Presets ────────────────────────────────────── -->
		<div class="fcpt-theme-panel">
			<div class="fcpt-theme-panel-head">
				<h4 class="fcpt-kicker">Modern Presets</h4>
				<p class="fcpt-help" style="margin:0;font-size:.85rem;opacity:.7">
					One-click apply a preset palette + typography. Deep-merges into your current customisations.
				</p>
			</div>
			<div class="fcpt-preset-grid" role="list" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:.75rem;margin-top:.75rem">
				<template x-for="p in presets" :key="p.key">
					<button type="button"
					        role="listitem"
					        class="fcpt-preset-card"
					        @click="applyPreset(p)"
					        :style="{
					            background: p.colors.surface,
					            color: p.colors.text,
					            border: '1px solid ' + (p.colors.border || '#e5e7eb'),
					            padding: '.75rem',
					            borderRadius: '.5rem',
					            textAlign: 'left',
					            cursor: 'pointer',
					            display: 'flex',
					            flexDirection: 'column',
					            gap: '.35rem'
					        }">
						<span style="display:flex;gap:.25rem">
							<span :style="'width:18px;height:18px;border-radius:4px;background:'+p.colors.accent"></span>
							<span :style="'width:18px;height:18px;border-radius:4px;background:'+(p.colors.accent_grad||p.colors.accent)"></span>
						</span>
						<strong style="font-size:.95rem" x-text="p.title"></strong>
						<small style="font-size:.7rem;opacity:.65" x-text="p.font"></small>
					</button>
				</template>
			</div>
		</div>

		<!-- ── Tabbed control panel ──────────────────────────────── -->
		<div class="fcpt-theme-tabs" role="tablist" style="display:flex;gap:.25rem;margin-top:1rem;border-bottom:1px solid #e5e7eb">
			<template x-for="tab in tabs" :key="tab.key">
				<button type="button"
				        role="tab"
				        :aria-selected="activeTab === tab.key"
				        :class="{ 'is-active': activeTab === tab.key }"
				        @click="activeTab = tab.key"
				        :style="{
				            padding: '.5rem 1rem',
				            border: 'none',
				            borderBottom: activeTab === tab.key ? '2px solid #2563eb' : '2px solid transparent',
				            background: 'transparent',
				            fontWeight: activeTab === tab.key ? '600' : '400',
				            cursor: 'pointer'
				        }"
				        x-text="tab.label"></button>
			</template>
		</div>

		<div class="fcpt-theme-editor-cols" style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-top:1rem">

			<div class="fcpt-theme-controls">

				<!-- Colors tab -->
				<div x-show="activeTab === 'colors'">
					<div class="fcpt-theme-row"><label>Accent</label>
						<input type="color" x-model="theme.colors.accent">
						<input type="text"  x-model="theme.colors.accent" class="form-control form-control-sm">
					</div>
					<div class="fcpt-theme-row"><label>Accent gradient</label>
						<input type="text" x-model="theme.colors.accent_grad" class="form-control form-control-sm" placeholder="linear-gradient(135deg, #x 0%, #y 100%)">
					</div>
					<div class="fcpt-theme-row"><label>Text</label>
						<input type="color" x-model="theme.colors.text">
						<input type="text"  x-model="theme.colors.text" class="form-control form-control-sm">
					</div>
					<div class="fcpt-theme-row"><label>Text muted</label>
						<input type="color" x-model="theme.colors.text_muted">
						<input type="text"  x-model="theme.colors.text_muted" class="form-control form-control-sm">
					</div>
					<div class="fcpt-theme-row"><label>Surface</label>
						<input type="color" x-model="theme.colors.surface">
						<input type="text"  x-model="theme.colors.surface" class="form-control form-control-sm">
					</div>
					<div class="fcpt-theme-row"><label>Surface elev</label>
						<input type="color" x-model="theme.colors.surface_alt">
						<input type="text"  x-model="theme.colors.surface_alt" class="form-control form-control-sm">
					</div>
					<div class="fcpt-theme-row"><label>Border</label>
						<input type="color" x-model="theme.colors.border">
						<input type="text"  x-model="theme.colors.border" class="form-control form-control-sm">
					</div>
					<div class="fcpt-theme-row"><label>Focus ring</label>
						<input type="color" x-model="theme.colors.focus_ring">
						<input type="text"  x-model="theme.colors.focus_ring" class="form-control form-control-sm">
					</div>
				</div>

				<!-- Typography tab -->
				<div x-show="activeTab === 'typography'">
					<div class="fcpt-theme-row"><label>Body font</label>
						<input type="text" x-model="theme.typography.family" class="form-control form-control-sm" placeholder="Inter, system-ui, sans-serif">
					</div>
					<div class="fcpt-theme-row"><label>Heading font</label>
						<input type="text" x-model="theme.typography.family_heading" class="form-control form-control-sm" placeholder="Same as body">
					</div>
					<div class="fcpt-theme-row"><label>Body line-height</label>
						<input type="number" step="0.05" min="1" max="2.5" x-model.number="theme.typography.line_height" class="form-control form-control-sm">
					</div>
					<div class="fcpt-theme-row"><label>Scale</label>
						<input type="number" step="0.05" min="0.8" max="1.4" x-model.number="theme.typography.scale" class="form-control form-control-sm">
					</div>
				</div>

				<!-- Links tab -->
				<div x-show="activeTab === 'links'">
					<div class="fcpt-theme-row"><label>Link color</label>
						<input type="color" x-model="theme.link.color">
						<input type="text"  x-model="theme.link.color" class="form-control form-control-sm">
					</div>
					<div class="fcpt-theme-row"><label>Link decoration</label>
						<select x-model="theme.link.textDecoration" class="form-select form-select-sm">
							<option value="none">none</option>
							<option value="underline">underline</option>
							<option value="underline dotted">underline dotted</option>
						</select>
					</div>
					<div class="fcpt-theme-row"><label>Hover color</label>
						<input type="color" x-model="theme.link.hoverColor">
						<input type="text"  x-model="theme.link.hoverColor" class="form-control form-control-sm">
					</div>
					<div class="fcpt-theme-row"><label>Hover decoration</label>
						<select x-model="theme.link.hoverTextDecoration" class="form-select form-select-sm">
							<option value="none">none</option>
							<option value="underline">underline</option>
						</select>
					</div>
				</div>

				<!-- Headings tab -->
				<div x-show="activeTab === 'headings'">
					<div class="fcpt-theme-row"><label>Heading color</label>
						<input type="color" x-model="theme.heading.color">
						<input type="text"  x-model="theme.heading.color" class="form-control form-control-sm">
					</div>
					<div class="fcpt-theme-row"><label>Heading weight</label>
						<select x-model="theme.heading.fontWeight" class="form-select form-select-sm">
							<option value="400">400 Normal</option>
							<option value="500">500 Medium</option>
							<option value="600">600 Semi-bold</option>
							<option value="700">700 Bold</option>
							<option value="800">800 Extra-bold</option>
							<option value="900">900 Black</option>
						</select>
					</div>
					<div class="fcpt-theme-row"><label>Letter spacing</label>
						<input type="text" x-model="theme.heading.letterSpacing" class="form-control form-control-sm" placeholder="-0.015em">
					</div>
					<div class="fcpt-theme-row"><label>Text transform</label>
						<select x-model="theme.heading.textTransform" class="form-select form-select-sm">
							<option value="none">none</option>
							<option value="uppercase">uppercase</option>
							<option value="capitalize">capitalize</option>
						</select>
					</div>
				</div>

				<!-- Appearance tab -->
				<div x-show="activeTab === 'appearance'">
					<div class="fcpt-theme-row"><label>Background gradient</label>
						<select x-model="theme.appearance.gradientPreset" class="form-select form-select-sm">
							<option value="none">None (solid surface)</option>
							<option value="aurora">Aurora — cyan→violet→pink</option>
							<option value="sunset">Sunset — orange→rose→purple</option>
							<option value="ocean">Ocean — cyan→teal→blue</option>
							<option value="meadow">Meadow — green→teal</option>
							<option value="dusk">Dusk — indigo→purple</option>
						</select>
					</div>
					<div class="fcpt-theme-row"><label>Background animation</label>
						<select x-model="theme.appearance.animation" class="form-select form-select-sm">
							<option value="none">None (static)</option>
							<option value="drift">Slow drift (16s)</option>
							<option value="breathe">Breathe (8s)</option>
						</select>
					</div>
					<div class="fcpt-theme-row"><label>Surface style</label>
						<select x-model="theme.appearance.surfaceStyle" class="form-select form-select-sm">
							<option value="solid">Solid card</option>
							<option value="glass">Glassmorphism (backdrop-filter blur)</option>
							<option value="outline">Outline only (transparent fill)</option>
						</select>
					</div>
					<div class="fcpt-theme-row"><label>Card radius</label>
						<select x-model="theme.radius" class="form-select form-select-sm">
							<option value="sm">Small (4px)</option>
							<option value="md">Medium (8px)</option>
							<option value="lg">Large (16px)</option>
							<option value="xl">Extra large (24px)</option>
							<option value="pill">Pill (999px)</option>
						</select>
					</div>
				</div>
			</div>

			<!-- Live Preview pane ────────────────────────────────── -->
			<div class="fcpt-theme-preview" :style="previewStyle()">
				<div :style="previewBgStyle()" style="padding:2rem;border-radius:.5rem;border:1px solid rgba(0,0,0,.05)">
					<p style="font-size:0.7rem;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.5rem;opacity:.6">
						Live preview
					</p>
					<h2 :style="headingStyle()">Article Title</h2>
					<p :style="bodyStyle()">
						This is the intro text. Colors, fonts, and links are all synced from this theme to the frontend.
					</p>
					<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin:1rem 0">
						<span :style="'background:' + (theme.colors.accent_grad || theme.colors.accent) + ';color:#fff;padding:.25rem .75rem;border-radius:1rem;font-size:.8rem'">Tag</span>
						<span :style="'background:' + (theme.colors.surface_alt || theme.colors.surface) + ';color:' + (theme.colors.text_muted || theme.colors.text) + ';padding:.25rem .75rem;border-radius:1rem;font-size:.8rem;border:1px solid ' + (theme.colors.border)">Category</span>
					</div>
					<a href="#" :style="linkStyle()" @click.prevent>Read more →</a>
				</div>
			</div>
		</div>

		<details class="mt-3">
			<summary class="text-muted" style="cursor:pointer;font-size:.85rem">JSON debug</summary>
			<pre class="mt-2 p-2 bg-light rounded" style="font-size:.75rem;max-height:240px;overflow:auto" x-text="JSON.stringify(theme, null, 2)"></pre>
		</details>
	</div>

	<input type="hidden" name="task" value="">
</form>

<script>
function fcptThemeEditor() {
	const defaults = {
		colors: {
			accent: '#2563eb',
			accent_grad: '',
			text: '#0f172a',
			text_muted: '#475569',
			surface: '#ffffff',
			surface_alt: '#f8fafc',
			border: '#e2e8f0',
			focus_ring: '#2563eb'
		},
		typography: {
			family: 'Inter, system-ui, sans-serif',
			family_heading: '',
			scale: 1.0,
			line_height: 1.6
		},
		link: {
			color: '#2563eb',
			textDecoration: 'none',
			hoverColor: '#1d4ed8',
			hoverTextDecoration: 'underline'
		},
		heading: {
			color: '#0f172a',
			fontWeight: '700',
			letterSpacing: '-0.015em',
			textTransform: 'none'
		},
		appearance: {
			gradientPreset: 'none',
			animation: 'none',
			surfaceStyle: 'solid'
		},
		radius: 'md',
		mode: 'light'
	};

	const stored = <?= json_encode(json_decode($themeJson, true) ?: [], JSON_UNESCAPED_UNICODE) ?>;

	const presets = <?= json_encode(array_map(function ($p) {
		$colors = $p['theme_data']['colors'] ?? [];
		$font   = $p['theme_data']['typography']['family'] ?? '';
		return [
			'key'         => $p['key'],
			'title'       => $p['key'],
			'font'        => preg_replace('/,.*$/', '', (string) $font),
			'colors'      => $colors,
			'theme_data'  => $p['theme_data'],
		];
	}, $presets), JSON_UNESCAPED_UNICODE) ?>;

	function deepMerge(a, b) {
		const out = Object.assign({}, a);
		for (const k of Object.keys(b || {})) {
			if (b[k] && typeof b[k] === 'object' && !Array.isArray(b[k])) {
				out[k] = deepMerge(a[k] || {}, b[k]);
			} else if (b[k] !== undefined && b[k] !== null && b[k] !== '') {
				out[k] = b[k];
			}
		}
		return out;
	}

	const GRADIENTS = {
		aurora:  'linear-gradient(135deg, #06b6d4 0%, #8b5cf6 50%, #ec4899 100%)',
		sunset:  'linear-gradient(135deg, #fb923c 0%, #f43f5e 60%, #a855f7 100%)',
		ocean:   'linear-gradient(135deg, #06b6d4 0%, #0e7490 50%, #1e40af 100%)',
		meadow:  'linear-gradient(135deg, #16a34a 0%, #0d9488 100%)',
		dusk:    'linear-gradient(135deg, #4338ca 0%, #7c3aed 100%)'
	};

	return {
		theme: deepMerge(defaults, stored),
		presets: presets,
		activeTab: 'colors',
		tabs: [
			{ key: 'colors',      label: 'Colors' },
			{ key: 'typography',  label: 'Typography' },
			{ key: 'links',       label: 'Links' },
			{ key: 'headings',    label: 'Headings' },
			{ key: 'appearance',  label: 'Appearance' }
		],

		init() {
			const form = document.getElementById('adminForm');
			if (form) {
				form.addEventListener('submit', () => {
					document.getElementById('fcpt_theme_data_input').value = JSON.stringify(this.theme);
				});
			}
		},

		applyPreset(p) {
			this.theme = deepMerge(this.theme, p.theme_data);
		},

		previewStyle() {
			return {
				background: this.theme.colors.surface,
				borderColor: this.theme.colors.border,
				fontFamily: this.theme.typography.family || 'inherit',
				color: this.theme.colors.text,
				padding: '1rem',
				borderRadius: '.5rem',
				border: '1px solid ' + this.theme.colors.border
			};
		},
		previewBgStyle() {
			const grad = GRADIENTS[this.theme.appearance.gradientPreset];
			if (!grad) return { background: this.theme.colors.surface };
			return {
				backgroundImage: grad,
				backgroundSize: this.theme.appearance.animation === 'none' ? 'auto' : '220% 220%',
				animation: this.theme.appearance.animation === 'drift'
					? 'fcptPreviewDrift 16s ease-in-out infinite alternate'
					: (this.theme.appearance.animation === 'breathe' ? 'fcptPreviewDrift 8s ease-in-out infinite alternate' : 'none')
			};
		},
		headingStyle() {
			return [
				'font-family:' + (this.theme.typography.family_heading || this.theme.typography.family || 'inherit'),
				'color:' + (this.theme.heading.color || this.theme.colors.text),
				'font-weight:' + (this.theme.heading.fontWeight || '700'),
				'letter-spacing:' + (this.theme.heading.letterSpacing || ''),
				'text-transform:' + (this.theme.heading.textTransform || 'none')
			].join(';');
		},
		bodyStyle() {
			return [
				'color:' + (this.theme.colors.text || '#0f172a'),
				'font-family:' + (this.theme.typography.family || 'inherit'),
				'line-height:' + (this.theme.typography.line_height || 1.6)
			].join(';');
		},
		linkStyle() {
			return [
				'color:' + (this.theme.link.color || this.theme.colors.accent),
				'text-decoration:' + (this.theme.link.textDecoration || 'none')
			].join(';');
		}
	};
}
</script>

<style>
@keyframes fcptPreviewDrift {
	0%   { background-position:   0%   0%; }
	100% { background-position: 100% 100%; }
}
.fcpt-theme-row { display: grid; grid-template-columns: 130px 40px 1fr; gap: .5rem; align-items: center; margin-bottom: .5rem; }
.fcpt-theme-row label { font-size: .85rem; font-weight: 500; }
.fcpt-preset-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px -4px rgba(0,0,0,.15); }
.fcpt-preset-card { transition: transform 150ms ease, box-shadow 150ms ease; }
@media (prefers-reduced-motion: reduce) {
	.fcpt-preset-card:hover { transform: none; }
}
</style>
