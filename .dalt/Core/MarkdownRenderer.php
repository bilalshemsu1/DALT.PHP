<?php

declare(strict_types=1);

namespace Core;

use Core\Markdown\AlertExtension;
use Core\Markdown\HighlightedFencedCodeRenderer;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\StringContainerHelper;

final class MarkdownRenderer
{
    private MarkdownConverter $converter;

    /**
     * The layout already renders the page title as the document's only <h1>.
     * Set per render so the parsed-document listener can recognise a content
     * heading that merely repeats it.
     */
    private ?string $pageTitle = null;

    public function __construct()
    {
        $environment = new Environment([
            // Content must never execute as HTML; approved interactive hints are
            // restored by preserveChallengeHintMarkup() before parsing.
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 100,
            'max_delimiters_per_line' => 1000,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new AlertExtension());
        $environment->addRenderer(FencedCode::class, new HighlightedFencedCodeRenderer(), 10);
        $environment->addEventListener(
            DocumentParsedEvent::class,
            function (DocumentParsedEvent $event): void {
                $this->reserveTopHeadingForTheLayout($event->getDocument());
            },
        );

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * A page may have exactly one <h1>, and the layout owns it.
     *
     * Source files open with `# Title` because they are also read as files on
     * disk and in the repository. Rendered into a page that already prints the
     * title in its header, that produced two <h1> elements on every lesson,
     * build and challenge page — on guided Build pages, two with identical text,
     * so a screen reader announced the same top-level heading twice in a row.
     *
     * A leading heading that only repeats the title is dropped; anything else
     * becomes an <h2>, which is the level the prose styles already treat as a
     * section. This runs on the parsed document rather than the source text, so
     * a `# comment` line inside a fenced shell block is untouched — a text-level
     * fix would have rewritten thirty-two of them in the Docker lesson alone.
     */
    private function reserveTopHeadingForTheLayout(Document $document): void
    {
        $first = true;

        foreach ($document->children() as $node) {
            if (!$node instanceof Heading) {
                $first = false;

                continue;
            }

            if ($node->getLevel() !== 1) {
                $first = false;

                continue;
            }

            if ($first && $this->titleMatches($node)) {
                $node->detach();
            } else {
                $node->setLevel(2);
            }

            $first = false;
        }
    }

    private function titleMatches(Heading $heading): bool
    {
        if ($this->pageTitle === null) {
            return false;
        }

        $normalise = static fn (string $value): string => strtolower(trim(
            (string) preg_replace('/\s+/', ' ', $value),
        ));

        return $normalise(StringContainerHelper::getChildText($heading)) === $normalise($this->pageTitle);
    }

    /**
     * @param string|null $pageTitle The title the surrounding layout renders as
     *                               the page's <h1>, when there is one.
     */
    public function render(string $markdown, ?string $pageTitle = null): string
    {
        $this->pageTitle = $pageTitle;


        // Existing challenge hints use semantic disclosure elements. Keep this
        // exact, attribute-free pair while treating every other raw HTML input
        // as text. A per-render token prevents source content from forging tags.
        $token = 'DALT_MARKDOWN_' . bin2hex(random_bytes(16));
        $parts = preg_split('/(^```.*?^```[ \\t]*$)/ms', $markdown, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts !== false) {
            foreach ($parts as $index => $part) {
                if ($index % 2 === 0) {
                    $part = preg_replace_callback(
                        '/<summary>(.*?)<\\/summary>/si',
                        static fn (array $match): string => "<!-- {$token}_SUMMARY:" . rtrim(strtr(base64_encode($match[1]), '+/', '-_'), '=') . ' -->',
                        $part,
                    ) ?? $part;
                    $parts[$index] = str_replace(
                        ['<details>', '</details>'],
                        ["<!-- {$token}_DETAILS_OPEN -->", "<!-- {$token}_DETAILS_CLOSE -->"],
                        $part,
                    );
                }
            }
            $markdown = implode('', $parts);
        }

        $html = str_replace(
            ["&lt;!-- {$token}_DETAILS_OPEN --&gt;", "&lt;!-- {$token}_DETAILS_CLOSE --&gt;"],
            ['<details>', '</details>'],
            $this->converter->convert($markdown)->getContent(),
        );

        return preg_replace_callback(
            '/&lt;!-- ' . preg_quote($token, '/') . '_SUMMARY:([A-Za-z0-9_-]+) --&gt;/',
            static fn (array $match): string => '<summary>' . htmlspecialchars((string) base64_decode(strtr($match[1], '-_', '+/'), true), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</summary>',
            $html,
        ) ?? $html;
    }
}
