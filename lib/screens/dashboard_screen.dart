import 'package:flutter/material.dart';
import '../theme.dart';
import '../services/api_service.dart';
import 'tickets_list_screen.dart';
import 'ticket_detail_screen.dart';
import 'payment_arrangement_screen.dart';
import 'rent_payment_screen.dart';
import 'violation_notice_sheet.dart';
import 'violations_screen.dart';
import 'receipts_history_screen.dart';
import 'messages_screen.dart';
import 'my_applications_screen.dart';
import 'package:flutter_svg/flutter_svg.dart';
import '../widgets/luxury_rent_card.dart';
import '../widgets/building_details_sheet.dart';
import '../widgets/eviction_lock_panel.dart';

class DashboardScreen extends StatefulWidget {
  final Map<String, dynamic> lease;
  final VoidCallback onRefresh;
  final Function(int)? onNavigateTab;
  final List<Map<String, dynamic>>? allLeases;
  final int activeLeaseIndex;
  final Function(int index, Map<String, dynamic> lease)? onLeaseChanged;

  const DashboardScreen({
    super.key,
    required this.lease,
    required this.onRefresh,
    this.onNavigateTab,
    this.allLeases,
    this.activeLeaseIndex = 0,
    this.onLeaseChanged,
  });

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  late Future<List<dynamic>> _ticketsFuture;
  late Future<List<dynamic>> _arrangementsFuture;
  late Future<List<Map<String, dynamic>>> _violationsFuture;
  late Future<String> _rentStatusFuture;
  String _userName = '';
  String _companyName = '';
  final Set<int> _checkedTenantIds = {};

  // GlobeOne-style multi-account switcher state
  List<Map<String, dynamic>> _allLeases = [];
  int _selectedLeaseIndex = 0;
  int _carouselPageIndex = 0;
  late PageController _cardPageController;

  @override
  void initState() {
    super.initState();
    _selectedLeaseIndex = widget.activeLeaseIndex;
    _carouselPageIndex = widget.activeLeaseIndex;
    _cardPageController = PageController(
      viewportFraction: 0.94,
      initialPage: widget.activeLeaseIndex,
    );
    if (widget.allLeases != null && widget.allLeases!.isNotEmpty) {
      _allLeases = widget.allLeases!;
    }
    final initialTid = widget.lease['tenant_id'] ?? widget.lease['id'];
    final initialTenantId = initialTid is int
        ? initialTid
        : (initialTid != null ? int.tryParse(initialTid.toString()) : null);
    final initialLessor = (widget.lease['lessor_name'] ?? '').toString();
    _ticketsFuture = ApiService.fetchTickets(tenantId: initialTenantId);
    _arrangementsFuture = ApiService.fetchInstallmentRequests(
      tenantId: initialTenantId,
    );
    _violationsFuture = ApiService.fetchViolations(
      tenantId: initialTenantId,
      lessorName: initialLessor,
    );
    _rentStatusFuture = ApiService.getDemoRentStatus();
    _loadUserName();
    _loadAllLeases();

    WidgetsBinding.instance.addPostFrameCallback((_) {
      _checkPendingViolations(tenantId: initialTenantId);
    });
  }

