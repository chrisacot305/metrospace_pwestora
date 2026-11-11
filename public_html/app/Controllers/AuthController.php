<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\View;
use App\Models\PropertyModel;

/**
 * AuthController — landing page, login, logout, pending-verification page.
 *   /index.php   → landing()
 *   /login.php   → login()
 *   /logout.php  → logout()
 *   /pending.php → pending()
 */
final class AuthController extends Controller
{
    /** Where a logged-in user belongs. */
    private function homeFor(array $user): string
    {
        return $user['status'] === 'active' ? '/dashboard.php' : '/pending.php';
    }

    public function landing(): void
    {
        if (Auth::check()) {
            $this->redirect($this->homeFor(Auth::user()));
        }
        View::render('auth/landing', [
            'listings'      => $this->publicListings(),
            'REQUIRED_DOCS' => [
                'business_permit' => 'Business Permit',
                'sec_dti'         => 'SEC / DTI Registration',
                'bir_cert'        => 'BIR Certificate',
                'land_title'      => 'Land Title',
                'gov_id'          => "Owner's Government ID",
            ],
        ]);
    }

    /**
     * Live listings for the landing page "Listings" section.
     * Never breaks the landing page: on any database problem it just returns [].
     */
    private function publicListings(): array
    {
        try {
            $rows = (new PropertyModel($this->pdo))->publicListings(12);
        } catch (\Throwable $e) {
            error_log('Landing listings failed: ' . $e->getMessage());
            return [];
        }

        $status = [
            'available'         => ['Available', 'available'],
            'reserved'          => ['Reserved', 'reserved'],
            'under_maintenance' => ['Under maintenance', 'reserved'],
            'occupied'          => ['Occupied', 'occupied'],
        ];

        $out = [];
        foreach ($rows as $r) {
            $photos = [];
            foreach (array_filter(explode('|', (string) $r['photo_paths'])) as $path) {
                $path = ltrim($path, '/');
                if (str_starts_with($path, 'uploads/properties/')) {   // photos only, never documents
                    $photos[] = '/' . $path;
                }
            }
            [$label, $tone] = (int) $r['has_active_tenant'] === 1
                ? $status['occupied']
                : ($status[$r['occupancy_status'] ?? 'available'] ?? $status['available']);

            // Landmark photo goes last in the gallery (only real property images, never documents).
            $landmarkPath = ltrim((string) ($r['landmark_photo_path'] ?? ''), '/');
            if ($landmarkPath !== '' && str_starts_with($landmarkPath, 'uploads/properties/')) {
                $photos[] = '/' . $landmarkPath;
            }

            $hasMap = $r['latitude'] !== null && $r['longitude'] !== null;
            $out[] = [
                'id'          => (int) $r['id'],
                'name'        => (string) $r['name'],
                'type'        => (string) ($r['type'] ?: 'Commercial'),
                'address'     => (string) $r['address'],
                'rent'        => $r['asking_rent'] !== null ? (float) $r['asking_rent'] : null,
                'floor_area'  => (string) ($r['floor_area'] ?? ''),
                'lease_term'  => (string) ($r['lease_term'] ?? ''),
                'escalation'  => (string) ($r['escalation'] ?? ''),
                'description' => mb_substr(trim((string) ($r['description'] ?? '')), 0, 600),
                'landmark'    => trim((string) ($r['landmark_name'] ?? '') . (!empty($r['landmark_distance']) ? ' (' . $r['landmark_distance'] . ')' : '')),
                'floor_level' => (string) ($r['floor_level'] ?? ''),
                'frontage'    => (string) ($r['frontage'] ?? ''),
                'parking'     => isset($r['parking_slots']) && $r['parking_slots'] !== null ? (int) $r['parking_slots'] : null,
                'hours'       => (string) ($r['operating_hours'] ?? ''),
                'deposit'     => (string) ($r['deposit_terms'] ?? ''),
                'permitted'   => (string) ($r['permitted_use'] ?? ''),
                'utilities'   => (string) ($r['utilities'] ?? ''),
                'amenities'   => (string) ($r['amenities'] ?? ''),
                'status'      => $label,
                'tone'        => $tone,
                'photos'      => $photos,
                'map'         => $hasMap ? 'https://www.google.com/maps?q=' . rawurlencode($r['latitude'] . ',' . $r['longitude']) : '',
                'listed'      => $r['created_at'] ? date('M Y', strtotime($r['created_at'])) : '',
            ];
        }
        return $out;
    }

    public function login(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard.php');
        }

        $error = '';
        if ($this->isPost()) {
            Csrf::check();
            $email    = mb_substr(trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : ''), 0, 150);
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

            $user = $email !== '' ? Auth::attempt($email, $password) : null;

            if ($user === 'locked') {
                $error = 'Too many failed login attempts. Please wait 15 minutes and try again.';
            } elseif (!$user) {
                $error = 'Incorrect email or password.';
            } else {
                Auth::login($user);
                audit_log($this->pdo, (int) $user['id'], 'login');
                $this->redirect($this->homeFor($user));
            }
        }

        View::render('auth/login', ['error' => $error]);
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/index.php');
    }

    public function pending(): void
    {
        Auth::requireLogin();
        $user = Auth::user();
        if ($user['status'] === 'active') {
            $this->redirect('/dashboard.php');
        }
        View::render('auth/pending', [
            'user'        => $user,
            'isSuspended' => $user['status'] === 'suspended',
        ]);
    }
}