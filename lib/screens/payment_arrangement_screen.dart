import 'package:flutter/material.dart';
import '../theme.dart';
import '../services/api_service.dart';

class PaymentArrangementScreen extends StatefulWidget {
  final Map<String, dynamic> lease;
  final VoidCallback? onArrangementSubmitted;

  const PaymentArrangementScreen({
    super.key,
    required this.lease,
    this.onArrangementSubmitted,
  });

  @override
  State<PaymentArrangementScreen> createState() =>
      _PaymentArrangementScreenState();
}

class _PaymentArrangementScreenState extends State<PaymentArrangementScreen> {
  String _selectedPlan = '2 payments';
  String _selectedReason = 'Cash flow delay this month';
  final TextEditingController _noteCtrl = TextEditingController();
  bool _submitting = false;
  bool _loading = true;
  List<dynamic> _existingRequests = [];

  final List<String> _reasons = [
    'Cash flow delay this month',
    'Seasonal slowdown in business',
    'Unexpected business expense',
  ];

  @override
  void initState() {
    super.initState();
    _fetchRequests();
  }

  @override
  void dispose() {
    _noteCtrl.dispose();
    super.dispose();
  }

  int? get _tenantId {
    final tid = widget.lease['tenant_id'] ?? widget.lease['id'];
    return tid is int ? tid : (tid != null ? int.tryParse(tid.toString()) : null);
  }

  double get _securityDeposit {
    final raw = widget.lease['security_deposit'];
    if (raw is num) return raw.toDouble();
    if (raw != null) return double.tryParse(raw.toString()) ?? 0.0;
    return 0.0;
  }

