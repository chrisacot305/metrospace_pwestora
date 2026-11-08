import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../theme.dart';
import '../services/api_service.dart';

class LeaseContractScreen extends StatefulWidget {
  final Map<String, dynamic>? lease;
  final Map<String, dynamic>? applicationData;
  final bool forceInitialLock;
  final Future<void> Function()? onLeaseMayHaveChanged;

  const LeaseContractScreen({
    super.key,
    this.lease,
    this.applicationData,
    this.forceInitialLock = false,
    this.onLeaseMayHaveChanged,
  });

  @override
  State<LeaseContractScreen> createState() => _LeaseContractScreenState();
}

class _LeaseContractScreenState extends State<LeaseContractScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  bool _loading = true;
  Map<String, dynamic>? _contract;
  bool _isPaymentComplete = false;
  bool _isSimulatingPayment = false;
  bool _isSubmittingAcceptance = false;

  int? get _tenantId {
    final tid =
        _contract?['tenant_id'] ??
        widget.lease?['tenant_id'] ??
        widget.lease?['id'];
    if (tid is int) return tid;
    if (tid != null) return int.tryParse(tid.toString());
    return null;
  }

  int get _contractVersion {
    final v = _contract?['version'];
    if (v is int) return v;
    if (v != null) return int.tryParse(v.toString()) ?? 1;
    return 1;
  }

  String get _contractStatus =>
      (_contract?['status'] ?? 'standard').toString().toLowerCase();

  bool get _canAccept =>
      _contract?['can_accept'] == true || (_contractStatus == 'finalized');

  bool get _isRevisionPending => _contract?['revision_pending'] == true;

  String? get _specialProvisions =>
      _contract?['special_provisions']?.toString();

  String? get _finalizedAt => _contract?['finalized_at']?.toString();

  String? get _acceptedAt => _contract?['accepted_at']?.toString();

  String? get _rejectedAt => _contract?['rejected_at']?.toString();

  String? get _rejectionReason => _contract?['rejection_reason']?.toString();

  bool get _isRejected =>
      _contractStatus == 'rejected' ||
      (_rejectedAt != null && _rejectedAt!.isNotEmpty);

  bool get _isAccepted =>
      _contractStatus == 'accepted' ||
      (_acceptedAt != null && _acceptedAt!.isNotEmpty);

  String _formatDateShort(String? raw) {
    if (raw == null || raw.isEmpty) return '';
    try {
      final dt = DateTime.parse(raw);
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
      return '${months[dt.month - 1]} ${dt.day}, ${dt.year}';
    } catch (_) {
      return raw.split('T').first.split(' ').first;
    }
  }

  // Standard Clauses with default descriptions
  final List<Map<String, dynamic>> _defaultClauses = [
    {
      'key': 'security_deposit',
      'label': 'Security Deposit',
      'standard':
          "A security deposit equivalent to two (2) months' rent will be collected and held. Refundable within 30 days of lease-end less deductions for damages.",
      'agreed': true,
      'note': '',
    },
    {
      'key': 'rent_escalation',
      'label': 'Rent Escalation Rate',
      'standard':
          "Monthly rent may increase by up to five percent (5%) upon each annual renewal, consistent with Philippine commercial leasing standard.",
      'agreed': true,
      'note': '',
    },
    {
      'key': 'rent_grace_period',
      'label': '7-Day Grace Period & Default Rules',
      'standard':
          "Monthly rent is due on the scheduled due date. A seven (7) calendar-day grace period is granted with zero late penalty. Payments unpaid after Day 7 incur a ₱500 late fee and initiate Strike 1 payment default escalation.",
      'agreed': true,
      'note': '',
    },
    {
      'key': 'rent_restructuring_policy',
      'label': 'Rent Restructuring Policy',
      'standard':
          "Tenants facing temporary cash-flow difficulty may request to split monthly rent into 2 or 3 installments subject to lessor approval. Late fees are frozen provided agreed installment deadlines are strictly settled.",
      'agreed': true,
      'note': '',
    },
    {
      'key': 'violations_discipline_policy',
      'label': 'Violations & 3-Strike Disciplinary Policy',
      'standard':
          "Unpaid rent past grace period, broken restructuring plans, or unauthorized space alterations trigger a 3-strike escalation (Strike 1: Warning, Strike 2: Notice & Fine, Strike 3: Lease Termination and Eviction).",
      'agreed': true,
      'note': '',
    },
    {
      'key': 'lease_term',
      'label': 'Lease Term Duration',
      'standard':
          "Standard commercial occupancy period as agreed upon in the application proposal with mutual option to renew 60 days before expiration.",
      'agreed': true,
      'note': '',
    },
    {
      'key': 'use_of_premises',
      'label': 'Permitted Use of Premises',
      'standard':
          "The leased commercial space will be utilized solely for the registered business concept stated in the approved lease proposal.",
      'agreed': true,
      'note': '',
    },
    {
      'key': 'maintenance_responsibility',
      'label': 'Maintenance & Structural Upkeep',
      'standard':
          "Lessor maintains outer structural integrity, roof, and central utilities. Lessee handles internal fit-outs, day-to-day fixtures, and interior repairs.",
      'agreed': true,
      'note': '',
    },
    {
      'key': 'no_sublease',
      'label': 'Assignment & Subleasing Policy',
      'standard':
          "The tenant shall not sublease, assign, transfer, or encumber the leasehold without prior express written authorization from the lessor.",
      'agreed': true,
      'note': '',
    },
    {
      'key': 'termination_notice',
      'label': 'Termination & Notice Period',
      'standard':
          "At least thirty (30) days formal written advance notice is required prior to vacating premises or early lease determination.",
      'agreed': true,
      'note': '',
    },
    {
      'key': 'compliance',
      'label': 'Legal Compliance & Business Permits',
      'standard':
          "The tenant agrees to maintain all necessary local LGU permits, BIR registration, and fire safety compliance certificates throughout occupancy.",
      'agreed': true,
      'note': '',
    },
  ];

  List<Map<String, dynamic>> _getEffectiveClauses() {
    dynamic rawNegotiation =
        widget.applicationData?['checklist_negotiation'] ??
        _contract?['checklist_negotiation'];

    Map<String, dynamic> negMap = {};
    if (rawNegotiation is Map) {
      negMap = Map<String, dynamic>.from(rawNegotiation);
    } else if (rawNegotiation is String && rawNegotiation.isNotEmpty) {
      try {
        final decoded = jsonDecode(rawNegotiation);
        if (decoded is Map) {
          negMap = Map<String, dynamic>.from(decoded);
        }
      } catch (_) {}
    }

    final rawContractClauses = _contract?['clauses'];
    if (rawContractClauses is List && rawContractClauses.isNotEmpty) {
      final List<Map<String, dynamic>> result = [];
      for (final item in rawContractClauses) {
        if (item is Map) {
          final key = (item['key'] ?? '').toString();
          final origin = (item['origin'] ?? 'template').toString();
          final label = (item['label'] ?? '').toString();
          final text = (item['text'] ?? item['standard'] ?? '').toString();

          dynamic clauseData;
          if (negMap.containsKey(key)) {
            clauseData = negMap[key];
          } else if (key == 'maintenance_responsibility' &&
              negMap.containsKey('maintenance')) {
            clauseData = negMap['maintenance'];
          } else if (key == 'no_sublease' && negMap.containsKey('subleasing')) {
            clauseData = negMap['subleasing'];
          } else if (key == 'compliance' &&
              (negMap.containsKey('insurance_compliance') ||
                  negMap.containsKey('insurance'))) {
            clauseData = negMap['insurance_compliance'] ?? negMap['insurance'];
          }

          String note = '';
          if (clauseData is Map) {
            note = (clauseData['rebuttal_note'] ?? clauseData['note'] ?? '')
                .toString();
          }

          result.add({
            'key': key,
            'label': label,
            'standard': text,
            'agreed': true,
            'note': note,
            'origin': origin,
            'legal_basis': (item['legal_basis'] ?? '').toString(),
          });
        }
      }
      return result;
    }

    final List<Map<String, dynamic>> result = [];
    for (final base in _defaultClauses) {
      final key = base['key'] as String;

      // Check multiple possible key aliases
      dynamic clauseData;
      if (negMap.containsKey(key)) {
        clauseData = negMap[key];
      } else if (key == 'maintenance_responsibility' &&
          negMap.containsKey('maintenance')) {
        clauseData = negMap['maintenance'];
      } else if (key == 'no_sublease' && negMap.containsKey('subleasing')) {
        clauseData = negMap['subleasing'];
      } else if (key == 'compliance' &&
          negMap.containsKey('insurance_compliance')) {
        clauseData = negMap['insurance_compliance'];
      } else if (key == 'compliance' && negMap.containsKey('insurance')) {
        clauseData = negMap['insurance'];
      }

      bool isAgreed = true;
      String note = '';

      if (clauseData is Map) {
        if (clauseData.containsKey('agreed')) {
          isAgreed = clauseData['agreed'] == true;
        }
        note = (clauseData['rebuttal_note'] ?? clauseData['note'] ?? '')
            .toString();
      } else if (clauseData is bool) {
        isAgreed = clauseData;
      } else if (negMap.isEmpty) {
        isAgreed = base['agreed'] == true;
        note = (base['note'] ?? '').toString();
      }

      result.add({
        'key': key,
        'label': base['label'],
        'standard': base['standard'],
        'agreed': isAgreed,
        'note': note,
      });
    }

    return result;
  }

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _loadContract();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadContract() async {
    setState(() {
      _loading = true;
    });

    final tid = widget.lease?['tenant_id'] ?? widget.lease?['id'];
    final int? tenantId = tid is int
        ? tid
        : (tid != null ? int.tryParse(tid.toString()) : null);

    try {
      final res = await ApiService.fetchContractView(tenantId: tenantId);
      if (!mounted) return;

      final hasLease = res['has_lease'] == true;
      final contractData = res['contract'] as Map<String, dynamic>?;

      setState(() {
        _contract = contractData;
        _isPaymentComplete = hasLease && !widget.forceInitialLock;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _isPaymentComplete = !widget.forceInitialLock;
        _loading = false;
      });
    }
  }

  void _simulateCompletePayment() {
    HapticFeedback.heavyImpact();
    setState(() => _isSimulatingPayment = true);
    Future.delayed(const Duration(milliseconds: 1200), () async {
      if (!mounted) return;
      setState(() {
        _isSimulatingPayment = false;
        _isPaymentComplete = true;
      });
      if (widget.onLeaseMayHaveChanged != null) {
        await widget.onLeaseMayHaveChanged!();
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Initial payment verified! Legal contract confidentiality lock unlocked.',
          ),
          backgroundColor: AppColors.success,
          behavior: SnackBarBehavior.floating,
        ),
      );
    });
  }

  Future<void> _handleAcceptContract(double rent, int term) async {
    final tid = _tenantId;
    if (tid == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Unable to locate lease record.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        final isDark = Theme.of(ctx).brightness == Brightness.dark;
        return AlertDialog(
          backgroundColor: isDark ? const Color(0xFF1E293B) : Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          title: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: AppColors.electricBlue.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(
                  Icons.verified_user_rounded,
                  color: AppColors.electricBlue,
                  size: 22,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Accept Agreement',
                  style: TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                    color: isDark ? Colors.white : AppColors.ink900,
                  ),
                ),
              ),
            ],
          ),
          content: Text(
            'By accepting, you confirm that you have read and agreed to all clauses, terms, and conditions of this Version $_contractVersion Lease Contract for ₱${_formatMoney(rent)}/month ($term months).\n\nYour digital acceptance will be recorded and shared with the lessor.',
            style: TextStyle(
              fontSize: 13,
              height: 1.45,
              color: isDark ? const Color(0xFFCBD5E1) : AppColors.ink700,
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(ctx).pop(false),
              child: Text(
                'Cancel',
                style: TextStyle(
                  color: isDark ? const Color(0xFF94A3B8) : AppColors.ink500,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            ElevatedButton(
              onPressed: () => Navigator.of(ctx).pop(true),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.electricBlue,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 10,
                ),
                elevation: 0,
              ),
              child: const Text(
                'Confirm & Accept',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
          ],
        );
      },
    );

    if (confirmed != true || !mounted) return;

    setState(() => _isSubmittingAcceptance = true);
    HapticFeedback.mediumImpact();

    try {
      final res = await ApiService.acceptContract(
        tenantId: tid,
        version: _contractVersion,
      );

      if (!mounted) return;

      if (res['ok'] == true) {
        setState(() {
          if (_contract != null) {
            _contract!['can_accept'] = false;
            _contract!['status'] = 'accepted';
            _contract!['accepted_at'] =
                res['accepted_at'] ?? DateTime.now().toIso8601String();
          }
          _isSubmittingAcceptance = false;
        });

        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Lease Agreement accepted successfully!'),
            backgroundColor: AppColors.success,
            behavior: SnackBarBehavior.floating,
          ),
        );

        if (widget.onLeaseMayHaveChanged != null) {
          await widget.onLeaseMayHaveChanged!();
        }
      } else {
        setState(() => _isSubmittingAcceptance = false);
        final msg = res['error']?.toString() ?? 'Failed to accept contract.';
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(msg),
            backgroundColor: AppColors.error,
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _isSubmittingAcceptance = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(ApiService.errorMessage(e)),
          backgroundColor: AppColors.error,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  Future<void> _handleRejectContract() async {
    final tid = _tenantId;
    if (tid == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Unable to locate lease record.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    final reasonCtrl = TextEditingController();
    final formKey = GlobalKey<FormState>();

    final reason = await showDialog<String>(
      context: context,
      builder: (ctx) {
        final isDark = Theme.of(ctx).brightness == Brightness.dark;
        return AlertDialog(
          backgroundColor: isDark ? const Color(0xFF1E293B) : Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          title: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: AppColors.error.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(
                  Icons.assignment_return_rounded,
                  color: AppColors.error,
                  size: 22,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Request Revisions',
                  style: TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                    color: isDark ? Colors.white : AppColors.ink900,
                  ),
                ),
              ),
            ],
          ),
          content: Form(
            key: formKey,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Explain what terms or clauses you disagree with or would like the lessor to modify before you accept:',
                  style: TextStyle(
                    fontSize: 13,
                    height: 1.4,
                    color: isDark ? const Color(0xFFCBD5E1) : AppColors.ink700,
                  ),
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: reasonCtrl,
                  maxLines: 3,
                  autofocus: true,
                  style: TextStyle(
                    fontSize: 13,
                    color: isDark ? Colors.white : AppColors.ink900,
                  ),
                  decoration: InputDecoration(
                    hintText: 'Write you reason...',
                    hintStyle: TextStyle(
                      fontSize: 12,
                      color: isDark
                          ? const Color(0xFF64748B)
                          : AppColors.ink400,
                    ),
                    filled: true,
                    fillColor: isDark
                        ? const Color(0xFF0F172A)
                        : const Color(0xFFF8FAFC),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: BorderSide(
                        color: isDark
                            ? const Color(0xFF334155)
                            : AppColors.border,
                      ),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: BorderSide(
                        color: isDark
                            ? const Color(0xFF334155)
                            : AppColors.border,
                      ),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(
                        color: AppColors.electricBlue,
                        width: 1.5,
                      ),
                    ),
                    contentPadding: const EdgeInsets.all(12),
                  ),
                  validator: (v) {
                    if (v == null || v.trim().length < 3) {
                      return 'Please provide a brief reason (min 3 chars).';
                    }
                    return null;
                  },
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(ctx).pop(null),
              child: Text(
                'Cancel',
                style: TextStyle(
                  color: isDark ? const Color(0xFF94A3B8) : AppColors.ink500,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            ElevatedButton(
              onPressed: () {
                if (formKey.currentState?.validate() == true) {
                  Navigator.of(ctx).pop(reasonCtrl.text.trim());
                }
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.error,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 10,
                ),
                elevation: 0,
              ),
              child: const Text(
                'Decline & Submit Notes',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
          ],
        );
      },
    );

    if (reason == null || reason.isEmpty || !mounted) return;

    setState(() => _isSubmittingAcceptance = true);
    HapticFeedback.mediumImpact();

    try {
      final res = await ApiService.rejectContract(
        tenantId: tid,
        version: _contractVersion,
        reason: reason,
      );

      if (!mounted) return;

      if (res['ok'] == true) {
        setState(() {
          if (_contract != null) {
            _contract!['can_accept'] = false;
            _contract!['can_reject'] = false;
            _contract!['status'] = 'rejected';
            _contract!['rejected_at'] =
                res['rejected_at'] ?? DateTime.now().toIso8601String();
            _contract!['rejection_reason'] = reason;
          }
          _isSubmittingAcceptance = false;
        });

        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Revisions requested. The lessor has been notified.'),
            backgroundColor: AppColors.warning,
            behavior: SnackBarBehavior.floating,
          ),
        );

        if (widget.onLeaseMayHaveChanged != null) {
          await widget.onLeaseMayHaveChanged!();
        }
      } else {
        setState(() => _isSubmittingAcceptance = false);
        final msg =
            res['error']?.toString() ?? 'Failed to submit revision request.';
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(msg),
            backgroundColor: AppColors.error,
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _isSubmittingAcceptance = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(ApiService.errorMessage(e)),
          backgroundColor: AppColors.error,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = widget.applicationData;
    final propertyName =
        _contract?['property_name'] ??
        app?['property_name'] ??
        'Commercial Property';
    final lessorName =
        _contract?['lessor_name'] ?? app?['lessor_name'] ?? 'Property Lessor';
    final rent = (_contract?['rent'] ?? app?['rent'] ?? 0).toDouble();
    final term = (_contract?['term_months'] ?? app?['term_months'] ?? 12)
        .toInt();

    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF000000) : AppColors.bg,
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(
          'Lease Agreement & Terms',
          style: TextStyle(
            color: isDark ? Colors.white : AppColors.ink900,
            fontWeight: FontWeight.w800,
            fontSize: 16,
          ),
        ),
        backgroundColor: isDark ? const Color(0xFF121212) : AppColors.surface,
        elevation: 0,
        bottom: TabBar(
          controller: _tabController,
          indicatorColor: const Color(0xFF1E6BFF),
          indicatorWeight: 3,
          labelColor: const Color(0xFF1E6BFF),
          unselectedLabelColor: isDark
              ? const Color(0xFF94A3B8)
              : AppColors.ink500,
          labelStyle: const TextStyle(
            fontWeight: FontWeight.w800,
            fontSize: 13,
          ),
          tabs: const [
            Tab(
              icon: Icon(Icons.checklist_rounded, size: 20),
              text: 'Negotiated Checklist',
            ),
            Tab(
              icon: Icon(Icons.gavel_rounded, size: 20),
              text: 'Legal Contract Document',
            ),
          ],
        ),
      ),
      bottomNavigationBar: _canAccept && !_loading
          ? SafeArea(
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 12,
                ),
                decoration: BoxDecoration(
                  color: isDark ? const Color(0xFF141414) : Colors.white,
                  border: Border(
                    top: BorderSide(
                      color: isDark
                          ? const Color(0xFF262626)
                          : AppColors.border,
                      width: 1,
                    ),
                  ),
                ),
                child: Row(
                  children: [
                    Expanded(
                      flex: 4,
                      child: SizedBox(
                        height: 46,
                        child: OutlinedButton(
                          onPressed: _isSubmittingAcceptance
                              ? null
                              : _handleRejectContract,
                          style: OutlinedButton.styleFrom(
                            foregroundColor: isDark
                                ? const Color(0xFFF87171)
                                : AppColors.error,
                            side: BorderSide(
                              color: isDark
                                  ? const Color(0xFF7F1D1D)
                                  : const Color(0xFFFECACA),
                              width: 1,
                            ),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                          ),
                          child: const FittedBox(
                            fit: BoxFit.scaleDown,
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Icon(Icons.assignment_return_rounded, size: 16),
                                SizedBox(width: 6),
                                Text(
                                  'Decline / Revise',
                                  style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      flex: 5,
                      child: SizedBox(
                        height: 46,
                        child: ElevatedButton(
                          onPressed: _isSubmittingAcceptance
                              ? null
                              : () => _handleAcceptContract(rent, term),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.electricBlue,
                            foregroundColor: Colors.white,
                            elevation: 0,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                          ),
                          child: _isSubmittingAcceptance
                              ? const SizedBox(
                                  width: 20,
                                  height: 20,
                                  child: CircularProgressIndicator(
                                    color: Colors.white,
                                    strokeWidth: 2,
                                  ),
                                )
                              : FittedBox(
                                  fit: BoxFit.scaleDown,
                                  child: Row(
                                    mainAxisAlignment: MainAxisAlignment.center,
                                    children: [
                                      const Icon(
                                        Icons.check_circle_outline,
                                        size: 17,
                                      ),
                                      const SizedBox(width: 6),
                                      Text(
                                        'Accept Agreement (v$_contractVersion)',
                                        style: const TextStyle(
                                          fontSize: 13.5,
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            )
          : null,
      body: _loading
          ? const Center(
              child: CircularProgressIndicator(color: AppColors.primary),
            )
          : TabBarView(
              controller: _tabController,
              children: [
                _buildNegotiatedChecklistTab(
                  propertyName,
                  lessorName,
                  rent,
                  term,
                ),
                _buildLegalContractTab(propertyName, lessorName, rent, term),
              ],
            ),
    );
  }

  // ---------------- TAB 1: INTERACTIVE NEGOTIATED CHECKLIST ----------------

  Widget _buildNegotiatedChecklistTab(
    String propertyName,
    String lessorName,
    double rent,
    int term,
  ) {
    final effectiveClauses = _getEffectiveClauses();
    final agreedCount = effectiveClauses
        .where(
          (c) => c['agreed'] == true && ((c['note'] ?? '').toString().isEmpty),
        )
        .length;
    final rebuttalCount = effectiveClauses.length - agreedCount;

    return SingleChildScrollView(
      physics: const BouncingScrollPhysics(),
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header Card with Overview
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [AppColors.primary, AppColors.primaryLight],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(20),
              boxShadow: [
                BoxShadow(
                  color: AppColors.primary.withValues(alpha: 0.18),
                  blurRadius: 14,
                  offset: const Offset(0, 5),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Wrap(
                  alignment: WrapAlignment.spaceBetween,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  spacing: 8,
                  runSpacing: 6,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.2),
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: const Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            Icons.handshake_outlined,
                            size: 14,
                            color: Colors.white,
                          ),
                          SizedBox(width: 5),
                          Text(
                            'INTERACTIVE NEGOTIATION',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 10.5,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.5,
                            ),
                          ),
                        ],
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: _isRejected
                            ? AppColors.error.withValues(alpha: 0.35)
                            : (_isAccepted
                                  ? AppColors.success.withValues(alpha: 0.35)
                                  : AppColors.success.withValues(alpha: 0.3)),
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Text(
                        _isRejected
                            ? 'Revision Requested'
                            : (_isAccepted
                                  ? 'Terms Accepted'
                                  : 'Terms Aligned'),
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                Text(
                  propertyName,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                    letterSpacing: -0.3,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  'Lessor: $lessorName · ₱${_formatMoney(rent)}/mo ($term Months)',
                  style: const TextStyle(
                    color: AppColors.tint,
                    fontSize: 12.5,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 16),
                const Divider(color: Colors.white24, height: 1),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: Row(
                        children: [
                          const Icon(
                            Icons.check_circle_rounded,
                            color: Colors.greenAccent,
                            size: 16,
                          ),
                          const SizedBox(width: 6),
                          Text(
                            '$agreedCount Standard Clauses',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (rebuttalCount > 0)
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF59E0B).withValues(alpha: 0.3),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Row(
                          children: [
                            const Icon(
                              Icons.edit_note_rounded,
                              color: Color(0xFFFDE68A),
                              size: 14,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              '$rebuttalCount Counter-Offer Note',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 11,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                      ),
                  ],
                ),
              ],
            ),
          ),
          if ((widget.applicationData?['negotiation_messages']
                      as List<dynamic>?)
                  ?.isNotEmpty ==
              true) ...[
            const SizedBox(height: 16),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: const Color(0xFFF0F7FF),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFCCE3FF), width: 1.5),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Negotiation Conversation',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF0B57D0),
                    ),
                  ),
                  const SizedBox(height: 10),
                  ...((widget.applicationData!['negotiation_messages']
                          as List<dynamic>)
                      .map((m) {
                        final isMe = (m['sender'] ?? '') == 'lessee';
                        final senderLabel = isMe
                            ? 'You (Applicant)'
                            : '$lessorName (Lessor)';
                        final text = (m['message'] ?? '').toString();
                        final timeRaw = m['sent_at']?.toString() ?? '';
                        final timeStr = timeRaw.isNotEmpty
                            ? timeRaw.split('T').first
                            : '';

                        return Container(
                          margin: const EdgeInsets.only(bottom: 8),
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(
                            color: isMe
                                ? Colors.white
                                : const Color(0xFFE8F1FE),
                            borderRadius: BorderRadius.circular(10),
                            border: Border.all(
                              color: isMe
                                  ? AppColors.border
                                  : const Color(0xFFBDD7FE),
                            ),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                mainAxisAlignment:
                                    MainAxisAlignment.spaceBetween,
                                children: [
                                  Expanded(
                                    child: Text(
                                      senderLabel,
                                      style: TextStyle(
                                        fontSize: 11.5,
                                        fontWeight: FontWeight.w700,
                                        color: isMe
                                            ? AppColors.ink900
                                            : const Color(0xFF0B57D0),
                                      ),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                  if (timeStr.isNotEmpty) ...[
                                    const SizedBox(width: 8),
                                    Text(
                                      timeStr,
                                      style: const TextStyle(
                                        fontSize: 10.5,
                                        color: AppColors.ink500,
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                              const SizedBox(height: 4),
                              Text(
                                text,
                                style: const TextStyle(
                                  fontSize: 12.5,
                                  color: AppColors.ink700,
                                  height: 1.35,
                                ),
                              ),
                            ],
                          ),
                        );
                      })),
                ],
              ),
            ),
          ] else if ((widget.applicationData?['lessor_rebuttal'] ?? '')
              .toString()
              .trim()
              .isNotEmpty) ...[
            const SizedBox(height: 16),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: const Color(0xFFF0F7FF),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFCCE3FF), width: 1.5),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Lessor Counter-Proposal / Rebuttal',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF0B57D0),
                        ),
                      ),
                      if ((widget.applicationData?['rebuttal_at'] ?? '')
                          .toString()
                          .isNotEmpty)
                        Text(
                          (widget.applicationData!['rebuttal_at'] as String)
                              .split('T')
                              .first,
                          style: const TextStyle(
                            fontSize: 11,
                            color: AppColors.ink500,
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Text(
                    (widget.applicationData!['lessor_rebuttal'] ?? '')
                        .toString()
                        .trim(),
                    style: const TextStyle(
                      fontSize: 13,
                      color: Color(0xFF1A3C6D),
                      height: 1.4,
                    ),
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 20),

          // Informative Section Label
          Builder(
            builder: (context) {
              final isDark = Theme.of(context).brightness == Brightness.dark;
              final clauses = _getEffectiveClauses();
              return Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Clause-by-Clause Negotiation',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                          color: isDark ? Colors.white : AppColors.ink900,
                          letterSpacing: -0.2,
                        ),
                      ),
                      Text(
                        '${clauses.length} Clauses',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                          color: isDark
                              ? const Color(0xFF94A3B8)
                              : AppColors.ink500,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),

                  // List of Clauses with interactive notes
                  ...List.generate(clauses.length, (index) {
                    final clause = clauses[index];
                    final isAgreed = clause['agreed'] == true;
                    final note = clause['note']?.toString() ?? '';

                    return Container(
                      margin: const EdgeInsets.only(bottom: 14),
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: isDark
                            ? const Color(0xFF141414)
                            : AppColors.surface,
                        borderRadius: BorderRadius.circular(18),
                        border: Border.all(
                          color: isDark
                              ? (isAgreed
                                    ? const Color(0xFF262626)
                                    : const Color(
                                        0xFFF59E0B,
                                      ).withValues(alpha: 0.5))
                              : (isAgreed
                                    ? AppColors.border
                                    : const Color(
                                        0xFFF59E0B,
                                      ).withValues(alpha: 0.6)),
                          width: isAgreed ? 1 : 1.5,
                        ),
                        boxShadow: [
                          BoxShadow(
                            color: isDark
                                ? Colors.black.withValues(alpha: 0.25)
                                : AppColors.primary.withValues(alpha: 0.03),
                            blurRadius: 10,
                            offset: const Offset(0, 3),
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
                                padding: const EdgeInsets.all(6),
                                decoration: BoxDecoration(
                                  color: isAgreed
                                      ? (isDark
                                            ? const Color(
                                                0xFF064E3B,
                                              ).withValues(alpha: 0.5)
                                            : AppColors.successSoft)
                                      : (isDark
                                            ? const Color(
                                                0xFF78350F,
                                              ).withValues(alpha: 0.4)
                                            : const Color(0xFFFEF3C7)),
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: Icon(
                                  isAgreed
                                      ? Icons.check_rounded
                                      : Icons.edit_note_rounded,
                                  size: 16,
                                  color: isAgreed
                                      ? AppColors.success
                                      : const Color(0xFFF59E0B),
                                ),
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      clause['label'] ?? '',
                                      style: TextStyle(
                                        fontSize: 14,
                                        fontWeight: FontWeight.w800,
                                        color: isDark
                                            ? Colors.white
                                            : AppColors.ink900,
                                      ),
                                    ),
                                    const SizedBox(height: 2),
                                    Text(
                                      isAgreed
                                          ? 'Standard Philippine Commercial Practice'
                                          : 'Digital Rebuttal & Custom Term Attached',
                                      style: TextStyle(
                                        fontSize: 11,
                                        fontWeight: FontWeight.w600,
                                        color: isAgreed
                                            ? (isDark
                                                  ? const Color(0xFF94A3B8)
                                                  : AppColors.ink500)
                                            : const Color(0xFFFBBF24),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 4,
                                ),
                                decoration: BoxDecoration(
                                  color: isAgreed
                                      ? (isDark
                                            ? const Color(
                                                0xFF064E3B,
                                              ).withValues(alpha: 0.5)
                                            : AppColors.successSoft)
                                      : (isDark
                                            ? const Color(
                                                0xFF78350F,
                                              ).withValues(alpha: 0.4)
                                            : const Color(0xFFFEF3C7)),
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: Text(
                                  isAgreed ? 'AGREED' : 'COUNTER-OFFER',
                                  style: TextStyle(
                                    fontSize: 9.5,
                                    fontWeight: FontWeight.w900,
                                    letterSpacing: 0.3,
                                    color: isAgreed
                                        ? AppColors.success
                                        : (isDark
                                              ? const Color(0xFFFBBF24)
                                              : const Color(0xFFB45309)),
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 10),
                          Text(
                            clause['standard'] ?? '',
                            style: TextStyle(
                              fontSize: 12.5,
                              color: isDark
                                  ? const Color(0xFFCBD5E1)
                                  : AppColors.ink700,
                              height: 1.4,
                            ),
                          ),

                          // Counter-offer / Rebuttal Note Box (if unchecked)
                          if (!isAgreed && note.isNotEmpty) ...[
                            const SizedBox(height: 12),
                            Container(
                              width: double.infinity,
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: isDark
                                    ? const Color(0xFF261905)
                                    : const Color(0xFFFFFBEB),
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(
                                  color: isDark
                                      ? const Color(0xFF78350F)
                                      : const Color(0xFFFDE68A),
                                ),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Icon(
                                        Icons.speaker_notes_outlined,
                                        size: 13,
                                        color: isDark
                                            ? const Color(0xFFFBBF24)
                                            : const Color(0xFFB45309),
                                      ),
                                      const SizedBox(width: 5),
                                      Text(
                                        'Lessee Counter-Offer Rebuttal:',
                                        style: TextStyle(
                                          fontSize: 11,
                                          fontWeight: FontWeight.w800,
                                          color: isDark
                                              ? const Color(0xFFFBBF24)
                                              : const Color(0xFFB45309),
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    '"$note"',
                                    style: TextStyle(
                                      fontSize: 12.5,
                                      fontStyle: FontStyle.italic,
                                      fontWeight: FontWeight.w600,
                                      color: isDark
                                          ? Colors.white
                                          : AppColors.ink900,
                                    ),
                                  ),
                                  const SizedBox(height: 6),
                                  const Row(
                                    children: [
                                      Icon(
                                        Icons.check_circle_outline,
                                        size: 12,
                                        color: Color(0xFF059669),
                                      ),
                                      SizedBox(width: 4),
                                      Expanded(
                                        child: Text(
                                          'Reviewed & pre-accepted by Lessor during approval',
                                          style: TextStyle(
                                            fontSize: 10.5,
                                            fontWeight: FontWeight.w700,
                                            color: Color(0xFF059669),
                                          ),
                                          maxLines: 2,
                                          overflow: TextOverflow.ellipsis,
                                        ),
                                      ),
                                    ],
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ],
                      ),
                    );
                  }),
                ],
              );
            },
          ),
        ],
      ),
    );
  }

  // ---------------- TAB 2: LEGAL CONTRACT & CONFIDENTIALITY LOCK ----------------

  Widget _buildLegalContractTab(
    String propertyName,
    String lessorName,
    double rent,
    int term,
  ) {
    if (!_isPaymentComplete) {
      return _buildConfidentialityLockedView(propertyName, lessorName, rent);
    }

    return _buildUnlockedContractDocument(propertyName, lessorName, rent, term);
  }

  Widget _buildConfidentialityLockedView(
    String propertyName,
    String lessorName,
    double rent,
  ) {
    return SingleChildScrollView(
      physics: const BouncingScrollPhysics(),
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          // Security Lock Badge Card
          Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(24),
              border: Border.all(
                color: const Color(0xFFF59E0B).withValues(alpha: 0.4),
                width: 1.5,
              ),
              boxShadow: [
                BoxShadow(
                  color: const Color(0xFFF59E0B).withValues(alpha: 0.08),
                  blurRadius: 20,
                  offset: const Offset(0, 6),
                ),
              ],
            ),
            child: Column(
              children: [
                Container(
                  width: 72,
                  height: 72,
                  decoration: BoxDecoration(
                    color: const Color(0xFFFEF3C7),
                    borderRadius: BorderRadius.circular(22),
                    border: Border.all(color: const Color(0xFFFDE68A)),
                  ),
                  child: const Center(
                    child: Icon(
                      Icons.lock_person_rounded,
                      size: 36,
                      color: Color(0xFFD97706),
                    ),
                  ),
                ),
                const SizedBox(height: 18),
                const Text(
                  'Confidentiality Lock Active',
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                    color: AppColors.ink900,
                    letterSpacing: -0.4,
                  ),
                ),
                const SizedBox(height: 8),
                const Text(
                  'The legally binding execution contract has been auto-generated based on your agreed checklist. Under platform security policy, full legal contract download is protected until initial security deposit / first month payment clearance is finalized.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 13,
                    color: AppColors.ink500,
                    height: 1.45,
                  ),
                ),
                const SizedBox(height: 20),

                // Deposit Amount Pill
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 12,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.bg,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: AppColors.border),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'INITIAL TRANSACTION REQUIRED',
                              style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.w800,
                                color: AppColors.ink500,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                            SizedBox(height: 2),
                            Text(
                              'Security Deposit & 1st Mo. Rent',
                              style: TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w800,
                                color: AppColors.ink900,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 10),
                      FittedBox(
                        fit: BoxFit.scaleDown,
                        child: Text(
                          '₱${_formatMoney(rent * 2)}',
                          style: const TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w900,
                            color: AppColors.primary,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 22),

                // Unlock / Pay Action Button (Centered content)
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: _isSimulatingPayment
                        ? null
                        : _simulateCompletePayment,
                    style: ElevatedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(
                        vertical: 16,
                        horizontal: 16,
                      ),
                      backgroundColor: const Color(0xFF102340),
                      foregroundColor: Colors.white,
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16),
                      ),
                    ),
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      alignment: Alignment.center,
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.center,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          if (_isSimulatingPayment) ...[
                            const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(
                                color: Colors.white,
                                strokeWidth: 2,
                              ),
                            ),
                            const SizedBox(width: 8),
                            const Text(
                              'Verifying Transaction…',
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                fontSize: 14.5,
                                fontWeight: FontWeight.w800,
                                color: Colors.white,
                              ),
                            ),
                          ] else ...[
                            const Icon(
                              Icons.verified_user_rounded,
                              size: 20,
                              color: Colors.white,
                            ),
                            const SizedBox(width: 8),
                            const Text(
                              'Complete Initial Payment & Unlock',
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                fontSize: 14.5,
                                fontWeight: FontWeight.w800,
                                color: Colors.white,
                              ),
                            ),
                          ],
                        ],
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
  }

  Widget _buildUnlockedContractDocument(
    String propertyName,
    String lessorName,
    double rent,
    int term,
  ) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    // Top status banner configuration
    final bool isAccepted = _isAccepted;
    final bool isRejected = _isRejected;
    final bool needsAcceptance = _canAccept;

    final Color bannerBg = isAccepted
        ? (isDark
              ? const Color(0xFF064E3B).withValues(alpha: 0.3)
              : AppColors.successSoft)
        : (isRejected
              ? (isDark
                    ? const Color(0xFF450A0A).withValues(alpha: 0.3)
                    : const Color(0xFFFEF2F2))
              : (needsAcceptance
                    ? (isDark
                          ? const Color(0xFF1E3A8A).withValues(alpha: 0.3)
                          : const Color(0xFFEFF6FF))
                    : (_isRevisionPending
                          ? (isDark
                                ? const Color(0xFF78350F).withValues(alpha: 0.3)
                                : const Color(0xFFFEF3C7))
                          : (isDark
                                ? const Color(0xFF064E3B).withValues(alpha: 0.3)
                                : AppColors.successSoft))));

    final Color bannerBorder = isAccepted
        ? AppColors.success.withValues(alpha: isDark ? 0.4 : 0.3)
        : (isRejected
              ? AppColors.error.withValues(alpha: isDark ? 0.4 : 0.3)
              : (needsAcceptance
                    ? AppColors.electricBlue.withValues(
                        alpha: isDark ? 0.4 : 0.3,
                      )
                    : (_isRevisionPending
                          ? const Color(
                              0xFFF59E0B,
                            ).withValues(alpha: isDark ? 0.4 : 0.3)
                          : AppColors.success.withValues(
                              alpha: isDark ? 0.4 : 0.3,
                            ))));

    final IconData bannerIcon = isAccepted
        ? Icons.verified_user_rounded
        : (isRejected
              ? Icons.assignment_return_rounded
              : (needsAcceptance
                    ? Icons.rate_review_outlined
                    : (_isRevisionPending
                          ? Icons.history_rounded
                          : Icons.lock_open_rounded)));

    final Color bannerIconColor = isAccepted
        ? AppColors.success
        : (isRejected
              ? AppColors.error
              : (needsAcceptance
                    ? AppColors.electricBlue
                    : (_isRevisionPending
                          ? const Color(0xFFD97706)
                          : AppColors.success)));

    final String bannerTitle = isAccepted
        ? 'Legal Agreement Digitally Accepted (v$_contractVersion)'
        : (isRejected
              ? 'Agreement Declined / Revision Requested (v$_contractVersion)'
              : (needsAcceptance
                    ? 'Agreement Finalized by Lessor (v$_contractVersion)'
                    : (_isRevisionPending
                          ? 'Revision in Progress'
                          : 'Legal Contract Unlocked & Active')));

    final String bannerDesc = isAccepted
        ? (_acceptedAt != null && _acceptedAt!.isNotEmpty
              ? 'Accepted on ${_formatDateShort(_acceptedAt)}. Legally binding under Philippine Commercial Law.'
              : 'Binding digital contract logged under Philippine Commercial Law.')
        : (isRejected
              ? 'You declined this version${_rejectedAt != null && _rejectedAt!.isNotEmpty ? ' on ${_formatDateShort(_rejectedAt)}' : ''}: "${_rejectionReason ?? ''}". The lessor has been notified to revise the terms.'
              : (needsAcceptance
                    ? 'The lessor has finalized this contract version. Please review the covenants below. You may accept or decline with notes.'
                    : (_isRevisionPending
                          ? 'Lessor is currently preparing a new contract version. This document displays the previously agreed version.'
                          : 'Verified deposit logged. Official contract binding under Philippine Commercial Law.')));

    return SingleChildScrollView(
      physics: const BouncingScrollPhysics(),
      padding: const EdgeInsets.all(18),
      child: Column(
        children: [
          // Contract Top Status Banner
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: BoxDecoration(
              color: bannerBg,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: bannerBorder),
            ),
            child: Row(
              children: [
                Icon(bannerIcon, color: bannerIconColor, size: 22),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        bannerTitle,
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                          color: bannerIconColor,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        bannerDesc,
                        style: TextStyle(
                          fontSize: 11,
                          color: isDark
                              ? const Color(0xFF94A3B8)
                              : AppColors.ink700,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),

          // Formal Document Paper View
          Container(
            padding: const EdgeInsets.all(22),
            decoration: BoxDecoration(
              color: isDark ? const Color(0xFF141414) : Colors.white,
              borderRadius: BorderRadius.circular(20),
              border: Border.all(
                color: isDark ? const Color(0xFF262626) : AppColors.border,
              ),
              boxShadow: [
                BoxShadow(
                  color: isDark
                      ? Colors.black.withValues(alpha: 0.3)
                      : Colors.black.withValues(alpha: 0.04),
                  blurRadius: 18,
                  offset: const Offset(0, 6),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Column(
                    children: [
                      Container(
                        width: 44,
                        height: 44,
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: isDark
                              ? const Color(0xFF1E293B)
                              : AppColors.accentSoft,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Image.asset(
                          isDark ? 'img/invert.png' : 'img/Frame 2.png',
                          fit: BoxFit.contain,
                        ),
                      ),
                      const SizedBox(height: 10),
                      Text(
                        'CONTRACT OF LEASE',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w900,
                          letterSpacing: 0.5,
                          color: isDark ? Colors.white : AppColors.ink900,
                        ),
                      ),
                      Text(
                        'Commercial Property Lease Agreement',
                        style: TextStyle(
                          fontSize: 12,
                          color: isDark
                              ? const Color(0xFF94A3B8)
                              : AppColors.ink500,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),
                Divider(
                  color: isDark
                      ? const Color(0xFF262626)
                      : AppColors.borderLight,
                ),
                const SizedBox(height: 14),

                Text(
                  'KNOW ALL MEN BY THESE PRESENTS:',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                    color: isDark ? const Color(0xFF94A3B8) : AppColors.ink700,
                  ),
                ),
                const SizedBox(height: 6),
                RichText(
                  text: TextSpan(
                    style: TextStyle(
                      fontSize: 12.5,
                      color: isDark
                          ? const Color(0xFFCBD5E1)
                          : AppColors.ink900,
                      height: 1.5,
                      fontFamily: 'Roboto',
                    ),
                    children: [
                      const TextSpan(
                        text:
                            'This Contract of Lease is entered into by and between the Lessor, ',
                      ),
                      TextSpan(
                        text: lessorName,
                        style: TextStyle(
                          fontWeight: FontWeight.w800,
                          color: isDark ? Colors.white : Colors.black,
                        ),
                      ),
                      const TextSpan(
                        text:
                            ', and the Lessee, for the commercial lease of premises situated at ',
                      ),
                      TextSpan(
                        text: propertyName,
                        style: TextStyle(
                          fontWeight: FontWeight.w800,
                          color: isDark ? Colors.white : Colors.black,
                        ),
                      ),
                      TextSpan(
                        text:
                            ' for a monthly consideration of ₱${_formatMoney(rent)} for a period of $term Months.',
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 18),

                Text(
                  'TERMS & SPECIAL NEGOTIATED COVENANTS:',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                    color: isDark ? const Color(0xFF94A3B8) : AppColors.ink700,
                  ),
                ),
                const SizedBox(height: 8),

                ...() {
                  final clauses = _getEffectiveClauses();
                  return List.generate(clauses.length, (i) {
                    final c = clauses[i];
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${i + 1}. ',
                            style: TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 12,
                              color: isDark ? Colors.white : Colors.black,
                            ),
                          ),
                          Expanded(
                            child: RichText(
                              text: TextSpan(
                                style: TextStyle(
                                  fontSize: 12,
                                  color: isDark
                                      ? const Color(0xFFCBD5E1)
                                      : AppColors.ink700,
                                  height: 1.4,
                                  fontFamily: 'Roboto',
                                ),
                                children: [
                                  TextSpan(
                                    text: '${c['label']}: ',
                                    style: TextStyle(
                                      fontWeight: FontWeight.w800,
                                      color: isDark
                                          ? Colors.white
                                          : AppColors.ink900,
                                    ),
                                  ),
                                  TextSpan(
                                    text: c['agreed'] == true
                                        ? c['standard']
                                        : '${c['standard']} (Custom Term: ${c['note']})',
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ],
                      ),
                    );
                  });
                }(),

                if (_specialProvisions != null &&
                    _specialProvisions!.trim().isNotEmpty) ...[
                  const SizedBox(height: 18),
                  Text(
                    'SPECIAL PROVISIONS & LESSOR STIPULATIONS:',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      color: isDark
                          ? const Color(0xFF94A3B8)
                          : AppColors.ink700,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: isDark
                          ? const Color(0xFF1E293B).withValues(alpha: 0.5)
                          : const Color(0xFFF8FAFC),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(
                        color: isDark
                            ? const Color(0xFF334155)
                            : const Color(0xFFE2E8F0),
                      ),
                    ),
                    child: Text(
                      _specialProvisions!.trim(),
                      style: TextStyle(
                        fontSize: 12.5,
                        height: 1.45,
                        color: isDark
                            ? const Color(0xFFE2E8F0)
                            : AppColors.ink900,
                      ),
                    ),
                  ),
                ],

                const SizedBox(height: 24),
                Divider(
                  color: isDark
                      ? const Color(0xFF262626)
                      : AppColors.borderLight,
                ),
                const SizedBox(height: 16),

                // Digital Signatures
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 4,
                            ),
                            decoration: BoxDecoration(
                              color: isDark
                                  ? const Color(
                                      0xFF1E3A8A,
                                    ).withValues(alpha: 0.3)
                                  : AppColors.accentSoft,
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              _finalizedAt != null
                                  ? 'FINALIZED BY LESSOR'
                                  : 'DIGITALLY SIGNED',
                              style: TextStyle(
                                fontSize: 9.5,
                                fontWeight: FontWeight.w800,
                                color: isDark
                                    ? const Color(0xFF60A5FA)
                                    : AppColors.primary,
                              ),
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            lessorName,
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w800,
                              color: isDark ? Colors.white : Colors.black,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          Text(
                            _finalizedAt != null && _finalizedAt!.isNotEmpty
                                ? 'Finalized ${_formatDateShort(_finalizedAt)}'
                                : 'Lessor / Property Owner',
                            style: TextStyle(
                              fontSize: 11,
                              color: isDark
                                  ? const Color(0xFF94A3B8)
                                  : AppColors.ink500,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 4,
                            ),
                            decoration: BoxDecoration(
                              color: isAccepted
                                  ? (isDark
                                        ? const Color(
                                            0xFF064E3B,
                                          ).withValues(alpha: 0.3)
                                        : AppColors.successSoft)
                                  : (isRejected
                                        ? (isDark
                                              ? const Color(
                                                  0xFF7F1D1D,
                                                ).withValues(alpha: 0.4)
                                              : const Color(0xFFFEE2E2))
                                        : (isDark
                                              ? const Color(
                                                  0xFF78350F,
                                                ).withValues(alpha: 0.4)
                                              : const Color(0xFFFEF3C7))),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              isAccepted
                                  ? 'DIGITALLY ACCEPTED'
                                  : (isRejected
                                        ? 'REVISION REQUESTED'
                                        : 'PENDING ACCEPTANCE'),
                              style: TextStyle(
                                fontSize: 9.5,
                                fontWeight: FontWeight.w800,
                                color: isAccepted
                                    ? AppColors.success
                                    : (isRejected
                                          ? (isDark
                                                ? const Color(0xFFF87171)
                                                : AppColors.error)
                                          : (isDark
                                                ? const Color(0xFFFBBF24)
                                                : const Color(0xFFB45309))),
                              ),
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            _contract?['tenant_name'] ??
                                'Lessee Account Holder',
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w800,
                              color: isDark ? Colors.white : Colors.black,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          Text(
                            isAccepted &&
                                    _acceptedAt != null &&
                                    _acceptedAt!.isNotEmpty
                                ? 'Accepted ${_formatDateShort(_acceptedAt)}'
                                : (isRejected &&
                                          _rejectedAt != null &&
                                          _rejectedAt!.isNotEmpty
                                      ? 'Declined ${_formatDateShort(_rejectedAt)}'
                                      : (isAccepted
                                            ? 'Tenant / Commercial Lessee'
                                            : 'Awaiting Lessee Action')),
                            style: TextStyle(
                              fontSize: 11,
                              color: isDark
                                  ? const Color(0xFF94A3B8)
                                  : AppColors.ink500,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                if (_contract?['fingerprint'] != null &&
                    (_contract!['fingerprint'] as String).isNotEmpty) ...[
                  const SizedBox(height: 16),
                  Center(
                    child: Text(
                      'Audit Hash: ${_contract!['fingerprint']}',
                      style: TextStyle(
                        fontSize: 9.5,
                        fontFamily: 'monospace',
                        color: isDark
                            ? const Color(0xFF64748B)
                            : AppColors.ink500,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 20),
        ],
      ),
    );
  }

  String _formatMoney(double v) {
    final s = v.toStringAsFixed(0);
    final buf = StringBuffer();
    for (int i = 0; i < s.length; i++) {
      if (i > 0 && (s.length - i) % 3 == 0) buf.write(',');
      buf.write(s[i]);
    }
    return buf.toString();
  }
}
