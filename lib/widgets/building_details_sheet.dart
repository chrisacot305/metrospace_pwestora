import 'dart:ui';
import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../screens/lease_contract_screen.dart';

class BuildingDetailsSheet extends StatefulWidget {
  final Map<String, dynamic> lease;
  final Function(int)? onNavigateTab;

  const BuildingDetailsSheet({
    super.key,
    required this.lease,
    this.onNavigateTab,
  });

  /// Displays the centered Frosted Glassmorphic modal with smooth pop-up
  static Future<void> show(
    BuildContext context, {
    required Map<String, dynamic> lease,
    Function(int)? onNavigateTab,
  }) {
    return showGeneralDialog(
      context: context,
      barrierDismissible: true,
      barrierLabel: 'Building Details',
      barrierColor: Colors.black.withValues(alpha: 0.50),
      transitionDuration: const Duration(milliseconds: 260),
      pageBuilder: (context, anim1, anim2) {
        return const SizedBox.shrink();
      },
      transitionBuilder: (context, anim1, anim2, child) {
        final curve = Curves.easeOutBack.transform(anim1.value);
        return BackdropFilter(
          filter: ImageFilter.blur(
            sigmaX: 10 * anim1.value,
            sigmaY: 10 * anim1.value,
          ),
          child: Opacity(
            opacity: anim1.value.clamp(0.0, 1.0),
            child: Transform.scale(
              scale: 0.88 + (0.12 * curve),
              child: Center(
                child: BuildingDetailsSheet(
                  lease: lease,
                  onNavigateTab: onNavigateTab,
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  @override
  State<BuildingDetailsSheet> createState() => _BuildingDetailsSheetState();
}

class _BuildingDetailsSheetState extends State<BuildingDetailsSheet> {
  Map<String, dynamic>? _propertyDetail;

  @override
  void initState() {
    super.initState();
    _fetchDetailIfNeeded();
  }

  int? get _propertyId {
    final pid = widget.lease['property_id'] ?? widget.lease['id'];
    if (pid is int) return pid;
    if (pid != null) return int.tryParse(pid.toString());
    return null;
  }

  Future<void> _fetchDetailIfNeeded() async {
    final pid = _propertyId;
    if (pid == null || pid <= 0) return;
    try {
      final res = await ApiService.fetchPropertyDetail(pid);
      if (mounted) {
        setState(() {
          _propertyDetail = res;
        });
      }
    } catch (_) {}
  }

  String get _propertyName {
    final name = widget.lease['property_name'] ??
        widget.lease['name'] ??
        _propertyDetail?['name'];
    return (name != null && name.toString().trim().isNotEmpty)
        ? name.toString().trim()
        : 'Commercial Premises';
  }

  String get _propertyAddress {
    final addr = widget.lease['property_address'] ??
        widget.lease['address'] ??
        _propertyDetail?['address'];
    return (addr != null && addr.toString().trim().isNotEmpty)
        ? addr.toString().trim()
        : 'Bacolod City, Philippines';
  }

  String? get _coverPhoto {
    final photo = widget.lease['cover_photo'] ??
        widget.lease['photo'] ??
        _propertyDetail?['cover_photo'];
    if (photo != null && photo.toString().isNotEmpty) {
      return photo.toString();
    }
    final photos = _propertyDetail?['photos'];
    if (photos is List && photos.isNotEmpty) {
      return photos.first.toString();
    }
    return null;
  }

  String get _businessName {
    final b = widget.lease['business_name'] ?? widget.lease['tenant_name'];
    return (b != null && b.toString().trim().isNotEmpty)
        ? b.toString().replaceAll(RegExp(r'\s*\([^)]*\)\s*$'), '').trim()
        : 'Commercial Tenant';
  }

  String get _unitLabel {
    final u = widget.lease['unit_label'] ??
        widget.lease['unit'] ??
        widget.lease['stall'];
    if (u != null && u.toString().trim().isNotEmpty) {
      final s = u.toString().trim();
      return s.toLowerCase().startsWith('unit') || s.toLowerCase().startsWith('stall')
          ? s
          : 'Unit $s';
    }
    return 'Assigned Stall';
  }

  String get _leaseTermMonths {
    final term = widget.lease['term_months'] ??
        widget.lease['lease_term'] ??
        _propertyDetail?['lease_term'] ??
        _propertyDetail?['term_months'];
    if (term != null && term.toString().trim().isNotEmpty) {
      final s = term.toString().trim();
      if (s.toLowerCase().contains('month') || s.toLowerCase().contains('year')) {
        return s;
      }
      final numVal = int.tryParse(s);
      if (numVal != null) {
        if (numVal <= 5) return '$numVal ${numVal == 1 ? "Year" : "Years"}';
        return '$numVal Months';
      }
      return '$s Months';
    }
    return 'Flexible';
  }

  String get _floorArea {
    final area = widget.lease['floor_area'] ?? _propertyDetail?['floor_area'];
    if (area != null && area.toString().trim().isNotEmpty) {
      final s = area.toString().trim();
      return s.toLowerCase().contains('sqm') || s.toLowerCase().contains('m²')
          ? s
          : '$s sqm';
    }
    return 'Inquire for size';
  }

  String get _monthlyRent {
    final raw = widget.lease['rent'] ??
        widget.lease['asking_rent'] ??
        _propertyDetail?['asking_rent'];
    if (raw != null) {
      final numVal = double.tryParse(raw.toString());
      if (numVal != null && numVal > 0) {
        final parts = numVal.toStringAsFixed(0).replaceAllMapped(
              RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
              (Match m) => '${m[1]},',
            );
        return '₱$parts/mo';
      }
    }
    return 'Inquire';
  }

  String get _dueDate {
    final direct = widget.lease['due_date'] ?? widget.lease['effective_due_date'];
    if (direct != null && direct.toString().trim().isNotEmpty) {
      return direct.toString().trim();
    }

    final rawDay = widget.lease['due_day'];
    final int day = (rawDay != null) ? (int.tryParse(rawDay.toString()) ?? 30) : 30;

    final now = DateTime.now();
    const months = [
      'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
      'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
    ];
    int targetMonth = now.month;
    int targetYear = now.year;
    if (now.day > day + 3) {
      targetMonth += 1;
      if (targetMonth > 12) {
        targetMonth = 1;
        targetYear += 1;
      }
    }
    final monthName = months[targetMonth - 1];
    return '$monthName $day, $targetYear';
  }

  String? get _escalationRate {
    final esc = widget.lease['escalation'] ?? _propertyDetail?['escalation'];
    if (esc != null && esc.toString().trim().isNotEmpty) {
      final s = esc.toString().trim();
      if (s.toLowerCase().contains('escalation') ||
          s.toLowerCase().contains('annual')) {
        return s;
      }
      return s.contains('%') ? '$s Annual Escalation' : '$s% Annual Escalation';
    }
    return null;
  }

  String get _appliedDate {
    final raw = widget.lease['applied_at'] ?? widget.lease['submitted_at'] ?? widget.lease['since'];
    if (raw != null && raw.toString().isNotEmpty) {
      try {
        final dt = DateTime.parse(raw.toString());
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return '${months[dt.month - 1]} ${dt.day}, ${dt.year}';
      } catch (_) {}
    }
    return 'On Record';
  }

  String get _leaseDurationTimeline {
    final start = widget.lease['start_date'] ?? widget.lease['since'];
    final end = widget.lease['end_date'];
    if (start != null && end != null && start.toString().isNotEmpty && end.toString().isNotEmpty) {
      try {
        final sDt = DateTime.parse(start.toString());
        final eDt = DateTime.parse(end.toString());
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return '${months[sDt.month - 1]} ${sDt.year} – ${months[eDt.month - 1]} ${eDt.year}';
      } catch (_) {}
    }
    return _leaseTermMonths;
  }

  String get _securityDeposit {
    final raw = widget.lease['security_deposit'];
    final val = (raw as num?)?.toDouble() ?? 0.0;
    if (val > 0) {
      return '₱${val.toStringAsFixed(0).replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (m) => '${m[1]},')}';
    }
    final rent = (widget.lease['rent'] as num?)?.toDouble() ?? 0.0;
    if (rent > 0) {
      return '₱${(rent * 2).toStringAsFixed(0).replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (m) => '${m[1]},')}';
    }
    return '2 Months Deposit';
  }

  String get _advanceRent {
    final raw = widget.lease['advance_rent'];
    final val = (raw as num?)?.toDouble() ?? 0.0;
    if (val > 0) {
      return '₱${val.toStringAsFixed(0).replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (m) => '${m[1]},')}';
    }
    final rent = (widget.lease['rent'] as num?)?.toDouble() ?? 0.0;
    if (rent > 0) {
      return '₱${rent.toStringAsFixed(0).replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (m) => '${m[1]},')}';
    }
    return '1 Month Advance';
  }

  String get _accountStanding {
    final strike = (widget.lease['strike_level'] as num?)?.toInt() ?? 0;
    if (strike == 0) return 'Clean Record (0 Strikes)';
    return 'Strike $strike Citation Active';
  }

  @override
  Widget build(BuildContext context) {
    final cover = _coverPhoto;

    return Material(
      color: Colors.transparent,
      child: Container(
        width: MediaQuery.of(context).size.width * 0.88,
        constraints: BoxConstraints(
          maxWidth: 380,
          maxHeight: MediaQuery.of(context).size.height * 0.88,
        ),
        margin: const EdgeInsets.symmetric(horizontal: 16),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(28),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFF0F172A).withValues(alpha: 0.18),
              blurRadius: 36,
              offset: const Offset(0, 16),
            ),
            BoxShadow(
              color: Colors.white.withValues(alpha: 0.40),
              blurRadius: 2,
              offset: const Offset(0, 1),
            ),
          ],
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(28),
          child: BackdropFilter(
            filter: ImageFilter.blur(sigmaX: 25, sigmaY: 25),
            child: Container(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [
                    Colors.white.withValues(alpha: 0.76),
                    Colors.white.withValues(alpha: 0.48),
                    const Color(0xFFF1F5F9).withValues(alpha: 0.62),
                  ],
                  stops: const [0.0, 0.45, 1.0],
                ),
                borderRadius: BorderRadius.circular(28),
                border: Border.all(
                  color: Colors.white.withValues(alpha: 0.85),
                  width: 1.5,
                ),
              ),
              child: Stack(
                children: [
                  // Ambient Glass Sheen flare in top right
                  Positioned(
                    top: -30,
                    right: -30,
                    child: IgnorePointer(
                      child: Container(
                        width: 170,
                        height: 170,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          gradient: RadialGradient(
                            colors: [
                              Colors.white.withValues(alpha: 0.50),
                              Colors.white.withValues(alpha: 0.0),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),

                  SingleChildScrollView(
                    physics: const BouncingScrollPhysics(),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                      // ================= 1. PICTURE OF THE BUILDING (INSET GLASS FRAME) =================
                      Padding(
                        padding: const EdgeInsets.fromLTRB(12, 12, 12, 0),
                        child: Stack(
                          children: [
                            ClipRRect(
                              borderRadius: BorderRadius.circular(18),
                              child: Container(
                                height: 155,
                                width: double.infinity,
                                decoration: BoxDecoration(
                                  color: const Color(0xFFCBD5E1).withValues(alpha: 0.35),
                                  border: Border.all(
                                    color: Colors.white.withValues(alpha: 0.65),
                                    width: 1.0,
                                  ),
                                ),
                                child: cover != null && cover.isNotEmpty
                                    ? Image.network(
                                        cover,
                                        fit: BoxFit.cover,
                                        errorBuilder: (context, error, stackTrace) =>
                                            _buildFallbackPattern(),
                                      )
                                    : _buildFallbackPattern(),
                              ),
                            ),

                            // Frosted Close Button
                            Positioned(
                              top: 10,
                              right: 10,
                              child: InkWell(
                                borderRadius: BorderRadius.circular(20),
                                onTap: () => Navigator.of(context).pop(),
                                child: ClipRRect(
                                  borderRadius: BorderRadius.circular(20),
                                  child: BackdropFilter(
                                    filter: ImageFilter.blur(sigmaX: 10, sigmaY: 10),
                                    child: Container(
                                      padding: const EdgeInsets.all(7),
                                      decoration: BoxDecoration(
                                        color: Colors.black.withValues(alpha: 0.35),
                                        shape: BoxShape.circle,
                                        border: Border.all(
                                          color: Colors.white.withValues(alpha: 0.60),
                                          width: 1.0,
                                        ),
                                      ),
                                      child: const Icon(
                                        Icons.close_rounded,
                                        size: 15,
                                        color: Colors.white,
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),

                      // ================= CARD BODY CONTENT =================
                      Padding(
                        padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Verified Status Indicator Dot
                            Row(
                              children: [
                                Container(
                                  width: 6.5,
                                  height: 6.5,
                                  decoration: const BoxDecoration(
                                    color: Color(0xFF10B981), // Emerald dot
                                    shape: BoxShape.circle,
                                  ),
                                ),
                                const SizedBox(width: 6),
                                const Text(
                                  'VERIFIED COMMERCIAL PREMISES',
                                  style: TextStyle(
                                    fontSize: 9.5,
                                    fontWeight: FontWeight.w800,
                                    color: Color(0xFF047857),
                                    letterSpacing: 0.6,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 6),

                            // Building Name
                            Text(
                              _propertyName,
                              style: const TextStyle(
                                fontSize: 20,
                                fontWeight: FontWeight.w900,
                                color: Color(0xFF0B1B3D),
                                letterSpacing: -0.4,
                                height: 1.2,
                              ),
                            ),
                            const SizedBox(height: 4),

                            // Building Street Address
                            Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Padding(
                                  padding: EdgeInsets.only(top: 2),
                                  child: Icon(
                                    Icons.location_on_rounded,
                                    size: 13,
                                    color: Color(0xFF1E6BFF),
                                  ),
                                ),
                                const SizedBox(width: 4),
                                Expanded(
                                  child: Text(
                                    _propertyAddress,
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      fontSize: 12,
                                      fontWeight: FontWeight.w500,
                                      color: Color(0xFF475569),
                                      height: 1.35,
                                    ),
                                  ),
                                ),
                              ],
                            ),

                            const SizedBox(height: 14),

                            // ================= REGISTERED BUSINESS & UNIT (FROSTED TILE) =================
                            ClipRRect(
                              borderRadius: BorderRadius.circular(16),
                              child: BackdropFilter(
                                filter: ImageFilter.blur(sigmaX: 12, sigmaY: 12),
                                child: Container(
                                  width: double.infinity,
                                  padding: const EdgeInsets.all(12),
                                  decoration: BoxDecoration(
                                    color: Colors.white.withValues(alpha: 0.52),
                                    borderRadius: BorderRadius.circular(16),
                                    border: Border.all(
                                      color: Colors.white.withValues(alpha: 0.85),
                                      width: 1.2,
                                    ),
                                    boxShadow: [
                                      BoxShadow(
                                        color: const Color(0xFF0F172A).withValues(alpha: 0.03),
                                        blurRadius: 8,
                                        offset: const Offset(0, 2),
                                      ),
                                    ],
                                  ),
                                  child: Row(
                                    children: [
                                      Container(
                                        padding: const EdgeInsets.all(8),
                                        decoration: BoxDecoration(
                                          color: const Color(0xFF1E6BFF).withValues(alpha: 0.12),
                                          borderRadius: BorderRadius.circular(10),
                                        ),
                                        child: const Icon(
                                          Icons.storefront_rounded,
                                          color: Color(0xFF1E6BFF),
                                          size: 18,
                                        ),
                                      ),
                                      const SizedBox(width: 10),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            const Text(
                                              'REGISTERED BUSINESS',
                                              style: TextStyle(
                                                fontSize: 9,
                                                fontWeight: FontWeight.w800,
                                                color: Color(0xFF64748B),
                                                letterSpacing: 0.5,
                                              ),
                                            ),
                                            const SizedBox(height: 2),
                                            Text(
                                              _businessName,
                                              style: const TextStyle(
                                                fontSize: 14,
                                                fontWeight: FontWeight.w800,
                                                color: Color(0xFF0B1B3D),
                                                letterSpacing: -0.2,
                                              ),
                                              maxLines: 1,
                                              overflow: TextOverflow.ellipsis,
                                            ),
                                            const SizedBox(height: 1),
                                            Text(
                                              _unitLabel,
                                              style: const TextStyle(
                                                fontSize: 11.5,
                                                fontWeight: FontWeight.w600,
                                                color: Color(0xFF475569),
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            ),

                            const SizedBox(height: 10),

                            // ================= LEASING TERM & FLOOR AREA (FROSTED TILES) =================
                            Row(
                              children: [
                                // Leasing Term
                                Expanded(
                                  child: ClipRRect(
                                    borderRadius: BorderRadius.circular(14),
                                    child: BackdropFilter(
                                      filter: ImageFilter.blur(sigmaX: 12, sigmaY: 12),
                                      child: Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
                                        decoration: BoxDecoration(
                                          color: Colors.white.withValues(alpha: 0.52),
                                          borderRadius: BorderRadius.circular(14),
                                          border: Border.all(
                                            color: Colors.white.withValues(alpha: 0.85),
                                            width: 1.2,
                                          ),
                                          boxShadow: [
                                            BoxShadow(
                                              color: const Color(0xFF0F172A).withValues(alpha: 0.03),
                                              blurRadius: 8,
                                              offset: const Offset(0, 2),
                                            ),
                                          ],
                                        ),
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            const Row(
                                              children: [
                                                Icon(
                                                  Icons.date_range_rounded,
                                                  size: 12,
                                                  color: Color(0xFF1E6BFF),
                                                ),
                                                SizedBox(width: 4),
                                                Text(
                                                  'PERIOD TERM',
                                                  style: TextStyle(
                                                    fontSize: 9,
                                                    fontWeight: FontWeight.w800,
                                                    color: Color(0xFF64748B),
                                                    letterSpacing: 0.5,
                                                  ),
                                                ),
                                              ],
                                            ),
                                            const SizedBox(height: 4),
                                            Text(
                                              _leaseTermMonths,
                                              style: const TextStyle(
                                                fontSize: 14,
                                                fontWeight: FontWeight.w800,
                                                color: Color(0xFF0B1B3D),
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
                                ),
                                const SizedBox(width: 10),

                                // Floor Area
                                Expanded(
                                  child: ClipRRect(
                                    borderRadius: BorderRadius.circular(14),
                                    child: BackdropFilter(
                                      filter: ImageFilter.blur(sigmaX: 12, sigmaY: 12),
                                      child: Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
                                        decoration: BoxDecoration(
                                          color: Colors.white.withValues(alpha: 0.52),
                                          borderRadius: BorderRadius.circular(14),
                                          border: Border.all(
                                            color: Colors.white.withValues(alpha: 0.85),
                                            width: 1.2,
                                          ),
                                          boxShadow: [
                                            BoxShadow(
                                              color: const Color(0xFF0F172A).withValues(alpha: 0.03),
                                              blurRadius: 8,
                                              offset: const Offset(0, 2),
                                            ),
                                          ],
                                        ),
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            const Row(
                                              children: [
                                                Icon(
                                                  Icons.square_foot_rounded,
                                                  size: 12,
                                                  color: Color(0xFF8B5CF6),
                                                ),
                                                SizedBox(width: 4),
                                                Text(
                                                  'FLOOR AREA',
                                                  style: TextStyle(
                                                    fontSize: 9,
                                                    fontWeight: FontWeight.w800,
                                                    color: Color(0xFF64748B),
                                                    letterSpacing: 0.5,
                                                  ),
                                                ),
                                              ],
                                            ),
                                            const SizedBox(height: 4),
                                            Text(
                                              _floorArea,
                                              style: const TextStyle(
                                                fontSize: 14,
                                                fontWeight: FontWeight.w800,
                                                color: Color(0xFF0B1B3D),
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
                                ),
                              ],
                            ),

                            const SizedBox(height: 10),

                            // ================= MONTHLY RENT & PAYMENT DUE (FROSTED TILES) =================
                            Row(
                              children: [
                                // Monthly Rent
                                Expanded(
                                  child: ClipRRect(
                                    borderRadius: BorderRadius.circular(14),
                                    child: BackdropFilter(
                                      filter: ImageFilter.blur(sigmaX: 12, sigmaY: 12),
                                      child: Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
                                        decoration: BoxDecoration(
                                          color: Colors.white.withValues(alpha: 0.52),
                                          borderRadius: BorderRadius.circular(14),
                                          border: Border.all(
                                            color: Colors.white.withValues(alpha: 0.85),
                                            width: 1.2,
                                          ),
                                          boxShadow: [
                                            BoxShadow(
                                              color: const Color(0xFF0F172A).withValues(alpha: 0.03),
                                              blurRadius: 8,
                                              offset: const Offset(0, 2),
                                            ),
                                          ],
                                        ),
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            const Row(
                                              children: [
                                                Icon(
                                                  Icons.payments_outlined,
                                                  size: 12,
                                                  color: Color(0xFF059669),
                                                ),
                                                SizedBox(width: 4),
                                                Text(
                                                  'MONTHLY RENT',
                                                  style: TextStyle(
                                                    fontSize: 9,
                                                    fontWeight: FontWeight.w800,
                                                    color: Color(0xFF64748B),
                                                    letterSpacing: 0.5,
                                                  ),
                                                ),
                                              ],
                                            ),
                                            const SizedBox(height: 4),
                                            Text(
                                              _monthlyRent,
                                              style: const TextStyle(
                                                fontSize: 14,
                                                fontWeight: FontWeight.w800,
                                                color: Color(0xFF0B1B3D),
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
                                ),
                                const SizedBox(width: 10),

                                // Payment Due Date
                                Expanded(
                                  child: ClipRRect(
                                    borderRadius: BorderRadius.circular(14),
                                    child: BackdropFilter(
                                      filter: ImageFilter.blur(sigmaX: 12, sigmaY: 12),
                                      child: Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
                                        decoration: BoxDecoration(
                                          color: Colors.white.withValues(alpha: 0.52),
                                          borderRadius: BorderRadius.circular(14),
                                          border: Border.all(
                                            color: Colors.white.withValues(alpha: 0.85),
                                            width: 1.2,
                                          ),
                                          boxShadow: [
                                            BoxShadow(
                                              color: const Color(0xFF0F172A).withValues(alpha: 0.03),
                                              blurRadius: 8,
                                              offset: const Offset(0, 2),
                                            ),
                                          ],
                                        ),
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            const Row(
                                              children: [
                                                Icon(
                                                  Icons.calendar_today_rounded,
                                                  size: 12,
                                                  color: Color(0xFFD97706),
                                                ),
                                                SizedBox(width: 4),
                                                Text(
                                                  'PAYMENT DUE',
                                                  style: TextStyle(
                                                    fontSize: 9,
                                                    fontWeight: FontWeight.w800,
                                                    color: Color(0xFF64748B),
                                                    letterSpacing: 0.5,
                                                  ),
                                                ),
                                              ],
                                            ),
                                            const SizedBox(height: 4),
                                            Text(
                                              _dueDate,
                                              style: const TextStyle(
                                                fontSize: 12.5,
                                                fontWeight: FontWeight.w800,
                                                color: Color(0xFF0B1B3D),
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
                                ),
                              ],
                            ),

                            // ================= RENT ESCALATION (OPTIONAL IF SET) =================
                            if (_escalationRate != null) ...[
                              const SizedBox(height: 10),
                              ClipRRect(
                                borderRadius: BorderRadius.circular(14),
                                child: BackdropFilter(
                                  filter: ImageFilter.blur(sigmaX: 12, sigmaY: 12),
                                  child: Container(
                                    width: double.infinity,
                                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                                    decoration: BoxDecoration(
                                      color: Colors.white.withValues(alpha: 0.52),
                                      borderRadius: BorderRadius.circular(14),
                                      border: Border.all(
                                        color: Colors.white.withValues(alpha: 0.85),
                                        width: 1.2,
                                      ),
                                      boxShadow: [
                                        BoxShadow(
                                          color: const Color(0xFF0F172A).withValues(alpha: 0.03),
                                          blurRadius: 8,
                                          offset: const Offset(0, 2),
                                        ),
                                      ],
                                    ),
                                    child: Row(
                                      children: [
                                        Container(
                                          padding: const EdgeInsets.all(6),
                                          decoration: BoxDecoration(
                                            color: const Color(0xFF10B981).withValues(alpha: 0.12),
                                            borderRadius: BorderRadius.circular(8),
                                          ),
                                          child: const Icon(
                                            Icons.trending_up_rounded,
                                            size: 14,
                                            color: Color(0xFF059669),
                                          ),
                                        ),
                                        const SizedBox(width: 8),
                                        Expanded(
                                          child: Column(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              const Text(
                                                'RENT ESCALATION',
                                                style: TextStyle(
                                                  fontSize: 8.5,
                                                  fontWeight: FontWeight.w800,
                                                  color: Color(0xFF64748B),
                                                  letterSpacing: 0.5,
                                                ),
                                              ),
                                              const SizedBox(height: 1),
                                              Text(
                                                _escalationRate!,
                                                style: const TextStyle(
                                                  fontSize: 12.5,
                                                  fontWeight: FontWeight.w800,
                                                  color: Color(0xFF0B1B3D),
                                                  letterSpacing: -0.2,
                                                ),
                                              ),
                                            ],
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                              ),
                            ],

                            const SizedBox(height: 10),

                            // ================= APPLICATION DATE & LEASE TIMELINE =================
                            Row(
                              children: [
                                Expanded(
                                  child: _buildFrostedInfoTile(
                                    icon: Icons.how_to_reg_outlined,
                                    iconColor: const Color(0xFF2563EB),
                                    title: 'APPLICATION DATE',
                                    value: _appliedDate,
                                  ),
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: _buildFrostedInfoTile(
                                    icon: Icons.event_available_rounded,
                                    iconColor: const Color(0xFF0284C7),
                                    title: 'LEASE TIMELINE',
                                    value: _leaseDurationTimeline,
                                  ),
                                ),
                              ],
                            ),

                            const SizedBox(height: 10),

                            // ================= DEPOSITS & ADVANCE RENT =================
                            Row(
                              children: [
                                Expanded(
                                  child: _buildFrostedInfoTile(
                                    icon: Icons.security_rounded,
                                    iconColor: const Color(0xFF10B981),
                                    title: 'SECURITY DEPOSIT',
                                    value: _securityDeposit,
                                  ),
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: _buildFrostedInfoTile(
                                    icon: Icons.account_balance_wallet_outlined,
                                    iconColor: const Color(0xFF6366F1),
                                    title: 'ADVANCE RENT',
                                    value: _advanceRent,
                                  ),
                                ),
                              ],
                            ),

                            const SizedBox(height: 10),

                            // ================= ACCOUNT STANDING & COMPLIANCE =================
                            _buildFrostedInfoTile(
                              icon: Icons.verified_user_outlined,
                              iconColor: const Color(0xFF059669),
                              title: 'LEGAL STANDING & COMPLIANCE',
                              value: _accountStanding,
                              isFullWidth: true,
                            ),

                            const SizedBox(height: 16),

                            // ================= VIEW CONTRACT ACTION BUTTON =================
                            InkWell(
                              borderRadius: BorderRadius.circular(14),
                              onTap: () {
                                Navigator.of(context).pop();
                                Navigator.of(context).push(
                                  MaterialPageRoute(
                                    builder: (_) => LeaseContractScreen(lease: widget.lease),
                                  ),
                                );
                              },
                              child: Container(
                                width: double.infinity,
                                padding: const EdgeInsets.symmetric(vertical: 13),
                                decoration: BoxDecoration(
                                  gradient: const LinearGradient(
                                    colors: [Color(0xFF1E3A8A), Color(0xFF1E6BFF)],
                                  ),
                                  borderRadius: BorderRadius.circular(14),
                                  boxShadow: [
                                    BoxShadow(
                                      color: const Color(0xFF1E6BFF).withValues(alpha: 0.28),
                                      blurRadius: 12,
                                      offset: const Offset(0, 4),
                                    ),
                                  ],
                                ),
                                child: Row(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: const [
                                    Icon(Icons.description_outlined, color: Colors.white, size: 16),
                                    SizedBox(width: 8),
                                    Text(
                                      'View Signed Lease Contract (PDF)',
                                      style: TextStyle(
                                        color: Colors.white,
                                        fontSize: 13,
                                        fontWeight: FontWeight.w800,
                                        letterSpacing: 0.2,
                                      ),
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
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildFrostedInfoTile({
    required IconData icon,
    required Color iconColor,
    required String title,
    required String value,
    bool isFullWidth = false,
  }) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(14),
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: 12, sigmaY: 12),
        child: Container(
          width: isFullWidth ? double.infinity : null,
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.52),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: Colors.white.withValues(alpha: 0.85),
              width: 1.2,
            ),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF0F172A).withValues(alpha: 0.03),
                blurRadius: 8,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(icon, size: 12, color: iconColor),
                  const SizedBox(width: 4),
                  Text(
                    title,
                    style: const TextStyle(
                      fontSize: 8.5,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF64748B),
                      letterSpacing: 0.5,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 3),
              Text(
                value,
                style: const TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF0B1B3D),
                  letterSpacing: -0.2,
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildFallbackPattern() {
    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            const Color(0xFFCBD5E1).withValues(alpha: 0.5),
            const Color(0xFF94A3B8).withValues(alpha: 0.3),
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.domain_rounded,
              size: 34,
              color: const Color(0xFF475569).withValues(alpha: 0.6),
            ),
            const SizedBox(height: 4),
            const Text(
              'COMMERCIAL SPACE',
              style: TextStyle(
                fontSize: 9.5,
                fontWeight: FontWeight.w800,
                color: Color(0xFF475569),
                letterSpacing: 0.8,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
