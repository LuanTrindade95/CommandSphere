<?php

use App\Services\Markdown\HtmlSanitizer;

it('strips a script tag entirely', function (): void {
    expect(HtmlSanitizer::sanitize('<p>before</p><script>alert(document.cookie)</script><p>after</p>'))
        ->toBe('<p>before</p><p>after</p>');
});

it('strips on* event handler attributes even on an otherwise allowed tag', function (): void {
    expect(HtmlSanitizer::sanitize('<img src="x" onerror="alert(1)">'))
        ->toBe('<img src="x">')
        ->and(HtmlSanitizer::sanitize('<p onclick="alert(1)">hi</p>'))
        ->toBe('<p>hi</p>');
});

it('strips an iframe tag entirely', function (): void {
    expect(HtmlSanitizer::sanitize('<iframe src="javascript:alert(1)"></iframe>'))->toBe('');
});

it('strips an svg with an onload handler entirely', function (): void {
    expect(HtmlSanitizer::sanitize('<svg onload="alert(1)"></svg>'))->toBe('');
});

it('strips object and embed tags entirely', function (): void {
    expect(HtmlSanitizer::sanitize('<object data="javascript:alert(1)"></object>'))->toBe('')
        ->and(HtmlSanitizer::sanitize('<embed src="javascript:alert(1)">'))->toBe('');
});

it('strips a style tag entirely', function (): void {
    expect(HtmlSanitizer::sanitize('<style>body{background:url(javascript:alert(1))}</style>'))->toBe('');
});

it('strips a form tag entirely', function (): void {
    expect(HtmlSanitizer::sanitize('<form action="javascript:alert(1)"><input></form>'))->toBe('');
});

it('drops javascript: link hrefs written as raw HTML', function (): void {
    expect(HtmlSanitizer::sanitize('<a href="javascript:alert(1)">click</a>'))->toBe('<a>click</a>');
});

it('drops data:text/html link hrefs carrying an embedded script', function (): void {
    expect(HtmlSanitizer::sanitize('<a href="data:text/html,<script>alert(1)</script>">click</a>'))
        ->toBe('<a>click</a>');
});

it('drops javascript: hrefs hidden behind decimal/hex html entities', function (): void {
    expect(HtmlSanitizer::sanitize('<a href="jav&#x61;script:alert(1)">click</a>'))->toBe('<a>click</a>')
        ->and(HtmlSanitizer::sanitize('<a href="jav&#97;script:alert(1)">click</a>'))->toBe('<a>click</a>');
});

it('drops javascript: hrefs regardless of case', function (): void {
    expect(HtmlSanitizer::sanitize('<a href="JaVaScRiPt:alert(1)">click</a>'))->toBe('<a>click</a>');
});

it('drops javascript: hrefs hidden behind embedded control bytes', function (): void {
    expect(HtmlSanitizer::sanitize("<a href=\"jav\tascript:alert(1)\">click</a>"))->toBe('<a>click</a>')
        ->and(HtmlSanitizer::sanitize("<a href=\"java\nscript:alert(1)\">click</a>"))->toBe('<a>click</a>');
});

it('keeps headings unchanged', function (): void {
    expect(HtmlSanitizer::sanitize('<h1>Title</h1><h2>Subtitle</h2>'))
        ->toBe('<h1>Title</h1><h2>Subtitle</h2>');
});

it('keeps escaped code blocks safe from tag injection while preserving visible text', function (): void {
    $html = '<pre><code class="language-php">&lt;?php echo &quot;hi&quot;; ?&gt;</code></pre>';

    // Quotes inside text content do not need re-escaping to render as literal
    // quote characters; the security-relevant `<`/`>` escaping is preserved.
    expect(HtmlSanitizer::sanitize($html))
        ->toBe('<pre><code class="language-php">&lt;?php echo "hi"; ?&gt;</code></pre>');
});

it('keeps tables with column alignment unchanged', function (): void {
    $html = '<table><thead><tr><th align="left">A</th></tr></thead>'
        .'<tbody><tr><td align="left">1</td></tr></tbody></table>';

    expect(HtmlSanitizer::sanitize($html))->toBe($html);
});

it('keeps GFM task list checkboxes unchanged', function (): void {
    $html = '<ul><li><input disabled type="checkbox"> todo</li>'
        .'<li><input checked disabled type="checkbox"> done</li></ul>';

    expect(HtmlSanitizer::sanitize($html))->toBe($html);
});

it('keeps http and https links unchanged', function (): void {
    expect(HtmlSanitizer::sanitize('<a href="https://example.com">x</a>'))
        ->toBe('<a href="https://example.com">x</a>')
        ->and(HtmlSanitizer::sanitize('<a href="http://example.com">x</a>'))
        ->toBe('<a href="http://example.com">x</a>');
});

it('keeps relative links unchanged', function (): void {
    expect(HtmlSanitizer::sanitize('<a href="./other-doc.md">x</a>'))
        ->toBe('<a href="./other-doc.md">x</a>');
});

it('keeps images unchanged', function (): void {
    expect(HtmlSanitizer::sanitize('<img src="/img/a.png" alt="a" title="t">'))
        ->toBe('<img src="/img/a.png" alt="a" title="t">');
});

it('returns an empty string for empty or whitespace-only input', function (): void {
    expect(HtmlSanitizer::sanitize(''))->toBe('')
        ->and(HtmlSanitizer::sanitize(null))->toBe('')
        ->and(HtmlSanitizer::sanitize('   '))->toBe('');
});
