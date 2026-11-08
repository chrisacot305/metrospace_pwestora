<?php
namespace App\Models;

/** Table: lessor_profiles (verification documents uploaded at registration) */
class LessorProfileModel extends Model
{
    public function create(int $userId, array $paths): void
    {
        $this->run(
            'INSERT INTO lessor_profiles (user_id, business_permit_path, sec_dti_path, bir_cert_path, land_title_path, gov_id_path)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $paths['business_permit_path'], $paths['sec_dti_path'], $paths['bir_cert_path'],
             $paths['land_title_path'], $paths['gov_id_path']]
        );
    }

    /** Which lessor a verification file belongs to (used by the protected document viewer). */
    public function ownerOfDocument(string $path): ?int
    {
        $id = $this->value(
            'SELECT user_id FROM lessor_profiles
             WHERE business_permit_path = ? OR sec_dti_path = ? OR bir_cert_path = ? OR land_title_path = ? OR gov_id_path = ?
             LIMIT 1',
            [$path, $path, $path, $path, $path]
        );
        return $id === false ? null : (int) $id;
    }
}
