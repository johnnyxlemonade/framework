<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Response;

use Lemonade\Framework\Http\Response\HtmlMinifier;
use PHPUnit\Framework\TestCase;

final class HtmlMinifierTest extends TestCase
{
    public function testMinifyHandlesNullAndEmptyInputs(): void
    {
        $minifier = new HtmlMinifier();

        self::assertSame('', $minifier->minify(null));
        self::assertSame('', $minifier->minify(''));
        self::assertSame('', $minifier->minify(" \n\t "));
    }

    public function testMinifyRemovesRegularCommentsAndKeepsConditionalAndSpecialComments(): void
    {
        $minifier = new HtmlMinifier();
        $html = '<div><!-- remove --><!--[if IE]>keep<![endif]--><!--!keep-special--></div>';

        $out = $minifier->minify($html);

        self::assertStringNotContainsString('remove', $out);
        self::assertStringContainsString('<!--[if IE]>keep<![endif]-->', $out);
        self::assertStringContainsString('<!--!keep-special-->', $out);
    }

    public function testMinifyNormalizesTagsAndWhitespaceAndTrimsResult(): void
    {
        $minifier = new HtmlMinifier();
        $html = "  <div\n  class=\"x\"\n  id=\"a\"  > \n  <span>  A  </span> \n </div>  ";

        $out = $minifier->minify($html);

        self::assertSame('<div class="x" id="a"><span> A </span></div>', $out);
    }

    public function testMinifyPreservesWhitespaceInsidePreservedTagsAndMultipleBlocks(): void
    {
        $minifier = new HtmlMinifier();
        $pre = "<pre>  a\n  b\t</pre>";
        $textarea = "<textarea>  x\n y </textarea>";
        $script = "<script>  var x = 1;\n  var y = 2; </script>";
        $style = "<style>  .a { color: red; }\n  .b { color: blue; } </style>";
        $template = "<template>  <div>\n  xx </div> </template>";
        $html = "<div> {$pre} {$textarea} {$script} {$style} {$template} </div>";

        $out = $minifier->minify($html);

        self::assertStringContainsString($pre, $out);
        self::assertStringContainsString($textarea, $out);
        self::assertStringContainsString($script, $out);
        self::assertStringContainsString($style, $out);
        self::assertStringContainsString($template, $out);
    }

    public function testMinifyReturnsTrimmedValueWhenNothingElseToMinify(): void
    {
        $minifier = new HtmlMinifier();
        self::assertSame('<p>ok</p>', $minifier->minify(" \n<p>ok</p>\n "));
    }
}
