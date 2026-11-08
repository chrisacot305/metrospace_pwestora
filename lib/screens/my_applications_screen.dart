import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../theme.dart';
import '../services/api_service.dart';
import '../widgets/app_error_view.dart';
import 'lease_contract_screen.dart';
import 'home_shell.dart';

/// Safely reads a number whether the API sent it as a JSON number or a string.
num _asNum(dynamic v) {
  if (v is num) return v;
  return num.tryParse(v?.toString() ?? '') ?? 0;
}

class MyApplicationsScreen extends StatefulWidget {
  final Future<void> Function()? onLeaseMayHaveChanged;
  final bool isTab;
  const MyApplicationsScreen({
    super.key,
    this.onLeaseMayHaveChanged,
    this.isTab = false,
  });

  @override
  State<MyApplicationsScreen> createState() => _MyApplicationsScreenState();
}

class _MyApplicationsScreenState extends State<MyApplicationsScreen> {
  static final Set<String> _globallyDismissedAppIds = {};
  late Future<List<dynamic>> _future;
  bool _hasShownCelebrationDialog = false;

  @override
  void initState() {
    super.initState();
    _future = ApiService.fetchMyApplications();
  }

  Future<void> _refresh() async {
    setState(() {
      _future = ApiService.fetchMyApplications();
    });
    await _future;
    if (widget.onLeaseMayHaveChanged != null) {
      await widget.onLeaseMayHaveChanged!();
    }
  }

  Future<void> _checkAndShowCelebration(
    BuildContext context,
    Map<String, dynamic> app,
  ) async {
    // If not in applicant tab mode (e.g. opened from tenant dashboard), never show
    if (!widget.isTab || _hasShownCelebrationDialog) return;

    final appId = (app['id'] ?? app['application_id'] ?? '').toString();
    if (appId.isNotEmpty && _globallyDismissedAppIds.contains(appId)) return;

    // Check if user already has an active lease / is already in tenant view
    try {
      final lease = await ApiService.fetchLeaseStatus();
      if (lease != null) {
        if (appId.isNotEmpty) {
          _globallyDismissedAppIds.add(appId);
          final prefs = await SharedPreferences.getInstance();
          await prefs.setBool('seen_celebration_$appId', true);
        }
        return;
      }
    } catch (_) {}

    // Check SharedPreferences if already seen / dismissed in past sessions
    if (appId.isNotEmpty) {
      try {
        final prefs = await SharedPreferences.getInstance();
        if (prefs.getBool('seen_celebration_$appId') == true) {
          _globallyDismissedAppIds.add(appId);
          return;
        }
      } catch (_) {}
    }

    if (!mounted || _hasShownCelebrationDialog) return;
    _hasShownCelebrationDialog = true;
    if (appId.isNotEmpty) {
      _globallyDismissedAppIds.add(appId);
      try {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setBool('seen_celebration_$appId', true);
      } catch (_) {}
    }

    if (!mounted || !context.mounted) return;
    _showAcceptanceCelebrationModal(context, app, appId);
  }

  void _showAcceptanceCelebrationModal(
    BuildContext context,
    Map<String, dynamic> app,
    String appId,
  ) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final propertyName = app['property_name']?.toString() ?? 'Commercial Space';
    final rent = app['rent'] != null ? '₱${app['rent']} / mo' : '';
    final businessName = app['business_name']?.toString() ?? '';

