<?php

namespace Amazingbv\StatamicGutenberg\Tests;

use Amazingbv\StatamicGutenberg\Icons\IconRepository;

class IconRepositoryTest extends TestCase
{
    public function test_default_icons_path_points_to_vendor_resources(): void
    {
        $this->assertSame(
            resource_path('vendor/statamic-gutenberg/icons.php'),
            config('statamic-gutenberg.icons_path')
        );
    }

    public function test_it_reads_icons_from_configured_php_file(): void
    {
        $path = sys_get_temp_dir().'/statamic-gutenberg-icons-'.uniqid().'.php';

        file_put_contents($path, <<<'PHP'
<?php

return [
    'check' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>',
];
PHP);

        config([
            'statamic-gutenberg.icons' => [
                'alert' => [
                    'label' => 'Alert',
                    'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 2 22h20z"/></svg>',
                ],
            ],
            'statamic-gutenberg.icons_path' => $path,
        ]);

        $icons = app(IconRepository::class)->all();

        $this->assertContains('alert', array_column($icons, 'name'));
        $this->assertContains('check', array_column($icons, 'name'));

        @unlink($path);
    }
}
