import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../theme.dart';
import '../services/api_service.dart';
import 'receipts_history_screen.dart';

class RentPaymentScreen extends StatefulWidget {
  final Map<String, dynamic> lease;
  final double rentAmount;
  final Map<String, dynamic>? activeArrangement;
  final VoidCallback? onPaymentSubmitted;

  final bool isOverdue;

  const RentPaymentScreen({
    super.key,
    required this.lease,
    required this.rentAmount,
    this.activeArrangement,
    this.onPaymentSubmitted,
    this.isOverdue = false,
  });

  @override
  State<RentPaymentScreen> createState() => _RentPaymentScreenState();
}

class _RentPaymentScreenState extends State<RentPaymentScreen> {
  final _amountController = TextEditingController();
  final _refNoController = TextEditingController();
  final _noteController = TextEditingController();

  String _selectedTarget = 'monthly_rent';
  XFile? _proofImage;
  bool _submitting = false;
  bool _loading = true;
  List<dynamic> _paymentsHistory = [];

  double get _baseRent => widget.rentAmount > 0
      ? widget.rentAmount
      : ((widget.lease['base_rent'] as num?)?.toDouble() ??
            (widget.lease['rent'] as num?)?.toDouble() ??
            0.0);

  double get _penaltyAmount {
    final p = widget.lease['penalty_amount'];
    if (p is num) return p.toDouble();
    if (p != null) return double.tryParse(p.toString()) ?? 0.0;
    return 0.0;
  }

  List<dynamic> get _penaltyBreakdown {
    final pb = widget.lease['penalty_breakdown'];
    if (pb is List) return pb;
    return [];
  }

  // Exact backend fine (Strike 1 = ₱0, Strike 2 = ₱500, Strike 3 = ₱1,500)
  double get _effectiveLateFee => _penaltyAmount;

  double get _overdueRentAmount =>
      (widget.lease['overdue_rent_amount'] as num?)?.toDouble() ?? 0.0;

  double get _nextCycleRentAmount =>
      (widget.lease['next_cycle_rent_amount'] as num?)?.toDouble() ?? 0.0;

  int get _overdueCyclesCount =>
      (widget.lease['overdue_cycles_count'] as num?)?.toInt() ?? 0;

  double get _activeCycleTarget =>
      (widget.lease['active_cycle_target'] as num?)?.toDouble() ?? _baseRent;

  double get _activeCyclePaid =>
      (widget.lease['active_cycle_paid'] as num?)?.toDouble() ?? 0.0;

  double? get _activeCycleRemaining =>
      (widget.lease['active_cycle_remaining'] as num?)?.toDouble();

  String get _overdueOptionLabel {
    final lbl = (widget.lease['overdue_option_label'] ?? '').toString().trim();
    return lbl.isNotEmpty ? lbl : 'Past-Due Cycle';
  }

  String get _outstandingMonthsLabel {
    final lbl =
        (widget.lease['outstanding_months_label'] ?? '').toString().trim();
    return lbl.isNotEmpty ? lbl : 'Overdue + Current Month';
  }

  bool get _isEffectiveOverdue =>
      widget.isOverdue ||
      widget.lease['is_overdue'] == true ||
      widget.lease['rent_status'] == 'overdue' ||
      _overdueRentAmount > 0.009;

  double get _option1Amount => _overdueRentAmount > 0.009
      ? (_overdueRentAmount + _effectiveLateFee)
      : (_baseRent + _effectiveLateFee);

  double get _option2Amount => (_overdueRentAmount + _nextCycleRentAmount) > 0.009
      ? (_overdueRentAmount + _nextCycleRentAmount + _effectiveLateFee)
      : (_baseRent * 2 + _effectiveLateFee);

  double get _securityDeposit {
    final raw = widget.lease['security_deposit'];
    if (raw is num) return raw.toDouble();
    if (raw != null) return double.tryParse(raw.toString()) ?? 0.0;
    return 0.0;
  }

  bool get _hasRestructuring {
    final status = (widget.activeArrangement?['status'] ?? '')
        .toString()
        .toLowerCase();
    return status == 'approved' || status == 'pending';
  }

  int get _numParts {
    final plan = (widget.activeArrangement?['plan_label'] ?? '').toString();
    return plan.contains('3') ? 3 : 2;
  }

  double get _splitAmount => _baseRent / _numParts;

  double get _totalObligation {
    if (_isEffectiveOverdue) {
      if (_selectedTarget == 'full_balance') {
        final gross = _activeCycleTarget > 0
            ? _activeCycleTarget
            : (_baseRent * 2);
        return gross + _effectiveLateFee;
      }
      final cyclesCount = _overdueCyclesCount > 0 ? _overdueCyclesCount : 1;
      return (_baseRent * cyclesCount) + _effectiveLateFee;
    }
    if (_hasRestructuring) {
      if (_selectedTarget == 'restructuring_plan') {
        return _splitAmount + _penaltyAmount;
      } else if (_selectedTarget == 'both') {
        return (_activeCycleRemaining ?? _baseRent) + _splitAmount + _penaltyAmount;
      }
    }
    final target = _activeCycleTarget > 0 ? _activeCycleTarget : _baseRent;
    return target + _penaltyAmount;
  }

  double get _paidThisMonth {
    if (_isEffectiveOverdue) {
      final paid = _totalObligation - _paymentLeft;
      return paid < 0 ? 0.0 : paid;
    }
    if (!_hasRestructuring && _activeCycleRemaining != null) {
      final paid = _totalObligation - _paymentLeft;
      return paid < 0 ? 0.0 : paid;
    }
    if (_hasRestructuring && _selectedTarget == 'monthly_rent' && _activeCycleRemaining != null) {
      final paid = _totalObligation - _paymentLeft;
      return paid < 0 ? 0.0 : paid;
    }
    final rptm = widget.lease['rent_paid_this_month'];
    if (rptm is num) return rptm.toDouble();
    if (rptm != null) return double.tryParse(rptm.toString()) ?? 0.0;
    return 0.0;
  }

  double get _paymentLeft {
    if (_isEffectiveOverdue) {
      return _selectedTarget == 'full_balance' ? _option2Amount : _option1Amount;
    }
    if (!_hasRestructuring && _activeCycleRemaining != null) {
      final left = _activeCycleRemaining! + _penaltyAmount;
      return left < 0.01 ? 0.0 : left;
    }
    if (_hasRestructuring) {
      if (_selectedTarget == 'monthly_rent' && _activeCycleRemaining != null) {
        final left = _activeCycleRemaining! + _penaltyAmount;
        return left < 0.01 ? 0.0 : left;
      }
      if (_selectedTarget == 'both') {
        return (_activeCycleRemaining ?? _baseRent) + _splitAmount + _penaltyAmount;
      }
      if (_selectedTarget == 'restructuring_plan') {
        final rptm = (widget.lease['rent_paid_this_month'] as num?)?.toDouble() ?? 0.0;
        final left = _totalObligation - rptm;
        return left < 0.01 ? 0.0 : left;
      }
    }
    final left = _totalObligation - _activeCyclePaid;
    return left < 0.01 ? 0.0 : left;
  }

