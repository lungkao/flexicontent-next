<?php
/**
 * @package         FLEXIcontent
 * @subpackage      Pro Templates — preset chooser
 *
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * Two-step preset gallery:
 *   Step 1 ($this->scope === ''): scope picker (item vs category, native radio
 *                                  group styled as cards, fieldset+legend)
 *   Step 2 ($this->scope set):    preset cards in a single form; each card is
 *                                  a submit <button> (single accessible name,
 *                                  Tab-between-buttons, Enter/Space activates)
 *
 * Accessibility: native HTML semantics drive keyboard + AT behavior. Alpine.js
 * is used only for client-side group filtering (no nav logic). All critical
 * state survives without JavaScript — back/cancel/submit are native links
 * and submit buttons.
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var FlexicontentViewProtemplate $this */

$document = \Joomla\CMS\Factory::getDocument();
$document->addStyleSheet(
	\Joomla\CMS\Uri\Uri::root(true) . '/administrator/components/com_flexicontent/assets/css/protemplate_chooser.css',
	['version' => 'auto']
);
$document->addScript(
	'https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js',
	['version' => 'auto'],
	['defer' => true]
);

$scope         = (string) $this->scope;
$itemCount     = (int)    $this->itemCount;
$categoryCount = (int)    $this->categoryCount;
$presets       = is_array($this->presets) ? $this->presets : [];
$groups        = is_array($this->groups)  ? $this->groups  : [];

// "Change layout" flow: when the editor lands here via the toolbar
// "Change layout" button on an existing template, the URL carries
// ?replace_id={id}. We forward it through both forms so the submit
// handler updates the existing row instead of inserting a new one.
$replaceId = (int) \Joomla\CMS\Factory::getApplication()->input->getInt('replace_id', 0);
?>

