import 'package:flutter/material.dart';
import '../theme.dart';
import '../services/api_service.dart';
import 'violation_notice_sheet.dart';
import 'messages_screen.dart';

class ViolationsScreen extends StatefulWidget {
  final Map<String, dynamic>? lease;

  const ViolationsScreen({super.key, this.lease});

  @override
  State<ViolationsScreen> createState() => _ViolationsScreenState();
}

class _ViolationsScreenState extends State<ViolationsScreen> {
  bool _loading = true;
  List<Map<String, dynamic>> _violations = [];
  int _selectedTab = 0; // 0: Citations History, 1: Disciplinary Policy

  @override
  void initState() {
    super.initState();
    _fetchViolations();
  }

  Future<void> _fetchViolations() async {
    setState(() => _loading = true);
    try {
      final tid = widget.lease?['tenant_id'] ?? widget.lease?['id'];
      final int? tenantId = tid is int
          ? tid
          : (tid != null ? int.tryParse(tid.toString()) : null);

      final lessorName = (widget.lease?['lessor_name'] ?? '').toString();
      final list = await ApiService.fetchViolations(
        tenantId: tenantId,
        lessorName: lessorName,
      );
      if (mounted) {
        setState(() {
          _violations = list.where((v) {
            if (v['lessor_name'] != null && lessorName.isNotEmpty) {
              final vLessor = v['lessor_name'].toString().trim().toLowerCase();
              if (vLessor.isNotEmpty && vLessor != lessorName.trim().toLowerCase()) return false;
            }
            return true;
          }).toList();
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  int get _highestStrike {
    int max = 0;
    for (final v in _violations) {
      final s = v['strike'] is int
          ? v['strike'] as int
          : int.tryParse(v['strike']?.toString() ?? '0') ?? 0;
      if (s > max) max = s;
    }
    return max;
  }

  String _formatDate(dynamic dateVal) {
    if (dateVal == null) return 'Recently';
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

  IconData _getCategoryIcon(String category) {
    final lower = category.toLowerCase();
    if (lower.contains('rent') || lower.contains('past due')) {
      return Icons.receipt_long_rounded;
    }
    if (lower.contains('noise')) {
      return Icons.volume_up_rounded;
    }
    if (lower.contains('waste') || lower.contains('garbage') || lower.contains('trash')) {
      return Icons.delete_outline_rounded;
    }
    if (lower.contains('hour') || lower.contains('late')) {
      return Icons.schedule_rounded;
    }
    if (lower.contains('alteration') || lower.contains('modification')) {
      return Icons.construction_rounded;
    }
    if (lower.contains('safety')) {
      return Icons.shield_outlined;
    }
    return Icons.gavel_rounded;
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final propertyName =
        (widget.lease?['property_name'] ?? 'Commercial Stall').toString();
    final highestStrike = _highestStrike;

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF121212) : const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: isDark ? const Color(0xFF1E1E1E) : Colors.white,
        elevation: 0,
        leading: AppBackButton(
          onPressed: () => Navigator.of(context).pop(),
        ),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Violations & Standing',
              style: TextStyle(
                fontSize: 17,
                fontWeight: FontWeight.w800,
                color: isDark ? Colors.white : AppColors.midnightNavy,
                letterSpacing: -0.2,
              ),
            ),
            Text(
              propertyName,
              style: TextStyle(
                fontSize: 11.5,
                color: isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B),
                fontWeight: FontWeight.w500,
              ),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(1),
          child: Divider(
            height: 1,
            color: isDark ? const Color(0xFF262626) : const Color(0xFFE2E8F0),
          ),
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _fetchViolations,
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(
                  parent: BouncingScrollPhysics(),
                ),
                padding: const EdgeInsets.fromLTRB(20, 18, 20, 36),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // 1. Standing Summary Card
                    _buildStandingHero(highestStrike, isDark: isDark),
                    const SizedBox(height: 16),

                    // 2. Real-World Rule Explainer Tip
                    _buildRealWorldCureTip(isDark: isDark),
                    const SizedBox(height: 20),

                    // 3. Tab Switcher (Citations vs Policy)
                    _buildTabSwitcher(isDark: isDark),
                    const SizedBox(height: 18),

                    // 4. Tab Body
                    if (_selectedTab == 0)
                      _buildCitationsList(isDark: isDark)
                    else
                      _buildPolicyTab(isDark: isDark),
                  ],
                ),
              ),
            ),
    );
  }

  // ================= 1. STANDING HERO CARD =================
  Widget _buildStandingHero(int strike, {bool isDark = false}) {
    Color badgeBg;
    Color badgeColor;
    String statusTitle;
    String statusDesc;
    IconData statusIcon;

    if (strike >= 3) {
      badgeBg = const Color(0xFFFEE2E2);
      badgeColor = const Color(0xFFDC2626);
      statusTitle = 'Strike 3: Material Breach';
      statusDesc = 'Threshold for formal lease cancellation & notice to vacate.';
      statusIcon = Icons.gavel_rounded;
    } else if (strike == 2) {
      badgeBg = const Color(0xFFFEF3C7);
      badgeColor = const Color(0xFFD97706);
      statusTitle = 'Strike 2: Notice of Default';
      statusDesc = '₱500.00 penalty billed. Action required to prevent Strike 3.';
      statusIcon = Icons.warning_amber_rounded;
    } else if (strike == 1) {
      badgeBg = const Color(0xFFEFF6FF);
      badgeColor = const Color(0xFF0284C7);
      statusTitle = 'Strike 1: Written Notice';
      statusDesc = 'Formal warning on record with 5–7 calendar days cure period.';
      statusIcon = Icons.info_outline_rounded;
    } else {
      badgeBg = const Color(0xFFF0FDF4);
      badgeColor = const Color(0xFF16A34A);
      statusTitle = 'Good Standing · 0 Strikes';
      statusDesc = 'Your commercial lease is in full compliance with property rules.';
      statusIcon = Icons.verified_user_rounded;
    }

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E1E1E) : Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: isDark ? const Color(0xFF262626) : const Color(0xFFE2E8F0),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
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
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: badgeBg,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(statusIcon, color: badgeColor, size: 24),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      statusTitle,
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w900,
                        color: isDark ? Colors.white : AppColors.midnightNavy,
                        letterSpacing: -0.2,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      statusDesc,
                      style: const TextStyle(
                        fontSize: 12,
                        color: Color(0xFF64748B),
                        height: 1.35,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),

          // 3-Strike Visual Stepper Meter
          Row(
            children: [
              _buildStrikeStep(1, 'Notice', strike >= 1, isCurrent: strike == 1),
              _buildStepConnector(strike >= 2),
              _buildStrikeStep(2, '₱500 Fine', strike >= 2, isCurrent: strike == 2),
              _buildStepConnector(strike >= 3),
              _buildStrikeStep(3, 'Eviction', strike >= 3, isCurrent: strike >= 3),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildStrikeStep(int stepNum, String label, bool active, {bool isCurrent = false}) {
    Color circleColor;
    Color textColor;
    if (stepNum == 3 && active) {
      circleColor = const Color(0xFFDC2626);
      textColor = const Color(0xFFDC2626);
    } else if (stepNum == 2 && active) {
      circleColor = const Color(0xFFD97706);
      textColor = const Color(0xFFD97706);
    } else if (stepNum == 1 && active) {
      circleColor = const Color(0xFF0284C7);
      textColor = const Color(0xFF0284C7);
    } else {
      circleColor = const Color(0xFFCBD5E1);
      textColor = const Color(0xFF94A3B8);
    }

    return Expanded(
      child: Column(
        children: [
          Container(
            width: 26,
            height: 26,
            decoration: BoxDecoration(
              color: active ? circleColor : Colors.white,
              shape: BoxShape.circle,
              border: Border.all(
                color: active ? circleColor : const Color(0xFFCBD5E1),
                width: 2,
              ),
            ),
            child: Center(
              child: active
                  ? const Icon(Icons.check, size: 14, color: Colors.white)
                  : Text(
                      '$stepNum',
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF94A3B8),
                      ),
                    ),
            ),
          ),
          const SizedBox(height: 6),
          Text(
            label,
            style: TextStyle(
              fontSize: 11,
              fontWeight: isCurrent ? FontWeight.w800 : FontWeight.w600,
              color: isCurrent ? textColor : const Color(0xFF64748B),
            ),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

  Widget _buildStepConnector(bool active) {
    return Container(
      width: 32,
      height: 2.5,
      margin: const EdgeInsets.only(bottom: 20),
      color: active ? const Color(0xFF0284C7) : const Color(0xFFE2E8F0),
    );
  }

  // ================= 2. REAL-WORLD CURE TIP =================
  Widget _buildRealWorldCureTip({bool isDark = false}) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E293B) : const Color(0xFFEFF6FF),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: isDark ? const Color(0xFF334155) : const Color(0xFFDBEAFE),
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(
            Icons.verified_rounded,
            color: Color(0xFF1E6BFF),
            size: 20,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: const [
                Text(
                  'How to Clear Violations & Maintain Good Standing',
                  style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF1E3A8A),
                  ),
                ),
                SizedBox(height: 3),
                Text(
                  'Violations are not permanent. Maintain a clean record for a 6-month rolling period with zero infractions to automatically reset your standing to 0 strikes. Monetary breaches can be cured by settling the balance.',
                  style: TextStyle(
                    fontSize: 11.5,
                    color: Color(0xFF3B82F6),
                    height: 1.4,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ================= 3. TAB SWITCHER =================
  Widget _buildTabSwitcher({bool isDark = false}) {
    return Container(
      height: 44,
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E1E1E) : const Color(0xFFEEF2F6),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          Expanded(
            child: InkWell(
              borderRadius: BorderRadius.circular(10),
              onTap: () => setState(() => _selectedTab = 0),
              child: Container(
                decoration: BoxDecoration(
                  color: _selectedTab == 0
                      ? (isDark ? const Color(0xFF262626) : Colors.white)
                      : Colors.transparent,
                  borderRadius: BorderRadius.circular(10),
                  boxShadow: _selectedTab == 0
                      ? [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.05),
                            blurRadius: 4,
                            offset: const Offset(0, 1),
                          ),
                        ]
                      : null,
                ),
                alignment: Alignment.center,
                child: Text(
                  'Citations History (${_violations.length})',
                  style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: _selectedTab == 0 ? FontWeight.w800 : FontWeight.w600,
                    color: _selectedTab == 0
                        ? (isDark ? Colors.white : AppColors.midnightNavy)
                        : const Color(0xFF64748B),
                  ),
                ),
              ),
            ),
          ),
          Expanded(
            child: InkWell(
              borderRadius: BorderRadius.circular(10),
              onTap: () => setState(() => _selectedTab = 1),
              child: Container(
                decoration: BoxDecoration(
                  color: _selectedTab == 1
                      ? (isDark ? const Color(0xFF262626) : Colors.white)
                      : Colors.transparent,
                  borderRadius: BorderRadius.circular(10),
                  boxShadow: _selectedTab == 1
                      ? [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.05),
                            blurRadius: 4,
                            offset: const Offset(0, 1),
                          ),
                        ]
                      : null,
                ),
                alignment: Alignment.center,
                child: Text(
                  'Disciplinary Policy',
                  style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: _selectedTab == 1 ? FontWeight.w800 : FontWeight.w600,
                    color: _selectedTab == 1
                        ? (isDark ? Colors.white : AppColors.midnightNavy)
                        : const Color(0xFF64748B),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ================= 4A. TAB: CITATIONS LIST =================
  Widget _buildCitationsList({bool isDark = false}) {
    if (_violations.isEmpty) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(vertical: 36, horizontal: 20),
        decoration: BoxDecoration(
          color: isDark ? const Color(0xFF1E1E1E) : Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: isDark ? const Color(0xFF262626) : const Color(0xFFE2E8F0),
          ),
        ),
        child: Column(
          children: const [
            Icon(Icons.verified_rounded, size: 48, color: Color(0xFF16A34A)),
            SizedBox(height: 12),
            Text(
              'No Violations on Record',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w800,
                color: AppColors.midnightNavy,
              ),
            ),
            SizedBox(height: 4),
            Text(
              'Your commercial stall is in excellent standing with zero recorded infractions.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 12.5, color: Color(0xFF64748B), height: 1.4),
            ),
          ],
        ),
      );
    }

    return Column(
      children: _violations.map((v) {
        final strike = v['strike'] is int
            ? v['strike'] as int
            : int.tryParse(v['strike']?.toString() ?? '1') ?? 1;
        final ack = v['acknowledged'];
        final isAck = ack == true || ack == 1 || ack == '1';
        final category = v['category']?.toString() ?? 'Lease Violation';
        final issuedAtStr = _formatDate(v['issued_at'] ?? v['created_at']);
        final fine = (v['fine_amount'] as num?)?.toDouble() ??
            (strike == 2 ? 500.0 : (strike >= 3 ? 1500.0 : 0.0));
        final icon = _getCategoryIcon(category);

        Color strikeColor;
        if (strike >= 3) {
          strikeColor = const Color(0xFFDC2626);
        } else if (strike == 2) {
          strikeColor = const Color(0xFFD97706);
        } else {
          strikeColor = const Color(0xFF0284C7);
        }

        return Container(
          margin: const EdgeInsets.only(bottom: 12),
          decoration: BoxDecoration(
            color: isDark ? const Color(0xFF1E1E1E) : Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(
              color: isDark ? const Color(0xFF262626) : const Color(0xFFE2E8F0),
            ),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.02),
                blurRadius: 6,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: Material(
            color: Colors.transparent,
            borderRadius: BorderRadius.circular(18),
            child: InkWell(
              borderRadius: BorderRadius.circular(18),
              onTap: () async {
                await ViolationNoticeSheet.show(
                  context,
                  violation: v,
                  isGated: false,
                );
                _fetchViolations();
              },
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Category Icon Squircle
                        Container(
                          width: 42,
                          height: 42,
                          decoration: BoxDecoration(
                            color: strikeColor.withValues(alpha: 0.12),
                            borderRadius: BorderRadius.circular(13),
                          ),
                          child: Icon(icon, color: strikeColor, size: 20),
                        ),
                        const SizedBox(width: 12),

                        // Title & Date
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                category,
                                style: TextStyle(
                                  fontSize: 14.5,
                                  fontWeight: FontWeight.w800,
                                  color: isDark ? Colors.white : AppColors.midnightNavy,
                                ),
                              ),
                              const SizedBox(height: 3),
                              Text(
                                'Issued: $issuedAtStr',
                                style: const TextStyle(
                                  fontSize: 11.5,
                                  color: Color(0xFF64748B),
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                            ],
                          ),
                        ),

                        // Strike Pill
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 3.5,
                          ),
                          decoration: BoxDecoration(
                            color: strikeColor.withValues(alpha: 0.12),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            'Strike $strike',
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                              color: strikeColor,
                            ),
                          ),
                        ),
                      ],
                    ),

                    if (fine > 0) ...[
                      const SizedBox(height: 10),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFEF3C7),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(Icons.monetization_on_outlined, size: 14, color: Color(0xFFD97706)),
                            const SizedBox(width: 6),
                            Text(
                              '₱${fine.toStringAsFixed(0)} Penalty Billed',
                              style: const TextStyle(
                                fontSize: 11.5,
                                fontWeight: FontWeight.w700,
                                color: Color(0xFF92400E),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],

                    const SizedBox(height: 12),
                    Divider(
                      height: 1,
                      color: isDark ? const Color(0xFF262626) : const Color(0xFFF1F5F9),
                    ),
                    const SizedBox(height: 10),

                    // Bottom Row: Acknowledged Badge & Appeal Action
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        // Acknowledged Badge
                        isAck
                            ? Row(
                                children: const [
                                  Icon(
                                    Icons.check_circle_rounded,
                                    color: Color(0xFF16A34A),
                                    size: 16,
                                  ),
                                  SizedBox(width: 5),
                                  Text(
                                    'Citation Acknowledged',
                                    style: TextStyle(
                                      fontSize: 11.5,
                                      fontWeight: FontWeight.w700,
                                      color: Color(0xFF15803D),
                                    ),
                                  ),
                                ],
                              )
                            : Row(
                                children: const [
                                  Icon(
                                    Icons.access_time_filled_rounded,
                                    color: Color(0xFFDC2626),
                                    size: 16,
                                  ),
                                  SizedBox(width: 5),
                                  Text(
                                    'Pending Read · Tap to Review',
                                    style: TextStyle(
                                      fontSize: 11.5,
                                      fontWeight: FontWeight.w700,
                                      color: Color(0xFFDC2626),
                                    ),
                                  ),
                                ],
                              ),

                        // Dispute / Message Lessor Action
                        InkWell(
                          borderRadius: BorderRadius.circular(6),
                          onTap: () {
                            Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => MessagesScreen(lease: widget.lease),
                              ),
                            );
                          },
                          child: Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                            child: Row(
                              children: const [
                                Text(
                                  'Appeal / Message',
                                  style: TextStyle(
                                    fontSize: 11.5,
                                    fontWeight: FontWeight.w700,
                                    color: Color(0xFF1E6BFF),
                                  ),
                                ),
                                SizedBox(width: 3),
                                Icon(Icons.arrow_forward_ios_rounded, size: 9, color: Color(0xFF1E6BFF)),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      }).toList(),
    );
  }

  // ================= 4B. TAB: DISCIPLINARY POLICY =================
  Widget _buildPolicyTab({bool isDark = false}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // 3-Strike Escalation Ladder
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: isDark ? const Color(0xFF1E1E1E) : Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(
              color: isDark ? const Color(0xFF262626) : const Color(0xFFE2E8F0),
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'The 3-Strike Escalation Ladder',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                  color: isDark ? Colors.white : AppColors.midnightNavy,
                ),
              ),
              const SizedBox(height: 12),
              _buildPolicyLadderItem(
                number: '1',
                title: 'Strike 1: Notice to Comply',
                desc: 'Formal citation logged with a 5–7 calendar day cure period. Zero penalty if remedied promptly.',
                color: const Color(0xFF0284C7),
                bgColor: const Color(0xFFEFF6FF),
              ),
              const SizedBox(height: 10),
              _buildPolicyLadderItem(
                number: '2',
                title: 'Strike 2: Notice of Default & Fine',
                desc: '₱500 citation penalty applied to next billing. Written formal demand letter logged to ledger.',
                color: const Color(0xFFD97706),
                bgColor: const Color(0xFFFEF3C7),
              ),
              const SizedBox(height: 10),
              _buildPolicyLadderItem(
                number: '3',
                title: 'Strike 3: Material Breach & Eviction',
                desc: 'Formal Notice to Vacate served, contract cancelled, deposit forfeited, and eviction proceedings initiated.',
                color: const Color(0xFFDC2626),
                bgColor: const Color(0xFFFEF2F2),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),

        // Tracked Categories Card
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: isDark ? const Color(0xFF1E1E1E) : Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(
              color: isDark ? const Color(0xFF262626) : const Color(0xFFE2E8F0),
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Tracked Violation Categories',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                  color: isDark ? Colors.white : AppColors.midnightNavy,
                ),
              ),
              const SizedBox(height: 12),
              _buildCategoryRuleItem(
                title: 'Unpaid Rent Default',
                desc: 'Payment unpaid beyond contractual grace period or breach of payment restructuring plan.',
                icon: Icons.receipt_long_rounded,
              ),
              _buildCategoryRuleItem(
                title: 'Late Operating Hours',
                desc: 'Operating past designated commercial mall closing hours without overtime approval.',
                icon: Icons.schedule_rounded,
              ),
              _buildCategoryRuleItem(
                title: 'Improper Waste & Cleanliness',
                desc: 'Disposal of commercial trash in common areas or failure to maintain food sanitary hygiene.',
                icon: Icons.delete_outline_rounded,
              ),
              _buildCategoryRuleItem(
                title: 'Noise & Nuisance Complaint',
                desc: 'Amplified sound, rowdiness, or obstruction of customer walkways and common corridors.',
                icon: Icons.volume_up_rounded,
              ),
              _buildCategoryRuleItem(
                title: 'Unauthorized Modifications',
                desc: 'Major electrical tap-in, wall drilling, or structural alterations without lessor permit.',
                icon: Icons.construction_rounded,
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildPolicyLadderItem({
    required String number,
    required String title,
    required String desc,
    required Color color,
    required Color bgColor,
  }) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 24,
            height: 24,
            decoration: BoxDecoration(
              color: color,
              shape: BoxShape.circle,
            ),
            child: Center(
              child: Text(
                number,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  fontSize: 12,
                ),
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
                  style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w800,
                    color: color,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  desc,
                  style: const TextStyle(
                    fontSize: 11.5,
                    color: Color(0xFF334155),
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCategoryRuleItem({
    required String title,
    required String desc,
    required IconData icon,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: const Color(0xFF64748B)),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                    color: AppColors.midnightNavy,
                  ),
                ),
                const SizedBox(height: 1),
                Text(
                  desc,
                  style: const TextStyle(
                    fontSize: 11.5,
                    color: Color(0xFF64748B),
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
