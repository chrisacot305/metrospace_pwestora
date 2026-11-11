import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:url_launcher/url_launcher.dart';
import '../theme.dart';
import '../services/api_service.dart';
import '../widgets/app_error_view.dart';
import 'apply_screen.dart';

class PropertyDetailScreen extends StatefulWidget {
  final int propertyId;
  const PropertyDetailScreen({super.key, required this.propertyId});

  @override
  State<PropertyDetailScreen> createState() => _PropertyDetailScreenState();
}

class _PropertyDetailScreenState extends State<PropertyDetailScreen> {
  late Future<Map<String, dynamic>> _future;
  late final PageController _pageController = PageController();
  int _photoIndex = 0;
  bool _descExpanded = false;

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    _future = ApiService.fetchPropertyDetail(widget.propertyId);
    _future
        .then((p) {
          final photos = (p['photos'] as List<dynamic>? ?? []).cast<String>();
          ApiService.addRecentlyViewed({
            'id': p['id'],
            'name': p['name'],
            'type': p['type'],
            'cover_photo': photos.isNotEmpty ? photos.first : null,
          });
        })
        .catchError((_) {
          // Detail failed to load — handled by FutureBuilder
        });
  }

  LatLng _getPropertyLatLng(Map<String, dynamic> p) {
    if (p['latitude'] != null && p['longitude'] != null) {
      final lat = double.tryParse(p['latitude'].toString());
      final lng = double.tryParse(p['longitude'].toString());
      if (lat != null && lng != null && lat != 0.0 && lng != 0.0) {
        return LatLng(lat, lng);
      }
    }
    return const LatLng(10.6765, 122.9510);
  }

  Future<void> _openGoogleMaps(Map<String, dynamic> p) async {
    final latLng = _getPropertyLatLng(p);
    final uri = Uri.parse(
      'https://www.google.com/maps/search/?api=1&query=${latLng.latitude},${latLng.longitude}',
    );
    try {
      final launched = await launchUrl(
        uri,
        mode: LaunchMode.externalApplication,
      );
      if (!launched) {
        await launchUrl(uri, mode: LaunchMode.platformDefault);
      }
    } catch (_) {
      try {
        await launchUrl(uri, mode: LaunchMode.platformDefault);
      } catch (_) {}
    }
  }

  String _formatLeaseTerm(dynamic term, dynamic months) {
    if (term != null && term.toString().trim().isNotEmpty) {
      final s = term.toString().trim();
      if (s.toLowerCase().contains('month') ||
          s.toLowerCase().contains('year')) {
        return s;
      }
      final numVal = int.tryParse(s);
      if (numVal != null) {
        if (numVal <= 5) return '$numVal ${numVal == 1 ? "Year" : "Years"}';
        return '$numVal Months';
      }
      return s;
    }
    if (months != null && months.toString().trim().isNotEmpty) {
      return '$months Months';
    }
    return 'Flexible';
  }

  String _formatFloorArea(dynamic area) {
    if (area != null && area.toString().trim().isNotEmpty) {
      final s = area.toString().trim();
      if (s.toLowerCase().contains('sqm') || s.toLowerCase().contains('m²')) {
        return s;
      }
      return '$s sqm';
    }
    return 'Inquire for sqm';
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final scaffoldBg = isDark ? const Color(0xFF000000) : AppColors.bg;
    final cardColor = isDark ? const Color(0xFF121212) : AppColors.surface;
    final borderColor = isDark ? const Color(0xFF262626) : AppColors.border;
    final titleColor = isDark ? Colors.white : AppColors.ink900;
    final subtextColor = isDark ? const Color(0xFF94A3B8) : AppColors.ink500;
    final bodyTextColor = isDark ? const Color(0xFFCBD5E1) : AppColors.ink700;
    final bottomBarBg = isDark ? const Color(0xFF121212) : Colors.white;

    return Scaffold(
      backgroundColor: scaffoldBg,
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return Scaffold(
              backgroundColor: scaffoldBg,
              body: const Center(
                child: CircularProgressIndicator(color: AppColors.electricBlue),
              ),
            );
          }
          if (snapshot.hasError) {
            return Scaffold(
              backgroundColor: scaffoldBg,
              appBar: AppBar(
                backgroundColor: cardColor,
                leading: AppBackButton(isDark: isDark),
              ),
              body: AppErrorView(
                error: snapshot.error,
                onRetry: () {
                  setState(() {
                    _future = ApiService.fetchPropertyDetail(widget.propertyId);
                  });
                },
              ),
            );
          }

          final p = snapshot.data!;
          final photos = (p['photos'] as List<dynamic>? ?? []).cast<String>();
          final rawRent = p['asking_rent'] ?? p['rent'];
          final double? askingRent = rawRent != null
              ? double.tryParse(rawRent.toString())
              : null;
          final propertyLatLng = _getPropertyLatLng(p);

          return Stack(
            children: [
              CustomScrollView(
                physics: const BouncingScrollPhysics(),
                slivers: [
                  // Full-bleed Image Gallery with Controls
                  SliverAppBar(
                    pinned: true,
                    expandedHeight: 320,
                    backgroundColor: isDark
                        ? const Color(0xFF0F172A)
                        : AppColors.primary,
                    foregroundColor: Colors.white,
                    leading: const AppBackButton(isDark: true),
                    flexibleSpace: FlexibleSpaceBar(
                      background: photos.isNotEmpty
                          ? Stack(
                              fit: StackFit.expand,
                              children: [
                                PageView.builder(
                                  controller: _pageController,
                                  physics: const AlwaysScrollableScrollPhysics(
                                    parent: BouncingScrollPhysics(),
                                  ),
                                  itemCount: photos.length,
                                  onPageChanged: (i) =>
                                      setState(() => _photoIndex = i),
                                  itemBuilder: (context, i) => Image.network(
                                    _resolveImageUrl(photos[i]),
                                    fit: BoxFit.cover,
                                    errorBuilder: (c, e, s) => Container(
                                      color: isDark
                                          ? const Color(0xFF1E293B)
                                          : AppColors.primaryLight,
                                      child: const Center(
                                        child: Icon(
                                          Icons.apartment,
                                          size: 48,
                                          color: AppColors.tint,
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                                IgnorePointer(
                                  child: DecoratedBox(
                                    decoration: BoxDecoration(
                                      gradient: LinearGradient(
                                        begin: Alignment.topCenter,
                                        end: Alignment.bottomCenter,
                                        colors: [
                                          Colors.black.withValues(alpha: 0.45),
                                          Colors.transparent,
                                          Colors.black.withValues(alpha: 0.65),
                                        ],
                                        stops: const [0.0, 0.5, 1.0],
                                      ),
                                    ),
                                  ),
                                ),
                                if (photos.length > 1) ...[
                                  Positioned(
                                    bottom: 16,
                                    left: 0,
                                    right: 0,
                                    child: Row(
                                      mainAxisAlignment:
                                          MainAxisAlignment.center,
                                      children: List.generate(photos.length, (
                                        i,
                                      ) {
                                        final active = i == _photoIndex;
                                        return GestureDetector(
                                          behavior: HitTestBehavior.opaque,
                                          onTap: () {
                                            _pageController.animateToPage(
                                              i,
                                              duration: const Duration(
                                                milliseconds: 250,
                                              ),
                                              curve: Curves.easeInOut,
                                            );
                                          },
                                          child: AnimatedContainer(
                                            duration: const Duration(
                                              milliseconds: 250,
                                            ),
                                            width: active ? 22 : 7,
                                            height: 7,
                                            margin: const EdgeInsets.symmetric(
                                              horizontal: 3,
                                            ),
                                            decoration: BoxDecoration(
                                              color: active
                                                  ? Colors.white
                                                  : Colors.white54,
                                              borderRadius:
                                                  BorderRadius.circular(999),
                                            ),
                                          ),
                                        );
                                      }),
                                    ),
                                  ),
                                  Positioned(
                                    left: 8,
                                    top: 0,
                                    bottom: 0,
                                    child: Center(
                                      child: _photoIndex > 0
                                          ? GestureDetector(
                                              behavior: HitTestBehavior.opaque,
                                              onTap: () {
                                                _pageController.previousPage(
                                                  duration: const Duration(
                                                    milliseconds: 250,
                                                  ),
                                                  curve: Curves.easeInOut,
                                                );
                                              },
                                              child: Container(
                                                width: 32,
                                                height: 32,
                                                decoration: BoxDecoration(
                                                  color: Colors.black
                                                      .withValues(alpha: 0.35),
                                                  shape: BoxShape.circle,
                                                ),
                                                child: const Icon(
                                                  Icons.chevron_left_rounded,
                                                  color: Colors.white,
                                                  size: 22,
                                                ),
                                              ),
                                            )
                                          : const SizedBox.shrink(),
                                    ),
                                  ),
                                  Positioned(
                                    right: 8,
                                    top: 0,
                                    bottom: 0,
                                    child: Center(
                                      child: _photoIndex < photos.length - 1
                                          ? GestureDetector(
                                              behavior: HitTestBehavior.opaque,
                                              onTap: () {
                                                _pageController.nextPage(
                                                  duration: const Duration(
                                                    milliseconds: 250,
                                                  ),
                                                  curve: Curves.easeInOut,
                                                );
                                              },
                                              child: Container(
                                                width: 32,
                                                height: 32,
                                                decoration: BoxDecoration(
                                                  color: Colors.black
                                                      .withValues(alpha: 0.35),
                                                  shape: BoxShape.circle,
                                                ),
                                                child: const Icon(
                                                  Icons.chevron_right_rounded,
                                                  color: Colors.white,
                                                  size: 22,
                                                ),
                                              ),
                                            )
                                          : const SizedBox.shrink(),
                                    ),
                                  ),
                                ],
                              ],
                            )
                          : Container(
                              color: isDark
                                  ? const Color(0xFF1E293B)
                                  : AppColors.primaryLight,
                              child: const Center(
                                child: Icon(
                                  Icons.apartment,
                                  size: 64,
                                  color: AppColors.tint,
                                ),
                              ),
                            ),
                    ),
                  ),

                  // Detail Body
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(20, 20, 20, 110),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          // Type pill and Status
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 12,
                                  vertical: 6,
                                ),
                                decoration: BoxDecoration(
                                  color: isDark
                                      ? const Color(0xFF1E293B)
                                      : AppColors.accentSoft,
                                  borderRadius: BorderRadius.circular(999),
                                ),
                                child: Text(
                                  (p['type'] ?? 'COMMERCIAL')
                                      .toString()
                                      .toUpperCase(),
                                  style: TextStyle(
                                    fontSize: 11.5,
                                    fontWeight: FontWeight.w800,
                                    color: isDark
                                        ? const Color(0xFF60A5FA)
                                        : AppColors.primary,
                                    letterSpacing: 0.5,
                                  ),
                                ),
                              ),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 10,
                                  vertical: 5,
                                ),
                                decoration: BoxDecoration(
                                  color: isDark
                                      ? const Color(
                                          0xFF064E3B,
                                        ).withValues(alpha: 0.4)
                                      : AppColors.successSoft,
                                  borderRadius: BorderRadius.circular(999),
                                ),
                                child: const Row(
                                  children: [
                                    Icon(
                                      Icons.verified,
                                      size: 14,
                                      color: AppColors.success,
                                    ),
                                    SizedBox(width: 4),
                                    Text(
                                      'Verified Space',
                                      style: TextStyle(
                                        fontSize: 11,
                                        fontWeight: FontWeight.w700,
                                        color: AppColors.success,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 14),

                          // Property Name
                          Text(
                            p['name'] ?? '',
                            style: TextStyle(
                              fontSize: 24,
                              fontWeight: FontWeight.w900,
                              color: titleColor,
                              letterSpacing: -0.5,
                            ),
                          ),
                          const SizedBox(height: 8),

                          // Address
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Padding(
                                padding: EdgeInsets.only(top: 2),
                                child: Icon(
                                  Icons.location_on,
                                  size: 16,
                                  color: AppColors.electricBlue,
                                ),
                              ),
                              const SizedBox(width: 6),
                              Expanded(
                                child: Text(
                                  p['address'] ?? '',
                                  style: TextStyle(
                                    fontSize: 14,
                                    color: subtextColor,
                                    height: 1.4,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 18),

                          // Verified Trust & Protection Banner
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 16,
                              vertical: 14,
                            ),
                            decoration: BoxDecoration(
                              color: cardColor,
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(color: borderColor),
                            ),
                            child: Row(
                              children: [
                                Container(
                                  width: 44,
                                  height: 44,
                                  decoration: BoxDecoration(
                                    color: isDark
                                        ? const Color(0xFF1E293B)
                                        : AppColors.accentSoft,
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  child: const Icon(
                                    Icons.verified_user_rounded,
                                    color: AppColors.electricBlue,
                                    size: 22,
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        'Verified Commercial Space',
                                        style: TextStyle(
                                          fontSize: 14,
                                          fontWeight: FontWeight.w800,
                                          color: titleColor,
                                        ),
                                      ),
                                      const SizedBox(height: 2),
                                      Text(
                                        'Legally binding digital contract & verified lessor accreditation',
                                        style: TextStyle(
                                          fontSize: 11.5,
                                          color: subtextColor,
                                          height: 1.3,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 20),

                          // Dynamic Key Information
                          Text(
                            'Key Information',
                            style: TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w800,
                              color: titleColor,
                            ),
                          ),
                          const SizedBox(height: 12),
                          Row(
                            children: [
                              Expanded(
                                child: _featureItem(
                                  icon: Icons.meeting_room_rounded,
                                  title: () {
                                    final avail = (p['available_units'] as num?)?.toInt() ??
                                        (p['occupancy_status'] == 'occupied' ? 0 : 1);
                                    if (avail <= 0) return 'Fully Occupied';
                                    if (avail == 1) return '1 Vacant Unit';
                                    return '$avail Vacant Units';
                                  }(),
                                  subtitle: 'Vacant Units',
                                  isDark: isDark,
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: _featureItem(
                                  icon: Icons.date_range_rounded,
                                  title: _formatLeaseTerm(
                                    p['lease_term'],
                                    p['term_months'],
                                  ),
                                  subtitle: 'Standard Lease',
                                  isDark: isDark,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 12),
                          Row(
                            children: [
                              Expanded(
                                child: _featureItem(
                                  icon: Icons.square_foot_rounded,
                                  title: _formatFloorArea(p['floor_area']),
                                  subtitle: 'Estimated Area',
                                  isDark: isDark,
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: _featureItem(
                                  icon: Icons.trending_up_rounded,
                                  title: (p['escalation'] != null &&
                                          p['escalation']
                                              .toString()
                                              .trim()
                                              .isNotEmpty)
                                      ? (p['escalation'].toString().contains('%')
                                          ? '${p['escalation']} Annual'
                                          : '${p['escalation']}% Annual')
                                      : 'Standard / None',
                                  subtitle: 'Escalation Rate',
                                  isDark: isDark,
                                ),
                              ),
                            ],
                          ),

                          ..._buildDescriptionSection(
                            p,
                            isDark: isDark,
                            cardColor: cardColor,
                            borderColor: borderColor,
                            titleColor: titleColor,
                            bodyTextColor: bodyTextColor,
                          ),
                          ..._buildExtraDetailsSection(
                            p,
                            isDark: isDark,
                            cardColor: cardColor,
                            borderColor: borderColor,
                            titleColor: titleColor,
                            subtextColor: subtextColor,
                            bodyTextColor: bodyTextColor,
                          ),

                          const SizedBox(height: 14),

                          // Lessor Contact Details: First Gmail, then Phone Number underneath
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              color: cardColor,
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(color: borderColor),
                            ),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Container(
                                  width: 40,
                                  height: 40,
                                  decoration: BoxDecoration(
                                    color: isDark
                                        ? const Color(0xFF1E293B)
                                        : AppColors.accentSoft,
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  child: const Icon(
                                    Icons.business_rounded,
                                    color: AppColors.electricBlue,
                                    size: 20,
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        'PROPERTY CONTACT DETAILS',
                                        style: TextStyle(
                                          fontSize: 10,
                                          fontWeight: FontWeight.w800,
                                          color: subtextColor,
                                          letterSpacing: 0.6,
                                        ),
                                      ),
                                      const SizedBox(height: 8),

                                      // 1. Gmail / Email (First)
                                      Row(
                                        children: [
                                          const Icon(
                                            Icons.mail_outline_rounded,
                                            size: 15,
                                            color: AppColors.electricBlue,
                                          ),
                                          const SizedBox(width: 8),
                                          Expanded(
                                            child: Text(
                                              (p['lessor_email'] != null &&
                                                      p['lessor_email']
                                                          .toString()
                                                          .trim()
                                                          .isNotEmpty)
                                                  ? p['lessor_email'].toString()
                                                  : 'Inquire via Pwestora',
                                              style: TextStyle(
                                                fontSize: 12.5,
                                                fontWeight: FontWeight.w600,
                                                color: bodyTextColor,
                                              ),
                                              overflow: TextOverflow.ellipsis,
                                            ),
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 6),

                                      // 2. Phone Number (Underneath)
                                      Row(
                                        children: [
                                          const Icon(
                                            Icons.phone_outlined,
                                            size: 15,
                                            color: AppColors.electricBlue,
                                          ),
                                          const SizedBox(width: 8),
                                          Expanded(
                                            child: Text(
                                              (p['lessor_phone'] != null &&
                                                      p['lessor_phone']
                                                          .toString()
                                                          .trim()
                                                          .isNotEmpty)
                                                  ? p['lessor_phone'].toString()
                                                  : 'Contact available upon inquiry',
                                              style: TextStyle(
                                                fontSize: 12.5,
                                                fontWeight: FontWeight.w600,
                                                color: bodyTextColor,
                                              ),
                                              overflow: TextOverflow.ellipsis,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ),

                          const SizedBox(height: 22),

                          // Location (Clickable Facebook Marketplace Style)
                          Text(
                            'Location',
                            style: TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w800,
                              color: titleColor,
                            ),
                          ),
                          const SizedBox(height: 12),
                          GestureDetector(
                            onTap: () => _openGoogleMaps(p),
                            behavior: HitTestBehavior.opaque,
                            child: Container(
                              decoration: BoxDecoration(
                                color: cardColor,
                                borderRadius: BorderRadius.circular(16),
                                border: Border.all(color: borderColor),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  // Clickable Map Area
                                  ClipRRect(
                                    borderRadius: const BorderRadius.vertical(
                                      top: Radius.circular(16),
                                    ),
                                    child: Stack(
                                      children: [
                                        SizedBox(
                                          height: 190,
                                          width: double.infinity,
                                          child: IgnorePointer(
                                            child: FlutterMap(
                                              options: MapOptions(
                                                initialCenter: propertyLatLng,
                                                initialZoom: 15.0,
                                                interactionOptions:
                                                    const InteractionOptions(
                                                      flags:
                                                          InteractiveFlag.none,
                                                    ),
                                              ),
                                              children: [
                                                TileLayer(
                                                  urlTemplate:
                                                      'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                                                  userAgentPackageName:
                                                      'com.pwestora.app',
                                                ),
                                                MarkerLayer(
                                                  markers: [
                                                    Marker(
                                                      point: propertyLatLng,
                                                      width: 40,
                                                      height: 40,
                                                      child: const Icon(
                                                        Icons.location_on,
                                                        size: 38,
                                                        color: Color(
                                                          0xFFEF4444,
                                                        ),
                                                      ),
                                                    ),
                                                  ],
                                                ),
                                              ],
                                            ),
                                          ),
                                        ),

                                        // Clickable Floating Badge (Facebook Marketplace Style)
                                        Positioned(
                                          bottom: 10,
                                          right: 10,
                                          child: Container(
                                            padding: const EdgeInsets.symmetric(
                                              horizontal: 10,
                                              vertical: 5,
                                            ),
                                            decoration: BoxDecoration(
                                              color: isDark
                                                  ? const Color(0xFF1E293B)
                                                  : Colors.white,
                                              borderRadius:
                                                  BorderRadius.circular(20),
                                              border: Border.all(
                                                color: isDark
                                                    ? const Color(0xFF334155)
                                                    : Colors.black12,
                                              ),
                                              boxShadow: [
                                                BoxShadow(
                                                  color: Colors.black
                                                      .withValues(alpha: 0.18),
                                                  blurRadius: 8,
                                                  offset: const Offset(0, 2),
                                                ),
                                              ],
                                            ),
                                            child: Row(
                                              mainAxisSize: MainAxisSize.min,
                                              children: [
                                                Icon(
                                                  Icons.open_in_new_rounded,
                                                  size: 13,
                                                  color: isDark
                                                      ? Colors.white
                                                      : AppColors.ink900,
                                                ),
                                                const SizedBox(width: 4),
                                                Text(
                                                  'Open in Maps',
                                                  style: TextStyle(
                                                    fontSize: 11,
                                                    fontWeight: FontWeight.w700,
                                                    color: isDark
                                                        ? Colors.white
                                                        : AppColors.ink900,
                                                  ),
                                                ),
                                              ],
                                            ),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),

                                  // Address line below map
                                  Padding(
                                    padding: const EdgeInsets.all(12),
                                    child: Row(
                                      children: [
                                        const Icon(
                                          Icons.location_on_outlined,
                                          size: 16,
                                          color: AppColors.electricBlue,
                                        ),
                                        const SizedBox(width: 6),
                                        Expanded(
                                          child: Text(
                                            p['address'] ??
                                                'Bacolod City, Philippines',
                                            style: TextStyle(
                                              fontSize: 12.5,
                                              fontWeight: FontWeight.w600,
                                              color: bodyTextColor,
                                            ),
                                            maxLines: 2,
                                            overflow: TextOverflow.ellipsis,
                                          ),
                                        ),
                                        const SizedBox(width: 4),
                                        Icon(
                                          Icons.arrow_forward_ios_rounded,
                                          size: 12,
                                          color: subtextColor,
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                          ..._buildLandmarkSection(
                            p,
                            isDark: isDark,
                            cardColor: cardColor,
                            borderColor: borderColor,
                            titleColor: titleColor,
                            subtextColor: subtextColor,
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),

              // Floating Bottom Action Bar with Fixed Price
              Positioned(
                bottom: 0,
                left: 0,
                right: 0,
                child: Container(
                  padding: const EdgeInsets.fromLTRB(20, 14, 20, 24),
                  decoration: BoxDecoration(
                    color: bottomBarBg,
                    border: Border(top: BorderSide(color: borderColor)),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(
                          alpha: isDark ? 0.4 : 0.08,
                        ),
                        blurRadius: 20,
                        offset: const Offset(0, -4),
                      ),
                    ],
                  ),
                  child: Row(
                    children: [
                      // Price display on bottom left
                      Expanded(
                        flex: 4,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              'MONTHLY RENT',
                              style: TextStyle(
                                fontSize: 9.5,
                                fontWeight: FontWeight.w800,
                                color: subtextColor,
                                letterSpacing: 0.4,
                              ),
                            ),
                            const SizedBox(height: 2),
                            FittedBox(
                              fit: BoxFit.scaleDown,
                              alignment: Alignment.centerLeft,
                              child: RichText(
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                text: TextSpan(
                                  children: [
                                    TextSpan(
                                      text: (askingRent != null && askingRent > 0)
                                          ? '₱${_formatMoney(askingRent)}'
                                          : 'Inquire for Rent',
                                      style: TextStyle(
                                        fontSize: 17,
                                        fontWeight: FontWeight.w900,
                                        color: isDark
                                            ? const Color(0xFF60A5FA)
                                            : AppColors.primary,
                                        letterSpacing: -0.3,
                                        fontFamily: 'Roboto',
                                      ),
                                    ),
                                    if (askingRent != null && askingRent > 0)
                                      TextSpan(
                                        text: '/mo',
                                        style: TextStyle(
                                          fontSize: 11,
                                          fontWeight: FontWeight.w600,
                                          color: subtextColor,
                                          fontFamily: 'Roboto',
                                        ),
                                      ),
                                  ],
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 12),
                      // Apply CTA button on bottom right
                      Expanded(
                        flex: 6,
                        child: ElevatedButton(
                          onPressed: () {
                            Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => ApplyScreen(
                                  propertyId: p['id'] as int,
                                  propertyName: p['name'] ?? '',
                                  askingRent: askingRent,
                                ),
                              ),
                            );
                          },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: isDark
                                ? AppColors.electricBlue
                                : AppColors.primary,
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(16),
                            ),
                          ),
                          child: const FittedBox(
                            fit: BoxFit.scaleDown,
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Text(
                                  'Apply for Space',
                                  style: TextStyle(
                                    fontSize: 14.5,
                                    fontWeight: FontWeight.w800,
                                    color: Colors.white,
                                  ),
                                ),
                                SizedBox(width: 6),
                                Icon(
                                  Icons.arrow_forward_rounded,
                                  size: 16,
                                  color: Colors.white,
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _featureItem({
    required IconData icon,
    required String title,
    required String subtitle,
    bool isDark = false,
  }) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF121212) : AppColors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: isDark ? const Color(0xFF262626) : AppColors.border,
          width: 1,
        ),
      ),
      child: Row(
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              color: isDark ? const Color(0xFF1E293B) : AppColors.accentSoft,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              icon,
              size: 18,
              color: isDark ? AppColors.electricBlue : AppColors.primary,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                    color: isDark ? Colors.white : AppColors.ink900,
                  ),
                ),
                Text(
                  subtitle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 11,
                    color: isDark ? const Color(0xFF94A3B8) : AppColors.ink500,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _resolveImageUrl(String rawUrl) {
    final trimmed = rawUrl.trim();
    if (trimmed.startsWith('http://') || trimmed.startsWith('https://')) {
      return trimmed;
    }
    final root = ApiService.baseUrl.replaceAll('/api', '');
    return '$root/${trimmed.replaceFirst(RegExp(r'^/+'), '')}';
  }

  String? _nonEmpty(dynamic val) {
    if (val == null) return null;
    final s = val.toString().trim();
    return s.isEmpty ? null : s;
  }

  /// Property description ("About this Space") displayed right below Key Information.
  List<Widget> _buildDescriptionSection(
    Map<String, dynamic> p, {
    required bool isDark,
    required Color cardColor,
    required Color borderColor,
    required Color titleColor,
    required Color bodyTextColor,
  }) {
    final desc = _nonEmpty(p['description']);
    if (desc == null) return const [];

    final isLong = desc.length > 200 || '\n'.allMatches(desc).length >= 4;

    return [
      const SizedBox(height: 20),
      Text(
        'About this Space',
        style: TextStyle(
          fontSize: 16,
          fontWeight: FontWeight.w800,
          color: titleColor,
        ),
      ),
      const SizedBox(height: 10),
      Container(
        width: double.infinity,
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: cardColor,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: borderColor),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              desc,
              maxLines: (isLong && !_descExpanded) ? 4 : null,
              overflow: (isLong && !_descExpanded)
                  ? TextOverflow.ellipsis
                  : TextOverflow.visible,
              style: TextStyle(
                fontSize: 13.5,
                height: 1.55,
                color: bodyTextColor,
              ),
            ),
            if (isLong) ...[
              const SizedBox(height: 8),
              GestureDetector(
                onTap: () => setState(() => _descExpanded = !_descExpanded),
                child: Text(
                  _descExpanded ? 'Show less' : 'Read more',
                  style: const TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                    color: AppColors.electricBlue,
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    ];
  }

  /// Expanded space specs & amenities from the lessor portal (rendered only when set).
  List<Widget> _buildExtraDetailsSection(
    Map<String, dynamic> p, {
    required bool isDark,
    required Color cardColor,
    required Color borderColor,
    required Color titleColor,
    required Color subtextColor,
    required Color bodyTextColor,
  }) {
    final parking = p['parking_slots'];
    final parkingText = (parking is num && parking > 0)
        ? '${parking.toInt()} ${parking == 1 ? 'Slot' : 'Slots'}'
        : null;

    final specRows = <(IconData, String, String)>[
      if (_nonEmpty(p['floor_level']) case final v?)
        (Icons.layers_outlined, 'Floor / Level', v),
      if (_nonEmpty(p['frontage']) case final v?)
        (Icons.straighten_rounded, 'Frontage', v),
      if (parkingText != null)
        (Icons.local_parking_rounded, 'Parking', parkingText),
      if (_nonEmpty(p['operating_hours']) case final v?)
        (Icons.schedule_rounded, 'Access Hours', v),
      if (_nonEmpty(p['permitted_use']) case final v?)
        (Icons.storefront_outlined, 'Permitted Use', v),
      if (_nonEmpty(p['utilities']) case final v?)
        (Icons.bolt_rounded, 'Utilities', v),
      if (_nonEmpty(p['deposit_terms']) case final v?)
        (Icons.account_balance_wallet_outlined, 'Deposit & Advance', v),
    ];

    final rawAmenities = p['amenities'];
    final amenities = rawAmenities is List
        ? rawAmenities
            .map((e) => e.toString().trim())
            .where((e) => e.isNotEmpty)
            .toList()
        : <String>[];

    if (specRows.isEmpty && amenities.isEmpty) return const [];

    return [
      const SizedBox(height: 16),
      if (specRows.isNotEmpty)
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: cardColor,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: borderColor),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'SPACE DETAILS & TERMS',
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                  color: subtextColor,
                  letterSpacing: 0.6,
                ),
              ),
              const SizedBox(height: 10),
              for (int i = 0; i < specRows.length; i++) ...[
                if (i > 0) const SizedBox(height: 8),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(
                      specRows[i].$1,
                      size: 15,
                      color: AppColors.electricBlue,
                    ),
                    const SizedBox(width: 8),
                    SizedBox(
                      width: 112,
                      child: Text(
                        specRows[i].$2,
                        style: TextStyle(
                          fontSize: 12,
                          color: subtextColor,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ),
                    Expanded(
                      child: Text(
                        specRows[i].$3,
                        style: TextStyle(
                          fontSize: 12.5,
                          fontWeight: FontWeight.w600,
                          color: bodyTextColor,
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
      if (amenities.isNotEmpty) ...[
        const SizedBox(height: 14),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: cardColor,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: borderColor),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'AMENITIES',
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                  color: subtextColor,
                  letterSpacing: 0.6,
                ),
              ),
              const SizedBox(height: 10),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: amenities.map((item) {
                  return Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: isDark
                          ? const Color(0xFF1E293B)
                          : AppColors.accentSoft,
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(
                          Icons.check_circle_outline_rounded,
                          size: 13,
                          color: AppColors.electricBlue,
                        ),
                        const SizedBox(width: 5),
                        Text(
                          item,
                          style: TextStyle(
                            fontSize: 11.5,
                            fontWeight: FontWeight.w600,
                            color: isDark
                                ? const Color(0xFFE2E8F0)
                                : AppColors.ink900,
                          ),
                        ),
                      ],
                    ),
                  );
                }).toList(),
              ),
            ],
          ),
        ),
      ],
    ];
  }

  /// Nearby landmark card displayed under the Location map when set.
  List<Widget> _buildLandmarkSection(
    Map<String, dynamic> p, {
    required bool isDark,
    required Color cardColor,
    required Color borderColor,
    required Color titleColor,
    required Color subtextColor,
  }) {
    final name = _nonEmpty(p['landmark_name']);
    final dist = _nonEmpty(p['landmark_distance']);
    final photo = _nonEmpty(p['landmark_photo']);
    if (name == null && dist == null && photo == null) return const [];

    return [
      const SizedBox(height: 12),
      Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: cardColor,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: borderColor),
        ),
        child: Row(
          children: [
            if (photo != null)
              ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: Image.network(
                  _resolveImageUrl(photo),
                  width: 48,
                  height: 48,
                  fit: BoxFit.cover,
                  errorBuilder: (c, e, s) => Container(
                    width: 48,
                    height: 48,
                    color: isDark
                        ? const Color(0xFF1E293B)
                        : AppColors.accentSoft,
                    child: const Icon(
                      Icons.place_rounded,
                      size: 20,
                      color: AppColors.electricBlue,
                    ),
                  ),
                ),
              )
            else
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: isDark
                      ? const Color(0xFF1E293B)
                      : AppColors.accentSoft,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(
                  Icons.near_me_rounded,
                  size: 19,
                  color: AppColors.electricBlue,
                ),
              ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'NEARBY LANDMARK',
                    style: TextStyle(
                      fontSize: 9.5,
                      fontWeight: FontWeight.w800,
                      color: subtextColor,
                      letterSpacing: 0.6,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    name ?? 'Nearby Landmark',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: titleColor,
                    ),
                  ),
                  if (dist != null)
                    Text(
                      dist,
                      style: TextStyle(
                        fontSize: 11.5,
                        color: subtextColor,
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    ];
  }

  String _formatMoney(double amount) {
    final whole = amount.toStringAsFixed(0);
    final reg = RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))');
    return whole.replaceAllMapped(reg, (Match m) => '${m[1]},');
  }
}
