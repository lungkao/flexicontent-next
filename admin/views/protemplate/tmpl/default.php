<?php
/**
 * @package         FLEXIcontent
 * @subpackage      Pro Templates — Builder (drag-and-drop layout editor)
 *
 * @author          FLEXIcontent Team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var FlexicontentViewProtemplate $this */

$document = \Joomla\CMS\Factory::getDocument();

// Alpine.js (deferred)
$document->addScript(
	'https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js',
	['version' => 'auto'],
	['defer' => true]
);

// Builder CSS
$document->addStyleSheet(
	\Joomla\CMS\Uri\Uri::root(true) . '/administrator/components/com_flexicontent/assets/css/protemplate_builder.css',
	['version' => 'auto']
);

$item       = $this->item;
$layoutJson = (!empty($item->layout_data)) ? $item->layout_data : '{"version":2,"sections":[]}';
$typeId     = (int) ($item->type_id ?? 0);

$fieldsJson = json_encode(array_map(static fn($f) => [
	'id'    => (int)    $f->id,
	'label' => (string) $f->label,
	'type'  => (string) $f->type,
], $this->fields ?? []), JSON_UNESCAPED_UNICODE);

$themesJson = json_encode(array_map(static fn($t) => [
	'id'    => (int)    $t->id,
	'title' => (string) $t->title,
	'data'  => json_decode($t->theme_data ?: '{}', true) ?: [],
], $this->themes ?? []), JSON_UNESCAPED_UNICODE);

// FLEXIcontent core article elements
$coreElementsJson = json_encode([
	['name' => 'title',         'label' => Text::_('JGLOBAL_TITLE'),                 'tag' => 'h2'],
	['name' => 'introtext',     'label' => Text::_('FLEXI_INTROTEXT'),               'tag' => 'div'],
	['name' => 'fulltext',      'label' => Text::_('FLEXI_FULLTEXT'),                'tag' => 'div'],
	['name' => 'image_intro',   'label' => Text::_('FLEXI_IMAGE_INTRO'),             'tag' => 'figure'],
	['name' => 'image_full',    'label' => Text::_('FLEXI_IMAGE_FULL'),              'tag' => 'figure'],
	['name' => 'author',        'label' => Text::_('FLEXI_AUTHOR'),                  'tag' => 'span'],
	['name' => 'created',       'label' => Text::_('FLEXI_CREATED_DATE'),            'tag' => 'time'],
	['name' => 'modified',      'label' => Text::_('FLEXI_LAST_MODIFIED'),           'tag' => 'time'],
	['name' => 'publish_up',    'label' => Text::_('FLEXI_PUBLISH_DATE'),            'tag' => 'time'],
	['name' => 'category',      'label' => Text::_('FLEXI_CATEGORY'),                'tag' => 'span'],
	['name' => 'tags',          'label' => Text::_('JTAG'),                          'tag' => 'div'],
	['name' => 'rating',        'label' => Text::_('FLEXI_RATING'),                  'tag' => 'div'],
	['name' => 'hits',          'label' => Text::_('FLEXI_ITEM_HITS'),               'tag' => 'span'],
	['name' => 'readmore',      'label' => Text::_('FLEXI_READMORE_LINK'),           'tag' => 'a'],
], JSON_UNESCAPED_UNICODE);
?>

