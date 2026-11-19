import 'package:flutter/material.dart';
import '../theme.dart';
import '../services/api_service.dart';

class ViolationNoticeSheet extends StatefulWidget {
  final Map<String, dynamic> violation;
  final bool isGated; // If true, tenant must tap acknowledge before dismissing

  const ViolationNoticeSheet({
    super.key,
    required this.violation,
    this.isGated = true,
  });

  /// Static helper to display the modal gate
  static Future<bool?> show(
    BuildContext context, {
    required Map<String, dynamic> violation,
    bool isGated = true,
  }) {
    final ack = violation['acknowledged'];
    final isAck = ack == true || ack == 1 || ack == '1';
    final disp = violation['disputed'];
    final isDisp = disp == true || disp == 1 || disp == '1';
    final rev = violation['is_revoked'];
    final isRev = rev == true || rev == 1 || rev == '1';
    final effectiveGated = isGated && !isAck && !isDisp && !isRev;

    return showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      isDismissible: true,
      enableDrag: true,
      backgroundColor: Colors.transparent,
      builder: (_) => ViolationNoticeSheet(
        violation: violation,
        isGated: effectiveGated,
      ),
    );
  }

  @override
  State<ViolationNoticeSheet> createState() => _ViolationNoticeSheetState();
}

class _ViolationNoticeSheetState extends State<ViolationNoticeSheet> {
  bool _isSubmitting = false;

  int get _strike => widget.violation['strike'] is int
      ? widget.violation['strike'] as int
      : int.tryParse(widget.violation['strike']?.toString() ?? '1') ?? 1;

  bool get _isLateRent =>
      _category.toLowerCase().contains('past_due') ||
      _category.toLowerCase().contains('late') ||
      _category.toLowerCase().contains('overdue');

  double get _penalty {
    final serverAmt = (widget.violation['fine_amount'] ?? widget.violation['penalty_amount']) as num?;
    if (serverAmt != null) return serverAmt.toDouble();
    if (_strike >= 3) return 1000.0;
    if (_strike == 2 && _isLateRent) return 500.0;
    return 0.0;
  }

  String get _category => widget.violation['category']?.toString() ?? 'Lease Violation';
  String get _contractRef => (widget.violation['contract_ref'] ?? '').toString();
  String get _clauseText => (widget.violation['clause_text'] ?? '').toString();
  String get _clauseRef => (widget.violation['clause_ref'] ?? '').toString();
  String get _clause => _contractRef.isNotEmpty ? '$_contractRef · $_category' : (widget.violation['clause']?.toString() ?? _category);
  String get _description => widget.violation['description']?.toString() ?? '';
  String get _issuedAt => widget.violation['issued_at']?.toString() ?? 'Recently';
  String? get _imageUrl => widget.violation['image_url']?.toString();

  Color get _strikeColor {
    if (_strike >= 3) return AppColors.error;
    if (_strike == 2) return const Color(0xFF1E6BFF); // Electric Blue
    return const Color(0xFF0284C7); // Cobalt / Sky Blue
  }

  Color get _strikeSoftColor {
    if (_strike >= 3) return AppColors.errorSoft;
    if (_strike == 2) return const Color(0xFFEFF6FF); // Soft Ice Blue Tint
    return const Color(0xFFF0F7FF); // Pale Blue Tint
  }

