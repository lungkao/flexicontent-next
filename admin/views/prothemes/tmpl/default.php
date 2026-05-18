<?php
/**
 * @package         FLEXIcontent
 * @subpackage      Pro Themes — list template
 *
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
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

$ctrl = 'prothemes.';
?>

<section class="fcpt-stats" aria-labelledby="fcpt-theme-stats-h">
	<h2 id="fcpt-theme-stats-h" class="fcpt-vh"><?= Text::_('FLEXI_PROTHEME_STATS_HEADING') ?></h2>
	<div class="fcpt-theme-intro">
		<div>
			<p class="fcpt-kicker"><?= Text::_('FLEXI_PROTHEME_KICKER') ?></p>
			<h2 class="fcpt-stats-headline"><?= Text::_('FLEXI_PROTHEME_MANAGER') ?></h2>
			<p class="fcpt-stats-blurb"><?= Text::_('FLEXI_PROTHEME_INTRO_BLURB') ?></p>
		</div>
		<div class="fcpt-stats-cta">
			<a href="<?= Route::_('index.php?option=com_flexicontent&view=protheme&layout=choose') ?>"
			   class="btn btn-primary btn-lg">
				<span aria-hidden="true">🎨</span>
				<?= Text::_('FLEXI_PROTHEME_CREATE_FROM_PRESET') ?>
			</a>
			<a href="<?= Route::_('index.php?option=com_flexicontent&view=protemplates') ?>"
			   class="btn btn-outline-secondary">
				← <?= Text::_('FLEXI_PROTEMPLATE_MANAGER') ?>
			</a>
		</div>
	</div>
</section>

<form action="<?= Route::_('index.php?option=com_flexicontent&view=prothemes') ?>"
      method="post" name="adminForm" id="adminForm"
      class="fcpt-list-form" role="search"
      aria-label="<?= htmlspecialchars(Text::_('FLEXI_PROTHEME_FILTER_FORM_LABEL'), ENT_QUOTES) ?>">

	<div class="fcpt-filters" role="group" aria-label="<?= htmlspecialchars(Text::_('FLEXI_PROTHEME_FILTERS_GROUP_LABEL'), ENT_QUOTES) ?>">
		<div class="fcpt-filter-field">
			<label for="filter_search" class="fcpt-vh"><?= Text::_('JSEARCH_FILTER') ?></label>
			<input type="search"
			       id="filter_search"
			       name="filter_search"
			       class="form-control"
			       placeholder="<?= htmlspecialchars(Text::_('JSEARCH_FILTER'), ENT_QUOTES) ?>"
			       value="<?= htmlspecialchars($this->state->get('filter.search', ''), ENT_QUOTES) ?>">
		</div>
		<div class="fcpt-filter-field">
			<label for="filter_state" class="fcpt-vh"><?= Text::_('JSTATUS') ?></label>
			<?= $this->lists['state_filter'] ?? '' ?>
		</div>
		<div class="fcpt-filter-actions">
			<button type="submit" class="btn btn-primary"><?= Text::_('JSEARCH_FILTER_SUBMIT') ?></button>
			<a href="<?= Route::_('index.php?option=com_flexicontent&view=prothemes') ?>"
			   class="btn btn-outline-secondary"><?= Text::_('JSEARCH_FILTER_CLEAR') ?></a>
		</div>
	</div>

	<p class="fcpt-result-count" aria-live="polite" aria-atomic="true">
		<?= Text::sprintf('FLEXI_PROTHEME_RESULT_COUNT',
			$this->pagination ? (int) $this->pagination->total : count($this->rows ?? [])
		) ?>
	</p>

	<table class="table table-striped table-hover fcpt-list-table" id="fc-protheme-list">
		<caption class="fcpt-vh"><?= Text::_('FLEXI_PROTHEME_TABLE_CAPTION') ?></caption>
		<thead>
			<tr>
				<th style="width:1%" scope="col"><?= HTMLHelper::_('grid.checkall') ?></th>
				<th scope="col"><?= Text::_('FLEXI_TITLE') ?></th>
				<th scope="col" style="width:12%"><?= Text::_('JSTATUS') ?></th>
				<th scope="col" class="d-none d-md-table-cell" style="width:8%"><?= Text::_('JGRID_HEADING_ORDERING') ?></th>
				<th scope="col" class="d-none d-md-table-cell" style="width:6%"><?= Text::_('JGRID_HEADING_ID') ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if (empty($this->rows)) : ?>
			<tr>
				<td colspan="5" class="text-center py-5">
					<div class="fcpt-empty-state-inline" role="status">
						<h3><?= Text::_('FLEXI_PROTHEME_EMPTY_TITLE') ?></h3>
						<p><?= Text::_('FLEXI_PROTHEME_EMPTY_HINT') ?></p>
						<a href="<?= Route::_('index.php?option=com_flexicontent&view=protheme&layout=choose') ?>"
						   class="btn btn-primary btn-lg">
							<span aria-hidden="true">🎨</span>
							<?= Text::_('FLEXI_PROTHEME_CREATE_FROM_PRESET') ?>
						</a>
					</div>
				</td>
			</tr>
		<?php else : ?>
			<?php foreach ($this->rows as $i => $row) :
				$td = json_decode($row->theme_data ?? '{}', true);
				$accent = $td['colors']['accent'] ?? '';
			?>
			<tr>
				<td><?= HTMLHelper::_('grid.id', $i, $row->id) ?></td>
				<td>
					<?php if ($accent && preg_match('/^#[0-9a-fA-F]{3,8}$/', $accent)) : ?>
					<span class="fcpt-theme-swatch"
					      style="background:<?= htmlspecialchars($accent, ENT_QUOTES) ?>"
					      aria-hidden="true"></span>
					<?php endif; ?>
					<a href="<?= Route::_('index.php?option=com_flexicontent&view=protheme&layout=edit&id=' . (int) $row->id) ?>">
						<?= htmlspecialchars($row->title, ENT_QUOTES) ?>
					</a>
					<?php if ($accent) : ?>
					<small class="text-muted ms-2"><?= htmlspecialchars($accent, ENT_QUOTES) ?></small>
					<?php endif; ?>
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

	<input type="hidden" name="task"       value="">
	<input type="hidden" name="boxchecked" value="0">
	<?= HTMLHelper::_('form.token') ?>
</form>
