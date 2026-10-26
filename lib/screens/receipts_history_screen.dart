import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../services/api_service.dart';
import '../theme.dart';

class ReceiptsHistoryScreen extends StatefulWidget {
  final Map<String, dynamic> lease;
  final double rentAmount;

  const ReceiptsHistoryScreen({
    super.key,
    required this.lease,
    this.rentAmount = 12000.0,
  });

  @override
  State<ReceiptsHistoryScreen> createState() => _ReceiptsHistoryScreenState();
}

class _ReceiptsHistoryScreenState extends State<ReceiptsHistoryScreen> {
  bool _loading = true;
  List<Map<String, dynamic>> _transactions = [];
  bool _sortAscending = false; // false = newest first (desc), true = oldest first (asc)
  String _dateFilter = 'all'; // 'all', 'this_month', 'custom'
  DateTimeRange? _customDateRange;

  @override
  void initState() {
    super.initState();
    _loadTransactions();
  }

  Future<void> _loadTransactions() async {
    setState(() => _loading = true);
    try {
      final tid = widget.lease['tenant_id'] ?? widget.lease['id'];
      final int? tenantId = tid is int ? tid : (tid != null ? int.tryParse(tid.toString()) : null);
      final list = await ApiService.fetchPayments(tenantId: tenantId);
      final parsed = <Map<String, dynamic>>[];

      for (final item in list) {
        if (item is Map) {
          final map = Map<String, dynamic>.from(item);
          parsed.add(_normalizeTransaction(map));
        }
      }

      parsed.sort(
        (a, b) => (b['paid_at_raw'] ?? '').toString().compareTo(
          (a['paid_at_raw'] ?? '').toString(),
        ),
      );

      if (mounted) {
        setState(() {
          _transactions = parsed;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _transactions = [];
          _loading = false;
        });
      }
    }
  }

  Map<String, dynamic> _normalizeTransaction(Map<String, dynamic> raw) {
    final amt = (raw['amount'] as num?)?.toDouble() ?? widget.rentAmount;
    final note = (raw['note'] ?? '').toString();
    final target = (raw['target'] ?? '').toString();
    final rawDate = (raw['paid_at'] ?? '').toString();
    var ref = (raw['reference_no'] ?? raw['ref_no'] ?? '').toString().trim();
    if (ref.isEmpty && note.contains('Ref:')) {
      final match = RegExp(r'Ref:\s*([^|\r\n]+)').firstMatch(note);
      if (match != null) {
        ref = match.group(1)?.trim() ?? '';
      }
    }

    final isOverdue =
        target.contains('overdue') ||
        note.toLowerCase().contains('overdue') ||
        note.toLowerCase().contains('past due') ||
        target == 'overdue_october';

    DateTime dt;
    try {
      dt = DateTime.parse(rawDate);
    } catch (_) {
      dt = DateTime.now();
    }

    final formattedDate = _formatDateHeader(dt);
    final formattedTime = _formatTime(dt);
    final refNo = ref.isNotEmpty
        ? ref
        : 'REF-${raw['id'] ?? (dt.millisecondsSinceEpoch % 100000000)}';

    final method = (raw['method'] ?? '').toString().toLowerCase();
    final isDepositAuto = method == 'security_deposit' || note.contains('[AUTO-DEPOSIT]') || note.toLowerCase().contains('security deposit');

    final rawStatus = (raw['status'] ?? '').toString().toLowerCase();
    final isPending = !isDepositAuto && (rawStatus == 'pending' ||
        note.toUpperCase().contains('[PENDING]') ||
        note.toLowerCase().contains('pending verification'));
    final status = isDepositAuto ? 'approved' : (isPending ? 'pending' : (rawStatus.isNotEmpty ? rawStatus : 'approved'));

    final title = isDepositAuto
        ? 'Automated Escrow Rent Settlement'
        : (isPending
            ? (isOverdue ? 'Past Due Payment (Pending)' : 'Payment Pending Verification')
            : (isOverdue ? 'Past Due Payment' : 'Payment Successful'));

    final typeLabel = isDepositAuto
        ? 'Security Deposit (Escrow Auto-Cover)'
        : (isPending
            ? (isOverdue ? 'Past Due Payment · Pending Verification' : 'Rent Payment · Pending Verification')
            : (isOverdue ? 'Past Due Payment (Beyond Due Date)' : 'Verified Rent Payment'));

    return {
      'id': raw['id'],
      'amount': amt,
      'is_overdue': isOverdue,
      'is_deposit_auto': isDepositAuto,
      'title': title,
      'type_label': typeLabel,
      'date_header': formattedDate,
      'time_str': formattedTime,
      'full_datetime': '$formattedDate · $formattedTime',
      'ref_no': refNo,
      'paid_at_raw': rawDate.isNotEmpty ? rawDate : dt.toIso8601String(),
      'status': status,
    };
  }

  String _formatDateHeader(DateTime dt) {
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
    final day = dt.day.toString().padLeft(2, '0');
    final m = months[dt.month - 1];
    return '$day $m ${dt.year}';
  }

  String _formatTime(DateTime dt) {
    int hour = dt.hour;
    final period = hour >= 12 ? 'PM' : 'AM';
    if (hour == 0) hour = 12;
    if (hour > 12) hour -= 12;
    final minute = dt.minute.toString().padLeft(2, '0');
    final hStr = hour.toString().padLeft(2, '0');
    return '$hStr:$minute $period';
  }

