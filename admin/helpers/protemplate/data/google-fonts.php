<?php
/**
 * Curated Google Fonts catalog for FLEXIcontent Pro Templates.
 *
 * Returns an associative array keyed by exact Google Fonts family name.
 * Each entry carries: weights[], subsets[], thai (bool), category.
 *
 * Why curated rather than full Google Fonts API list (~1500 families)?
 *   - Picker UX: ~100 popular fonts are scannable; 1500 is overwhelming
 *   - Offline: no Google Fonts API key needed, no runtime fetch
 *   - Privacy: no first-load IP leak to api.googleapis.com
 *   - Maintenance: append a row to add a font, no version-bump dance
 *
 * Adding a font:
 *   1. Append the entry below in the same shape
 *   2. Run the PHPUnit suite to confirm schema validation passes
 *   3. (Optional) test the live load via the admin picker
 *
 * Source: Google Fonts most-used list 2024 + Thai-script families that
 * ship with the official Google Fonts Thai subset. Categories follow
 * Google's own taxonomy (sans-serif / serif / display / handwriting /
 * monospace).
 *
 * Thai-capable fonts are flagged `thai => true`. The loader appends
 * `&subset=thai` when any selected family has this flag.
 *
 * @package FLEXIcontent
 * @since   6.1.0-beta.8
 */

defined('_JEXEC') or die('Restricted access');

