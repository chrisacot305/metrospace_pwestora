<?php
namespace App\Core;

use PDO;

/**
 * Controller — base class for every controller.
 * Gives access to the database, the logged-in user, and small helpers.
 */
abstract class Controller
{
    protected PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /** Post/Redirect/Get: send the browser to $url and stop. */
    protected function redirect(string $url): void
    {
        // Only allow redirects inside this site.
        if (!str_starts_with($url, '/') || str_starts_with($url, '//')) {
            $url = '/dashboard.php';
        }
        header('Location: ' . $url);
        exit;
    }

    protected function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    /** Result object understood by DashboardController: which view to show with which data. */
    protected function view(string $view, array $data): array
    {
        return ['view' => $view, 'data' => $data];
    }
}
