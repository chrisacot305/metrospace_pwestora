<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\NotifyModel;

/** /notifications_mark_read.php — marks all bell notifications as read, then goes back. */
final class NotificationController extends Controller
{
    public function markAllRead(): void
    {
        Auth::requireLogin();
        (new NotifyModel($this->pdo))->markAllRead((int) Auth::user()['id']);

        // Only go back to a page on THIS site (the old code trusted any Referer → open redirect).
        $back = '/dashboard.php';
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($ref !== '' && parse_url($ref, PHP_URL_HOST) === $host) {
            $path = parse_url($ref, PHP_URL_PATH) ?: '/dashboard.php';
            $query = parse_url($ref, PHP_URL_QUERY);
            $back = $path . ($query ? '?' . $query : '');
        }
        $this->redirect($back);
    }
}
