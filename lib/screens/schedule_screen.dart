import 'package:flutter/material.dart';
import '../theme.dart';
import '../services/api_service.dart';
import 'tickets_list_screen.dart';

class ScheduleScreen extends StatefulWidget {
  final Map<String, dynamic>? lease;
  const ScheduleScreen({super.key, this.lease});

  @override
  State<ScheduleScreen> createState() => _ScheduleScreenState();
}

class _ScheduleScreenState extends State<ScheduleScreen> {
  late DateTime _currentMonth;
  DateTime? _selectedDate;
  bool _showAll = true; // Controls "View all" vs "View less"
  bool _loading = true;

  List<Map<String, dynamic>> _events = [];
  List<dynamic> _apiTickets = [];
  List<dynamic> _apiPayments = [];
  List<dynamic> _apiInstallments = [];

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _currentMonth = DateTime(now.year, now.month, 1);
    _selectedDate = DateTime(now.year, now.month, now.day);
    _fetchScheduleData();
  }

  @override
  void didUpdateWidget(covariant ScheduleScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.lease != widget.lease) {
      _buildEventsForCurrentMonth();
      _fetchScheduleData();
    }
  }

  Future<void> _fetchScheduleData() async {
    setState(() => _loading = true);
    try {
      final tid = widget.lease?['tenant_id'] ?? widget.lease?['id'];
      final int? tenantId = tid is int
          ? tid
          : (tid != null ? int.tryParse(tid.toString()) : null);
      final results = await Future.wait([
        ApiService.fetchTickets(tenantId: tenantId),
        ApiService.fetchPayments(tenantId: tenantId),
        ApiService.fetchInstallmentRequests(tenantId: tenantId),
      ]);
      if (mounted) {
        setState(() {
          _apiTickets = results[0];
          _apiPayments = results[1];
          _apiInstallments = results[2];
          _buildEventsForCurrentMonth();
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _buildEventsForCurrentMonth();
          _loading = false;
        });
      }
    }
  }

  String _getShortMonth(int month) {
    const months = [
      'JAN',
      'FEB',
      'MAR',
      'APR',
      'MAY',
      'JUN',
      'JUL',
      'AUG',
      'SEP',
      'OCT',
      'NOV',
      'DEC',
    ];
    return months[(month - 1).clamp(0, 11)];
  }

  IconData _getCategoryIcon(String category) {
    final cat = category.toLowerCase();
    if (cat.contains('elect') ||
        cat.contains('light') ||
        cat.contains('power')) {
      return Icons.bolt_rounded;
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

  void _buildEventsForCurrentMonth() {
    final list = <Map<String, dynamic>>[];
    final rentNum = (widget.lease?['rent'] as num?)?.toDouble() ?? 12000.0;

    // 1. Live Maintenance Tickets from API
    for (final t in _apiTickets) {
      final createdAtStr = t['created_at']?.toString() ?? '';
      DateTime? dt = DateTime.tryParse(createdAtStr);
      dt ??= DateTime.now();

      if (dt.year == _currentMonth.year && dt.month == _currentMonth.month) {
        final categoryStr = t['category']?.toString() ?? 'Maintenance';
        final stage = (t['stage'] as num?)?.toInt() ?? 0;
        final stageLabel = stage < kTicketStages.length
            ? kTicketStages[stage]
            : 'In Progress';
        final submittedDateStr = _formatDate(dt);
        final subtitle = stageLabel.trim().toLowerCase() == 'submitted'
            ? (submittedDateStr.isNotEmpty ? 'Submitted $submittedDateStr' : 'Submitted')
            : (submittedDateStr.isNotEmpty ? '$stageLabel • Submitted $submittedDateStr' : stageLabel);

        String displayTitle = t['title']?.toString().trim() ?? '';
        if (displayTitle.startsWith('[')) {
          final closeIdx = displayTitle.indexOf(']');
          if (closeIdx > 1) {
            final inside = displayTitle.substring(1, closeIdx);
            displayTitle = inside.contains(' - ') ? inside.split(' - ').first.trim() : inside.trim();
          }
        }
        if (displayTitle.isEmpty) {
          final cat = t['category']?.toString().trim() ?? 'Maintenance';
          displayTitle = cat.isNotEmpty ? (cat[0].toUpperCase() + cat.substring(1)) : 'Maintenance';
        }

        list.add({
          'id': 'ticket_${t['id']}',
          'title': displayTitle,
          'subtitle': subtitle,
          'day': dt.day,
          'month': _getShortMonth(dt.month),
          'category': 'maintenance',
          'categoryLabel': 'Maintenance',
          'color': AppColors.tagMaintenance,
          'icon': _getCategoryIcon(categoryStr),
        });
      }
    }

    // 2. Live Payments from API (never fabricate payments when a real lease exists)
    final now = DateTime.now();
    final isPastOrCurrent =
        _currentMonth.year < now.year ||
        (_currentMonth.year == now.year && _currentMonth.month <= now.month);

    final effectivePayments = widget.lease != null
        ? _apiPayments
        : (_apiPayments.isNotEmpty
              ? _apiPayments
              : (isPastOrCurrent
                    ? [
                        {
                          'id': 'hist_${_currentMonth.year}_${_currentMonth.month}',
                          'amount': rentNum,
                          'paid_at':
                              '${_currentMonth.year}-${_currentMonth.month.toString().padLeft(2, '0')}-05 14:30:00',
                          'method': 'GCash / InstaPay',
                        },
                      ]
                    : []));

    for (final p in effectivePayments) {
      final pStatus = (p['status'] ?? '').toString().toLowerCase();
      if (pStatus == 'declined') continue;
      final paidAtStr = p['paid_at']?.toString() ?? '';
      DateTime? dt = DateTime.tryParse(paidAtStr);
      if (dt != null &&
          dt.year == _currentMonth.year &&
          dt.month == _currentMonth.month) {
        final amount = (p['amount'] as num?)?.toDouble() ?? 0.0;
        final paidDateStr = _formatDate(dt);

        list.add({
          'id': 'pay_${p['id']}',
          'title': pStatus == 'pending' ? 'Rent Payment (Pending)' : 'Monthly Rent Payment',
          'subtitle': '₱${_formatMoney(amount)} ${pStatus == 'pending' ? 'Submitted' : 'Paid'} • $paidDateStr',
          'day': dt.day,
          'month': _getShortMonth(dt.month),
          'category': 'rent',
          'categoryLabel': 'Rent',
          'color': AppColors.tagRent,
          'icon': Icons.account_balance_wallet_rounded,
        });
      }
    }

    // 3. Rent Due date from active lease contract due_day
    if (widget.lease != null) {
      final daysInCurMonth = _daysInMonth(_currentMonth);
      final rawDueDay = widget.lease!['due_day'];
      final parsedDueDay = rawDueDay is int
          ? rawDueDay
          : int.tryParse(rawDueDay?.toString() ?? '') ?? daysInCurMonth;
      final clampedDueDay = parsedDueDay.clamp(1, daysInCurMonth);
      final dueDateTime = DateTime(_currentMonth.year, _currentMonth.month, clampedDueDay);
      final dueStr = _formatDate(dueDateTime);
      list.add({
        'id': 'rent_due_${_currentMonth.year}_${_currentMonth.month}',
        'title': 'Rent Due',
        'subtitle': '₱${_formatMoney(rentNum)} • Due $dueStr',
        'day': clampedDueDay,
        'month': _getShortMonth(_currentMonth.month),
        'category': 'rent',
        'categoryLabel': 'Rent',
        'color': AppColors.tagRent,
        'icon': Icons.account_balance_wallet_rounded,
      });
    }

    // 4. Restructuring Payment (Installments from API)
    for (final inst in _apiInstallments) {
      final status = (inst['status'] ?? '').toString().toLowerCase();
      if (status == 'approved' || status == 'active' || status == 'pending') {
        final partAmount = (inst['amount'] as num?)?.toDouble() ?? (rentNum / 2);
        final currentPart = (inst['current_part'] as int?) ?? 1;
        final instDate = DateTime(_currentMonth.year, _currentMonth.month, 15);
        final dateStr = _formatDate(instDate);

        list.add({
          'id': 'installment_${inst['id']}_$currentPart',
          'title': 'Restructuring Payment',
          'subtitle': '₱${_formatMoney(partAmount)} Due • $dateStr',
          'day': 15,
          'month': _getShortMonth(_currentMonth.month),
          'category': 'rent',
          'categoryLabel': 'Restructuring',
          'color': AppColors.tagRent,
          'icon': Icons.handshake_outlined,
        });
      }
    }

    // Sort by day
    list.sort((a, b) => (a['day'] as int).compareTo(b['day'] as int));
    _events = list;
  }

  void _changeMonth(int offset) {
    setState(() {
      _currentMonth = DateTime(
        _currentMonth.year,
        _currentMonth.month + offset,
        1,
      );
      _showAll = true;
      _buildEventsForCurrentMonth();
    });
  }

  void _goToToday() {
    final now = DateTime.now();
    setState(() {
      _currentMonth = DateTime(now.year, now.month, 1);
      _selectedDate = DateTime(now.year, now.month, now.day);
      _showAll = true;
      _buildEventsForCurrentMonth();
    });
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

  int _daysInMonth(DateTime date) {
    return DateTime(date.year, date.month + 1, 0).day;
  }

  int _firstWeekdayOffset(DateTime date) {
    // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
    return DateTime(date.year, date.month, 1).weekday % 7;
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final now = DateTime.now();
    final isCurrentMonth =
        _currentMonth.year == now.year && _currentMonth.month == now.month;
    final selectedDay =
        (_selectedDate != null &&
            _selectedDate!.year == _currentMonth.year &&
            _selectedDate!.month == _currentMonth.month)
        ? _selectedDate!.day
        : (isCurrentMonth ? now.day : 1);

    final filteredEvents = _showAll
        ? _events
        : _events.where((e) => e['day'] == selectedDay).toList();

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF000000) : Colors.white,
      body: SafeArea(
        bottom: false,
        child: CustomScrollView(
          physics: const BouncingScrollPhysics(),
          slivers: [
            // Header: Title and Subtitle
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Schedule',
                      style: TextStyle(
                        fontSize: 26,
                        fontWeight: FontWeight.w900,
                        color: isDark ? Colors.white : const Color(0xFF0B1B3D),
                        letterSpacing: -0.5,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      'See what\'s happening and when.',
                      style: TextStyle(
                        fontSize: 13.5,
                        color: isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B),
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
              ),
            ),

            // ================= FULL CALENDAR =================
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Column(
                  children: [
                    const SizedBox(height: 10),
                    // Month Navigation Header
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        IconButton(
                          icon: Icon(
                            Icons.chevron_left_rounded,
                            color: isDark ? Colors.white : const Color(0xFF0B1B3D),
                            size: 26,
                          ),
                          onPressed: () => _changeMonth(-1),
                        ),
                        Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              '${_getMonthName(_currentMonth.month)} ${_currentMonth.year}',
                              style: TextStyle(
                                fontSize: 17,
                                fontWeight: FontWeight.w800,
                                color: isDark ? Colors.white : const Color(0xFF0B1B3D),
                              ),
                            ),
                            if (!isCurrentMonth) ...[
                              const SizedBox(width: 8),
                              GestureDetector(
                                onTap: _goToToday,
                                child: Container(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 8,
                                    vertical: 3,
                                  ),
                                  decoration: BoxDecoration(
                                    color: AppColors.electricBlue.withValues(
                                      alpha: 0.15,
                                    ),
                                    borderRadius: BorderRadius.circular(12),
                                    border: Border.all(
                                      color: AppColors.electricBlue.withValues(
                                        alpha: 0.35,
                                      ),
                                    ),
                                  ),
                                  child: const Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      Icon(
                                        Icons.today_rounded,
                                        size: 12,
                                        color: AppColors.electricBlue,
                                      ),
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
                          icon: Icon(
                            Icons.chevron_right_rounded,
                            color: isDark ? Colors.white : const Color(0xFF0B1B3D),
                            size: 26,
                          ),
                          onPressed: () => _changeMonth(1),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),

                    // Days of Week (Sun - Sat) full width
                    Row(
                      children:
                          const [
                                'Sun',
                                'Mon',
                                'Tue',
                                'Wed',
                                'Thu',
                                'Fri',
                                'Sat',
                              ]
                              .map(
                                (d) => Expanded(
                                  child: Text(
                                    d,
                                    textAlign: TextAlign.center,
                                    style: TextStyle(
                                      fontSize: 13,
                                      fontWeight: FontWeight.w600,
                                      color: isDark
                                          ? const Color(0xFF64748B)
                                          : const Color(0xFF94A3B8),
                                    ),
                                  ),
                                ),
                              )
                              .toList(),
                    ),
                    const SizedBox(height: 6),

                    // Full-width Day Grid
                    _buildLargeCalendarGrid(isDark: isDark),

                    const SizedBox(height: 14),

                    // Category Legend Row
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceAround,
                      children: [
                        _buildLegendItem('Rent', AppColors.tagRent, isDark: isDark),
                        _buildLegendItem(
                          'Maintenance',
                          AppColors.tagMaintenance,
                          isDark: isDark,
                        ),
                        _buildLegendItem('Building', AppColors.tagBuilding, isDark: isDark),
                        _buildLegendItem('Other', AppColors.tagOther, isDark: isDark),
                      ],
                    ),
                    const SizedBox(height: 16),
                  ],
                ),
              ),
            ),

            // Section Header: Upcoming Events & View all/less toggle
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 8, 20, 12),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      'Upcoming Events',
                      style: TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w800,
                        color: isDark ? Colors.white : const Color(0xFF0B1B3D),
                        letterSpacing: -0.2,
                      ),
                    ),
                    GestureDetector(
                      onTap: () {
                        setState(() {
                          _showAll = !_showAll;
                        });
                      },
                      child: Text(
                        _showAll ? 'View less' : 'View all',
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF1E6BFF),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),

            // Events List
            if (_loading)
              const SliverToBoxAdapter(
                child: Padding(
                  padding: EdgeInsets.symmetric(vertical: 40),
                  child: Center(
                    child: CircularProgressIndicator(
                      color: AppColors.electricBlue,
                    ),
                  ),
                ),
              )
            else if (filteredEvents.isEmpty)
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 20,
                    vertical: 12,
                  ),
                  child: Container(
                    padding: const EdgeInsets.all(24),
                    decoration: BoxDecoration(
                      color: isDark ? const Color(0xFF121212) : const Color(0xFFF8FAFC),
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(
                        color: isDark ? const Color(0xFF262626) : const Color(0xFFE2E8F0),
                      ),
                    ),
                    child: Column(
                      children: [
                        Icon(
                          Icons.event_available_rounded,
                          size: 38,
                          color: isDark ? const Color(0xFF475569) : const Color(0xFFCBD5E1),
                        ),
                        const SizedBox(height: 10),
                        Text(
                          'No events on ${_getMonthName(_currentMonth.month)} $selectedDay',
                          style: TextStyle(
                            fontWeight: FontWeight.w700,
                            color: isDark ? Colors.white : const Color(0xFF334155),
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'Tap "View all" to see all scheduled events for this month.',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontSize: 12,
                            color: isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              )
            else
              SliverPadding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                sliver: SliverList(
                  delegate: SliverChildBuilderDelegate((context, index) {
                    final event = filteredEvents[index];
                    return _buildEventCard(event, isDark: isDark);
                  }, childCount: filteredEvents.length),
                ),
              ),

            // Bottom space for floating dock
            const SliverToBoxAdapter(child: SizedBox(height: 120)),
          ],
        ),
      ),
    );
  }

  Widget _buildLegendItem(String label, Color color, {required bool isDark}) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 7,
          height: 7,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        const SizedBox(width: 5),
        Text(
          label,
          style: TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w600,
            color: isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B),
          ),
        ),
      ],
    );
  }

  Widget _buildLargeCalendarGrid({required bool isDark}) {
    final now = DateTime.now();
    final totalDays = _daysInMonth(_currentMonth);
    final offset = _firstWeekdayOffset(_currentMonth);
    final totalCells = offset + totalDays;

    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 7,
        childAspectRatio: 1.05,
      ),
      itemCount: totalCells,
      itemBuilder: (context, index) {
        if (index < offset) {
          return const SizedBox.shrink();
        }

        final day = index - offset + 1;
        final isToday =
            _currentMonth.year == now.year &&
            _currentMonth.month == now.month &&
            day == now.day;
        final isSelected =
            !_showAll &&
            _selectedDate != null &&
            _selectedDate!.year == _currentMonth.year &&
            _selectedDate!.month == _currentMonth.month &&
            _selectedDate!.day == day;
        final dayEvents = _events.where((e) => e['day'] == day).toList();

        BoxDecoration boxDecoration;
        Color textColor;
        FontWeight fontWeight;

        if (isToday && isSelected) {
          boxDecoration = BoxDecoration(
            color: AppColors.electricBlue,
            shape: BoxShape.circle,
            boxShadow: [
              BoxShadow(
                color: AppColors.electricBlue.withValues(alpha: 0.4),
                blurRadius: 7,
                offset: const Offset(0, 2),
              ),
            ],
          );
          textColor = Colors.white;
          fontWeight = FontWeight.w900;
        } else if (isToday) {
          // Current date ALWAYS remains marked
          boxDecoration = BoxDecoration(
            color: AppColors.electricBlue.withValues(alpha: isDark ? 0.25 : 0.12),
            shape: BoxShape.circle,
            border: Border.all(color: AppColors.electricBlue, width: 2.0),
          );
          textColor = isDark ? const Color(0xFF60A5FA) : AppColors.electricBlue;
          fontWeight = FontWeight.w900;
        } else if (isSelected) {
          boxDecoration = BoxDecoration(
            color: isDark ? const Color(0xFF1E3A8A) : const Color(0xFF0B1B3D),
            shape: BoxShape.circle,
            boxShadow: [
              BoxShadow(
                color: (isDark ? const Color(0xFF1E3A8A) : const Color(0xFF0B1B3D))
                    .withValues(alpha: 0.3),
                blurRadius: 6,
                offset: const Offset(0, 2),
              ),
            ],
          );
          textColor = Colors.white;
          fontWeight = FontWeight.w800;
        } else {
          boxDecoration = const BoxDecoration(
            color: Colors.transparent,
            shape: BoxShape.circle,
          );
          textColor = isDark ? Colors.white : const Color(0xFF0B1B3D);
          fontWeight = FontWeight.w600;
        }

        return GestureDetector(
          onTap: () {
            setState(() {
              if (_selectedDate != null &&
                  _selectedDate!.year == _currentMonth.year &&
                  _selectedDate!.month == _currentMonth.month &&
                  _selectedDate!.day == day &&
                  !_showAll) {
                _showAll = true;
              } else {
                _selectedDate = DateTime(
                  _currentMonth.year,
                  _currentMonth.month,
                  day,
                );
                _showAll = false;
              }
            });
          },
          behavior: HitTestBehavior.opaque,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: boxDecoration,
                alignment: Alignment.center,
                child: Text(
                  '$day',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: fontWeight,
                    color: textColor,
                  ),
                ),
              ),
              const SizedBox(height: 2),
              if (dayEvents.isNotEmpty)
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: dayEvents.take(3).map((e) {
                    return Container(
                      margin: const EdgeInsets.symmetric(horizontal: 1),
                      width: 4.5,
                      height: 4.5,
                      decoration: BoxDecoration(
                        color: e['color'] as Color,
                        shape: BoxShape.circle,
                      ),
                    );
                  }).toList(),
                )
              else
                const SizedBox(height: 4.5),
            ],
          ),
        );
      },
    );
  }

  Widget _buildEventCard(Map<String, dynamic> event, {required bool isDark}) {
    final color = event['color'] as Color;
    final icon = event['icon'] as IconData;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF121212) : const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: isDark ? const Color(0xFF262626) : const Color(0xFFE2E8F0),
        ),
      ),
      child: Row(
        children: [
          // Date badge
          Container(
            width: 50,
            padding: const EdgeInsets.symmetric(vertical: 8),
            decoration: BoxDecoration(
              color: isDark ? const Color(0xFF1A1A1A) : Colors.white,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: isDark ? const Color(0xFF2D2D2D) : const Color(0xFFE2E8F0),
              ),
            ),
            child: Column(
              children: [
                Text(
                  event['month'],
                  style: TextStyle(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w800,
                    color: isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B),
                  ),
                ),
                const SizedBox(height: 1),
                Text(
                  '${event['day']}',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                    color: isDark ? Colors.white : const Color(0xFF0B1B3D),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 14),

          // Icon squircle
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: color.withValues(alpha: isDark ? 0.22 : 0.12),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Icon(icon, color: color, size: 22),
          ),
          const SizedBox(width: 14),

          // Info
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  event['title'],
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    color: isDark ? Colors.white : const Color(0xFF0B1B3D),
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  event['subtitle'],
                  style: TextStyle(
                    fontSize: 12,
                    color: isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B),
                    fontWeight: FontWeight.w500,
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