    showDialog(
      context: context,
      barrierDismissible: true,
      builder: (dialogCtx) {
        return Dialog(
          backgroundColor: Colors.transparent,
          insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
          child: Container(
            constraints: const BoxConstraints(maxWidth: 420),
            padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 24),
            decoration: BoxDecoration(
              color: isDark ? const Color(0xFF11223F) : Colors.white,
              borderRadius: BorderRadius.circular(26),
              border: Border.all(
                color: isDark ? const Color(0xFF1E3A5F) : AppColors.border,
                width: 1.2,
              ),
              boxShadow: [
                BoxShadow(
                  color: isDark
                      ? Colors.black.withValues(alpha: 0.6)
                      : const Color(0xFF0A1832).withValues(alpha: 0.15),
                  blurRadius: 28,
                  offset: const Offset(0, 10),
                ),
              ],
            ),
            child: SingleChildScrollView(
              physics: const BouncingScrollPhysics(),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  // Luminous Emerald Icon
                  Container(
                    width: 64,
                    height: 64,
                    decoration: BoxDecoration(
                      color: AppColors.success.withValues(alpha: isDark ? 0.2 : 0.12),
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: AppColors.success.withValues(alpha: isDark ? 0.45 : 0.25),
                        width: 2,
                      ),
                    ),
                    child: const Center(
                      child: Icon(
                        Icons.verified_rounded,
                        color: AppColors.success,
                        size: 34,
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),

                  // Status Pill
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                    decoration: BoxDecoration(
                      color: AppColors.success.withValues(alpha: isDark ? 0.2 : 0.1),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: const Text(
                      'OFFICIALLY ACCEPTED',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                        color: AppColors.success,
                        letterSpacing: 0.8,
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),

                  // Headline
                  Text(
                    'Congratulations!\nYou Have Been Accepted',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w900,
                      height: 1.25,
                      letterSpacing: -0.4,
                      color: isDark ? Colors.white : AppColors.ink900,
                    ),
                  ),
                  const SizedBox(height: 8),

                  // Subtitle
                  Text(
                    'Your application has been approved by the lessor. Your commercial space is officially ready for you!',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 13,
                      height: 1.4,
                      color: isDark ? AppColors.ink300 : AppColors.ink500,
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Property Preview Tile
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: isDark ? const Color(0xFF0A193F) : const Color(0xFFF8FAFC),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(
                        color: isDark ? const Color(0xFF1E3A5F) : const Color(0xFFE2E8F0),
                      ),
                    ),
                    child: Row(
                      children: [
                        Container(
                          width: 40,
                          height: 40,
                          decoration: BoxDecoration(
                            color: AppColors.electricBlue.withValues(alpha: isDark ? 0.25 : 0.1),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: const Icon(
                            Icons.storefront_rounded,
                            color: AppColors.electricBlue,
                            size: 20,
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                propertyName,
                                style: TextStyle(
                                  fontSize: 13.5,
                                  fontWeight: FontWeight.w800,
                                  color: isDark ? Colors.white : AppColors.ink900,
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                              if (businessName.isNotEmpty) ...[
                                const SizedBox(height: 1),
                                Text(
                                  businessName,
                                  style: TextStyle(
                                    fontSize: 11.5,
                                    color: isDark ? AppColors.ink400 : AppColors.ink500,
                                  ),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ],
                            ],
                          ),
                        ),
                        if (rent.isNotEmpty)
                          Text(
                            rent,
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w800,
                              color: AppColors.success,
                            ),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Direct Link to Dashboard Action Button
                  Container(
                    width: double.infinity,
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [Color(0xFF1D4ED8), Color(0xFF2563EB)],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(14),
                      boxShadow: [
                        BoxShadow(
                          color: const Color(0xFF2563EB).withValues(alpha: 0.35),
                          blurRadius: 12,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    child: Material(
                      color: Colors.transparent,
                      child: InkWell(
                        borderRadius: BorderRadius.circular(14),
                        onTap: () async {
                          Navigator.of(dialogCtx).pop();
                          if (appId.isNotEmpty) {
                            _globallyDismissedAppIds.add(appId);
                            try {
                              final prefs = await SharedPreferences.getInstance();
                              await prefs.setBool('seen_celebration_$appId', true);
                              await prefs.setBool('tenant_view_activated_$appId', true);
                            } catch (_) {}
                          }
                          if (widget.onLeaseMayHaveChanged != null) {
                            await widget.onLeaseMayHaveChanged!();
                          }
                          await ApiService.fetchLeaseStatus();
                          if (!context.mounted) return;
                          Navigator.of(context).pushAndRemoveUntil(
                            MaterialPageRoute(builder: (_) => const HomeShell()),
                            (route) => false,
                          );
                        },
                        child: const Padding(
                          padding: EdgeInsets.symmetric(vertical: 14),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(Icons.dashboard_customize_rounded, color: Colors.white, size: 18),
                              SizedBox(width: 8),
                              Text(
                                'Direct to Dashboard',
                                style: TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w800,
                                  fontSize: 14,
                                ),
                              ),
                              SizedBox(width: 6),
                              Icon(Icons.arrow_forward_rounded, color: Colors.white, size: 16),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),

                  // Polite Instructions Box
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: isDark ? const Color(0xFF0F1E36) : const Color(0xFFF1F5F9),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: isDark ? const Color(0xFF1E3A5F) : const Color(0xFFE2E8F0),
                      ),
                    ),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(
                          Icons.info_outline_rounded,
                          size: 16,
                          color: isDark ? const Color(0xFF60A5FA) : AppColors.electricBlue,
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            'Instructions: Tap "Direct to Dashboard" to open your tenant dashboard. If your view doesn\'t update right away, please exit and reopen the app (or restart) to refresh your account.',
                            style: TextStyle(
                              fontSize: 11.5,
                              height: 1.35,
                              color: isDark ? AppColors.ink300 : AppColors.ink700,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),

                  // Secondary Action / Dismiss
                  TextButton(
                    onPressed: () {
                      Navigator.of(dialogCtx).pop();
                      if (appId.isNotEmpty) {
                        _globallyDismissedAppIds.add(appId);
                        SharedPreferences.getInstance().then((prefs) {
                          prefs.setBool('seen_celebration_$appId', true);
                        }).catchError((_) {});
                      }
                    },
                    child: Text(
                      'View Application Details',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: isDark ? AppColors.ink300 : AppColors.ink500,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    ).then((_) async {
      if (appId.isNotEmpty) {
        _globallyDismissedAppIds.add(appId);
        try {
          final prefs = await SharedPreferences.getInstance();
          await prefs.setBool('seen_celebration_$appId', true);
        } catch (_) {}
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Scaffold(
      backgroundColor: isDark ? AppColors.midnightNavy : AppColors.bg,
      appBar: AppBar(
        automaticallyImplyLeading: !widget.isTab,
        leading: widget.isTab ? null : const AppBackButton(),
        title: Text(
          'My Applications',
          style: TextStyle(
            color: isDark ? Colors.white : AppColors.ink900,
            fontWeight: FontWeight.w800,
          ),
        ),
        backgroundColor: isDark ? AppColors.navySurface : AppColors.surface,
        elevation: 0,
        actions: [
          IconButton(
            icon: Icon(
              Icons.refresh_rounded,
              size: 22,
              color: isDark ? Colors.white : AppColors.primary,
            ),
            tooltip: 'Refresh applications',
            onPressed: _refresh,
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _refresh,
        color: AppColors.primary,
        child: FutureBuilder<List<dynamic>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(
                child: CircularProgressIndicator(color: AppColors.primary),
              );
            }
            if (snapshot.hasError) {
              return SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(
                  parent: BouncingScrollPhysics(),
                ),
                child: Padding(
                  padding: EdgeInsets.fromLTRB(
                    24,
                    60,
                    24,
                    widget.isTab ? 120 : 60,
                  ),
                  child: AppErrorView(
                    error: snapshot.error,
                    onRetry: _refresh,
                  ),
                ),
              );
            }

            final apps = (snapshot.data ?? []).cast<Map<String, dynamic>>();

            if (apps.isEmpty) {
              return SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(
                  parent: BouncingScrollPhysics(),
                ),
                child: Padding(
                  padding: EdgeInsets.fromLTRB(
                    24,
                    60,
                    24,
                    widget.isTab ? 120 : 60,
                  ),
                  child: Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Container(
                          width: 80,
                          height: 80,
                          decoration: AppDecorations.squircle(
                            color: AppColors.accentSoft,
                            radius: 24,
                          ),
                          child: const Icon(
                            Icons.assignment_outlined,
                            size: 40,
                            color: AppColors.primary,
                          ),
                        ),
                        const SizedBox(height: 20),
                        const Text(
                          'No Applications Submitted',
                          style: TextStyle(
                            fontSize: 19,
                            fontWeight: FontWeight.w800,
                            color: AppColors.ink900,
                          ),
                        ),
                        const SizedBox(height: 8),
                        const Text(
                          'Browse available commercial spaces and submit a lease proposal. Your application progress and status updates will appear here.',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: AppColors.ink500,
                            fontSize: 13.5,
                            height: 1.45,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            }

            final pendingCount = apps
                .where(
                  (a) =>
                      (a['status'] ?? '').toString().toLowerCase() == 'pending',
                )
                .length;
            final approvedApps = apps.where(
              (a) => (a['status'] ?? '').toString().toLowerCase() == 'approved',
            ).toList();
            final approvedCount = approvedApps.length;

            if (widget.isTab && approvedCount > 0 && !_hasShownCelebrationDialog) {
              WidgetsBinding.instance.addPostFrameCallback((_) {
                if (mounted && !_hasShownCelebrationDialog) {
                  _checkAndShowCelebration(context, approvedApps.first);
                }
              });
            }

            return ListView(
              physics: const AlwaysScrollableScrollPhysics(
                parent: BouncingScrollPhysics(),
              ),
              padding: EdgeInsets.fromLTRB(18, 16, 18, widget.isTab ? 120 : 40),
              children: [
                // Header Summary Card
                Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [AppColors.primary, AppColors.primaryLight],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(18),
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
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(8),
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.15),
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: const Icon(
                              Icons.track_changes_rounded,
                              color: Colors.white,
                              size: 20,
                            ),
                          ),
                          const SizedBox(width: 12),
                          const Expanded(
                            child: Text(
                              'Application Tracker',
                              style: TextStyle(
                                fontSize: 17,
                                fontWeight: FontWeight.w800,
                                color: Colors.white,
                                letterSpacing: -0.2,
                              ),
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 4,
                            ),
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.2),
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Text(
                              '${apps.length} Total',
                              style: const TextStyle(
                                fontSize: 11.5,
                                fontWeight: FontWeight.w700,
                                color: Colors.white,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      const Text(
                        'Track the review stages and landlord approvals of your commercial rental proposals.',
                        style: TextStyle(
                          color: AppColors.tint,
                          fontSize: 12.5,
                          height: 1.35,
                        ),
                      ),
                      const SizedBox(height: 14),
                      Row(
                        children: [
                          _SummaryChip(
                            label: 'Under Review',
                            count: pendingCount,
                            color: const Color(0xFFFED7AA),
                            textColor: const Color(0xFF9A3412),
                          ),
                          const SizedBox(width: 8),
                          _SummaryChip(
                            label: 'Approved',
                            count: approvedCount,
                            color: const Color(0xFFBBF7D0),
                            textColor: const Color(0xFF166534),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),

                // Section Label
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      'Submitted Proposals',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                        color: isDark ? Colors.white : AppColors.ink900,
                        letterSpacing: -0.2,
                      ),
                    ),
                    Text(
                      'Showing ${apps.length}',
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: AppColors.ink500,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),

                // List of Application Cards with Process Steppers
                ...apps.map(
                  (app) => _ApplicationProcessCard(
                    app: app,
                    onRefresh: _refresh,
                    isInsideTenantView: !widget.isTab,
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _SummaryChip extends StatelessWidget {
  final String label;
  final int count;
  final Color color;
  final Color textColor;

  const _SummaryChip({
    required this.label,
    required this.count,
    required this.color,
    required this.textColor,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            '$count',
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w900,
              color: textColor,
            ),
          ),
          const SizedBox(width: 5),
          Text(
            label,
            style: TextStyle(
              fontSize: 11.5,
              fontWeight: FontWeight.w700,
              color: textColor,
            ),
          ),
        ],
      ),
    );
  }
}

class _ApplicationProcessCard extends StatefulWidget {
  final Map<String, dynamic> app;
  final VoidCallback onRefresh;
  final bool isInsideTenantView;
  const _ApplicationProcessCard({
    required this.app,
    required this.onRefresh,
    this.isInsideTenantView = false,
  });

  @override
  State<_ApplicationProcessCard> createState() =>
      _ApplicationProcessCardState();
}

class _ApplicationProcessCardState extends State<_ApplicationProcessCard> {
  bool _isReplying = false;
  bool _isActivated = false;

  @override
  void initState() {
    super.initState();
    _checkActivation();
  }

  void _checkActivation() async {
    if (widget.isInsideTenantView) {
      if (mounted) setState(() => _isActivated = true);
      return;
    }
    final appId = (widget.app['id'] ?? widget.app['application_id'] ?? '').toString();
    if (appId.isNotEmpty) {
      final prefs = await SharedPreferences.getInstance();
      if (prefs.getBool('tenant_view_activated_$appId') == true) {
        if (mounted) setState(() => _isActivated = true);
      }
    }
  }

  void _showReplyModal(BuildContext context, int appId, String lessorName) {
    final textController = TextEditingController();
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setModalState) {
            return Container(
              padding: EdgeInsets.only(
                left: 20,
                right: 20,
                top: 20,
                bottom: MediaQuery.of(ctx).viewInsets.bottom + 24,
              ),
              decoration: const BoxDecoration(
                color: AppColors.surface,
                borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Text(
                          'Reply to $lessorName',
                          style: const TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                            color: AppColors.ink900,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      IconButton(
                        onPressed: () => Navigator.pop(ctx),
                        icon: const Icon(
                          Icons.close,
                          size: 20,
                          color: AppColors.ink500,
                        ),
                        padding: EdgeInsets.zero,
                        constraints: const BoxConstraints(),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Send your counter-proposal or answer to the lessor regarding the lease terms.',
                    style: TextStyle(fontSize: 12.5, color: AppColors.ink500),
                  ),
                  const SizedBox(height: 14),
                  TextField(
                    controller: textController,
                    maxLines: 4,
                    autofocus: true,
                    style: const TextStyle(
                      fontSize: 13,
                      color: AppColors.ink900,
                    ),
                    decoration: InputDecoration(
                      hintText: 'Type your reply or proposed terms here...',
                      hintStyle: const TextStyle(
                        fontSize: 13,
                        color: AppColors.ink500,
                      ),
                      filled: true,
                      fillColor: AppColors.bg,
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: AppColors.border),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: AppColors.border),
                      ),
                      focusedBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(
                          color: AppColors.primary,
                          width: 1.5,
                        ),
                      ),
                      contentPadding: const EdgeInsets.all(14),
                    ),
                  ),
                  const SizedBox(height: 16),
                  SizedBox(
                    width: double.infinity,
                    height: 46,
                    child: ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      onPressed: _isReplying
                          ? null
                          : () async {
                              final text = textController.text.trim();
                              if (text.isEmpty) return;
                              setModalState(() => _isReplying = true);
                              try {
                                await ApiService.replyToApplication(
                                  applicationId: appId,
                                  message: text,
                                );
                                if (!ctx.mounted) return;
                                Navigator.pop(ctx);
                                widget.onRefresh();
                                ScaffoldMessenger.of(context).showSnackBar(
                                  const SnackBar(
                                    content: Text(
                                      'Reply sent to lessor successfully!',
                                    ),
                                    backgroundColor: AppColors.success,
                                    behavior: SnackBarBehavior.floating,
                                  ),
                                );
                              } catch (e) {
                                if (!ctx.mounted) return;
                                setModalState(() => _isReplying = false);
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(
                                    content: Text(ApiService.errorMessage(e)),
                                    backgroundColor: AppColors.error,
                                    behavior: SnackBarBehavior.floating,
                                  ),
                                );
                              }
                            },
                      child: _isReplying
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(
                                color: Colors.white,
                                strokeWidth: 2,
                              ),
                            )
                          : const Text(
                              'Send Reply to Lessor',
                              style: TextStyle(
                                fontWeight: FontWeight.w700,
                                fontSize: 13.5,
                                color: Colors.white,
                              ),
                            ),
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final app = widget.app;
    final appId = (app['id'] is num)
        ? (app['id'] as num).toInt()
        : int.tryParse(app['id']?.toString() ?? '0') ?? 0;
    final status = (app['status'] ?? 'pending').toString().toLowerCase();
    final rent = _asNum(app['rent']).toDouble();
    final term = _asNum(app['term_months']).toInt();
    final propertyName =
        app['property_name']?.toString() ?? 'Commercial Property';
    final businessName = app['business_name']?.toString() ?? '';
    final lessorName = app['lessor_name']?.toString() ?? 'Lessor';
    final rawDate = app['submitted_at'] ?? app['created_at'] ?? '';
    final createdAt = _formatDate(rawDate);
    final lessorRebuttal = (app['lessor_rebuttal'] ?? '').toString().trim();
    final rawRebuttalDate = app['rebuttal_at']?.toString() ?? '';
    final rebuttalDate = rawRebuttalDate.isNotEmpty
        ? _formatDate(rawRebuttalDate)
        : '';
    final negotiationMessages =
        (app['negotiation_messages'] as List<dynamic>?) ?? [];

    Color statusColor;
    Color statusBg;
    String statusText;
    int currentStep; // 1: Submitted, 2: Under Review, 3: Completed / Decision

    switch (status) {
      case 'approved':
        statusColor = AppColors.success;
        statusBg = AppColors.successSoft;
        statusText = 'Approved';
        currentStep = 3;
        break;
      case 'rejected':
        statusColor = AppColors.error;
        statusBg = AppColors.errorSoft;
        statusText = 'Declined';
        currentStep = 3;
        break;
      default:
        statusColor = AppColors.warning;
        statusBg = AppColors.warningSoft;
        statusText = 'Under Review';
        currentStep = 2;
    }

    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Container(
      margin: const EdgeInsets.only(bottom: 18),
      decoration: AppDecorations.card(
        color: isDark ? AppColors.navyCard : AppColors.surface,
        border: Border.all(
          color: isDark ? AppColors.navyBorder : AppColors.border.withValues(alpha: 0.85),
          width: 1,
        ),
        radius: 18,
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header Bar
          Container(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
            decoration: BoxDecoration(
              color: isDark ? AppColors.navySurface : AppColors.bg,
              border: Border(
                bottom: BorderSide(
                  color: isDark ? AppColors.navyBorder : AppColors.border.withValues(alpha: 0.6),
                ),
              ),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 38,
                  height: 38,
                  decoration: AppDecorations.squircle(
                    color: isDark ? AppColors.navyCard : AppColors.surface,
                    radius: 10,
                  ),
                  child: Icon(
                    Icons.storefront_rounded,
                    size: 20,
                    color: isDark ? const Color(0xFF60A5FA) : AppColors.primary,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        propertyName,
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                          color: isDark ? Colors.white : AppColors.ink900,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Row(
                        children: [
                          const Icon(
                            Icons.person_outline,
                            size: 13,
                            color: AppColors.ink500,
                          ),
                          const SizedBox(width: 4),
                          Expanded(
                            child: Text(
                              'Lessor: $lessorName',
                              style: const TextStyle(
                                fontSize: 12,
                                color: AppColors.ink500,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 5,
                  ),
                  decoration: AppDecorations.badge(bg: statusBg),
                  child: Text(
                    statusText,
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      color: statusColor,
                    ),
                  ),
                ),
              ],
            ),
          ),

          // Proposal Info Grid
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: _DetailBox(
                        title: 'Proposed Monthly Rent',
                        value: '₱${_formatMoney(rent)}',
                        icon: Icons.payments_outlined,
                        valueColor: AppColors.primary,
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _DetailBox(
                        title: 'Lease Duration',
                        value: '$term Months',
                        icon: Icons.calendar_today_outlined,
                        valueColor: AppColors.ink900,
                      ),
                    ),
                  ],
                ),
                if (businessName.isNotEmpty) ...[
                  const SizedBox(height: 10),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 9,
                    ),
                    decoration: BoxDecoration(
                      color: AppColors.surface,
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: AppColors.border),
                    ),
                    child: Row(
                      children: [
                        const Icon(
                          Icons.business_center_outlined,
                          size: 15,
                          color: AppColors.ink500,
                        ),
                        const SizedBox(width: 8),
                        const Text(
                          'Business Concept: ',
                          style: TextStyle(
                            fontSize: 12,
                            color: AppColors.ink500,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        Expanded(
                          child: Text(
                            businessName,
                            style: const TextStyle(
                              fontSize: 12,
                              color: AppColors.ink900,
                              fontWeight: FontWeight.w700,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],

                // Negotiation Messages Thread & Rebuttal Display
                if (negotiationMessages.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF0F7FF),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFFCCE3FF)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text(
                              'Negotiation Conversation',
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w800,
                                color: Color(0xFF0B57D0),
                              ),
                            ),
                            Text(
                              '${negotiationMessages.length} message(s)',
                              style: const TextStyle(
                                fontSize: 10.5,
                                color: AppColors.ink500,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        ...negotiationMessages.map((m) {
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
                            margin: const EdgeInsets.only(bottom: 6),
                            padding: const EdgeInsets.all(8),
                            decoration: BoxDecoration(
                              color: isMe
                                  ? Colors.white
                                  : const Color(0xFFE8F1FE),
                              borderRadius: BorderRadius.circular(8),
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
                                          fontSize: 11,
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
                                      const SizedBox(width: 6),
                                      Text(
                                        timeStr,
                                        style: const TextStyle(
                                          fontSize: 10,
                                          color: AppColors.ink500,
                                        ),
                                      ),
                                    ],
                                  ],
                                ),
                                const SizedBox(height: 3),
                                Text(
                                  text,
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: AppColors.ink700,
                                    height: 1.3,
                                  ),
                                ),
                              ],
                            ),
                          );
                        }),
                        if (status == 'pending') ...[
                          const SizedBox(height: 6),
                          SizedBox(
                            width: double.infinity,
                            height: 36,
                            child: OutlinedButton.icon(
                              onPressed: () =>
                                  _showReplyModal(context, appId, lessorName),
                              icon: const Icon(
                                Icons.reply_rounded,
                                size: 16,
                                color: Color(0xFF0B57D0),
                              ),
                              label: const Text(
                                'Reply to Lessor',
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w700,
                                  color: Color(0xFF0B57D0),
                                ),
                              ),
                              style: OutlinedButton.styleFrom(
                                backgroundColor: Colors.white,
                                side: const BorderSide(
                                  color: Color(0xFF0B57D0),
                                ),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(8),
                                ),
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ] else if (lessorRebuttal.isNotEmpty) ...[
                  const SizedBox(height: 10),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF0F7FF),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: const Color(0xFFCCE3FF)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text(
                              'Lessor Reply / Rebuttal',
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w700,
                                color: Color(0xFF0B57D0),
                              ),
                            ),
                            if (rebuttalDate.isNotEmpty)
                              Text(
                                rebuttalDate,
                                style: const TextStyle(
                                  fontSize: 11,
                                  color: AppColors.ink500,
                                ),
                              ),
                          ],
                        ),
                        const SizedBox(height: 5),
                        Text(
                          lessorRebuttal,
                          style: const TextStyle(
                            fontSize: 12.5,
                            color: Color(0xFF1A3C6D),
                            height: 1.35,
                          ),
                        ),
                        if (status == 'pending') ...[
                          const SizedBox(height: 10),
                          SizedBox(
                            width: double.infinity,
                            height: 36,
                            child: OutlinedButton.icon(
                              onPressed: () =>
                                  _showReplyModal(context, appId, lessorName),
                              icon: const Icon(
                                Icons.reply_rounded,
                                size: 16,
                                color: Color(0xFF0B57D0),
                              ),
                              label: const Text(
                                'Reply to Lessor',
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w700,
                                  color: Color(0xFF0B57D0),
                                ),
                              ),
                              style: OutlinedButton.styleFrom(
                                backgroundColor: Colors.white,
                                side: const BorderSide(
                                  color: Color(0xFF0B57D0),
                                ),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(8),
                                ),
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ),

          const Divider(height: 1, color: AppColors.border),

          // Application Progress Stepper (Process Flow)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Application Progress',
                  style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w800,
                    color: AppColors.ink700,
                    letterSpacing: -0.1,
                  ),
                ),
                const SizedBox(height: 14),

                // 3 Steps: 1. Submitted -> 2. Under Review -> 3. Decision / Contract
                _StepRow(
                  stepNumber: 1,
                  title: 'Proposal Submitted',
                  subtitle: createdAt.isNotEmpty
                      ? 'Submitted on $createdAt'
                      : 'Form details received',
                  isCompleted: true,
                  isActive: currentStep == 1,
                  isLast: false,
                ),
                _StepRow(
                  stepNumber: 2,
                  title: 'Lessor Review & Assessment',
                  subtitle: currentStep >= 2
                      ? (currentStep == 2
                            ? 'Evaluating business concept & rental terms'
                            : 'Review completed by lessor')
                      : 'Awaiting review',
                  isCompleted: currentStep > 2,
                  isActive: currentStep == 2,
                  isLast: false,
                  activeColor: AppColors.warning,
                ),
                _StepRow(
                  stepNumber: 3,
                  title: status == 'rejected'
                      ? 'Application Declined'
                      : (status == 'approved'
                            ? 'Approved & Ready for Lease'
                            : 'Final Decision & Lease'),
                  subtitle: status == 'approved'
                      ? 'Proposal accepted! Your lease contract is ready.'
                      : (status == 'rejected'
                            ? 'This proposal was declined by the property owner.'
                            : 'Awaiting final landlord confirmation'),
                  isCompleted: currentStep == 3,
                  isActive: currentStep == 3,
                  isLast: true,
                  activeColor: status == 'rejected'
                      ? AppColors.error
                      : AppColors.success,
                  isError: status == 'rejected',
                ),
                if (status == 'approved') ...[
                  const SizedBox(height: 16),
                  if (widget.isInsideTenantView || _isActivated)
                    // In tenant view or activated: Color green with check and 'Approved'
                    Container(
                      width: double.infinity,
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(
                          colors: [Color(0xFF059669), Color(0xFF10B981)],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        borderRadius: BorderRadius.circular(14),
                        boxShadow: [
                          BoxShadow(
                            color: const Color(0xFF10B981).withValues(alpha: 0.35),
                            blurRadius: 10,
                            offset: const Offset(0, 4),
                          ),
                        ],
                      ),
                      child: Material(
                        color: Colors.transparent,
                        child: InkWell(
                          borderRadius: BorderRadius.circular(14),
                          onTap: () {
                            if (Navigator.canPop(context)) {
                              Navigator.of(context).pop();
                            } else {
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(
                                  content: Text('Application Approved • Tenant View Active'),
                                  duration: Duration(seconds: 2),
                                  behavior: SnackBarBehavior.floating,
                                ),
                              );
                            }
                          },
                          child: const Padding(
                            padding: EdgeInsets.symmetric(
                              horizontal: 16,
                              vertical: 13,
                            ),
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Icon(
                                  Icons.check_circle_rounded,
                                  color: Colors.white,
                                  size: 20,
                                ),
                                SizedBox(width: 8),
                                Text(
                                  'Approved',
                                  style: TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w900,
                                    fontSize: 14,
                                    letterSpacing: 0.2,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                    )
                  else
                    // Before being in tenant view: 'Direct to Dashboard' action button
                    Container(
                      width: double.infinity,
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(
                          colors: [Color(0xFF1D4ED8), Color(0xFF2563EB)],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        borderRadius: BorderRadius.circular(14),
                        boxShadow: [
                          BoxShadow(
                            color: const Color(0xFF2563EB).withValues(alpha: 0.35),
                            blurRadius: 10,
                            offset: const Offset(0, 4),
                          ),
                        ],
                      ),
                      child: Material(
                        color: Colors.transparent,
                        child: InkWell(
                          borderRadius: BorderRadius.circular(14),
                          onTap: () async {
                            final appIdStr = appId.toString();
                            if (appIdStr.isNotEmpty) {
                              try {
                                final prefs = await SharedPreferences.getInstance();
                                await prefs.setBool('tenant_view_activated_$appIdStr', true);
                                await prefs.setBool('seen_celebration_$appIdStr', true);
                              } catch (_) {}
                            }
                            await ApiService.fetchLeaseStatus();
                            if (!context.mounted) return;
                            Navigator.of(context).pushAndRemoveUntil(
                              MaterialPageRoute(builder: (_) => const HomeShell()),
                              (route) => false,
                            );
                          },
                          child: const Padding(
                            padding: EdgeInsets.symmetric(
                              horizontal: 16,
                              vertical: 13,
                            ),
                            child: FittedBox(
                              fit: BoxFit.scaleDown,
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(
                                    Icons.dashboard_customize_rounded,
                                    color: Colors.white,
                                    size: 19,
                                  ),
                                  SizedBox(width: 8),
                                  Text(
                                    'Direct to Dashboard',
                                    style: TextStyle(
                                      color: Colors.white,
                                      fontWeight: FontWeight.w800,
                                      fontSize: 13.5,
                                      letterSpacing: -0.1,
                                    ),
                                  ),
                                  SizedBox(width: 6),
                                  Icon(
                                    Icons.arrow_forward_rounded,
                                    color: Colors.white70,
                                    size: 16,
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  const SizedBox(height: 8),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      style: OutlinedButton.styleFrom(
                        side: BorderSide(
                          color: isDark ? const Color(0xFF1E3A5F) : AppColors.border,
                        ),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                        padding: const EdgeInsets.symmetric(vertical: 11),
                      ),
                      onPressed: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) =>
                                LeaseContractScreen(applicationData: app),
                          ),
                        );
                      },
                      icon: Icon(
                        Icons.description_outlined,
                        size: 16,
                        color: isDark ? const Color(0xFF60A5FA) : AppColors.primary,
                      ),
                      label: Text(
                        'View Lease Agreement & Terms',
                        style: TextStyle(
                          fontSize: 12.5,
                          fontWeight: FontWeight.w700,
                          color: isDark ? Colors.white : AppColors.ink900,
                        ),
                      ),
                    ),
                  ),
                ] else if (status == 'pending') ...[
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      onPressed: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) =>
                                LeaseContractScreen(applicationData: app),
                          ),
                        );
                      },
                      icon: const Icon(
                        Icons.assignment_outlined,
                        size: 16,
                        color: AppColors.primary,
                      ),
                      label: const FittedBox(
                        fit: BoxFit.scaleDown,
                        child: Text(
                          'View Negotiated Terms & Checklist',
                          style: TextStyle(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w700,
                            color: AppColors.primary,
                          ),
                        ),
                      ),
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 10),
                        side: const BorderSide(color: AppColors.primaryLight),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(10),
                        ),
                      ),
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

  String _formatDate(dynamic raw) {
    if (raw == null || raw.toString().isEmpty) return '';
    try {
      final dt = DateTime.parse(raw.toString());
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
      return raw.toString();
    }
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

class _DetailBox extends StatelessWidget {
  final String title;
  final String value;
  final IconData icon;
  final Color valueColor;

  const _DetailBox({
    required this.title,
    required this.value,
    required this.icon,
    required this.valueColor,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(
        color: AppColors.bg,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border.withValues(alpha: 0.7)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, size: 13, color: AppColors.ink500),
              const SizedBox(width: 4),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    fontSize: 10.5,
                    color: AppColors.ink500,
                    fontWeight: FontWeight.w600,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w800,
              color: valueColor,
            ),
          ),
        ],
      ),
    );
  }
}

class _StepRow extends StatelessWidget {
  final int stepNumber;
  final String title;
  final String subtitle;
  final bool isCompleted;
  final bool isActive;
  final bool isLast;
  final Color activeColor;
  final bool isError;

  const _StepRow({
    required this.stepNumber,
    required this.title,
    required this.subtitle,
    required this.isCompleted,
    required this.isActive,
    required this.isLast,
    this.activeColor = AppColors.primary,
    this.isError = false,
  });

  @override
  Widget build(BuildContext context) {
    Color iconBg;
    Color iconColor;
    IconData icon;

    if (isCompleted) {
      if (isError) {
        iconBg = AppColors.errorSoft;
        iconColor = AppColors.error;
        icon = Icons.close_rounded;
      } else {
        iconBg = AppColors.successSoft;
        iconColor = AppColors.success;
        icon = Icons.check_rounded;
      }
    } else if (isActive) {
      iconBg = activeColor.withValues(alpha: 0.15);
      iconColor = activeColor;
      icon = Icons.hourglass_top_rounded;
    } else {
      iconBg = AppColors.borderLight;
      iconColor = AppColors.ink300;
      icon = Icons.circle;
    }

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Column(
            children: [
              Container(
                width: 26,
                height: 26,
                decoration: BoxDecoration(
                  color: iconBg,
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: isActive
                        ? activeColor
                        : (isCompleted ? iconColor : AppColors.border),
                    width: 1.5,
                  ),
                ),
                child: Icon(
                  icon,
                  size: isCompleted || isActive ? 14 : 8,
                  color: iconColor,
                ),
              ),
              if (!isLast)
                Expanded(
                  child: Container(
                    width: 2,
                    color: isCompleted ? AppColors.success : AppColors.border,
                  ),
                ),
            ],
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(bottom: isLast ? 0 : 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: isActive || isCompleted
                          ? FontWeight.w800
                          : FontWeight.w600,
                      color: isActive
                          ? activeColor
                          : (isCompleted
                                ? (isError ? AppColors.error : AppColors.ink900)
                                : AppColors.ink500),
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      fontSize: 11.5,
                      color: AppColors.ink500,
                      height: 1.3,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
