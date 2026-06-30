<?php

namespace Amazingbv\StatamicGutenberg\Tests;

use Amazingbv\StatamicGutenberg\Bard\BardBlockRepository;
use Amazingbv\StatamicGutenberg\Blocks\BlockRegistry;
use Amazingbv\StatamicGutenberg\Blocks\BlockRenderer;
use Amazingbv\StatamicGutenberg\GutenbergManager;
use Amazingbv\StatamicGutenberg\Http\Controllers\CP\BardPreviewController;
use Illuminate\Http\Request;

class BardBlockRepositoryTest extends TestCase
{
    private ?string $blueprintsPath = null;
    private ?string $fieldsetsPath = null;
    private ?string $viewsPath = null;

    protected function tearDown(): void
    {
        foreach ([$this->blueprintsPath, $this->fieldsetsPath, $this->viewsPath] as $path) {
            if ($path && is_dir($path)) {
                $this->deleteDirectory($path);
            }
        }

        parent::tearDown();
    }

    public function test_bard_sets_are_discovered_from_blueprints_and_exposed_to_editor(): void
    {
        $this->writeBlueprint('collections/pages/page.yaml', <<<'YAML'
title: Page
tabs:
  main:
    sections:
      -
        fields:
          -
            handle: content
            field:
              type: bard
              sets:
                hero:
                  display: Hero
                  instructions: Hero block
                  fields:
                    -
                      handle: heading
                      field:
                        type: text
                        display: Heading
                        default: Hello
                    -
                      handle: enabled
                      field:
                        type: toggle
                        display: Enabled
                        default: true
YAML);

        $payload = app(BardBlockRepository::class)->editorPayload();

        $this->assertCount(1, $payload);
        $this->assertSame('bard/hero', $payload[0]['name']);
        $this->assertSame('hero', $payload[0]['set']);
        $this->assertSame('pages.content', $payload[0]['source']);
        $this->assertSame('Hero', $payload[0]['metadata']['title']);
        $this->assertSame('bard', explode('/', $payload[0]['metadata']['name'])[0]);
        $this->assertSame('text', $payload[0]['fields'][0]['type']);
        $this->assertSame('toggle', $payload[0]['fields'][1]['type']);
        $this->assertSame(['heading' => 'Hello', 'enabled' => true], $payload[0]['defaults']);
        $this->assertContains('bard/hero', app(BlockRegistry::class)->allowedBlocks(['core/paragraph']));
        $this->assertSame($payload, app(GutenbergManager::class)->editorBardBlocks());
    }

    public function test_conflicting_bard_set_handles_get_source_prefixed_block_names(): void
    {
        $this->writeBlueprint('collections/pages/page.yaml', $this->bardBlueprintYaml('content', 'hero'));
        $this->writeFieldset('shared.yaml', $this->bardFieldsetYaml('content', 'hero'));

        $names = app(BardBlockRepository::class)->names();

        $this->assertContains('bard/pages-content-hero', $names);
        $this->assertContains('bard/fieldset-shared-content-hero', $names);
        $this->assertNotContains('bard/hero', $names);
    }

    public function test_unknown_fieldtype_defaults_preserve_array_data(): void
    {
        $this->writeBlueprint('collections/pages/page.yaml', <<<'YAML'
title: Page
tabs:
  main:
    sections:
      -
        fields:
          -
            handle: content
            field:
              type: bard
              sets:
                data_card:
                  display: Data card
                  fields:
                    -
                      handle: payload
                      field:
                        type: mystery
                        display: Payload
                        default:
                          title: Preserved
                          items:
                            - one
YAML);

        $block = app(BardBlockRepository::class)->find('bard/data-card');

        $this->assertSame('mystery', $block['fields'][0]['type']);
        $this->assertSame(['title' => 'Preserved', 'items' => ['one']], $block['defaults']['payload']);
    }

    public function test_frontend_renderer_and_preview_endpoint_render_bard_blocks(): void
    {
        $this->writeBlueprint('collections/pages/page.yaml', $this->bardBlueprintYaml('content', 'hero'));
        $this->writeView('bard/hero.blade.php', '<section class="bard-hero">{{ $heading }} {{ $values[\'kicker\'] ?? \'\' }}</section>');

        $html = '<!-- wp:bard/hero {"values":{"heading":"Hello","kicker":"World"}} /-->';
        $rendered = (string) app(BlockRenderer::class)->render($html);

        $this->assertStringContainsString('<section class="bard-hero">Hello World</section>', $rendered);

        $request = Request::create(
            '/cp/amazingbv/statamic-gutenberg/bard-preview',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'block' => 'bard/hero',
                'set' => 'hero',
                'source' => 'pages.content',
                'values' => [
                    'heading' => 'Preview',
                    'kicker' => 'HTML',
                ],
            ], JSON_UNESCAPED_SLASHES)
        );

        $payload = app(BardPreviewController::class)
            ->__invoke($request, app(BlockRenderer::class))
            ->getData(true);

        $this->assertSame('<section class="bard-hero">Preview HTML</section>', $payload['html']);
    }

    private function bardBlueprintYaml(string $fieldHandle, string $setHandle): string
    {
        return <<<YAML
title: Page
tabs:
  main:
    sections:
      -
        fields:
          -
            handle: {$fieldHandle}
            field:
              type: bard
              sets:
                {$setHandle}:
                  display: Hero
                  fields:
                    -
                      handle: heading
                      field:
                        type: text
                        display: Heading
YAML;
    }

    private function bardFieldsetYaml(string $fieldHandle, string $setHandle): string
    {
        return <<<YAML
title: Shared
fields:
  -
    handle: {$fieldHandle}
    field:
      type: bard
      sets:
        {$setHandle}:
          display: Hero
          fields:
            -
              handle: heading
              field:
                type: text
                display: Heading
YAML;
    }

    private function writeBlueprint(string $path, string $yaml): void
    {
        $this->blueprintsPath ??= sys_get_temp_dir().'/sgb-bard-blueprints-'.bin2hex(random_bytes(6));
        $this->writeFile($this->blueprintsPath.'/'.$path, $yaml);
        config(['statamic-gutenberg.bard_blocks.blueprints_path' => $this->blueprintsPath]);
        $this->refreshBardInstances();
    }

    private function writeFieldset(string $path, string $yaml): void
    {
        $this->fieldsetsPath ??= sys_get_temp_dir().'/sgb-bard-fieldsets-'.bin2hex(random_bytes(6));
        $this->writeFile($this->fieldsetsPath.'/'.$path, $yaml);
        config(['statamic-gutenberg.bard_blocks.fieldsets_path' => $this->fieldsetsPath]);
        $this->refreshBardInstances();
    }

    private function writeView(string $path, string $contents): void
    {
        $this->viewsPath ??= sys_get_temp_dir().'/sgb-bard-views-'.bin2hex(random_bytes(6));
        $this->writeFile($this->viewsPath.'/'.$path, $contents);
        view()->addLocation($this->viewsPath);
    }

    private function writeFile(string $path, string $contents): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);
    }

    private function refreshBardInstances(): void
    {
        $this->app->forgetInstance(BardBlockRepository::class);
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
