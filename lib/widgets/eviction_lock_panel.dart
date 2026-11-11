import 'package:flutter/material.dart';
import '../theme.dart';

/// True when a lease is in Strike 3 eviction standing or terminated by the lessor.
bool isLeaseEvictionLocked(Map<String, dynamic>? lease) {
  if (lease == null) return false;
  return lease['is_eviction'] == true ||
      lease['is_terminated'] == true ||
      (lease['rent_status'] ?? '').toString() == 'terminated';
}

/// Concise label identifying one business / stall row in multi-lease switchers.
String leaseDisplayLabel(Map<String, dynamic> lease) {
  final biz = (lease['business_name'] ?? '')
      .toString()
      .replaceAll(RegExp(r'\s*\([^)]*\)\s*$'), '')
      .trim();
  final prop = (lease['property_name'] ?? '').toString().trim();
  if (biz.isNotEmpty && prop.isNotEmpty && biz.toLowerCase() != prop.toLowerCase()) {
    return '$biz • $prop';
  }
  return biz.isNotEmpty ? biz : (prop.isNotEmpty ? prop : 'Commercial Unit');
}

/// Shared Strike 3 Eviction / Lease Termination lock panel shown either full-screen
/// (single locked business) or inline below the swipeable card carousel (multi-business).
class EvictionLockPanel extends StatelessWidget {
  final Map<String, dynamic> lease;
  final VoidCallback onProceedToPayment;
  final VoidCallback onViewNotice;
  final VoidCallback? onRefresh;
  final String? cleanBusinessLabel;
  final VoidCallback? onSwitchToCleanBusiness;

  const EvictionLockPanel({
    super.key,
    required this.lease,
    required this.onProceedToPayment,
    required this.onViewNotice,
    this.onRefresh,
    this.cleanBusinessLabel,
    this.onSwitchToCleanBusiness,
  });

