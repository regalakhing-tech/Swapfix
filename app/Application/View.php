<?php
declare(strict_types=1);

namespace SwapFix\Application;

use Psr\Http\Message\ServerRequestInterface;

/** Renders a template inside a layout with escaped-output helpers. */
final class View
{
    private const ROOT = __DIR__ . '/../../resources/views/';

    public function __construct(private readonly Container $app) {}

    public function render(ServerRequestInterface $request, string $template, array $data = [], string $layout = 'app'): string
    {
        $ui = new Ui($this->app, $request);
        $content = $this->capture($template, $data + ['ui' => $ui]);
        return $this->capture('layouts/' . $layout, $data + ['ui' => $ui, 'content' => $content]);
    }

    public function partial(string $template, array $data): string
    {
        return $this->capture($template, $data);
    }

    private function capture(string $template, array $data): string
    {
        $file = self::ROOT . $template . '.php';
        $data['e'] = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $render = static function (string $__file, array $__data): void {
            extract($__data, EXTR_SKIP);
            require $__file;
        };
        ob_start();
        try {
            $render($file, $data);
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