  String _formatMoney(num val) {
    final str = val.toStringAsFixed(2);
    final parts = str.split('.');
    final whole = parts[0];
    final dec = parts[1];
    final buffer = StringBuffer();
    for (int i = 0; i < whole.length; i++) {
      if (i > 0 && (whole.length - i) % 3 == 0) {
        buffer.write(',');
      }
      buffer.write(whole[i]);
    }
    return '${buffer.toString()}.$dec';
  }

  DateTime _parseDate(Map<String, dynamic> tx) {
    final raw = tx['paid_at_raw']?.toString() ?? '';
    return DateTime.tryParse(raw) ?? DateTime.now();
  }

  String get _filterLabel {
    switch (_dateFilter) {
      case 'last_week':
        return 'Last Week';
      case 'last_3_weeks':
        return 'Last 3 Weeks';
      case 'last_month':
        return 'Last Month';
      case 'last_3_months':
        return 'Last 3 Months';
      case 'custom':
        if (_customDateRange != null) {
          const m = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
          final s = _customDateRange!.start;
          final e = _customDateRange!.end;
          return '${s.day} ${m[s.month - 1]} - ${e.day} ${m[e.month - 1]}';
        }
        return 'Custom Range';
      default:
        return 'All Time';
    }
  }

  // Get filtered and sorted list of transactions (Pre-computed & fast)
  List<Map<String, dynamic>> _getFilteredAndSortedTransactions() {
    var list = List<Map<String, dynamic>>.from(_transactions);
    final now = DateTime.now();

    // 1. Filter by Timeline Suggestion
    if (_dateFilter == 'last_week') {
      final cutoff = now.subtract(const Duration(days: 7));
      list = list.where((tx) => _parseDate(tx).isAfter(cutoff)).toList();
    } else if (_dateFilter == 'last_3_weeks') {
      final cutoff = now.subtract(const Duration(days: 21));
      list = list.where((tx) => _parseDate(tx).isAfter(cutoff)).toList();
    } else if (_dateFilter == 'last_month') {
      final cutoff = now.subtract(const Duration(days: 30));
      list = list.where((tx) => _parseDate(tx).isAfter(cutoff)).toList();
    } else if (_dateFilter == 'last_3_months') {
      final cutoff = now.subtract(const Duration(days: 90));
      list = list.where((tx) => _parseDate(tx).isAfter(cutoff)).toList();
    } else if (_dateFilter == 'custom' && _customDateRange != null) {
      final start = DateTime(
        _customDateRange!.start.year,
        _customDateRange!.start.month,
        _customDateRange!.start.day,
      );
      final end = DateTime(
        _customDateRange!.end.year,
        _customDateRange!.end.month,
        _customDateRange!.end.day,
        23,
        59,
        59,
      );
      list = list.where((tx) {
        final dt = _parseDate(tx);
        return !dt.isBefore(start) && !dt.isAfter(end);
      }).toList();
    }

    // 2. Sort Order (Newest First vs Oldest First)
    list.sort((a, b) {
      final dtA = _parseDate(a);
      final dtB = _parseDate(b);
      return _sortAscending ? dtA.compareTo(dtB) : dtB.compareTo(dtA);
    });

    return list;
  }

  // Group transactions by date_header preserving the sorted order
  Map<String, List<Map<String, dynamic>>> _groupByDate(List<Map<String, dynamic>> source) {
    final groups = <String, List<Map<String, dynamic>>>{};
    for (final tx in source) {
      final header = tx['date_header'] as String? ?? 'Recent';
      groups.putIfAbsent(header, () => []).add(tx);
    }
    return groups;
  }

  int _daysInMonth(DateTime month) {
    return DateTime(month.year, month.month + 1, 0).day;
  }

  int _firstWeekdayOffset(DateTime month) {
    return DateTime(month.year, month.month, 1).weekday % 7;
  }

  String _getMonthName(int month) {
    const months = [
      'January', 'February', 'March', 'April', 'May', 'June',
      'July', 'August', 'September', 'October', 'November', 'December'
    ];
    return months[(month - 1).clamp(0, 11)];
  }

  String _getShortMonth(int month) {
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return months[(month - 1).clamp(0, 11)];
  }

  // Custom Calendar Picker that matches the ScheduleScreen (Calendar Nav) design exactly
  void _showScheduleStyleCalendarPicker() {
    final now = DateTime.now();
    DateTime pickerMonth = _customDateRange?.start ?? DateTime(now.year, now.month, 1);
    pickerMonth = DateTime(pickerMonth.year, pickerMonth.month, 1);
    DateTime? rangeStart = _customDateRange?.start;
    DateTime? rangeEnd = _customDateRange?.end;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setPickerState) {
          final isCurrentMonth = pickerMonth.year == now.year && pickerMonth.month == now.month;
          final totalDays = _daysInMonth(pickerMonth);
          final offset = _firstWeekdayOffset(pickerMonth);
          final totalCells = offset + totalDays;

          String rangeText;
          if (rangeStart != null && rangeEnd != null) {
            rangeText = '${rangeStart!.day} ${_getShortMonth(rangeStart!.month)} ${rangeStart!.year} — ${rangeEnd!.day} ${_getShortMonth(rangeEnd!.month)} ${rangeEnd!.year}';
          } else if (rangeStart != null) {
            rangeText = 'From ${rangeStart!.day} ${_getShortMonth(rangeStart!.month)} (Select end date)';
          } else {
            rangeText = 'Tap dates to select timeline range';
          }

          return Container(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
            ),
            child: SafeArea(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  // Drag Handle
                  Container(
                    width: 42,
                    height: 4,
                    decoration: BoxDecoration(
                      color: const Color(0xFFCBD5E1),
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                  const SizedBox(height: 14),

                  // Header with Title and Clear
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Select Date Range',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w900,
                          color: Color(0xFF0B1B3D),
                          letterSpacing: -0.3,
                        ),
                      ),
                      if (rangeStart != null)
                        GestureDetector(
                          onTap: () {
                            setPickerState(() {
                              rangeStart = null;
                              rangeEnd = null;
                            });
                          },
                          child: const Text(
                            'Clear',
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                              color: Color(0xFF005CEE),
                            ),
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 8),

                  // Current Selection Display
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                    decoration: BoxDecoration(
                      color: const Color(0xFFEFF6FF),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFFDBEAFE)),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.date_range_rounded, size: 16, color: Color(0xFF005CEE)),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            rangeText,
                            style: const TextStyle(
                              fontSize: 12.5,
                              fontWeight: FontWeight.w700,
                              color: Color(0xFF005CEE),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),

