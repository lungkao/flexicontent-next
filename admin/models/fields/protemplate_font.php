<?php
/**
 * @package         FLEXIcontent
 * @subpackage      Pro Templates — Searchable Google Fonts picker form field
 *
 * @author          FLEXIcontent Team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * Joomla form field type that renders an accessible ARIA 1.2 combobox
 * bound to the curated Google Fonts catalog (admin/helpers/protemplate/
 * data/google-fonts.php). Designers can type to filter, arrow-key
 * through suggestions, and see a live preview of the selected family.
 *
 * Field usage in an XML form:
 *   <field
 *     type="protemplate_font"
 *     name="family"
 *     label="Body font"
 *     default="Inter, system-ui, sans-serif"
 *     category="sans-serif"
 *     thai="true"
 *   />
 *
 * The saved VALUE is the full CSS font-family stack — e.g.
 *   `Inter, system-ui, sans-serif`
 * — so the existing Pro Layout typography contract is preserved and
 * the Renderer / Fonts loader keep working without migration.
 *
 * Accessibility (WCAG 2.2 AA, cleared by accessibility-lead 2026-05-19):
 *   - ARIA 1.2 combobox pattern (role=combobox, aria-expanded,
 *     aria-controls, aria-autocomplete=list, aria-activedescendant)
 *   - Live region announces filter result count
 *   - Preview pane has aria-live=polite + multi-script sample (Thai +
 *     Latin) for Thai-capable fonts
 *
 * @since 6.1.0-beta.8
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

if (class_exists('JFormFieldProtemplate_font', false)) {
	return;
}

class JFormFieldProtemplate_font extends FormField
{
	protected $type = 'Protemplate_font';

	protected function getInput()
	{
		require_once JPATH_ADMINISTRATOR
			. '/components/com_flexicontent/helpers/protemplate/Fonts.php';

		$catalog = \FlexicontentProTemplateFonts::catalog();

		$categoryFilter = (string) $this->element['category'];
		$thaiOnly       = (string) $this->element['thai'] === 'true';

		if ($categoryFilter !== '' || $thaiOnly) {
			$filtered = [];
			foreach ($catalog as $name => $meta) {
				if ($categoryFilter !== '' && ($meta['category'] ?? '') !== $categoryFilter) {
					continue;
				}
				if ($thaiOnly && empty($meta['thai'])) {
					continue;
				}
				$filtered[$name] = $meta;
			}
			$catalog = $filtered;
		}

		$jsCatalog = [];
		foreach ($catalog as $name => $meta) {
			$jsCatalog[] = [
				'name'     => $name,
				'category' => $meta['category'] ?? 'sans-serif',
				'thai'     => !empty($meta['thai']),
			];
		}

		$value     = (string) $this->value;
		$pickedName = '';
		foreach (\FlexicontentProTemplateFonts::splitStack($value) as $name) {
			if (isset($catalog[$name])) {
				$pickedName = $name;
				break;
			}
		}

		try {
			$wam = \Joomla\CMS\Factory::getDocument()->getWebAssetManager();
			$wam->registerAndUseStyle(
				'fc-protemplate-font-picker',
				Uri::root() . 'administrator/components/com_flexicontent/assets/css/protemplate_font_picker.css',
				['version' => 'auto']
			);
			$wam->registerAndUseScript(
				'fc-protemplate-font-picker',
				Uri::root() . 'administrator/components/com_flexicontent/assets/js/protemplate_font_picker.js',
				['version' => 'auto'],
				['defer' => true]
			);
		} catch (\Throwable $e) {
			// No WAM (rare admin contexts). Picker JS still works if assets
			// are loaded by the host view; degrades to plain hidden input.
		}

		$id          = $this->id;
		$name        = $this->name;
		$comboboxId  = $id . '-combobox';
		$listboxId   = $id . '-listbox';
		$liveId      = $id . '-live';
		$previewId   = $id . '-preview';
		$catalogJson = htmlspecialchars(
			json_encode($jsCatalog, JSON_UNESCAPED_UNICODE),
			ENT_QUOTES,
			'UTF-8'
		);

		$labels = [
			'placeholder' => Text::_('FLEXI_PROTEMPLATE_FONT_PICKER_PLACEHOLDER') ?: 'Type to search fonts…',
			'preview'     => Text::_('FLEXI_PROTEMPLATE_FONT_PICKER_PREVIEW')     ?: 'Preview',
			'no_results'  => Text::_('FLEXI_PROTEMPLATE_FONT_PICKER_NO_RESULTS')  ?: 'No fonts match this filter',
			'results'     => Text::_('FLEXI_PROTEMPLATE_FONT_PICKER_RESULTS')     ?: '%d fonts match',
			'sample'      => Text::_('FLEXI_PROTEMPLATE_FONT_PICKER_SAMPLE')      ?: 'The quick brown fox jumps over the lazy dog',
			'sample_thai' => Text::_('FLEXI_PROTEMPLATE_FONT_PICKER_SAMPLE_THAI') ?: 'นกอินทรีเหินบินอยู่บนยอดเมฆ ตัวอักษรไทย ๐๑๒๓',
			'clear'       => Text::_('FLEXI_PROTEMPLATE_FONT_PICKER_CLEAR')       ?: 'Clear selection',
		];

		$html = [];
		$html[] = '<div class="fcpt-font-picker"'
			. ' data-fcpt-font-picker'
			. ' data-fcpt-input="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"'
			. ' data-fcpt-catalog="' . $catalogJson . '"'
			. ' data-fcpt-labels="' . htmlspecialchars(json_encode($labels), ENT_QUOTES, 'UTF-8') . '">';

		$html[] = '<input type="hidden" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"'
			. ' name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"'
			. ' value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"'
			. ' data-fcpt-stack-value>';

		$html[] = '<input type="text"'
			. ' id="' . htmlspecialchars($comboboxId, ENT_QUOTES, 'UTF-8') . '"'
			. ' class="fcpt-font-picker-input form-control"'
			. ' role="combobox"'
			. ' aria-expanded="false"'
			. ' aria-controls="' . htmlspecialchars($listboxId, ENT_QUOTES, 'UTF-8') . '"'
			. ' aria-autocomplete="list"'
			. ' aria-label="' . htmlspecialchars((string) $this->label, ENT_QUOTES, 'UTF-8') . '"'
			. ' autocomplete="off"'
			. ' spellcheck="false"'
			. ' placeholder="' . htmlspecialchars($labels['placeholder'], ENT_QUOTES, 'UTF-8') . '"'
			. ' value="' . htmlspecialchars($pickedName, ENT_QUOTES, 'UTF-8') . '"'
			. ' data-fcpt-combobox>';

		$html[] = '<button type="button" class="fcpt-font-picker-clear"'
			. ' aria-label="' . htmlspecialchars($labels['clear'], ENT_QUOTES, 'UTF-8') . '"'
			. ' data-fcpt-clear>&times;</button>';

		$html[] = '<ul id="' . htmlspecialchars($listboxId, ENT_QUOTES, 'UTF-8') . '"'
			. ' class="fcpt-font-picker-listbox"'
			. ' role="listbox"'
			. ' tabindex="-1"'
			. ' aria-label="' . htmlspecialchars((string) $this->label, ENT_QUOTES, 'UTF-8') . '"'
			. ' hidden'
			. ' data-fcpt-listbox></ul>';

		$html[] = '<div id="' . htmlspecialchars($liveId, ENT_QUOTES, 'UTF-8') . '"'
			. ' class="fcpt-font-picker-live"'
			. ' role="status"'
			. ' aria-live="polite"'
			. ' aria-atomic="true"'
			. ' data-fcpt-live></div>';

		$html[] = '<div id="' . htmlspecialchars($previewId, ENT_QUOTES, 'UTF-8') . '"'
			. ' class="fcpt-font-picker-preview"'
			. ' aria-live="polite"'
			. ' data-fcpt-preview>';
		$html[] = '<span class="fcpt-font-picker-preview-label">'
			. htmlspecialchars($labels['preview'], ENT_QUOTES, 'UTF-8') . '</span>';
		$html[] = '<p class="fcpt-font-picker-preview-sample" lang="en" data-fcpt-preview-sample>'
			. htmlspecialchars($labels['sample'], ENT_QUOTES, 'UTF-8') . '</p>';
		$html[] = '<p class="fcpt-font-picker-preview-thai" lang="th" data-fcpt-preview-thai>'
			. htmlspecialchars($labels['sample_thai'], ENT_QUOTES, 'UTF-8') . '</p>';
		$html[] = '</div>';

		$html[] = '</div>';

		return implode("\n", $html);
	}
}
