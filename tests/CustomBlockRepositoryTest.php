<?php

namespace Amazingbv\StatamicGutenberg\Tests;

use Amazingbv\StatamicGutenberg\Blocks\BlockRegistry;
use Amazingbv\StatamicGutenberg\Blocks\BlockRenderer;
use Amazingbv\StatamicGutenberg\CustomBlocks\CustomBlockRepository;
use Amazingbv\StatamicGutenberg\GutenbergManager;

class CustomBlockRepositoryTest extends TestCase
{
    private ?string $blocksPath = null;

    protected function tearDown(): void
    {
        if ($this->blocksPath && is_dir($this->blocksPath)) {
            $this->deleteDirectory($this->blocksPath);
        }

        parent::tearDown();
    }

    public function test_custom_blocks_are_discovered_and_exposed_to_editor_and_frontend(): void
    {
        $this->writeCustomBlock('custom-card', [
            'apiVersion' => 3,
            'name' => 'amazing/card',
            'title' => 'Card',
            'category' => 'design',
            'attributes' => [
                'heading' => ['type' => 'string'],
            ],
            'editorScript' => 'file:./block.js',
            'style' => 'file:./block.css',
            'viewScript' => 'file:./view.js',
            'render' => 'file:./block.php',
        ], [
            'block.js' => 'window.__customCardEditorLoaded = true;',
            'block.css' => '.wp-block-amazing-card { color: red; }',
            'view.js' => 'window.__customCardViewLoaded = true;',
            'block.php' => '<?php return "";',
        ]);

        $payload = app(CustomBlockRepository::class)->editorPayload();
        $manager = app(GutenbergManager::class);

        $this->assertSame('amazing/card', $payload[0]['name']);
        $this->assertSame('Card', $payload[0]['metadata']['title']);
        $this->assertStringContainsString('/vendor/statamic-gutenberg/blocks/custom-card/block.js', $payload[0]['editorScripts'][0]['src']);
        $this->assertStringContainsString('/vendor/statamic-gutenberg/blocks/custom-card/block.css', $payload[0]['editorStyles'][0]);
        $this->assertContains('amazing/card', app(BlockRegistry::class)->allowedBlocks(['core/paragraph']));
        $this->assertStringContainsString('/vendor/statamic-gutenberg/blocks/custom-card/block.css', (string) $manager->frontendStyles());
        $this->assertStringContainsString('<script src="/vendor/statamic-gutenberg/blocks/custom-card/view.js?ver=', (string) $manager->frontendScripts());
    }

    public function test_custom_block_asset_php_version_is_used_for_wordpress_script_builds(): void
    {
        $this->writeCustomBlock('custom-card', [
            'apiVersion' => 3,
            'name' => 'amazing/card',
            'title' => 'Card',
            'editorScript' => 'file:./index.js',
        ], [
            'index.js' => 'window.__customCardEditorLoaded = true;',
            'index.asset.php' => '<?php return ["dependencies" => ["wp-blocks"], "version" => "abc123"];',
        ]);

        $payload = app(CustomBlockRepository::class)->editorPayload();

        $this->assertSame('/vendor/statamic-gutenberg/blocks/custom-card/index.js?ver=abc123', $payload[0]['editorScripts'][0]['src']);
    }

    public function test_custom_dynamic_block_php_renders_with_wordpress_like_variables_and_wrapper_attributes(): void
    {
        $this->writeCustomBlock('custom-card', [
            'apiVersion' => 3,
            'name' => 'amazing/card',
            'title' => 'Card',
            'render' => 'file:./block.php',
        ], [
            'block.php' => <<<'PHP'
<?php

return '<section'.get_block_wrapper_attributes(['class' => 'custom-card']).'><h2>'.e($attributes['heading'] ?? '').'</h2>'.$content.'</section>';
PHP,
        ]);

        $html = '<!-- wp:amazing/card {"heading":"Hello","align":"wide","className":"extra","anchor":"card-one"} --><!-- wp:paragraph --><p>Inner</p><!-- /wp:paragraph --><!-- /wp:amazing/card -->';
        $rendered = (string) app(BlockRenderer::class)->render($html, [
            'allowed_blocks' => ['core/paragraph'],
        ]);

        $this->assertStringContainsString('class="wp-block-amazing-card alignwide extra custom-card"', $rendered);
        $this->assertStringContainsString('id="card-one"', $rendered);
        $this->assertStringContainsString('<h2>Hello</h2>', $rendered);
        $this->assertStringContainsString('<p>Inner</p>', $rendered);
    }

