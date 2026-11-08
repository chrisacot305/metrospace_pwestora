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
    return showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      isDismissible: !isGated,
      enableDrag: !isGated,
      backgroundColor: Colors.transparent,
      builder: (_) => PopScope(
        canPop: !isGated,
        child: ViolationNoticeSheet(
          violation: violation,
          isGated: isGated,
        ),
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

  double get _penalty => widget.violation['penalty_amount'] is num
      ? (widget.violation['penalty_amount'] as num).toDouble()
      : (_strike == 2 ? 500.0 : (_strike >= 3 ? 1500.0 : 0.0));

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
          // Drag handle
          Center(
            child: Container(
              margin: const EdgeInsets.only(top: 12, bottom: 8),
              width: 44,
              height: 5,
              decoration: BoxDecoration(
                color: AppColors.ink300,
                borderRadius: BorderRadius.circular(10),
              ),
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
                      : (_strike == 2 ? Icons.warning_amber_rounded : Icons.info_outline_rounded),
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
                      'FORMAL LEASE CITATION',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 1.2,
                        color: _strikeColor,
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
                  label: 'Eviction',
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

  Widget _buildActionButtons() {
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
                Text(
                  'Citation Acknowledged',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF15803D),
                    letterSpacing: -0.1,
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
                label: const Text(
                  'Download Formal Notice of Eviction (PDF)',
                  style: TextStyle(fontSize: 13, color: AppColors.ink700, fontWeight: FontWeight.w600),
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
              label: Text(
                _isSubmitting ? 'Logging Acknowledgment...' : 'Acknowledge Eviction Notice',
                style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
              ),
            ),
          ),
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
              label: const Text(
                'Download Formal Notice of Eviction (PDF)',
                style: TextStyle(fontSize: 13, color: AppColors.ink700, fontWeight: FontWeight.w600),
              ),
            ),
          ),
        ],
      );
    }

    return SizedBox(
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
                  Text(
                    'Acknowledge Receipt & Compliance',
                    style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
                  ),
                ],
              ),
      ),
    );
  }
}
