<?php
/**
 * @package         FLEXIcontent
 * @subpackage      Pro Themes — preset chooser
 *
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * Single-step preset gallery. Each card is a single submit <button> in one
 * form, named preset_key — Tab-between-buttons, Enter/Space activates, one
 * accessible name per card. Alpine.js used only for client-side group filter.
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var FlexicontentViewProtheme $this */

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

$presets = is_array($this->presets) ? $this->presets : [];
$groups  = is_array($this->groups)  ? $this->groups  : [];
?>

<div class="fcpt-chooser-shell">

	<div class="fcpt-chooser-step2" x-data="fcptThemeChooserFilter()" x-init="init()">

		<header class="fcpt-chooser-header">
			<a href="<?= Route::_('index.php?option=com_flexicontent&view=prothemes') ?>"
			   class="fcpt-back-link" rel="nofollow">
				<span aria-hidden="true">←</span>
				<?= Text::_('FLEXI_PROTHEME_CHOOSE_BACK') ?>
			</a>

			<h1 id="fcpt-theme-heading" tabindex="-1" autofocus class="fcpt-chooser-h1">
				<?= Text::_('FLEXI_PROTHEME_CHOOSE_PRESET_HEADING') ?>
			</h1>
			<p class="fcpt-chooser-sub">
				<?= Text::_('FLEXI_PROTHEME_CHOOSE_PRESET_SUB') ?>
			</p>
		</header>

		<form method="post"
		      action="<?= Route::_('index.php?option=com_flexicontent') ?>"
		      class="fcpt-preset-form"
		      name="adminForm" id="adminForm"
		      aria-labelledby="fcpt-theme-heading">

			<input type="hidden" name="option" value="com_flexicontent">
			<input type="hidden" name="task"   value="prothemes.createFromPreset">
			<?= HTMLHelper::_('form.token') ?>

			<div class="fcpt-form-row">
				<label for="fcpt-theme-title" class="fcpt-form-label">
					<?= Text::_('FLEXI_PROTHEME_NEW_TITLE_LABEL') ?>
				</label>
				<input type="text"
				       id="fcpt-theme-title"
				       name="title"
				       class="form-control fcpt-form-input"
				       placeholder="<?= htmlspecialchars(Text::_('FLEXI_PROTHEME_NEW_TITLE_PLACEHOLDER'), ENT_QUOTES) ?>"
				       maxlength="200"
				       autocomplete="off"
				       aria-describedby="fcpt-theme-title-help">
				<p class="fcpt-form-help" id="fcpt-theme-title-help">
					<?= Text::_('FLEXI_PROTHEME_NEW_TITLE_HELP') ?>
				</p>
			</div>

			<div class="fcpt-filter-row" role="group" aria-label="<?= htmlspecialchars(Text::_('FLEXI_PROTHEME_FILTER_BY_GROUP'), ENT_QUOTES) ?>">
				<span class="fcpt-filter-label" aria-hidden="true">
					<?= Text::_('FLEXI_PROTHEME_FILTER_BY_GROUP') ?>:
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
				<li class="fcpt-preset-cell" data-group="all">
					<button type="submit"
					        name="preset_key"
					        value="blank-theme"
					        class="fcpt-preset-card is-blank"
					        aria-labelledby="fcpt-theme-blank-name"
					        aria-describedby="fcpt-theme-blank-desc">
						<span class="fcpt-preset-thumb" aria-hidden="true">
							<svg viewBox="0 0 240 150" xmlns="http://www.w3.org/2000/svg" focusable="false">
								<rect width="240" height="150" rx="6" fill="#f8fafc" stroke="#64748b" stroke-dasharray="6 4"/>
								<text x="120" y="92" text-anchor="middle" font-size="48" fill="#64748b" font-family="system-ui">+</text>
							</svg>
						</span>
						<span id="fcpt-theme-blank-name" class="fcpt-preset-name">
							<?= Text::_('FLEXI_PROTHEME_BLANK_TITLE') ?>
						</span>
						<span id="fcpt-theme-blank-desc" class="fcpt-preset-desc">
							<?= Text::_('FLEXI_PROTHEME_BLANK_DESC') ?>
						</span>
					</button>
				</li>

				<?php foreach ($presets as $p) :
					$keyAttr = htmlspecialchars($p['key'], ENT_QUOTES);
					$nameId  = 'fcpt-tn-' . $keyAttr;
					$descId  = 'fcpt-td-' . $keyAttr;
					$grpKey  = htmlspecialchars($p['group'], ENT_QUOTES);
				?>
				<li class="fcpt-preset-cell"
				    data-group="<?= $grpKey ?>"
				    x-show="activeGroup === 'all' || activeGroup === '<?= $grpKey ?>'"
				    x-transition.opacity>
					<button type="submit"
					        name="preset_key"
					        value="<?= $keyAttr ?>"
					        class="fcpt-preset-card fcpt-theme-card"
					        aria-labelledby="<?= $nameId ?>"
					        aria-describedby="<?= $descId ?>">
						<span class="fcpt-preset-thumb" aria-hidden="true">
							<?= $p['thumbnail'] ?>
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
</div>

<script>
function fcptThemeChooserFilter() {
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