  Future<void> _fetchRequests() async {
    setState(() => _loading = true);
    try {
      final reqs = await ApiService.fetchInstallmentRequests(tenantId: _tenantId);
      if (mounted) {
        setState(() {
          _existingRequests = reqs;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _submitRequest() async {
    setState(() => _submitting = true);
    final note = _noteCtrl.text.trim();
    final combinedReason = note.isNotEmpty
        ? '$_selectedReason • Note: "$note"'
        : _selectedReason;
    final rent = (widget.lease['rent'] as num?)?.toDouble() ?? 12000.0;
    final insts = _getInstallmentsForPlan(rent);
    final scheduleLines = insts
        .map(
          (i) =>
              '• ${i['label']}: ₱${_formatMoney((i['amount'] as num).toDouble())} (${i['timing']})',
        )
        .join('\n');

    try {
      await ApiService.createInstallmentRequest(
        reason: combinedReason,
        planLabel: _selectedPlan,
        tenantId: _tenantId,
      );

      // Auto-post formal proposal details to the chat thread with lessor
      try {
        final notePart = note.isNotEmpty ? '\nNote: "$note"' : '';
        await ApiService.sendMessage(
          '📋 Payment Restructuring Request\n'
          'Plan: $_selectedPlan\n'
          'Reason: $_selectedReason$notePart\n'
          'Schedule:\n$scheduleLines\n'
          'Status: Pending Review (48h Cooling-off Active)',
          tenantId: _tenantId,
        );
      } catch (_) {
        // Chat notification is complementary; proceed even if message post fails
      }

      if (mounted) {
        setState(() => _submitting = false);
        widget.onArrangementSubmitted?.call();
        _showSuccessModal();
      }
    } catch (e) {
      if (mounted) {
        setState(() => _submitting = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Failed to submit request: $e'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }

  Future<void> _confirmCancelRequest(dynamic req) async {
    final rawId = req['id'];
    final reqId = rawId is int
        ? rawId
        : (rawId is num
              ? rawId.toInt()
              : int.tryParse(rawId?.toString() ?? '') ?? 0);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
        title: Row(
          children: const [
            Icon(
              Icons.history_toggle_off_rounded,
              color: Color(0xFFDC2626),
              size: 22,
            ),
            SizedBox(width: 8),
            Text(
              'Cancel Request?',
              style: TextStyle(
                fontSize: 17,
                fontWeight: FontWeight.w800,
                color: Color(0xFF0B1B3D),
              ),
            ),
          ],
        ),
        content: const Text(
          'Within your 48-hour cooling-off window, you can freely cancel this restructuring request with zero penalty. Your payment schedule will remain standard monthly rent.',
          style: TextStyle(
            fontSize: 13,
            color: Color(0xFF64748B),
            height: 1.45,
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text(
              'Keep Request',
              style: TextStyle(
                color: Color(0xFF64748B),
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFDC2626),
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
              elevation: 0,
            ),
            child: const Text(
              'Yes, Cancel Request',
              style: TextStyle(fontWeight: FontWeight.w800),
            ),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      setState(() => _loading = true);
      final success = await ApiService.cancelInstallmentRequest(reqId);
      if (mounted) {
        if (success) {
          try {
            await ApiService.sendMessage(
              '↩️ Restructuring Request Revoked\n'
              'The pending payment restructuring request has been canceled within the 48-hour cooling-off window. Standard rent terms remain active.',
            );
          } catch (_) {}
        }
        widget.onArrangementSubmitted?.call();
        await _fetchRequests();
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              success
                  ? 'Restructuring request cancelled successfully.'
                  : 'Unable to cancel request.',
            ),
            backgroundColor: success
                ? const Color(0xFF0B1B3D)
                : AppColors.error,
          ),
        );
      }
    }
  }

  void _showSuccessModal() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => Container(
        padding: const EdgeInsets.all(24),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: const Color(0xFFE2E8F0),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            const SizedBox(height: 24),
            Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(
                color: const Color(0xFFECFDF5),
                shape: BoxShape.circle,
                border: Border.all(
                  color: const Color(0xFF10B981).withValues(alpha: 0.3),
                  width: 2,
                ),
              ),
              child: const Icon(
                Icons.check_circle_rounded,
                color: Color(0xFF10B981),
                size: 36,
              ),
            ),
            const SizedBox(height: 18),
            const Text(
              'Restructuring Request Submitted!',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w900,
                color: Color(0xFF0B1B3D),
                letterSpacing: -0.3,
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 8),
            const Text(
              'Your lessor has been notified via the Pwestora Portal. You have a 48-hour cooling-off period to revoke this request if you change your mind.',
              style: TextStyle(
                fontSize: 13,
                color: Color(0xFF64748B),
                height: 1.4,
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 24),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () {
                  Navigator.pop(ctx);
                  _fetchRequests();
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF0B1B3D),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                  ),
                  elevation: 0,
                ),
                child: const Text(
                  'Back to Schedule & Requests',
                  style: TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
            ),
            const SizedBox(height: 12),
          ],
        ),
      ),
    );
  }

  List<Map<String, dynamic>> _getInstallmentsForPlan(double rent) {
    final now = DateTime.now();
    const months = [
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];
    final endOfMonthDay = DateTime(now.year, now.month + 1, 0).day;
    final dueMonthShort = months[(now.month - 1).clamp(0, 11)];

    final nextMonthDate = DateTime(now.year, now.month + 1, 15);
    final nextMonthShort = months[(nextMonthDate.month - 1).clamp(0, 11)];

    if (_selectedPlan == '3 payments') {
      final p2d2 = DateTime(
        now.year,
        now.month,
        endOfMonthDay,
      ).add(const Duration(days: 10));
      final p2d2Short = months[(p2d2.month - 1).clamp(0, 11)];
      final p2d3 = DateTime(
        now.year,
        now.month,
        endOfMonthDay,
      ).add(const Duration(days: 20));
      final p2d3Short = months[(p2d3.month - 1).clamp(0, 11)];
      return [
        {
          'label': 'Payment 1 (33%)',
          'amount': rent / 3,
          'timing': '$dueMonthShort $endOfMonthDay, ${now.year}',
        },
        {
          'label': 'Payment 2 (33%)',
          'amount': rent / 3,
          'timing': '$p2d2Short ${p2d2.day}, ${p2d2.year}',
        },
        {
          'label': 'Payment 3 (33%)',
          'amount': rent / 3,
          'timing': '$p2d3Short ${p2d3.day}, ${p2d3.year}',
        },
      ];
    } else {
      return [
        {
          'label': 'Payment 1 (50%)',
          'amount': rent / 2,
          'timing': '$dueMonthShort $endOfMonthDay, ${now.year}',
        },
        {
          'label': 'Payment 2 (50%)',
          'amount': rent / 2,
          'timing': '$nextMonthShort 15, ${nextMonthDate.year}',
        },
      ];
    }
  }

  void _showConfirmationModal(double rent) {
    final installments = _getInstallmentsForPlan(rent);
    bool agreed = false;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setModalState) {
          return Container(
            constraints: BoxConstraints(
              maxHeight: MediaQuery.of(context).size.height * 0.88,
            ),
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Top handle
                const SizedBox(height: 12),
                Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: const Color(0xFFE2E8F0),
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
                const SizedBox(height: 16),

                // Scrollable content
                Flexible(
                  child: SingleChildScrollView(
                    physics: const BouncingScrollPhysics(),
                    padding: const EdgeInsets.symmetric(horizontal: 22),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Icon + Title
                        Row(
                          children: [
                            Container(
                              width: 44,
                              height: 44,
                              decoration: BoxDecoration(
                                color: const Color(0xFFEFF6FF),
                                borderRadius: BorderRadius.circular(14),
                                border: Border.all(
                                  color: const Color(
                                    0xFF1E6BFF,
                                  ).withValues(alpha: 0.2),
                                ),
                              ),
                              child: const Icon(
                                Icons.handshake_rounded,
                                color: Color(0xFF1E6BFF),
                                size: 24,
                              ),
                            ),
                            const SizedBox(width: 14),
                            const Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    'Review Plan Agreement',
                                    style: TextStyle(
                                      fontSize: 18,
                                      fontWeight: FontWeight.w900,
                                      color: Color(0xFF0B1B3D),
                                      letterSpacing: -0.3,
                                    ),
                                  ),
                                  SizedBox(height: 2),
                                  Text(
                                    'Verify milestone dates & terms before submitting',
                                    style: TextStyle(
                                      fontSize: 12,
                                      color: Color(0xFF64748B),
                                      fontWeight: FontWeight.w500,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 18),

                        // Milestone Summary Card
                        Container(
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF8FAFC),
                            borderRadius: BorderRadius.circular(18),
                            border: Border.all(color: const Color(0xFFE2E8F0)),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                mainAxisAlignment:
                                    MainAxisAlignment.spaceBetween,
                                children: [
                                  const Text(
                                    'Proposed Installments',
                                    style: TextStyle(
                                      fontSize: 12.5,
                                      fontWeight: FontWeight.w800,
                                      color: Color(0xFF0B1B3D),
                                    ),
                                  ),
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 8,
                                      vertical: 3,
                                    ),
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFE0E7FF),
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                    child: Text(
                                      _selectedPlan.toUpperCase(),
                                      style: const TextStyle(
                                        fontSize: 10,
                                        fontWeight: FontWeight.w800,
                                        color: Color(0xFF4338CA),
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 12),
                              ...installments.map((inst) {
                                final amount = (inst['amount'] as num)
                                    .toDouble();
                                return Padding(
                                  padding: const EdgeInsets.symmetric(
                                    vertical: 5,
                                  ),
                                  child: Row(
                                    mainAxisAlignment:
                                        MainAxisAlignment.spaceBetween,
                                    children: [
                                      Row(
                                        children: [
                                          const Icon(
                                            Icons.calendar_today_rounded,
                                            size: 14,
                                            color: Color(0xFF1E6BFF),
                                          ),
                                          const SizedBox(width: 8),
                                          Column(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.start,
                                            children: [
                                              Text(
                                                inst['label'],
                                                style: const TextStyle(
                                                  fontSize: 12,
                                                  fontWeight: FontWeight.w700,
                                                  color: Color(0xFF1E293B),
                                                ),
                                              ),
                                              Text(
                                                'Due: ${inst['timing']}',
                                                style: const TextStyle(
                                                  fontSize: 10.5,
                                                  color: Color(0xFF64748B),
                                                ),
                                              ),
                                            ],
                                          ),
                                        ],
                                      ),
                                      Text(
                                        '₱${_formatMoney(amount)}',
                                        style: const TextStyle(
                                          fontSize: 13.5,
                                          fontWeight: FontWeight.w800,
                                          color: Color(0xFF0B1B3D),
                                        ),
                                      ),
                                    ],
                                  ),
                                );
                              }),
                              const Divider(
                                height: 20,
                                color: Color(0xFFE2E8F0),
                              ),
                              Row(
                                mainAxisAlignment:
                                    MainAxisAlignment.spaceBetween,
                                children: [
                                  const Text(
                                    'Total Rent Amount',
                                    style: TextStyle(
                                      fontSize: 12.5,
                                      fontWeight: FontWeight.w700,
                                      color: Color(0xFF64748B),
                                    ),
                                  ),
                                  Text(
                                    '₱${_formatMoney(rent)}',
                                    style: const TextStyle(
                                      fontSize: 16,
                                      fontWeight: FontWeight.w900,
                                      color: Color(0xFF1E6BFF),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 6),
                              Row(
                                children: [
                                  const Text(
                                    'Stated Reason: ',
                                    style: TextStyle(
                                      fontSize: 11,
                                      fontWeight: FontWeight.w600,
                                      color: Color(0xFF64748B),
                                    ),
                                  ),
                                  Expanded(
                                    child: Text(
                                      _selectedReason,
                                      style: const TextStyle(
                                        fontSize: 11,
                                        fontWeight: FontWeight.w700,
                                        color: Color(0xFF0B1B3D),
                                      ),
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                ],
                              ),
                              if (_noteCtrl.text.trim().isNotEmpty) ...[
                                const SizedBox(height: 5),
                                Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    const Text(
                                      'Note to Lessor: ',
                                      style: TextStyle(
                                        fontSize: 11,
                                        fontWeight: FontWeight.w600,
                                        color: Color(0xFF64748B),
                                      ),
                                    ),
                                    Expanded(
                                      child: Text(
                                        '"${_noteCtrl.text.trim()}"',
                                        style: const TextStyle(
                                          fontSize: 11,
                                          fontWeight: FontWeight.w700,
                                          color: Color(0xFF0B1B3D),
                                          fontStyle: FontStyle.italic,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ],
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),

                        // Key Safeguards & 48h Window
                        _buildAgreementPoint(
                          Icons.timer_outlined,
                          '48-Hour Cooling-Off Period:',
                          'You have 2 days from submission to freely cancel this request with zero penalty.',
                          const Color(0xFF1E6BFF),
                        ),
                        const SizedBox(height: 10),
                        _buildAgreementPoint(
                          Icons.verified_rounded,
                          'Zero Late Fines:',
                          'Default late charges are waived as long as each milestone is paid by its due date.',
                          const Color(0xFF10B981),
                        ),
                        const SizedBox(height: 10),
                        _buildAgreementPoint(
                          Icons.timer_outlined,
                          '48-Hour Cancellation Window:',
                          'You can cancel this restructuring request anytime within 48 hours before lessor review.',
                          const Color(0xFF8B5CF6),
                        ),

                        const SizedBox(height: 18),

                        // Mandatory Agreement Checkbox
                        InkWell(
                          onTap: () {
                            setModalState(() {
                              agreed = !agreed;
                            });
                          },
                          borderRadius: BorderRadius.circular(14),
                          child: Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: agreed
                                  ? const Color(0xFFEFF6FF)
                                  : const Color(0xFFF8FAFC),
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(
                                color: agreed
                                    ? const Color(0xFF93C5FD)
                                    : const Color(0xFFE2E8F0),
                                width: 1.2,
                              ),
                            ),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                SizedBox(
                                  width: 24,
                                  height: 24,
                                  child: Checkbox(
                                    value: agreed,
                                    onChanged: (val) {
                                      setModalState(() {
                                        agreed = val ?? false;
                                      });
                                    },
                                    activeColor: const Color(0xFF1E6BFF),
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(5),
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 10),
                                const Expanded(
                                  child: Text(
                                    'I have reviewed the payment milestones and agree to this restructuring request. I understand this formal agreement will be forwarded for lessor approval.',
                                    style: TextStyle(
                                      fontSize: 11.5,
                                      fontWeight: FontWeight.w600,
                                      color: Color(0xFF1E293B),
                                      height: 1.35,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(height: 18),
                      ],
                    ),
                  ),
                ),

                // Action Buttons
                Container(
                  padding: const EdgeInsets.fromLTRB(22, 12, 22, 24),
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    border: Border(top: BorderSide(color: Color(0xFFF1F5F9))),
                  ),
                  child: Row(
                    children: [
                      AppBackButton(
                        onPressed: () => Navigator.pop(ctx),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: ElevatedButton(
                          onPressed: agreed
                              ? () {
                                  Navigator.pop(ctx);
                                  _submitRequest();
                                }
                              : null,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF0B1B3D),
                            disabledBackgroundColor: const Color(0xFFE2E8F0),
                            foregroundColor: Colors.white,
                            disabledForegroundColor: const Color(0xFF94A3B8),
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14),
                            ),
                            elevation: 0,
                          ),
                          child: const Text(
                            'Agree & Submit Request',
                            style: TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 14,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }


  String _formatMoney(num val) {
    final str = val.toStringAsFixed(0);
    final buffer = StringBuffer();
    for (int i = 0; i < str.length; i++) {
      if (i > 0 && (str.length - i) % 3 == 0) {
        buffer.write(',');
      }
      buffer.write(str[i]);
    }
    return buffer.toString();
  }

  @override
  Widget build(BuildContext context) {
    final rent = (widget.lease['rent'] as num?)?.toDouble() ?? 12000.0;

    final now = DateTime.now();
    const months = [
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];
    final endOfMonthDay = DateTime(now.year, now.month + 1, 0).day;
    final dueMonthShort = months[(now.month - 1).clamp(0, 11)];

    final nextMonthDate = DateTime(now.year, now.month + 1, 15);
    final nextMonthShort = months[(nextMonthDate.month - 1).clamp(0, 11)];

    // Dates for 3 payments
    final p2d2 = DateTime(
      now.year,
      now.month,
      endOfMonthDay,
    ).add(const Duration(days: 10));
    final p2d2Short = months[(p2d2.month - 1).clamp(0, 11)];
    final p2d3 = DateTime(
      now.year,
      now.month,
      endOfMonthDay,
    ).add(const Duration(days: 20));
    final p2d3Short = months[(p2d3.month - 1).clamp(0, 11)];

    return Scaffold(
      backgroundColor: const Color(0xFFF4F7FC),
      body: CustomScrollView(
        physics: const BouncingScrollPhysics(),
        slivers: [
          // ================= ARTISTIC MIDNIGHT CURVED HERO APPBAR =================
          SliverAppBar(
            expandedHeight: 210,
            pinned: true,
            backgroundColor: const Color(0xFF071739),
            elevation: 0,
            leading: const AppBackButton(isDark: true),
            flexibleSpace: FlexibleSpaceBar(
              background: Container(
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    colors: [
                      Color(0xFF071739),
                      Color(0xFF0F2C69),
                      Color(0xFF1E6BFF),
                    ],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                ),
                child: Stack(
                  children: [
                    // Decorative glow circle
                    Positioned(
                      top: -30,
                      right: -30,
                      child: Container(
                        width: 140,
                        height: 140,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: const Color(
                            0xFF00E5FF,
                          ).withValues(alpha: 0.12),
                        ),
                      ),
                    ),
                    Positioned(
                      bottom: 20,
                      left: 20,
                      right: 20,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 4,
                            ),
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.16),
                              borderRadius: BorderRadius.circular(20),
                              border: Border.all(
                                color: Colors.white.withValues(alpha: 0.25),
                                width: 0.8,
                              ),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: const [
                                Icon(
                                  Icons.handshake_outlined,
                                  size: 13,
                                  color: Color(0xFF00E5FF),
                                ),
                                SizedBox(width: 5),
                                Text(
                                  'Formal Concession Protocol',
                                  style: TextStyle(
                                    fontSize: 10.5,
                                    fontWeight: FontWeight.w800,
                                    color: Colors.white,
                                    letterSpacing: 0.3,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 8),
                          const Text(
                            'Payment Restructuring',
                            style: TextStyle(
                              fontSize: 22,
                              fontWeight: FontWeight.w900,
                              color: Colors.white,
                              letterSpacing: -0.4,
                            ),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            'Structured split installments to protect cashflow.',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w400,
                              color: Colors.white.withValues(alpha: 0.8),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),

          // ================= MAIN BODY CONTENT =================
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // 1. MUTUAL BENEFIT BAR
                  _buildMutualBenefitBar(),

                  const SizedBox(height: 18),

                  // 2. SECURITY DEPOSIT & ADVANCE SHIELD (LUXURY MINIMAL)
                  _buildSecurityDepositShieldCard(),

                  // 2.5 INSTRUCTIONS & HOW IT WORKS (SMOOTH & INTEGRATED)
                  _buildInstructionsCard(),

                  const SizedBox(height: 24),

                  // 3. ACTIVE / EXISTING REQUESTS (If any)
                  if (_loading)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 8),
                      child: Center(
                        child: SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Color(0xFF1E6BFF),
                          ),
                        ),
                      ),
                    )
                  else if (_existingRequests.isNotEmpty) ...[
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'Your Restructuring History',
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF0B1B3D),
                            letterSpacing: -0.2,
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 3,
                          ),
                          decoration: BoxDecoration(
                            color: const Color(0xFFEAF2FF),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Text(
                            '${_existingRequests.length} filed',
                            style: const TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                              color: Color(0xFF1E6BFF),
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    ..._existingRequests.map(
                      (req) => _buildRequestHistoryCard(req),
                    ),
                    const SizedBox(height: 24),
                  ],

                  // 4. PLAN SELECTION SECTION (STEP 1)
                  Row(
                    children: [
                      _buildStepBadge('1'),
                      const SizedBox(width: 8),
                      const Text(
                        'Step 1: Select Installment Plan',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF0B1B3D),
                          letterSpacing: -0.2,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Pre-calculated structured milestones for your upcoming rent.',
                    style: TextStyle(
                      fontSize: 12,
                      color: Color(0xFF64748B),
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  const SizedBox(height: 12),

                  // Option A: 2 Payments
                  _buildPlanCard(
                    planKey: '2 payments',
                    title: '2-Part Split Plan (Bi-Weekly)',
                    badge: 'Recommended',
                    badgeColor: const Color(0xFF10B981),
                    installments: [
                      {
                        'label': 'Payment 1 (50%)',
                        'amount': rent / 2,
                        'timing':
                            'Due $dueMonthShort $endOfMonthDay, ${now.year}',
                      },
                      {
                        'label': 'Payment 2 (50%)',
                        'amount': rent / 2,
                        'timing':
                            'Due $nextMonthShort 15, ${nextMonthDate.year}',
                      },
                    ],
                  ),

                  const SizedBox(height: 12),

                  // Option B: 3 Payments
                  _buildPlanCard(
                    planKey: '3 payments',
                    title: '3-Part Split Plan (Decennial)',
                    badge: 'Extended',
                    badgeColor: const Color(0xFF8B5CF6),
                    installments: [
                      {
                        'label': 'Payment 1 (33%)',
                        'amount': rent / 3,
                        'timing':
                            'Due $dueMonthShort $endOfMonthDay, ${now.year}',
                      },
                      {
                        'label': 'Payment 2 (33%)',
                        'amount': rent / 3,
                        'timing': 'Due $p2d2Short ${p2d2.day}, ${p2d2.year}',
                      },
                      {
                        'label': 'Payment 3 (33%)',
                        'amount': rent / 3,
                        'timing': 'Due $p2d3Short ${p2d3.day}, ${p2d3.year}',
                      },
                    ],
                  ),

                  const SizedBox(height: 24),

                  // 5. STRUCTURED REASON SELECTOR (STEP 2)
                  Row(
                    children: [
                      _buildStepBadge('2'),
                      const SizedBox(width: 8),
                      const Text(
                        'Step 2: State Your Reason',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF0B1B3D),
                          letterSpacing: -0.2,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Required for landlord audit & formal record keeping.',
                    style: TextStyle(
                      fontSize: 12,
                      color: Color(0xFF64748B),
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  const SizedBox(height: 12),

                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: _reasons.map((r) {
                      final isSelected = _selectedReason == r;
                      return ChoiceChip(
                        label: Text(r),
                        selected: isSelected,
                        onSelected: (val) {
                          if (val) setState(() => _selectedReason = r);
                        },
                        selectedColor: const Color(0xFF0B1B3D),
                        backgroundColor: Colors.white,
                        labelStyle: TextStyle(
                          fontSize: 12,
                          fontWeight: isSelected
                              ? FontWeight.w800
                              : FontWeight.w600,
                          color: isSelected
                              ? Colors.white
                              : const Color(0xFF334155),
                        ),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                          side: BorderSide(
                            color: isSelected
                                ? const Color(0xFF0B1B3D)
                                : const Color(0xFFE2E8F0),
                            width: 1.2,
                          ),
                        ),
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 8,
                        ),
                      );
                    }).toList(),
                  ),

                  const SizedBox(height: 12),

                  // Optional message / explanation to lessor
                  Container(
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: const Color(0xFFE2E8F0)),
                    ),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 12,
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: const [
                            Icon(
                              Icons.chat_bubble_outline_rounded,
                              size: 14,
                              color: Color(0xFF1E6BFF),
                            ),
                            SizedBox(width: 6),
                            Text(
                              'Other : Personal Note (Optional)',
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w700,
                                color: Color(0xFF0B1B3D),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 6),
                        TextField(
                          controller: _noteCtrl,
                          maxLines: 3,
                          minLines: 2,
                          style: const TextStyle(
                            fontSize: 12.5,
                            color: Color(0xFF0B1B3D),
                          ),
                          decoration: const InputDecoration(
                            hintText: 'Specify other circumstances or write a note to your lessor...',
                            hintStyle: TextStyle(
                              fontSize: 11.5,
                              color: Color(0xFF94A3B8),
                            ),
                            border: InputBorder.none,
                            isDense: true,
                            contentPadding: EdgeInsets.zero,
                          ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 24),

                  // 6. LEGAL & LEASE CLAUSE DISCLAIMER (STEP 3)
                  Row(
                    children: [
                      _buildStepBadge('3'),
                      const SizedBox(width: 8),
                      const Text(
                        'Step 3: Review & Confirm Agreement',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF0B1B3D),
                          letterSpacing: -0.2,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),

                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: const Color(0xFFEFF6FF),
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(
                        color: const Color(0xFF3B82F6).withValues(alpha: 0.25),
                      ),
                    ),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: const [
                        Icon(
                          Icons.info_outline_rounded,
                          size: 18,
                          color: Color(0xFF1E6BFF),
                        ),
                        SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            'Submitting this request creates an official audit log in the Lessor Pwestora Portal. It replaces informal verbal agreements and locks in your structured installment schedule.',
                            style: TextStyle(
                              fontSize: 11.5,
                              fontWeight: FontWeight.w500,
                              color: Color(0xFF1E40AF),
                              height: 1.35,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 20),

                  // 7. SUBMIT BUTTON -> OPENS REVIEW & AGREEMENT CONFIRMATION
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: _submitting
                          ? null
                          : () => _showConfirmationModal(rent),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF0B1B3D),
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(16),
                        ),
                        elevation: 0,
                      ),
                      child: _submitting
                          ? const SizedBox(
                              width: 22,
                              height: 22,
                              child: CircularProgressIndicator(
                                color: Colors.white,
                                strokeWidth: 2.5,
                              ),
                            )
                          : Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: const [
                                Text(
                                  'Review & Confirm Plan',
                                  style: TextStyle(
                                    fontSize: 14.5,
                                    fontWeight: FontWeight.w800,
                                    color: Colors.white,
                                    letterSpacing: 0.2,
                                  ),
                                ),
                                SizedBox(width: 8),
                                Icon(
                                  Icons.arrow_forward_ios_rounded,
                                  size: 13,
                                  color: Colors.white,
                                ),
                              ],
                            ),
                    ),
                  ),

                  const SizedBox(height: 40),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMutualBenefitBar() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0A1832).withValues(alpha: 0.03),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          _buildBenefitItem(
            Icons.verified_rounded,
            'Zero Late Fines',
            const Color(0xFF10B981),
          ),
          Container(width: 1, height: 24, color: const Color(0xFFE2E8F0)),
          _buildBenefitItem(
            Icons.timer_outlined,
            '48h Cooling-off',
            const Color(0xFF1E6BFF),
          ),
          Container(width: 1, height: 24, color: const Color(0xFFE2E8F0)),
          _buildBenefitItem(
            Icons.history_edu_rounded,
            'Audited Trail',
            const Color(0xFF8B5CF6),
          ),
        ],
      ),
    );
  }

  Widget _buildBenefitItem(IconData icon, String text, Color color) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14, color: color),
        const SizedBox(width: 5),
        Text(
          text,
          style: TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w800,
            color: color,
          ),
        ),
      ],
    );
  }



  Widget _buildPlanCard({
    required String planKey,
    required String title,
    required String badge,
    required Color badgeColor,
    required List<Map<String, dynamic>> installments,
  }) {
    final isSelected = _selectedPlan == planKey;

    return InkWell(
      borderRadius: BorderRadius.circular(20),
      onTap: () => setState(() => _selectedPlan = planKey),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: isSelected
                ? const Color(0xFF1E6BFF)
                : const Color(0xFFE2E8F0),
            width: isSelected ? 2 : 1,
          ),
          boxShadow: [
            BoxShadow(
              color: isSelected
                  ? const Color(0xFF1E6BFF).withValues(alpha: 0.08)
                  : const Color(0xFF0A1832).withValues(alpha: 0.03),
              blurRadius: 14,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Plan Header
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    Container(
                      width: 22,
                      height: 22,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: isSelected
                              ? const Color(0xFF1E6BFF)
                              : const Color(0xFFCBD5E1),
                          width: isSelected ? 6 : 2,
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Text(
                      title,
                      style: const TextStyle(
                        fontSize: 13.5,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF0B1B3D),
                      ),
                    ),
                  ],
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 3,
                  ),
                  decoration: BoxDecoration(
                    color: badgeColor.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    badge,
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                      color: badgeColor,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),

            // Installment Breakdown Timeline
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: const Color(0xFFEEF2F6)),
              ),
              child: Column(
                children: installments.map((inst) {
                  final amount = (inst['amount'] as num).toDouble();
                  return Padding(
                    padding: const EdgeInsets.symmetric(vertical: 4),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: [
                            const Icon(
                              Icons.fiber_manual_record,
                              size: 8,
                              color: Color(0xFF1E6BFF),
                            ),
                            const SizedBox(width: 8),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  inst['label'],
                                  style: const TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.w700,
                                    color: Color(0xFF1E293B),
                                  ),
                                ),
                                Text(
                                  inst['timing'],
                                  style: const TextStyle(
                                    fontSize: 10.5,
                                    color: Color(0xFF64748B),
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                        Text(
                          '₱${_formatMoney(amount)}',
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF0B1B3D),
                          ),
                        ),
                      ],
                    ),
                  );
                }).toList(),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildRequestHistoryCard(dynamic req) {
    final status = (req['status'] ?? 'pending').toString().toLowerCase();
    final reason = req['reason'] ?? 'Restructuring';
    final plan = req['plan_label'] ?? '2 payments';
    final amount = (req['amount'] as num?)?.toDouble() ?? 0.0;
    final createdAt = req['created_at']?.toString() ?? '';

    final createdDt = DateTime.tryParse(createdAt);
    final now = DateTime.now();
    final diffHours = createdDt != null ? now.difference(createdDt).inHours : 0;
    final isWithin48h = diffHours < 48;
    final hoursLeft = (48 - diffHours).clamp(1, 48);

    Color statusBg = const Color(0xFFFEF3C7);
    Color statusColor = const Color(0xFFD97706);
    String statusLabel = 'PENDING LESSOR REVIEW';

    if (status == 'approved') {
      statusBg = const Color(0xFFECFDF5);
      statusColor = const Color(0xFF10B981);
      statusLabel = 'APPROVED & ACTIVE';
    } else if (status == 'rejected') {
      statusBg = const Color(0xFFFEE2E2);
      statusColor = const Color(0xFFEF4444);
      statusLabel = 'DECLINED';
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 7,
                        vertical: 2,
                      ),
                      decoration: BoxDecoration(
                        color: statusBg,
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        statusLabel,
                        style: TextStyle(
                          fontSize: 9.5,
                          fontWeight: FontWeight.w900,
                          color: statusColor,
                          letterSpacing: 0.3,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      plan,
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF64748B),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  reason,
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF0B1B3D),
                  ),
                ),
                if (createdAt.isNotEmpty)
                  Text(
                    'Submitted: $createdAt',
                    style: const TextStyle(
                      fontSize: 10.5,
                      color: Color(0xFF94A3B8),
                    ),
                  ),
                if (status == 'pending') ...[
                  const SizedBox(height: 8),
                  if (isWithin48h)
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 7,
                            vertical: 3,
                          ),
                          decoration: BoxDecoration(
                            color: const Color(0xFFEFF6FF),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const Icon(
                                Icons.timer_outlined,
                                size: 11,
                                color: Color(0xFF1E6BFF),
                              ),
                              const SizedBox(width: 4),
                              Text(
                                '${hoursLeft}h left to cancel',
                                style: const TextStyle(
                                  fontSize: 10,
                                  fontWeight: FontWeight.w700,
                                  color: Color(0xFF1E6BFF),
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 8),
                        InkWell(
                          onTap: () => _confirmCancelRequest(req),
                          borderRadius: BorderRadius.circular(8),
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 4,
                            ),
                            decoration: BoxDecoration(
                              color: const Color(0xFFFEF2F2),
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(
                                color: const Color(0xFFFCA5A5),
                                width: 0.8,
                              ),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: const [
                                Icon(
                                  Icons.close_rounded,
                                  size: 12,
                                  color: Color(0xFFDC2626),
                                ),
                                SizedBox(width: 4),
                                Text(
                                  'Cancel Request',
                                  style: TextStyle(
                                    fontSize: 10.5,
                                    fontWeight: FontWeight.w700,
                                    color: Color(0xFFDC2626),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    )
                  else
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF1F5F9),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: const Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            Icons.lock_clock_outlined,
                            size: 11,
                            color: Color(0xFF64748B),
                          ),
                          SizedBox(width: 4),
                          Text(
                            '2-day cancellation window ended • Awaiting lessor review',
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.w600,
                              color: Color(0xFF64748B),
                            ),
                          ),
                        ],
                      ),
                    ),
                ],
              ],
            ),
          ),
          Text(
            '₱${_formatMoney(amount)}',
            style: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w900,
              color: Color(0xFF0B1B3D),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildAgreementPoint(
    IconData icon,
    String title,
    String description,
    Color color,
  ) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 26,
          height: 26,
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.12),
            shape: BoxShape.circle,
          ),
          child: Icon(icon, color: color, size: 15),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: RichText(
            text: TextSpan(
              style: const TextStyle(
                fontSize: 11.5,
                color: Color(0xFF64748B),
                height: 1.35,
              ),
              children: [
                TextSpan(
                  text: '$title ',
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF0B1B3D),
                  ),
                ),
                TextSpan(text: description),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildStepBadge(String number) {
    return Container(
      width: 22,
      height: 22,
      decoration: const BoxDecoration(
        color: Color(0xFF0B1B3D),
        shape: BoxShape.circle,
      ),
      alignment: Alignment.center,
      child: Text(
        number,
        style: const TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w900,
          color: Colors.white,
        ),
      ),
    );
  }

  Widget _buildInstructionsCard() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0A1832).withValues(alpha: 0.03),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(
                  color: const Color(0xFFEFF6FF),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(
                    color: const Color(0xFF1E6BFF).withValues(alpha: 0.2),
                  ),
                ),
                child: const Icon(
                  Icons.lightbulb_outline_rounded,
                  color: Color(0xFF1E6BFF),
                  size: 18,
                ),
              ),
              const SizedBox(width: 10),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'How Payment Restructuring Works',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w900,
                        color: Color(0xFF0B1B3D),
                        letterSpacing: -0.2,
                      ),
                    ),
                    Text(
                      'A formal protocol to manage temporary cash flow delays',
                      style: TextStyle(
                        fontSize: 11,
                        color: Color(0xFF64748B),
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          const Divider(height: 1, color: Color(0xFFF1F5F9)),
          const SizedBox(height: 12),

          _buildInstructionStep(
            stepNumber: '1',
            title: 'Explore Split Milestones',
            description:
                'Select 2-part or 3-part plan to preview exact amounts and due dates with zero commitment.',
            color: const Color(0xFF1E6BFF),
          ),
          const SizedBox(height: 10),
          _buildInstructionStep(
            stepNumber: '2',
            title: 'Review Terms & Agree',
            description:
                'Tap "Review & Confirm Plan". You will inspect the schedule, accept terms, and receive 48h to cancel.',
            color: const Color(0xFF8B5CF6),
          ),
          const SizedBox(height: 10),
          _buildInstructionStep(
            stepNumber: '3',
            title: 'Lessor Approval & Scheduled Payments',
            description:
                'Once confirmed by your lessor, pay each milestone via GCash/Maya/Bank with 0 late penalties.',
            color: const Color(0xFF10B981),
          ),

          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: const Row(
              children: [
                Icon(Icons.shield_outlined, size: 14, color: Color(0xFF10B981)),
                SizedBox(width: 6),
                Expanded(
                  child: Text(
                    '2-Day Protection: You have 48 hours to cancel this request before lessor review.',
                    style: TextStyle(
                      fontSize: 10.5,
                      fontWeight: FontWeight.w600,
                      color: Color(0xFF475569),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInstructionStep({
    required String stepNumber,
    required String title,
    required String description,
    required Color color,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 24,
          height: 24,
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.12),
            shape: BoxShape.circle,
            border: Border.all(
              color: color.withValues(alpha: 0.25),
              width: 1.2,
            ),
          ),
          alignment: Alignment.center,
          child: Text(
            stepNumber,
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w900,
              color: color,
            ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF0B1B3D),
                ),
              ),
              const SizedBox(height: 1),
              Text(
                description,
                style: const TextStyle(
                  fontSize: 11,
                  color: Color(0xFF64748B),
                  fontWeight: FontWeight.w500,
                  height: 1.35,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  // ================= 2. SECURITY DEPOSIT & ADVANCE SHIELD =================
  Widget _buildSecurityDepositShieldCard() {
    final deposit = _securityDeposit;
    return Container(
      margin: const EdgeInsets.only(bottom: 24),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0A1832).withValues(alpha: 0.04),
            blurRadius: 16,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: const Color(0xFFEFF6FF),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: const Color(0xFFBFDBFE)),
                    ),
                    child: const Icon(
                      Icons.shield_outlined,
                      size: 18,
                      color: Color(0xFF005CEE),
                    ),
                  ),
                  const SizedBox(width: 10),
                  const Text(
                    'Security Deposit Shield',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF0A1832),
                      letterSpacing: -0.2,
                    ),
                  ),
                ],
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: const Color(0xFFEFF6FF),
                  borderRadius: BorderRadius.circular(6),
                  border: Border.all(color: const Color(0xFFBFDBFE)),
                ),
                child: const Text(
                  'LOCKED ASSET',
                  style: TextStyle(
                    fontSize: 9.5,
                    fontWeight: FontWeight.w900,
                    color: Color(0xFF005CEE),
                    letterSpacing: 0.4,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          const Text(
            'Current Locked Escrow Balance',
            style: TextStyle(
              fontSize: 11.5,
              fontWeight: FontWeight.w600,
              color: Color(0xFF64748B),
            ),
          ),
          const SizedBox(height: 4),
          Text(
            '₱${_formatMoney(deposit)}',
            style: const TextStyle(
              fontSize: 28,
              fontWeight: FontWeight.w900,
              color: Color(0xFF0A1832),
              letterSpacing: -0.8,
            ),
          ),
          const SizedBox(height: 8),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: const [
                Icon(Icons.info_outline_rounded, size: 16, color: Color(0xFF005CEE)),
                SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Optional advance shield held in escrow. Untouchable during normal operations. Automatically covers unpaid rent when your grace period expires with zero strikes.',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w500,
                      color: Color(0xFF475569),
                      height: 1.4,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: _showTopUpDepositModal,
              icon: const Icon(Icons.add_circle_outline_rounded, size: 16),
              label: const Text(
                'Top Up Advance / Security Deposit',
                style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
              ),
              style: OutlinedButton.styleFrom(
                foregroundColor: const Color(0xFF005CEE),
                backgroundColor: Colors.white,
                side: const BorderSide(color: Color(0xFFBFDBFE), width: 1.2),
                padding: const EdgeInsets.symmetric(vertical: 13),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
            ),
          ),
        ],
      ),
    );
  }

  void _showTopUpDepositModal() {
    final amountController = TextEditingController();
    final refController = TextEditingController();
    bool isSubmitting = false;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setModalState) {
          return Container(
            padding: EdgeInsets.fromLTRB(
              22,
              16,
              22,
              MediaQuery.of(ctx).viewInsets.bottom + 24,
            ),
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
            ),
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Center(
                    child: Container(
                      width: 40,
                      height: 4,
                      decoration: BoxDecoration(
                        color: const Color(0xFFCBD5E1),
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(7),
                        decoration: BoxDecoration(
                          color: const Color(0xFFEFF6FF),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: const Color(0xFFBFDBFE)),
                        ),
                        child: const Icon(Icons.shield_rounded, size: 18, color: Color(0xFF005CEE)),
                      ),
                      const SizedBox(width: 10),
                      const Text(
                        'Top Up Security Deposit / Advance',
                        style: TextStyle(
                          fontSize: 15.5,
                          fontWeight: FontWeight.w900,
                          color: Color(0xFF0A1832),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  Center(
                    child: Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                      ),
                      child: Image.asset(
                        'img/instapay_qr.jpg',
                        width: 160,
                        height: 160,
                        fit: BoxFit.contain,
                      ),
                    ),
                  ),
                  const SizedBox(height: 10),
                  const Center(
                    child: Text(
                      'Pwestora Escrow Account • InstaPay QR',
                      style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700, color: Color(0xFF64748B)),
                    ),
                  ),
                  const SizedBox(height: 16),
                  const Text(
                    'Amount to Deposit (₱)',
                    style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Color(0xFF475569)),
                  ),
                  const SizedBox(height: 6),
                  TextField(
                    controller: amountController,
                    keyboardType: TextInputType.number,
                    style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: Color(0xFF0A1832)),
                    decoration: InputDecoration(
                      prefixText: '₱ ',
                      hintText: '5,000',
                      filled: true,
                      fillColor: const Color(0xFFF8FAFC),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'InstaPay / Bank Reference Number',
                    style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Color(0xFF475569)),
                  ),
                  const SizedBox(height: 6),
                  TextField(
                    controller: refController,
                    style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
                    decoration: InputDecoration(
                      hintText: 'e.g. 1029384756',
                      filled: true,
                      fillColor: const Color(0xFFF8FAFC),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
                      ),
                    ),
                  ),
                  const SizedBox(height: 18),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: isSubmitting
                          ? null
                          : () async {
                              final amtText = amountController.text.trim().replaceAll(',', '');
                              final amt = double.tryParse(amtText) ?? 0.0;
                              final ref = refController.text.trim();
                              if (amt <= 0) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  const SnackBar(content: Text('Please enter a valid amount greater than 0.')),
                                );
                                return;
                              }
                              if (ref.isEmpty) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  const SnackBar(content: Text('Please enter your transaction reference number.')),
                                );
                                return;
                              }
                              setModalState(() => isSubmitting = true);
                              try {
                                await ApiService.submitRentPayment(
                                  amount: amt,
                                  target: 'security_deposit',
                                  referenceNo: ref,
                                  tenantId: _tenantId,
                                );
                                if (!ctx.mounted) return;
                                Navigator.pop(ctx);
                                if (!mounted) return;
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(
                                    content: Text('₱${_formatMoney(amt)} deposit top-up submitted for verification!'),
                                    backgroundColor: const Color(0xFF0A1832),
                                  ),
                                );
                                widget.onArrangementSubmitted?.call();
                              } catch (e) {
                                setModalState(() => isSubmitting = false);
                                if (!mounted) return;
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(content: Text('Submission failed: $e')),
                                );
                              }
                            },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF0A1832),
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                        elevation: 0,
                      ),
                      child: isSubmitting
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                            )
                          : const Text(
                              'Submit Deposit for Verification',
                              style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w800),
                            ),
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
