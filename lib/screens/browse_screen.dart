import 'package:flutter/material.dart';
import '../theme.dart';
import '../services/api_service.dart';
import '../widgets/app_error_view.dart';
import 'property_detail_screen.dart';

class BrowseScreen extends StatefulWidget {
  final Future<void> Function()? onLeaseMayHaveChanged;
  const BrowseScreen({super.key, this.onLeaseMayHaveChanged});

  @override
  State<BrowseScreen> createState() => _BrowseScreenState();
}

class _BrowseScreenState extends State<BrowseScreen> {
  late Future<List<dynamic>> _propertiesFuture;
  late Future<List<Map<String, dynamic>>> _recentFuture;
  String _query = '';
  String _selectedCategory = 'All';
  final _searchCtrl = TextEditingController();

  final _categories = const [
    ('All', Icons.grid_view_rounded),
    ('Retail', Icons.storefront_rounded),
    ('Office', Icons.business_rounded),
    ('Food & Dining', Icons.restaurant_rounded),
    ('Storage', Icons.inventory_2_rounded),
    ('Kiosk / Booth', Icons.store_rounded),
  ];

  @override
  void initState() {
    super.initState();
    _propertiesFuture = ApiService.fetchProperties();
    _recentFuture = ApiService.getRecentlyViewed();
  }

