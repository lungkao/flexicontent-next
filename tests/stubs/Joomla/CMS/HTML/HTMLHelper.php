<?php
/**
 * Test stub for Joomla\CMS\HTML\HTMLHelper.
 *
 * Renderer.php calls addIncludePath() at file load and _('date', ...)
 * for date formatting. Stub is intentionally minimal — only the calls
 * the Renderer makes — so unit tests can exercise the helper without
 * Joomla CMS in scope.
 */

namespace Joomla\CMS\HTML;

if (!class_exists('Joomla\\CMS\\HTML\\HTMLHelper', false)) {
	class HTMLHelper
	{
		public static function addIncludePath(string $path = ''): void
		{
		}

		/**
		 * Minimal stand-in for the 'date' helper used by Renderer::renderDate.
		 * Production formats via current Joomla locale; the stub returns
		 * the raw datetime string so tests get deterministic output.
		 */
		public static function _(string $key, $raw = '', string $format = '')
		{
			if ($key === 'date') {
				return (string) $raw;
			}
			return '';
		}
	}
}