<div class="fcpt-chooser-shell">

	<?php if ($scope === '') : ?>
	<!-- ── STEP 1: scope picker ─────────────────────────────────── -->

	<form method="get" action="<?= Route::_('index.php') ?>"
	      class="fcpt-chooser-step1"
	      name="adminForm" id="adminForm" novalidate>
		<input type="hidden" name="option" value="com_flexicontent">
		<input type="hidden" name="view"   value="protemplate">
		<input type="hidden" name="layout" value="choose">
		<!-- task field is required by Joomla's toolbar Cancel handler
		     (Joomla.submitform reads document.adminForm.task) -->
		<input type="hidden" name="task"   value="">
		<?php if ($replaceId > 0) : ?>
		<input type="hidden" name="replace_id" value="<?= $replaceId ?>">
		<?php endif; ?>

		<header class="fcpt-chooser-header">
			<h1 tabindex="-1" autofocus class="fcpt-chooser-h1">
				<?= Text::_('FLEXI_PROTEMPLATE_CHOOSE_SCOPE_HEADING') ?>
			</h1>
			<p class="fcpt-chooser-sub">
				<?= Text::_('FLEXI_PROTEMPLATE_CHOOSE_SCOPE_SUB') ?>
			</p>
		</header>

		<fieldset class="fcpt-scope-picker">
			<legend class="fcpt-vh"><?= Text::_('FLEXI_PROTEMPLATE_CHOOSE_SCOPE_LEGEND') ?></legend>

			<label class="fcpt-scope-card" data-scope="item">
				<input type="radio" name="scope" value="item" class="fcpt-vh" required checked>
				<span class="fcpt-scope-illust" aria-hidden="true">
					<svg viewBox="0 0 240 150" xmlns="http://www.w3.org/2000/svg" focusable="false">
						<rect width="240" height="150" rx="6" fill="#f8fafc" stroke="#64748b"/>
						<rect x="20" y="22" width="200" height="14" rx="2" fill="#0f172a"/>
						<rect x="20" y="46" width="200" height="56" rx="3" fill="#64748b"/>
						<rect x="20" y="112" width="200" height="6" fill="#475569"/>
						<rect x="20" y="124" width="160" height="6" fill="#475569"/>
					</svg>
				</span>
				<span class="fcpt-scope-name"><?= Text::_('FLEXI_PROTEMPLATE_SCOPE_ITEM') ?></span>
				<span class="fcpt-scope-desc"><?= Text::_('FLEXI_PROTEMPLATE_SCOPE_ITEM_DESC') ?></span>
				<span class="fcpt-scope-meta">
					<?= Text::sprintf('FLEXI_PROTEMPLATE_SCOPE_PRESET_COUNT', $itemCount) ?>
				</span>
			</label>

			<label class="fcpt-scope-card" data-scope="category">
				<input type="radio" name="scope" value="category" class="fcpt-vh">
				<span class="fcpt-scope-illust" aria-hidden="true">
					<svg viewBox="0 0 240 150" xmlns="http://www.w3.org/2000/svg" focusable="false">
						<rect width="240" height="150" rx="6" fill="#f8fafc" stroke="#64748b"/>
						<rect x="20" y="20" width="120" height="10" rx="2" fill="#0f172a"/>
						<rect x="20" y="40" width="62" height="44" rx="3" fill="#64748b"/>
						<rect x="89" y="40" width="62" height="44" rx="3" fill="#64748b"/>
						<rect x="158" y="40" width="62" height="44" rx="3" fill="#64748b"/>
						<rect x="20" y="94" width="62" height="32" rx="3" fill="#475569"/>
						<rect x="89" y="94" width="62" height="32" rx="3" fill="#475569"/>
						<rect x="158" y="94" width="62" height="32" rx="3" fill="#475569"/>
					</svg>
				</span>
				<span class="fcpt-scope-name"><?= Text::_('FLEXI_PROTEMPLATE_SCOPE_CATEGORY') ?></span>
				<span class="fcpt-scope-desc"><?= Text::_('FLEXI_PROTEMPLATE_SCOPE_CATEGORY_DESC') ?></span>
				<span class="fcpt-scope-meta">
					<?= Text::sprintf('FLEXI_PROTEMPLATE_SCOPE_PRESET_COUNT', $categoryCount) ?>
				</span>
			</label>
		</fieldset>

		<div class="fcpt-chooser-actions">
			<a href="<?= Route::_('index.php?option=com_flexicontent&view=protemplates') ?>"
			   class="btn btn-link fcpt-cancel-link">
				<?= Text::_('JCANCEL') ?>
			</a>
			<button type="submit" class="btn btn-primary btn-lg fcpt-continue-btn">
				<?= Text::_('FLEXI_PROTEMPLATE_CHOOSE_CONTINUE') ?>
				<span aria-hidden="true">→</span>
			</button>
		</div>
	</form>

	<?php else : ?>
	<!-- ── STEP 2: preset cards for chosen scope ──────────────── -->

	<div class="fcpt-chooser-step2" x-data="fcptChooserFilter()" x-init="init()">

		<header class="fcpt-chooser-header">
			<a href="<?= Route::_('index.php?option=com_flexicontent&view=protemplate&layout=choose') ?>"
			   class="fcpt-back-link" rel="nofollow">
				<span aria-hidden="true">←</span>
				<?= Text::_('FLEXI_PROTEMPLATE_CHOOSE_BACK') ?>
			</a>

			<h1 id="fcpt-step2-heading" tabindex="-1" autofocus class="fcpt-chooser-h1">
				<?= Text::_($scope === 'category' ? 'FLEXI_PROTEMPLATE_CHOOSE_PRESET_HEADING_CATEGORY' : 'FLEXI_PROTEMPLATE_CHOOSE_PRESET_HEADING_ITEM') ?>
			</h1>
			<p class="fcpt-chooser-sub">
				<?= Text::_('FLEXI_PROTEMPLATE_CHOOSE_PRESET_SUB') ?>
			</p>
		</header>

		<form method="post"
		      action="<?= Route::_('index.php?option=com_flexicontent') ?>"
		      class="fcpt-preset-form"
		      name="adminForm" id="adminForm"
		      aria-labelledby="fcpt-step2-heading">

			<input type="hidden" name="option" value="com_flexicontent">
			<input type="hidden" name="task"   value="protemplates.createFromPreset">
			<input type="hidden" name="scope"  value="<?= htmlspecialchars($scope, ENT_QUOTES) ?>">
			<?php if ($replaceId > 0) : ?>
			<input type="hidden" name="replace_id" value="<?= $replaceId ?>">
			<?php endif; ?>
			<?= HTMLHelper::_('form.token') ?>

			<div class="fcpt-form-row">
				<label for="fcpt-new-title" class="fcpt-form-label">
					<?= Text::_('FLEXI_PROTEMPLATE_NEW_TITLE_LABEL') ?>
				</label>
				<input type="text"
				       id="fcpt-new-title"
				       name="title"
				       class="form-control fcpt-form-input"
				       placeholder="<?= htmlspecialchars(Text::_('FLEXI_PROTEMPLATE_NEW_TITLE_PLACEHOLDER'), ENT_QUOTES) ?>"
				       maxlength="200"
				       autocomplete="off"
				       aria-describedby="fcpt-title-help">
				<p class="fcpt-form-help" id="fcpt-title-help">
					<?= Text::_('FLEXI_PROTEMPLATE_NEW_TITLE_HELP') ?>
				</p>
			</div>

			<div class="fcpt-filter-row" role="group" aria-label="<?= htmlspecialchars(Text::_('FLEXI_PROTEMPLATE_FILTER_BY_GROUP'), ENT_QUOTES) ?>">
				<span class="fcpt-filter-label" aria-hidden="true">
					<?= Text::_('FLEXI_PROTEMPLATE_FILTER_BY_GROUP') ?>:
				</span>
				<?php foreach ($groups as $g) : ?>
				<button type="button"
				        class="fcpt-filter-chip"
				        :class="{ 'is-active': activeGroup === '<?= htmlspecialchars($g['key'], ENT_QUOTES) ?>' }"
				        :aria-pressed="activeGroup === '<?= htmlspecialchars($g['key'], ENT_QUOTES) ?>' ? 'true' : 'false'"
				        @click="setGroup('<?= htmlspecialchars($g['key'], ENT_QUOTES) ?>')">
					<?= Text::_($g['label_key']) ?>
				</button>
				<?php endforeach; ?>
			</div>

			<div class="fcpt-vh" aria-live="polite" aria-atomic="true" x-text="liveStatus"></div>

			<ul class="fcpt-preset-grid" role="list">
				<!-- Blank starter card -->
				<li class="fcpt-preset-cell" data-group="all">
					<button type="submit"
					        name="preset_key"
					        value="blank-<?= htmlspecialchars($scope, ENT_QUOTES) ?>"
					        class="fcpt-preset-card is-blank"
					        aria-labelledby="fcpt-blank-name"
					        aria-describedby="fcpt-blank-desc">
						<span class="fcpt-preset-thumb" aria-hidden="true">
							<svg viewBox="0 0 240 150" xmlns="http://www.w3.org/2000/svg" focusable="false">
								<rect width="240" height="150" rx="6" fill="#f8fafc" stroke="#64748b" stroke-dasharray="6 4"/>
								<text x="120" y="92" text-anchor="middle" font-size="48" fill="#64748b" font-family="system-ui">+</text>
							</svg>
						</span>
						<span id="fcpt-blank-name" class="fcpt-preset-name">
							<?= Text::_('FLEXI_PRESET_BLANK_TITLE') ?>
						</span>
						<span id="fcpt-blank-desc" class="fcpt-preset-desc">
							<?= Text::_('FLEXI_PRESET_BLANK_DESC') ?>
						</span>
					</button>
				</li>

				<?php foreach ($presets as $p) :
					$keyAttr = htmlspecialchars($p['key'], ENT_QUOTES);
					$nameId  = 'fcpt-pn-' . $keyAttr;
					$descId  = 'fcpt-pd-' . $keyAttr;
					$grpKey  = htmlspecialchars($p['group'], ENT_QUOTES);
				?>
				<li class="fcpt-preset-cell"
				    data-group="<?= $grpKey ?>"
				    x-show="activeGroup === 'all' || activeGroup === '<?= $grpKey ?>'"
				    x-transition.opacity>
					<button type="submit"
					        name="preset_key"
					        value="<?= $keyAttr ?>"
					        class="fcpt-preset-card"
					        aria-labelledby="<?= $nameId ?>"
					        aria-describedby="<?= $descId ?>">
						<span class="fcpt-preset-thumb" aria-hidden="true">
							<?= $p['thumbnail'] /* trusted: built by PresetLibrary */ ?>
						</span>
						<span id="<?= $nameId ?>" class="fcpt-preset-name">
							<?= Text::_($p['title_key']) ?>
						</span>
						<span id="<?= $descId ?>" class="fcpt-preset-desc">
							<?= Text::_($p['description_key']) ?>
						</span>
						<span class="fcpt-preset-group-tag" aria-hidden="true">
							<?= Text::_('FLEXI_PRESET_GROUP_' . strtoupper($p['group'])) ?>
						</span>
					</button>
				</li>
				<?php endforeach; ?>
			</ul>

			<div class="fcpt-empty-state" role="status"
			     x-show="visibleCount === 0" x-cloak>
				<p><?= Text::_('FLEXI_PRESET_NO_RESULTS') ?></p>
				<button type="button" class="btn btn-outline-secondary" @click="setGroup('all')">
					<?= Text::_('FLEXI_PRESET_CLEAR_FILTER') ?>
				</button>
			</div>
		</form>
	</div>

	<script>
	function fcptChooserFilter() {
		return {
			activeGroup:  'all',
			liveStatus:   '',
			visibleCount: 0,
			init() {
				this.recount();
				this.$watch('activeGroup', () => this.recount());
			},
			setGroup(key) { this.activeGroup = key; },
			recount() {
				const cells = document.querySelectorAll('.fcpt-preset-cell');
				let visible = 0;
				cells.forEach(cell => {
					const group = cell.dataset.group;
					if (this.activeGroup === 'all' || group === 'all' || group === this.activeGroup) {
						visible += 1;
					}
				});
				this.visibleCount = visible;
				const tmpl = <?= json_encode(Text::_('FLEXI_PRESET_SHOWING')) ?>;
				const none = <?= json_encode(Text::_('FLEXI_PRESET_NO_RESULTS')) ?>;
				this.liveStatus = visible === 0 ? none : tmpl.replace('%d', visible);
			}
		};
	}
	</script>

	<?php endif; ?>

</div>