  Future<void> _handleAcknowledge() async {
    setState(() => _isSubmitting = true);
    final id = widget.violation['id'] is int ? widget.violation['id'] as int : 0;
    final tid = widget.violation['tenant_id'] is int
        ? widget.violation['tenant_id'] as int
        : (widget.violation['tenant_id'] != null
            ? int.tryParse(widget.violation['tenant_id'].toString())
            : null);
    await ApiService.acknowledgeViolation(id, strike: _strike, tenantId: tid);

    if (!mounted) return;
    setState(() {
      _isSubmitting = false;
      _localAcknowledged = true;
    });
    Navigator.of(context).pop(true);

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: AppColors.primary,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        content: Row(
          children: const [
            Icon(Icons.check_circle_rounded, color: AppColors.success, size: 20),
            SizedBox(width: 10),
            Expanded(
              child: Text(
                'Citation acknowledged. Timestamp recorded to audit ledger.',
                style: TextStyle(fontSize: 13, color: Colors.white, fontWeight: FontWeight.w500),
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;

    return Container(
      constraints: BoxConstraints(
        maxHeight: MediaQuery.of(context).size.height * 0.90,
      ),
      decoration: const BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Top bar with Drag handle & Close (✕) button
          Padding(
            padding: const EdgeInsets.only(top: 10, left: 16, right: 16, bottom: 4),
            child: Row(
              children: [
                const SizedBox(width: 32),
                Expanded(
                  child: Center(
                    child: Container(
                      width: 44,
                      height: 5,
                      decoration: BoxDecoration(
                        color: AppColors.ink300,
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                  ),
                ),
                InkWell(
                  onTap: () => Navigator.of(context).pop(false),
                  borderRadius: BorderRadius.circular(16),
                  child: Container(
                    width: 32,
                    height: 32,
                    decoration: const BoxDecoration(
                      color: AppColors.borderLight,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.close_rounded,
                      size: 18,
                      color: AppColors.ink500,
                    ),
                  ),
                ),
              ],
            ),
          ),

          Flexible(
            child: SingleChildScrollView(
              padding: EdgeInsets.fromLTRB(20, 8, 20, 24 + bottomInset),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Header Hero Card
                  _buildHeaderHero(),
                  const SizedBox(height: 18),

                  // Strike Progress Stepper
                  _buildStrikeProgress(),
                  const SizedBox(height: 20),

                  // Contract Clause Mapping Card
                  _buildClauseCard(),
                  const SizedBox(height: 16),

                  // Financial Penalty (if Strike >= 2)
                  if (_strike >= 2) ...[
                    _buildPenaltyCard(),
                    const SizedBox(height: 16),
                  ],

                  // Photo Evidence Preview
                  if (_imageUrl != null && _imageUrl!.isNotEmpty) ...[
                    _buildEvidenceCard(),
                    const SizedBox(height: 16),
                  ],

                  // Audit & Read Receipt Info Box
                  _buildAuditInfoBox(),
                  const SizedBox(height: 24),

                  // Action Buttons
                  _buildActionButtons(),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHeaderHero() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: _strike >= 3
              ? [const Color(0xFF3B0712), const Color(0xFF1E0308)]
              : [const Color(0xFF0C1B38), const Color(0xFF081429)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(
          color: _strike >= 3
              ? AppColors.error.withValues(alpha: 0.5)
              : const Color(0xFF1E3A5F),
          width: 1.2,
        ),
        boxShadow: [
          BoxShadow(
            color: (_strike >= 3 ? AppColors.error : AppColors.electricBlue).withValues(alpha: 0.15),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: _strikeColor.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: _strikeColor.withValues(alpha: 0.4)),
                ),
                child: Icon(
                  _strike >= 3
                      ? Icons.gavel_rounded
                      : (_strike == 2 ? Icons.warning_rounded : Icons.info_outline_rounded),
                  color: _strikeColor,
                  size: 26,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _isRevoked
                          ? 'REVOKED CITATION'
                          : (_isDisputed ? 'CITATION UNDER DISPUTE' : 'FORMAL LEASE CITATION'),
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 1.2,
                        color: _isRevoked
                            ? const Color(0xFF94A3B8)
                            : (_isDisputed ? AppColors.electricBlue : _strikeColor),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _category,
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                        color: Colors.white,
                        letterSpacing: -0.3,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            _description,
            style: TextStyle(
              fontSize: 13,
              color: Colors.white.withValues(alpha: 0.85),
              height: 1.45,
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Icon(Icons.schedule_rounded, size: 14, color: Colors.white.withValues(alpha: 0.6)),
              const SizedBox(width: 6),
              Text(
                'Issued: $_issuedAt',
                style: TextStyle(
                  fontSize: 12,
                  color: Colors.white.withValues(alpha: 0.65),
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildStrikeProgress() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: AppColors.bg,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Escalation Level',
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  color: AppColors.ink900,
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: _strikeSoftColor,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: _strikeColor.withValues(alpha: 0.3)),
                ),
                child: Text(
                  'Strike $_strike of 3',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                    color: _strikeColor,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          // 3-step indicator bars
          Row(
            children: [
              Expanded(
                child: _buildProgressBar(
                  step: 1,
                  active: _strike >= 1,
                  label: 'Warning',
                  color: const Color(0xFF0284C7),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _buildProgressBar(
                  step: 2,
                  active: _strike >= 2,
                  label: 'Fine Injected',
                  color: const Color(0xFF1E6BFF),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _buildProgressBar(
                  step: 3,
                  active: _strike >= 3,
                  label: 'Pre-Eviction',
                  color: AppColors.error,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildProgressBar({
    required int step,
    required bool active,
    required String label,
    required Color color,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          height: 6,
          decoration: BoxDecoration(
            color: active ? color : AppColors.ink300,
            borderRadius: BorderRadius.circular(4),
          ),
        ),
        const SizedBox(height: 6),
        Text(
          'S$step: $label',
          style: TextStyle(
            fontSize: 10,
            fontWeight: active ? FontWeight.w700 : FontWeight.w500,
            color: active ? AppColors.ink900 : AppColors.ink400,
          ),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
      ],
    );
  }

  Widget _buildClauseCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.accentSoft.withValues(alpha: 0.4),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.electricBlue.withValues(alpha: 0.2)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.description_outlined, color: AppColors.electricBlue, size: 20),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Lease Agreement Section Violated',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.3,
                    color: AppColors.electricBlueDark,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  _clause,
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: AppColors.ink900,
                  ),
                ),
                if (_clauseText.isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Text(
                    '"$_clauseText"',
                    style: const TextStyle(
                      fontSize: 12,
                      fontStyle: FontStyle.italic,
                      color: AppColors.ink700,
                      height: 1.4,
                    ),
                  ),
                ],
                if (_clauseRef.isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Text(
                    'Legal basis: $_clauseRef',
                    style: const TextStyle(
                      fontSize: 11,
                      color: AppColors.ink500,
                    ),
                  ),
                ],
                if (_description.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Text(
                    _description,
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppColors.ink700,
                      height: 1.35,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPenaltyCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _strike >= 3 ? AppColors.errorSoft : const Color(0xFFEFF6FF),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: (_strike >= 3 ? AppColors.error : const Color(0xFFBFDBFE)).withValues(alpha: 0.8),
        ),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: _strike >= 3 ? AppColors.error.withValues(alpha: 0.15) : const Color(0xFFDBEAFE),
              shape: BoxShape.circle,
            ),
            child: Icon(
              Icons.receipt_long_rounded,
              color: _strike >= 3 ? AppColors.error : const Color(0xFF1E6BFF),
              size: 22,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _strike >= 3 ? 'Contractual Breach Fine & Default' : 'Automated Ledger Penalty',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: _strike >= 3 ? AppColors.error : const Color(0xFF1E40AF),
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  '₱${_penalty.toStringAsFixed(0)}',
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                    color: _strike >= 3 ? AppColors.error : const Color(0xFF0A1832),
                  ),
                ),
                Text(
                  'Applied directly to upcoming monthly billing statement.',
                  style: TextStyle(
                    fontSize: 11,
                    color: _strike >= 3 ? AppColors.error : const Color(0xFF2563EB),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildEvidenceCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.bg,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: const [
              Icon(Icons.camera_alt_outlined, size: 16, color: AppColors.ink500),
              SizedBox(width: 8),
              Text(
                'Lessor Timestamped Evidence',
                style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.ink700),
              ),
            ],
          ),
          const SizedBox(height: 10),
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: Image.network(
              _imageUrl!,
              height: 140,
              width: double.infinity,
              fit: BoxFit.cover,
              errorBuilder: (context, error, stackTrace) => Container(
                height: 100,
                color: AppColors.ink300,
                alignment: Alignment.center,
                child: const Text('Evidence photo attached', style: TextStyle(color: AppColors.ink500, fontSize: 12)),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildAuditInfoBox() {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Row(
        children: const [
          Icon(Icons.verified_user_outlined, size: 18, color: AppColors.ink500),
          SizedBox(width: 10),
          Expanded(
            child: Text(
              'Immutable Audit Record: Your viewing and acknowledgment timestamps are cryptographically logged to the property compliance registry.',
              style: TextStyle(
                fontSize: 11,
                color: AppColors.ink500,
                height: 1.4,
              ),
            ),
          ),
        ],
      ),
    );
  }

  bool? _localAcknowledged;
  bool get _isAcknowledged {
    if (_localAcknowledged != null) return _localAcknowledged!;
    final ack = widget.violation['acknowledged'];
    return ack == true || ack == 1 || ack == '1';
  }

  bool? _localDisputed;
  bool get _isDisputed {
    if (_localDisputed != null) return _localDisputed!;
    final disp = widget.violation['disputed'];
    return disp == true || disp == 1 || disp == '1';
  }

  String get _disputeStatus => (widget.violation['dispute_status'] ?? '').toString().toLowerCase();
  String? get _rejectionNote => widget.violation['dispute_resolution_note']?.toString();
  bool get _isDisputeRejected => _disputeStatus == 'rejected';

  bool get _isRevoked {
    final rev = widget.violation['is_revoked'];
    return rev == true || rev == 1 || rev == '1';
  }

  bool get _isPaid {
    final p = widget.violation['is_paid'];
    return p == true || p == 1 || p == '1';
  }

  Widget _buildActionButtons() {
    if (_isPaid) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 16),
        decoration: BoxDecoration(
          color: const Color(0xFFF0FDF4),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFF86EFAC), width: 1.2),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: const [
            Icon(Icons.verified_rounded, color: Color(0xFF16A34A), size: 22),
            SizedBox(width: 10),
            Flexible(
              child: Text(
                'Citation Penalty Paid & Settled',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF15803D),
                ),
              ),
            ),
          ],
        ),
      );
    }
    if (_isRevoked) {
      return Column(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 16),
            decoration: BoxDecoration(
              color: const Color(0xFFF1F5F9),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: const Color(0xFFCBD5E1), width: 1.2),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: const [
                Icon(Icons.check_circle_outline_rounded, color: AppColors.ink500, size: 22),
                SizedBox(width: 10),
                Flexible(
                  child: Text(
                    'Citation Revoked by Lessor',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      color: AppColors.ink700,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            height: 48,
            child: ElevatedButton(
              onPressed: () => Navigator.of(context).pop(),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
              child: const Text('Close', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
            ),
          ),
        ],
      );
    }

    if (_isDisputed && !_isDisputeRejected) {
      return Column(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 16),
            decoration: BoxDecoration(
              color: AppColors.accentSoft,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: const Color(0xFFBFDBFE), width: 1.2),
            ),
            child: Column(
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: const [
                    Icon(Icons.pending_actions_rounded, color: AppColors.electricBlue, size: 22),
                    SizedBox(width: 8),
                    Flexible(
                      child: Text(
                        'Dispute Under Review',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                          color: AppColors.midnightNavy,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                const Text(
                  'Your explanation was forwarded to the lessor. Discussion is open in your in-app chat thread. Strike is on hold pending lessor decision.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 12,
                    color: AppColors.ink500,
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            height: 48,
            child: ElevatedButton(
              onPressed: () => Navigator.of(context).pop(),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
              child: const Text(
                'Close Notice',
                style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
              ),
            ),
          ),
        ],
      );
    }

    if (_isAcknowledged) {
      return Column(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 16),
            decoration: BoxDecoration(
              color: const Color(0xFFF0FDF4),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: const Color(0xFFBBF7D0), width: 1.2),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: const [
                Icon(Icons.check_circle_rounded, color: Color(0xFF16A34A), size: 22),
                SizedBox(width: 10),
                Flexible(
                  child: Text(
                    'Citation Acknowledged',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF15803D),
                      letterSpacing: -0.1,
                    ),
                  ),
                ),
              ],
            ),
          ),
          if (_strike >= 3) ...[
            const SizedBox(height: 10),
            SizedBox(
              width: double.infinity,
              child: TextButton.icon(
                onPressed: () {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Eviction Notice PDF reference generated: Ref #EV-103'),
                      behavior: SnackBarBehavior.floating,
                    ),
                  );
                },
                icon: const Icon(Icons.download_rounded, size: 18, color: AppColors.ink700),
                label: const Flexible(
                  child: Text(
                    'Download Eviction Notice (PDF)',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(fontSize: 13, color: AppColors.ink700, fontWeight: FontWeight.w600),
                  ),
                ),
              ),
            ),
          ],
        ],
      );
    }

    if (_strike >= 3) {
      return Column(
        children: [
          SizedBox(
            width: double.infinity,
            height: 52,
            child: ElevatedButton.icon(
              onPressed: _isSubmitting ? null : _handleAcknowledge,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.error,
                foregroundColor: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
              icon: _isSubmitting
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                    )
                  : const Icon(Icons.assignment_turned_in_rounded),
              label: Flexible(
                child: Text(
                  _isSubmitting ? 'Logging Acknowledgment...' : 'Acknowledge Eviction Notice',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
                ),
              ),
            ),
          ),
          if (!_isLateRent) ...[
            const SizedBox(height: 10),
            SizedBox(
              width: double.infinity,
              height: 48,
              child: OutlinedButton.icon(
                onPressed: _isSubmitting ? null : _showDisputeDialog,
                icon: const Icon(Icons.help_outline_rounded, size: 18, color: AppColors.electricBlue),
                label: const Flexible(
                  child: Text(
                    'Dispute Citation (Lessor Review)',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.electricBlue),
                  ),
                ),
                style: OutlinedButton.styleFrom(
                  side: const BorderSide(color: Color(0xFFBFDBFE)),
                  backgroundColor: AppColors.accentSoft,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
              ),
            ),
          ],
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            child: TextButton.icon(
              onPressed: () {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Eviction Notice PDF reference generated: Ref #EV-103'),
                    behavior: SnackBarBehavior.floating,
                  ),
                );
              },
              icon: const Icon(Icons.download_rounded, size: 18, color: AppColors.ink700),
              label: const Flexible(
                child: Text(
                  'Download Eviction Notice (PDF)',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(fontSize: 13, color: AppColors.ink700, fontWeight: FontWeight.w600),
                ),
              ),
            ),
          ),
          const SizedBox(height: 6),
          SizedBox(
            width: double.infinity,
            child: TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: const Text(
                'Close Notice',
                style: TextStyle(fontSize: 13, color: AppColors.ink500, fontWeight: FontWeight.w600),
              ),
            ),
          ),
        ],
      );
    }

    return Column(
      children: [
        if (_isDisputeRejected) ...[
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 14),
            margin: const EdgeInsets.only(bottom: 14),
            decoration: BoxDecoration(
              color: const Color(0xFFFEF2F2),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: const Color(0xFFFECACA), width: 1.2),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(Icons.report_problem_rounded, color: AppColors.error, size: 20),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Dispute Rejected (Violation Upheld)',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF991B1B),
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        _rejectionNote ?? 'Lessor reviewed your explanation and upheld this citation. Strike $_strike is active on your compliance record.',
                        style: const TextStyle(
                          fontSize: 11.5,
                          color: Color(0xFF7F1D1D),
                          height: 1.35,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
        SizedBox(
          width: double.infinity,
          height: 52,
          child: ElevatedButton(
            onPressed: _isSubmitting ? null : _handleAcknowledge,
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.primary,
              foregroundColor: Colors.white,
              elevation: 0,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            ),
            child: _isSubmitting
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                  )
                : Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: const [
                      Icon(Icons.check_circle_outline_rounded, size: 20),
                      SizedBox(width: 8),
                      Flexible(
                        child: Text(
                          'Acknowledge Receipt & Compliance',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
                        ),
                      ),
                    ],
                  ),
          ),
        ),
        if (!_isLateRent && !_isDisputeRejected) ...[
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            height: 48,
            child: OutlinedButton.icon(
              onPressed: _isSubmitting ? null : _showDisputeDialog,
              icon: const Icon(Icons.help_outline_rounded, size: 18, color: AppColors.electricBlue),
              label: const Flexible(
                child: Text(
                  'Dispute Citation (Lessor Review)',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.electricBlue),
                ),
              ),
              style: OutlinedButton.styleFrom(
                side: const BorderSide(color: Color(0xFFBFDBFE)),
                backgroundColor: AppColors.accentSoft,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
            ),
          ),
        ],
        const SizedBox(height: 8),
        SizedBox(
          width: double.infinity,
          child: TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text(
              'Close Notice',
              style: TextStyle(fontSize: 13, color: AppColors.ink500, fontWeight: FontWeight.w600),
            ),
          ),
        ),
      ],
    );
  }

  void _showDisputeDialog() {
    final textController = TextEditingController();
    bool isSubmittingDispute = false;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) {
          final bottomInset = MediaQuery.of(context).viewInsets.bottom;
          return Container(
            padding: EdgeInsets.fromLTRB(20, 16, 20, 20 + bottomInset),
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 40,
                    height: 4,
                    margin: const EdgeInsets.only(bottom: 16),
                    decoration: BoxDecoration(
                      color: AppColors.ink300,
                      borderRadius: BorderRadius.circular(4),
                    ),
                  ),
                ),
                Row(
                  children: const [
                    Icon(Icons.gavel_rounded, color: AppColors.electricBlue, size: 22),
                    SizedBox(width: 8),
                    Text(
                      'Dispute Lease Citation',
                      style: TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w800,
                        color: AppColors.ink900,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  'State your defense or explanation regarding the citation for "$_category". This will place the citation under review and immediately notify the lessor in your chat thread.',
                  style: const TextStyle(fontSize: 13, color: AppColors.ink500, height: 1.4),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: textController,
                  maxLines: 4,
                  autofocus: true,
                  decoration: InputDecoration(
                    hintText: 'Enter your explanation or factual clarification...',
                    hintStyle: const TextStyle(fontSize: 13, color: AppColors.ink400),
                    filled: true,
                    fillColor: AppColors.bg,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: AppColors.border),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: AppColors.primary, width: 1.5),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: isSubmittingDispute ? null : () => Navigator.of(sheetContext).pop(),
                        style: OutlinedButton.styleFrom(
                          minimumSize: const Size(0, 48),
                          side: const BorderSide(color: AppColors.border),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                        child: const Text('Cancel', style: TextStyle(color: AppColors.ink700, fontWeight: FontWeight.w600)),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      flex: 2,
                      child: ElevatedButton(
                        onPressed: isSubmittingDispute
                            ? null
                            : () async {
                                final text = textController.text.trim();
                                if (text.isEmpty) {
                                  ScaffoldMessenger.of(sheetContext).showSnackBar(
                                    const SnackBar(
                                      content: Text('Please write an explanation for the dispute.'),
                                      behavior: SnackBarBehavior.floating,
                                    ),
                                  );
                                  return;
                                }

                                final messenger = ScaffoldMessenger.of(context);
                                final nav = Navigator.of(sheetContext);
                                setSheetState(() => isSubmittingDispute = true);

                                final id = widget.violation['id'] is int
                                    ? widget.violation['id'] as int
                                    : int.tryParse(widget.violation['id']?.toString() ?? '0') ?? 0;
                                final tid = widget.violation['tenant_id'] is int
                                    ? widget.violation['tenant_id'] as int
                                    : (widget.violation['tenant_id'] != null
                                        ? int.tryParse(widget.violation['tenant_id'].toString())
                                        : null);

                                final res = await ApiService.disputeViolation(
                                  violationId: id,
                                  reason: text,
                                  tenantId: tid,
                                );

                                nav.pop();
                                if (!mounted) return;

                                if (res['ok'] == true) {
                                  setState(() => _localDisputed = true);
                                  messenger.showSnackBar(
                                    SnackBar(
                                      backgroundColor: const Color(0xFF1E293B),
                                      behavior: SnackBarBehavior.floating,
                                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                      content: Row(
                                        children: const [
                                          Icon(Icons.send_rounded, color: Color(0xFF38BDF8), size: 18),
                                          SizedBox(width: 10),
                                          Expanded(
                                            child: Text(
                                              'Dispute filed. Message forwarded to lessor chat thread.',
                                              style: TextStyle(fontSize: 13, color: Colors.white, fontWeight: FontWeight.w500),
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  );
                                } else {
                                  messenger.showSnackBar(
                                    SnackBar(
                                      content: Text(res['message']?.toString() ?? 'Failed to file dispute.'),
                                      backgroundColor: AppColors.error,
                                      behavior: SnackBarBehavior.floating,
                                    ),
                                  );
                                }
                              },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.primary,
                          foregroundColor: Colors.white,
                          elevation: 0,
                          minimumSize: const Size(0, 48),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                        child: isSubmittingDispute
                            ? const SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                              )
                            : const Text(
                                'Submit Dispute',
                                style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
                              ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
