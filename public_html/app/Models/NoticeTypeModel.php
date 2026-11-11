<?php
namespace App\Models;

/**
 * app/Models/NoticeTypeModel.php — editable notice types for SuperAdmin → Notify Lessors.
 * Table notice_types (created by tools/migrate.php): name, template, color, is_active, sort_order.
 * Before the migration runs, the built-in defaults are returned so the page keeps working.
 */
class NoticeTypeModel extends Model
{
    /** Allowed colour keys => hex (used for the dot / pill next to each type). */
    public const COLORS = [
        'blue'   => '#2563EB',
        'purple' => '#7C3AED',
        'orange' => '#D97706',
        'red'    => '#DC2626',
        'green'  => '#16A34A',
        'slate'  => '#475569',
    ];

    private const DEFAULTS = [
        ['id' => 0, 'name' => 'General Announcement', 'color' => 'blue',   'is_active' => 1, 'sort_order' => 1, 'template' => ''],
        ['id' => 0, 'name' => 'Policy Update',        'color' => 'purple', 'is_active' => 1, 'sort_order' => 2, 'template' => ''],
        ['id' => 0, 'name' => 'Verification Reminder','color' => 'orange', 'is_active' => 1, 'sort_order' => 3, 'template' => ''],
        ['id' => 0, 'name' => 'Compliance Notice',    'color' => 'red',    'is_active' => 1, 'sort_order' => 4, 'template' => ''],
    ];

    private ?bool $ready = null;

    /** true once tools/migrate.php has created the table. */
    public function isReady(): bool
    {
        if ($this->ready === null) {
            try {
                $this->pdo->query('SELECT 1 FROM notice_types LIMIT 1');
                $this->ready = true;
            } catch (\Throwable $e) {
                $this->ready = false;
            }
        }
        return $this->ready;
    }

    public function allTypes(): array
    {
        if (!$this->isReady()) return self::DEFAULTS;
        return $this->all('SELECT * FROM notice_types ORDER BY sort_order ASC, name ASC');
    }

    public function activeTypes(): array
    {
        if (!$this->isReady()) return self::DEFAULTS;
        return $this->all('SELECT * FROM notice_types WHERE is_active = 1 ORDER BY sort_order ASC, name ASC');
    }

    public function find(int $id): ?array
    {
        if (!$this->isReady() || $id <= 0) return null;
        return $this->one('SELECT * FROM notice_types WHERE id = ?', [$id]);
    }

    public function nameTaken(string $name, int $exceptId = 0): bool
    {
        return $this->value('SELECT id FROM notice_types WHERE name = ? AND id <> ?', [$name, $exceptId]) !== false;
    }

    public function create(string $name, string $template, string $color, bool $active): int
    {
        $next = (int) $this->value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM notice_types');
        return $this->insert(
            'INSERT INTO notice_types (name, template, color, is_active, sort_order) VALUES (?, ?, ?, ?, ?)',
            [$name, $template, $this->color($color), $active ? 1 : 0, $next]
        );
    }

    public function update(int $id, string $name, string $template, string $color, bool $active): void
    {
        $this->run(
            'UPDATE notice_types SET name = ?, template = ?, color = ?, is_active = ? WHERE id = ?',
            [$name, $template, $this->color($color), $active ? 1 : 0, $id]
        );
    }

    public function setActive(int $id, bool $active): void
    {
        $this->run('UPDATE notice_types SET is_active = ? WHERE id = ?', [$active ? 1 : 0, $id]);
    }

    public function delete(int $id): void
    {
        $this->run('DELETE FROM notice_types WHERE id = ?', [$id]);
    }

    /** How many notices were sent with this type name (notifications_log.title). */
    public function usageCounts(): array
    {
        try {
            $rows = $this->all('SELECT title, COUNT(*) AS n FROM notifications_log GROUP BY title');
        } catch (\Throwable $e) {
            return [];
        }
        return array_column($rows, 'n', 'title');
    }

    public static function hex(?string $color): string
    {
        return self::COLORS[$color ?? ''] ?? self::COLORS['blue'];
    }

    private function color(string $c): string
    {
        return isset(self::COLORS[$c]) ? $c : 'blue';
    }
}
