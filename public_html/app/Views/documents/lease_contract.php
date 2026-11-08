<?php
/**
 * app/Views/documents/lease_contract.php — printable lease contract.
 * $contract comes from App\Services\ContractService (the tenant's negotiated contract,
 * or the standard template when none has been prepared).
 */
use App\Services\ContractService;

$tenantDisplay = $lease['tenant_full_name'] ?? $lease['tenant_name'];
$businessName  = $lease['business_name'] ?: ($lease['app_business_name'] ?? '');
$fmtDate = static fn(?string $d) => $d ? (new DateTime($d))->format('F j, Y') : '—';
$isDraft = $contract['status'] === ContractService::STATUS_DRAFT;
$peso = static fn($v) => '₱' . number_format((float) $v, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Lease Contract — <?= htmlspecialchars($tenantDisplay) ?></title>
<style>
  body { font-family: Georgia, 'Times New Roman', serif; max-width: 760px; margin: 50px auto; color: #16181D; line-height: 1.7; padding: 0 24px; }
  .letterhead { text-align: center; margin-bottom: 30px; }
  .letterhead h1 { font-size: 20px; letter-spacing: .04em; margin: 0; color: #102340; }
  .disclaimer { background: #FCF1E0; border: 1px solid #E8A33D; border-radius: 8px; padding: 14px 16px; font-size: 12.5px; font-family: -apple-system, sans-serif; margin-bottom: 30px; }
  .banner { border-radius: 8px; padding: 12px 16px; font-size: 13px; font-family: -apple-system, sans-serif; margin-bottom: 20px; text-align:center; font-weight:600; }
  .banner.draft { background:#F4F6F9; border:2px dashed #9AA6B5; color:#475569; }
  .banner.archived { background:#E7EEF6; border:1px solid #678EBC; color:#102340; }
  .meta { display: flex; justify-content: space-between; font-size: 13px; color: #444; margin-bottom: 24px; flex-wrap: wrap; gap: 8px; }
  h2 { font-size: 15px; text-transform: uppercase; letter-spacing: .05em; border-bottom: 2px solid #102340; padding-bottom: 6px; margin-top: 34px; }
  .party-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 10px; }
  table.terms { width:100%; border-collapse: collapse; font-size: 14px; }
  table.terms td { padding: 6px 4px; border-bottom: 1px solid #E4E7EC; vertical-align: top; }
  table.terms td:first-child { color:#555; width: 45%; }
  .clause { margin-bottom: 16px; }
  .clause .num { font-weight: 700; }
  .clause p { margin: 0; white-space: pre-line; }
  .provisions { white-space: pre-line; }
  .basis { display:block; font-size: 11.5px; color:#666; font-style: italic; margin-top: 2px; }
  ol.refs { padding-left: 20px; font-size: 13px; line-height: 1.55; }
  ol.refs li { margin-bottom: 8px; }
  .source { margin-top: 34px; border: 1px solid #E4E7EC; border-radius: 8px; padding: 12px 16px; font-size: 12px; font-family: -apple-system, sans-serif; color: #444; }
  .source table { width: 100%; border-collapse: collapse; }
  .source td { padding: 3px 0; vertical-align: top; }
  .source td:first-child { color: #777; width: 34%; }
  .signature { margin-top: 50px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
  .print-btn { text-align: center; margin-bottom: 20px; }
  .print-btn button { padding: 10px 20px; font-size: 14px; cursor: pointer; font-family: -apple-system, sans-serif; }
  .watermark { position: fixed; top: 40%; left: 0; right: 0; text-align: center; font-size: 120px; color: rgba(16,35,64,.06); transform: rotate(-24deg); pointer-events: none; font-family: -apple-system, sans-serif; font-weight: 800; }
  @media print { .print-btn { display: none; } }
</style>
</head>
<body>
  <?php if ($isDraft): ?><div class="watermark">DRAFT</div><?php endif; ?>
  <div class="print-btn"><button onclick="window.print()">Print / Save as PDF</button></div>

  <?php if ($isDraft): ?>
    <div class="banner draft">DRAFT (version <?= (int) $contract['version'] ?>). Only you can see this. It is not final and the tenant has not seen it yet.</div>
  <?php elseif ($isArchivedView): ?>
    <div class="banner archived">Archived version <?= (int) $contract['version'] ?>. This version has been replaced by a newer one.</div>
  <?php endif; ?>

  <div class="letterhead">
    <h1>PWESTORA</h1>
    <p style="font-size:12px; color:#666; margin-top:4px;">
      Commercial Lease Agreement<?= $contract['source'] === 'standard' ? ' — Standard Template' : '' ?>
    </p>
  </div>

  <div class="disclaimer">
    <strong>Note:</strong> This document reflects the terms both parties set on the Pwestora platform, built on common
    Philippine commercial-leasing practice. It is <strong>not legal advice</strong> and is not a substitute for review
    by a licensed attorney. Both parties are encouraged to have this reviewed before signing.
  </div>

  <div class="meta">
    <span>Generated: <?= (new DateTime())->format('F j, Y') ?></span>
    <span>
      Reference: LC-<?= str_pad((string) $tenantId, 4, '0', STR_PAD_LEFT) ?><?= $contract['version'] > 0 ? '-v' . (int) $contract['version'] : '' ?>
    </span>
  </div>

  <h2>Parties</h2>
  <div class="party-grid">
    <div><strong>Lessor</strong><br><?= htmlspecialchars((string) $lease['lessor_name']) ?></div>
    <div>
      <strong>Tenant</strong><br><?= htmlspecialchars((string) $tenantDisplay) ?>
      <?php if ($businessName): ?><br><span style="font-size:13px; color:#555;">doing business as <?= htmlspecialchars($businessName) ?></span><?php endif; ?>
    </div>
  </div>

  <h2>Premises</h2>
  <p>
    <strong><?= htmlspecialchars($lease['property_name']) ?></strong> (<?= htmlspecialchars($lease['property_type']) ?>)<?php if (!empty($lease['unit_label'])): ?>, unit <?= htmlspecialchars($lease['unit_label']) ?><?php endif; ?>,
    located at <?= htmlspecialchars($lease['property_address']) ?>.
  </p>

  <h2>Term &amp; Rent</h2>
  <table class="terms">
    <tr><td>Lease term</td><td><strong><?= (int) $contract['term_months'] ?> months</strong></td></tr>
    <tr><td>Commencement date</td><td><?= $fmtDate($contract['start_date']) ?></td></tr>
    <tr><td>Expiry date</td><td><?= $fmtDate($endDate) ?></td></tr>
    <tr><td>Monthly rent</td><td><strong><?= $peso($contract['monthly_rent']) ?></strong>, exclusive of applicable taxes unless stated otherwise
      <span class="basis">Legal basis: <?= htmlspecialchars(ContractService::RENT_REFERENCE) ?></span></td></tr>
    <?php if ($contract['due_day'] > 0): ?>
      <tr><td>Rent due</td><td>On or before day <?= (int) $contract['due_day'] ?> of each month</td></tr>
    <?php endif; ?>
    <?php if ($contract['source'] !== 'standard'): ?>
      <tr><td>Security deposit</td><td><?= $peso($contract['security_deposit']) ?></td></tr>
      <tr><td>Advance rent</td><td><?= $peso($contract['advance_rent']) ?></td></tr>
    <?php endif; ?>
  </table>

  <h2><?= $contract['source'] === 'standard' ? 'Standard Clauses' : 'Terms and Conditions' ?></h2>
  <?php foreach ($contract['clauses'] as $i => $c): ?>
    <div class="clause">
      <p><span class="num"><?= $i + 1 ?>. <?= htmlspecialchars($c['label']) ?>.</span> <?= htmlspecialchars($c['text']) ?></p>
      <?php if (($c['ref'] ?? '') !== ''): ?><span class="basis">Legal basis: <?= htmlspecialchars($c['ref']) ?></span><?php endif; ?>
    </div>
  <?php endforeach; ?>

  <?php if (trim($contract['special_provisions']) !== ''): ?>
    <h2>Special Provisions</h2>
    <p class="provisions"><?= htmlspecialchars($contract['special_provisions']) ?></p>
  <?php endif; ?>

  <h2>Legal Basis and References</h2>
  <p style="font-size:13px; margin-top:0;">This agreement is built on the following Philippine laws and rules. Where this contract is silent, the Civil Code provisions on lease apply.</p>
  <ol class="refs">
    <?php foreach (ContractService::legalReferences() as $ref): ?>
      <li><strong><?= htmlspecialchars($ref['law']) ?></strong>: <?= htmlspecialchars($ref['use']) ?></li>
    <?php endforeach; ?>
  </ol>

  <h2>Acknowledgment</h2>
  <?php if ($contract['status'] === ContractService::STATUS_ACCEPTED && $contract['accepted_at']): ?>
    <p>
      The tenant reviewed and electronically accepted version <?= (int) $contract['version'] ?> of this contract on the
      Pwestora platform on <strong><?= (new DateTime($contract['accepted_at']))->format('F j, Y \a\t g:i A') ?></strong>.
      The lessor finalized it on <?= $fmtDate($contract['finalized_at']) ?>.
    </p>
  <?php elseif ($contract['status'] === ContractService::STATUS_FINALIZED): ?>
    <p>
      The lessor finalized this contract on <?= $fmtDate($contract['finalized_at']) ?>, after negotiation with the tenant
      on the Pwestora platform. It is awaiting the tenant's acceptance.
    </p>
  <?php elseif ($contract['status'] === ContractService::STATUS_REJECTED): ?>
    <p style="color:#b91c1c; font-weight:600;">
      The tenant reviewed version <?= (int) $contract['version'] ?> of this contract and requested revisions / declined acceptance
      <?= !empty($contract['rejected_at']) ? 'on ' . (new DateTime($contract['rejected_at']))->format('F j, Y \a\t g:i A') : '' ?>.
      <?= !empty($contract['rejection_reason']) ? '<br>Tenant feedback: "' . htmlspecialchars($contract['rejection_reason']) . '"' : '' ?>
    </p>
  <?php elseif ($contract['source'] === 'standard'): ?>
    <p>
      By proceeding on the Pwestora platform, the tenant confirmed acknowledgment of each clause above at the time
      of application, and the lessor confirmed the property and lease terms at the time of listing and application review.
    </p>
  <?php else: ?>
    <p>This draft has not been finalized.</p>
  <?php endif; ?>

  <div class="source">
    <strong style="display:block; margin-bottom:6px; color:#102340;">Document source and verification</strong>
    <table>
      <tr><td>Prepared through</td><td>Pwestora Commercial Leasing Platform (system-generated)</td></tr>
      <tr><td>Based on template</td><td><?= htmlspecialchars($contract['template_name'] ?: ContractService::BUILTIN_TEMPLATE_NAME) ?><?= $contract['source'] === 'standard' ? ' (standard, not customized)' : '' ?></td></tr>
      <tr><td>Template origin</td><td>Common Philippine commercial-leasing practice, with each clause cited to the laws listed above</td></tr>
      <tr><td>Contract reference</td><td>LC-<?= str_pad((string) $tenantId, 4, '0', STR_PAD_LEFT) ?><?= $contract['version'] > 0 ? '-v' . (int) $contract['version'] : '' ?></td></tr>
      <tr><td>Document fingerprint</td><td style="font-family:monospace; letter-spacing:.05em;"><?= ContractService::documentFingerprint($tenantId, $contract) ?></td></tr>
      <tr><td>Status</td><td><?= htmlspecialchars(ContractService::statusLabel($contract['status'])) ?></td></tr>
    </table>
    <p style="margin:8px 0 0; color:#777;">The fingerprint is calculated from the terms and clauses above. A printed copy can be checked against the record in Pwestora: if any wording was changed, the fingerprint will not match.
      This template is not a government-accredited or lawyer-certified form. For full legal effect against third persons, have the signed contract notarized.</p>
  </div>

  <div class="signature">
    <div>
      <p>_________________________</p>
      <p style="font-size:13px; color:#555;"><?= htmlspecialchars((string) $lease['lessor_name']) ?><br>Lessor</p>
    </div>
    <div>
      <p>_________________________</p>
      <p style="font-size:13px; color:#555;"><?= htmlspecialchars((string) $tenantDisplay) ?><br>Tenant</p>
    </div>
  </div>
</body>
</html>
