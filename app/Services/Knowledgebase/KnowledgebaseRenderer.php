<?php

namespace Pterodactyl\Services\Knowledgebase;

class KnowledgebaseRenderer
{
    public function render(?string $source): string
    {
        $source = str_replace(["\r\n", "\r"], "\n", (string) $source);
        $lines = explode("\n", $source);

        $html = [];
        $paragraph = [];
        $listType = null;
        $inCode = false;
        $codeLanguage = '';
        $codeLines = [];
        $calloutType = null;
        $calloutTitle = null;
        $calloutLines = [];

        $flushParagraph = function () use (&$paragraph, &$html): void {
            if ($paragraph === []) {
                return;
            }

            $text = trim(implode(' ', array_map('trim', $paragraph)));
            if ($text !== '') {
                $html[] = '<p>' . $this->inline($text) . '</p>';
            }

            $paragraph = [];
        };

        $closeList = function () use (&$listType, &$html): void {
            if ($listType !== null) {
                $html[] = '</' . $listType . '>';
                $listType = null;
            }
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($inCode) {
                if (str_starts_with($trimmed, str_repeat(chr(96), 3))) {
                    $language = preg_replace('/[^a-z0-9_+-]/i', '', $codeLanguage) ?: '';
                    $class = $language !== '' ? ' class="language-' . e($language) . '"' : '';
                    $html[] = '<pre><code' . $class . '>' . e(implode("\n", $codeLines)) . '</code></pre>';
                    $inCode = false;
                    $codeLanguage = '';
                    $codeLines = [];
                } else {
                    $codeLines[] = $line;
                }

                continue;
            }

            if ($calloutType !== null) {
                if ($trimmed === ':::') {
                    $content = [];
                    $buffer = [];

                    foreach ($calloutLines as $calloutLine) {
                        if (trim($calloutLine) === '') {
                            if ($buffer !== []) {
                                $content[] = '<p>' . $this->inline(trim(implode(' ', $buffer))) . '</p>';
                                $buffer = [];
                            }
                        } else {
                            $buffer[] = trim($calloutLine);
                        }
                    }

                    if ($buffer !== []) {
                        $content[] = '<p>' . $this->inline(trim(implode(' ', $buffer))) . '</p>';
                    }

                    $title = $calloutTitle ?: match ($calloutType) {
                        'warning' => 'Bemærk',
                        'success' => 'Tip',
                        'danger' => 'Vigtigt',
                        default => 'Information',
                    };

                    $html[] = '<div class="kbCallout kbCallout--' . e($calloutType) . '"><strong>' . e($title) . '</strong>' . implode('', $content) . '</div>';
                    $calloutType = null;
                    $calloutTitle = null;
                    $calloutLines = [];
                } else {
                    $calloutLines[] = $line;
                }

                continue;
            }

            if (preg_match('/^\x60{3}\s*([a-z0-9_+-]*)\s*$/i', $trimmed, $match)) {
                $flushParagraph();
                $closeList();
                $inCode = true;
                $codeLanguage = $match[1] ?? '';
                continue;
            }

            if (preg_match('/^:::(info|warning|success|danger)(?:\s+(.+))?$/i', $trimmed, $match)) {
                $flushParagraph();
                $closeList();
                $calloutType = strtolower($match[1]);
                $calloutTitle = isset($match[2]) ? trim($match[2]) : null;
                $calloutLines = [];
                continue;
            }

            if ($trimmed === '') {
                $flushParagraph();
                $closeList();
                continue;
            }

            if (preg_match('/^(#{2,4})\s+(.+)$/', $trimmed, $match)) {
                $flushParagraph();
                $closeList();
                $level = strlen($match[1]);
                $html[] = '<h' . $level . '>' . $this->inline($match[2]) . '</h' . $level . '>';
                continue;
            }

            if (preg_match('/^(-{3,}|\*{3,})$/', $trimmed)) {
                $flushParagraph();
                $closeList();
                $html[] = '<hr>';
                continue;
            }

            if (preg_match('/^>\s?(.*)$/', $trimmed, $match)) {
                $flushParagraph();
                $closeList();
                $html[] = '<blockquote>' . $this->inline($match[1]) . '</blockquote>';
                continue;
            }

            if (preg_match('/^\d+[.)]\s+(.+)$/', $trimmed, $match)) {
                $flushParagraph();
                if ($listType !== 'ol') {
                    $closeList();
                    $listType = 'ol';
                    $html[] = '<ol>';
                }
                $html[] = '<li>' . $this->inline($match[1]) . '</li>';
                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $match)) {
                $flushParagraph();
                if ($listType !== 'ul') {
                    $closeList();
                    $listType = 'ul';
                    $html[] = '<ul>';
                }
                $html[] = '<li>' . $this->inline($match[1]) . '</li>';
                continue;
            }

            $closeList();
            $paragraph[] = $line;
        }