    public function test_custom_dynamic_block_wrapper_attributes_include_common_style_supports(): void
    {
        $this->writeCustomBlock('custom-card', [
            'apiVersion' => 3,
            'name' => 'amazing/card',
            'title' => 'Card',
            'render' => 'file:./block.php',
        ], [
            'block.php' => <<<'PHP'
<?php

return '<section'.get_block_wrapper_attributes(['class' => 'custom-card']).'>'.$content.'</section>';
PHP,
        ]);

        $html = '<!-- wp:amazing/card {"textColor":"primary","backgroundColor":"light","fontSize":"large","style":{"typography":{"textAlign":"center","fontSize":"clamp(1rem, 2vw, 2rem)"},"spacing":{"padding":{"top":"var:preset|spacing|40"},"margin":{"bottom":"2rem"}},"color":{"text":"var:preset|color|secondary","background":"#fff"},"border":{"radius":"8px","color":"javascript:alert(1)"},"shadow":"var:preset|shadow|natural"}} --><!-- wp:paragraph --><p>Inner</p><!-- /wp:paragraph --><!-- /wp:amazing/card -->';
        $rendered = (string) app(BlockRenderer::class)->render($html, [
            'allowed_blocks' => ['core/paragraph'],
        ]);

        $this->assertStringContainsString('has-primary-color', $rendered);
        $this->assertStringContainsString('has-text-color', $rendered);
        $this->assertStringContainsString('has-light-background-color', $rendered);
        $this->assertStringContainsString('has-background', $rendered);
        $this->assertStringContainsString('has-large-font-size', $rendered);
        $this->assertStringContainsString('has-text-align-center', $rendered);
        $this->assertStringContainsString('has-natural-box-shadow', $rendered);
        $this->assertStringContainsString('color: var(--wp--preset--color--secondary)', $rendered);
        $this->assertStringContainsString('background-color: #fff', $rendered);
        $this->assertStringContainsString('margin-bottom: 2rem', $rendered);
        $this->assertStringContainsString('padding-top: var(--wp--preset--spacing--40)', $rendered);
        $this->assertStringContainsString('font-size: clamp(1rem, 2vw, 2rem)', $rendered);
        $this->assertStringContainsString('border-radius: 8px', $rendered);
        $this->assertStringContainsString('box-shadow: var(--wp--preset--shadow--natural)', $rendered);
        $this->assertStringNotContainsString('javascript:', $rendered);
    }

    public function test_custom_dynamic_block_supports_wordpress_render_file_block_object_and_helpers(): void
    {
        $this->writeCustomBlock('vertical-tabs', [
            'apiVersion' => 3,
            'name' => 'amazing/vertical-tabs',
            'title' => 'Vertical Tabs',
            'render' => 'file:./render.php',
        ], [
            'render.php' => <<<'PHP'
<?php

$items = [];

foreach ($block->inner_blocks as $child_block) {
    if ('amazing/vertical-tab' !== ($child_block->parsed_block['blockName'] ?? '')) {
        continue;
    }

    $items[] = sprintf(
        '<article data-title="%s">%s</article>',
        esc_attr(wp_strip_all_tags($child_block->attributes['title'] ?? '')),
        $child_block->render()
    );
}

return '<section'.get_block_wrapper_attributes([
    'class' => 'wp-render-file',
    'id' => wp_unique_id('tabs-'),
]).'>'.implode('', $items).'</section>';
PHP,
        ]);

        $this->writeCustomBlock('vertical-tab', [
            'apiVersion' => 3,
            'name' => 'amazing/vertical-tab',
            'title' => 'Vertical Tab',
        ]);

        $html = '<!-- wp:amazing/vertical-tabs --><!-- wp:amazing/vertical-tab {"title":"<b>First</b>"} --><!-- wp:paragraph --><p>Inner</p><!-- /wp:paragraph --><!-- /wp:amazing/vertical-tab --><!-- /wp:amazing/vertical-tabs -->';
        $rendered = (string) app(BlockRenderer::class)->render($html, [
            'allowed_blocks' => ['core/paragraph'],
        ]);

        $this->assertStringContainsString('class="wp-block-amazing-vertical-tabs wp-render-file"', $rendered);
        $this->assertStringContainsString('id="tabs-', $rendered);
        $this->assertStringContainsString('data-title="First"', $rendered);
        $this->assertStringContainsString('<p>Inner</p>', $rendered);
    }

    public function test_custom_block_assets_are_served_without_exposing_php_render_files(): void
    {
        $this->writeCustomBlock('custom-card', [
            'apiVersion' => 3,
            'name' => 'amazing/card',
            'title' => 'Card',
        ], [
            'block.css' => '.wp-block-amazing-card { color: red; }',
            'block.php' => '<?php return "";',
        ]);

        $this->get('/vendor/statamic-gutenberg/blocks/custom-card/block.css')
            ->assertOk()
            ->assertHeader('cache-control', 'max-age=31536000, public')
            ->assertHeader('content-type', 'text/css; charset=utf-8');

        $this->get('/vendor/statamic-gutenberg/blocks/custom-card/block.php')
            ->assertNotFound();
    }

    private function writeCustomBlock(string $slug, array $metadata, array $files = []): void
    {
        $this->blocksPath ??= sys_get_temp_dir().'/sgb-custom-blocks-'.bin2hex(random_bytes(6));
        $directory = $this->blocksPath.'/'.$slug;

        mkdir($directory, 0777, true);
        file_put_contents($directory.'/block.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        foreach ($files as $path => $contents) {
            $file = $directory.'/'.ltrim($path, '/');
            if (! is_dir(dirname($file))) {
                mkdir(dirname($file), 0777, true);
            }
            file_put_contents($file, $contents);
        }

        config(['statamic-gutenberg.custom_blocks_path' => $this->blocksPath]);
        $this->refreshCustomBlockInstances();
    }

    private function refreshCustomBlockInstances(): void
    {
        $this->app->forgetInstance(CustomBlockRepository::class);
        $this->app->forgetInstance(BlockRegistry::class);
        $this->app->forgetInstance(BlockRenderer::class);
        $this->app->forgetInstance(GutenbergManager::class);
    }

    private function deleteDirectory(string $directory): void
    {
        foreach (array_diff(scandir($directory) ?: [], ['.', '..']) as $item) {
            $path = $directory.'/'.$item;

            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}