<form action="<?= Route::_('index.php?option=com_flexicontent&view=protemplates&layout=edit&id=' . (int) ($item->id ?? 0)) ?>"
      method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">

	<input type="hidden" name="jform[id]"          value="<?= (int) ($item->id ?? 0) ?>">
	<input type="hidden" name="jform[layout_data]" id="fcpt_layout_data_input" value="">
	<?= HTMLHelper::_('form.token') ?>

	<!-- ── Details card ─────────────────────────────────────────── -->
	<div class="fcpt-details-card">
		<div class="fcpt-details-intro">
			<span class="fcpt-setup-icon" aria-hidden="true">⭐</span>
			<div>
				<p class="fcpt-kicker mb-1"><?= Text::_('FLEXI_PROTEMPLATE_SETUP') ?></p>
				<h3><?= Text::_('FLEXI_PROTEMPLATE_BUILDER_TITLE') ?></h3>
				<p><?= Text::_('FLEXI_PROTEMPLATE_BUILDER_SUBTITLE') ?></p>
			</div>
		</div>

		<div class="fcpt-detail-field is-title">
			<?= $this->form->renderField('title') ?>
		</div>
		<div class="fcpt-details-side">
			<div class="fcpt-detail-field"><?= $this->form->renderField('type_id') ?></div>
			<div class="fcpt-detail-field"><?= $this->form->renderField('catid') ?></div>
			<?php
			// Hidden routing columns — UI doesn't expose assignment_type /
			// assignment_value / view_scope; they are auto-derived by the
			// model on save based on catid + type_id (scope is fixed by
			// the chooser at create time). Rendering the hidden inputs
			// ensures the form submission carries them through validation.
			?>
			<?= $this->form->getInput('view_scope') ?>
			<?= $this->form->getInput('assignment_type') ?>
			<?= $this->form->getInput('assignment_value') ?>
			<div class="fcpt-detail-field"><?= $this->form->renderField('state') ?></div>
			<?= $this->form->getInput('ordering') ?>
			<div class="fcpt-detail-field is-wide"><?= $this->form->renderField('note') ?></div>
		</div>
	</div>

	<!-- ── Builder App (Alpine.js) ───────────────────────────────── -->
	<div id="fcpt-builder"
	     x-data="fcptBuilder()"
	     x-init="init()"
	     x-cloak
	     class="fcpt-builder-app">

		<!-- Sidebar ──────────────────────────────────────── -->
		<aside class="fcpt-sidebar">
			<div class="fcpt-toolbox">
				<p class="fcpt-kicker"><?= Text::_('FLEXI_PROTEMPLATE_STRUCTURE') ?></p>
				<button type="button" class="btn btn-primary w-100 mb-2" @click="addSection()">
					+ <?= Text::_('FLEXI_PROTEMPLATE_ADD_SECTION') ?>
				</button>
				<div class="fcpt-row-tools">
					<select class="form-select form-select-sm" x-model.number="rowPreset">
						<option value="1">1 <?= Text::_('FLEXI_PROTEMPLATE_COLUMN') ?></option>
						<option value="2">2 <?= Text::_('FLEXI_PROTEMPLATE_COLUMNS') ?></option>
						<option value="3">3 <?= Text::_('FLEXI_PROTEMPLATE_COLUMNS') ?></option>
						<option value="4">4 <?= Text::_('FLEXI_PROTEMPLATE_COLUMNS') ?></option>
					</select>
					<button type="button" class="btn btn-sm btn-outline-primary" @click="addRowToSelectedSection()">
						+ <?= Text::_('FLEXI_PROTEMPLATE_ADD_ROW') ?>
					</button>
				</div>
			</div>

			<div class="fcpt-toolbox">
				<p class="fcpt-kicker"><?= Text::_('FLEXI_PROTEMPLATE_STYLE') ?></p>

				<label class="fcpt-mini-label"><?= Text::_('FLEXI_PROTEMPLATE_THEME_PRESET') ?></label>
				<select class="form-select form-select-sm mb-2" x-model="layout.settings.theme">
					<option value="clean">Clean</option>
					<option value="editorial">Editorial</option>
					<option value="soft">Soft Card</option>
					<option value="contrast">Contrast</option>
				</select>

				<label class="fcpt-mini-label"><?= Text::_('FLEXI_PROTEMPLATE_CUSTOM_THEME') ?></label>
				<select class="form-select form-select-sm mb-2" x-model.number="layout.settings.themeId">
					<option value="0"><?= Text::_('FLEXI_PROTEMPLATE_NO_THEME') ?></option>
					<template x-for="theme in availableThemes" :key="theme.id">
						<option :value="theme.id" x-text="theme.title"></option>
					</template>
				</select>

				<template x-if="selectedTheme()">
					<div class="fcpt-selected-theme-card" :style="selectedThemePreviewStyle()">
						<div>
							<strong x-text="selectedTheme().title"></strong>
							<span><?= Text::_('FLEXI_PROTEMPLATE_THEME_SYNCED') ?></span>
						</div>
						<a class="btn btn-sm btn-light"
						   href="<?= Route::_('index.php?option=com_flexicontent&view=prothemes') ?>">
							<?= Text::_('FLEXI_PROTEMPLATE_EDIT_THEMES') ?>
						</a>
					</div>
				</template>

				<label class="fcpt-mini-label mt-2"><?= Text::_('FLEXI_PROTEMPLATE_WIDTH') ?></label>
				<select class="form-select form-select-sm mb-2" x-model="layout.settings.width">
					<option value="default"><?= Text::_('FLEXI_PROTEMPLATE_WIDTH_DEFAULT') ?></option>
					<option value="narrow"><?= Text::_('FLEXI_PROTEMPLATE_WIDTH_NARROW') ?></option>
					<option value="wide"><?= Text::_('FLEXI_PROTEMPLATE_WIDTH_WIDE') ?></option>
					<option value="full"><?= Text::_('FLEXI_PROTEMPLATE_WIDTH_FULL') ?></option>
				</select>

				<label class="fcpt-mini-label"><?= Text::_('FLEXI_PROTEMPLATE_SPACING') ?></label>
				<select class="form-select form-select-sm mb-2" x-model="layout.settings.spacing">
					<option value="compact"><?= Text::_('FLEXI_PROTEMPLATE_SPACING_COMPACT') ?></option>
					<option value="normal"><?= Text::_('FLEXI_PROTEMPLATE_SPACING_NORMAL') ?></option>
					<option value="airy"><?= Text::_('FLEXI_PROTEMPLATE_SPACING_AIRY') ?></option>
				</select>
			</div>
		</aside>

		<!-- Canvas ───────────────────────────────────────── -->
		<main class="fcpt-canvas">
			<div class="fcpt-canvas-head">
				<div>
					<p class="fcpt-kicker mb-1"><?= Text::_('FLEXI_PROTEMPLATE_CANVAS') ?></p>
					<strong x-text="layout.sections.length + ' section(s)'"></strong>
				</div>
				<button type="button" class="btn btn-sm btn-outline-secondary" @click="previewMode = !previewMode">
					<span x-show="!previewMode"><?= Text::_('FLEXI_PROTEMPLATE_PREVIEW') ?></span>
					<span x-show="previewMode"><?= Text::_('FLEXI_PROTEMPLATE_EDIT_MODE') ?></span>
				</button>
				<span class="fcpt-autosave-status" x-text="autosave.status"></span>
			</div>

			<div x-show="layout.sections.length === 0" class="fcpt-empty-state">
				<h3><?= Text::_('FLEXI_PROTEMPLATE_BUILDER_EMPTY') ?></h3>
				<button type="button" @click="addSection()" class="btn btn-primary">
					+ <?= Text::_('FLEXI_PROTEMPLATE_ADD_SECTION') ?>
				</button>
			</div>

			<div x-show="previewMode" class="fcpt-admin-preview">
				<div x-html="previewHtml()"></div>
			</div>

			<!-- Sections ──────────────────────────────────── -->
			<template x-for="(section, sectionIndex) in layout.sections" :key="section.id">
				<section class="builder-section"
				         x-show="!previewMode"
				         :class="{ 'is-selected': selected.section === sectionIndex && selected.row === null }"
				         draggable="true"
				         @dragstart.stop="onDragStartSection($event, sectionIndex)"
				         @dragover.prevent
				         @drop.stop.prevent="onDropSection($event, sectionIndex)"
				         @dragend="clearDrag()"
				         @click.stop="selectSection(sectionIndex)">

					<div class="builder-section-head">
						<div>
							<span class="badge bg-dark">Section <span x-text="sectionIndex + 1"></span></span>
							<input type="text" class="form-control form-control-sm mt-2"
							       x-model="section.label" placeholder="Section label">
						</div>
						<div class="builder-section-actions">
							<select class="form-select form-select-sm" x-model="section.appearance" @click.stop>
								<option value="plain">Plain</option>
								<option value="card">Card</option>
								<option value="band">Band</option>
								<option value="hero">Hero</option>
								<option value="glass">Glass</option>
								<option value="bento">Bento</option>
								<option value="feature">Feature Split</option>
								<option value="spotlight">Spotlight</option>
								<option value="editorial">Editorial</option>
							</select>
							<div class="btn-group btn-group-sm">
								<button type="button" class="btn btn-outline-secondary"
								        :disabled="sectionIndex === 0"
								        @click.stop="moveSectionUp(sectionIndex)" title="Up">↑</button>
								<button type="button" class="btn btn-outline-secondary"
								        :disabled="sectionIndex === layout.sections.length - 1"
								        @click.stop="moveSectionDown(sectionIndex)" title="Down">↓</button>
								<button type="button" class="btn btn-outline-primary"
								        @click.stop="addRow(section)">+ Row</button>
								<button type="button" class="btn btn-outline-danger"
								        @click.stop="removeSection(sectionIndex)">×</button>
							</div>
						</div>
					</div>

					<!-- Rows ──────────────────────────────── -->
					<template x-for="(row, rowIndex) in section.rows" :key="row.id">
						<div class="builder-row"
						     :class="{ 'is-selected': selected.section === sectionIndex && selected.row === rowIndex && selected.col === null }"
						     draggable="true"
						     @dragstart.stop="onDragStartRow($event, sectionIndex, rowIndex)"
						     @dragover.prevent
						     @drop.stop.prevent="onDropRow($event, sectionIndex, rowIndex)"
						     @dragend="clearDrag()"
						     @click.stop="selectRow(sectionIndex, rowIndex)">

							<div class="builder-row-head">
								<span>Row <span x-text="rowIndex + 1"></span></span>
								<div class="btn-group btn-group-sm">
									<select x-model="row.appearance" class="form-select form-select-sm"
									        style="max-width:130px" @click.stop>
										<option value="default">Default</option>
										<option value="bento">Bento</option>
										<option value="media">Media</option>
										<option value="timeline">Timeline</option>
										<option value="stacked">Stacked</option>
										<option value="compact">Compact</option>
									</select>
									<button type="button" @click.stop="setColPreset(row,[12])"
									        class="btn btn-outline-secondary">1</button>
									<button type="button" @click.stop="setColPreset(row,[6,6])"
									        class="btn btn-outline-secondary">2</button>
									<button type="button" @click.stop="setColPreset(row,[4,4,4])"
									        class="btn btn-outline-secondary">3</button>
									<button type="button" @click.stop="setColPreset(row,[3,3,3,3])"
									        class="btn btn-outline-secondary">4</button>
									<button type="button" @click.stop="removeRow(section, rowIndex)"
									        class="btn btn-outline-danger">×</button>
								</div>
							</div>

							<!-- Columns ───────────────────── -->
							<div class="row g-3">
								<template x-for="(col, colIndex) in row.cols" :key="col.id">
									<div :class="'col-md-' + col.width">
										<div class="builder-col"
										     :class="{ 'is-selected': selected.section === sectionIndex && selected.row === rowIndex && selected.col === colIndex }"
										     @click.stop="selectColumn(sectionIndex, rowIndex, colIndex)"
										     @dragover.prevent
										     @drop.stop.prevent="onDropColumn($event, sectionIndex, rowIndex, colIndex)">

											<div class="col-header">
												<select x-model.number="col.width" class="form-select form-select-sm">
													<template x-for="n in [2,3,4,5,6,7,8,9,10,12]" :key="n">
														<option :value="n" x-text="n + '/12'"></option>
													</template>
												</select>
												<div class="btn-group btn-group-sm">
													<button type="button" class="btn btn-outline-primary"
													        @click.stop="openElementPicker(sectionIndex, rowIndex, colIndex)">
														+ Element
													</button>
													<button type="button" class="btn btn-outline-danger"
													        @click.stop="removeColumn(row, colIndex)"
													        x-show="row.cols.length > 1">×</button>
												</div>
											</div>

											<!-- Elements ──────────────── -->
											<template x-for="(el, elIndex) in col.elements" :key="el._uid">
												<div class="element-card"
												     :class="{ 'is-dragging': isDraggingElement(sectionIndex, rowIndex, colIndex, elIndex) }"
												     draggable="true"
												     @dragstart.stop="onDragStartElement($event, sectionIndex, rowIndex, colIndex, elIndex)"
												     @dragover.prevent
												     @drop.stop.prevent="onDropElement($event, sectionIndex, rowIndex, colIndex, elIndex)"
												     @dragend="clearDrag()">
													<div class="element-card-head">
														<button type="button" class="element-drag-handle"
														        draggable="true"
														        @dragstart.stop="onDragStartElement($event, sectionIndex, rowIndex, colIndex, elIndex)"
														        title="Drag">↕</button>
														<span class="element-type" x-text="getElementTypeLabel(el)"></span>
														<button type="button" class="btn btn-xs btn-link text-danger"
														        @click.stop="removeElement(col, elIndex)">Remove</button>
													</div>

													<!-- FLEXIcontent Field -->
													<template x-if="el.type === 'field'">
														<div class="el-field">
															<div class="el-body">
																<strong x-text="getFieldLabel(el.id)"></strong>
																<label class="form-check form-check-inline ms-2 small">
																	<input type="checkbox" x-model="el.showLabel" class="form-check-input">
																	Show label
																</label>
																<select x-model="el.variant" class="form-select form-select-sm mt-2" style="max-width:150px">
																	<option value="default">Default</option>
																	<option value="card">Card</option>
																	<option value="glass">Glass</option>
																	<option value="pill">Pill</option>
																	<option value="stat">Stat</option>
																	<option value="callout">Callout</option>
																	<option value="muted">Muted</option>
																</select>
																<input type="text" x-model="el.class" class="form-control form-control-sm mt-2" placeholder="CSS class">
															</div>
														</div>
													</template>

													<!-- Article/Item element -->
													<template x-if="el.type === 'article'">
														<div class="el-field">
															<div class="el-body">
																<strong x-text="getCoreElementLabel(el.name)"></strong>
																<div class="d-flex gap-2 mt-2">
																	<select x-model="el.tag" class="form-select form-select-sm" style="max-width:100px">
																		<option>div</option><option>p</option><option>span</option>
																		<option>small</option><option>time</option><option>figure</option>
																		<option>h1</option><option>h2</option><option>h3</option>
																		<option>h4</option><option>h5</option><option>h6</option>
																	</select>
																	<select x-model="el.variant" class="form-select form-select-sm" style="max-width:130px">
																		<option value="default">Default</option>
																		<option value="display">Display</option>
																		<option value="lead">Lead</option>
																		<option value="muted">Muted</option>
																		<option value="pill">Pill</option>
																		<option value="card">Card</option>
																		<option value="glass">Glass</option>
																		<option value="stat">Stat</option>
																	</select>
																	<input type="text" x-model="el.class" class="form-control form-control-sm" placeholder="CSS class">
																</div>
															</div>
														</div>
													</template>

													<!-- Text -->
													<template x-if="el.type === 'text'">
														<div>
															<textarea x-model="el.text" class="form-control form-control-sm" rows="2" placeholder="Text"></textarea>
															<div class="d-flex gap-2 mt-2">
																<select x-model="el.tag" class="form-select form-select-sm" style="max-width:100px">
																	<option>p</option><option>div</option><option>span</option><option>small</option>
																	<option>h1</option><option>h2</option><option>h3</option><option>h4</option>
																</select>
																<select x-model="el.variant" class="form-select form-select-sm" style="max-width:130px">
																	<option value="default">Default</option>
																	<option value="lead">Lead</option>
																	<option value="callout">Callout</option>
																	<option value="muted">Muted</option>
																</select>
																<input type="text" x-model="el.class" class="form-control form-control-sm" placeholder="CSS class">
															</div>
														</div>
													</template>

													<!-- Separator -->
													<template x-if="el.type === 'separator'">
														<div class="el-separator">— Separator —</div>
													</template>

													<!-- Heading -->
													<template x-if="el.type === 'heading'">
														<div class="d-flex gap-2">
															<input type="text" x-model="el.text" class="form-control form-control-sm" placeholder="Heading">
															<select x-model="el.level" class="form-select form-select-sm" style="width:82px">
																<option>h1</option><option>h2</option><option>h3</option>
																<option>h4</option><option>h5</option><option>h6</option>
															</select>
														</div>
													</template>

													<!-- HTML -->
													<template x-if="el.type === 'html'">
														<textarea x-model="el.content" class="form-control form-control-sm" rows="2" placeholder="HTML"></textarea>
													</template>
												</div>
											</template>

											<div class="column-add-area"
											     @click.stop="openElementPicker(sectionIndex, rowIndex, colIndex)"
											     @dragover.prevent
											     @drop.stop.prevent="onDropColumn($event, sectionIndex, rowIndex, colIndex)">
												<span x-show="col.elements.length === 0"><?= Text::_('FLEXI_PROTEMPLATE_DROP_HERE') ?></span>
												<span x-show="col.elements.length > 0">+ Add element</span>
											</div>
										</div>
									</div>
								</template>
							</div>
						</div>
					</template>
				</section>
			</template>
		</main>

		<!-- Element Picker Modal ──────────────────────────── -->
		<div class="fcpt-modal-backdrop"
		     x-show="picker.open"
		     x-cloak
		     x-transition.opacity
		     @keydown.escape.window="closeElementPicker()"
		     @click.self="closeElementPicker()">
			<div class="fcpt-element-modal" role="dialog" aria-modal="true">
				<div class="fcpt-modal-head">
					<div>
						<p class="fcpt-kicker mb-1"><?= Text::_('FLEXI_PROTEMPLATE_ADD_ELEMENT') ?></p>
						<h3><?= Text::_('FLEXI_PROTEMPLATE_CHOOSE_ELEMENT') ?></h3>
					</div>
					<button type="button" class="btn btn-sm btn-outline-secondary" @click="closeElementPicker()">×</button>
				</div>

				<div class="fcpt-modal-tabs">
					<button type="button" :class="{active: picker.tab === 'core'}"   @click="picker.tab = 'core'">
						<?= Text::_('FLEXI_PROTEMPLATE_CORE_ELEMENTS') ?>
					</button>
					<button type="button" :class="{active: picker.tab === 'custom'}" @click="picker.tab = 'custom'">
						<?= Text::_('FLEXI_PROTEMPLATE_CUSTOM_FIELDS') ?>
					</button>
					<button type="button" :class="{active: picker.tab === 'basic'}"  @click="picker.tab = 'basic'">
						<?= Text::_('FLEXI_PROTEMPLATE_BASIC_ELEMENTS') ?>
					</button>
				</div>

				<input type="search"
				       class="form-control fcpt-picker-search"
				       x-model="picker.search"
				       placeholder="<?= Text::_('JSEARCH_FILTER_SUBMIT') ?>">

				<div class="fcpt-picker-grid">
					<template x-for="item in getPickerItems()" :key="item.key">
						<button type="button"
						        class="fcpt-picker-card"
						        :class="'is-' + item.group"
						        @click="addPickedElement(item)">
							<strong x-text="item.label"></strong>
							<span x-text="item.description"></span>
						</button>
					</template>
					<div class="fcpt-empty-small" x-show="getPickerItems().length === 0">
						<?= Text::_('FLEXI_PROTEMPLATE_NO_ELEMENTS') ?>
					</div>
				</div>
			</div>
		</div>

	</div><!-- /fcpt-builder -->

	<input type="hidden" name="task" value="">
