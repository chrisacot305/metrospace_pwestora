import 'package:flutter/material.dart';
import '../theme.dart';
import '../services/api_service.dart';
import '../widgets/app_error_view.dart';
import 'ticket_detail_screen.dart';

const kTicketStages = [
  'Submitted',
  'Under Review',
  'Approved',
  'Contractor Assigned',
  'Scheduled',
  'In Progress',
  'Inspection',
  'Completed',
];

const kCategoryIcons = {
  'plumbing': Icons.water_drop_outlined,
  'electrical': Icons.bolt_outlined,
  'structural': Icons.construction_outlined,
  'internet': Icons.wifi,
  'hvac': Icons.ac_unit,
  'cleaning': Icons.auto_awesome_outlined,
  'security': Icons.shield_outlined,
  'lighting': Icons.lightbulb_outline,
};

const kPriorityColors = {
  'low': (AppColors.success, AppColors.successSoft),
  'medium': (AppColors.accent, AppColors.accentSoft),
  'high': (AppColors.warning, AppColors.warningSoft),
  'urgent': (AppColors.error, AppColors.errorSoft),
};

class TicketsListScreen extends StatefulWidget {
  final Map<String, dynamic>? lease;
  const TicketsListScreen({super.key, this.lease});

  @override
  State<TicketsListScreen> createState() => _TicketsListScreenState();
}

class _TicketsListScreenState extends State<TicketsListScreen> {
  late Future<List<dynamic>> _future;
  String _filter = 'all';

  int? get _tenantId {
    final tid = widget.lease?['tenant_id'] ?? widget.lease?['id'];
    return tid is int
        ? tid
        : (tid != null ? int.tryParse(tid.toString()) : null);
  }

  @override
  void initState() {
    super.initState();
    _future = ApiService.fetchTickets(tenantId: _tenantId);
  }