  Future<void> _refresh() async {
    setState(() {
      _propertiesFuture = ApiService.fetchProperties();
      _recentFuture = ApiService.getRecentlyViewed();
    });
    await _propertiesFuture;
    if (widget.onLeaseMayHaveChanged != null) {
      await widget.onLeaseMayHaveChanged!();
    }
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final scaffoldBg = isDark ? const Color(0xFF000000) : AppColors.bg;
    final surfaceColor = isDark ? const Color(0xFF121212) : AppColors.surface;
    final borderColor = isDark ? const Color(0xFF262626) : AppColors.border;
    final titleColor = isDark ? Colors.white : AppColors.ink900;
    final subtextColor = isDark ? const Color(0xFF94A3B8) : AppColors.ink500;

    return Scaffold(
      backgroundColor: scaffoldBg,
      body: SafeArea(
        bottom: false,
        child: RefreshIndicator(
          onRefresh: _refresh,
          color: AppColors.electricBlue,
          child: CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(
              parent: BouncingScrollPhysics(),
            ),
            slivers: [
              // Top Bar: Header & Logo
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(18, 14, 18, 10),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      Expanded(
                        child: Row(
                          children: [
                            if (Navigator.canPop(context))
                              Padding(
                                padding: const EdgeInsets.only(right: 6),
                                child: AppBackButton(
                                  isDark: isDark,
                                  onPressed: () => Navigator.of(context).pop(),
                                ),
                              ),
                            Flexible(
                              child: FittedBox(
                                fit: BoxFit.scaleDown,
                                alignment: Alignment.centerLeft,
                                child: Text(
                                  'Explore Spaces',
                                  style: TextStyle(
                                    fontSize: 24,
                                    fontWeight: FontWeight.w900,
                                    color: titleColor,
                                    letterSpacing: -0.5,
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 8),
                      Container(
                        width: 44,
                        height: 44,
                        padding: const EdgeInsets.all(7),
                        decoration: BoxDecoration(
                          color: surfaceColor,
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(
                            color: borderColor,
                          ),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: isDark ? 0.3 : 0.04),
                              blurRadius: 10,
                              offset: const Offset(0, 3),
                            ),
                          ],
                        ),
                        child: Image.asset(
                          isDark ? 'img/invert.png' : 'img/Frame 2.png',
                          fit: BoxFit.contain,
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              // Search Bar (Full-Width, Clean)
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(18, 6, 18, 14),
                  child: Container(
                    height: 48,
                    decoration: BoxDecoration(
                      color: surfaceColor,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: borderColor),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: isDark ? 0.2 : 0.03),
                          blurRadius: 8,
                          offset: const Offset(0, 2),
                        ),
                      ],
                    ),
                    child: TextField(
                      controller: _searchCtrl,
                      onChanged: (v) =>
                          setState(() => _query = v.toLowerCase()),
                      style: TextStyle(
                        color: titleColor,
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                      ),
                      decoration: InputDecoration(
                        hintText: 'Search Bacolod malls, stalls, booths…',
                        hintStyle: TextStyle(
                          color: subtextColor,
                          fontSize: 13.5,
                        ),
                        prefixIcon: Icon(
                          Icons.search_rounded,
                          color: subtextColor,
                          size: 22,
                        ),
                        suffixIcon: _query.isNotEmpty
                            ? IconButton(
                                icon: Icon(
                                  Icons.close,
                                  size: 18,
                                  color: subtextColor,
                                ),
                                onPressed: () {
                                  _searchCtrl.clear();
                                  setState(() => _query = '');
                                },
                              )
                            : null,
                        border: InputBorder.none,
                        enabledBorder: InputBorder.none,
                        focusedBorder: InputBorder.none,
                        contentPadding: const EdgeInsets.symmetric(
                          vertical: 12,
                        ),
                      ),
                    ),
                  ),
                ),
              ),

              // Hero Promotion Banner
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(18, 0, 18, 16),
                  child: Container(
                    height: 130,
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: isDark
                            ? const [Color(0xFF0F224A), Color(0xFF142C56)]
                            : const [AppColors.primary, AppColors.primaryLight],
                      ),
                      borderRadius: BorderRadius.circular(20),
                      border: isDark
                          ? Border.all(color: const Color(0xFF1E3A5F), width: 1.2)
                          : null,
                      boxShadow: [
                        BoxShadow(
                          color: isDark
                              ? const Color(0xFF1E6BFF).withValues(alpha: 0.15)
                              : AppColors.primary.withValues(alpha: 0.22),
                          blurRadius: 14,
                          offset: const Offset(0, 6),
                        ),
                      ],
                    ),
                    child: Stack(
                      children: [
                        Positioned(
                          right: -20,
                          bottom: -20,
                          child: Icon(
                            Icons.storefront_rounded,
                            size: 140,
                            color: Colors.white.withValues(alpha: 0.08),
                          ),
                        ),
                        Padding(
                          padding: const EdgeInsets.all(18),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 3,
                                ),
                                decoration: BoxDecoration(
                                  color: Colors.white.withValues(alpha: 0.2),
                                  borderRadius: BorderRadius.circular(999),
                                ),
                                child: const Text(
                                  'FEATURED SPACES',
                                  style: TextStyle(
                                    color: AppColors.tint,
                                    fontSize: 9.5,
                                    fontWeight: FontWeight.w800,
                                    letterSpacing: 0.6,
                                  ),
                                ),
                              ),
                              const SizedBox(height: 6),
                              const Text(
                                'Direct Commercial Leases',
                                style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 17,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                              const SizedBox(height: 2),
                              const Text(
                                'Zero middleman markup · 100% verified lessors',
                                style: TextStyle(
                                  color: AppColors.tint,
                                  fontSize: 11.5,
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

              // Category Pills
              SliverToBoxAdapter(
                child: SizedBox(
                  height: 42,
                  child: ListView.builder(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 18),
                    itemCount: _categories.length,
                    itemBuilder: (context, i) {
                      final (name, icon) = _categories[i];
                      final active = _selectedCategory == name;
                      final pillBg = active
                          ? (isDark ? AppColors.electricBlue : AppColors.primary)
                          : (isDark ? const Color(0xFF1E293B) : surfaceColor);
                      final pillBorder = active
                          ? (isDark ? AppColors.electricBlue : AppColors.primary)
                          : (isDark ? const Color(0xFF334155) : borderColor);
                      final pillTextColor = active
                          ? Colors.white
                          : (isDark ? const Color(0xFFCBD5E1) : AppColors.ink700);
                      final pillIconColor = active
                          ? Colors.white
                          : subtextColor;

                      return Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: InkWell(
                          borderRadius: BorderRadius.circular(999),
                          onTap: () => setState(() => _selectedCategory = name),
                          child: AnimatedContainer(
                            duration: const Duration(milliseconds: 200),
                            padding: const EdgeInsets.symmetric(
                              horizontal: 14,
                              vertical: 8,
                            ),
                            decoration: BoxDecoration(
                              color: pillBg,
                              borderRadius: BorderRadius.circular(999),
                              border: Border.all(
                                color: pillBorder,
                                width: 1,
                              ),
                              boxShadow: active
                                  ? [
                                      BoxShadow(
                                        color: (isDark
                                                ? AppColors.electricBlue
                                                : AppColors.primary)
                                            .withValues(alpha: 0.25),
                                        blurRadius: 8,
                                        offset: const Offset(0, 3),
                                      ),
                                    ]
                                  : null,
                            ),
                            child: Row(
                              children: [
                                Icon(
                                  icon,
                                  size: 15,
                                  color: pillIconColor,
                                ),
                                const SizedBox(width: 6),
                                Text(
                                  name,
                                  style: TextStyle(
                                    fontSize: 12.5,
                                    fontWeight: active
                                        ? FontWeight.w700
                                        : FontWeight.w600,
                                    color: pillTextColor,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                ),
              ),

              // Recently Viewed Carousel
              SliverToBoxAdapter(
                child: FutureBuilder<List<Map<String, dynamic>>>(
                  future: _recentFuture,
                  builder: (context, snapshot) {
                    final recent = snapshot.data ?? [];
                    if (recent.isEmpty) return const SizedBox.shrink();
                    return Container(
                      margin: const EdgeInsets.only(top: 14),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 18),
                            child: Text(
                              'Recently Viewed',
                              style: TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w800,
                                color: titleColor,
                              ),
                            ),
                          ),
                          const SizedBox(height: 8),
                          SizedBox(
                            height: 70,
                            child: ListView.builder(
                              scrollDirection: Axis.horizontal,
                              padding: const EdgeInsets.symmetric(
                                horizontal: 18,
                              ),
                              itemCount: recent.length,
                              itemBuilder: (context, i) {
                                final p = recent[i];
                                return GestureDetector(
                                  onTap: () => Navigator.of(context).push(
                                    MaterialPageRoute(
                                      builder: (_) => PropertyDetailScreen(
                                        propertyId: p['id'] as int,
                                      ),
                                    ),
                                  ),
                                  child: Container(
                                    width: 140,
                                    margin: const EdgeInsets.only(right: 10),
                                    padding: const EdgeInsets.all(6),
                                    decoration: BoxDecoration(
                                      color: surfaceColor,
                                      borderRadius: BorderRadius.circular(12),
                                      border: Border.all(color: borderColor),
                                    ),
                                    child: Row(
                                      children: [
                                        ClipRRect(
                                          borderRadius: BorderRadius.circular(8),
                                          child: SizedBox(
                                            width: 44,
                                            height: 44,
                                            child: p['cover_photo'] != null
                                                ? Image.network(
                                                    p['cover_photo'],
                                                    fit: BoxFit.cover,
                                                    errorBuilder: (c, e, s) =>
                                                        Container(
                                                          color: isDark
                                                              ? const Color(0xFF1E293B)
                                                              : AppColors.bg,
                                                        ),
                                                  )
                                                : Container(
                                                    color: isDark
                                                        ? const Color(0xFF1E293B)
                                                        : AppColors.bg,
                                                  ),
                                          ),
                                        ),
                                        const SizedBox(width: 8),
                                        Expanded(
                                          child: Column(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.start,
                                            mainAxisAlignment:
                                                MainAxisAlignment.center,
                                            children: [
                                              Text(
                                                p['name'] ?? '',
                                                maxLines: 1,
                                                overflow: TextOverflow.ellipsis,
                                                style: TextStyle(
                                                  fontSize: 11,
                                                  fontWeight: FontWeight.w700,
                                                  color: titleColor,
                                                ),
                                              ),
                                              Text(
                                                p['type'] ?? '',
                                                maxLines: 1,
                                                style: const TextStyle(
                                                  fontSize: 9.5,
                                                  color: AppColors.electricBlue,
                                                ),
                                              ),
                                            ],
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                );
                              },
                            ),
                          ),
                        ],
                      ),
                    );
                  },
                ),
              ),

              // Section Title
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(18, 20, 18, 12),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        _selectedCategory == 'All'
                            ? 'Popular Spaces'
                            : '$_selectedCategory Spaces',
                        style: TextStyle(
                          fontSize: 17,
                          fontWeight: FontWeight.w900,
                          color: titleColor,
                          letterSpacing: -0.3,
                        ),
                      ),
                      const Text(
                        'View all',
                        style: TextStyle(
                          fontSize: 12.5,
                          fontWeight: FontWeight.w700,
                          color: AppColors.electricBlue,
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              // 2-Column Grid of Property Cards
              FutureBuilder<List<dynamic>>(
                future: _propertiesFuture,
                builder: (context, snapshot) {
                  if (snapshot.connectionState != ConnectionState.done) {
                    return const SliverToBoxAdapter(
                      child: Padding(
                        padding: EdgeInsets.only(top: 60),
                        child: Center(
                          child: CircularProgressIndicator(
                            color: AppColors.electricBlue,
                          ),
                        ),
                      ),
                    );
                  }
                  if (snapshot.hasError) {
                    return SliverToBoxAdapter(
                      child: AppErrorView(
                        error: snapshot.error,
                        onRetry: _refresh,
                      ),
                    );
                  }

                  var properties = snapshot.data ?? [];

                  if (_selectedCategory != 'All') {
                    properties = properties.where((p) {
                      final type = (p['type'] ?? '').toString().toLowerCase();
                      final filter = _selectedCategory.toLowerCase();
                      return type.contains(filter) || filter.contains(type);
                    }).toList();
                  }

                  if (_query.isNotEmpty) {
                    properties = properties.where((p) {
                      final name = (p['name'] ?? '').toString().toLowerCase();
                      final address = (p['address'] ?? '')
                          .toString()
                          .toLowerCase();
                      final type = (p['type'] ?? '').toString().toLowerCase();
                      return name.contains(_query) ||
                          address.contains(_query) ||
                          type.contains(_query);
                    }).toList();
                  }

                  if (properties.isEmpty) {
                    return SliverToBoxAdapter(
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(24, 50, 24, 120),
                        child: Column(
                          children: [
                            Container(
                              width: 64,
                              height: 64,
                              decoration: BoxDecoration(
                                color: isDark
                                    ? const Color(0xFF1E293B)
                                    : AppColors.accentSoft,
                                borderRadius: BorderRadius.circular(16),
                              ),
                              child: const Icon(
                                Icons.search_off,
                                size: 32,
                                color: AppColors.electricBlue,
                              ),
                            ),
                            const SizedBox(height: 14),
                            Text(
                              _query.isNotEmpty
                                  ? 'No spaces found for "$_query"'
                                  : 'No available spaces in "$_selectedCategory"',
                              style: TextStyle(
                                fontWeight: FontWeight.w800,
                                fontSize: 15,
                                color: titleColor,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'Try selecting another category or clearing your search.',
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                color: subtextColor,
                                fontSize: 13,
                              ),
                            ),
                          ],
                        ),
                      ),
                    );
                  }

                  return SliverPadding(
                    padding: const EdgeInsets.fromLTRB(18, 0, 18, 120),
                    sliver: SliverGrid(
                      gridDelegate:
                          const SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: 2,
                            childAspectRatio: 0.60,
                            crossAxisSpacing: 14,
                            mainAxisSpacing: 14,
                          ),
                      delegate: SliverChildBuilderDelegate((context, i) {
                        final prop = properties[i] as Map<String, dynamic>;

                        return _DripzyGridCard(
                          property: prop,
                        );
                      }, childCount: properties.length),
                    ),
                  );
                },
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Dripzy 2-Column Grid Card Design with Theme-Aware Dark Mode
class _DripzyGridCard extends StatelessWidget {
  final Map<String, dynamic> property;

  const _DripzyGridCard({
    required this.property,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cardColor = isDark ? const Color(0xFF121212) : AppColors.surface;
    final cardBorder = isDark ? const Color(0xFF262626) : AppColors.border.withValues(alpha: 0.8);
    final titleColor = isDark ? Colors.white : AppColors.ink900;
    final subColor = isDark ? const Color(0xFF94A3B8) : AppColors.ink500;
    final rentColor = isDark ? const Color(0xFF60A5FA) : AppColors.primary;

    final coverPhoto = property['cover_photo'] as String?;
    final type = (property['type'] ?? 'RETAIL').toString().toUpperCase();
    final rawRent = property['asking_rent'] ?? property['rent'];
    final double? rent = rawRent != null
        ? double.tryParse(rawRent.toString())
        : null;

    return Container(
      decoration: BoxDecoration(
        color: cardColor,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: cardBorder),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: isDark ? 0.35 : 0.04),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(20),
        child: InkWell(
          borderRadius: BorderRadius.circular(20),
          onTap: () {
            Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) =>
                    PropertyDetailScreen(propertyId: property['id'] as int),
              ),
            );
          },
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Top Picture Container with Floating Action & Badges
              Padding(
                padding: const EdgeInsets.all(6.0),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(16),
                  child: Stack(
                    children: [
                      AspectRatio(
                        aspectRatio: 1.0,
                        child: coverPhoto != null
                            ? Image.network(
                                coverPhoto,
                                fit: BoxFit.cover,
                                errorBuilder: (c, e, s) => Container(
                                  color: isDark
                                      ? const Color(0xFF1E293B)
                                      : AppColors.bg,
                                  child: Icon(
                                    Icons.apartment,
                                    size: 36,
                                    color: subColor,
                                  ),
                                ),
                              )
                            : Container(
                                color: isDark
                                    ? const Color(0xFF1E293B)
                                    : AppColors.bg,
                                child: Icon(
                                  Icons.apartment,
                                  size: 36,
                                  color: subColor,
                                ),
                              ),
                      ),
                      // Floating Type Badge (Top Left)
                      Positioned(
                        top: 8,
                        left: 8,
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 4,
                          ),
                          decoration: BoxDecoration(
                            color: Colors.black.withValues(alpha: 0.75),
                            borderRadius: BorderRadius.circular(999),
                          ),
                          child: Text(
                            type,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 9,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.3,
                            ),
                          ),
                        ),
                      ),
                      // Floating Vacancy Badge (Bottom Left of Photo)
                      Builder(
                        builder: (context) {
                          final avail = (property['available_units'] as num?)?.toInt() ??
                              (property['occupancy_status'] == 'occupied' ? 0 : 1);
                          final String vacancyText = avail <= 0
                              ? 'Fully Occupied'
                              : (avail == 1 ? '1 Vacant Unit' : '$avail Vacant Units');

                          return Positioned(
                            bottom: 8,
                            left: 8,
                            child: Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 7,
                                vertical: 3,
                              ),
                              decoration: BoxDecoration(
                                color: Colors.black.withValues(alpha: 0.70),
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  const Icon(
                                    Icons.meeting_room_rounded,
                                    size: 10,
                                    color: Colors.white70,
                                  ),
                                  const SizedBox(width: 3),
                                  Text(
                                    vacancyText,
                                    style: const TextStyle(
                                      color: Colors.white,
                                      fontSize: 9,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
                    ],
                  ),
                ),
              ),

              // Content Section
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(10, 4, 10, 8),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            property['name'] ?? '',
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 13.5,
                              fontWeight: FontWeight.w800,
                              color: titleColor,
                              height: 1.2,
                            ),
                          ),
                          const SizedBox(height: 3),
                          Row(
                            children: [
                              Icon(
                                Icons.location_on_outlined,
                                size: 12,
                                color: subColor,
                              ),
                              const SizedBox(width: 2),
                              Expanded(
                                child: Text(
                                  property['address'] ?? '',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(
                                    fontSize: 11,
                                    color: subColor,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Expanded(
                            child: (rent != null && rent > 0)
                                ? RichText(
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    text: TextSpan(
                                      children: [
                                        TextSpan(
                                          text: '₱${_formatCardMoney(rent)}',
                                          style: TextStyle(
                                            fontSize: 13.5,
                                            fontWeight: FontWeight.w900,
                                            color: rentColor,
                                            letterSpacing: -0.2,
                                            fontFamily: 'Roboto',
                                          ),
                                        ),
                                        TextSpan(
                                          text: ' /mo',
                                          style: TextStyle(
                                            fontSize: 10,
                                            fontWeight: FontWeight.w600,
                                            color: subColor,
                                            fontFamily: 'Roboto',
                                          ),
                                        ),
                                      ],
                                    ),
                                  )
                                : Text(
                                    'Inquire Rate',
                                    style: TextStyle(
                                      fontSize: 11,
                                      fontWeight: FontWeight.w700,
                                      color: subColor,
                                    ),
                                  ),
                          ),
                          Container(
                            padding: const EdgeInsets.all(5),
                            decoration: BoxDecoration(
                              color: isDark
                                  ? const Color(0xFF1E293B)
                                  : AppColors.accentSoft,
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Icon(
                              Icons.arrow_forward_rounded,
                              size: 12,
                              color: isDark
                                  ? Colors.white
                                  : AppColors.primary,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _formatCardMoney(double amount) {
    final whole = amount.toStringAsFixed(0);
    final reg = RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))');
    return whole.replaceAllMapped(reg, (Match m) => '${m[1]},');
  }
}