        if ($inCode) {
            $language = preg_replace('/[^a-z0-9_+-]/i', '', $codeLanguage) ?: '';
            $class = $language !== '' ? ' class="language-' . e($language) . '"' : '';
            $html[] = '<pre><code' . $class . '>' . e(implode("\n", $codeLines)) . '</code></pre>';
        }

        if ($calloutType !== null) {
            $title = $calloutTitle ?: 'Information';
            $html[] = '<div class="kbCallout kbCallout--' . e($calloutType) . '"><strong>' . e($title) . '</strong><p>' . $this->inline(trim(implode(' ', $calloutLines))) . '</p></div>';
        }

        $flushParagraph();
        $closeList();

        return implode("\n", $html);
    }

    private function inline(string $text): string
    {
        $tokens = [];

        $token = function (string $html) use (&$tokens): string {
            $key = '@@KBTOKEN' . count($tokens) . '@@';
            $tokens[$key] = $html;

            return $key;
        };

        $text = preg_replace_callback('/\x60([^\x60]+)\x60/', function ($match) use ($token) {
            return $token('<code>' . e($match[1]) . '</code>');
        }, $text) ?? $text;

        $text = preg_replace_callback('/!\[([^\]]*)\]\(([^\s\)]+)(?:\s+"([^"]*)")?\)/', function ($match) use ($token) {
            $url = trim($match[2]);
            if (!$this->allowedImageUrl($url)) {
                return $match[0];
            }

            $alt = trim($match[1]);
            $caption = trim($match[3] ?? '');
            $figure = '<figure><img src="' . e($url) . '" alt="' . e($alt) . '" loading="lazy" decoding="async">';

            if ($caption !== '') {
                $figure .= '<figcaption>' . e($caption) . '</figcaption>';
            }

            $figure .= '</figure>';

            return $token($figure);
        }, $text) ?? $text;

        $text = preg_replace_callback('/\[([^\]]+)\]\(([^\s\)]+)\)/', function ($match) use ($token) {
            $url = trim($match[2]);
            if (!$this->allowedLinkUrl($url)) {
                return $match[0];
            }

            $external = str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
            $extra = $external ? ' target="_blank" rel="noopener noreferrer"' : '';

            return $token('<a href="' . e($url) . '"' . $extra . '>' . e($match[1]) . '</a>');
        }, $text) ?? $text;

        $text = e($text);
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $text) ?? $text;

        return strtr($text, $tokens);
    }

    private function allowedLinkUrl(string $url): bool
    {
        return (str_starts_with($url, '/') && !str_starts_with($url, '//'))
            || str_starts_with($url, '#')
            || str_starts_with($url, 'https://')
            || str_starts_with($url, 'http://')
            || str_starts_with($url, 'mailto:');
    }

    private function allowedImageUrl(string $url): bool
    {
        return str_starts_with($url, '/storage/knowledgebase/')
            || str_starts_with($url, 'https://')
            || str_starts_with($url, 'http://');
    }
}
