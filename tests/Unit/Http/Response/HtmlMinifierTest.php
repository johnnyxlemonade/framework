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

    public function testMinifyProducesOneLineHtmlAndRemovesWhitespaceBetweenTags(): void
    {
        $minifier = new HtmlMinifier();
        $html = "  <html>\n    <body>\n        <div class=\"test\">\n            Hello\n        </div>\n    </body>\n</html>  ";

        self::assertSame('<html><body><div class="test">Hello</div></body></html>', $minifier->minify($html));
    }

    public function testMinifyKeepsWhitespaceThatSeparatesText(): void
    {
        $minifier = new HtmlMinifier();
        $html = "<p>Hello\n    <strong>world</strong>\n    again</p>";

        self::assertSame('<p>Hello <strong>world</strong> again</p>', $minifier->minify($html));
    }

    public function testMinifyEncodesPreAndTextareaWhitespaceWithoutChangingParsedText(): void
    {
        $minifier = new HtmlMinifier();
        $preContent = "  a\r\n  b\t";
        $textareaContent = "  x\n y\t";
        $html = "<div><pre>{$preContent}</pre><textarea>{$textareaContent}</textarea></div>";

        $out = $minifier->minify($html);

        self::assertStringNotContainsString("\n", $out);
        self::assertStringNotContainsString("\r", $out);
        self::assertStringContainsString('<pre> &#32;a&#10; &#32;b&#9;</pre>', $out);
        self::assertStringContainsString('<textarea> &#32;x&#10; y&#9;</textarea>', $out);
        // HTML parsing normalizes CRLF to LF in text nodes.
        self::assertSame(str_replace("\r\n", "\n", $preContent), $this->parsedTextContent($out, 'pre'));
        self::assertSame($textareaContent, $this->parsedTextContent($out, 'textarea'));
    }

    public function testMinifyPreservesScriptAndStyleAndMinifiesTemplateHtml(): void
    {
        $minifier = new HtmlMinifier();
        $script = "<script>  var x = 1;\n  var y = 2; </script>";
        $style = "<style>  .a { color: red; }\n  .b { color: blue; } </style>";
        $html = "<div>{$script}{$style}<template>\n  <div>\n    xx\n  </div>\n</template></div>";

        $out = $minifier->minify($html);

        self::assertStringContainsString($script, $out);
        self::assertStringContainsString($style, $out);
        self::assertStringContainsString('<template><div>xx</div></template>', $out);
    }

    public function testMinifyDoesNotDamageAttributesContainingSpecialCharacters(): void
    {
        $minifier = new HtmlMinifier();
        $html = "<a\n data-comparison=\"1 > 0\"\n title='a  b'\n >\n Link\n</a>";

        self::assertSame('<a data-comparison="1 > 0" title=\'a  b\'>Link</a>', $minifier->minify($html));
    }

    public function testMinifyReturnsTrimmedValueWhenNothingElseToMinify(): void
    {
        $minifier = new HtmlMinifier();
        self::assertSame('<p>ok</p>', $minifier->minify(" \n<p>ok</p>\n "));
    }

    private function parsedTextContent(string $html, string $tag): string
    {
        self::assertMatchesRegularExpression(sprintf('/<%1$s>(.*?)<\\/%1$s>/', $tag), $html);
        preg_match(sprintf('/<%1$s>(.*?)<\\/%1$s>/', $tag), $html, $matches);

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