  String _formatMoney(num val) {
    final str = val.toStringAsFixed(0);
    final buffer = StringBuffer();
    for (int i = 0; i < str.length; i++) {
      if (i > 0 && (str.length - i) % 3 == 0) buffer.write(',');
      buffer.write(str[i]);
    }
    return buffer.toString();
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bool isTerminated = lease['is_terminated'] == true ||
        (lease['rent_status'] ?? '').toString() == 'terminated';
    final double baseRent = (lease['base_rent'] as num?)?.toDouble() ??
        (lease['rent'] as num?)?.toDouble() ??
        0.0;
    final double totalDue = (lease['total_due'] as num?)?.toDouble() ?? baseRent;
    final double penalty = (lease['penalty_amount'] as num?)?.toDouble() ?? 0.0;
    final double pendingRent =
        (lease['pending_rent_amount'] as num?)?.toDouble() ?? 0.0;
    final String monthsLabel =
        (lease['outstanding_months_label'] ?? '').toString().trim();
    final String propName =
        (lease['property_name'] ?? 'Commercial Unit').toString();
    final String bizName = (lease['business_name'] ?? '')
        .toString()
        .replaceAll(RegExp(r'\s*\([^)]*\)\s*$'), '')
        .trim();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (onSwitchToCleanBusiness != null) ...[
          InkWell(
            onTap: onSwitchToCleanBusiness,
            borderRadius: BorderRadius.circular(16),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              decoration: BoxDecoration(
                color: isDark ? const Color(0xFF0F172A) : const Color(0xFFEFF6FF),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(
                  color: isDark
                      ? const Color(0xFF1E6BFF).withValues(alpha: 0.45)
                      : const Color(0xFFBFDBFE),
                ),
              ),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(7),
                    decoration: BoxDecoration(
                      color: const Color(0xFF1E6BFF).withValues(alpha: 0.14),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Icon(
                      Icons.swap_horiz_rounded,
                      size: 18,
                      color: Color(0xFF1E6BFF),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Other Business Active',
                          style: TextStyle(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w800,
                            color: isDark ? Colors.white : const Color(0xFF1E3A8A),
                          ),
                        ),
                        Text(
                          (cleanBusinessLabel != null && cleanBusinessLabel!.isNotEmpty)
                              ? 'Swipe card or tap to open $cleanBusinessLabel'
                              : 'Swipe the card above to access your clean business',
                          style: TextStyle(
                            fontSize: 11.5,
                            fontWeight: FontWeight.w500,
                            color: isDark ? const Color(0xFF94A3B8) : const Color(0xFF1D4ED8),
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ),
                  ),
                  const Icon(
                    Icons.arrow_forward_ios_rounded,
                    size: 13,
                    color: Color(0xFF1E6BFF),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 14),
        ],
        Container(
          padding: const EdgeInsets.all(22),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [Color(0xFF991B1B), Color(0xFF7F1D1D)],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(22),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF991B1B).withValues(alpha: 0.25),
                blurRadius: 16,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 4,
                ),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.18),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  isTerminated
                      ? 'LEASE TERMINATED'
                      : 'STRIKE 3 • EVICTION NOTICE',
                  style: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w900,
                    color: Colors.white,
                    letterSpacing: 0.7,
                  ),
                ),
              ),
              const SizedBox(height: 14),
              Text(
                propName,
                style: const TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w900,
                  color: Colors.white,
                ),
              ),
              if (bizName.isNotEmpty) ...[
                const SizedBox(height: 4),
                Text(
                  bizName,
                  style: TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w600,
                    color: Colors.white.withValues(alpha: 0.85),
                  ),
                ),
              ],
              const SizedBox(height: 14),
              Text(
                isTerminated
                    ? 'This lease has been terminated by the lessor. Other features for this business are locked.'
                    : 'This business has reached Strike 3 eviction standing. Other features are locked for this lease and only the Rent Payment page and Eviction Notice remain accessible until your overdue balance and penalties are settled and verified.',
                style: TextStyle(
                  fontSize: 13,
                  height: 1.45,
                  color: Colors.white.withValues(alpha: 0.92),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: isDark ? const Color(0xFF161616) : Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(
              color: isDark ? const Color(0xFF2B2B2B) : const Color(0xFFFECACA),
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Outstanding Balance to Unlock Business',
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: isDark ? const Color(0xFF94A3B8) : AppColors.ink500,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                '₱${_formatMoney(totalDue)}',
                style: TextStyle(
                  fontSize: 30,
                  fontWeight: FontWeight.w900,
                  color: isDark ? Colors.white : AppColors.error,
                ),
              ),
              if (monthsLabel.isNotEmpty) ...[
                const SizedBox(height: 4),
                Text(
                  'Periods Covered: $monthsLabel',
                  style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w600,
                    color: isDark ? const Color(0xFFCBD5E1) : AppColors.ink700,
                  ),
                ),
              ],
              if (penalty > 0) ...[
                const SizedBox(height: 4),
                Text(
                  'Includes ₱${_formatMoney(penalty)} Strike Penalty Fines',
                  style: const TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                    color: AppColors.error,
                  ),
                ),
              ],
              if (pendingRent > 0) ...[
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFEF3C7),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(
                    '₱${_formatMoney(pendingRent)} payment submitted and awaiting lessor verification. Once verified, access unlocks automatically.',
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: Color(0xFF92400E),
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 18),
        if (!isTerminated) ...[
          ElevatedButton.icon(
            onPressed: onProceedToPayment,
            icon: const Icon(Icons.payment_rounded, size: 20),
            label: Text(
              totalDue > 0
                  ? 'Proceed to Payment (₱${_formatMoney(totalDue)})'
                  : 'Open Payment Page',
              style: const TextStyle(
                fontSize: 14.5,
                fontWeight: FontWeight.w800,
              ),
            ),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.error,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(vertical: 15),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
              ),
            ),
          ),
          const SizedBox(height: 10),
        ],
        OutlinedButton.icon(
          onPressed: onViewNotice,
          icon: const Icon(Icons.description_outlined, size: 18),
          label: const Text(
            'View Official Eviction & Violation Notice',
            style: TextStyle(fontWeight: FontWeight.w700),
          ),
          style: OutlinedButton.styleFrom(
            foregroundColor: isDark ? Colors.white : AppColors.midnightNavy,
            side: BorderSide(
              color: isDark ? const Color(0xFF333333) : AppColors.border,
            ),
            padding: const EdgeInsets.symmetric(vertical: 14),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14),
            ),
          ),
        ),
        if (onRefresh != null) ...[
          const SizedBox(height: 10),
          TextButton.icon(
            onPressed: onRefresh,
            icon: const Icon(Icons.refresh_rounded, size: 18),
            label: const Text('Refresh Standing & Check Verification'),
          ),
        ],
      ],
    );
  }
}