  Future<void> _refresh() async {
    setState(() => _future = ApiService.fetchTickets(tenantId: _tenantId));
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    final bName = widget.lease?['property_name'] ??
        widget.lease?['title'] ??
        widget.lease?['property_title'] ??
        '';
    final stallNo = widget.lease?['stall_number'] ??
        widget.lease?['unit_number'] ??
        widget.lease?['stall_no'] ??
        '';
    final subtitleParts = [
      if (stallNo.toString().isNotEmpty) 'Stall $stallNo',
      if (bName.toString().isNotEmpty) bName.toString(),
    ];
    final buildingSubtitle = subtitleParts.isNotEmpty
        ? subtitleParts.join(' • ')
        : 'Stall Unit';

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF000000) : AppColors.bg,
      appBar: AppBar(
        backgroundColor: isDark ? const Color(0xFF000000) : AppColors.bg,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleSpacing: 4,
        leading: AppBackButton(
          isDark: isDark,
          onPressed: () => Navigator.of(context).pop(),
        ),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'Maintenance Status & Logs',
              style: TextStyle(
                fontSize: 19,
                fontWeight: FontWeight.w900,
                color: isDark ? Colors.white : AppColors.ink900,
                letterSpacing: -0.4,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              buildingSubtitle,
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: isDark ? Colors.white60 : AppColors.ink500,
              ),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
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
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  const SizedBox(height: 60),
                  AppErrorView(
                    error: snapshot.error,
                    onRetry: _refresh,
                  ),
                ],
              );
            }

            final tickets = (snapshot.data ?? []).cast<Map<String, dynamic>>();

            bool isTicketRejected(Map<String, dynamic> t) {
              final stage = (t['stage'] as num?)?.toInt() ?? 0;
              final status = (t['status'] ?? '').toString().toLowerCase();
              return stage < 0 || stage >= 8 || status == 'rejected' || status == 'declined';
            }

            bool isTicketResolved(Map<String, dynamic> t) {
              if (isTicketRejected(t)) return false;
              final stage = (t['stage'] as num?)?.toInt() ?? 0;
              final status = (t['status'] ?? '').toString().toLowerCase();
              return status == 'completed' || status == 'resolved' || stage == kTicketStages.length - 1;
            }

            bool isTicketInProgress(Map<String, dynamic> t) {
              return !isTicketRejected(t) && !isTicketResolved(t);
            }

            final total = tickets.length;
            final inProgress = tickets.where(isTicketInProgress).length;
            final resolved = tickets.where(isTicketResolved).length;
            final rejected = tickets.where(isTicketRejected).length;

            final resolveRate = total > 0
                ? ((resolved / total) * 100).toInt()
                : 100;

            final filteredTickets = tickets.where((t) {
              if (_filter == 'active') return isTicketInProgress(t);
              if (_filter == 'completed') return isTicketResolved(t);
              if (_filter == 'rejected') return isTicketRejected(t);
              return true;
            }).toList();

            return ListView(
              physics: const AlwaysScrollableScrollPhysics(
                parent: BouncingScrollPhysics(),
              ),
              padding: const EdgeInsets.fromLTRB(18, 10, 18, 40),
              children: [
                // 1. Clean, Non-gradient Circular Resolution Rate (Hero)
                Center(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        SizedBox(
                          width: 116,
                          height: 116,
                          child: Stack(
                            fit: StackFit.expand,
                            children: [
                              CircularProgressIndicator(
                                value: total > 0 ? (resolved / total) : 1.0,
                                strokeWidth: 9,
                                backgroundColor: isDark
                                    ? const Color(0xFF262626)
                                    : const Color(0xFFE2E8F0),
                                valueColor: AlwaysStoppedAnimation(
                                  total == 0 || resolveRate == 100
                                      ? AppColors.success
                                      : AppColors.electricBlue,
                                ),
                              ),
                              Center(
                                child: Column(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Text(
                                      '$resolveRate%',
                                      style: TextStyle(
                                        fontSize: 26,
                                        fontWeight: FontWeight.w900,
                                        color: isDark
                                            ? Colors.white
                                            : AppColors.ink900,
                                        letterSpacing: -0.6,
                                      ),
                                    ),
                                    Text(
                                      total == 0 ? 'ALL CLEAR' : 'RESOLVED',
                                      style: TextStyle(
                                        fontSize: 9.5,
                                        fontWeight: FontWeight.w800,
                                        color: isDark
                                            ? Colors.white60
                                            : AppColors.ink500,
                                        letterSpacing: 0.8,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 10),
                        Text(
                          total == 0
                              ? 'No maintenance incidents reported'
                              : '$resolved of $total reports resolved',
                          style: TextStyle(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w600,
                            color: isDark ? Colors.white70 : AppColors.ink500,
                          ),
                          textAlign: TextAlign.center,
                        ),
                      ],
                    ),
                  ),
                ),

                const SizedBox(height: 16),

                // 2. The 4 Metric Boxes (2x2 Grid) with Tap-to-Filter
                Row(
                  children: [
                    Expanded(
                      child: _StatusStatBox(
                        title: 'Total Logs',
                        count: '$total',
                        hint: 'All records',
                        icon: Icons.receipt_long_rounded,
                        color: AppColors.primary,
                        isSelected: _filter == 'all',
                        onTap: () => setState(() => _filter = 'all'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _StatusStatBox(
                        title: 'In Progress',
                        count: '$inProgress',
                        hint: 'Action ongoing',
                        icon: Icons.hourglass_top_rounded,
                        color: AppColors.warning,
                        isSelected: _filter == 'active',
                        onTap: () => setState(() => _filter = 'active'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: _StatusStatBox(
                        title: 'Resolved',
                        count: '$resolved',
                        hint: 'Completed',
                        icon: Icons.check_circle_outline_rounded,
                        color: AppColors.success,
                        isSelected: _filter == 'completed',
                        onTap: () => setState(() => _filter = 'completed'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _StatusStatBox(
                        title: 'Rejected',
                        count: '$rejected',
                        hint: 'Declined',
                        icon: Icons.cancel_outlined,
                        color: AppColors.error,
                        isSelected: _filter == 'rejected',
                        onTap: () => setState(() => _filter = 'rejected'),
                      ),
                    ),
                  ],
                ),

                const SizedBox(height: 18),

                // 3. Status Segmented Control (All / In Progress / Resolved / Rejected)
                Container(
                  padding: const EdgeInsets.all(4),
                  decoration: BoxDecoration(
                    color: isDark ? const Color(0xFF141414) : AppColors.surface,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(
                      color: isDark
                          ? const Color(0xFF262626)
                          : AppColors.border,
                    ),
                  ),
                  child: Row(
                    children: [
                      _SegmentTab(
                        title: 'All ($total)',
                        active: _filter == 'all',
                        onTap: () => setState(() => _filter = 'all'),
                      ),
                      _SegmentTab(
                        title: 'Active ($inProgress)',
                        active: _filter == 'active',
                        onTap: () => setState(() => _filter = 'active'),
                      ),
                      _SegmentTab(
                        title: 'Resolved ($resolved)',
                        active: _filter == 'completed',
                        onTap: () => setState(() => _filter = 'completed'),
                      ),
                      _SegmentTab(
                        title: 'Rejected ($rejected)',
                        active: _filter == 'rejected',
                        onTap: () => setState(() => _filter = 'rejected'),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 16),

                // 4. Ticket List
                if (filteredTickets.isEmpty) ...[
                  const SizedBox(height: 36),
                  Center(
                    child: Container(
                      width: 60,
                      height: 60,
                      decoration: AppDecorations.squircle(
                        color: isDark
                            ? const Color(0xFF1E1E1E)
                            : AppColors.accentSoft,
                      ),
                      child: Icon(
                        _filter == 'rejected'
                            ? Icons.check_circle_outline
                            : Icons.assignment_outlined,
                        size: 30,
                        color: _filter == 'rejected'
                            ? AppColors.success
                            : AppColors.primary,
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),
                  Center(
                    child: Text(
                      _filter == 'rejected'
                          ? 'No rejected reports'
                          : _filter == 'completed'
                              ? 'No resolved reports yet'
                              : _filter == 'active'
                                  ? 'No active in-progress reports'
                                  : 'No maintenance logs found',
                      style: TextStyle(
                        fontSize: 15.5,
                        fontWeight: FontWeight.w800,
                        color: isDark ? Colors.white : AppColors.ink900,
                      ),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Center(
                    child: Text(
                      _filter == 'rejected'
                          ? 'All reported issues have been processed or are in review.'
                          : 'Incidents submitted will appear here.',
                      style: TextStyle(
                        color: isDark ? Colors.white54 : AppColors.ink500,
                        fontSize: 12.5,
                      ),
                    ),
                  ),
                ] else ...[
                  ...filteredTickets.map((t) => _ArtoReportCard(ticket: t)),
                ],
              ],
            );
          },
        ),
      ),
    );
  }
}

/// Interactive 2x2 Metric Stat Box with Tap-to-Filter
class _StatusStatBox extends StatelessWidget {
  final String title;
  final String count;
  final String hint;
  final IconData icon;
  final Color color;
  final bool isSelected;
  final VoidCallback onTap;

  const _StatusStatBox({
    required this.title,
    required this.count,
    required this.hint,
    required this.icon,
    required this.color,
    required this.isSelected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cardBg = isDark ? const Color(0xFF141414) : Colors.white;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          decoration: BoxDecoration(
            color: cardBg,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: isSelected
                  ? color
                  : (isDark ? const Color(0xFF262626) : AppColors.border),
              width: isSelected ? 2.0 : 1.0,
            ),
            boxShadow: isSelected
                ? [
                    BoxShadow(
                      color: color.withValues(alpha: 0.15),
                      blurRadius: 10,
                      offset: const Offset(0, 3),
                    ),
                  ]
                : [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: isDark ? 0.2 : 0.03),
                      blurRadius: 6,
                      offset: const Offset(0, 2),
                    ),
                  ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Container(
                    width: 34,
                    height: 34,
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Icon(icon, size: 18, color: color),
                  ),
                  Text(
                    hint,
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w600,
                      color: isDark ? Colors.white54 : AppColors.ink400,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                count,
                style: TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w900,
                  color: isDark ? Colors.white : AppColors.ink900,
                  letterSpacing: -0.4,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                title,
                style: TextStyle(
                  fontSize: 11.5,
                  fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                  color: isSelected
                      ? color
                      : (isDark ? Colors.white70 : AppColors.ink700),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SegmentTab extends StatelessWidget {
  final String title;
  final bool active;
  final VoidCallback onTap;

  const _SegmentTab({
    required this.title,
    required this.active,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
          decoration: BoxDecoration(
            color: active ? AppColors.primary : Colors.transparent,
            borderRadius: BorderRadius.circular(10),
          ),
          alignment: Alignment.center,
          child: FittedBox(
            fit: BoxFit.scaleDown,
            child: Text(
              title,
              maxLines: 1,
              style: TextStyle(
                fontSize: 12,
                fontWeight: active ? FontWeight.w800 : FontWeight.w600,
                color: active ? Colors.white : AppColors.ink700,
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Arto Plus Report Card
class _ArtoReportCard extends StatelessWidget {
  final Map<String, dynamic> ticket;
  const _ArtoReportCard({required this.ticket});

  @override
  Widget build(BuildContext context) {
    final stage = (ticket['stage'] as num?)?.toInt() ?? 0;
    final priority = (ticket['priority'] as String?) ?? 'medium';
    final (pColor, pBg) =
        kPriorityColors[priority] ?? kPriorityColors['medium']!;
    final icon = kCategoryIcons[ticket['category']] ?? Icons.build_outlined;
    final isRejected = stage < 0 ||
        stage >= 8 ||
        (ticket['status'] ?? '').toString().toLowerCase() == 'rejected' ||
        (ticket['status'] ?? '').toString().toLowerCase() == 'declined';

    final progress = isRejected
        ? 1.0
        : (kTicketStages.length > 1
            ? stage / (kTicketStages.length - 1)
            : 0.0);

    final stageName = isRejected
        ? 'Declined / Rejected'
        : (stage < kTicketStages.length
            ? kTicketStages[stage]
            : 'Stage $stage');

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: AppDecorations.card(radius: 18),
      child: Material(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(18),
        child: InkWell(
          borderRadius: BorderRadius.circular(18),
          onTap: () => Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => TicketDetailScreen(ticket: ticket),
            ),
          ),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 44,
                      height: 44,
                      decoration: AppDecorations.squircle(
                        color: AppColors.accentSoft,
                      ),
                      child: Icon(icon, size: 22, color: AppColors.primary),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            ticket['title']?.toString() ?? 'Maintenance Issue',
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 14.5,
                              fontWeight: FontWeight.w800,
                              color: AppColors.ink900,
                            ),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            'Report #MT-${ticket['id']} · ${ticket['category']?.toString().toUpperCase() ?? 'GENERAL'}',
                            style: const TextStyle(
                              fontSize: 11.5,
                              color: AppColors.ink500,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 3,
                      ),
                      decoration: AppDecorations.badge(bg: pBg),
                      child: Text(
                        priority.toUpperCase(),
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w800,
                          color: pColor,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Expanded(
                      child: Text(
                        'Stage: $stageName',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: AppColors.primary,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(
                        isRejected ? 'Declined' : '${(progress * 100).toInt()}% Done',
                        style: TextStyle(
                          fontSize: 11.5,
                          fontWeight: FontWeight.w700,
                          color: isRejected ? AppColors.error : AppColors.ink500,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                ClipRRect(
                  borderRadius: BorderRadius.circular(999),
                  child: LinearProgressIndicator(
                    value: progress,
                    minHeight: 7,
                    backgroundColor: isRejected ? AppColors.errorSoft : AppColors.borderLight,
                    valueColor: AlwaysStoppedAnimation(
                      isRejected
                          ? AppColors.error
                          : (progress >= 1.0 ? AppColors.success : AppColors.primary),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
