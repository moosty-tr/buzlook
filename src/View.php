<?php

declare(strict_types=1);

namespace Buzlook;

/**
 * Renders PHP templates inside the shared layout.
 */
final class View
{
    public function __construct(
        private readonly string $templatesPath,
        private readonly Translator $translator,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): void
    {
        $content = $this->capture($template, $data);
        $title = (string) ($data['title'] ?? 'Buzlook');

        header('Vary: Accept-Language');
        echo $this->capture('layout', ['content' => $content, 'title' => $title]);
    }

    public function escape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param array<string, scalar> $params
     */
    public function t(string $key, array $params = []): string
    {
        return $this->translator->t($key, $params);
    }

    /**
     * Translated text, HTML-escaped (for templates).
     *
     * @param array<string, scalar> $params
     */
    public function te(string $key, array $params = []): string
    {
        return $this->escape($this->translator->t($key, $params));
    }

    public function language(): string
    {
        return $this->translator->language();
    }

    /**
     * Texts needed by JavaScript, as JSON that is safe inside a <script> element.
     */
    public function jsMessages(): string
    {
        return (string) json_encode(
            $this->translator->withPrefix('js.'),
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function capture(string $template, array $data): string
    {
        extract($data, EXTR_SKIP);

        ob_start();
        require $this->templatesPath . '/' . $template . '.php';

        return (string) ob_get_clean();
    }
}
