<?php
/**
 * audit-pro-layouts.php — Diagnose Pro Template / Pro Layout rows on a
 * live Joomla install. Use when a category or item view renders empty
 * after a preset library upgrade (e.g. 6.1.0-beta.6 → beta.8) so you
 * can tell which DB rows still carry stale mockup placeholders and
 * need to be re-created via the "Change layout" button.
 *
 * Read-only: prints a report to stdout, never writes to the DB.
 *
 * Usage:
 *   php tools/audit-pro-layouts.php
 *
 * Pass --json for machine-readable output instead of the table view.
 *
 * Detection rules (per row):
 *   • EMPTY        — layout_data has no sections, or no rows / cols / elements
 *   • TEXT_ONLY    — all elements are type=text (mockup placeholders)
 *   • STALE_PHRASE — element text contains a known mockup phrase
 *   • MISSING_CORE — no title / introtext / image / created article element
 *   • OK           — layout has real article elements, no stale phrases
 *
 * @package FLEXIcontent
 * @since   6.1.0-beta.8
 */

declare(strict_types=1);

// ── Resolve project root + Joomla configuration ─────────────────────
$root = dirname(__DIR__);
$configFile = $root . '/configuration.php';

if (!is_file($configFile)) {
	fwrite(STDERR, "audit-pro-layouts: configuration.php not found at {$root}\n");
	fwrite(STDERR, "Run this script from inside a Joomla install where com_flexicontent is deployed.\n");
	exit(1);
}

require_once $configFile;

if (!class_exists('JConfig')) {
	fwrite(STDERR, "audit-pro-layouts: configuration.php loaded but JConfig class is missing.\n");
	exit(1);
}

$cfg = new JConfig();

// ── Connect (PDO, no Joomla framework) ──────────────────────────────
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $cfg->host, $cfg->db);

try {
	$pdo = new PDO($dsn, $cfg->user, $cfg->password, [
		PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	]);
} catch (\Throwable $e) {
	fwrite(STDERR, "audit-pro-layouts: DB connect failed: " . $e->getMessage() . "\n");
	exit(2);
}

$prefix = $cfg->dbprefix;
$table  = $prefix . 'flexicontent_pro_layouts';

try {
	$stmt = $pdo->query(sprintf(
		'SELECT id, title, view_scope, state, layout_data, modified
		 FROM `%s` ORDER BY id ASC',
		$table
	));
	$rows = $stmt->fetchAll();
} catch (\Throwable $e) {
	fwrite(STDERR, "audit-pro-layouts: SELECT failed (does the table exist?): " . $e->getMessage() . "\n");
	exit(3);
}

// ── Detection rules ─────────────────────────────────────────────────
$stalePhrases = [
	'Card slot',
	'Featured story',
	'Featured items',
	'Items grid renders here',
	'More from this category',
	'Items list renders below',
	'Primary feature renders here',
	'Editor pick',
	'Lead story',
	'Secondary item slot',
	'Dense items list renders here',
	'Top item from category renders here',
];

$audit = function (array $row) use ($stalePhrases) {
	$flags = [];
	$json  = (string) ($row['layout_data'] ?? '');

	if ($json === '' || $json === 'null') {
		return ['EMPTY'];
	}

	$layout = json_decode($json, true);
	if (!is_array($layout)) {
		return ['EMPTY'];
	}

	$sections = $layout['sections'] ?? [];
	if (empty($sections)) {
		return ['EMPTY'];
	}

	$elementCount = 0;
	$textCount    = 0;
	$articleNames = [];

	foreach ($sections as $sec) {
		foreach (($sec['rows'] ?? []) as $row2) {
			foreach (($row2['cols'] ?? []) as $col) {
				foreach (($col['elements'] ?? []) as $el) {
					$elementCount++;
					$type = (string) ($el['type'] ?? '');
					if ($type === 'text') { $textCount++; }
					if ($type === 'article' && isset($el['name'])) {
						$articleNames[] = (string) $el['name'];
					}
				}
			}
		}
	}

	if ($elementCount === 0) { return ['EMPTY']; }

	if ($textCount === $elementCount) { $flags[] = 'TEXT_ONLY'; }

	foreach ($stalePhrases as $phrase) {
		if (stripos($json, $phrase) !== false) {
			$flags[] = 'STALE_PHRASE';
			break;
		}
	}

	$hasTitle = in_array('title',       $articleNames, true);
	$hasIntro = in_array('introtext',   $articleNames, true);
	$hasImage = in_array('image_intro', $articleNames, true) || in_array('image_full', $articleNames, true);
	$hasDate  = in_array('created',     $articleNames, true) || in_array('publish_up', $articleNames, true);

	if (!$hasTitle || !$hasIntro || !$hasImage || !$hasDate) {
		$flags[] = 'MISSING_CORE';
	}

	if (empty($flags)) { $flags[] = 'OK'; }
	return $flags;
};

// ── Build report ────────────────────────────────────────────────────
$results = [];
foreach ($rows as $row) {
	$results[] = [
		'id'       => (int) $row['id'],
		'title'    => (string) $row['title'],
		'scope'    => (string) $row['view_scope'],
		'state'    => (int) $row['state'],
		'modified' => (string) $row['modified'],
		'flags'    => $audit($row),
	];
}

// ── Output ─────────────────────────────────────────────────────────
$json = in_array('--json', $argv, true);

if ($json) {
	echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
	exit(0);
}

echo "\nPro Layout audit  —  table: {$table}\n";
echo "Total rows: " . count($results) . "\n\n";

if (empty($results)) {
	echo "  (no Pro Layout rows)\n\n";
	exit(0);
}

printf("  %-4s  %-8s  %-5s  %-40s  %s\n", 'ID', 'SCOPE', 'STATE', 'TITLE', 'FLAGS');
printf("  %s\n", str_repeat('-', 100));

$counts = ['OK' => 0, 'EMPTY' => 0, 'TEXT_ONLY' => 0, 'STALE_PHRASE' => 0, 'MISSING_CORE' => 0];

foreach ($results as $r) {
	foreach ($r['flags'] as $f) {
		if (isset($counts[$f])) { $counts[$f]++; }
	}
	$titleShort = mb_substr($r['title'], 0, 40);
	printf(
		"  %-4d  %-8s  %-5d  %-40s  %s\n",
		$r['id'],
		$r['scope'],
		$r['state'],
		$titleShort,
		implode(',', $r['flags'])
	);
}

echo "\n  Summary:\n";
foreach ($counts as $flag => $n) {
	if ($n > 0) {
		printf("    %-13s %3d\n", $flag . ':', $n);
	}
}

echo "\n  Action map:\n";
echo "    OK            — no action.\n";
echo "    EMPTY         — re-create via the chooser, or delete the row.\n";
echo "    TEXT_ONLY     — pre-beta.8 mockup. Use the new \"Change layout\"\n";
echo "                    toolbar button to pick a real preset.\n";
echo "    STALE_PHRASE  — pre-beta.8 mockup. Same fix as TEXT_ONLY.\n";
echo "    MISSING_CORE  — at least one of title/introtext/image/created\n";
echo "                    is absent. Edit manually or swap preset.\n\n";

exit(0);
