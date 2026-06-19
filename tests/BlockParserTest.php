<?php

namespace Amazingbv\StatamicGutenberg\Tests;

use Amazingbv\StatamicGutenberg\Blocks\BlockParser;

class BlockParserTest extends TestCase
{
    public function test_it_round_trips_nested_blocks(): void
    {
        $html = '<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph --><p>Left</p><!-- /wp:paragraph --></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph --><p>Right</p><!-- /wp:paragraph --></div><!-- /wp:column --></div><!-- /wp:columns -->';

        $parser = new BlockParser;
        $blocks = $parser->parse($html);

        $this->assertCount(1, $blocks);
        $this->assertSame('core/columns', $blocks[0]->name());
        $this->assertCount(2, $blocks[0]->innerBlocks());
        $this->assertSame($html, $parser->serialize($blocks));
    }

    public function test_it_preserves_malformed_attribute_json(): void
    {
        $html = '<!-- wp:paragraph {"broken":true --><p>Broken attributes</p><!-- /wp:paragraph -->';

        $parser = new BlockParser;

        $this->assertSame($html, $parser->serialize($parser->parse($html)));
    }
}
