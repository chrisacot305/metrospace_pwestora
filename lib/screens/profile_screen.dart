import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../theme.dart';
import '../services/api_service.dart';
import '../services/theme_service.dart';
import 'login_screen.dart';
import 'lease_contract_screen.dart';
import 'violations_screen.dart';
import 'payment_arrangement_screen.dart';
import 'personal_info_screen.dart';
import 'messages_screen.dart';

class ProfileScreen extends StatefulWidget {
  final Map<String, dynamic>? lease;
  final Function(int)? onNavigateTab;
  final Future<void> Function()? onLeaseMayHaveChanged;

  const ProfileScreen({
    super.key,
    this.lease,
    this.onNavigateTab,
    this.onLeaseMayHaveChanged,
  });

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  String _name = '';
  String _companyName = '';
  Map<String, dynamic>? _leaseData;

  @override
  void initState() {
    super.initState();
    _loadProfile();
  }

  @override
  void didUpdateWidget(covariant ProfileScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.lease != widget.lease) {
      _loadProfile();
    }
  }

  Future<void> _loadProfile() async {
    final name = await ApiService.getUserName();
    final company = await ApiService.getCompanyName();
    if (mounted) {
      setState(() {
        if (name != null && name.isNotEmpty) _name = name;
        if (company != null && company.isNotEmpty) _companyName = company;
      });
    }
    if (widget.lease != null) {
      setState(() => _leaseData = widget.lease);
    } else {
      final l = await ApiService.fetchLeaseStatus();
      if (mounted) setState(() => _leaseData = l);
    }
  }

  Future<void> _logout() async {
    await ApiService.clearSession();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
      (route) => false,
    );
  }

  void _openLeaseStanding() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ViolationsScreen(lease: _leaseData ?? widget.lease),
      ),
    ).then((_) => _loadProfile());
  }



  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final hasLease = _leaseData != null;
    final rawLeaseCompany = _leaseData?['business_name']?.toString().trim() ?? '';
    final leaseCompany = rawLeaseCompany.replaceAll(RegExp(r'\s*\([^)]*\)\s*$'), '').trim();
    final effectiveCompany = _companyName.isNotEmpty
        ? _companyName.replaceAll(RegExp(r'\s*\([^)]*\)\s*$'), '').trim()
        : (leaseCompany.isNotEmpty ? leaseCompany : '');
    final displayProfileTitle = effectiveCompany.isNotEmpty
        ? effectiveCompany
        : (_name.isNotEmpty ? _name.replaceAll(RegExp(r'\s*\([^)]*\)\s*$'), '').trim() : 'Pwestora User');
    final displayProfileSub = effectiveCompany.isNotEmpty
        ? _name
        : (hasLease ? 'Commercial Space' : 'Applicant / Prospective Tenant');
    final avatarLetter = displayProfileTitle.isNotEmpty
        ? displayProfileTitle[0].toUpperCase()
        : 'P';

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF000000) : AppColors.bg,
      body: SafeArea(
        bottom: false,
        child: CustomScrollView(
          slivers: [
            // Top Profile Header
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 20, 20, 16),
                child: Row(
                  children: [
                    // Avatar
                    Container(
                      width: 54,
                      height: 54,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        gradient: const LinearGradient(
                          colors: [Color(0xFF1E3A8A), Color(0xFF3B82F6)],
                        ),
                        border: Border.all(color: Colors.white, width: 2),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.1),
                            blurRadius: 8,
                            offset: const Offset(0, 3),
                          ),
                        ],
                      ),
                      child: Center(
                        child: Text(
                          avatarLetter,
                          style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w900,
                            fontSize: 22,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 16),

                    // Company Name & Personal Name
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            displayProfileTitle,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w800,
                              color: isDark ? Colors.white : AppColors.ink900,
                              letterSpacing: -0.3,
                            ),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            displayProfileSub,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 12.5,
                              color: isDark ? const Color(0xFF94A3B8) : AppColors.ink500,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),

            // Lease Information Card (Only for Active Tenants)
            if (hasLease) ...[
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(18),
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => LeaseContractScreen(
                            lease: _leaseData ?? widget.lease,
                            initialTab: 1,
                            titleOverride: 'Lease Agreement',
                          ),
                        ),
                      );
                    },
                    child: Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: isDark ? const Color(0xFF121212) : Colors.white,
                        borderRadius: BorderRadius.circular(18),
                        border: Border.all(
                          color: isDark ? const Color(0xFF262626) : AppColors.border.withValues(alpha: 0.85),
                          width: 1,
                        ),
                      ),
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  _leaseData?['property_name']?.toString() ?? 'Commercial Lease Agreement',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(
                                    fontSize: 14,
                                    fontWeight: FontWeight.w700,
                                    color: isDark ? Colors.white : AppColors.ink900,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  'Active Agreement • Tap to View Contract',
                                  style: TextStyle(
                                    fontSize: 12,
                                    color: isDark ? const Color(0xFF94A3B8) : AppColors.ink500,
                                    height: 1.3,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          Icon(Icons.chevron_right_rounded, color: isDark ? const Color(0xFF64748B) : AppColors.ink400),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
              const SliverToBoxAdapter(child: SizedBox(height: 16)),
            ] else ...[
              const SliverToBoxAdapter(child: SizedBox(height: 10)),
            ],

            // Section 1: Account (Basic Information)
            _buildSectionHeader('Account'),
            _buildSectionGroup([
              _buildMenuItem(
                icon: Icons.person_outline_rounded,
                title: 'Personal Information',
                onTap: () async {
                  await Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const PersonalInfoScreen()),
                  );
                  _loadProfile();
                },
              ),
            ]),

            const SliverToBoxAdapter(child: SizedBox(height: 16)),

            // Tenant-Only Sections (Documents, Compliance, Settings)
            if (hasLease) ...[
              // Section 2: Documents & Compliance
              _buildSectionHeader('Documents & Compliance'),
              _buildSectionGroup([
                _buildMenuItem(
                  icon: Icons.handshake_outlined,
                  title: 'Restructuring & Relief Policy',
                  onTap: _showRestructuringPolicyModal,
                ),
                _buildMenuItem(
                  icon: Icons.gavel_rounded,
                  title: 'Violations & Disciplinary Records',
                  onTap: _openLeaseStanding,
                ),
                _buildMenuItem(
                  icon: Icons.assignment_outlined,
                  title: 'Lease Agreement',
                  onTap: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => LeaseContractScreen(
                          lease: _leaseData ?? widget.lease,
                          initialTab: 1,
                          titleOverride: 'Lease Agreement',
                        ),
                      ),
                    );
                  },
                ),
                _buildMenuItem(
                  icon: Icons.rule_folder_outlined,
                  title: 'Contract Guidelines',
                  onTap: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => LeaseContractScreen(
                          lease: _leaseData ?? widget.lease,
                          initialTab: 0,
                          titleOverride: 'Contract Guidelines & Rules',
                        ),
                      ),
                    );
                  },
                ),
              ]),

              const SliverToBoxAdapter(child: SizedBox(height: 16)),

              // Section 3: Settings (Tenant)
              _buildSectionHeader('Settings'),
              _buildSectionGroup([
                _buildThemeSwitchItem(),
                _buildMenuItem(
                  icon: Icons.help_outline_rounded,
                  title: 'Help & Support',
                  onTap: _showHelpAndSupportModal,
                ),
              ]),
            ] else ...[
              // Help & Support Section (Applicant)
              _buildSectionHeader('Settings & Support'),
              _buildSectionGroup([
                _buildThemeSwitchItem(),
                _buildMenuItem(
                  icon: Icons.help_outline_rounded,
                  title: 'Help & Support',
                  onTap: _showHelpAndSupportModal,
                ),
              ]),
            ],

            const SliverToBoxAdapter(child: SizedBox(height: 24)),

            // Logout Button
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: TextButton.icon(
                  onPressed: _logout,
                  style: TextButton.styleFrom(
                    foregroundColor: AppColors.error,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                  ),
                  icon: const Icon(Icons.logout_rounded, size: 20),
                  label: const Text(
                    'Sign Out',
                    style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
                  ),
                ),
              ),
            ),

            const SliverToBoxAdapter(
              child: SizedBox(height: 100),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSectionHeader(String title) {
    return SliverToBoxAdapter(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 6, 20, 8),
        child: Text(
          title,
          style: const TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w800,
            color: AppColors.ink400,
            letterSpacing: 0.2,
          ),
        ),
      ),
    );
  }

  Widget _buildSectionGroup(List<Widget> items) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return SliverToBoxAdapter(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 20),
        child: Container(
          decoration: BoxDecoration(
            color: isDark ? const Color(0xFF121212) : Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(
              color: isDark ? const Color(0xFF262626) : AppColors.border.withValues(alpha: 0.85),
              width: 1,
            ),
            boxShadow: [
              BoxShadow(
                color: isDark ? Colors.black.withValues(alpha: 0.25) : const Color(0xFF0A1832).withValues(alpha: 0.05),
                blurRadius: 16,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: Column(
            children: List.generate(items.length, (i) {
              return Column(
                children: [
                  items[i],
                  if (i < items.length - 1)
                    Divider(
                      color: isDark ? const Color(0xFF262626) : AppColors.border,
                      height: 1,
                      indent: 52,
                    ),
                ],
              );
            }),
          ),
        ),
      ),
    );
  }

  Widget _buildMenuItem({
    required IconData icon,
    required String title,
    required VoidCallback onTap,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Row(
          children: [
            Icon(icon, size: 20, color: isDark ? const Color(0xFF94A3B8) : AppColors.ink700),
            const SizedBox(width: 14),
            Expanded(
              child: Text(
                title,
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: isDark ? Colors.white : AppColors.ink900,
                ),
              ),
            ),
            Icon(Icons.chevron_right_rounded, size: 18, color: isDark ? const Color(0xFF64748B) : AppColors.ink400),
          ],
        ),
      ),
    );
  }

  Widget _buildThemeSwitchItem() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return ValueListenableBuilder<ThemeMode>(
      valueListenable: ThemeService.themeModeNotifier,
      builder: (context, themeMode, _) {
        final isDarkModeActive = themeMode == ThemeMode.dark ||
            (themeMode == ThemeMode.system &&
                MediaQuery.platformBrightnessOf(context) == Brightness.dark);

        return Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(6),
                decoration: BoxDecoration(
                  color: isDarkModeActive
                      ? const Color(0xFF1E3A8A).withValues(alpha: 0.4)
                      : const Color(0xFFFEF3C7),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(
                  isDarkModeActive ? Icons.dark_mode_rounded : Icons.light_mode_rounded,
                  size: 18,
                  color: isDarkModeActive ? const Color(0xFF60A5FA) : const Color(0xFFF59E0B),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Dark Mode',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                        color: isDark ? Colors.white : AppColors.ink900,
                      ),
                    ),
                    const SizedBox(height: 1),
                    Text(
                      isDarkModeActive ? 'On (Pitch Black)' : 'Off (Light)',
                      style: const TextStyle(
                        fontSize: 11.5,
                        color: AppColors.ink400,
                      ),
                    ),
                  ],
                ),
              ),
              Switch.adaptive(
                value: isDarkModeActive,
                activeTrackColor: AppColors.electricBlue,
                activeThumbColor: Colors.white,
                onChanged: (val) {
                  ThemeService.setThemeMode(val ? ThemeMode.dark : ThemeMode.light);
                },
              ),
            ],
          ),
        );
      },
    );
  }



  void _showRestructuringPolicyModal() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => Container(
        padding: const EdgeInsets.fromLTRB(22, 16, 22, 28),
        decoration: BoxDecoration(
          color: isDark ? const Color(0xFF161616) : Colors.white,
          borderRadius: const BorderRadius.only(
            topLeft: Radius.circular(28),
            topRight: Radius.circular(28),
          ),
          border: isDark ? const Border(top: BorderSide(color: Color(0xFF262626))) : null,
        ),
        child: SafeArea(
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 44,
                    height: 4,
                    decoration: BoxDecoration(
                      color: isDark ? const Color(0xFF404040) : const Color(0xFFCBD5E1),
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
                const SizedBox(height: 18),
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: isDark ? const Color(0xFF1E3A8A).withValues(alpha: 0.3) : const Color(0xFFEFF6FF),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: const Icon(Icons.handshake_outlined, color: AppColors.electricBlue, size: 22),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Rent Restructuring Policy',
                            style: TextStyle(
                              fontSize: 17,
                              fontWeight: FontWeight.w900,
                              color: isDark ? Colors.white : AppColors.midnightNavy,
                              letterSpacing: -0.3,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            'Commercial payment relief guidelines & terms',
                            style: TextStyle(fontSize: 12, color: isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B)),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: isDark ? const Color(0xFF222222) : const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: isDark ? const Color(0xFF333333) : const Color(0xFFE2E8F0)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '1. Purpose & Eligibility',
                        style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: isDark ? Colors.white : AppColors.midnightNavy),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'Commercial tenants experiencing temporary cash-flow difficulty may formally request a relief plan via the app to avoid default. Requires an active lease with no unresolved Strike 2 or 3 breaches.',
                        style: TextStyle(fontSize: 12, color: isDark ? const Color(0xFFCBD5E1) : const Color(0xFF475569), height: 1.4),
                      ),
                      const SizedBox(height: 12),
                      Text(
                        '2. Available Installment Plans',
                        style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: isDark ? Colors.white : AppColors.midnightNavy),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        '• 2-Payment Split: 50% due on scheduled due date; 50% due on the 15th of next month.\n• 3-Payment Split: 33.3% Part 1, 33.3% on Day 15, and 33.3% on Day 30.',
                        style: TextStyle(fontSize: 12, color: isDark ? const Color(0xFFCBD5E1) : const Color(0xFF475569), height: 1.45),
                      ),
                      const SizedBox(height: 12),
                      Text(
                        '3. Late Fee Freeze on Deferred Part',
                        style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: isDark ? Colors.white : AppColors.midnightNavy),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'Once approved by the lessor, standard late fees (₱500) and default escalation are frozen on the deferred portion, provided installments are paid on time.',
                        style: TextStyle(fontSize: 12, color: isDark ? const Color(0xFFCBD5E1) : const Color(0xFF475569), height: 1.4),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: isDark ? const Color(0xFF450A0A).withValues(alpha: 0.4) : const Color(0xFFFEF2F2),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: isDark ? const Color(0xFF7F1D1D) : const Color(0xFFFCA5A5)),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(Icons.warning_amber_rounded, color: isDark ? const Color(0xFFF87171) : const Color(0xFFDC2626), size: 18),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Breach Policy: If any scheduled installment is missed past its deadline, the relief plan is immediately revoked, the balance accelerates, and an automatic Strike 1 Payment Default violation is issued.',
                          style: TextStyle(
                            fontSize: 11.5,
                            color: isDark ? const Color(0xFFFCA5A5) : const Color(0xFF991B1B),
                            height: 1.35,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 18),
                if (_leaseData != null)
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      onPressed: () {
                        Navigator.pop(ctx);
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => PaymentArrangementScreen(
                              lease: _leaseData!,
                              onArrangementSubmitted: () => _loadProfile(),
                            ),
                          ),
                        );
                      },
                      icon: const Icon(Icons.add_task_rounded, size: 18),
                      label: const Text('Apply for Restructuring Plan', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5)),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                        elevation: 0,
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

  void _showHelpAndSupportModal() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    int expandedFaqIndex = -1;

    final faqs = [
      {
        'q': 'How do I pay rent and upload proof of payment?',
        'a': 'Go to your Home dashboard, tap "Pay Rent", choose GCash or Bank Transfer, and upload your screenshot with the exact reference number. Payment is verified by the lessor within 1–2 hours.',
        'icon': Icons.payment_rounded,
      },
      {
        'q': 'How does the 3-Strike Escalation policy work?',
        'a': 'Strike 1 is a warning, Strike 2 adds a ₱500 penalty, and Strike 3 enters a 5-day Notice to Cure remedy period. You can submit a formal dispute with explanations from your Violations tab.',
        'icon': Icons.gavel_rounded,
      },
      {
        'q': 'Can I split my rent if experiencing cash flow delays?',
        'a': 'Yes! Active tenants in good standing can apply for a 50/50 Payment Restructuring plan directly from the Payment Arrangement screen with zero late penalty escalation.',
        'icon': Icons.handshake_outlined,
      },
      {
        'q': 'How do I request physical stall repairs or maintenance?',
        'a': 'Tap the elevated (+) Report button on your bottom navigation dock to upload photos and submit an urgent ticket to building engineering.',
        'icon': Icons.build_circle_outlined,
      },
    ];

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            return Container(
              constraints: BoxConstraints(
                maxHeight: MediaQuery.of(ctx).size.height * 0.88,
              ),
              decoration: BoxDecoration(
                color: isDark ? const Color(0xFF161616) : Colors.white,
                borderRadius: const BorderRadius.vertical(top: Radius.circular(26)),
              ),
              child: SafeArea(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Center(
                      child: Container(
                        width: 44,
                        height: 4,
                        margin: const EdgeInsets.only(top: 12, bottom: 8),
                        decoration: BoxDecoration(
                          color: isDark ? const Color(0xFF333333) : const Color(0xFFE2E8F0),
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),
                    ),
                    Flexible(
                      child: SingleChildScrollView(
                        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Header
                            Row(
                              children: [
                                Container(
                                  padding: const EdgeInsets.all(9),
                                  decoration: BoxDecoration(
                                    color: isDark
                                        ? const Color(0xFF1E3A8A).withValues(alpha: 0.35)
                                        : const Color(0xFFEFF6FF),
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  child: const Icon(
                                    Icons.support_agent_rounded,
                                    color: AppColors.electricBlue,
                                    size: 24,
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        'Help & Support',
                                        style: TextStyle(
                                          fontSize: 17,
                                          fontWeight: FontWeight.w900,
                                          color: isDark ? Colors.white : AppColors.midnightNavy,
                                          letterSpacing: -0.3,
                                        ),
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                      const SizedBox(height: 2),
                                      Text(
                                        'Direct assistance and commercial lease answers',
                                        style: TextStyle(
                                          fontSize: 12,
                                          color: isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B),
                                        ),
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 20),

                            // Section 1: Contact Channels
                            Text(
                              'Direct Support Channels',
                              style: TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w800,
                                color: isDark ? Colors.white70 : const Color(0xFF334155),
                                letterSpacing: 0.1,
                              ),
                            ),
                            const SizedBox(height: 10),

                            // Channel 1: Message Lessor
                            _buildSupportChannelTile(
                              icon: Icons.chat_outlined,
                              iconColor: AppColors.electricBlue,
                              iconBg: isDark
                                  ? const Color(0xFF1E3A8A).withValues(alpha: 0.3)
                                  : const Color(0xFFEFF6FF),
                              title: 'Message Lessor / Admin',
                              subtitle: 'Open real-time in-app discussion',
                              trailing: const Icon(
                                Icons.arrow_forward_ios_rounded,
                                size: 12,
                                color: AppColors.electricBlue,
                              ),
                              onTap: () {
                                Navigator.pop(ctx);
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => MessagesScreen(lease: _leaseData),
                                  ),
                                );
                              },
                              isDark: isDark,
                            ),
                            const SizedBox(height: 8),

                            // Channel 2: Phone Hotline
                            _buildSupportChannelTile(
                              icon: Icons.phone_in_talk_outlined,
                              iconColor: const Color(0xFF10B981),
                              iconBg: isDark
                                  ? const Color(0xFF065F46).withValues(alpha: 0.3)
                                  : const Color(0xFFECFDF5),
                              title: 'Property Admin Hotline',
                              subtitle: '+63 (02) 8888-7937 • Mon–Sat 8am–6pm',
                              trailing: const Icon(
                                Icons.copy_rounded,
                                size: 14,
                                color: Color(0xFF10B981),
                              ),
                              onTap: () {
                                Clipboard.setData(const ClipboardData(text: '+63288887937'));
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(
                                    content: const Text('Admin hotline copied to clipboard: (02) 8888-7937'),
                                    backgroundColor: const Color(0xFF10B981),
                                    behavior: SnackBarBehavior.floating,
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                  ),
                                );
                              },
                              isDark: isDark,
                            ),
                            const SizedBox(height: 8),

                            // Channel 3: Email Support
                            _buildSupportChannelTile(
                              icon: Icons.mail_outline_rounded,
                              iconColor: const Color(0xFF6366F1),
                              iconBg: isDark
                                  ? const Color(0xFF312E81).withValues(alpha: 0.3)
                                  : const Color(0xFFEEF2FF),
                              title: 'Email Customer Support',
                              subtitle: 'support@pwestora.com • 24hr response time',
                              trailing: const Icon(
                                Icons.copy_rounded,
                                size: 14,
                                color: Color(0xFF6366F1),
                              ),
                              onTap: () {
                                Clipboard.setData(const ClipboardData(text: 'support@pwestora.com'));
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(
                                    content: const Text('Support email copied to clipboard: support@pwestora.com'),
                                    backgroundColor: const Color(0xFF6366F1),
                                    behavior: SnackBarBehavior.floating,
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                  ),
                                );
                              },
                              isDark: isDark,
                            ),

                            const SizedBox(height: 22),

                            // Section 2: Frequently Asked Questions
                            Text(
                              'Frequently Asked Questions',
                              style: TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w800,
                                color: isDark ? Colors.white70 : const Color(0xFF334155),
                                letterSpacing: 0.1,
                              ),
                            ),
                            const SizedBox(height: 10),

                            ...List.generate(faqs.length, (idx) {
                              final item = faqs[idx];
                              final isExpanded = expandedFaqIndex == idx;
                              return Container(
                                margin: const EdgeInsets.only(bottom: 8),
                                decoration: BoxDecoration(
                                  color: isDark ? const Color(0xFF1E1E1E) : const Color(0xFFF8FAFC),
                                  borderRadius: BorderRadius.circular(14),
                                  border: Border.all(
                                    color: isExpanded
                                        ? AppColors.electricBlue.withValues(alpha: 0.5)
                                        : (isDark ? const Color(0xFF2B2B2B) : const Color(0xFFE2E8F0)),
                                  ),
                                ),
                                child: InkWell(
                                  borderRadius: BorderRadius.circular(14),
                                  onTap: () {
                                    setModalState(() {
                                      expandedFaqIndex = isExpanded ? -1 : idx;
                                    });
                                  },
                                  child: Padding(
                                    padding: const EdgeInsets.all(14),
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Row(
                                          children: [
                                            Icon(
                                              item['icon'] as IconData,
                                              size: 16,
                                              color: isExpanded
                                                  ? AppColors.electricBlue
                                                  : (isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B)),
                                            ),
                                            const SizedBox(width: 10),
                                            Expanded(
                                              child: Text(
                                                item['q'] as String,
                                                style: TextStyle(
                                                  fontSize: 13,
                                                  fontWeight: FontWeight.w700,
                                                  color: isDark ? Colors.white : AppColors.midnightNavy,
                                                ),
                                              ),
                                            ),
                                            const SizedBox(width: 6),
                                            Icon(
                                              isExpanded
                                                  ? Icons.keyboard_arrow_up_rounded
                                                  : Icons.keyboard_arrow_down_rounded,
                                              color: const Color(0xFF64748B),
                                              size: 20,
                                            ),
                                          ],
                                        ),
                                        if (isExpanded) ...[
                                          const SizedBox(height: 10),
                                          const Divider(height: 1),
                                          const SizedBox(height: 10),
                                          Text(
                                            item['a'] as String,
                                            style: TextStyle(
                                              fontSize: 12,
                                              color: isDark ? const Color(0xFFCBD5E1) : const Color(0xFF475569),
                                              height: 1.45,
                                            ),
                                          ),
                                        ],
                                      ],
                                    ),
                                  ),
                                ),
                              );
                            }),

                            const SizedBox(height: 16),
                            Center(
                              child: Text(
                                'Pwestora Commercial Leasing Platform • v1.0.4',
                                style: TextStyle(
                                  fontSize: 11,
                                  color: isDark ? const Color(0xFF64748B) : const Color(0xFF94A3B8),
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  Widget _buildSupportChannelTile({
    required IconData icon,
    required Color iconColor,
    required Color iconBg,
    required String title,
    required String subtitle,
    required Widget trailing,
    required VoidCallback onTap,
    required bool isDark,
  }) {
    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: isDark ? const Color(0xFF1E1E1E) : const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: isDark ? const Color(0xFF2B2B2B) : const Color(0xFFE2E8F0),
          ),
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: iconBg,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, size: 18, color: iconColor),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: isDark ? Colors.white : AppColors.midnightNavy,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: TextStyle(
                      fontSize: 11.5,
                      color: isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B),
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            trailing,
          ],
        ),
      ),
    );
  }
}
