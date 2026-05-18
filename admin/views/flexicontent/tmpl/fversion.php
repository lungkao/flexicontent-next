<?php
/**
* @version 1.5 stable $Id$
* @package Joomla
* @subpackage FLEXIcontent
* @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
* @license GNU/GPL v2
* 
* FLEXIcontent is a derivative work of the excellent QuickFAQ component
* @copyright (C) 2008 Christoph Lukes
* see www.schlu.net for more information
*
* FLEXIcontent is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*/

defined('_JEXEC') or die('Restricted access');
$app = \Joomla\CMS\Factory::getApplication();
$template	= $app->getTemplate();
if ($this->check['connect'] == 0) :
?>
	<div class="fc-update-check fc-update-check--error" role="alert">
		<span class="fc-update-icon icon-warning" aria-hidden="true"></span>
		<div class="fc-update-msg">
			<p class="fc-update-msg-title"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_VERSION'); ?></p>
			<p class="fc-update-msg-text"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_CONNECTION_FAILED'); ?></p>
		</div>
	</div>
<?php
elseif ($this->check['enabled'] == 1) :
	$cur = (int) $this->check['current'];
	if     ($cur ==  0) { $tone = 'success'; $msg_key = 'FLEXI_LATEST_VERSION_INSTALLED';      $icon = 'icon-checkmark'; }
	elseif ($cur == -1) { $tone = 'warn';    $msg_key = 'FLEXI_NEWS_VERSION_COMPONENT';        $icon = 'icon-download'; }
	else                { $tone = 'info';    $msg_key = 'FLEXI_NEWER_THAN_OFFICIAL_INSTALLED'; $icon = 'icon-info'; }
	try {
		$installed_date = \Joomla\CMS\HTML\HTMLHelper::_('date', $this->check['current_creationDate'], 'Y-m-d', 'UTC');
	} catch (Exception $e) {
		$installed_date = $this->check['current_creationDate'];
	}
?>
	<div class="fc-update-check fc-update-check--<?php echo $tone; ?>">
		<div class="fc-update-status">
			<span class="fc-update-icon <?php echo $icon; ?>" aria-hidden="true"></span>
			<div class="fc-update-msg">
				<p class="fc-update-msg-title"><?php echo \Joomla\CMS\Language\Text::_($msg_key); ?></p>
				<?php if ($cur == -1) : ?>
				<a class="fc-update-cta" href="http://www.flexicontent.org/downloads/latest-version.html" target="_blank" rel="noopener noreferrer">
					<?php echo \Joomla\CMS\Language\Text::_('FLEXI_DOWNLOAD'); ?>
					<span class="visually-hidden"><?php echo \Joomla\CMS\Language\Text::_('JOPEN_IN_NEW_WINDOW'); ?></span>
				</a>
				<?php endif; ?>
			</div>
		</div>
		<dl class="fc-update-versions">
			<div class="fc-update-row">
				<dt class="fc-update-label"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_LATEST_VERSION'); ?></dt>
				<dd class="fc-update-value">
					<span class="fc-update-badge fc-update-badge--latest"><?php echo htmlspecialchars($this->check['version'], ENT_QUOTES, 'UTF-8'); ?></span>
					<span class="fc-update-date">
						<span class="fc-update-date-label"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_RELEASED_DATE'); ?>:</span>
						<time><?php echo htmlspecialchars($this->check['released'], ENT_QUOTES, 'UTF-8'); ?></time>
					</span>
				</dd>
			</div>
			<div class="fc-update-row">
				<dt class="fc-update-label"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_INSTALLED_VERSION'); ?></dt>
				<dd class="fc-update-value">
					<span class="fc-update-badge fc-update-badge--<?php echo $tone; ?>"><?php echo htmlspecialchars($this->check['current_version'], ENT_QUOTES, 'UTF-8'); ?></span>
					<span class="fc-update-date">
						<span class="fc-update-date-label"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_RELEASED_DATE'); ?>:</span>
						<time><?php echo htmlspecialchars($installed_date, ENT_QUOTES, 'UTF-8'); ?></time>
					</span>
				</dd>
			</div>
		</dl>
	</div>
<?php
endif;
?>
