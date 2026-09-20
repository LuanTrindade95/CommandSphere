<?php

use App\Data\GitHubMarkdownFile;
use App\Models\Community;
use App\Models\Document;
use App\Models\Plugin;
use App\Models\PluginVersion;
use App\Services\Markdown\MarkdownParser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Parses `$html` and fails the test if it contains any element/attribute
 * capable of executing script in a browser. This checks the real DOM rather
 * than the raw string, so harmless escaped display text (e.g. Markdown
 * rendering "<script>" as visible "&lt;script&gt;" text) is not a false
 * positive.
 */
function assertNoExecutableHtml(string $html): void
{
    $dom = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?><div>'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $dangerousTags = ['script', 'iframe', 'object', 'embed', 'style', 'form', 'svg', 'math', 'link', 'meta', 'base'];

    foreach ($dangerousTags as $tag) {
        expect($dom->getElementsByTagName($tag)->length)->toBe(0);
    }

    foreach ($dom->getElementsByTagName('*') as $element) {
        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            $name = strtolower($attribute->name);

            expect($name)->not->toMatch('/^on/');

            if (in_array($name, ['href', 'src'], true)) {
                $normalized = strtolower(preg_replace('/[\x00-\x20\x7F]+/', '', $attribute->value) ?? '');

                expect($normalized)->not->toMatch('/^javascript:/')
                    ->and($normalized)->not->toMatch('/^data:text\/html/')
                    ->and($normalized)->not->toMatch('/^vbscript:/');
            }
        }
    }
}

it('sanitizes malicious raw HTML and links embedded in ingested Markdown while keeping legitimate rendering', function (): void {
    $markdown = <<<'MD'
# Command Reference

## /ban-player

```yaml
syntax: /ban <player>
description: Bans a player.
```

Some *legit* text with a [http link](https://example.com), a [relative link](./other.md)
and an ![image](/img/a.png "alt").

- [ ] pending task
- [x] done task

| Left | Right |
|:-----|------:|
| a | b |

```php
<?php echo "<b>hi</b>"; ?>
```

<script>alert(document.cookie)</script>

<img src="x" onerror="alert(1)">

<iframe src="javascript:alert(1)"></iframe>

<svg onload="alert(1)"></svg>

<object data="javascript:alert(1)"></object>
<embed src="javascript:alert(1)">

<style>body{background:url(javascript:alert(1))}</style>

<form action="javascript:alert(1)"><input></form>

[js link](javascript:alert(1))

[data html link](data:text/html,<script>alert(1)</script>)

<a href="jav&#x61;script:alert(1)">encoded</a>

<a href="JaVaScRiPt:alert(1)">case variant</a>
MD;

    $parsed = app(MarkdownParser::class)->parse(new GitHubMarkdownFile(
        path: 'docs/security.md',
        content: $markdown,
    ));

    $html = $parsed->contentHtml;

    // Legitimate rendering is preserved.
    expect($html)->toContain('<h1>Command Reference</h1>')
        ->toContain('href="https://example.com"')
        ->toContain('href="./other.md"')
        ->toContain('src="/img/a.png"')
        ->toContain('type="checkbox"')
        ->toContain('<table')
        ->toContain('&lt;?php echo "&lt;b&gt;hi&lt;/b&gt;"; ?&gt;');

    // Nothing executable survives as real markup, regardless of vector. Raw HTML
    // that Markdown escaped into inert display text (e.g. "&lt;script&gt;") is not
    // a vulnerability, so we assert against the *parsed* output rather than doing
    // naive substring checks that would also flag harmless escaped text.
    assertNoExecutableHtml($html);
});

it('serves a document whose content_html was written directly to the database, already sanitized', function (): void {
    $community = Community::factory()->create();
    $plugin = Plugin::factory()->create(['community_id' => $community->id]);
    $pluginVersion = PluginVersion::factory()->create(['plugin_id' => $plugin->id]);

    $document = Document::factory()->create([
        'plugin_version_id' => $pluginVersion->id,
        'content_html' => '<h1>Legacy Doc</h1>'
            .'<script>alert(document.cookie)</script>'
            .'<img src="x" onerror="alert(1)">'
            .'<a href="javascript:alert(1)">click</a>'
            .'<a href="jav&#x61;script:alert(1)">encoded</a>',
    ]);

    // The stored column keeps the untouched legacy payload: no data migration was performed.
    expect($document->getRawOriginal('content_html'))->toContain('<script>');

    $response = $this->getJson("/api/v1/documents/{$document->id}");

    $response->assertOk();

    $html = $response->json('data.content_html');

    expect($html)->toContain('<h1>Legacy Doc</h1>');
    assertNoExecutableHtml($html);
});
