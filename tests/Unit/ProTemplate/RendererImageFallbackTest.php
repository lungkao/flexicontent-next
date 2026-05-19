<?php
/**
 * Unit tests for FlexicontentProTemplateRenderer image fallback logic
 * (release/6.1.0-beta.8+ regression: empty <img> on category teaser).
 *
 * The Renderer's renderImage() reads $item->images for image_intro /
 * image_fulltext. Two failure modes prompted these guards:
 *
 *   1) com_content stores `images` as a JSON STRING in the DB. Callers
 *      sometimes hand the Renderer the raw string (no decode). Casting
 *      a string to array via (array)$str gave [0 => '...'] — broken.
 *   2) Items relying on the FlexicontentFields custom 'image' field do
 *      not populate com_content's $item->images at all. The Renderer
 *      now falls back to extracting the first <img src> from introtext
 *      / fulltext so the teaser still shows a thumbnail.
 *
 * Both helpers are exercised through render() — no protected access via
 * Reflection — to keep the contract black-box.
 *
 * @package  FLEXIcontent
 * @since    6.1.0-beta.8
 */

namespace FLEXIcontent\Tests\Unit\ProTemplate;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3)
	. '/admin/helpers/protemplate/Renderer.php';

class RendererImageFallbackTest extends TestCase
{
	/** Minimal layout: one section/row/col, single image_intro element. */
	private function imageOnlyLayout(): array
	{
		return [
			'settings' => ['theme' => 'clean', 'width' => 'default', 'spacing' => 'normal'],
			'sections' => [[
				'id' => 's1', 'label' => 'sec', 'class' => '', 'appearance' => 'plain',
				'rows' => [[
					'id' => 'r1', 'class' => '', 'appearance' => 'default',
					'cols' => [[
						'id' => 'c1', 'width' => 12, 'class' => '', 'appearance' => 'default',
						'elements' => [
							[
								'type'    => 'article',
								'name'    => 'image_intro',
								'tag'     => 'figure',
								'variant' => 'card',
								'class'   => '',
							],
						],
					]],
				]],
			]],
		];
	}

	private function item(array $props): \stdClass
	{
		$o = new \stdClass();
		$o->id    = $props['id']    ?? 42;
		$o->title = $props['title'] ?? 'Sample item';
		foreach ($props as $k => $v) {
			$o->{$k} = $v;
		}
		return $o;
	}

	public function testRendersImageFromAssocArrayImages(): void
	{
		$item = $this->item([
			'images' => [
				'image_intro'     => 'images/sample.jpg',
				'image_intro_alt' => 'a description',
			],
		]);
		$r = new \FlexicontentProTemplateRenderer();
		$out = $r->render($this->imageOnlyLayout(), $item, 'category');

		$this->assertStringContainsString('src="images/sample.jpg"', $out);
		$this->assertStringContainsString('<figure class="fcpt-image-intro">', $out);
	}

	public function testRendersImageFromJsonStringImages(): void
	{
		// com_content stores images as a JSON string. Renderer must
		// normalize before lookup — pre-fix this produced no <img>.
		$item = $this->item([
			'images' => json_encode([
				'image_intro' => 'images/from-json.jpg',
			]),
		]);
		$r = new \FlexicontentProTemplateRenderer();
		$out = $r->render($this->imageOnlyLayout(), $item, 'category');

		$this->assertStringContainsString('src="images/from-json.jpg"', $out);
	}

	public function testRendersImageFromStdClassImages(): void
	{
		// The image field plugin's onAllFieldsPostDataValidated stores
		// $item->images as a decoded stdClass before re-encoding. If a
		// caller hands us the stdClass form, normalizeImages must accept it.
		$imgs = new \stdClass();
		$imgs->image_intro = 'images/from-obj.jpg';
		$item = $this->item(['images' => $imgs]);

		$r = new \FlexicontentProTemplateRenderer();
		$out = $r->render($this->imageOnlyLayout(), $item, 'category');

		$this->assertStringContainsString('src="images/from-obj.jpg"', $out);
	}

	public function testFallsBackToIntrotextImage(): void
	{
		// FlexicontentFields image field — $item->images stays empty,
		// but introtext is HTML with an <img> at the top. The renderer
		// must surface that image in the teaser.
		$item = $this->item([
			'images'    => null,
			'introtext' => '<p><img src="images/from-intro.jpg" alt="x"/> '
				. 'Some article preview text.</p>',
		]);
		$r = new \FlexicontentProTemplateRenderer();
		$out = $r->render($this->imageOnlyLayout(), $item, 'category');

		$this->assertStringContainsString('src="images/from-intro.jpg"', $out);
	}

	public function testFallsBackToFulltextImageWhenIntroEmpty(): void
	{
		$item = $this->item([
			'images'    => '',
			'introtext' => '',
			'fulltext'  => '<div><img src="images/from-full.jpg" alt=""></div>',
		]);
		$r = new \FlexicontentProTemplateRenderer();
		$out = $r->render($this->imageOnlyLayout(), $item, 'category');

		$this->assertStringContainsString('src="images/from-full.jpg"', $out);
	}

	public function testRendersEmptyWhenNoImageAnywhere(): void
	{
		$item = $this->item([
			'images'    => null,
			'introtext' => '<p>No images at all.</p>',
			'fulltext'  => '',
		]);
		$r = new \FlexicontentProTemplateRenderer();
		$out = $r->render($this->imageOnlyLayout(), $item, 'category');

		// renderImage returns '' → renderCol returns '' → renderRow
		// returns '' → renderSection returns '' → no <figure>/<img> survives.
		$this->assertStringNotContainsString('<img', $out);
		$this->assertStringNotContainsString('<figure', $out);
	}

	public function testSkipsDataUriInFallback(): void
	{
		// data: URIs in introtext are usually tracking pixels / inline
		// payloads, not real hero images. Must not be picked up.
		$item = $this->item([
			'images'    => '',
			'introtext' => '<img src="data:image/gif;base64,R0lGODlh" alt="">'
				. '<img src="images/real-after.jpg" alt="">',
		]);
		$r = new \FlexicontentProTemplateRenderer();
		$out = $r->render($this->imageOnlyLayout(), $item, 'category');

		$this->assertStringContainsString('src="images/real-after.jpg"', $out);
		$this->assertStringNotContainsString('data:image/gif', $out);
	}

	public function testCategoryContextForcesEmptyAlt(): void
	{
		// In category context the adjacent h2 (title) names the card.
		// Renderer must emit alt="" for the image to avoid double-announce.
		$item = $this->item([
			'images' => ['image_intro' => 'images/foo.jpg'],
		]);
		$r = new \FlexicontentProTemplateRenderer();
		$out = $r->render($this->imageOnlyLayout(), $item, 'category');

		$this->assertMatchesRegularExpression(
			'#<img[^>]*\balt=""#',
			$out,
			'category context must force empty alt; adjacent h2 names the card'
		);
	}

	/**
	 * Source-text regression: the production Renderer must keep the
	 * normalizeImages() + extractImageFromContent() helpers.
	 */
	public function testProductionRendererContainsFallbackHelpers(): void
	{
		$src = file_get_contents(dirname(__DIR__, 3)
			. '/admin/helpers/protemplate/Renderer.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('protected function normalizeImages', $src);
		$this->assertStringContainsString('protected function extractImageFromContent', $src);
		$this->assertStringContainsString('$this->extractImageFromContent()', $src);
	}
}
