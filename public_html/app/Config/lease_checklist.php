<?php
/**
 * app/Config/lease_checklist.php  (was includes/lease_checklist.php)
 * Standard commercial-lease clauses reflected in common Philippine
 * practice (2-month security deposit, 5% annual escalation, 30-day
 * notice, etc.). These are GENERIC, common-practice terms — not legal
 * advice, and not tailored to any specific property or jurisdiction
 * nuance. Both parties should have a licensed attorney review the final
 * lease before signing anything based on this template.
 *
 * 'reference' = the Philippine law each clause is based on (shown on the printed
 * contract and on violation notices). Lessors can edit it per template/contract.
 */
return [
    'security_deposit' => [
        'label' => 'Security Deposit',
        'text'  => "A security deposit equivalent to two (2) months' rent will be collected and held. "
                 . "It is refundable within 30 days of lease-end, less any deductions for damages or unpaid obligations.",
        'reference' => "Civil Code (RA 386) Art. 1306 (freedom of stipulation); Arts. 1665 & 1667 (return of premises; lessee liable for deterioration)",
    ],
    'rent_escalation' => [
        'label' => 'Rent Escalation',
        'text'  => "Monthly rent may increase by up to five percent (5%) upon each annual renewal, "
                 . "consistent with standard commercial leasing practice.",
        'reference' => "Civil Code Art. 1306 (stipulation of the parties). RA 9653 (Rent Control Act) covers residential units only, so commercial escalation is by agreement",
    ],
    'use_of_premises' => [
        'label' => 'Use of Premises',
        'text'  => "The leased premises will be used solely for the business purpose stated in the tenant's application, "
                 . "and not for any illegal or unauthorized activity.",
        'reference' => "Civil Code Art. 1657(2) (use for the purpose stipulated); Art. 1673 (judicial ejectment for use not stipulated)",
    ],
    'maintenance_responsibility' => [
        'label' => 'Maintenance Responsibility',
        'text'  => "Structural and major-system repairs are the lessor's responsibility. "
                 . "Interior fixtures, fittings, and day-to-day upkeep are the tenant's responsibility.",
        'reference' => "Civil Code Art. 1654(2) (lessor makes necessary repairs); Arts. 1657(2) & 1663 (lessee's diligent use; duty to report needed repairs)",
    ],
    'no_sublease' => [
        'label' => 'No Subleasing',
        'text'  => "The tenant will not sublease, assign, or transfer the lease to a third party without the lessor's prior written consent.",
        'reference' => "Civil Code Art. 1649 (assignment needs lessor's consent); Art. 1650 (subleasing is allowed unless expressly prohibited, hence this clause)",
    ],
    'termination_notice' => [
        'label' => 'Termination Notice',
        'text'  => "At least thirty (30) days' written notice is required before vacating the premises or terminating the lease early.",
        'reference' => "Civil Code Art. 1306 (stipulation); Arts. 1659 & 1673 (rescission; grounds for judicial ejectment)",
    ],
    'insurance' => [
        'label' => 'Insurance',
        'text'  => "The tenant is responsible for insuring their own business property, inventory, and equipment within the premises.",
        'reference' => "Civil Code Art. 1306 (stipulation); Insurance Code (PD 612, as amended by RA 10607)",
    ],
    'compliance' => [
        'label' => 'Legal Compliance',
        'text'  => "The tenant will comply with all applicable national and local laws — including business permits, "
                 . "fire safety, and zoning regulations — for the duration of the lease.",
        'reference' => "Local Government Code (RA 7160) on business permits; Fire Code of the Philippines (RA 9514); Civil Code Art. 1657",
    ],
];
