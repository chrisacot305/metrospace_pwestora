<?php
namespace App\Core;

/**
 * View — renders a template from app/Views/.
 * Variables passed in $data become normal variables inside the template.
 */
final class View
{
    public static function path(string $view): string
    {
        $view = str_replace(['..', '\\'], '', $view);
        return APP_PATH . '/Views/' . $view . '.php';
    }

    public static function render(string $view, array $data = []): void
    {
        $__file = self::path($view);
        if (!is_file($__file)) {
            throw new \RuntimeException("View not found: $view");
        }
        // Templates may need these globals, exactly like the old include-based pages.
        $pdo = $data['pdo'] ?? Database::connection();
        extract($data, EXTR_SKIP);
        require $__file;
    }

    /** Renders a view and returns the HTML instead of printing it. */
    public static function capture(string $view, array $data = []): string
    {
        ob_start();
        try {
            self::render($view, $data);
        } finally {
            $html = ob_get_clean();
        }
        return $html;
    }

    /** Plain "403 Forbidden" page, then stop. */
    public static function forbidden(string $message = 'You do not have access to this page.'): void
    {
        http_response_code(403);
        self::render('errors/forbidden', ['message' => $message]);
        exit;
    }
}
