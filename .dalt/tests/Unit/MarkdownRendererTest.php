<?php

declare(strict_types=1);

use Core\MarkdownRenderer;

function markdownRenderer(): MarkdownRenderer
{
    return new MarkdownRenderer();
}

test('renders CommonMark and the GFM features used by course content', function () {
    $html = markdownRenderer()->render(<<<'MARKDOWN'
# Heading

Plain *emphasis* and **strong** with ~~strikethrough~~.

- item
- [x] completed

| Name | Value |
| --- | --- |
| café | ✅ |

https://dalt.test
MARKDOWN);

    expect($html)->toContain('<h2>Heading</h2>')
        ->toContain('<em>emphasis</em>')
        ->toContain('<strong>strong</strong>')
        ->toContain('<del>strikethrough</del>')
        ->toContain('<ul>')
        ->toContain('checked="" disabled="" type="checkbox"')
        ->toContain('<table>')
        ->toContain('café')
        ->toContain('<a href="https://dalt.test">https://dalt.test</a>');
});

test('highlights explicit fences and safely falls back for unknown or unlabelled code', function () {
    $html = markdownRenderer()->render(<<<'MARKDOWN'
```php
<?php echo '<tag>';
```

```not-a-language
<tag attr="quoted">
```

```
  keep indentation
```
MARKDOWN);

    expect($html)->toContain('class="hljs language-php"')
        ->toContain('hljs-keyword')
        ->toContain('&lt;tag&gt;')
        ->toContain('class="hljs language-not-a-language"')
        ->toContain('&lt;tag attr=&quot;quoted&quot;&gt;')
        ->toContain('  keep indentation');
});

test('renders all supported GitHub alert types without changing normal quotes', function (string $type) {
    $html = markdownRenderer()->render("> [!{$type}]\n> Useful context.\n\n> Ordinary quote.");

    expect($html)->toContain('markdown-alert markdown-alert-' . strtolower($type))
        ->toContain('Useful context.')
        ->toContain('<blockquote>')
        ->toContain('Ordinary quote.');
})->with(['NOTE', 'TIP', 'IMPORTANT', 'WARNING', 'CAUTION']);

test('escapes arbitrary raw HTML and disables unsafe link schemes', function () {
    $html = markdownRenderer()->render("<script>alert(\"x\")</script>\n\n[bad](javascript:alert(1))");

    expect($html)->toContain('&lt;script&gt;alert("x")&lt;/script&gt;')
        ->not->toContain('<script>')
        ->not->toContain('href="javascript:');
});

test('preserves the existing attribute-free challenge hint markup only', function () {
    $html = markdownRenderer()->render("<details>\n<summary>Hint</summary>\n\nContent\n\n</details>\n\n<div class=\"unsafe\">Nope</div>");

    expect($html)->toContain('<details>')
        ->toContain('<summary>Hint</summary>')
        ->toContain('</details>')
        ->toContain('&lt;div class="unsafe"&gt;Nope&lt;/div&gt;');
});

test('renders representative PHP, shell, SQL, and Docker course code without corruption', function () {
    $renderer = markdownRenderer();
    $php = $renderer->render((string) file_get_contents(base_path('.dalt/course/lessons/02-routing/README.md')));
    $shell = $renderer->render((string) file_get_contents(base_path('.dalt/course/lessons/06-docker-basics/README.md')));
    $sql = $renderer->render((string) file_get_contents(base_path('.dalt/course/lessons/10-postgres-intermediate/README.md')));
    $docker = $renderer->render((string) file_get_contents(base_path('.dalt/course/lessons/07-dockerfile/README.md')));

    expect($php)->toContain('language-php')->toContain('&lt;?php')
        ->and($shell)->toContain('language-bash')->toContain('docker')
        ->and($sql)->toContain('language-sql')->toContain('SELECT')
        ->and($docker)->toContain('language-dockerfile')->toContain('FROM');
});

test('rendered content never contains an h1, because the layout owns the only one', function () {
    // Two <h1> elements on a page is not a style preference. Every lesson, build
    // and challenge page printed the title in its header and then again from the
    // Markdown source, so a screen reader announced two competing top-level
    // headings — and on guided Build pages, the same words twice in a row.
    $html = markdownRenderer()->render(<<<'MARKDOWN'
# Some lesson title

Intro.

## A real section

### A subsection

# A second top-level heading
MARKDOWN);

    expect($html)->not->toContain('<h1')
        ->toContain('<h2>Some lesson title</h2>')
        ->toContain('<h2>A real section</h2>')
        ->toContain('<h3>A subsection</h3>')
        ->toContain('<h2>A second top-level heading</h2>');
});

test('a leading heading that only repeats the page title is dropped, not demoted', function () {
    $html = markdownRenderer()->render("# Accept an invitation once\n\nBody text.\n", 'Accept an invitation once');

    expect($html)->not->toContain('Accept an invitation once')
        ->toContain('Body text.');

    // Whitespace and case differences are still the same title.
    expect(markdownRenderer()->render("#   accept an   invitation ONCE\n\nBody.\n", 'Accept an invitation once'))
        ->not->toContain('invitation');
});

test('only a leading duplicate is dropped; a later matching heading is kept as a section', function () {
    $html = markdownRenderer()->render(
        "# Recovery\n\nIntro.\n\n## Steps\n\n# Recovery\n\nAgain.\n",
        'Recovery',
    );

    expect(substr_count($html, 'Recovery'))->toBe(1)
        ->and($html)->toContain('<h2>Recovery</h2>')
        ->and($html)->not->toContain('<h1');
});

test('a hash inside a fenced block is a comment, not a heading', function () {
    // The Docker lesson has thirty-two lines starting with "# " inside shell
    // fences. Demoting headings by rewriting source text would have mangled
    // every one of them; this works on the parsed document instead.
    $html = markdownRenderer()->render(<<<'MARKDOWN'
## Build it

```sh
# build the image
docker build -t app .
```
MARKDOWN);

    expect($html)->toContain('# build the image')
        ->toContain('<h2>Build it</h2>')
        ->not->toContain('<h1');
});
