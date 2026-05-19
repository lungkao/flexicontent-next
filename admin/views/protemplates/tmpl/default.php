<?php
/**
 * @package         FLEXIcontent
 * @subpackage      Pro Templates — list template
 *
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * Layout: stat strip + multi-select filter row + active filter chips +
 * accessible data table. Chip removal works without JS — each chip is a
 * named submit button that resets one filter then triggers a form submit.
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$document = Factory::getDocument();
$document->addStyleSheet(
	\Joomla\CMS\Uri\Uri::root(true) . '/administrator/components/com_flexicontent/assets/css/protemplate_chooser.css',
	['version' => 'auto']
);

$ctrl  = 'protemplates.';
$state = $this->state;
$stats = is_array($this->stats) ? $this->stats : [];

$f = [
	'search'          => (string) $state->get('filter.search', ''),
	'state'           => (string) $state->get('filter.state', ''),
	'view_scope'      => (string) $state->get('filter.view_scope', ''),
	'type_id'         => (int)    $state->get('filter.type_id', 0),
	'catid'           => (int)    $state->get('filter.catid', 0),
	'assignment_type' => (string) $state->get('filter.assignment_type', ''),
];

$typeLabel = null;
if ($f['type_id'] > 0 && is_array($this->typeOptions)) {
	foreach ($this->typeOptions as $t) {
		if ((int) $t->id === $f['type_id']) {
			$typeLabel = $t->title;
			break;
		}
	}
}
$catLabel = null;
if ($f['catid'] > 0 && is_array($this->catOptions)) {
	foreach ($this->catOptions as $c) {
		if ((int) $c->id === $f['catid']) {
			$catLabel = $c->title;
			break;
		}
	}
}

$hasAnyFilter = ($f['search'] !== '' || $f['state'] !== '' || $f['view_scope'] !== ''
              || $f['type_id'] > 0  || $f['catid'] > 0      || $f['assignment_type'] !== '');
?>

<!-- ── Stat strip ─────────────────────────────────────────────── -->
<section class="fcpt-stats" aria-labelledby="fcpt-stats-h">
	<h2 id="fcpt-stats-h" class="fcpt-vh"><?= Text::_('FLEXI_PROTEMPLATE_STATS_HEADING') ?></h2>
	<dl class="fcpt-stats-grid">
		<div class="fcpt-stat-card">
			<dt><?= Text::_('FLEXI_PROTEMPLATE_STAT_TOTAL') ?></dt>
			<dd><?= (int) ($stats['total'] ?? 0) ?></dd>
		</div>
		<div class="fcpt-stat-card is-item">
			<dt><?= Text::_('FLEXI_PROTEMPLATE_STAT_ITEM') ?></dt>
			<dd><?= (int) ($stats['item_scope'] ?? 0) ?></dd>
		</div>
		<div class="fcpt-stat-card is-category">
			<dt><?= Text::_('FLEXI_PROTEMPLATE_STAT_CATEGORY') ?></dt>
			<dd><?= (int) ($stats['category_scope'] ?? 0) ?></dd>
		</div>
		<div class="fcpt-stat-card is-published">
			<dt><?= Text::_('FLEXI_PROTEMPLATE_STAT_PUBLISHED') ?></dt>
			<dd><?= (int) ($stats['published'] ?? 0) ?></dd>
		</div>
		<div class="fcpt-stat-card is-draft">
			<dt><?= Text::_('FLEXI_PROTEMPLATE_STAT_DRAFT') ?></dt>
			<dd><?= (int) ($stats['draft'] ?? 0) ?></dd>
		</div>
	</dl>

	<div class="fcpt-stats-cta fcpt-stats-cta--hero">
		<a href="<?= Route::_('index.php?option=com_flexicontent&view=protemplate&layout=choose') ?>"
		   class="btn btn-primary btn-lg fcpt-hero-cta fcpt-hero-cta--primary">
			<span class="fcpt-hero-cta-icon" aria-hidden="true">⭐</span>
			<span class="fcpt-hero-cta-text"><?= Text::_('FLEXI_PROTEMPLATE_CREATE_FROM_PRESET') ?></span>
		</a>
		<a href="<?= Route::_('index.php?option=com_flexicontent&view=prothemes') ?>"
		   class="btn btn-outline-secondary btn-lg fcpt-hero-cta fcpt-hero-cta--secondary">
			<span class="fcpt-hero-cta-icon" aria-hidden="true">🎨</span>
			<span class="fcpt-hero-cta-text"><?= Text::_('FLEXI_PROTHEME_MANAGE') ?></span>
		</a>
	</div>
</section>

<form action="<?= Route::_('index.php?option=com_flexicontent&view=protemplates') ?>"
      method="post" name="adminForm" id="adminForm"
      class="fcpt-list-form"
      role="search"
      aria-label="<?= htmlspecialchars(Text::_('FLEXI_PROTEMPLATE_FILTER_FORM_LABEL'), ENT_QUOTES) ?>">

	<div class="fcpt-filters" role="group" aria-label="<?= htmlspecialchars(Text::_('FLEXI_PROTEMPLATE_FILTERS_GROUP_LABEL'), ENT_QUOTES) ?>">
		<div class="fcpt-filter-field">
			<label for="filter_search" class="fcpt-vh"><?= Text::_('JSEARCH_FILTER') ?></label>
			<input type="search"
			       id="filter_search"
			       name="filter_search"
			       class="form-control"
			       placeholder="<?= htmlspecialchars(Text::_('JSEARCH_FILTER'), ENT_QUOTES) ?>"
			       value="<?= htmlspecialchars($f['search'], ENT_QUOTES) ?>">
		</div>

		<div class="fcpt-filter-field">
			<label for="filter_view_scope" class="fcpt-vh"><?= Text::_('FLEXI_PROTEMPLATE_VIEW_SCOPE') ?></label>
			<?= $this->lists['view_scope_filter'] ?? '' ?>
		</div>

		<div class="fcpt-filter-field">
			<label for="filter_type_id" class="fcpt-vh"><?= Text::_('FLEXI_TYPE') ?></label>
			<?= $this->lists['type_filter'] ?? '' ?>
		</div>

		<div class="fcpt-filter-field">
			<label for="filter_catid" class="fcpt-vh"><?= Text::_('FLEXI_CATEGORY') ?></label>
			<?= $this->lists['cat_filter'] ?? '' ?>
		</div>

		<div class="fcpt-filter-field">
			<label for="filter_assignment_type" class="fcpt-vh"><?= Text::_('FLEXI_PROTEMPLATE_ASSIGNMENT_TYPE') ?></label>
			<?= $this->lists['assignment_filter'] ?? '' ?>
		</div>

		<div class="fcpt-filter-field">
			<label for="filter_state" class="fcpt-vh"><?= Text::_('JSTATUS') ?></label>
			<?= $this->lists['state_filter'] ?? '' ?>
		</div>

		<div class="fcpt-filter-actions">
			<button type="submit" class="btn btn-primary"><?= Text::_('JSEARCH_FILTER_SUBMIT') ?></button>
			<a href="<?= Route::_('index.php?option=com_flexicontent&view=protemplates') ?>"
			   class="btn btn-outline-secondary"><?= Text::_('JSEARCH_FILTER_CLEAR') ?></a>
		</div>
	</div>

	<?php if ($hasAnyFilter) : ?>
	<div class="fcpt-active-filters" role="group" aria-label="<?= htmlspecialchars(Text::_('FLEXI_PROTEMPLATE_ACTIVE_FILTERS_LABEL'), ENT_QUOTES) ?>">
		<span class="fcpt-active-filters-label" aria-hidden="true">
			<?= Text::_('FLEXI_PROTEMPLATE_ACTIVE_FILTERS') ?>:
		</span>

		<?php if ($f['search'] !== '') : ?>
		<button type="submit" name="filter_search" value="" class="fcpt-chip"
		        aria-label="<?= htmlspecialchars(Text::sprintf('FLEXI_PROTEMPLATE_REMOVE_FILTER', Text::_('JSEARCH_FILTER') . ': ' . $f['search']), ENT_QUOTES) ?>">
			<?= htmlspecialchars(Text::_('JSEARCH_FILTER') . ': ' . $f['search'], ENT_QUOTES) ?>
			<span aria-hidden="true">×</span>
		</button>
		<?php endif; ?>

		<?php if ($f['view_scope'] !== '') :
			$lbl = $f['view_scope'] === 'category'
				? Text::_('FLEXI_PROTEMPLATE_VIEW_SCOPE_CATEGORY')
				: Text::_('FLEXI_PROTEMPLATE_VIEW_SCOPE_ITEM');
		?>
		<button type="submit" name="filter_view_scope" value="" class="fcpt-chip"
		        aria-label="<?= htmlspecialchars(Text::sprintf('FLEXI_PROTEMPLATE_REMOVE_FILTER', Text::_('FLEXI_PROTEMPLATE_VIEW_SCOPE') . ': ' . $lbl), ENT_QUOTES) ?>">
			<?= htmlspecialchars(Text::_('FLEXI_PROTEMPLATE_VIEW_SCOPE') . ': ' . $lbl, ENT_QUOTES) ?>
			<span aria-hidden="true">×</span>
		</button>
		<?php endif; ?>

		<?php if ($typeLabel) : ?>
		<button type="submit" name="filter_type_id" value="0" class="fcpt-chip"
		        aria-label="<?= htmlspecialchars(Text::sprintf('FLEXI_PROTEMPLATE_REMOVE_FILTER', Text::_('FLEXI_TYPE') . ': ' . $typeLabel), ENT_QUOTES) ?>">
			<?= htmlspecialchars(Text::_('FLEXI_TYPE') . ': ' . $typeLabel, ENT_QUOTES) ?>
			<span aria-hidden="true">×</span>
		</button>
		<?php endif; ?>

		<?php if ($catLabel) : ?>
		<button type="submit" name="filter_catid" value="0" class="fcpt-chip"
		        aria-label="<?= htmlspecialchars(Text::sprintf('FLEXI_PROTEMPLATE_REMOVE_FILTER', Text::_('FLEXI_CATEGORY') . ': ' . $catLabel), ENT_QUOTES) ?>">
			<?= htmlspecialchars(Text::_('FLEXI_CATEGORY') . ': ' . $catLabel, ENT_QUOTES) ?>
			<span aria-hidden="true">×</span>
		</button>
		<?php endif; ?>

		<?php if ($f['assignment_type'] !== '') :
			$lbl = Text::_('FLEXI_PROTEMPLATE_ASSIGN_' . strtoupper($f['assignment_type']));
		?>
		<button type="submit" name="filter_assignment_type" value="" class="fcpt-chip"
		        aria-label="<?= htmlspecialchars(Text::sprintf('FLEXI_PROTEMPLATE_REMOVE_FILTER', Text::_('FLEXI_PROTEMPLATE_ASSIGNMENT_TYPE') . ': ' . $lbl), ENT_QUOTES) ?>">
			<?= htmlspecialchars(Text::_('FLEXI_PROTEMPLATE_ASSIGNMENT_TYPE') . ': ' . $lbl, ENT_QUOTES) ?>
			<span aria-hidden="true">×</span>
		</button>
		<?php endif; ?>

		<?php if ($f['state'] !== '') :
			$lbl = $f['state'] === '1' ? Text::_('JPUBLISHED') : Text::_('JUNPUBLISHED');
		?>
		<button type="submit" name="filter_state" value="" class="fcpt-chip"
		        aria-label="<?= htmlspecialchars(Text::sprintf('FLEXI_PROTEMPLATE_REMOVE_FILTER', Text::_('JSTATUS') . ': ' . $lbl), ENT_QUOTES) ?>">
			<?= htmlspecialchars(Text::_('JSTATUS') . ': ' . $lbl, ENT_QUOTES) ?>
			<span aria-hidden="true">×</span>
		</button>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<p class="fcpt-result-count" aria-live="polite" aria-atomic="true">
		<?= Text::sprintf('FLEXI_PROTEMPLATE_RESULT_COUNT',
			$this->pagination ? (int) $this->pagination->total : count($this->rows ?? [])
		) ?>
	</p>

	<table class="table table-striped table-hover fcpt-list-table" id="fc-protemplate-list">
		<caption class="fcpt-vh"><?= Text::_('FLEXI_PROTEMPLATE_TABLE_CAPTION') ?></caption>
		<thead>
			<tr>
				<th style="width:1%" scope="col"><?= HTMLHelper::_('grid.checkall') ?></th>
				<th scope="col"><?= Text::_('FLEXI_TITLE') ?></th>
				<th scope="col" class="d-none d-md-table-cell" style="width:10%"><?= Text::_('FLEXI_PROTEMPLATE_VIEW_SCOPE') ?></th>
				<th scope="col" class="d-none d-md-table-cell" style="width:12%"><?= Text::_('FLEXI_TYPE') ?></th>
				<th scope="col" class="d-none d-lg-table-cell" style="width:12%"><?= Text::_('FLEXI_CATEGORY') ?></th>
				<th scope="col" class="d-none d-md-table-cell" style="width:12%"><?= Text::_('FLEXI_PROTEMPLATE_ASSIGNMENT_TYPE') ?></th>
				<th scope="col" style="width:8%"><?= Text::_('JSTATUS') ?></th>
				<th scope="col" class="d-none d-md-table-cell" style="width:6%"><?= Text::_('JGRID_HEADING_ORDERING') ?></th>
				<th scope="col" class="d-none d-md-table-cell" style="width:6%"><?= Text::_('JGRID_HEADING_ID') ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if (empty($this->rows)) : ?>
			<tr>
				<td colspan="9" class="text-center py-5">
					<div class="fcpt-empty-state-inline" role="status">
						<?php if ($hasAnyFilter) : ?>
						<h3><?= Text::_('FLEXI_PROTEMPLATE_NO_RESULTS_TITLE') ?></h3>
						<p><?= Text::_('FLEXI_PROTEMPLATE_NO_RESULTS_HINT') ?></p>
						<a href="<?= Route::_('index.php?option=com_flexicontent&view=protemplates') ?>"
						   class="btn btn-outline-primary"><?= Text::_('JSEARCH_FILTER_CLEAR') ?></a>
						<?php else : ?>
						<h3><?= Text::_('FLEXI_PROTEMPLATE_EMPTY_TITLE') ?></h3>
						<p><?= Text::_('FLEXI_PROTEMPLATE_EMPTY_HINT') ?></p>
						<a href="<?= Route::_('index.php?option=com_flexicontent&view=protemplate&layout=choose') ?>"
						   class="btn btn-primary btn-lg">
							<span aria-hidden="true">⭐</span>
							<?= Text::_('FLEXI_PROTEMPLATE_CREATE_FROM_PRESET') ?>
						</a>
						<?php endif; ?>
					</div>
				</td>
			</tr>
		<?php else : ?>
			<?php foreach ($this->rows as $i => $row) :
				$vs = ($row->view_scope ?? 'item') === 'category' ? 'category' : 'item';
			?>
			<tr>
				<td><?= HTMLHelper::_('grid.id', $i, $row->id) ?></td>
				<td>
					<a href="<?= Route::_('index.php?option=com_flexicontent&view=protemplate&layout=edit&id=' . (int) $row->id) ?>">
						<?= htmlspecialchars($row->title, ENT_QUOTES) ?>
					</a>
					<?php if (!empty($row->note)) : ?>
						<br><small class="text-muted"><?= htmlspecialchars($row->note, ENT_QUOTES) ?></small>
					<?php endif; ?>
					<span class="fcpt-scope-badge fcpt-scope-<?= htmlspecialchars($vs, ENT_QUOTES) ?> d-md-none ms-1">
						<?= Text::_('FLEXI_PROTEMPLATE_VIEW_SCOPE_' . strtoupper($vs)) ?>
					</span>
				</td>
				<td class="d-none d-md-table-cell">
					<span class="fcpt-scope-badge fcpt-scope-<?= htmlspecialchars($vs, ENT_QUOTES) ?>">
						<?= Text::_('FLEXI_PROTEMPLATE_VIEW_SCOPE_' . strtoupper($vs)) ?>
					</span>
				</td>
				<td class="d-none d-md-table-cell"><?= htmlspecialchars($row->type_name ?? Text::_('FLEXI_PROTEMPLATE_ALL_TYPES'), ENT_QUOTES) ?></td>
				<td class="d-none d-lg-table-cell"><?= htmlspecialchars($row->cat_name ?? Text::_('FLEXI_PROTEMPLATE_ALL_CATS'), ENT_QUOTES) ?></td>
				<td class="d-none d-md-table-cell">
					<span class="badge bg-secondary">
						<?= Text::_('FLEXI_PROTEMPLATE_ASSIGN_' . strtoupper($row->assignment_type)) ?>
					</span>
				</td>
				<td><?= HTMLHelper::_('jgrid.published', $row->state, $i, $ctrl, true) ?></td>
				<td class="d-none d-md-table-cell"><?= (int) $row->ordering ?></td>
				<td class="d-none d-md-table-cell"><?= (int) $row->id ?></td>
			</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

	<?= $this->pagination ? $this->pagination->getListFooter() : '' ?>

	<input type="hidden" name="task" value="">
	<input type="hidden" name="boxchecked" value="0">
	<?= HTMLHelper::_('form.token') ?>
</form>
