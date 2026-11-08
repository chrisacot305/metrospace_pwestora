<?php
namespace App\Models;

use PDO;
/**
 * app/Models/SettingsModel.php
 * Handles database queries for Platform Settings.
 */
class SettingsModel extends Model
{

    public function getAllSettings(): array {
        return $this->pdo->query('SELECT setting_key, setting_value FROM platform_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function updateSetting(string $key, string $value): void {
        $stmt = $this->pdo->prepare('UPDATE platform_settings SET setting_value = ? WHERE setting_key = ?');
        $stmt->execute([$value, $key]);
    }
}