</form>

<script>
function fcptBuilder() {
    return {
        layout: fcptNormalizeLayout(<?= $layoutJson ?>),
        availableFields:  <?= $fieldsJson      ?: '[]' ?>,
        coreElements:     <?= $coreElementsJson ?: '[]' ?>,
        availableThemes:  <?= $themesJson       ?: '[]' ?>,
        basicElements: [
            { key: 'text',      label: 'Text',      description: 'Plain text block',    group: 'basic', element: {type:'text',      text:'Custom text', tag:'p', variant:'default', class:''} },
            { key: 'heading',   label: 'Heading',   description: 'Section title',        group: 'basic', element: {type:'heading',   text:'Heading', level:'h3', variant:'default'} },
            { key: 'separator', label: 'Separator', description: 'Horizontal divider',   group: 'basic', element: {type:'separator'} },
            { key: 'html',      label: 'HTML',      description: 'Custom HTML block',    group: 'basic', element: {type:'html',      content:'<p>Custom HTML</p>'} }
        ],
        previewMode: false,
        rowPreset: 1,
        selected: { section: null, row: null, col: null },
        picker: {
            open: false,
            tab: 'core',
            search: '',
            target: { section: null, row: null, col: null }
        },
        _drag: null,
        autosave: {
            timer:    null,
            status:   '',
            endpoint: 'index.php?option=com_flexicontent&task=protemplates.autosaveJson',
            layoutId: <?= (int) ($item->id ?? 0) ?>
        },

        init() {
            const form = document.getElementById('adminForm');
            if (form) {
                form.addEventListener('submit', () => {
                    document.getElementById('fcpt_layout_data_input').value = JSON.stringify(this.layout);
                });
            }
            if (this.layout.sections.length > 0) this.selectSection(0);
            this.$watch('layout', () => this.queueAutosave());
        },

        // ── Autosave ──────────────────────────────────────────────
        queueAutosave() {
            if (!this.autosave.layoutId) return;
            window.clearTimeout(this.autosave.timer);
            this.autosave.status = 'Draft pending';
            this.autosave.timer = window.setTimeout(() => this.autosaveDraft(), 1200);
        },
        autosaveDraft() {
            const form  = document.getElementById('adminForm');
            const token = form?.querySelector('input[type="hidden"][value="1"]')?.name;
            if (!form || !token) return;
            const data = new FormData();
            data.append('id', String(this.autosave.layoutId));
            data.append('layout_json', JSON.stringify(this.layout));
            data.append(token, '1');
            this.autosave.status = 'Autosaving...';
            fetch(this.autosave.endpoint, { method: 'POST', body: data, credentials: 'same-origin' })
                .then(r => r.json())
                .then(payload => { this.autosave.status = payload?.success === false ? 'Autosave failed' : 'Draft saved'; })
                .catch(() => { this.autosave.status = 'Autosave failed'; });
        },

        // ── Sections ──────────────────────────────────────────────
        addSection() {
            this.layout.sections.push({ id: fcptUid('section'), label: 'Section ' + (this.layout.sections.length + 1), class: 'fcpt-section-default', appearance: 'plain', rows: [] });
            this.selectSection(this.layout.sections.length - 1);
        },
        removeSection(index) {
            if (!confirm('Remove section?')) return;
            this.layout.sections.splice(index, 1);
            this.selected = { section: null, row: null, col: null };
        },
        onDragStartSection(event, sectionIndex) {
            if (this.previewMode || fcptIsInteractive(event.target)) return event.preventDefault();
            this._drag = { kind: 'section', sectionIndex };
            event.dataTransfer.effectAllowed = 'move';
        },
        onDropSection(event, targetSectionIndex) {
            if (!this._drag) return;
            if (this._drag.kind === 'section') this.moveSection(this._drag.sectionIndex, targetSectionIndex);
            if (this._drag.kind === 'row') this.moveRow(this._drag.sectionIndex, this._drag.rowIndex, targetSectionIndex, this.layout.sections[targetSectionIndex].rows.length);
            this.clearDrag();
        },
        moveSection(from, to) {
            if (from === to) return;
            fcptMoveItem(this.layout.sections, from, to);
            this.selectSection(to);
        },
        moveSectionUp(i)   { if (i > 0) this.moveSection(i, i - 1); },
        moveSectionDown(i) { if (i < this.layout.sections.length - 1) this.moveSection(i, i + 2); },

        // ── Rows ──────────────────────────────────────────────────
        addRowToSelectedSection() {
            if (this.selected.section === null) { if (this.layout.sections.length === 0) this.addSection(); this.selected.section = 0; }
            this.addRow(this.layout.sections[this.selected.section], Number(this.rowPreset));
        },
        addRow(section, columns) {
            section.rows.push({ id: fcptUid('row'), class: 'fcpt-row-default', appearance: 'default', cols: fcptCreateColumns(columns || Number(this.rowPreset) || 1) });
            this.selectRow(this.layout.sections.indexOf(section), section.rows.length - 1);
        },
        removeRow(section, index) { section.rows.splice(index, 1); this.selected.row = null; this.selected.col = null; },
        onDragStartRow(event, sectionIndex, rowIndex) {
            if (this.previewMode || fcptIsInteractive(event.target)) return event.preventDefault();
            this._drag = { kind: 'row', sectionIndex, rowIndex };
            event.dataTransfer.effectAllowed = 'move';
        },
        onDropRow(event, targetSectionIndex, targetRowIndex) {
            if (!this._drag || this._drag.kind !== 'row') return;
            this.moveRow(this._drag.sectionIndex, this._drag.rowIndex, targetSectionIndex, targetRowIndex);
            this.clearDrag();
        },
        moveRow(fromSec, fromRow, toSec, toRow) {
            const fromRows = this.layout.sections[fromSec]?.rows;
            const toRows   = this.layout.sections[toSec]?.rows;
            if (!fromRows || !toRows) return;
            const [row] = fromRows.splice(fromRow, 1);
            if (!row) return;
            if (fromRows === toRows && fromRow < toRow) toRow -= 1;
            toRows.splice(Math.max(0, Math.min(toRow, toRows.length)), 0, row);
            this.selectRow(toSec, Math.max(0, Math.min(toRow, toRows.length - 1)));
        },

        // ── Columns ───────────────────────────────────────────────
        addColumn(row)         { row.cols.push({ id: fcptUid('col'), width: 6, class: 'fcpt-col-default', elements: [] }); },
        removeColumn(row, i)   { row.cols.splice(i, 1); this.selected.col = null; },
        setColPreset(row, widths) {
            row.cols = widths.map((width, i) => ({ id: row.cols[i]?.id || fcptUid('col'), width, class: row.cols[i]?.class || 'fcpt-col-default', appearance: row.cols[i]?.appearance || 'default', elements: row.cols[i]?.elements || [] }));
        },

        // ── Elements ──────────────────────────────────────────────
        addElementToColumn(si, ri, ci, element) {
            const col = this.layout.sections[si]?.rows[ri]?.cols[ci];
            if (!col) return;
            col.elements.push({ ...element, _uid: fcptUid('el') });
            this.selectColumn(si, ri, ci);
        },
        removeElement(col, i) { col.elements.splice(i, 1); },
        onDragStartNew(event, data) {
            this._drag = { kind: 'new-element', element: { ...data, _uid: fcptUid('el') } };
            event.dataTransfer.setData('text/plain', 'new-element');
            event.dataTransfer.effectAllowed = 'copy';
        },
        onDragStartElement(event, si, ri, ci, ei) {
            if (this.previewMode || fcptIsInteractive(event.target)) return event.preventDefault();
            this._drag = { kind: 'element', sectionIndex: si, rowIndex: ri, colIndex: ci, elementIndex: ei };
            event.dataTransfer.setData('text/plain', 'fcpt-element');
            event.dataTransfer.effectAllowed = 'move';
        },
        onDropColumn(event, si, ri, ci) {
            const col = this.layout.sections[si]?.rows[ri]?.cols[ci];
            if (!col || !this._drag) return;
            if (this._drag.kind === 'new-element') col.elements.push({ ...this._drag.element, _uid: fcptUid('el') });
            if (this._drag.kind === 'element') this.moveElement(this._drag, si, ri, ci, col.elements.length);
            this.clearDrag();
        },
        onDropElement(event, si, ri, ci, ei) {
            if (!this._drag) return;
            if (this._drag.kind === 'new-element') { const col = this.layout.sections[si]?.rows[ri]?.cols[ci]; if (!col) return; col.elements.splice(ei, 0, { ...this._drag.element, _uid: fcptUid('el') }); }
            if (this._drag.kind === 'element') this.moveElement(this._drag, si, ri, ci, ei);
            this.clearDrag();
        },
        moveElement(src, tsi, tri, tci, tei) {
            const srcCol = this.layout.sections[src.sectionIndex]?.rows[src.rowIndex]?.cols[src.colIndex];
            const tgtCol = this.layout.sections[tsi]?.rows[tri]?.cols[tci];
            if (!srcCol || !tgtCol) return;
            const [el] = srcCol.elements.splice(src.elementIndex, 1);
            if (!el) return;
            if (srcCol === tgtCol && src.elementIndex < tei) tei -= 1;
            tgtCol.elements.splice(Math.max(0, Math.min(tei, tgtCol.elements.length)), 0, el);
            this.selectColumn(tsi, tri, tci);
        },
        isDraggingElement(si, ri, ci, ei) {
            return this._drag?.kind === 'element' && this._drag.sectionIndex === si && this._drag.rowIndex === ri && this._drag.colIndex === ci && this._drag.elementIndex === ei;
        },
        clearDrag() { this._drag = null; },

        // ── Selection ─────────────────────────────────────────────
        selectSection(s)       { this.selected = { section: s, row: null, col: null }; },
        selectRow(s, r)        { this.selected = { section: s, row: r, col: null }; },
        selectColumn(s, r, c)  { this.selected = { section: s, row: r, col: c }; },
        getSelectedColumn() {
            const s = this.layout.sections[this.selected.section];
            const r = s?.rows[this.selected.row];
            const c = r?.cols[this.selected.col];
            if (c) return c;
            if (!s) { this.addSection(); return null; }
            if (!s.rows.length) this.addRow(s, 1);
            this.selectColumn(this.selected.section, 0, 0);
            return s.rows[0].cols[0];
        },

        // ── Element Picker ────────────────────────────────────────
        openElementPicker(si, ri, ci) {
            this.picker.open = true; this.picker.tab = 'core'; this.picker.search = '';
            this.picker.target = { section: si, row: ri, col: ci };
            this.selectColumn(si, ri, ci);
        },
        closeElementPicker() { this.picker.open = false; this.picker.search = ''; },
        getPickerItems() {
            const search = this.picker.search.trim().toLowerCase();
            let items = [];
            if (this.picker.tab === 'core') {
                items = this.coreElements.map(el => ({ key: 'core-' + el.name, label: el.label, description: el.name, group: 'core', element: this.makeCoreElement(el) }));
            }
            if (this.picker.tab === 'custom') {
                items = this.availableFields.map(f => ({ key: 'field-' + f.id, label: f.label, description: f.type, group: 'custom', element: this.makeFieldElement(f) }));
            }
            if (this.picker.tab === 'basic') {
                items = this.basicElements.map(item => ({ ...item, element: { ...item.element } }));
            }
            if (!search) return items;
            return items.filter(item => (item.label + ' ' + item.description).toLowerCase().includes(search));
        },
        addPickedElement(item) {
            const t = this.picker.target;
            if (t.section === null || t.row === null || t.col === null) return;
            this.addElementToColumn(t.section, t.row, t.col, item.element);
            this.closeElementPicker();
        },

        // ── Labels ────────────────────────────────────────────────
        getFieldLabel(id) {
            const f = this.availableFields.find(f => Number(f.id) === Number(id));
            return f ? f.label : '#' + id;
        },
        getCoreElementLabel(name) {
            const el = this.coreElements.find(el => el.name === name);
            return el ? el.label : name;
        },
        getElementTypeLabel(element) {
            if (element.type === 'article') return this.getCoreElementLabel(element.name);
            if (element.type === 'field')   return this.getFieldLabel(element.id);
            return String(element.type || 'element').toUpperCase();
        },

        // ── Theme helpers ─────────────────────────────────────────
        selectedTheme() {
            return this.availableThemes.find(t => Number(t.id) === Number(this.layout.settings.themeId)) || null;
        },
        themeValue(theme, path, fallback = '') {
            return String(path).split('.').reduce((v, k) => v && typeof v === 'object' && k in v ? v[k] : undefined, theme?.data || {}) || fallback;
        },
        selectedThemePreviewStyle() {
            const t = this.selectedTheme();
            if (!t) return {};
            return {
                '--selected-theme-bg':     this.themeValue(t, 'colors.surface', '#fff'),
                '--selected-theme-color':  this.themeValue(t, 'colors.text', '#172033'),
                '--selected-theme-accent': this.themeValue(t, 'colors.accent', '#2f6fed'),
                '--selected-theme-border': this.themeValue(t, 'colors.border', '#d7dee8')
            };
        },

        // ── Element factories ─────────────────────────────────────
        makeFieldElement(field) {
            return { type: 'field', id: field.id, showLabel: true, variant: 'default', class: '' };
        },
        makeCoreElement(el) {
            return { type: 'article', name: el.name, tag: el.tag || 'div', variant: el.name === 'title' ? 'display' : 'default', class: 'fcpt-item-' + String(el.name).replace(/_/g, '-') };
        },

        // ── Preview ───────────────────────────────────────────────
        previewHtml() {
            const s = this.layout.settings || {};
            const cls = ['fcpt-output', 'fcpt-theme-' + (s.theme || 'clean'), 'fcpt-width-' + (s.width || 'default'), 'fcpt-spacing-' + (s.spacing || 'normal')].join(' ');
            return '<div class="' + cls + '">' + this.layout.sections.map(section =>
                '<section class="fcpt-section fcpt-section-' + fcptEscAttr(section.appearance || 'plain') + ' ' + fcptEscAttr(section.class || '') + '">'
                + (section.rows || []).map(row =>
                    '<div class="row fcpt-row fcpt-row-' + fcptEscAttr(row.appearance || 'default') + '">'
                    + (row.cols || []).map(col => {
                        const body = (col.elements || []).map(el => this.previewElement(el)).join('');
                        return body ? '<div class="col-md-' + Number(col.width || 12) + ' fcpt-col">' + body + '</div>' : '';
                    }).join('')
                    + '</div>'
                ).join('')
                + '</section>'
            ).join('') + '</div>';
        },
        previewElement(el) {
            const variant = fcptEscAttr(el.variant || 'default');
            const klass   = fcptEscAttr(el.class || '');
            if (el.type === 'field') {
                const label = this.getFieldLabel(el.id);
                const type  = this.availableFields.find(f => Number(f.id) === Number(el.id))?.type || 'unknown';
                return '<div class="fcpt-field fcpt-field-type-' + fcptEscAttr(type) + ' fcpt-el-' + variant + ' ' + klass + '">'
                    + (el.showLabel === false ? '' : '<span class="fcpt-label">' + fcptEscHtml(label) + '</span>')
                    + '<span class="fcpt-value">Sample ' + fcptEscHtml(label) + '</span></div>';
            }
            if (el.type === 'article') {
                const label = this.getCoreElementLabel(el.name);
                const tag   = ['div','p','span','small','time','figure','h1','h2','h3','h4','h5','h6'].includes(el.tag) ? el.tag : 'div';
                return '<' + tag + ' class="fcpt-core fcpt-core-' + fcptEscAttr(el.name || '') + ' fcpt-el-' + variant + ' ' + klass + '">' + fcptEscHtml(label) + '</' + tag + '>';
            }
            if (el.type === 'heading') {
                const level = ['h1','h2','h3','h4','h5','h6'].includes(el.level) ? el.level : 'h3';
                return '<' + level + ' class="fcpt-heading fcpt-el-' + variant + '">' + fcptEscHtml(el.text || 'Heading') + '</' + level + '>';
            }
            if (el.type === 'text') {
                const tag = ['p','div','span','small','h1','h2','h3','h4','h5','h6'].includes(el.tag) ? el.tag : 'p';
                return '<' + tag + ' class="fcpt-text fcpt-el-' + variant + ' ' + klass + '">' + fcptEscHtml(el.text || 'Text') + '</' + tag + '>';
            }
            if (el.type === 'separator') return '<hr class="fcpt-separator">';
            if (el.type === 'html')      return '<div class="fcpt-html">' + (el.content || '') + '</div>';
            return '';
        }
    };
}