  bool get _isPaymentComplete => _paymentLeft <= 0.01;

  double get _enteredAmount {
    final amtText = _amountController.text.trim().replaceAll(',', '');
    return double.tryParse(amtText) ?? 0.0;
  }

  bool get _isOverpaying =>
      !_isPaymentComplete && _enteredAmount > _paymentLeft && _paymentLeft > 0;

  @override
  void initState() {
    super.initState();
    _amountController.addListener(_onAmountChanged);
    _initInitialTargetAndAmount();
    _fetchPaymentsHistory();
  }

  void _onAmountChanged() {
    if (mounted) setState(() {});
  }

  void _syncAmountToPaymentLeft() {
    if (_isPaymentComplete) {
      _amountController.text = '0';
    } else {
      _amountController.text = _paymentLeft.toStringAsFixed(0);
    }
  }

  void _initInitialTargetAndAmount() {
    if (_isEffectiveOverdue) {
      _selectedTarget = 'overdue_cycle';
    } else if (_hasRestructuring) {
      _selectedTarget = 'restructuring_plan';
    } else {
      _selectedTarget = 'monthly_rent';
    }
    _syncAmountToPaymentLeft();
  }

  void _onTargetChanged(String? val) {
    if (val == null) return;
    setState(() {
      _selectedTarget = val;
      _syncAmountToPaymentLeft();
    });
  }

  int? get _tenantId {
    final tid = widget.lease['tenant_id'] ?? widget.lease['id'];
    return tid is int
        ? tid
        : (tid != null ? int.tryParse(tid.toString()) : null);
  }