return [
	// ── Sans-serif ───────────────────────────────────────────────────
	'Inter'             => ['weights'=>['400','500','600','700','900'],         'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Roboto'            => ['weights'=>['400','500','700','900'],               'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Open Sans'         => ['weights'=>['400','500','600','700','800'],         'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Lato'              => ['weights'=>['400','700','900'],                     'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Montserrat'        => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Poppins'           => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Nunito'            => ['weights'=>['400','600','700','800','900'],         'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Nunito Sans'       => ['weights'=>['400','600','700','800','900'],         'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Raleway'           => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Source Sans 3'     => ['weights'=>['400','600','700','900'],               'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'PT Sans'           => ['weights'=>['400','700'],                           'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Ubuntu'            => ['weights'=>['400','500','700'],                     'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Work Sans'         => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Quicksand'         => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Mulish'            => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Rubik'             => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'DM Sans'           => ['weights'=>['400','500','700'],                     'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Manrope'           => ['weights'=>['400','500','600','700','800'],         'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Plus Jakarta Sans' => ['weights'=>['400','500','600','700','800'],         'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Outfit'            => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Karla'             => ['weights'=>['400','500','600','700','800'],         'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Cabin'             => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Oxygen'            => ['weights'=>['400','700'],                           'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Fira Sans'         => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'IBM Plex Sans'     => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Barlow'            => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Inter Tight'       => ['weights'=>['400','500','600','700','900'],         'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Space Grotesk'     => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Archivo'           => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Bricolage Grotesque'=> ['weights'=>['400','500','600','700','800'],        'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Albert Sans'       => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Be Vietnam Pro'    => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext','vietnamese'], 'thai'=>false, 'category'=>'sans-serif'],
	'Geist'             => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Onest'             => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Figtree'           => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Hanken Grotesk'    => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],
	'Lexend'            => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'sans-serif'],

	// ── Serif ────────────────────────────────────────────────────────
	'Playfair Display'  => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'serif'],
	'Merriweather'      => ['weights'=>['400','700','900'],                     'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'serif'],
	'Lora'              => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'serif'],
	'PT Serif'          => ['weights'=>['400','700'],                           'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'serif'],
	'Crimson Pro'       => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'serif'],
	'Cormorant Garamond'=> ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'serif'],
	'Source Serif 4'    => ['weights'=>['400','600','700','900'],               'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'serif'],
	'EB Garamond'       => ['weights'=>['400','500','600','700','800'],         'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'serif'],
	'Bitter'            => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'serif'],
	'Libre Baskerville' => ['weights'=>['400','700'],                           'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'serif'],
	'Libre Caslon Text' => ['weights'=>['400','700'],                           'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'serif'],
	'Spectral'          => ['weights'=>['400','500','600','700','800'],         'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'serif'],
	'Cardo'             => ['weights'=>['400','700'],                           'subsets'=>['latin','latin-ext','greek'], 'thai'=>false, 'category'=>'serif'],
	'Vollkorn'          => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'serif'],
	'Cormorant'         => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'serif'],
	'Fraunces'          => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'serif'],
	'Newsreader'        => ['weights'=>['400','500','600','700','800'],         'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'serif'],
	'Domine'            => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'serif'],

	// ── Display ──────────────────────────────────────────────────────
	'Bebas Neue'        => ['weights'=>['400'],                                 'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'display'],
	'Oswald'            => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'display'],
	'Anton'             => ['weights'=>['400'],                                 'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'display'],
	'Righteous'         => ['weights'=>['400'],                                 'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'display'],
	'Abril Fatface'     => ['weights'=>['400'],                                 'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'display'],
	'Yeseva One'        => ['weights'=>['400'],                                 'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'display'],
	'DM Serif Display'  => ['weights'=>['400'],                                 'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'display'],
	'Bungee'            => ['weights'=>['400'],                                 'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'display'],
	'Comfortaa'         => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'display'],
	'Fredoka'           => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'display'],

	// ── Handwriting ──────────────────────────────────────────────────
	'Dancing Script'    => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'handwriting'],
	'Pacifico'          => ['weights'=>['400'],                                 'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'handwriting'],
	'Caveat'            => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'handwriting'],
	'Sacramento'        => ['weights'=>['400'],                                 'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'handwriting'],
	'Great Vibes'       => ['weights'=>['400'],                                 'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'handwriting'],
	'Satisfy'           => ['weights'=>['400'],                                 'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'handwriting'],

	// ── Monospace ────────────────────────────────────────────────────
	'JetBrains Mono'    => ['weights'=>['400','500','600','700','800'],         'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'monospace'],
	'Fira Code'         => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'monospace'],
	'Source Code Pro'   => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'monospace'],
	'IBM Plex Mono'     => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'monospace'],
	'Space Mono'        => ['weights'=>['400','700'],                           'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'monospace'],
	'Roboto Mono'       => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext'],         'thai'=>false, 'category'=>'monospace'],

	// ── Thai-script ──────────────────────────────────────────────────
	'Sarabun'           => ['weights'=>['400','500','600','700','800'],         'subsets'=>['latin','latin-ext','thai'],  'thai'=>true,  'category'=>'sans-serif'],
	'Prompt'            => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext','thai','vietnamese'], 'thai'=>true,  'category'=>'sans-serif'],
	'Kanit'             => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext','thai','vietnamese'], 'thai'=>true,  'category'=>'sans-serif'],
	'Mitr'              => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext','thai','vietnamese'], 'thai'=>true,  'category'=>'sans-serif'],
	'Noto Sans Thai'    => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext','thai'],  'thai'=>true,  'category'=>'sans-serif'],
	'Noto Serif Thai'   => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext','thai'],  'thai'=>true,  'category'=>'serif'],
	'IBM Plex Sans Thai'=> ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext','thai'],  'thai'=>true,  'category'=>'sans-serif'],
	'IBM Plex Sans Thai Looped' => ['weights'=>['400','500','600','700'],       'subsets'=>['latin','latin-ext','thai'],  'thai'=>true,  'category'=>'sans-serif'],
	'Bai Jamjuree'      => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext','thai','vietnamese'], 'thai'=>true,  'category'=>'sans-serif'],
	'Chakra Petch'      => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext','thai','vietnamese'], 'thai'=>true,  'category'=>'sans-serif'],
	'K2D'               => ['weights'=>['400','500','600','700','800'],         'subsets'=>['latin','latin-ext','thai','vietnamese'], 'thai'=>true,  'category'=>'sans-serif'],
	'Krub'              => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext','thai','vietnamese'], 'thai'=>true,  'category'=>'sans-serif'],
	'Maitree'           => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext','thai'],  'thai'=>true,  'category'=>'serif'],
	'Mali'              => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext','thai','vietnamese'], 'thai'=>true,  'category'=>'handwriting'],
	'Niramit'           => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext','thai','vietnamese'], 'thai'=>true,  'category'=>'sans-serif'],
	'Pridi'             => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','thai','vietnamese'], 'thai'=>true,  'category'=>'serif'],
	'Sriracha'          => ['weights'=>['400'],                                 'subsets'=>['latin','latin-ext','thai','vietnamese'], 'thai'=>true,  'category'=>'handwriting'],
	'Taviraj'           => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext','thai','vietnamese'], 'thai'=>true,  'category'=>'serif'],
	'Trirong'           => ['weights'=>['400','500','600','700','800','900'],   'subsets'=>['latin','latin-ext','thai','vietnamese'], 'thai'=>true,  'category'=>'serif'],
	'Charm'             => ['weights'=>['400','700'],                           'subsets'=>['latin','thai'],              'thai'=>true,  'category'=>'handwriting'],
	'Athiti'            => ['weights'=>['400','500','600','700'],               'subsets'=>['latin','latin-ext','thai'],  'thai'=>true,  'category'=>'sans-serif'],
	'Itim'              => ['weights'=>['400'],                                 'subsets'=>['latin','latin-ext','thai','vietnamese'], 'thai'=>true,  'category'=>'handwriting'],
];
