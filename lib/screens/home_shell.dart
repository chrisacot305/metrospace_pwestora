import 'package:flutter/material.dart';
import '../theme.dart';
import '../services/api_service.dart';
import '../widgets/scooped_nav_bar.dart';
import '../widgets/loading.dart';
import '../widgets/eviction_lock_panel.dart';
import 'browse_screen.dart';
import 'my_applications_screen.dart';
import 'dashboard_screen.dart';
import 'schedule_screen.dart';
import 'ticket_create_screen.dart';
import 'profile_screen.dart';
import 'rent_payment_screen.dart';
import 'violations_screen.dart';
import 'login_screen.dart';

class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _currentIndex = 0;
  int _applicantAppsTick = 0;
  late Future<List<Map<String, dynamic>>> _leasesFuture;
  List<Map<String, dynamic>> _allLeases = [];
  int _activeLeaseIndex = 0;

  @override
  void initState() {
    super.initState();
    _leasesFuture = _fetchAndStoreLeases();
  }

  Future<List<Map<String, dynamic>>> _fetchAndStoreLeases() async {
    try {
      final leases = await ApiService.fetchAllActiveLeases();
      if (mounted) {
        setState(() {
          _allLeases = leases;
          if (_activeLeaseIndex >= _allLeases.length) {
            _activeLeaseIndex = 0;
          }
        });
      }
      return leases;
    } catch (_) {
      return _allLeases;
    }
  }

  Future<void> _recheckLease() async {
    final leases = await _fetchAndStoreLeases();
    if (!mounted) return;
    setState(() {
      _leasesFuture = Future.value(leases);
    });
  }

  void _onBusinessSwiped(int newIndex, Map<String, dynamic> lease) {
    if (_activeLeaseIndex != newIndex) {
      setState(() {
        _activeLeaseIndex = newIndex;
      });
    }
  }

  Map<String, dynamic>? get _activeLease {
    if (_allLeases.isNotEmpty && _activeLeaseIndex < _allLeases.length) {
      return _allLeases[_activeLeaseIndex];
    }
    return null;
  }

  void _openReportFlow(Map<String, dynamic>? lease) async {
    final created = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => TicketCreateScreen(lease: lease),
      ),
    );
    if (created == true) {
      _recheckLease();
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<Map<String, dynamic>>>(
      future: _leasesFuture,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          final isDark = Theme.of(context).brightness == Brightness.dark;
          return Scaffold(
            backgroundColor: isDark ? const Color(0xFF000000) : AppColors.bg,
            body: const Center(
              child: LogoLoadingIndicator(
                size: 88,
                strokeWidth: 3.8,
              ),
            ),
          );
        }

        final leases = _allLeases.isNotEmpty
            ? _allLeases
            : (snapshot.data ?? const <Map<String, dynamic>>[]);
        final hasActiveLease = leases.isNotEmpty;
        final currentLease = _activeLease ?? (hasActiveLease ? leases.first : null);
        final bool hasCleanLease = leases.any((l) => !isLeaseEvictionLocked(l));
        final bool isSelectedLocked = isLeaseEvictionLocked(currentLease);

        // Full-screen lock only when the user has NO clean business to switch to.
        // If another business is clean, keep Dashboard carousel open and lock per card.
        if (hasActiveLease && !hasCleanLease && currentLease != null) {
          return _buildEvictionLockScreen(currentLease, allLeases: leases);
        }

        // Build screens based on user state
        final List<Widget> screens = hasActiveLease
            ? [
                DashboardScreen(
                  lease: currentLease!,
                  allLeases: _allLeases,
                  activeLeaseIndex: _activeLeaseIndex,
                  onLeaseChanged: _onBusinessSwiped,
                  onRefresh: _recheckLease,
                  onNavigateTab: (idx) {
                    if (isSelectedLocked && idx != 0) {
                      _showLockedBusinessNotice();
                      return;
                    }
                    setState(() => _currentIndex = idx);
                  },
                ),
                ScheduleScreen(
                  key: ValueKey('schedule_${currentLease['property_id'] ?? currentLease['id'] ?? _activeLeaseIndex}'),
                  lease: currentLease,
                ),
                BrowseScreen(onLeaseMayHaveChanged: _recheckLease),
                ProfileScreen(
                  key: ValueKey('profile_${currentLease['property_id'] ?? currentLease['id'] ?? _activeLeaseIndex}'),
                  lease: currentLease,
                  onNavigateTab: (idx) {
                    if (isSelectedLocked && idx != 0) {
                      _showLockedBusinessNotice();
                      return;
                    }
                    setState(() => _currentIndex = idx);
                  },
                  onLeaseMayHaveChanged: _recheckLease,
                ),
              ]
            : [
                BrowseScreen(onLeaseMayHaveChanged: _recheckLease),
                MyApplicationsScreen(
                  key: ValueKey('apps_tab_$_applicantAppsTick'),
                  onLeaseMayHaveChanged: _recheckLease,
                  isTab: true,
                ),
                ProfileScreen(
                  lease: null,
                  onNavigateTab: (idx) => setState(() => _currentIndex = idx),
                  onLeaseMayHaveChanged: _recheckLease,
                ),
              ];

        final clampedIndex = _currentIndex.clamp(0, screens.length - 1);
        final safeIndex = (hasActiveLease && isSelectedLocked) ? 0 : clampedIndex;
        final isKeyboardOpen = MediaQuery.of(context).viewInsets.bottom > 0;
        final hideDock = isKeyboardOpen;

        return Scaffold(
          extendBody: true,
          resizeToAvoidBottomInset: false,
          body: IndexedStack(
            index: safeIndex,
            children: screens,
          ),
          bottomNavigationBar: hideDock
              ? null
              : (hasActiveLease
                  ? _buildTenantDock(
                      currentLease!,
                      safeIndex,
                      isLocked: isSelectedLocked,
                    )
                  : _buildApplicantDock(safeIndex)),
        );
      },
    );
  }

  void _showLockedBusinessNotice() {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: const Color(0xFF991B1B),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          content: const Text(
            'Locked for this evicted business. Settle its balance or swipe the card above to switch to a clean business.',
            style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
          ),
        ),
      );
  }

  Future<void> _signOutFromLock() async {
    await ApiService.clearSession();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
      (route) => false,
    );
  }

  /// Strike 3 / Eviction Lock when no clean business is available on the account.
  Widget _buildEvictionLockScreen(
    Map<String, dynamic> lease, {
    List<Map<String, dynamic>> allLeases = const [],
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final double baseRent = (lease['base_rent'] as num?)?.toDouble() ??
        (lease['rent'] as num?)?.toDouble() ??
        0.0;

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF0A0A0A) : const Color(0xFFFEF2F2),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: AppColors.error.withValues(alpha: 0.14),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: const Icon(
                          Icons.gavel_rounded,
                          color: AppColors.error,
                          size: 20,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Text(
                        'Pwestora Account Lock',
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                          color: isDark ? Colors.white : AppColors.midnightNavy,
                        ),
                      ),
                    ],
                  ),
                  TextButton.icon(
                    onPressed: _signOutFromLock,
                    icon: const Icon(Icons.logout_rounded, size: 17),
                    label: const Text('Sign Out'),
                    style: TextButton.styleFrom(
                      foregroundColor: isDark ? Colors.white70 : AppColors.ink700,
                    ),
                  ),
                ],
              ),
              if (allLeases.length > 1) ...[
                const SizedBox(height: 14),
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: List.generate(allLeases.length, (i) {
                      final isSelected = i == _activeLeaseIndex;
                      final label = leaseDisplayLabel(allLeases[i]);
                      return Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: ChoiceChip(
                          selected: isSelected,
                          onSelected: (_) {
                            if (_activeLeaseIndex != i) {
                              setState(() => _activeLeaseIndex = i);
                            }
                          },
                          label: Text(label),
                          selectedColor: AppColors.error,
                          labelStyle: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                            color: isSelected
                                ? Colors.white
                                : (isDark ? Colors.white70 : AppColors.ink700),
                          ),
                        ),
                      );
                    }),
                  ),
                ),
              ],
              const SizedBox(height: 18),
              EvictionLockPanel(
                lease: lease,
                onProceedToPayment: () async {
                  await Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => RentPaymentScreen(
                        lease: lease,
                        rentAmount: baseRent,
                        isOverdue: lease['is_overdue'] == true ||
                            (lease['overdue_rent_amount'] as num? ?? 0) > 0,
                        onPaymentSubmitted: _recheckLease,
                      ),
                    ),
                  );
                  _recheckLease();
                },
                onViewNotice: () async {
                  await Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => ViolationsScreen(lease: lease),
                    ),
                  );
                  _recheckLease();
                },
                onRefresh: _recheckLease,
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// Bottom Dock for Active Tenants: Home, Schedule, (+) Report, Chat, Me
  /// Featuring a smooth scooped cradle with elevated Report button and Navy Blue active/hover states.
  Widget _buildTenantDock(
    Map<String, dynamic> lease,
    int currentIndex, {
    bool isLocked = false,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final scaffoldBg = isDark ? const Color(0xFF000000) : AppColors.bg;
    final dockBg = isDark ? const Color(0xFF121212) : Colors.white;
    final dockBorder = isDark ? const Color(0xFF262626) : AppColors.border;
    final dockShadow = isDark ? Colors.black : const Color(0xFF0A1832);
    final reportLabelColor = isDark ? Colors.white : const Color(0xFF0A1832);

    return SafeArea(
      top: false,
      child: Container(
        padding: const EdgeInsets.only(left: 16, right: 16, bottom: 12, top: 4),
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              scaffoldBg.withValues(alpha: 0.0),
              scaffoldBg.withValues(alpha: 0.95),
              scaffoldBg,
            ],
          ),
        ),
        child: Align(
          alignment: Alignment.bottomCenter,
          heightFactor: 1.0,
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 520),
            child: SizedBox(
              height: 84,
              child: Stack(
                clipBehavior: Clip.none,
                children: [
                  // 1. Custom sculpted white cradle bar
                  Positioned.fill(
                    child: CustomPaint(
                      painter: ScoopedCradlePainter(
                        color: dockBg,
                        shadowColor: dockShadow,
                        borderColor: dockBorder,
                        cornerRadius: 32,
                        cradleWidth: 86,
                        cradleDepth: 34,
                        barTop: 18,
                      ),
                    ),
                  ),
                  // 2. Navigation items
                  Positioned(
                    top: 18,
                    left: 6,
                    right: 6,
                    bottom: 0,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                      crossAxisAlignment: CrossAxisAlignment.center,
                      children: [
                        Expanded(
                          child: ScoopedNavItem(
                            index: 0,
                            currentIndex: currentIndex,
                            icon: Icons.home_rounded,
                            outlineIcon: Icons.home_outlined,
                            label: 'Home',
                            onTap: (idx) => setState(() => _currentIndex = idx),
                          ),
                        ),
                        Expanded(
                          child: ScoopedNavItem(
                            index: 1,
                            currentIndex: currentIndex,
                            icon: Icons.calendar_month_rounded,
                            outlineIcon: Icons.calendar_month_outlined,
                            label: 'Schedule',
                            isLocked: isLocked,
                            onTap: (idx) {
                              if (isLocked) {
                                _showLockedBusinessNotice();
                                return;
                              }
                              setState(() => _currentIndex = idx);
                            },
                          ),
                        ),
                        // Center Report item aligned on the exact same baseline as other nav items
                        SizedBox(
                          width: 68,
                          child: GestureDetector(
                            behavior: HitTestBehavior.opaque,
                            onTap: () => isLocked ? _showLockedBusinessNotice() : _openReportFlow(lease),
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 6),
                              color: Colors.transparent,
                              child: Column(
                                mainAxisSize: MainAxisSize.min,
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  const SizedBox(height: 23), // Matches 23px Icon height in ScoopedNavItem
                                  const SizedBox(height: 3),  // Matches 3px gap in ScoopedNavItem
                                  Text(
                                    'Report',
                                    style: TextStyle(
                                      fontSize: 10.5,
                                      fontWeight: FontWeight.w700,
                                      color: reportLabelColor.withValues(alpha: isLocked ? 0.42 : 1.0),
                                      letterSpacing: -0.2,
                                    ),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                        Expanded(
                          child: ScoopedNavItem(
                            index: 2,
                            currentIndex: currentIndex,
                            icon: Icons.explore_rounded,
                            outlineIcon: Icons.explore_outlined,
                            label: 'Explore',
                            isLocked: isLocked,
                            onTap: (idx) {
                              if (isLocked) {
                                _showLockedBusinessNotice();
                                return;
                              }
                              setState(() => _currentIndex = idx);
                            },
                          ),
                        ),
                        Expanded(
                          child: ScoopedNavItem(
                            index: 3,
                            currentIndex: currentIndex,
                            icon: Icons.person_rounded,
                            outlineIcon: Icons.person_outline_rounded,
                            label: 'Me',
                            isLocked: isLocked,
                            onTap: (idx) {
                              if (isLocked) {
                                _showLockedBusinessNotice();
                                return;
                              }
                              setState(() => _currentIndex = idx);
                            },
                          ),
                        ),
                      ],
                    ),
                  ),
                  // 3. Elevated circular button resting in the scooped cradle
                  Positioned(
                    top: 5,
                    left: 0,
                    right: 0,
                    child: Center(
                      child: GestureDetector(
                        behavior: HitTestBehavior.opaque,
                        onTap: () => isLocked ? _showLockedBusinessNotice() : _openReportFlow(lease),
                        child: Opacity(
                          opacity: isLocked ? 0.55 : 1.0,
                          child: Container(
                            width: 44,
                            height: 44,
                            decoration: BoxDecoration(
                              gradient: LinearGradient(
                                colors: isLocked
                                    ? const [
                                        Color(0xFF6B7280),
                                        Color(0xFF4B5563),
                                      ]
                                    : const [
                                        Color(0xFF21447D),
                                        Color(0xFF142C56),
                                      ],
                                begin: Alignment.topLeft,
                                end: Alignment.bottomRight,
                              ),
                              shape: BoxShape.circle,
                              border: Border.all(
                                color: (isLocked ? const Color(0xFF9CA3AF) : const Color(0xFF1E6BFF))
                                    .withValues(alpha: 0.45),
                                width: 1.2,
                              ),
                              boxShadow: [
                                BoxShadow(
                                  color: (isLocked ? Colors.black : const Color(0xFF1E6BFF))
                                      .withValues(alpha: 0.25),
                                  blurRadius: 10,
                                  offset: const Offset(0, 3),
                                ),
                                BoxShadow(
                                  color: const Color(0xFF0A1832).withValues(alpha: 0.20),
                                  blurRadius: 8,
                                  offset: const Offset(0, 4),
                                ),
                              ],
                            ),
                            child: Icon(
                              isLocked ? Icons.lock_rounded : Icons.add_rounded,
                              color: Colors.white,
                              size: isLocked ? 20 : 26,
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  /// Bottom Dock for Prospective Tenants / Applicants: Browse, Applications, Me
  Widget _buildApplicantDock(int currentIndex) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final scaffoldBg = isDark ? const Color(0xFF000000) : AppColors.bg;
    final dockBg = isDark ? const Color(0xFF121212) : Colors.white;
    final dockBorder = isDark ? const Color(0xFF262626) : AppColors.border;
    final dockShadow = isDark ? Colors.black : const Color(0xFF0A1832);

    return SafeArea(
      top: false,
      child: Container(
        padding: const EdgeInsets.only(left: 16, right: 16, bottom: 12, top: 4),
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              scaffoldBg.withValues(alpha: 0.0),
              scaffoldBg.withValues(alpha: 0.95),
              scaffoldBg,
            ],
          ),
        ),
        child: Align(
          alignment: Alignment.bottomCenter,
          heightFactor: 1.0,
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 500),
            child: Container(
              height: 68,
              decoration: BoxDecoration(
                color: dockBg,
                borderRadius: BorderRadius.circular(34),
                border: Border.all(color: dockBorder.withValues(alpha: 0.8), width: 1),
                boxShadow: [
                  BoxShadow(
                    color: dockShadow.withValues(alpha: isDark ? 0.3 : 0.08),
                    blurRadius: 20,
                    offset: const Offset(0, 8),
                  ),
                  BoxShadow(
                    color: dockShadow.withValues(alpha: isDark ? 0.15 : 0.03),
                    blurRadius: 6,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  Expanded(
                    child: ScoopedNavItem(
                      index: 0,
                      currentIndex: currentIndex,
                      icon: Icons.explore_rounded,
                      outlineIcon: Icons.explore_outlined,
                      label: 'Explore',
                      onTap: (idx) => setState(() => _currentIndex = idx),
                    ),
                  ),
                  Expanded(
                    child: ScoopedNavItem(
                      index: 1,
                      currentIndex: currentIndex,
                      icon: Icons.assignment_rounded,
                      outlineIcon: Icons.assignment_outlined,
                      label: 'Applications',
                      onTap: (idx) => setState(() {
                        _currentIndex = idx;
                        _applicantAppsTick++;
                      }),
                    ),
                  ),
                  Expanded(
                    child: ScoopedNavItem(
                      index: 2,
                      currentIndex: currentIndex,
                      icon: Icons.person_rounded,
                      outlineIcon: Icons.person_outline_rounded,
                      label: 'Me',
                      onTap: (idx) => setState(() => _currentIndex = idx),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