                  // Month Navigation Header (Exact ScheduleScreen design)
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      IconButton(
                        icon: const Icon(Icons.chevron_left_rounded, color: Color(0xFF0B1B3D), size: 26),
                        onPressed: () {
                          setPickerState(() {
                            pickerMonth = DateTime(pickerMonth.year, pickerMonth.month - 1, 1);
                          });
                        },
                      ),
                      Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            '${_getMonthName(pickerMonth.month)} ${pickerMonth.year}',
                            style: const TextStyle(
                              fontSize: 17,
                              fontWeight: FontWeight.w800,
                              color: Color(0xFF0B1B3D),
                            ),
                          ),
                          if (!isCurrentMonth) ...[
                            const SizedBox(width: 8),
                            GestureDetector(
                              onTap: () {
                                setPickerState(() {
                                  pickerMonth = DateTime(now.year, now.month, 1);
                                });
                              },
                              child: Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                decoration: BoxDecoration(
                                  color: AppColors.electricBlue.withValues(alpha: 0.1),
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(color: AppColors.electricBlue.withValues(alpha: 0.3)),
                                ),
                                child: const Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Icon(Icons.today_rounded, size: 12, color: AppColors.electricBlue),
                                    SizedBox(width: 4),
                                    Text(
                                      'Today',
                                      style: TextStyle(
                                        fontSize: 11,
                                        fontWeight: FontWeight.w700,
                                        color: AppColors.electricBlue,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),
                      IconButton(
                        icon: const Icon(Icons.chevron_right_rounded, color: Color(0xFF0B1B3D), size: 26),
                        onPressed: () {
                          setPickerState(() {
                            pickerMonth = DateTime(pickerMonth.year, pickerMonth.month + 1, 1);
                          });
                        },
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),

                  // Days of Week Header (Sun - Sat, exact ScheduleScreen design)
                  Row(
                    children: const ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
                        .map((d) => Expanded(
                              child: Text(
                                d,
                                textAlign: TextAlign.center,
                                style: const TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                  color: Color(0xFF94A3B8),
                                ),
                              ),
                            ))
                        .toList(),
                  ),
                  const SizedBox(height: 6),

                  // Calendar Grid (Exact ScheduleScreen styling with Range highlighting)
                  GridView.builder(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 7,
                      childAspectRatio: 1.15,
                    ),
                    itemCount: totalCells,
                    itemBuilder: (context, index) {
                      if (index < offset) {
                        return const SizedBox.shrink();
                      }

                      final day = index - offset + 1;
                      final date = DateTime(pickerMonth.year, pickerMonth.month, day);
                      final isToday = date.year == now.year && date.month == now.month && date.day == now.day;

                      final isStart = rangeStart != null &&
                          rangeStart!.year == date.year &&
                          rangeStart!.month == date.month &&
                          rangeStart!.day == date.day;

                      final isEnd = rangeEnd != null &&
                          rangeEnd!.year == date.year &&
                          rangeEnd!.month == date.month &&
                          rangeEnd!.day == date.day;

                      final isInRange = rangeStart != null &&
                          rangeEnd != null &&
                          date.isAfter(rangeStart!) &&
                          date.isBefore(rangeEnd!);

                      BoxDecoration decoration;
                      Color textColor;
                      FontWeight fontWeight;

                      if (isStart || isEnd) {
                        decoration = BoxDecoration(
                          color: const Color(0xFF0B1B3D),
                          shape: BoxShape.circle,
                          boxShadow: [
                            BoxShadow(
                              color: const Color(0xFF0B1B3D).withValues(alpha: 0.25),
                              blurRadius: 6,
                              offset: const Offset(0, 2),
                            ),
                          ],
                        );
                        textColor = Colors.white;
                        fontWeight = FontWeight.w900;
                      } else if (isInRange) {
                        decoration = BoxDecoration(
                          color: const Color(0xFFEFF6FF),
                          shape: BoxShape.circle,
                          border: Border.all(color: const Color(0xFFBFDBFE), width: 1),
                        );
                        textColor = const Color(0xFF005CEE);
                        fontWeight = FontWeight.w800;
                      } else if (isToday) {
                        decoration = BoxDecoration(
                          color: AppColors.electricBlue.withValues(alpha: 0.12),
                          shape: BoxShape.circle,
                          border: Border.all(color: AppColors.electricBlue, width: 2.0),
                        );
                        textColor = AppColors.electricBlue;
                        fontWeight = FontWeight.w900;
                      } else {
                        decoration = const BoxDecoration(color: Colors.transparent, shape: BoxShape.circle);
                        textColor = const Color(0xFF0B1B3D);
                        fontWeight = FontWeight.w600;
                      }

                      return GestureDetector(
                        onTap: () {
                          setPickerState(() {
                            if (rangeStart == null || (rangeStart != null && rangeEnd != null)) {
                              rangeStart = date;
                              rangeEnd = null;
                            } else if (rangeStart != null && rangeEnd == null) {
                              if (date.isBefore(rangeStart!)) {
                                rangeStart = date;
                              } else {
                                rangeEnd = date;
                              }
                            }
                          });
                        },
                        behavior: HitTestBehavior.opaque,
                        child: Center(
                          child: Container(
                            width: 36,
                            height: 36,
                            decoration: decoration,
                            alignment: Alignment.center,
                            child: Text(
                              '$day',
                              style: TextStyle(
                                fontSize: 14.5,
                                fontWeight: fontWeight,
                                color: textColor,
                              ),
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                  const SizedBox(height: 16),

                  // Apply Range Button
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: rangeStart == null
                          ? null
                          : () {
                              final finalStart = rangeStart!;
                              final finalEnd = rangeEnd ?? rangeStart!;
                              setState(() {
                                _customDateRange = DateTimeRange(start: finalStart, end: finalEnd);
                                _dateFilter = 'custom';
                              });
                              Navigator.pop(ctx);
                            },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF0B1B3D),
                        foregroundColor: Colors.white,
                        disabledBackgroundColor: const Color(0xFFE2E8F0),
                        disabledForegroundColor: const Color(0xFF94A3B8),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                        elevation: 0,
                      ),
                      child: const Text(
                        'Apply Date Filter',
                        style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800),
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

  // Filter & Sort Bottom Sheet with timeline suggestions (Last Week, Last 3 Weeks, Last Month, etc.)
  void _showFilterSortSheet() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setSheetState) {
          return Container(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 28),
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
            ),
            child: SafeArea(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Handle
                  Center(
                    child: Container(
                      width: 42,
                      height: 4,
                      decoration: BoxDecoration(
                        color: const Color(0xFFCBD5E1),
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Header with Reset button
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Filter & Sort',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w900,
                          color: AppColors.midnightNavy,
                        ),
                      ),
                      if (_dateFilter != 'all' || _sortAscending)
                        TextButton(
                          onPressed: () {
                            setSheetState(() {
                              _dateFilter = 'all';
                              _sortAscending = false;
                              _customDateRange = null;
                            });
                            setState(() {});
                            Navigator.pop(ctx);
                          },
                          child: const Text(
                            'Reset to Default',
                            style: TextStyle(
                              fontSize: 12.5,
                              fontWeight: FontWeight.w700,
                              color: Color(0xFF005CEE),
                            ),
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 16),

                  // Section 1: Date Filter Suggestions
                  const Text(
                    'DATE TIMELINE',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF64748B),
                      letterSpacing: 0.8,
                    ),
                  ),
                  const SizedBox(height: 10),

                  _buildFilterOptionTile(
                    title: 'All Time',
                    subtitle: 'Show all payment transactions',
                    isSelected: _dateFilter == 'all',
                    onTap: () {
                      setState(() => _dateFilter = 'all');
                      Navigator.pop(ctx);
                    },
                  ),
                  _buildFilterOptionTile(
                    title: 'Last Week',
                    subtitle: 'Past 7 days',
                    isSelected: _dateFilter == 'last_week',
                    onTap: () {
                      setState(() => _dateFilter = 'last_week');
                      Navigator.pop(ctx);
                    },
                  ),
                  _buildFilterOptionTile(
                    title: 'Last 3 Weeks',
                    subtitle: 'Past 21 days',
                    isSelected: _dateFilter == 'last_3_weeks',
                    onTap: () {
                      setState(() => _dateFilter = 'last_3_weeks');
                      Navigator.pop(ctx);
                    },
                  ),
                  _buildFilterOptionTile(
                    title: 'Last Month',
                    subtitle: 'Past 30 days',
                    isSelected: _dateFilter == 'last_month',
                    onTap: () {
                      setState(() => _dateFilter = 'last_month');
                      Navigator.pop(ctx);
                    },
                  ),
                  _buildFilterOptionTile(
                    title: 'Last 3 Months',
                    subtitle: 'Past 90 days',
                    isSelected: _dateFilter == 'last_3_months',
                    onTap: () {
                      setState(() => _dateFilter = 'last_3_months');
                      Navigator.pop(ctx);
                    },
                  ),
                  _buildFilterOptionTile(
                    title: 'Custom Date Range...',
                    subtitle: _dateFilter == 'custom' ? _filterLabel : 'Select start and end dates',
                    isSelected: _dateFilter == 'custom',
                    icon: Icons.calendar_today_rounded,
                    onTap: () {
                      Navigator.pop(ctx);
                      _showScheduleStyleCalendarPicker();
                    },
                  ),

                  const SizedBox(height: 16),
                  const Divider(color: Color(0xFFE2E8F0)),
                  const SizedBox(height: 12),

                  // Section 2: Sort Order
                  const Text(
                    'SORT BY DATE',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF64748B),
                      letterSpacing: 0.8,
                    ),
                  ),
                  const SizedBox(height: 10),

                  Row(
                    children: [
                      Expanded(
                        child: _buildSortPill(
                          label: 'Newest First',
                          icon: Icons.arrow_downward_rounded,
                          isSelected: !_sortAscending,
                          onTap: () {
                            setState(() => _sortAscending = false);
                            Navigator.pop(ctx);
                          },
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _buildSortPill(
                          label: 'Oldest First',
                          icon: Icons.arrow_upward_rounded,
                          isSelected: _sortAscending,
                          onTap: () {
                            setState(() => _sortAscending = true);
                            Navigator.pop(ctx);
                          },
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildFilterOptionTile({
    required String title,
    required String subtitle,
    required bool isSelected,
    required VoidCallback onTap,
    IconData? icon,
  }) {
    return InkWell(
      borderRadius: BorderRadius.circular(12),
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
        child: Row(
          children: [
            Icon(
              icon ?? (isSelected ? Icons.radio_button_checked : Icons.radio_button_off),
              size: 20,
              color: isSelected ? const Color(0xFF005CEE) : const Color(0xFF94A3B8),
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
                      fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                      color: isSelected ? const Color(0xFF005CEE) : const Color(0xFF0F172A),
                    ),
                  ),
                  const SizedBox(height: 1),
                  Text(
                    subtitle,
                    style: const TextStyle(fontSize: 11.5, color: Color(0xFF64748B)),
                  ),
                ],
              ),
            ),
            if (isSelected)
              const Icon(Icons.check_rounded, size: 18, color: Color(0xFF005CEE)),
          ],
        ),
      ),
    );
  }

  Widget _buildSortPill({
    required String label,
    required IconData icon,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFFEFF6FF) : const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: isSelected ? const Color(0xFF005CEE) : const Color(0xFFE2E8F0),
            width: isSelected ? 1.5 : 1,
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              icon,
              size: 15,
              color: isSelected ? const Color(0xFF005CEE) : const Color(0xFF64748B),
            ),
            const SizedBox(width: 6),
            Text(
              label,
              style: TextStyle(
                fontSize: 12.5,
                fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                color: isSelected ? const Color(0xFF005CEE) : const Color(0xFF0F172A),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildTransactionTile(Map<String, dynamic> tx, int index, int total) {
    final isOverdue = tx['is_overdue'] == true;
    final isPending = tx['status'] == 'pending';
    final isDepositAuto = tx['is_deposit_auto'] == true;
    final title = tx['title'] as String;
    final amount = (tx['amount'] as num?)?.toDouble() ?? 0.0;
    final timeStr = tx['time_str'] as String;
    final refNo = tx['ref_no'] as String;

    return InkWell(
      onTap: () => _showGcashReceipt(tx),
      borderRadius: BorderRadius.vertical(
        top: index == 0 ? const Radius.circular(16) : Radius.zero,
        bottom: index == total - 1 ? const Radius.circular(16) : Radius.zero,
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        child: Row(
          children: [
            // Icon
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: isDepositAuto
                    ? const Color(0xFFEFF6FF)
                    : (isPending
                        ? const Color(0xFFF1F5F9)
                        : (isOverdue ? const Color(0xFFFEF2F2) : const Color(0xFFEFF6FF))),
                shape: BoxShape.circle,
                border: Border.all(
                  color: isDepositAuto
                      ? const Color(0xFFBFDBFE)
                      : (isPending
                          ? const Color(0xFFCBD5E1)
                          : (isOverdue ? const Color(0xFFFCA5A5) : const Color(0xFFBFDBFE))),
                  width: 1,
                ),
              ),
              child: Icon(
                isDepositAuto
                    ? Icons.shield_rounded
                    : (isPending
                        ? Icons.schedule_rounded
                        : (isOverdue ? Icons.error_outline_rounded : Icons.arrow_outward_rounded)),
                color: isDepositAuto
                    ? const Color(0xFF0A1832)
                    : (isPending
                        ? const Color(0xFF475569)
                        : (isOverdue ? const Color(0xFFEF4444) : const Color(0xFF005CEE))),
                size: 20,
              ),
            ),
            const SizedBox(width: 12),

            // Details
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          title,
                          style: TextStyle(
                            fontSize: 13.5,
                            fontWeight: FontWeight.w800,
                            color: isDepositAuto
                                ? AppColors.midnightNavy
                                : (isPending
                                    ? const Color(0xFF475569)
                                    : (isOverdue ? const Color(0xFFB91C1C) : AppColors.midnightNavy)),
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      if (isDepositAuto) ...[
                        const SizedBox(width: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: const Color(0xFFEFF6FF),
                            borderRadius: BorderRadius.circular(5),
                            border: Border.all(color: const Color(0xFFBFDBFE)),
                          ),
                          child: const Text(
                            'ESCROW',
                            style: TextStyle(
                              fontSize: 9,
                              fontWeight: FontWeight.w900,
                              color: Color(0xFF005CEE),
                              letterSpacing: 0.3,
                            ),
                          ),
                        ),
                      ] else if (isPending) ...[
                        const SizedBox(width: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF1F5F9),
                            borderRadius: BorderRadius.circular(5),
                            border: Border.all(color: const Color(0xFFCBD5E1)),
                          ),
                          child: const Text(
                            'PENDING',
                            style: TextStyle(
                              fontSize: 9,
                              fontWeight: FontWeight.w900,
                              color: Color(0xFF475569),
                              letterSpacing: 0.3,
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 3),
                  Text(
                    '$timeStr • Ref: $refNo',
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w500,
                      color: Color(0xFF64748B),
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),

            // Amount
            Text(
              '- ₱${_formatMoney(amount)}',
              style: TextStyle(
                fontSize: 14.5,
                fontWeight: FontWeight.w900,
                color: isOverdue ? const Color(0xFFDC2626) : const Color(0xFF0F172A),
                letterSpacing: -0.3,
              ),
            ),
            const SizedBox(width: 4),
            const Icon(Icons.chevron_right_rounded, size: 18, color: Color(0xFF94A3B8)),
          ],
        ),
      ),
    );
  }


  void _showGcashReceipt(Map<String, dynamic> tx) {
    final isOverdue = tx['is_overdue'] == true;
    final isPending = tx['status'] == 'pending';
    final isDepositAuto = tx['is_deposit_auto'] == true;
    final typeLabel = tx['type_label'] as String? ??
        (isDepositAuto
            ? 'Security Deposit (Escrow Auto-Cover)'
            : (isPending
                ? 'Rent Payment · Pending Verification'
                : (isOverdue ? 'Past Due Payment' : 'Verified Rent Payment')));
    final amount = (tx['amount'] as num?)?.toDouble() ?? 0.0;
    final dateTime = tx['full_datetime'] as String? ?? '';
    final refNo = tx['ref_no'] as String? ?? '';

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
        decoration: const BoxDecoration(
          color: Color(0xFFF1F5F9),
          borderRadius: BorderRadius.only(
            topLeft: Radius.circular(28),
            topRight: Radius.circular(28),
          ),
        ),
        child: SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Bottom sheet handle bar
              Container(
                width: 44,
                height: 4,
                decoration: BoxDecoration(
                  color: const Color(0xFFCBD5E1),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const SizedBox(height: 18),

              // ================= GCASH-STYLE DIGITAL RECEIPT CARD =================
              Container(
                width: double.infinity,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20),
                  boxShadow: [
                    BoxShadow(
                      color: const Color(0xFF0F172A).withValues(alpha: 0.08),
                      blurRadius: 18,
                      offset: const Offset(0, 6),
                    ),
                  ],
                ),
                child: Column(
                  children: [
                    // Top Receipt Header Banner
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 16,
                        vertical: 12,
                      ),
                      decoration: BoxDecoration(
                        color: isDepositAuto
                            ? const Color(0xFF0A1832) // Luxury Midnight Navy for Escrow Auto
                            : (isPending
                                ? const Color(0xFF1E3A5F) // Refined Slate Navy for Pending
                                : (isOverdue ? const Color(0xFFDC2626) : const Color(0xFF005CEE))), // Classic GCash Blue
                        borderRadius: const BorderRadius.only(
                          topLeft: Radius.circular(20),
                          topRight: Radius.circular(20),
                        ),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Expanded(
                            child: Row(
                              children: [
                                Icon(
                                  isDepositAuto
                                      ? Icons.shield_rounded
                                      : (isPending ? Icons.pending_actions_rounded : Icons.receipt_long_rounded),
                                  color: Colors.white,
                                  size: 18,
                                ),
                                const SizedBox(width: 8),
                                Flexible(
                                  child: Text(
                                    isDepositAuto
                                        ? 'Escrow Settlement Receipt'
                                        : (isPending ? 'Pending Receipt' : 'Transaction Receipt'),
                                    style: const TextStyle(
                                      color: Colors.white,
                                      fontWeight: FontWeight.w800,
                                      fontSize: 13,
                                      letterSpacing: 0.2,
                                    ),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 3,
                            ),
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.22),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              isDepositAuto
                                  ? 'AUTO-SETTLED'
                                  : (isPending ? 'PENDING' : 'COMPLETED'),
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 10,
                                fontWeight: FontWeight.w900,
                                letterSpacing: 0.5,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),

                    Padding(
                      padding: const EdgeInsets.fromLTRB(22, 22, 22, 20),
                      child: Column(
                        children: [
                          // Status Icon Circle
                          Container(
                            width: 58,
                            height: 58,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: isDepositAuto
                                  ? const Color(0xFFEFF6FF)
                                  : (isPending
                                      ? const Color(0xFFF1F5F9)
                                      : (isOverdue
                                          ? const Color(0xFFFEF2F2)
                                          : const Color(0xFFECFDF5))),
                              border: Border.all(
                                color: isDepositAuto
                                    ? const Color(0xFFBFDBFE)
                                    : (isPending
                                        ? const Color(0xFFCBD5E1)
                                        : (isOverdue
                                            ? const Color(0xFFFCA5A5)
                                            : const Color(0xFFA7F3D0))),
                                width: 2,
                              ),
                            ),
                            child: Icon(
                              isDepositAuto
                                  ? Icons.shield_rounded
                                  : (isPending
                                      ? Icons.schedule_rounded
                                      : (isOverdue ? Icons.priority_high_rounded : Icons.check_rounded)),
                              size: 34,
                              color: isDepositAuto
                                  ? const Color(0xFF0A1832)
                                  : (isPending
                                      ? const Color(0xFF475569)
                                      : (isOverdue
                                          ? const Color(0xFFEF4444)
                                          : const Color(0xFF10B981))),
                            ),
                          ),
                          const SizedBox(height: 12),

                          // Transaction Title
                          Text(
                            isDepositAuto
                                ? 'Rent Payment Covered'
                                : (isPending
                                    ? (isOverdue ? 'Past Due Payment (Pending)' : 'Payment Pending Verification')
                                    : (isOverdue ? 'Past Due Payment' : 'Payment Successful')),
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w900,
                              color: AppColors.midnightNavy,
                              letterSpacing: -0.3,
                            ),
                          ),
                          if (isPending) ...[
                            const SizedBox(height: 6),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                              decoration: BoxDecoration(
                                color: const Color(0xFFF1F5F9),
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(color: const Color(0xFFCBD5E1)),
                              ),
                              child: const Text(
                                'Awaiting lessor verification before balance is credited',
                                textAlign: TextAlign.center,
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w700,
                                  color: Color(0xFF475569),
                                ),
                              ),
                            ),
                          ],
                          const SizedBox(height: 4),

                          // Date & Time
                          Text(
                            dateTime,
                            style: const TextStyle(
                              fontSize: 12.5,
                              fontWeight: FontWeight.w500,
                              color: Color(0xFF64748B),
                            ),
                          ),
                          const SizedBox(height: 16),

                          // Big Amount
                          Text(
                            '₱${_formatMoney(amount)}',
                            style: const TextStyle(
                              fontSize: 32,
                              fontWeight: FontWeight.w900,
                              color: AppColors.midnightNavy,
                              letterSpacing: -1.0,
                            ),
                          ),
                          const SizedBox(height: 20),

                          // Perforated / Dashed Divider Line
                          Row(
                            children: List.generate(
                              30,
                              (index) => Expanded(
                                child: Container(
                                  color: index % 2 == 0
                                      ? const Color(0xFFCBD5E1)
                                      : Colors.transparent,
                                  height: 1.5,
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(height: 18),

                          // Clean 3-Line Details
                          _buildReceiptRow(
                            label: 'Payment Method',
                            valueWidget: Text(
                              isDepositAuto
                                  ? 'Security Deposit (Escrow)'
                                  : typeLabel,
                              textAlign: TextAlign.end,
                              style: const TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w800,
                                color: AppColors.midnightNavy,
                              ),
                            ),
                          ),
                          const SizedBox(height: 12),

                          _buildReceiptRow(
                            label: 'Date and Time',
                            valueWidget: Text(
                              dateTime,
                              textAlign: TextAlign.end,
                              style: const TextStyle(
                                fontSize: 12.5,
                                fontWeight: FontWeight.w600,
                                color: Color(0xFF0F172A),
                              ),
                            ),
                          ),
                          const SizedBox(height: 12),

                          _buildReceiptRow(
                            label: 'Reference Number',
                            valueWidget: InkWell(
                              onTap: () {
                                Clipboard.setData(ClipboardData(text: refNo));
                                ScaffoldMessenger.of(context).showSnackBar(
                                  const SnackBar(
                                    content: Text('Reference number copied to clipboard!'),
                                    duration: Duration(seconds: 2),
                                  ),
                                );
                              },
                              borderRadius: BorderRadius.circular(6),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Text(
                                    refNo,
                                    style: const TextStyle(
                                      fontSize: 13,
                                      fontWeight: FontWeight.w800,
                                      color: Color(0xFF005CEE),
                                      letterSpacing: 0.3,
                                    ),
                                  ),
                                  const SizedBox(width: 4),
                                  const Icon(
                                    Icons.copy_rounded,
                                    size: 14,
                                    color: Color(0xFF005CEE),
                                  ),
                                ],
                              ),
                            ),
                          ),
                          const SizedBox(height: 12),

                          _buildReceiptRow(
                            label: 'Amount',
                            valueWidget: Text(
                              '₱${_formatMoney(amount)}',
                              textAlign: TextAlign.end,
                              style: const TextStyle(
                                fontSize: 13.5,
                                fontWeight: FontWeight.w800,
                                color: Color(0xFF0F172A),
                              ),
                            ),
                          ),
                          const SizedBox(height: 12),

                          _buildReceiptRow(
                            label: 'Date and Time',
                            valueWidget: Text(
                              dateTime,
                              textAlign: TextAlign.end,
                              style: const TextStyle(
                                fontSize: 12.5,
                                fontWeight: FontWeight.w600,
                                color: Color(0xFF0F172A),
                              ),
                            ),
                          ),
                          const SizedBox(height: 12),

                          _buildReceiptRow(
                            label: 'Reference Number',
                            valueWidget: InkWell(
                              onTap: () {
                                Clipboard.setData(ClipboardData(text: refNo));
                                ScaffoldMessenger.of(context).showSnackBar(
                                  const SnackBar(
                                    content: Text(
                                      'Reference number copied to clipboard!',
                                    ),
                                    duration: Duration(seconds: 2),
                                  ),
                                );
                              },
                              borderRadius: BorderRadius.circular(6),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Text(
                                    refNo,
                                    style: const TextStyle(
                                      fontSize: 13,
                                      fontWeight: FontWeight.w800,
                                      color: Color(0xFF005CEE),
                                      letterSpacing: 0.3,
                                    ),
                                  ),
                                  const SizedBox(width: 4),
                                  const Icon(
                                    Icons.copy_rounded,
                                    size: 14,
                                    color: Color(0xFF005CEE),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 20),

              // Action Buttons
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () {
                        Navigator.pop(ctx);
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(
                            content: Text(
                              'Receipt image saved to photos / device storage.',
                            ),
                            backgroundColor: Color(0xFF0A1832),
                          ),
                        );
                      },
                      icon: const Icon(Icons.file_download_outlined, size: 18),
                      label: const Text(
                        'Save Receipt',
                        style: TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 13,
                        ),
                      ),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: const Color(0xFF005CEE),
                        side: const BorderSide(
                          color: Color(0xFF005CEE),
                          width: 1.5,
                        ),
                        backgroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: () => Navigator.pop(ctx),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF0A1832),
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                        elevation: 0,
                      ),
                      child: const Text(
                        'Done',
                        style: TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 13,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildReceiptRow({
    required String label,
    required Widget valueWidget,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: const TextStyle(
            fontSize: 12.5,
            fontWeight: FontWeight.w600,
            color: Color(0xFF64748B),
          ),
        ),
        const SizedBox(width: 16),
        Flexible(child: valueWidget),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    final filteredTransactions = _getFilteredAndSortedTransactions();
    final grouped = _groupByDate(filteredTransactions);

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'Transactions',
              style: TextStyle(
                fontSize: 17,
                fontWeight: FontWeight.w900,
                color: AppColors.midnightNavy,
                letterSpacing: -0.3,
              ),
            ),
            if ((widget.lease['property_name'] ?? widget.lease['business_name'] ?? '').toString().isNotEmpty)
              Text(
                (widget.lease['property_name'] ?? widget.lease['business_name']).toString(),
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF64748B),
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
          ],
        ),
        centerTitle: true,
        backgroundColor: Colors.white,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        leading: const AppBackButton(),
        actions: [
          // Filter & Sort Trigger Icon with Active Indicator Pip
          Stack(
            alignment: Alignment.center,
            children: [
              IconButton(
                tooltip: 'Filter & Sort Timeline',
                icon: const Icon(Icons.tune_rounded, color: Color(0xFF0F172A)),
                onPressed: _showFilterSortSheet,
              ),
              if (_dateFilter != 'all' || _sortAscending)
                Positioned(
                  top: 10,
                  right: 10,
                  child: Container(
                    width: 8,
                    height: 8,
                    decoration: const BoxDecoration(
                      color: Color(0xFF005CEE),
                      shape: BoxShape.circle,
                    ),
                  ),
                ),
            ],
          ),
          IconButton(
            tooltip: 'Refresh',
            icon: const Icon(Icons.refresh_rounded, color: AppColors.ink700),
            onPressed: _loadTransactions,
          ),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(1),
          child: Container(color: const Color(0xFFE2E8F0), height: 1),
        ),
      ),
      body: Column(
        children: [
          // Active Filter Pill (Only shown when filter or sort is applied)
          if (_dateFilter != 'all' || _sortAscending)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              decoration: const BoxDecoration(
                color: Color(0xFFEFF6FF),
                border: Border(bottom: BorderSide(color: Color(0xFFBFDBFE))),
              ),
              child: Row(
                children: [
                  const Icon(Icons.filter_list_rounded, size: 14, color: Color(0xFF005CEE)),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      'Timeline: $_filterLabel • ${_sortAscending ? 'Oldest first' : 'Newest first'}',
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF005CEE),
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const SizedBox(width: 8),
                  GestureDetector(
                    onTap: () {
                      setState(() {
                        _dateFilter = 'all';
                        _sortAscending = false;
                        _customDateRange = null;
                      });
                    },
                    child: const Text(
                      'Clear',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF005CEE),
                        decoration: TextDecoration.underline,
                      ),
                    ),
                  ),
                ],
              ),
            ),

          // High-Performance Zero-Lag Transactions List
          Expanded(
            child: _loading
                ? const Center(
                    child: CircularProgressIndicator(color: AppColors.electricBlue),
                  )
                : _transactions.isEmpty
                ? Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: const [
                        Icon(
                          Icons.receipt_long_outlined,
                          size: 56,
                          color: Color(0xFF94A3B8),
                        ),
                        SizedBox(height: 12),
                        Text(
                          'No transactions recorded yet',
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w700,
                            color: Color(0xFF64748B),
                          ),
                        ),
                      ],
                    ),
                  )
                : filteredTransactions.isEmpty
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(
                            Icons.event_busy_rounded,
                            size: 50,
                            color: Color(0xFF94A3B8),
                          ),
                          const SizedBox(height: 12),
                          const Text(
                            'No receipts found for this timeline',
                            style: TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w700,
                              color: Color(0xFF0F172A),
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            'No payments match "$_filterLabel". Try choosing another date range.',
                            textAlign: TextAlign.center,
                            style: const TextStyle(fontSize: 13, color: Color(0xFF64748B)),
                          ),
                          const SizedBox(height: 16),
                          TextButton(
                            onPressed: () {
                              setState(() {
                                _dateFilter = 'all';
                                _customDateRange = null;
                              });
                            },
                            child: const Text('Show All Receipts'),
                          ),
                        ],
                      ),
                    ),
                  )
                : RefreshIndicator(
                    color: AppColors.electricBlue,
                    onRefresh: _loadTransactions,
                    child: ListView.builder(
                      physics: const BouncingScrollPhysics(),
                      padding: const EdgeInsets.symmetric(
                        horizontal: 16,
                        vertical: 16,
                      ),
                      itemCount: grouped.length,
                      itemBuilder: (context, index) {
                        final dateKey = grouped.keys.elementAt(index);
                        final items = grouped[dateKey] ?? [];

                        return Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Date Header
                            Padding(
                              padding: const EdgeInsets.only(left: 4, top: 10, bottom: 8),
                              child: Text(
                                dateKey,
                                style: const TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w800,
                                  color: Color(0xFF475569),
                                  letterSpacing: 0.1,
                                ),
                              ),
                            ),

                            // Fast Zero-Lag Group Card (Flat Column, No Nested ListView)
                            Container(
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(16),
                                border: Border.all(color: const Color(0xFFE2E8F0)),
                                boxShadow: [
                                  BoxShadow(
                                    color: const Color(0xFF0F172A).withValues(alpha: 0.03),
                                    blurRadius: 8,
                                    offset: const Offset(0, 2),
                                  ),
                                ],
                              ),
                              child: Column(
                                children: [
                                  for (int i = 0; i < items.length; i++) ...[
                                    if (i > 0)
                                      const Divider(
                                        height: 1,
                                        thickness: 0.8,
                                        indent: 56,
                                        endIndent: 16,
                                        color: Color(0xFFF1F5F9),
                                      ),
                                    _buildTransactionTile(items[i], i, items.length),
                                  ],
                                ],
                              ),
                            ),
                          ],
                        );
                      },
                    ),
                  ),
          ),
        ],
      ),
    );
  }
}