  @override
  void didUpdateWidget(covariant DashboardScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.allLeases != null && widget.allLeases != oldWidget.allLeases) {
      final oldTid = oldWidget.lease['tenant_id'] ?? oldWidget.lease['id'];
      final newTid = widget.lease['tenant_id'] ?? widget.lease['id'];
      if (oldTid != newTid) {
        _checkedTenantIds.clear();
      }
      setState(() {
        _allLeases = widget.allLeases!;
      });
    }
    if (widget.activeLeaseIndex != _selectedLeaseIndex &&
        _cardPageController.hasClients) {
      setState(() {
        _selectedLeaseIndex = widget.activeLeaseIndex;
        _carouselPageIndex = widget.activeLeaseIndex;
      });
      _cardPageController.jumpToPage(widget.activeLeaseIndex);
      _loadAccountFutures(_activeTenantId);
    }
  }

  @override
  void dispose() {
    _cardPageController.dispose();
    super.dispose();
  }

  Future<void> _loadAllLeases({bool forceNetwork = false}) async {
    try {
      final list = (!forceNetwork && widget.allLeases != null && widget.allLeases!.isNotEmpty)
          ? widget.allLeases!
          : await ApiService.fetchAllActiveLeases(
              initialLease: forceNetwork ? null : widget.lease,
            );
      if (mounted) {
        setState(() {
          _allLeases = list;
          if (_selectedLeaseIndex >= _allLeases.length) {
            _selectedLeaseIndex = 0;
            _carouselPageIndex = 0;
          }
        });
        if (_allLeases.isNotEmpty && _selectedLeaseIndex < _allLeases.length) {
          widget.onLeaseChanged?.call(
            _selectedLeaseIndex,
            _allLeases[_selectedLeaseIndex],
          );
        }
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _allLeases = [widget.lease];
        });
      }
    }
  }

  Map<String, dynamic> get _activeLease {
    if (_allLeases.isNotEmpty && _selectedLeaseIndex < _allLeases.length) {
      return _allLeases[_selectedLeaseIndex];
    }
    return widget.lease;
  }

  int? get _activeTenantId {
    final tid = _activeLease['tenant_id'] ?? _activeLease['id'];
    return tid is int
        ? tid
        : (tid != null ? int.tryParse(tid.toString()) : null);
  }

  void _loadAccountFutures(int? tenantId) {
    final lessor = (_activeLease['lessor_name'] ?? '').toString();
    setState(() {
      _ticketsFuture = ApiService.fetchTickets(tenantId: tenantId);
      _arrangementsFuture = ApiService.fetchInstallmentRequests(
        tenantId: tenantId,
      );
      _violationsFuture = ApiService.fetchViolations(
        tenantId: tenantId,
        lessorName: lessor,
      );
    });
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _checkPendingViolations(tenantId: tenantId);
    });
  }

  Future<void> _checkPendingViolations({int? tenantId}) async {
    final effectiveId = tenantId ?? _activeTenantId ?? 0;
    if (_checkedTenantIds.contains(effectiveId)) return;
    _checkedTenantIds.add(effectiveId);
    try {
      final activeLessor = (_activeLease['lessor_name'] ?? '').toString();
      final rawViolations = await ApiService.fetchViolations(
        tenantId: effectiveId > 0 ? effectiveId : null,
        lessorName: activeLessor,
      );
      if (!mounted) return;
      // Filter out violations belonging to a different lease or landlord
      final violations = rawViolations.where((v) {
        if (v['tenant_id'] != null && effectiveId > 0) {
          final int? vTid = v['tenant_id'] is int
              ? v['tenant_id'] as int
              : int.tryParse(v['tenant_id'].toString());
          if (vTid != null && vTid != effectiveId) {
            return false;
          }
        }
        if (v['lessor_name'] != null && activeLessor.isNotEmpty) {
          final vLessor = v['lessor_name'].toString().trim().toLowerCase();
          if (vLessor.isNotEmpty &&
              vLessor != activeLessor.trim().toLowerCase()) {
            return false;
          }
        }
        return true;
      }).toList();

      final unack = violations
          .where((v) => (v['acknowledged'] == 0 || v['acknowledged'] == false))
          .toList();
      if (unack.isNotEmpty) {
        final target = unack.first;
        await ViolationNoticeSheet.show(
          context,
          violation: target,
          isGated: true,
        );
        _refresh();
      }
    } catch (_) {}
  }

  Future<void> _loadUserName() async {
    final name = await ApiService.getUserName();
    final company = await ApiService.getCompanyName();
    if (mounted) {
      setState(() {
        if (name != null && name.isNotEmpty) _userName = name;
        if (company != null && company.isNotEmpty) _companyName = company;
      });
    }
  }

  Future<void> _refresh() async {
    final tid = _activeTenantId;
    final lessor = (_activeLease['lessor_name'] ?? '').toString();
    setState(() {
      _ticketsFuture = ApiService.fetchTickets(tenantId: tid);
      _arrangementsFuture = ApiService.fetchInstallmentRequests(tenantId: tid);
      _violationsFuture = ApiService.fetchViolations(
        tenantId: tid,
        lessorName: lessor,
      );
      _rentStatusFuture = ApiService.getDemoRentStatus();
    });
    await Future.wait([
      _ticketsFuture,
      _arrangementsFuture,
      _violationsFuture,
      _loadAllLeases(forceNetwork: true),
    ]);
    widget.onRefresh();
  }

  String _getMonthName(int month) {
    const months = [
      'January',
      'February',
      'March',
      'April',
      'May',
      'June',
      'July',
      'August',
      'September',
      'October',
      'November',
      'December',
    ];
    return months[month - 1];
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

  String _formatDate(dynamic dateVal) {
    if (dateVal == null) return '';
    DateTime? dt;
    if (dateVal is DateTime) {
      dt = dateVal;
    } else {
      dt = DateTime.tryParse(dateVal.toString());
    }
    if (dt == null) return dateVal.toString();
    const months = [
      'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
      'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
    ];
    return '${months[dt.month - 1]} ${dt.day.toString().padLeft(2, '0')}, ${dt.year}';
  }

  IconData _getReportCategoryIcon(String category) {
    final cat = category.toLowerCase();
    if (cat.contains('elect') ||
        cat.contains('light') ||
        cat.contains('power')) {
      return Icons.bolt_rounded; // Lightning for electrical
    } else if (cat.contains('plumb') ||
        cat.contains('water') ||
        cat.contains('leak') ||
        cat.contains('pipe')) {
      return Icons.water_drop_rounded;
    } else if (cat.contains('comfort') ||
        cat.contains('cr') ||
        cat.contains('toilet') ||
        cat.contains('bath')) {
      return Icons.bathtub_outlined;
    } else if (cat.contains('ac') ||
        cat.contains('hvac') ||
        cat.contains('cool')) {
      return Icons.ac_unit_rounded;
    } else if (cat.contains('door') ||
        cat.contains('lock') ||
        cat.contains('key')) {
      return Icons.lock_outline_rounded;
    } else if (cat.contains('clean') ||
        cat.contains('trash') ||
        cat.contains('pest')) {
      return Icons.cleaning_services_outlined;
    }
    return Icons.build_rounded;
  }

  Widget _buildExpandBusinessCard(bool isDark) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 4),
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: isDark
              ? [const Color(0xFF0F172A), const Color(0xFF0B1B3D)]
              : [const Color(0xFF0B1B3D), const Color(0xFF162D63)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: const Color(0xFF1E6BFF).withValues(alpha: 0.35),
          width: 1.2,
        ),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0B1B3D).withValues(alpha: 0.25),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 5,
                ),
                decoration: BoxDecoration(
                  color: const Color(0xFF1E6BFF).withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(
                    color: const Color(0xFF1E6BFF).withValues(alpha: 0.4),
                  ),
                ),
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      Icons.add_business_rounded,
                      size: 14,
                      color: Colors.white,
                    ),
                    SizedBox(width: 5),
                    Text(
                      'BUSINESS EXPANSION',
                      style: TextStyle(
                        fontSize: 10.5,
                        fontWeight: FontWeight.w900,
                        color: Colors.white,
                        letterSpacing: 0.6,
                      ),
                    ),
                  ],
                ),
              ),
              const Icon(
                Icons.arrow_forward_rounded,
                color: Colors.white70,
                size: 20,
              ),
            ],
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: const [
              Text(
                'Expand to Another Space',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                  color: Colors.white,
                  letterSpacing: -0.3,
                ),
              ),
              SizedBox(height: 4),
              Text(
                'Browse prime retail stalls, kiosks, and commercial units to open your next branch.',
                style: TextStyle(
                  fontSize: 12,
                  color: Color(0xFF94A3B8),
                  height: 1.35,
                ),
              ),
            ],
          ),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: () => widget.onNavigateTab?.call(2),
              icon: const Icon(Icons.explore_rounded, size: 16),
              label: const Text('Browse Available Stalls'),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF1E6BFF),
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 12),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
                elevation: 0,
              ),
            ),
          ),
        ],
      ),
    );
  }

  void _showLockedNotice() {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: const Color(0xFF991B1B),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          content: const Text(
            'Locked for this evicted business. Settle its balance or swipe the card to switch to a clean business.',
            style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
          ),
        ),
      );
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final rentAmount = (_activeLease['rent'] as num?)?.toDouble() ?? 12000.0;
    final bool isActiveLocked = isLeaseEvictionLocked(_activeLease);
    final leaseListForSwitch = _allLeases.isNotEmpty ? _allLeases : [widget.lease];
    final int cleanLeaseIndex =
        leaseListForSwitch.indexWhere((l) => !isLeaseEvictionLocked(l));
    final String? cleanBizLabel = cleanLeaseIndex >= 0
        ? leaseDisplayLabel(leaseListForSwitch[cleanLeaseIndex])
        : null;
    final now = DateTime.now();
    final currentMonthName = _getMonthName(now.month);

    return Scaffold(
      backgroundColor: isDark
          ? const Color(0xFF000000)
          : const Color(0xFFF7F8FA),
      body: RefreshIndicator(
        onRefresh: _refresh,
        color: isDark ? AppColors.electricBlue : AppColors.midnightNavy,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(
            parent: BouncingScrollPhysics(),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // ================= TOP HEADER (PWESTORA BRAND & PROFILE AVATAR) =================
              Padding(
                padding: EdgeInsets.only(
                  top: MediaQuery.of(context).padding.top + 16,
                  left: 20,
                  right: 20,
                  bottom: 12,
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    // Brand Logo + Pwestora Name (Prominent & bold)
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Image.asset(
                          isDark ? 'img/invert.png' : 'img/Frame 2.png',
                          width: 26,
                          height: 26,
                          fit: BoxFit.contain,
                        ),
                        const SizedBox(width: 8),
                        Text(
                          'Pwestora',
                          style: TextStyle(
                            fontSize: 22,
                            fontWeight: FontWeight.w900,
                            color: isDark
                                ? Colors.white
                                : const Color(0xFF111418),
                            letterSpacing: -0.6,
                          ),
                        ),
                      ],
                    ),
                    const Spacer(),

                    // Receipts Button (Small icon beside Message button — locked while viewing an evicted card)
                    Tooltip(
                      message: isActiveLocked ? 'Locked during Eviction Notice' : 'Receipts',
                      child: Opacity(
                        opacity: isActiveLocked ? 0.45 : 1.0,
                        child: InkWell(
                          borderRadius: BorderRadius.circular(22),
                          onTap: isActiveLocked
                              ? _showLockedNotice
                              : () {
                                  Navigator.push(
                                    context,
                                    MaterialPageRoute(
                                      builder: (_) => ReceiptsHistoryScreen(
                                        lease: _activeLease,
                                        rentAmount: rentAmount,
                                      ),
                                    ),
                                  );
                                },
                          child: Container(
                            width: 44,
                            height: 44,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: isDark
                                  ? const Color(0xFF1E293B)
                                  : Colors.white,
                              boxShadow: [
                                BoxShadow(
                                  color: isDark
                                      ? Colors.black.withValues(alpha: 0.35)
                                      : const Color(
                                          0xFF0F172A,
                                        ).withValues(alpha: 0.07),
                                  blurRadius: 12,
                                  offset: const Offset(0, 3),
                                ),
                              ],
                            ),
                            alignment: Alignment.center,
                            child: Icon(
                              isActiveLocked
                                  ? Icons.lock_outline_rounded
                                  : Icons.receipt_long_rounded,
                              size: 21,
                              color: isActiveLocked
                                  ? AppColors.error
                                  : (isDark
                                      ? const Color(0xFF60A5FA)
                                      : const Color(0xFF0F172A)),
                            ),
                          ),
                        ),
                      ),
                    ),

                    const SizedBox(width: 8),

                    // Chat Button (Clean borderless floating circular button — locked while viewing an evicted card)
                    Opacity(
                      opacity: isActiveLocked ? 0.45 : 1.0,
                      child: InkWell(
                        borderRadius: BorderRadius.circular(22),
                        onTap: isActiveLocked
                            ? _showLockedNotice
                            : () {
                                Navigator.of(context).push(
                                  MaterialPageRoute(
                                    builder: (_) => MessagesScreen(
                                      lease: _activeLease,
                                      onNavigateTab: widget.onNavigateTab,
                                    ),
                                  ),
                                );
                              },
                        child: Container(
                          width: 44,
                          height: 44,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: isDark
                                ? const Color(0xFF1E293B)
                                : Colors.white,
                            boxShadow: [
                              BoxShadow(
                                color: isDark
                                    ? Colors.black.withValues(alpha: 0.35)
                                    : const Color(
                                        0xFF0F172A,
                                      ).withValues(alpha: 0.07),
                                blurRadius: 12,
                                offset: const Offset(0, 3),
                              ),
                            ],
                          ),
                          child: isActiveLocked
                              ? const Icon(
                                  Icons.lock_outline_rounded,
                                  size: 20,
                                  color: AppColors.error,
                                )
                              : Stack(
                                  clipBehavior: Clip.none,
                                  alignment: Alignment.center,
                                  children: [
                                    SvgPicture.asset(
                                      'img/comment-alt-dots.svg',
                                      width: 21,
                                      height: 21,
                                      colorFilter: ColorFilter.mode(
                                        isDark
                                            ? const Color(0xFF60A5FA)
                                            : const Color(0xFF0F172A),
                                        BlendMode.srcIn,
                                      ),
                                    ),
                                    Positioned(
                                      top: 2,
                                      right: 2,
                                      child: Container(
                                        width: 10,
                                        height: 10,
                                        decoration: BoxDecoration(
                                          color: const Color(0xFF2563EB),
                                          shape: BoxShape.circle,
                                          border: Border.all(
                                            color: isDark
                                                ? const Color(0xFF1E293B)
                                                : Colors.white,
                                            width: 1.8,
                                          ),
                                          boxShadow: [
                                            BoxShadow(
                                              color: const Color(
                                                0xFF2563EB,
                                              ).withValues(alpha: 0.4),
                                              blurRadius: 4,
                                              offset: const Offset(0, 1),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 6),

              // ================= STACKED LUXURY RENT CARD CAROUSEL (PAGEVIEW) =================
              FutureBuilder<String>(
                future: _rentStatusFuture,
                builder: (context, rentStatusSnap) {
                  final simulatorStatus = rentStatusSnap.data ?? 'normal';

                  return FutureBuilder<List<dynamic>>(
                    future: _arrangementsFuture,
                    builder: (context, snapshot) {
                      final reqs = snapshot.data ?? [];
                      final latest = reqs.isNotEmpty
                          ? (reqs.first as Map<String, dynamic>)
                          : null;
                      final status = (latest?['status'] ?? '')
                          .toString()
                          .toLowerCase();
                      final plan = latest?['plan_label'] ?? '2 payments';
                      final isApproved = status == 'approved';
                      final isPending = status == 'pending';
                      final isActiveLeaseOverdue =
                          _activeLease['is_overdue'] == true ||
                          _activeLease['rent_status'] == 'overdue' ||
                          simulatorStatus == 'overdue';
                      final hasRestructuring =
                          !isActiveLeaseOverdue && (isApproved || isPending);
                      final currentPart =
                          (latest?['current_part'] as int?) ?? 1;

                      final leaseList = _allLeases.isNotEmpty
                          ? _allLeases
                          : [widget.lease];

                      return Column(
                        children: [
                          SizedBox(
                            height: 236,
                            child: PageView.builder(
                              controller: _cardPageController,
                              physics: const BouncingScrollPhysics(),
                              itemCount: leaseList.length + 1,
                              onPageChanged: (idx) {
                                setState(() {
                                  _carouselPageIndex = idx;
                                  if (idx < leaseList.length) {
                                    _selectedLeaseIndex = idx;
                                    final selectedLease = leaseList[idx];
                                    widget.onLeaseChanged?.call(
                                      idx,
                                      selectedLease,
                                    );
                                    final tid =
                                        selectedLease['tenant_id'] ??
                                        selectedLease['id'];
                                    final int? newTenantId = tid is int
                                        ? tid
                                        : (tid != null
                                              ? int.tryParse(tid.toString())
                                              : null);
                                    _loadAccountFutures(newTenantId);
                                  }
                                });
                              },
                              itemBuilder: (context, index) {
                                if (index == leaseList.length) {
                                  return Padding(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 6,
                                    ),
                                    child: _buildExpandBusinessCard(isDark),
                                  );
                                }

                                final leaseItem = leaseList[index];
                                final bool isItemEviction =
                                    leaseItem['is_eviction'] == true;
                                final bool isItemTerminated =
                                    leaseItem['is_terminated'] == true ||
                                    (leaseItem['rent_status'] ?? '')
                                            .toString() ==
                                        'terminated';
                                final bool isItemLocked =
                                    isItemEviction || isItemTerminated;
                                final isBackendGrace =
                                    leaseItem['is_grace_period'] == true ||
                                    leaseItem['rent_status'] == 'grace_period';
                                final isBackendOverdue =
                                    leaseItem['is_overdue'] == true ||
                                    leaseItem['rent_status'] == 'overdue';
                                final isBackendSettled =
                                    leaseItem['is_settled'] == true ||
                                    leaseItem['rent_status'] == 'settled';
                                final isSimulatorActive =
                                    !isBackendGrace &&
                                    !isBackendOverdue &&
                                    (simulatorStatus == 'overdue' ||
                                        simulatorStatus == 'grace_period');

                                final bool isItemGrace = isSimulatorActive
                                    ? (simulatorStatus == 'grace_period')
                                    : isBackendGrace;
                                final bool isItemOverdue = isSimulatorActive
                                    ? (simulatorStatus == 'overdue')
                                    : isBackendOverdue;
                                final bool hasRestructuring =
                                    !isItemLocked && !isItemOverdue && (isApproved || isPending);

                                final int itemGraceDays = isSimulatorActive
                                    ? 5
                                    : ((leaseItem['grace_days_remaining'] as num?)?.toInt() ?? 5);

                                final double baseRent =
                                    (leaseItem['base_rent'] as num?)?.toDouble() ??
                                    (leaseItem['rent'] as num?)?.toDouble() ??
                                    0.0;
                                final double itemPenalty =
                                    (leaseItem['penalty_amount'] as num?)?.toDouble() ?? 0.0;
                                final double backendTotalDue =
                                    (leaseItem['total_due'] as num?)?.toDouble() ??
                                    (baseRent + itemPenalty);
                                final String monthsLabel =
                                    (leaseItem['outstanding_months_label'] ?? '').toString().trim();
                                final String formattedDueDate =
                                    (leaseItem['due_date_formatted'] ?? '').toString().trim();
                                final String itemDueLabel =
                                    (leaseItem['due_status_label'] ?? '').toString().trim();

                                final String dynamicDueDate;
                                if (hasRestructuring && isApproved && currentPart > 1) {
                                  dynamicDueDate = '15 Nov ${now.year}';
                                } else if (isItemGrace || isItemOverdue) {
                                  if (formattedDueDate.isNotEmpty) {
                                    dynamicDueDate = formattedDueDate;
                                  } else {
                                    final dueDay = (leaseItem['due_day'] as num?)?.toInt() ?? now.day;
                                    dynamicDueDate = '$dueDay $currentMonthName ${now.year}';
                                  }
                                } else if (itemDueLabel.isNotEmpty && !isSimulatorActive) {
                                  dynamicDueDate = itemDueLabel;
                                } else if (formattedDueDate.isNotEmpty) {
                                  dynamicDueDate = formattedDueDate;
                                } else {
                                  final dueDay = (leaseItem['due_day'] as num?)?.toInt() ?? now.day;
                                  dynamicDueDate = '$dueDay $currentMonthName ${now.year}';
                                }

                                final double itemEffectiveRent = isSimulatorActive
                                    ? (isItemOverdue
                                          ? (baseRent * 2 + (itemPenalty > 0 ? itemPenalty : 500))
                                          : (baseRent + itemPenalty))
                                    : ((isBackendSettled && backendTotalDue <= 0)
                                          ? baseRent
                                          : backendTotalDue);

                                final String? effectiveSubtext;
                                if (isItemOverdue) {
                                  effectiveSubtext = monthsLabel.isNotEmpty
                                      ? monthsLabel
                                      : (isSimulatorActive ? 'Simulated Overdue Balance' : 'Overdue Balance');
                                } else if (itemPenalty > 0) {
                                  effectiveSubtext =
                                      'Includes ₱${itemPenalty.toStringAsFixed(0)} Strike Penalty';
                                } else {
                                  effectiveSubtext = null;
                                }
                                final itemRent = baseRent;
                                final itemCompany =
                                    (leaseItem['business_name'] ?? '')
                                        .toString()
                                        .replaceAll(RegExp(r'\s*\([^)]*\)\s*$'), '')
                                        .trim();
                                final itemEffectiveCompany =
                                    itemCompany.isNotEmpty
                                    ? itemCompany
                                    : (_companyName.isNotEmpty
                                          ? _companyName.replaceAll(RegExp(r'\s*\([^)]*\)\s*$'), '').trim()
                                          : '');
                                final itemTenantName =
                                    itemEffectiveCompany.isNotEmpty
                                    ? itemEffectiveCompany
                                    : (_userName.isNotEmpty
                                          ? _userName.replaceAll(RegExp(r'\s*\([^)]*\)\s*$'), '').trim()
                                          : ((leaseItem['tenant_name']
                                                    ?.toString() ??
                                                'Tenant')
                                                .replaceAll(RegExp(r'\s*\([^)]*\)\s*$'), '')
                                                .trim()));
                                final itemPropertyName =
                                    (leaseItem['property_name'] ??
                                            'Commercial Stall')
                                        .toString();

                                return Padding(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 6,
                                  ),
                                  child: LuxuryRentCard(
                                    rentAmount: itemEffectiveRent,
                                    dueDate: dynamicDueDate,
                                    propertyName: itemPropertyName,
                                    tenantName: itemTenantName,
                                    activeArrangement: hasRestructuring
                                        ? latest
                                        : null,
                                    isOverdue: isItemOverdue,
                                    isGracePeriod: isItemGrace,
                                    graceDaysRemaining: itemGraceDays,
                                    overdueSubtext: effectiveSubtext,
                                    isEviction: isItemEviction,
                                    isTerminated: isItemTerminated,
                                    onCardTap: () {
                                      if (isItemLocked) {
                                        Navigator.of(context)
                                            .push(
                                              MaterialPageRoute(
                                                builder: (_) => ViolationsScreen(
                                                  lease: leaseItem,
                                                ),
                                              ),
                                            )
                                            .then((_) => _refresh());
                                        return;
                                      }
                                      final enriched = Map<String, dynamic>.from(leaseItem);
                                      enriched['due_date'] = dynamicDueDate;
                                      BuildingDetailsSheet.show(
                                        context,
                                        lease: enriched,
                                        onNavigateTab: widget.onNavigateTab,
                                      );
                                    },
                                    onArrangementTap: isItemLocked
                                        ? null
                                        : () {
                                            Navigator.of(context)
                                                .push(
                                                  MaterialPageRoute(
                                                    builder: (_) =>
                                                        PaymentArrangementScreen(
                                                          lease: leaseItem,
                                                          onArrangementSubmitted:
                                                              () => _refresh(),
                                                        ),
                                                  ),
                                                )
                                                .then((_) => _refresh());
                                          },
                                    onPayRent: () {
                                      if (isItemTerminated) {
                                        Navigator.of(context)
                                            .push(
                                              MaterialPageRoute(
                                                builder: (_) => ViolationsScreen(
                                                  lease: leaseItem,
                                                ),
                                              ),
                                            )
                                            .then((_) => _refresh());
                                        return;
                                      }
                                      Navigator.of(context)
                                          .push(
                                            MaterialPageRoute(
                                              builder: (_) => RentPaymentScreen(
                                                lease: leaseItem,
                                                rentAmount: itemRent,
                                                isOverdue: isItemOverdue || isItemEviction,
                                                activeArrangement:
                                                    hasRestructuring
                                                    ? latest
                                                    : null,
                                                onPaymentSubmitted: () =>
                                                    _refresh(),
                                              ),
                                            ),
                                          )
                                          .then((_) => _refresh());
                                    },
                                  ),
                                );
                              },
                            ),
                          ),

                          const SizedBox(height: 10),

                          // Animated Carousel Dots
                          Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: List.generate(leaseList.length + 1, (i) {
                              final isSelected = i == _carouselPageIndex;
                              return AnimatedContainer(
                                duration: const Duration(milliseconds: 250),
                                margin: const EdgeInsets.symmetric(
                                  horizontal: 3,
                                ),
                                width: isSelected ? 22 : 7,
                                height: 7,
                                decoration: BoxDecoration(
                                  color: isSelected
                                      ? const Color(0xFF1E6BFF)
                                      : (isDark
                                            ? const Color(0xFF334155)
                                            : const Color(0xFFCBD5E1)),
                                  borderRadius: BorderRadius.circular(4),
                                ),
                              );
                            }),
                          ),

                          // Active Restructuring Plan Strip (if any, hidden on evicted/terminated cards)
                          if (hasRestructuring && !isActiveLocked)
                            Padding(
                              padding: const EdgeInsets.fromLTRB(20, 10, 20, 0),
                              child: InkWell(
                                borderRadius: BorderRadius.circular(14),
                                onTap: () {
                                  Navigator.of(context)
                                      .push(
                                        MaterialPageRoute(
                                          builder: (_) =>
                                              PaymentArrangementScreen(
                                                lease: _activeLease,
                                                onArrangementSubmitted: () =>
                                                    _refresh(),
                                              ),
                                        ),
                                      )
                                      .then((_) => _refresh());
                                },
                                child: Container(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 14,
                                    vertical: 10,
                                  ),
                                  decoration: BoxDecoration(
                                    color: isApproved
                                        ? const Color(0xFFECFDF5)
                                        : const Color(0xFFFFFBEB),
                                    borderRadius: BorderRadius.circular(14),
                                    border: Border.all(
                                      color: isApproved
                                          ? const Color(0xFFA7F3D0)
                                          : const Color(0xFFFDE68A),
                                    ),
                                  ),
                                  child: Row(
                                    children: [
                                      Icon(
                                        isApproved
                                            ? Icons.check_circle_rounded
                                            : Icons.hourglass_top_rounded,
                                        size: 15,
                                        color: isApproved
                                            ? const Color(0xFF10B981)
                                            : const Color(0xFFD97706),
                                      ),
                                      const SizedBox(width: 8),
                                      Expanded(
                                        child: Text(
                                          isApproved
                                              ? 'Restructuring Active: $plan approved'
                                              : 'Restructuring: $plan under lessor review',
                                          style: TextStyle(
                                            fontSize: 12,
                                            fontWeight: FontWeight.w700,
                                            color: isApproved
                                                ? const Color(0xFF065F46)
                                                : const Color(0xFF92400E),
                                          ),
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                        ),
                                      ),
                                      const Icon(
                                        Icons.arrow_forward_ios_rounded,
                                        size: 11,
                                        color: Color(0xFF64748B),
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            ),
                        ],
                      );
                    },
                  );
                },
              ),

              if (isActiveLocked)
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 14, 20, 0),
                  child: EvictionLockPanel(
                    lease: _activeLease,
                    cleanBusinessLabel: cleanBizLabel,
                    onSwitchToCleanBusiness: (cleanLeaseIndex >= 0 &&
                            _cardPageController.hasClients)
                        ? () {
                            _cardPageController.animateToPage(
                              cleanLeaseIndex,
                              duration: const Duration(milliseconds: 350),
                              curve: Curves.easeOutCubic,
                            );
                          }
                        : null,
                    onProceedToPayment: () async {
                      await Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => RentPaymentScreen(
                            lease: _activeLease,
                            rentAmount: rentAmount,
                            isOverdue: _activeLease['is_overdue'] == true ||
                                (_activeLease['overdue_rent_amount'] as num? ?? 0) > 0,
                            onPaymentSubmitted: () => _refresh(),
                          ),
                        ),
                      );
                      _refresh();
                    },
                    onViewNotice: () async {
                      await Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => ViolationsScreen(lease: _activeLease),
                        ),
                      );
                      _refresh();
                    },
                    onRefresh: _refresh,
                  ),
                )
              else ...[
                // Inline Violation Strip (if any)
                FutureBuilder<List<Map<String, dynamic>>>(
                future: _violationsFuture,
                builder: (context, snapshot) {
                  final rawViolations = snapshot.data ?? [];
                  final activeTid = _activeTenantId;
                  final activeLessor = (_activeLease['lessor_name'] ?? '')
                      .toString()
                      .trim()
                      .toLowerCase();

                  final violations = rawViolations.where((v) {
                    if (v['tenant_id'] != null && activeTid != null) {
                      final int? vTid = v['tenant_id'] is int
                          ? v['tenant_id'] as int
                          : int.tryParse(v['tenant_id'].toString());
                      if (vTid != null && vTid != activeTid) return false;
                    }
                    if (v['lessor_name'] != null && activeLessor.isNotEmpty) {
                      final vLessor =
                          v['lessor_name'].toString().trim().toLowerCase();
                      if (vLessor.isNotEmpty && vLessor != activeLessor) {
                        return false;
                      }
                    }
                    return true;
                  }).toList();

                  final unpaidViolations = violations
                      .where((v) => v['is_paid'] != true && v['is_paid'] != 1)
                      .toList();
                  if (unpaidViolations.isEmpty) return const SizedBox.shrink();

                  final latest = unpaidViolations.first;
                  final strike = latest['strike'] is int
                      ? latest['strike'] as int
                      : int.tryParse(latest['strike']?.toString() ?? '1') ?? 1;
                  final fineAmt = (latest['fine_amount'] as num?)?.toDouble() ??
                      (strike >= 3 ? 1500.0 : (strike == 2 ? 500.0 : 0.0));
                  final isEviction = strike >= 3;

                  return Padding(
                    padding: const EdgeInsets.fromLTRB(20, 8, 20, 0),
                    child: InkWell(
                      borderRadius: BorderRadius.circular(14),
                      onTap: () async {
                        await Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => ViolationsScreen(lease: _activeLease),
                          ),
                        );
                        _refresh();
                      },
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 14,
                          vertical: 10,
                        ),
                        decoration: BoxDecoration(
                          color: isEviction
                              ? const Color(0xFFFEE2E2)
                              : const Color(0xFFEFF6FF),
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(
                            color: isEviction
                                ? const Color(0xFFFCA5A5)
                                : const Color(0xFFBFDBFE),
                          ),
                        ),
                        child: Row(
                          children: [
                            Icon(
                              isEviction
                                  ? Icons.gavel_rounded
                                  : Icons.info_outline_rounded,
                              size: 15,
                              color: isEviction
                                  ? AppColors.error
                                  : const Color(0xFF1E6BFF),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                isEviction
                                    ? 'Final Notice: Lease Default (View Notice)'
                                    : (fineAmt > 0
                                          ? 'Strike $strike Notice: ₱${_formatMoney(fineAmt)} Penalty Billed (View)'
                                          : 'Formal Notice: Strike $strike Infraction on Record (View)'),
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w700,
                                  color: isEviction
                                      ? const Color(0xFF991B1B)
                                      : const Color(0xFF1E40AF),
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                            const Icon(
                              Icons.arrow_forward_ios_rounded,
                              size: 11,
                              color: Color(0xFF64748B),
                            ),
                          ],
                        ),
                      ),
                    ),
                  );
                },
              ),

              const SizedBox(height: 18),

              // ================= QUICK ACTIONS ISLAND (4 BUTTONS) =================
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    vertical: 18,
                    horizontal: 8,
                  ),
                  decoration: BoxDecoration(
                    color: isDark ? const Color(0xFF121212) : Colors.white,
                    borderRadius: BorderRadius.circular(24),
                    border: isDark
                        ? Border.all(color: const Color(0xFF262626), width: 1)
                        : null,
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(
                          alpha: isDark ? 0.35 : 0.04,
                        ),
                        blurRadius: 16,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                    children: [
                      // 1. Payment Plan with Peso Sign
                      _buildIslandActionItem(
                        isDark: isDark,
                        iconWidget: Text(
                          '₱',
                          style: TextStyle(
                            fontSize: 21,
                            fontWeight: FontWeight.w800,
                            color: isDark
                                ? Colors.white
                                : const Color(0xFF111418),
                          ),
                        ),
                        label: 'Payment Plan',
                        onTap: () {
                          Navigator.of(context)
                              .push(
                                MaterialPageRoute(
                                  builder: (_) => PaymentArrangementScreen(
                                    lease: _activeLease,
                                    onArrangementSubmitted: () => _refresh(),
                                  ),
                                ),
                              )
                              .then((_) => _refresh());
                        },
                      ),

                      // 2. Applications with Assignment Icon (My Applications for business expansion)
                      _buildIslandActionItem(
                        isDark: isDark,
                        icon: Icons.assignment_outlined,
                        label: 'Applications',
                        onTap: () {
                          Navigator.of(context)
                              .push(
                                MaterialPageRoute(
                                  builder: (_) => MyApplicationsScreen(
                                    isTab: false,
                                    onLeaseMayHaveChanged: _refresh,
                                  ),
                                ),
                              )
                              .then((_) => _refresh());
                        },
                      ),

                      // 3. Violations with Warning Icon
                      _buildIslandActionItem(
                        isDark: isDark,
                        icon: Icons.warning_amber_rounded,
                        label: 'Violations',
                        onTap: () {
                          Navigator.of(context)
                              .push(
                                MaterialPageRoute(
                                  builder: (_) =>
                                      ViolationsScreen(lease: _activeLease),
                                ),
                              )
                              .then((_) => _refresh());
                        },
                      ),

                      // 4. Maintenance with build_circle_outlined Icon
                      _buildIslandActionItem(
                        isDark: isDark,
                        icon: Icons.build_circle_outlined,
                        label: 'Maintenance',
                        onTap: () {
                          Navigator.of(context)
                              .push(
                                MaterialPageRoute(
                                  builder: (_) =>
                                      TicketsListScreen(lease: _activeLease),
                                ),
                              )
                              .then((_) => _refresh());
                        },
                      ),
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 18),

              // ================= MANAGE EXPENSES / RECENT ACTIVITY =================
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: Container(
                  decoration: BoxDecoration(
                    color: isDark ? const Color(0xFF121212) : Colors.white,
                    borderRadius: BorderRadius.circular(24),
                    border: isDark
                        ? Border.all(color: const Color(0xFF262626), width: 1)
                        : null,
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(
                          alpha: isDark ? 0.35 : 0.04,
                        ),
                        blurRadius: 16,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Header Row: 'Tenant Updates' and 'View All'
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            'Tenant Updates',
                            style: TextStyle(
                              fontSize: 17,
                              fontWeight: FontWeight.w900,
                              color: isDark
                                  ? Colors.white
                                  : const Color(0xFF111418),
                              letterSpacing: -0.4,
                            ),
                          ),
                          InkWell(
                            onTap: () {
                              Navigator.of(context).push(
                                MaterialPageRoute(
                                  builder: (_) =>
                                      TicketsListScreen(lease: _activeLease),
                                ),
                              );
                            },
                            child: const Padding(
                              padding: EdgeInsets.symmetric(
                                horizontal: 4,
                                vertical: 2,
                              ),
                              child: Text(
                                'View All',
                                style: TextStyle(
                                  fontSize: 12.5,
                                  fontWeight: FontWeight.w700,
                                  color: Color(0xFF1E6BFF),
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 18),

                      // 1. Dynamic Monthly Stall Rent Row
                      Builder(builder: (context) {
                        final activeRemaining =
                            (_activeLease['active_cycle_remaining'] as num?)?.toDouble();
                        final totalDueRow =
                            (_activeLease['total_due'] as num?)?.toDouble() ??
                            activeRemaining ??
                            rentAmount;
                        final bool settledRow =
                            _activeLease['is_settled'] == true && totalDueRow <= 0;
                        final dueFormatted =
                            (_activeLease['due_date_formatted'] ?? '').toString().trim();
                        final dueLabel =
                            (_activeLease['due_status_label'] ?? '').toString().trim();
                        final dueText = settledRow
                            ? 'Paid for current period'
                            : (dueFormatted.isNotEmpty
                                  ? 'Due $dueFormatted${dueLabel.contains('Grace') ? ' ($dueLabel)' : ''}'
                                  : (dueLabel.isNotEmpty ? dueLabel : 'Current Cycle'));
                        final displayRowAmt = settledRow ? rentAmount : totalDueRow;

                        return _buildActivityRow(
                          isDark: isDark,
                          icon: Icons.receipt_long_rounded,
                          title: 'Monthly Stall Rent',
                          subtitle:
                              '₱${_formatMoney(displayRowAmt)} • $dueText',
                          trailing: settledRow
                              ? 'Paid'
                              : '₱${_formatMoney(displayRowAmt)}',
                          onTap: () {
                            Navigator.of(context)
                                .push(
                                  MaterialPageRoute(
                                    builder: (_) => RentPaymentScreen(
                                      lease: _activeLease,
                                      rentAmount: rentAmount,
                                      isOverdue: _activeLease['is_overdue'] == true ||
                                          _activeLease['rent_status'] == 'overdue',
                                      onPaymentSubmitted: () => _refresh(),
                                    ),
                                  ),
                                )
                                .then((_) => _refresh());
                          },
                        );
                      }),

                      // 2. Dynamic Maintenance Tickets from Backend
                      FutureBuilder<List<dynamic>>(
                        future: _ticketsFuture,
                        builder: (context, snapshot) {
                          final tickets = snapshot.data ?? [];
                          if (tickets.isEmpty) return const SizedBox.shrink();

                          return Column(
                            children: tickets.take(3).map((t) {
                              final ticket = t as Map<String, dynamic>;
                              return Column(
                                children: [
                                  Divider(
                                    color: isDark
                                        ? const Color(0xFF262626)
                                        : const Color(0xFFF3F4F6),
                                    height: 24,
                                  ),
                                  _buildTicketActivityRow(
                                    ticket,
                                    isDark: isDark,
                                  ),
                                ],
                              );
                            }).toList(),
                          );
                        },
                      ),

                      // 3. Dynamic Payment Restructuring Arrangement from Backend (if any)
                      FutureBuilder<List<dynamic>>(
                        future: _arrangementsFuture,
                        builder: (context, snapshot) {
                          final reqs = snapshot.data ?? [];
                          if (reqs.isEmpty) return const SizedBox.shrink();

                          final latest = reqs.first as Map<String, dynamic>;
                          final status = (latest['status'] ?? 'pending')
                              .toString();
                          final isApproved = status.toLowerCase() == 'approved';
                          final partAmount = (latest['amount'] as num?)?.toDouble() ?? (rentAmount / 2);
                          final currentPart = (latest['current_part'] as int?) ?? 1;
                          final partDueDate = DateTime(now.year, now.month + (currentPart > 1 ? 1 : 0), 15);
                          final partDueStr = _formatDate(partDueDate);

                          return Column(
                            children: [
                              Divider(
                                color: isDark
                                    ? const Color(0xFF262626)
                                    : const Color(0xFFF3F4F6),
                                height: 24,
                              ),
                              _buildActivityRow(
                                isDark: isDark,
                                icon: isApproved
                                    ? Icons.verified_rounded
                                    : Icons.hourglass_top_rounded,
                                title: 'Restructuring Payment',
                                subtitle: '₱${_formatMoney(partAmount)} Due • $partDueStr',
                                trailing: isApproved ? 'Active' : 'Pending',
                                onTap: () {
                                  Navigator.of(context)
                                      .push(
                                        MaterialPageRoute(
                                          builder: (_) =>
                                              PaymentArrangementScreen(
                                                lease: _activeLease,
                                                onArrangementSubmitted: () =>
                                                    _refresh(),
                                              ),
                                        ),
                                      )
                                      .then((_) => _refresh());
                                },
                              ),
                            ],
                          );
                        },
                      ),

                      // 4. Dynamic Infraction Notice from Backend (if any)
                      FutureBuilder<List<Map<String, dynamic>>>(
                        future: _violationsFuture,
                        builder: (context, snapshot) {
                          final violations = snapshot.data ?? [];
                          if (violations.isEmpty) {
                            return const SizedBox.shrink();
                          }

                          final latest = violations.first;
                          final strike = latest['strike']?.toString() ?? '1';
                          final fine = (latest['fine_amount'] as num?)?.toDouble() ?? 500.0;
                          final dateIssued = _formatDate(latest['created_at'] ?? latest['issued_at'] ?? now);

                          return Column(
                            children: [
                              Divider(
                                color: isDark
                                    ? const Color(0xFF262626)
                                    : const Color(0xFFF3F4F6),
                                height: 24,
                              ),
                              _buildActivityRow(
                                isDark: isDark,
                                icon: Icons.gavel_rounded,
                                title: 'Notice of Infraction (Strike $strike)',
                                subtitle: '₱${_formatMoney(fine)} Billed • $dateIssued',
                                trailing: 'Notice',
                                onTap: () async {
                                  await Navigator.of(context).push(
                                    MaterialPageRoute(
                                      builder: (_) => ViolationsScreen(lease: _activeLease),
                                    ),
                                  );
                                  _refresh();
                                },
                              ),
                            ],
                          );
                        },
                      ),
                    ],
                  ),
                ),
              ),
              ],

              // Bottom padding for dock
              const SizedBox(height: 100),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildIslandActionItem({
    IconData? icon,
    Widget? iconWidget,
    required String label,
    required VoidCallback onTap,
    bool isDark = false,
  }) {
    return Expanded(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 2, vertical: 2),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 50,
                height: 50,
                decoration: BoxDecoration(
                  color: isDark
                      ? const Color(0xFF1E1E1E)
                      : const Color(0xFFF3F4F6),
                  shape: BoxShape.circle,
                  border: isDark
                      ? Border.all(color: const Color(0xFF262626), width: 1)
                      : null,
                ),
                child: Center(
                  child:
                      iconWidget ??
                      Icon(
                        icon,
                        color: isDark ? Colors.white : const Color(0xFF111418),
                        size: 21,
                      ),
                ),
              ),
              const SizedBox(height: 7),
              FittedBox(
                fit: BoxFit.scaleDown,
                child: Text(
                  label,
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                    color: isDark
                        ? const Color(0xFF94A3B8)
                        : const Color(0xFF4B5563),
                    letterSpacing: -0.1,
                  ),
                  textAlign: TextAlign.center,
                  maxLines: 1,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildActivityRow({
    required IconData icon,
    required String title,
    required String subtitle,
    required String trailing,
    VoidCallback? onTap,
    bool isDark = false,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 2),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: isDark
                    ? const Color(0xFF1E1E1E)
                    : const Color(0xFFF3F4F6),
                borderRadius: BorderRadius.circular(13),
                border: isDark
                    ? Border.all(color: const Color(0xFF262626), width: 0.8)
                    : null,
              ),
              child: Icon(
                icon,
                color: isDark
                    ? const Color(0xFF60A5FA)
                    : const Color(0xFF111418),
                size: 20,
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: isDark ? Colors.white : const Color(0xFF111418),
                      letterSpacing: -0.1,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: TextStyle(
                      fontSize: 11.5,
                      fontWeight: FontWeight.w400,
                      color: isDark
                          ? const Color(0xFF94A3B8)
                          : const Color(0xFF8A92A0),
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
                trailing,
                style: TextStyle(
                  fontSize: 14.5,
                  fontWeight: FontWeight.w800,
                  color: isDark ? Colors.white : const Color(0xFF111418),
                  letterSpacing: -0.3,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildTicketActivityRow(
    Map<String, dynamic> ticket, {
    bool isDark = false,
  }) {
    String title = ticket['title']?.toString().trim() ?? '';
    if (title.startsWith('[')) {
      final closeIdx = title.indexOf(']');
      if (closeIdx > 1) {
        final inside = title.substring(1, closeIdx);
        title = inside.contains(' - ')
            ? inside.split(' - ').first.trim()
            : inside.trim();
      }
    }
    if (title.isEmpty) {
      final cat = ticket['category']?.toString() ?? 'General';
      title = cat.isNotEmpty
          ? (cat[0].toUpperCase() + cat.substring(1))
          : 'Maintenance Request';
    }
    final category = ticket['category']?.toString() ?? 'General';
    final stageNum = (ticket['stage'] as num?)?.toInt() ?? 0;
    final stageName = stageNum < kTicketStages.length
        ? kTicketStages[stageNum]
        : 'In Progress';
    final icon = _getReportCategoryIcon(category.isNotEmpty ? category : title);

    final isResolved = stageNum >= kTicketStages.length - 1;

    final submittedDateStr = _formatDate(ticket['created_at']);
    final detailsSubtitle = stageName.trim().toLowerCase() == 'submitted'
        ? (submittedDateStr.isNotEmpty ? 'Submitted $submittedDateStr' : 'Submitted')
        : (submittedDateStr.isNotEmpty ? '$stageName • Submitted $submittedDateStr' : stageName);

    return _buildActivityRow(
      isDark: isDark,
      icon: icon,
      title: title,
      subtitle: detailsSubtitle,
      trailing: isResolved ? 'Resolved' : 'In Progress',
      onTap: () {
        Navigator.of(context)
            .push(
              MaterialPageRoute(
                builder: (_) => TicketDetailScreen(ticket: ticket),
              ),
            )
            .then((_) => _refresh());
      },
    );
  }
}