// ── Utility functions ──────────────────────────────────────────────────────────

function fcptUid(prefix) {
    return prefix + '-' + Date.now() + '-' + Math.random().toString(16).slice(2);
}

function fcptEscHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
}

function fcptEscAttr(value) {
    return fcptEscHtml(value).replace(/[^a-zA-Z0-9_\-\s]/g, '');
}

function fcptIsInteractive(target) {
    return Boolean(target?.closest?.('input, textarea, select, button, a, label'));
}

function fcptMoveItem(items, from, to) {
    if (!Array.isArray(items) || from === to) return;
    const [item] = items.splice(from, 1);
    if (!item) return;
    if (from < to) to -= 1;
    items.splice(Math.max(0, Math.min(to, items.length)), 0, item);
}

function fcptCreateColumns(count) {
    const widths = ({1:[12], 2:[6,6], 3:[4,4,4], 4:[3,3,3,3]})[Number(count)] || [12];
    return widths.map(width => ({ id: fcptUid('col'), width, class: 'fcpt-col-default', appearance: 'default', elements: [] }));
}

function fcptNormalizeLayout(layout) {
    const settings = fcptNormalizeSettings(layout?.settings || {});
    if (!layout || typeof layout !== 'object') return { version: 2, settings, sections: [] };
    if (Array.isArray(layout.sections)) {
        return {
            version: 2, settings,
            sections: layout.sections.map(s => ({
                id: s.id || fcptUid('section'),
                label: s.label || 'Section',
                class: s.class || 'fcpt-section-default',
                appearance: ['plain','card','band','hero','glass','bento','feature','spotlight','editorial'].includes(s.appearance) ? s.appearance : 'plain',
                rows: fcptNormalizeRows(s.rows)
            }))
        };
    }
    return { version: 2, settings, sections: [] };
}

function fcptNormalizeSettings(s) {
    const pick = (v, opts, def) => opts.includes(v) ? v : def;
    return {
        theme:   pick(s.theme,   ['clean','editorial','soft','contrast'], 'clean'),
        width:   pick(s.width,   ['default','narrow','wide','full'], 'default'),
        spacing: pick(s.spacing, ['compact','normal','airy'], 'normal'),
        themeId: Number(s.themeId || 0)
    };
}

function fcptNormalizeRows(rows) {
    return Array.isArray(rows) ? rows.map(row => ({
        id: row.id || fcptUid('row'),
        class: row.class || 'fcpt-row-default',
        appearance: ['default','bento','media','timeline','stacked','compact'].includes(row.appearance) ? row.appearance : 'default',
        cols: Array.isArray(row.cols) ? row.cols.map(col => ({
            id: col.id || fcptUid('col'),
            width: Number(col.width) || 12,
            class: col.class || 'fcpt-col-default',
            appearance: col.appearance || 'default',
            elements: Array.isArray(col.elements) ? col.elements.map(el => ({ ...el, _uid: el._uid || fcptUid('el') })) : []
        })) : []
    })) : [];
}
</script>