  Future<void> _fetchPaymentsHistory() async {
    setState(() => _loading = true);
    try {
      final list = await ApiService.fetchPayments(tenantId: _tenantId);
      if (mounted) {
        setState(() {
          _paymentsHistory = list;
          _loading = false;
          _syncAmountToPaymentLeft();
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _pickProofPhoto() async {
    try {
      final picker = ImagePicker();
      final picked = await picker.pickImage(
        source: ImageSource.gallery,
        maxWidth: 1600,
        imageQuality: 85,
      );
      if (picked != null && mounted) {
        setState(() => _proofImage = picked);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not open image picker: $e')),
        );
      }
    }
  }

  void _downloadOrSaveQr() {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: AppColors.primary,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        content: Row(
          children: const [
            Icon(
              Icons.check_circle_rounded,
              color: AppColors.electricBlue,
              size: 20,
            ),
            SizedBox(width: 10),
            Expanded(
              child: Text(
                'InstaPay QR Code saved to photos / device storage.',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _submitPayment() async {
    if (_isPaymentComplete) return;

    final amtText = _amountController.text.trim().replaceAll(',', '');
    final amt = double.tryParse(amtText) ?? 0.0;

    if (amt <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter a valid payment amount.')),
      );
      return;
    }

    if (amt > _paymentLeft && _paymentLeft > 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          backgroundColor: const Color(0xFFDC2626),
          content: Text(
            'Cannot submit: Amount (₱${_formatMoney(amt)}) exceeds remaining payment left of ₱${_formatMoney(_paymentLeft)}.',
          ),
        ),
      );
      return;
    }

    String ref = _refNoController.text.trim();
    if (ref.isEmpty) {
      if (_proofImage != null) {
        ref = 'PROOF-${DateTime.now().millisecondsSinceEpoch % 1000000}';
      } else {
        ref = 'TRX-${DateTime.now().millisecondsSinceEpoch % 1000000}';
      }
    }

    setState(() => _submitting = true);

    try {
      await ApiService.submitRentPayment(
        amount: amt,
        target: _selectedTarget,
        referenceNo: ref,
        note: _noteController.text.trim().isNotEmpty
            ? _noteController.text.trim()
            : null,
        tenantId: _tenantId,
      );

      if (_isEffectiveOverdue &&
          (_selectedTarget == 'overdue_cycle' ||
              _selectedTarget == 'overdue_october' ||
              _selectedTarget == 'full_balance')) {
        await ApiService.setDemoRentStatus('normal');
      }

      widget.onPaymentSubmitted?.call();
      await _fetchPaymentsHistory();

      if (!mounted) return;

      setState(() {
        _submitting = false;
        _refNoController.clear();
        _proofImage = null;
      });

      _showSuccessDialog(amt);
    } catch (e) {
      if (mounted) {
        setState(() => _submitting = false);
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(ApiService.errorMessage(e))));
      }
    }
  }

  void _showSuccessDialog(double amt) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: isDark ? const Color(0xFF161616) : Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(22),
          side: isDark
              ? const BorderSide(color: Color(0xFF262626))
              : BorderSide.none,
        ),
        contentPadding: const EdgeInsets.fromLTRB(24, 24, 24, 20),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 56,
              height: 56,
              decoration: BoxDecoration(
                color: isDark
                    ? const Color(0xFF1E3A8A).withValues(alpha: 0.3)
                    : const Color(0xFFEFF6FF),
                shape: BoxShape.circle,
                border: Border.all(
                  color: isDark
                      ? const Color(0xFF2563EB)
                      : const Color(0xFFBFDBFE),
                  width: 1.5,
                ),
              ),
              child: const Icon(
                Icons.check_circle_rounded,
                color: AppColors.electricBlue,
                size: 32,
              ),
            ),
            const SizedBox(height: 16),
            Text(
              'Payment Submitted!',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w900,
                color: isDark ? Colors.white : AppColors.midnightNavy,
                letterSpacing: -0.3,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Your payment of ₱${_formatMoney(amt)} has been recorded as Pending. The lessor will verify the transaction and credit your balance.',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 13,
                color: isDark ? const Color(0xFF94A3B8) : AppColors.ink500,
                height: 1.4,
              ),
            ),
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () => Navigator.pop(ctx),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF1E6BFF),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                  elevation: 0,
                ),
                child: const Text(
                  'Back to Payments',
                  style: TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
            ),
          ],
        ),
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

  // Calculate percentage paid / remaining for current obligation
  Map<String, dynamic> _computePaymentProgress() {
    double totalPendingPaid = 0.0;

    for (final p in _paymentsHistory) {
      final amt = (p['amount'] as num?)?.toDouble() ?? 0.0;
      final status = (p['status'] ?? '').toString().toLowerCase();
      final note = (p['note'] ?? '').toString();
      final method = (p['method'] ?? '').toString().toLowerCase();
      final isDeclined =
          status == 'declined' || note.toUpperCase().contains('[DECLINED');
      final isTopUp =
          note.toLowerCase().contains('security deposit top-up') ||
          (method == 'security_deposit' &&
              !note.toUpperCase().contains('[AUTO-DEPOSIT]'));
      if (isDeclined || isTopUp) continue;

      final isPending =
          status == 'pending' ||
          note.toUpperCase().contains('[PENDING]') ||
          note.toLowerCase().contains('pending verification');
      if (isPending) {
        totalPendingPaid += amt;
      }
    }

    if (totalPendingPaid == 0.0) {
      totalPendingPaid =
          (widget.lease['pending_rent_amount'] as num?)?.toDouble() ?? 0.0;
    }

    final totalVerifiedPaid = _paidThisMonth;
    final totalTarget = _totalObligation > 0
        ? _totalObligation
        : (_baseRent > 0 ? _baseRent : 1.0);
    final verifiedPercent = ((totalVerifiedPaid / totalTarget) * 100)
        .clamp(0, 100)
        .toInt();
    final remainingAmount = _paymentLeft.clamp(0.0, totalTarget);
    final remainingPercent = (100 - verifiedPercent).clamp(0, 100);

    return {
      'verifiedPaid': totalVerifiedPaid,
      'pendingPaid': totalPendingPaid,
      'totalTarget': totalTarget,
      'verifiedPercent': verifiedPercent,
      'remainingAmount': remainingAmount,
      'remainingPercent': remainingPercent,
    };
  }

  @override
  void dispose() {
    _amountController.removeListener(_onAmountChanged);
    _amountController.dispose();
    _refNoController.dispose();
    _noteController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final progress = _computePaymentProgress();
    final verifiedPercent = progress['verifiedPercent'] as int;
    final remainingPercent = progress['remainingPercent'] as int;
    final remainingAmount = progress['remainingAmount'] as double;
    final pendingPaid = progress['pendingPaid'] as double;

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF000000) : AppColors.bg,
      appBar: AppBar(
        backgroundColor: isDark ? const Color(0xFF121212) : AppColors.primary,
        elevation: 0,
        leading: const AppBackButton(isDark: true),
        title: const Text(
          'Pay Rent',
          style: TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.w800,
            color: Colors.white,
            letterSpacing: -0.3,
          ),
        ),
        centerTitle: true,
        actions: [
          IconButton(
            icon: const Icon(
              Icons.refresh_rounded,
              color: Colors.white,
              size: 20,
            ),
            tooltip: 'Refresh Status',
            onPressed: _fetchPaymentsHistory,
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _fetchPaymentsHistory,
        color: AppColors.electricBlue,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // ================= 1. PAYMENT PROGRESS CARD =================
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: isDark ? const Color(0xFF141414) : Colors.white,
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(
                    color: isDark ? const Color(0xFF262626) : AppColors.border,
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: isDark
                          ? Colors.black.withValues(alpha: 0.25)
                          : const Color(0xFF0A1832).withValues(alpha: 0.03),
                      blurRadius: 10,
                      offset: const Offset(0, 3),
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'Settlement Progress',
                          style: TextStyle(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w700,
                            color: isDark
                                ? const Color(0xFF94A3B8)
                                : AppColors.ink500,
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 3,
                          ),
                          decoration: BoxDecoration(
                            color: verifiedPercent >= 100
                                ? (isDark
                                      ? const Color(
                                          0xFF064E3B,
                                        ).withValues(alpha: 0.4)
                                      : const Color(0xFFECFDF5))
                                : (isDark
                                      ? const Color(
                                          0xFF1E3A8A,
                                        ).withValues(alpha: 0.3)
                                      : const Color(0xFFEFF6FF)),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            verifiedPercent >= 100
                                ? '100% Fully Settled'
                                : '$verifiedPercent% Paid',
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                              color: verifiedPercent >= 100
                                  ? const Color(0xFF10B981)
                                  : (isDark
                                        ? const Color(0xFF60A5FA)
                                        : AppColors.electricBlue),
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(6),
                      child: LinearProgressIndicator(
                        value: verifiedPercent / 100.0,
                        minHeight: 8,
                        backgroundColor: isDark
                            ? const Color(0xFF262626)
                            : const Color(0xFFE2E8F0),
                        color: verifiedPercent >= 100
                            ? const Color(0xFF10B981)
                            : const Color(0xFF1E6BFF),
                      ),
                    ),
                    const SizedBox(height: 10),
                    Wrap(
                      alignment: WrapAlignment.spaceBetween,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      spacing: 8,
                      runSpacing: 4,
                      children: [
                        Text(
                          'Remaining: ₱${_formatMoney(remainingAmount)} ($remainingPercent%)',
                          style: TextStyle(
                            fontSize: 11.5,
                            fontWeight: FontWeight.w600,
                            color: isDark
                                ? const Color(0xFFCBD5E1)
                                : AppColors.ink700,
                          ),
                        ),
                        if (pendingPaid > 0)
                          Text(
                            '₱${_formatMoney(pendingPaid)} pending verification',
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w600,
                              color: isDark
                                  ? const Color(0xFFFBBF24)
                                  : const Color(0xFFD97706),
                            ),
                          ),
                      ],
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 16),

              // ================= 2. INSTAPAY QR CODE CARD =================
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: isDark ? const Color(0xFF141414) : Colors.white,
                  borderRadius: BorderRadius.circular(22),
                  border: Border.all(
                    color: isDark ? const Color(0xFF262626) : AppColors.border,
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: isDark
                          ? Colors.black.withValues(alpha: 0.25)
                          : const Color(0xFF0A1832).withValues(alpha: 0.04),
                      blurRadius: 14,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: Column(
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 4,
                          ),
                          decoration: BoxDecoration(
                            color: isDark
                                ? const Color(0xFF1E3A8A).withValues(alpha: 0.5)
                                : const Color(0xFF0B1B3D),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: const Text(
                            'InstaPay Direct QR',
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                              color: Colors.white,
                              letterSpacing: 0.3,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),

                    // QR Code Image
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(
                          color: const Color(0xFFE2E8F0),
                          width: 1.2,
                        ),
                      ),
                      child: Image.asset(
                        'img/instapay_qr.jpg',
                        width: 200,
                        height: 200,
                        fit: BoxFit.contain,
                      ),
                    ),

                    const SizedBox(height: 14),
                    Text(
                      'Pwestora Escrow Account',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                        color: isDark ? Colors.white : AppColors.midnightNavy,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Supports GCash, Maya, BDO, BPI, UnionBank & all PH banks',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 11.5,
                        color: isDark
                            ? const Color(0xFF94A3B8)
                            : AppColors.ink500,
                      ),
                    ),
                    const SizedBox(height: 14),

                    // Download / Save QR Button
                    OutlinedButton.icon(
                      onPressed: _downloadOrSaveQr,
                      icon: const Icon(Icons.file_download_outlined, size: 16),
                      label: const FittedBox(
                        fit: BoxFit.scaleDown,
                        child: Text(
                          'Save QR Code to Device',
                          style: TextStyle(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: isDark
                            ? Colors.white
                            : AppColors.primary,
                        side: BorderSide(
                          color: isDark
                              ? const Color(0xFF333333)
                              : const Color(0xFFCBD5E1),
                        ),
                        padding: const EdgeInsets.symmetric(
                          horizontal: 16,
                          vertical: 10,
                        ),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),

              // ================= 3. PAYMENT FORM =================
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: isDark ? const Color(0xFF141414) : Colors.white,
                  borderRadius: BorderRadius.circular(22),
                  border: Border.all(
                    color: isDark ? const Color(0xFF262626) : AppColors.border,
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: isDark
                          ? Colors.black.withValues(alpha: 0.25)
                          : const Color(0xFF0A1832).withValues(alpha: 0.04),
                      blurRadius: 14,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Payment Details',
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                        color: isDark ? Colors.white : AppColors.midnightNavy,
                        letterSpacing: -0.2,
                      ),
                    ),
                    const SizedBox(height: 14),

                    // Itemized Statement Breakdown
                    if (_penaltyAmount > 0 || _isEffectiveOverdue) ...[
                      _buildPaymentBreakdownCard(),
                    ],

                    // Payment Target Options (Overdue Arrears or Restructuring)
                    if (_isEffectiveOverdue) ...[
                      Text(
                        'Select Payment Target (Oldest Debt First)',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: isDark
                              ? const Color(0xFFCBD5E1)
                              : AppColors.ink700,
                        ),
                      ),
                      const SizedBox(height: 8),
                      _buildPaymentOptionCard(
                        value: 'overdue_cycle',
                        title: 'Option 1: Pay Overdue ($_overdueOptionLabel)',
                        amountText: '₱${_formatMoney(_option1Amount)}',
                        badgeText: _effectiveLateFee > 0
                            ? 'Priority Target · Includes Fines'
                            : 'Priority Target · Clears Overdue',
                        badgeColor: isDark
                            ? const Color(0xFF450A0A).withValues(alpha: 0.4)
                            : const Color(0xFFFEF2F2),
                        badgeTextColor: isDark
                            ? const Color(0xFFF87171)
                            : const Color(0xFFDC2626),
                        description: _effectiveLateFee > 0
                            ? 'Settles your unpaid $_overdueOptionLabel arrears and ₱${_formatMoney(_effectiveLateFee)} penalty first to clear overdue status.'
                            : 'Settles your unpaid $_overdueOptionLabel arrears first to clear overdue status before the next cycle.',
                      ),
                      _buildPaymentOptionCard(
                        value: 'full_balance',
                        title:
                            'Option 2: Pay Full Balance ($_outstandingMonthsLabel)',
                        amountText: '₱${_formatMoney(_option2Amount)}',
                        badgeText: 'Full Balance Settlement',
                        badgeColor: isDark
                            ? const Color(0xFF1E3A8A).withValues(alpha: 0.3)
                            : const Color(0xFFEFF6FF),
                        badgeTextColor: isDark
                            ? const Color(0xFF60A5FA)
                            : AppColors.electricBlue,
                        description:
                            'Settles all overdue balance ($_outstandingMonthsLabel)${_effectiveLateFee > 0 ? ' plus ₱${_formatMoney(_effectiveLateFee)} penalties' : ''} together in one payment.',
                      ),
                      const SizedBox(height: 12),
                    ] else if (_hasRestructuring) ...[
                      Text(
                        'Select Payment Target',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: isDark
                              ? const Color(0xFFCBD5E1)
                              : AppColors.ink700,
                        ),
                      ),
                      const SizedBox(height: 8),
                      _buildPaymentOptionCard(
                        value: 'restructuring_plan',
                        title: 'Option 1: Restructuring Plan Installment',
                        amountText: '₱${_formatMoney(_splitAmount)}',
                        badgeText: 'Payment Relief Plan',
                        badgeColor: isDark
                            ? const Color(0xFF1E3A8A).withValues(alpha: 0.3)
                            : const Color(0xFFEFF6FF),
                        badgeTextColor: isDark
                            ? const Color(0xFF60A5FA)
                            : AppColors.electricBlue,
                        description:
                            'Pay the agreed restructuring installment split for this period.',
                      ),
                      _buildPaymentOptionCard(
                        value: 'monthly_rent',
                        title: 'Option 2: Regular Monthly Rent',
                        amountText: '₱${_formatMoney(widget.rentAmount)}',
                        badgeText: 'Standard Rent',
                        badgeColor: isDark
                            ? const Color(0xFF262626)
                            : const Color(0xFFF1F5F9),
                        badgeTextColor: isDark
                            ? const Color(0xFFCBD5E1)
                            : AppColors.ink700,
                        description:
                            'Pay standard monthly rent for the current billing cycle.',
                      ),
                      _buildPaymentOptionCard(
                        value: 'both',
                        title: 'Option 3: Full Settlement (Both)',
                        amountText:
                            '₱${_formatMoney(widget.rentAmount + _splitAmount)}',
                        badgeText: 'Combined Total',
                        badgeColor: isDark
                            ? const Color(0xFF064E3B).withValues(alpha: 0.4)
                            : const Color(0xFFECFDF5),
                        badgeTextColor: isDark
                            ? const Color(0xFF34D399)
                            : const Color(0xFF059669),
                        description:
                            'Clear both monthly rent and restructuring installment together in one transaction.',
                      ),
                      const SizedBox(height: 12),
                    ],
                    if (_securityDeposit > 0 && !_isPaymentComplete) ...[
                      Container(
                        margin: const EdgeInsets.only(bottom: 14),
                        padding: const EdgeInsets.symmetric(
                          horizontal: 14,
                          vertical: 10,
                        ),
                        decoration: BoxDecoration(
                          color: isDark
                              ? const Color(0xFF1E3A8A).withValues(alpha: 0.25)
                              : const Color(0xFFEFF6FF),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(
                            color: isDark
                                ? const Color(0xFF1E3A8A).withValues(alpha: 0.5)
                                : const Color(0xFFBFDBFE),
                            width: 0.8,
                          ),
                        ),
                        child: Row(
                          children: [
                            Icon(
                              Icons.shield_outlined,
                              size: 17,
                              color: isDark
                                  ? const Color(0xFF60A5FA)
                                  : const Color(0xFF005CEE),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                'Backed by ₱${_formatMoney(_securityDeposit)} Deposit in Escrow • Auto-covers if unpaid on grace expiry',
                                style: TextStyle(
                                  fontSize: 11.5,
                                  fontWeight: FontWeight.w700,
                                  color: isDark
                                      ? const Color(0xFF93C5FD)
                                      : const Color(0xFF0A1832),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],

                    // Amount Input
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'Amount to Pay',
                          style: TextStyle(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w800,
                            color: isDark
                                ? Colors.white
                                : AppColors.midnightNavy,
                          ),
                        ),
                        if (_isPaymentComplete)
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 3,
                            ),
                            decoration: BoxDecoration(
                              color: isDark
                                  ? const Color(
                                      0xFF064E3B,
                                    ).withValues(alpha: 0.4)
                                  : const Color(0xFFECFDF5),
                              borderRadius: BorderRadius.circular(6),
                              border: Border.all(
                                color: isDark
                                    ? const Color(0xFF059669)
                                    : const Color(0xFFA7F3D0),
                              ),
                            ),
                            child: const Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(
                                  Icons.check_circle_rounded,
                                  color: Color(0xFF10B981),
                                  size: 12,
                                ),
                                SizedBox(width: 4),
                                Text(
                                  'FULLY SETTLED',
                                  style: TextStyle(
                                    fontSize: 10,
                                    fontWeight: FontWeight.w900,
                                    color: Color(0xFF059669),
                                  ),
                                ),
                              ],
                            ),
                          )
                        else if (_paymentLeft > 0)
                          InkWell(
                            onTap: () {
                              _amountController.text = _paymentLeft
                                  .toStringAsFixed(0);
                              setState(() {});
                            },
                            borderRadius: BorderRadius.circular(6),
                            child: Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 8,
                                vertical: 3,
                              ),
                              decoration: BoxDecoration(
                                color: isDark
                                    ? const Color(
                                        0xFF1E3A8A,
                                      ).withValues(alpha: 0.3)
                                    : const Color(0xFFEFF6FF),
                                borderRadius: BorderRadius.circular(6),
                                border: Border.all(
                                  color: isDark
                                      ? const Color(0xFF2563EB)
                                      : const Color(0xFFBFDBFE),
                                ),
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Text(
                                    'Remaining: ₱${_formatMoney(_paymentLeft)}',
                                    style: TextStyle(
                                      fontSize: 11,
                                      fontWeight: FontWeight.w800,
                                      color: isDark
                                          ? const Color(0xFF60A5FA)
                                          : AppColors.electricBlue,
                                    ),
                                  ),
                                  const SizedBox(width: 4),
                                  Text(
                                    '• Auto-fill',
                                    style: TextStyle(
                                      fontSize: 10,
                                      fontWeight: FontWeight.w700,
                                      color: isDark
                                          ? const Color(0xFF93C5FD)
                                          : const Color(0xFF3B82F6),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    TextField(
                      controller: _amountController,
                      keyboardType: TextInputType.number,
                      enabled: !_isPaymentComplete && !_submitting,
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                        color: _isOverpaying
                            ? const Color(0xFFDC2626)
                            : (isDark ? Colors.white : AppColors.ink900),
                      ),
                      decoration: InputDecoration(
                        prefixText: '₱ ',
                        prefixStyle: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                          color: _isOverpaying
                              ? const Color(0xFFDC2626)
                              : (isDark ? Colors.white : AppColors.ink900),
                        ),
                        filled: true,
                        fillColor: _isPaymentComplete
                            ? (isDark
                                  ? const Color(0xFF1A1A1A)
                                  : const Color(0xFFF1F5F9))
                            : (_isOverpaying
                                  ? (isDark
                                        ? const Color(0xFF450A0A)
                                        : const Color(0xFFFEF2F2))
                                  : (isDark
                                        ? const Color(0xFF1F1F1F)
                                        : const Color(0xFFF8FAFC))),
                        contentPadding: const EdgeInsets.symmetric(
                          horizontal: 14,
                          vertical: 12,
                        ),
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: BorderSide(
                            color: _isOverpaying
                                ? const Color(0xFFEF4444)
                                : (isDark
                                      ? const Color(0xFF333333)
                                      : AppColors.border),
                          ),
                        ),
                        enabledBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: BorderSide(
                            color: _isOverpaying
                                ? const Color(0xFFEF4444)
                                : (isDark
                                      ? const Color(0xFF333333)
                                      : AppColors.border),
                            width: _isOverpaying ? 1.5 : 1.0,
                          ),
                        ),
                        focusedBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: BorderSide(
                            color: _isOverpaying
                                ? const Color(0xFFEF4444)
                                : AppColors.electricBlue,
                            width: 1.8,
                          ),
                        ),
                        disabledBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: BorderSide(
                            color: isDark
                                ? const Color(0xFF262626)
                                : const Color(0xFFE2E8F0),
                          ),
                        ),
                      ),
                    ),

                    if (_isOverpaying) ...[
                      const SizedBox(height: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 8,
                        ),
                        decoration: BoxDecoration(
                          color: isDark
                              ? const Color(0xFF450A0A).withValues(alpha: 0.4)
                              : const Color(0xFFFEF2F2),
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(
                            color: isDark
                                ? const Color(0xFF7F1D1D)
                                : const Color(0xFFFCA5A5),
                          ),
                        ),
                        child: Row(
                          children: [
                            Icon(
                              Icons.error_outline_rounded,
                              color: isDark
                                  ? const Color(0xFFF87171)
                                  : const Color(0xFFDC2626),
                              size: 18,
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                'Warning: Amount exceeds remaining payment left of ₱${_formatMoney(_paymentLeft)}. Please adjust the amount.',
                                style: TextStyle(
                                  fontSize: 11.5,
                                  fontWeight: FontWeight.w700,
                                  color: isDark
                                      ? const Color(0xFFFCA5A5)
                                      : const Color(0xFFB91C1C),
                                ),
                              ),
                            ),
                            TextButton(
                              onPressed: () {
                                _amountController.text = _paymentLeft
                                    .toStringAsFixed(0);
                                setState(() {});
                              },
                              style: TextButton.styleFrom(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 4,
                                ),
                                minimumSize: Size.zero,
                                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                              ),
                              child: Text(
                                'Set to Max',
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w900,
                                  color: isDark
                                      ? const Color(0xFFF87171)
                                      : const Color(0xFFDC2626),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],

                    const SizedBox(height: 18),

                    // Proof of Payment / Upload Receipt (Optional)
                    Text(
                      'Proof of Payment (Photo / Screenshot - Optional)',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: isDark
                            ? const Color(0xFFCBD5E1)
                            : AppColors.ink700,
                      ),
                    ),
                    const SizedBox(height: 6),
                    InkWell(
                      onTap: _pickProofPhoto,
                      borderRadius: BorderRadius.circular(12),
                      child: Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: isDark
                              ? const Color(0xFF1F1F1F)
                              : const Color(0xFFF8FAFC),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(
                            color: _proofImage != null
                                ? AppColors.electricBlue
                                : (isDark
                                      ? const Color(0xFF333333)
                                      : AppColors.border),
                            style: BorderStyle.solid,
                          ),
                        ),
                        child: Row(
                          children: [
                            Container(
                              width: 36,
                              height: 36,
                              decoration: BoxDecoration(
                                color: isDark
                                    ? const Color(0xFF2A2A2A)
                                    : Colors.white,
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(
                                  color: isDark
                                      ? const Color(0xFF3A3A3A)
                                      : const Color(0xFFE2E8F0),
                                ),
                              ),
                              child: const Icon(
                                Icons.add_photo_alternate_outlined,
                                size: 18,
                                color: AppColors.electricBlue,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    _proofImage != null
                                        ? _proofImage!.name
                                        : 'Attach Transfer Receipt (Optional)',
                                    style: TextStyle(
                                      fontSize: 12.5,
                                      fontWeight: FontWeight.w700,
                                      color: _proofImage != null
                                          ? (isDark
                                                ? Colors.white
                                                : AppColors.ink900)
                                          : (isDark
                                                ? const Color(0xFFCBD5E1)
                                                : AppColors.ink700),
                                    ),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                  Text(
                                    _proofImage != null
                                        ? 'Tap to change photo'
                                        : 'JPG, PNG screenshot from bank app (optional)',
                                    style: TextStyle(
                                      fontSize: 11,
                                      color: isDark
                                          ? const Color(0xFF94A3B8)
                                          : AppColors.ink500,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            if (_proofImage != null)
                              IconButton(
                                icon: Icon(
                                  Icons.close_rounded,
                                  size: 16,
                                  color: isDark
                                      ? const Color(0xFF94A3B8)
                                      : AppColors.ink500,
                                ),
                                onPressed: () =>
                                    setState(() => _proofImage = null),
                              ),
                          ],
                        ),
                      ),
                    ),

                    const SizedBox(height: 16),

                    // Reference Number Input
                    Text(
                      'Transaction Reference Number (Optional)',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: isDark
                            ? const Color(0xFFCBD5E1)
                            : AppColors.ink700,
                      ),
                    ),
                    const SizedBox(height: 6),
                    TextField(
                      controller: _refNoController,
                      enabled: !_isPaymentComplete && !_submitting,
                      style: TextStyle(
                        fontSize: 13.5,
                        fontWeight: FontWeight.w600,
                        color: isDark ? Colors.white : AppColors.ink900,
                      ),
                      decoration: InputDecoration(
                        hintText:
                            'e.g. 102938475612 (or leave blank to auto-generate)',
                        hintStyle: TextStyle(
                          fontSize: 12.5,
                          color: isDark
                              ? const Color(0xFF64748B)
                              : AppColors.ink400,
                        ),
                        filled: true,
                        fillColor: _isPaymentComplete
                            ? (isDark
                                  ? const Color(0xFF1A1A1A)
                                  : const Color(0xFFF1F5F9))
                            : (isDark
                                  ? const Color(0xFF1F1F1F)
                                  : const Color(0xFFF8FAFC)),
                        contentPadding: const EdgeInsets.symmetric(
                          horizontal: 14,
                          vertical: 12,
                        ),
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: BorderSide(
                            color: isDark
                                ? const Color(0xFF333333)
                                : AppColors.border,
                          ),
                        ),
                        enabledBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: BorderSide(
                            color: isDark
                                ? const Color(0xFF333333)
                                : AppColors.border,
                          ),
                        ),
                        focusedBorder: const OutlineInputBorder(
                          borderRadius: BorderRadius.all(Radius.circular(12)),
                          borderSide: BorderSide(
                            color: AppColors.electricBlue,
                            width: 1.5,
                          ),
                        ),
                        disabledBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: BorderSide(
                            color: isDark
                                ? const Color(0xFF262626)
                                : const Color(0xFFE2E8F0),
                          ),
                        ),
                      ),
                    ),

                    const SizedBox(height: 22),

                    // Submit Button
                    SizedBox(
                      width: double.infinity,
                      child: _isPaymentComplete
                          ? ElevatedButton(
                              onPressed: null,
                              style: ElevatedButton.styleFrom(
                                backgroundColor: const Color(0xFF10B981),
                                disabledBackgroundColor: const Color(
                                  0xFF10B981,
                                ),
                                foregroundColor: Colors.white,
                                disabledForegroundColor: Colors.white,
                                padding: const EdgeInsets.symmetric(
                                  vertical: 14,
                                ),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(14),
                                ),
                                elevation: 0,
                              ),
                              child: const Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(
                                    Icons.check_circle_rounded,
                                    color: Colors.white,
                                    size: 20,
                                  ),
                                  SizedBox(width: 8),
                                  Text(
                                    'Payment Complete',
                                    style: TextStyle(
                                      fontSize: 14,
                                      fontWeight: FontWeight.w800,
                                      letterSpacing: 0.2,
                                    ),
                                  ),
                                ],
                              ),
                            )
                          : ElevatedButton(
                              onPressed: (_submitting || _isOverpaying)
                                  ? null
                                  : _submitPayment,
                              style: ElevatedButton.styleFrom(
                                backgroundColor: const Color(0xFF1E6BFF),
                                foregroundColor: Colors.white,
                                disabledBackgroundColor: isDark
                                    ? const Color(0xFF2A2A2A)
                                    : const Color(0xFFCBD5E1),
                                disabledForegroundColor: isDark
                                    ? const Color(0xFF64748B)
                                    : Colors.white70,
                                padding: const EdgeInsets.symmetric(
                                  vertical: 14,
                                ),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(14),
                                ),
                                elevation: 0,
                              ),
                              child: _submitting
                                  ? const SizedBox(
                                      width: 20,
                                      height: 20,
                                      child: CircularProgressIndicator(
                                        color: Colors.white,
                                        strokeWidth: 2,
                                      ),
                                    )
                                  : const Text(
                                      'Submit Payment for Verification',
                                      style: TextStyle(
                                        fontSize: 14,
                                        fontWeight: FontWeight.w800,
                                        letterSpacing: 0.2,
                                      ),
                                    ),
                            ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              // ================= 4. RECENT SUBMISSIONS HISTORY =================
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Payment History & Audit',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: isDark ? Colors.white : AppColors.midnightNavy,
                    ),
                  ),
                  InkWell(
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => ReceiptsHistoryScreen(
                            lease: widget.lease,
                            rentAmount: widget.rentAmount,
                          ),
                        ),
                      );
                    },
                    borderRadius: BorderRadius.circular(8),
                    child: const Padding(
                      padding: EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                      child: Row(
                        children: [
                          Text(
                            'View Receipts',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                              color: Color(0xFF3B82F6),
                            ),
                          ),
                          SizedBox(width: 2),
                          Icon(
                            Icons.chevron_right_rounded,
                            size: 16,
                            color: Color(0xFF3B82F6),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),

              if (_loading)
                const Center(
                  child: Padding(
                    padding: EdgeInsets.all(20),
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: AppColors.electricBlue,
                    ),
                  ),
                )
              else if (_paymentsHistory.isEmpty)
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: isDark ? const Color(0xFF141414) : Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(
                      color: isDark
                          ? const Color(0xFF262626)
                          : AppColors.border,
                    ),
                  ),
                  child: Center(
                    child: Text(
                      'No payments recorded yet. Submit your transfer above.',
                      style: TextStyle(
                        fontSize: 12.5,
                        color: isDark
                            ? const Color(0xFF94A3B8)
                            : AppColors.ink500,
                      ),
                    ),
                  ),
                )
              else
                ..._paymentsHistory.map((p) {
                  final status = (p['status'] ?? 'approved')
                      .toString()
                      .toLowerCase();
                  final isPending = status == 'pending';
                  final isDeclined = status == 'declined';
                  final amt = (p['amount'] as num?)?.toDouble() ?? 0.0;
                  final paidAt = p['paid_at']?.toString() ?? '';
                  final note = p['note']?.toString() ?? '';

                  return InkWell(
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => ReceiptsHistoryScreen(
                            lease: widget.lease,
                            rentAmount: widget.rentAmount,
                          ),
                        ),
                      );
                    },
                    borderRadius: BorderRadius.circular(16),
                    child: Container(
                      margin: const EdgeInsets.only(bottom: 8),
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: isDark ? const Color(0xFF141414) : Colors.white,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(
                          color: isDark
                              ? const Color(0xFF262626)
                              : AppColors.border,
                        ),
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
                                        color: isDeclined
                                            ? (isDark
                                                  ? const Color(0xFF450A0A)
                                                        .withValues(alpha: 0.45)
                                                  : const Color(0xFFFEE2E2))
                                            : isPending
                                            ? (isDark
                                                  ? const Color(
                                                      0xFF78350F,
                                                    ).withValues(alpha: 0.4)
                                                  : const Color(0xFFFEF3C7))
                                            : (isDark
                                                  ? const Color(
                                                      0xFF064E3B,
                                                    ).withValues(alpha: 0.4)
                                                  : const Color(0xFFECFDF5)),
                                        borderRadius: BorderRadius.circular(6),
                                      ),
                                      child: Text(
                                        isDeclined
                                            ? 'DECLINED'
                                            : isPending
                                            ? 'PENDING VERIFICATION'
                                            : 'VERIFIED & CREDITED',
                                        style: TextStyle(
                                          fontSize: 9.5,
                                          fontWeight: FontWeight.w900,
                                          color: isDeclined
                                              ? (isDark
                                                    ? const Color(0xFFF87171)
                                                    : const Color(0xFFDC2626))
                                              : isPending
                                              ? (isDark
                                                    ? const Color(0xFFFBBF24)
                                                    : const Color(0xFFD97706))
                                              : (isDark
                                                    ? const Color(0xFF34D399)
                                                    : const Color(0xFF10B981)),
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  note.isNotEmpty
                                      ? note
                                      : 'Rent Payment (Bank Transfer)',
                                  style: TextStyle(
                                    fontSize: 12.5,
                                    fontWeight: FontWeight.w700,
                                    color: isDark
                                        ? Colors.white
                                        : AppColors.ink900,
                                  ),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                                if (paidAt.isNotEmpty)
                                  Text(
                                    'Date: $paidAt',
                                    style: TextStyle(
                                      fontSize: 11,
                                      color: isDark
                                          ? const Color(0xFF94A3B8)
                                          : AppColors.ink400,
                                    ),
                                  ),
                              ],
                            ),
                          ),
                          Text(
                            '₱${_formatMoney(amt)}',
                            style: TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w900,
                              color: isDark
                                  ? Colors.white
                                  : AppColors.midnightNavy,
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                }),

              const SizedBox(height: 30),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildPaymentOptionCard({
    required String value,
    required String title,
    required String amountText,
    required String description,
    String? badgeText,
    Color? badgeColor,
    Color? badgeTextColor,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final isSelected = _selectedTarget == value;
    return InkWell(
      onTap: () => _onTargetChanged(value),
      borderRadius: BorderRadius.circular(14),
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: isSelected
              ? (isDark
                    ? const Color(0xFF1E3A8A).withValues(alpha: 0.25)
                    : const Color(0xFFEFF6FF))
              : (isDark ? const Color(0xFF1A1A1A) : const Color(0xFFF8FAFC)),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: isSelected
                ? AppColors.electricBlue
                : (isDark ? const Color(0xFF2C2C2C) : const Color(0xFFE2E8F0)),
            width: isSelected ? 1.8 : 1.0,
          ),
          boxShadow: isSelected
              ? [
                  BoxShadow(
                    color: AppColors.electricBlue.withValues(
                      alpha: isDark ? 0.2 : 0.08,
                    ),
                    blurRadius: 8,
                    offset: const Offset(0, 2),
                  ),
                ]
              : null,
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.only(top: 2, right: 12),
              child: Icon(
                isSelected
                    ? Icons.radio_button_checked_rounded
                    : Icons.radio_button_off_rounded,
                size: 20,
                color: isSelected
                    ? AppColors.electricBlue
                    : (isDark ? const Color(0xFF64748B) : AppColors.ink400),
              ),
            ),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Text(
                          title,
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w800,
                            color: isSelected
                                ? (isDark
                                      ? Colors.white
                                      : AppColors.midnightNavy)
                                : (isDark ? Colors.white : AppColors.ink900),
                            height: 1.25,
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        amountText,
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w900,
                          color: isSelected
                              ? (isDark
                                    ? const Color(0xFF60A5FA)
                                    : AppColors.electricBlue)
                              : (isDark
                                    ? Colors.white
                                    : AppColors.midnightNavy),
                        ),
                      ),
                    ],
                  ),
                  if (badgeText != null) ...[
                    const SizedBox(height: 5),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 7,
                        vertical: 2,
                      ),
                      decoration: BoxDecoration(
                        color:
                            badgeColor ??
                            (isDark
                                ? const Color(0xFF1E3A8A).withValues(alpha: 0.3)
                                : const Color(0xFFEFF6FF)),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        badgeText,
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                          color:
                              badgeTextColor ??
                              (isDark
                                  ? const Color(0xFF60A5FA)
                                  : AppColors.electricBlue),
                        ),
                      ),
                    ),
                  ],
                  if (description.isNotEmpty) ...[
                    const SizedBox(height: 6),
                    Text(
                      description,
                      style: TextStyle(
                        fontSize: 11.5,
                        fontWeight: FontWeight.w500,
                        color: isSelected
                            ? (isDark
                                  ? const Color(0xFFCBD5E1)
                                  : const Color(0xFF334155))
                            : (isDark
                                  ? const Color(0xFF94A3B8)
                                  : AppColors.ink500),
                        height: 1.35,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPaymentBreakdownCard() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bool includeNextCycle =
        _isEffectiveOverdue && _selectedTarget == 'full_balance';
    final double rentPortion = _isEffectiveOverdue
        ? (includeNextCycle ? (_overdueRentAmount + _nextCycleRentAmount) : _overdueRentAmount)
        : (_activeCycleRemaining ?? _baseRent);
    final double displayRent = rentPortion > 0 ? rentPortion : _baseRent;
    final double total = _paymentLeft;
    final int strikeLevel =
        (widget.lease['strike_level'] as num?)?.toInt() ?? 0;

    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1A1A1A) : const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: isDark ? const Color(0xFF2C2C2C) : const Color(0xFFE2E8F0),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Itemized Payment Statement',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                    color: isDark ? Colors.white : AppColors.midnightNavy,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              if (_penaltyAmount > 0 || strikeLevel > 0)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: _penaltyAmount > 0
                        ? (isDark
                              ? const Color(0xFF78350F).withValues(alpha: 0.4)
                              : const Color(0xFFFEF3C7))
                        : (isDark
                              ? const Color(0xFF1E3A8A).withValues(alpha: 0.35)
                              : const Color(0xFFEFF6FF)),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    _penaltyAmount > 0
                        ? 'Includes Penalty'
                        : 'Strike $strikeLevel (₱0 Fine)',
                    style: TextStyle(
                      fontSize: 10.5,
                      fontWeight: FontWeight.w800,
                      color: _penaltyAmount > 0
                          ? (isDark
                                ? const Color(0xFFFBBF24)
                                : const Color(0xFFD97706))
                          : (isDark
                                ? const Color(0xFF93C5FD)
                                : AppColors.electricBlue),
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 12),
          if (_isEffectiveOverdue && _overdueRentAmount > 0) ...[
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Overdue Arrears ($_overdueOptionLabel)',
                    style: TextStyle(
                      fontSize: 12.5,
                      color: isDark ? const Color(0xFFCBD5E1) : AppColors.ink700,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  '₱${_formatMoney(_overdueRentAmount)}',
                  style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                    color: isDark ? Colors.white : AppColors.ink900,
                  ),
                ),
              ],
            ),
            if (includeNextCycle && _nextCycleRentAmount > 0) ...[
              const SizedBox(height: 6),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      'Next Billing Cycle Rent',
                      style: TextStyle(
                        fontSize: 12.5,
                        color: isDark ? const Color(0xFFCBD5E1) : AppColors.ink700,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    '₱${_formatMoney(_nextCycleRentAmount)}',
                    style: TextStyle(
                      fontSize: 12.5,
                      fontWeight: FontWeight.w700,
                      color: isDark ? Colors.white : AppColors.ink900,
                    ),
                  ),
                ],
              ),
            ],
          ] else
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Remaining Rent Balance',
                    style: TextStyle(
                      fontSize: 12.5,
                      color: isDark ? const Color(0xFFCBD5E1) : AppColors.ink700,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  '₱${_formatMoney(displayRent)}',
                  style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                    color: isDark ? Colors.white : AppColors.ink900,
                  ),
                ),
              ],
            ),
          const SizedBox(height: 8),
          if (_penaltyBreakdown.isNotEmpty)
            ..._penaltyBreakdown.map((pb) {
              final label = pb['label'] ?? 'Violation Penalty';
              final amt = (pb['amount'] as num?)?.toDouble() ?? 0.0;
              return Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        '⚠️ $label',
                        style: TextStyle(
                          fontSize: 12,
                          color: isDark
                              ? const Color(0xFFF87171)
                              : const Color(0xFFDC2626),
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      '+₱${_formatMoney(amt)}',
                      style: TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w800,
                        color: isDark
                            ? const Color(0xFFF87171)
                            : const Color(0xFFDC2626),
                      ),
                    ),
                  ],
                ),
              );
            })
          else if (_penaltyAmount > 0)
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      '⚠️ Strike ${strikeLevel > 0 ? strikeLevel : 2} Infraction Penalty',
                      style: TextStyle(
                        fontSize: 12,
                        color: isDark
                            ? const Color(0xFFF87171)
                            : const Color(0xFFDC2626),
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    '+₱${_formatMoney(_penaltyAmount)}',
                    style: TextStyle(
                      fontSize: 12.5,
                      fontWeight: FontWeight.w800,
                      color: isDark
                          ? const Color(0xFFF87171)
                          : const Color(0xFFDC2626),
                    ),
                  ),
                ],
              ),
            )
          else if (_isEffectiveOverdue)
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      'Strike 1 Overdue Warning (No monetary fine)',
                      style: TextStyle(
                        fontSize: 12,
                        color: isDark
                            ? const Color(0xFF93C5FD)
                            : AppColors.electricBlue,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    '₱0',
                    style: TextStyle(
                      fontSize: 12.5,
                      fontWeight: FontWeight.w800,
                      color: isDark
                          ? const Color(0xFF93C5FD)
                          : AppColors.electricBlue,
                    ),
                  ),
                ],
              ),
            ),
          Divider(
            height: 18,
            color: isDark ? const Color(0xFF2C2C2C) : const Color(0xFFE2E8F0),
          ),
          Row(
            children: [
              Expanded(
                child: Text(
                  'Total Payable Balance',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                    color: isDark ? Colors.white : AppColors.midnightNavy,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Text(
                '₱${_formatMoney(total)}',
                style: const TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w900,
                  color: Color(0xFF3B82F6),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
