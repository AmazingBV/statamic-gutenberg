<?php

namespace Amazingbv\StatamicGutenberg\Fieldtypes;

use Amazingbv\StatamicGutenberg\GutenbergManager;
use Statamic\Facades\AssetContainer;
use Statamic\Fields\Fieldtype;

class Gutenberg extends Fieldtype
{
    protected $categories = ['text', 'media'];
    protected $icon = 'fieldtype-bard';
    protected $keywords = ['blocks', 'editor', 'wordpress'];

    protected function configFieldItems(): array
    {
        return [
            [
                'display' => 'Gutenberg',
                'fields' => [
                    'allowed_blocks' => [
                        'display' => 'Allowed Blocks',
                        'instructions' => 'Use Gutenberg block names such as core/paragraph or statamic/hero.',
                        'type' => 'list',
                        'default' => config('statamic-gutenberg.allowed_blocks', []),
                    ],
                    'assets_container' => [
                        'display' => 'Assets Container',
                        'type' => 'asset_container',
                        'max_items' => 1,
                        'mode' => 'select',
                        'default' => config('statamic-gutenberg.assets_container', 'assets'),
                        'width' => '50',
                    ],
                    'render_mode' => [
                        'display' => 'Render Mode',
                        'type' => 'select',
                        'default' => config('statamic-gutenberg.render_mode', 'blade'),
                        'options' => [
                            'blade' => 'Blade mappings',
                            'raw' => 'Sanitized saved HTML',
                        ],
                        'width' => '50',
                    ],
                    'allow_unknown_blocks' => [
                        'display' => 'Allow Unknown Blocks',
                        'type' => 'toggle',
                        'default' => config('statamic-gutenberg.allow_unknown_blocks', false),
                        'width' => '50',
                    ],
                    'sanitize_html' => [
                        'display' => 'Sanitize HTML',
                        'type' => 'toggle',
                        'default' => config('statamic-gutenberg.sanitize_html', true),
                        'width' => '50',
                    ],
                ],
            ],
        ];
    }

    public function defaultValue()
    {
        return '';
    }

    public function preload()
    {
        $container = $this->config('assets_container', config('statamic-gutenberg.assets_container', 'assets'));

        return [
            'allowedBlocks' => app(GutenbergManager::class)->allowedBlocks($this->config('allowed_blocks')),
            'assetsContainer' => $container,
            'assetsUrl' => cp_route('amazingbv.statamic-gutenberg.assets.index', ['container' => $container]),
            'uploadUrl' => cp_route('amazingbv.statamic-gutenberg.assets.upload', ['container' => $container]),
            'editorUrl' => cp_route('amazingbv.statamic-gutenberg.editor'),
            'hasAssetsContainer' => AssetContainer::find($container) !== null,
        ];
    }

    public function preProcess($data)
    {
        return is_string($data) ? $data : '';
    }

    public function process($data)
    {
        return is_string($data) ? $data : '';
    }

    public function augment($value)
    {
        return app(GutenbergManager::class)->render($value, $this->config());
    }
}
