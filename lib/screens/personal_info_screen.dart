import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../theme.dart';
import '../services/api_service.dart';

class PersonalInfoScreen extends StatefulWidget {
  const PersonalInfoScreen({super.key});

  @override
  State<PersonalInfoScreen> createState() => _PersonalInfoScreenState();
}

class _PersonalInfoScreenState extends State<PersonalInfoScreen> {
  bool _loading = true;
  String _fullName = '';
  String _companyName = '';
  String _email = '';
  String _phone = '';

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() => _loading = true);
    final profile = await ApiService.fetchUserProfile();
    if (mounted) {
      setState(() {
        _fullName = profile['full_name']?.toString().trim().isNotEmpty == true
            ? profile['full_name'].toString().trim()
            : 'Pwestora User';
        _companyName = profile['company_name']?.toString().trim() ?? '';
        _email = profile['email']?.toString().trim().isNotEmpty == true
            ? profile['email'].toString().trim()
            : 'user@pwestora.com';
        _phone = profile['phone']?.toString().trim().isNotEmpty == true
            ? profile['phone'].toString().trim()
            : '';
        _loading = false;
      });
    }
  }

  /// Opens a clean bottom sheet allowing user to customize their details (Name, Email, Phone)
  void _openEditFieldSheet({
    required String title,
    required String currentValue,
    required String label,
    required TextInputType keyboardType,
    required IconData icon,
    List<TextInputFormatter>? inputFormatters,
    String? hintText,
    required Future<void> Function(String newValue) onSave,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final ctrl = TextEditingController(text: currentValue);
    bool saving = false;
    String? sheetError;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            return Container(
              padding: EdgeInsets.only(
                left: 22,
                right: 22,
                top: 20,
                bottom: MediaQuery.of(context).viewInsets.bottom + 24,
              ),
              decoration: BoxDecoration(
                color: isDark ? const Color(0xFF161616) : Colors.white,
                borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.15),
                    blurRadius: 20,
                    offset: const Offset(0, -4),
                  ),
                ],
              ),
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Center(
                      child: Container(
                        width: 40,
                        height: 4,
                        decoration: BoxDecoration(
                          color: isDark ? const Color(0xFF333333) : AppColors.border,
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),
                    ),
                    const SizedBox(height: 18),

                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(
                            color: AppColors.primary.withValues(alpha: 0.08),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Icon(icon, color: AppColors.primary, size: 22),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                title,
                                style: TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w800,
                                  color: isDark ? Colors.white : AppColors.ink900,
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                'Update your $label information',
                                style: TextStyle(
                                  fontSize: 12,
                                  color: isDark ? const Color(0xFF94A3B8) : AppColors.ink500,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 20),

                    if (sheetError != null) ...[
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        decoration: BoxDecoration(
                          color: AppColors.errorSoft,
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.error_outline, size: 16, color: AppColors.error),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                sheetError!,
                                style: const TextStyle(color: AppColors.error, fontSize: 12, fontWeight: FontWeight.w600),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 14),
                    ],

                    Text(
                      label,
                      style: TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w700,
                        color: isDark ? Colors.white70 : AppColors.ink700,
                      ),
                    ),
                    const SizedBox(height: 6),
                    TextField(
                      controller: ctrl,
                      autofocus: true,
                      keyboardType: keyboardType,
                      inputFormatters: inputFormatters,
                      decoration: InputDecoration(
                        hintText: hintText ?? 'Enter $label',
                        prefixIcon: Icon(icon, size: 19),
                      ),
                    ),
                    const SizedBox(height: 24),

                    SizedBox(
                      width: double.infinity,
                      height: 52,
                      child: ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.primary,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                        ),
                        onPressed: saving
                            ? null
                            : () async {
                                final newVal = ctrl.text.trim();
                                if (newVal.isEmpty) {
                                  setSheetState(() => sheetError = '$label cannot be empty.');
                                  return;
                                }
                                setSheetState(() {
                                  saving = true;
                                  sheetError = null;
                                });
                                try {
                                  await onSave(newVal);
                                  if (context.mounted) Navigator.pop(ctx);
                                } catch (e) {
                                  setSheetState(() {
                                    saving = false;
                                    sheetError = ApiService.errorMessage(e);
                                  });
                                }
                              },
                        child: saving
                            ? const SizedBox(
                                width: 22,
                                height: 22,
                                child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                              )
                            : const Text(
                                'Save Changes',
                                style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: Colors.white),
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

  /// Displays the Change Password modal (Design only as requested)
  void _openChangePasswordSheet() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final currentPassCtrl = TextEditingController();
    final newPassCtrl = TextEditingController();
    final confirmPassCtrl = TextEditingController();
    bool obscureCurrent = true;
    bool obscureNew = true;
    bool obscureConfirm = true;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            return Container(
              padding: EdgeInsets.only(
                left: 22,
                right: 22,
                top: 20,
                bottom: MediaQuery.of(context).viewInsets.bottom + 24,
              ),
              decoration: BoxDecoration(
                color: isDark ? const Color(0xFF161616) : Colors.white,
                borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.15),
                    blurRadius: 20,
                    offset: const Offset(0, -4),
                  ),
                ],
              ),
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Center(
                      child: Container(
                        width: 40,
                        height: 4,
                        decoration: BoxDecoration(
                          color: isDark ? const Color(0xFF333333) : AppColors.border,
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),
                    ),
                    const SizedBox(height: 18),

                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(
                            color: AppColors.primary.withValues(alpha: 0.08),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: const Icon(Icons.lock_reset_rounded, color: AppColors.primary, size: 22),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Change Password',
                                style: TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w800,
                                  color: isDark ? Colors.white : AppColors.ink900,
                                ),
                              ),
                              const SizedBox(height: 3),
                              Text(
                                'Choose a secure password of at least 8 characters',
                                style: TextStyle(
                                  fontSize: 12,
                                  color: isDark ? const Color(0xFF94A3B8) : AppColors.ink500,
                                  height: 1.3,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 22),

                    // Current Password
                    Text(
                      'Current Password',
                      style: TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w700,
                        color: isDark ? Colors.white70 : AppColors.ink700,
                      ),
                    ),
                    const SizedBox(height: 6),
                    TextField(
                      controller: currentPassCtrl,
                      obscureText: obscureCurrent,
                      decoration: InputDecoration(
                        hintText: '••••••••',
                        prefixIcon: const Icon(Icons.lock_outline, size: 19),
                        suffixIcon: IconButton(
                          icon: Icon(obscureCurrent ? Icons.visibility_off : Icons.visibility, size: 19),
                          onPressed: () => setSheetState(() => obscureCurrent = !obscureCurrent),
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),

                    // New Password
                    Text(
                      'New Password',
                      style: TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w700,
                        color: isDark ? Colors.white70 : AppColors.ink700,
                      ),
                    ),
                    const SizedBox(height: 6),
                    TextField(
                      controller: newPassCtrl,
                      obscureText: obscureNew,
                      decoration: InputDecoration(
                        hintText: '••••••••',
                        prefixIcon: const Icon(Icons.lock_outline, size: 19),
                        suffixIcon: IconButton(
                          icon: Icon(obscureNew ? Icons.visibility_off : Icons.visibility, size: 19),
                          onPressed: () => setSheetState(() => obscureNew = !obscureNew),
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),

                    // Confirm New Password
                    Text(
                      'Confirm New Password',
                      style: TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w700,
                        color: isDark ? Colors.white70 : AppColors.ink700,
                      ),
                    ),
                    const SizedBox(height: 6),
                    TextField(
                      controller: confirmPassCtrl,
                      obscureText: obscureConfirm,
                      decoration: InputDecoration(
                        hintText: '••••••••',
                        prefixIcon: const Icon(Icons.lock_outline, size: 19),
                        suffixIcon: IconButton(
                          icon: Icon(obscureConfirm ? Icons.visibility_off : Icons.visibility, size: 19),
                          onPressed: () => setSheetState(() => obscureConfirm = !obscureConfirm),
                        ),
                      ),
                    ),
                    const SizedBox(height: 24),

                    // Save Password Button (Design Only Preview)
                    SizedBox(
                      width: double.infinity,
                      height: 52,
                      child: ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.primary,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                        ),
                        onPressed: () {
                          Navigator.pop(ctx);
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(
                              backgroundColor: AppColors.primary,
                              behavior: SnackBarBehavior.floating,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                              content: const Row(
                                children: [
                                  Icon(Icons.info_outline_rounded, color: AppColors.cyanGlow, size: 20),
                                  SizedBox(width: 10),
                                  Expanded(
                                    child: Text(
                                      'Change Password design preview. Backend logic will be wired next.',
                                      style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Colors.white),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          );
                        },
                        child: const Text(
                          'Update Password',
                          style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: Colors.white),
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

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF000000) : AppColors.bg,
      appBar: AppBar(
        leading: const AppBackButton(),
        title: const Text('Personal Information'),
        backgroundColor: isDark ? const Color(0xFF121212) : AppColors.surface,
        elevation: 0,
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
          : RefreshIndicator(
              onRefresh: _loadData,
              color: AppColors.primary,
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
                padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 18),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 520),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // User Avatar & Name Card (Without "certified tenant" badge)
                        Container(
                          padding: const EdgeInsets.all(20),
                          decoration: BoxDecoration(
                            color: isDark ? const Color(0xFF121212) : Colors.white,
                            borderRadius: BorderRadius.circular(20),
                            border: Border.all(
                              color: isDark ? const Color(0xFF262626) : AppColors.border,
                            ),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: isDark ? 0.2 : 0.04),
                                blurRadius: 14,
                                offset: const Offset(0, 4),
                              ),
                            ],
                          ),
                          child: Row(
                            children: [
                              Container(
                                width: 56,
                                height: 56,
                                decoration: BoxDecoration(
                                  shape: BoxShape.circle,
                                  gradient: const LinearGradient(
                                    colors: [Color(0xFF0A1832), Color(0xFF1E6BFF)],
                                    begin: Alignment.topLeft,
                                    end: Alignment.bottomRight,
                                  ),
                                  boxShadow: [
                                    BoxShadow(
                                      color: AppColors.primary.withValues(alpha: 0.2),
                                      blurRadius: 10,
                                      offset: const Offset(0, 4),
                                    ),
                                  ],
                                ),
                                alignment: Alignment.center,
                                child: Text(
                                  _companyName.isNotEmpty
                                      ? _companyName[0].toUpperCase()
                                      : (_fullName.isNotEmpty ? _fullName[0].toUpperCase() : 'U'),
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w900,
                                    fontSize: 24,
                                  ),
                                ),
                              ),
                              const SizedBox(width: 16),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      _companyName.isNotEmpty ? _companyName : _fullName,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(
                                        fontSize: 18,
                                        fontWeight: FontWeight.w800,
                                        color: isDark ? Colors.white : AppColors.ink900,
                                        letterSpacing: -0.2,
                                      ),
                                    ),
                                    const SizedBox(height: 3),
                                    Text(
                                      _companyName.isNotEmpty ? _fullName : _email,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(
                                        fontSize: 13,
                                        color: isDark ? const Color(0xFF94A3B8) : AppColors.ink500,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 24),

                        // Section 1: Contact & Personal Details
                        Padding(
                          padding: const EdgeInsets.only(left: 4, bottom: 8),
                          child: Text(
                            'COMMERCIAL IDENTITY & CONTACT',
                            style: TextStyle(
                              fontSize: 11.5,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.6,
                              color: isDark ? const Color(0xFF94A3B8) : AppColors.ink500,
                            ),
                          ),
                        ),

                        Container(
                          decoration: BoxDecoration(
                            color: isDark ? const Color(0xFF121212) : Colors.white,
                            borderRadius: BorderRadius.circular(20),
                            border: Border.all(
                              color: isDark ? const Color(0xFF262626) : AppColors.border,
                            ),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: isDark ? 0.2 : 0.04),
                                blurRadius: 12,
                                offset: const Offset(0, 3),
                              ),
                            ],
                          ),
                          child: Column(
                            children: [
                              // 1. Company / Business Name Item (with edit pen icon)
                              _buildEditableInfoTile(
                                isDark: isDark,
                                icon: Icons.storefront_outlined,
                                label: 'Company / Business Name',
                                value: _companyName.isNotEmpty ? _companyName : 'Tap to set company name',
                                isPlaceholder: _companyName.isEmpty,
                                onEdit: () {
                                  _openEditFieldSheet(
                                    title: 'Edit Company / Brand',
                                    currentValue: _companyName,
                                    label: 'Company / Brand Name',
                                    keyboardType: TextInputType.text,
                                    icon: Icons.storefront_outlined,
                                    hintText: 'e.g. Daily Grind Cafe & Bakes',
                                    onSave: (val) async {
                                      await ApiService.updateUserProfile(
                                        fullName: _fullName,
                                        companyName: val,
                                        email: _email,
                                        phone: _phone,
                                      );
                                      if (mounted) setState(() => _companyName = val);
                                    },
                                  );
                                },
                              ),
                              _buildDivider(isDark),

                              // 2. Full Name Item (with edit pen icon)
                              _buildEditableInfoTile(
                                isDark: isDark,
                                icon: Icons.person_outline_rounded,
                                label: 'Authorized Representative / Name',
                                value: _fullName,
                                onEdit: () {
                                  _openEditFieldSheet(
                                    title: 'Edit Representative Name',
                                    currentValue: _fullName,
                                    label: 'Full Name',
                                    keyboardType: TextInputType.name,
                                    icon: Icons.person_outline_rounded,
                                    hintText: 'Juan Dela Cruz',
                                    onSave: (val) async {
                                      await ApiService.updateUserProfile(
                                        fullName: val,
                                        companyName: _companyName,
                                        email: _email,
                                        phone: _phone,
                                      );
                                      if (mounted) setState(() => _fullName = val);
                                    },
                                  );
                                },
                              ),
                              _buildDivider(isDark),

                              // 2. Email Address Item (with edit pen icon)
                              _buildEditableInfoTile(
                                isDark: isDark,
                                icon: Icons.email_outlined,
                                label: 'Gmail / Email Address',
                                value: _email,
                                onEdit: () {
                                  _openEditFieldSheet(
                                    title: 'Edit Email Address',
                                    currentValue: _email,
                                    label: 'Email Address',
                                    keyboardType: TextInputType.emailAddress,
                                    icon: Icons.email_outlined,
                                    hintText: 'you@example.com',
                                    onSave: (val) async {
                                      await ApiService.updateUserProfile(
                                        fullName: _fullName,
                                        companyName: _companyName,
                                        email: val,
                                        phone: _phone,
                                      );
                                      if (mounted) setState(() => _email = val);
                                    },
                                  );
                                },
                              ),
                              _buildDivider(isDark),

                              // 3. Mobile Number Item (with edit pen icon)
                              _buildEditableInfoTile(
                                isDark: isDark,
                                icon: Icons.phone_android_rounded,
                                label: 'Mobile Number',
                                value: _phone.isNotEmpty ? _phone : 'Tap to add mobile number',
                                isPlaceholder: _phone.isEmpty,
                                trailingBadge: _phone.isNotEmpty
                                    ? Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                        decoration: BoxDecoration(
                                          color: AppColors.accentSoft,
                                          borderRadius: BorderRadius.circular(8),
                                        ),
                                        child: const Row(
                                          mainAxisSize: MainAxisSize.min,
                                          children: [
                                            Icon(Icons.sms_outlined, size: 12, color: AppColors.electricBlue),
                                            SizedBox(width: 4),
                                            Text(
                                              'SMS Active',
                                              style: TextStyle(
                                                fontSize: 11,
                                                fontWeight: FontWeight.w700,
                                                color: AppColors.electricBlue,
                                              ),
                                            ),
                                          ],
                                        ),
                                      )
                                    : null,
                                onEdit: () {
                                  _openEditFieldSheet(
                                    title: 'Edit Mobile Number',
                                    currentValue: _phone,
                                    label: 'Mobile Number',
                                    keyboardType: TextInputType.phone,
                                    icon: Icons.phone_android_rounded,
                                    hintText: '09171234567',
                                    inputFormatters: [
                                      FilteringTextInputFormatter.digitsOnly,
                                      LengthLimitingTextInputFormatter(11),
                                    ],
                                    onSave: (val) async {
                                      await ApiService.updateUserProfile(
                                        fullName: _fullName,
                                        companyName: _companyName,
                                        email: _email,
                                        phone: val,
                                      );
                                      if (mounted) setState(() => _phone = val);
                                    },
                                  );
                                },
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 24),

                        // Section 2: Security & Password
                        Padding(
                          padding: const EdgeInsets.only(left: 4, bottom: 8),
                          child: Text(
                            'SECURITY',
                            style: TextStyle(
                              fontSize: 11.5,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.6,
                              color: isDark ? const Color(0xFF94A3B8) : AppColors.ink500,
                            ),
                          ),
                        ),

                        Container(
                          decoration: BoxDecoration(
                            color: isDark ? const Color(0xFF121212) : Colors.white,
                            borderRadius: BorderRadius.circular(20),
                            border: Border.all(
                              color: isDark ? const Color(0xFF262626) : AppColors.border,
                            ),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: isDark ? 0.2 : 0.04),
                                blurRadius: 12,
                                offset: const Offset(0, 3),
                              ),
                            ],
                          ),
                          child: Material(
                            color: Colors.transparent,
                            borderRadius: BorderRadius.circular(20),
                            child: InkWell(
                              borderRadius: BorderRadius.circular(20),
                              onTap: _openChangePasswordSheet,
                              child: Padding(
                                padding: const EdgeInsets.all(16),
                                child: Row(
                                  children: [
                                    Container(
                                      width: 42,
                                      height: 42,
                                      decoration: BoxDecoration(
                                        color: isDark
                                            ? const Color(0xFF1E293B)
                                            : const Color(0xFFF1F5F9),
                                        borderRadius: BorderRadius.circular(12),
                                      ),
                                      child: const Icon(
                                        Icons.lock_outline_rounded,
                                        size: 20,
                                        color: AppColors.primary,
                                      ),
                                    ),
                                    const SizedBox(width: 14),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            'Password',
                                            style: TextStyle(
                                              fontSize: 14.5,
                                              fontWeight: FontWeight.w700,
                                              color: isDark ? Colors.white : AppColors.ink900,
                                            ),
                                          ),
                                          const SizedBox(height: 2),
                                          Text(
                                            '•••••••••••• (Change Password)',
                                            style: TextStyle(
                                              fontSize: 12.5,
                                              color: isDark ? const Color(0xFF94A3B8) : AppColors.ink500,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                      decoration: BoxDecoration(
                                        color: isDark
                                            ? const Color(0xFF1E293B)
                                            : const Color(0xFFF1F5F9),
                                        borderRadius: BorderRadius.circular(10),
                                      ),
                                      child: Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          Text(
                                            'Edit',
                                            style: TextStyle(
                                              fontSize: 12,
                                              fontWeight: FontWeight.w700,
                                              color: isDark ? Colors.white70 : AppColors.ink700,
                                            ),
                                          ),
                                          const SizedBox(width: 4),
                                          Icon(
                                            Icons.chevron_right_rounded,
                                            size: 16,
                                            color: isDark ? Colors.white54 : AppColors.ink400,
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

                        const SizedBox(height: 28),
                      ],
                    ),
                  ),
                ),
              ),
            ),
    );
  }

  /// Builds each info row with an interactive edit pen icon button
  Widget _buildEditableInfoTile({
    required bool isDark,
    required IconData icon,
    required String label,
    required String value,
    required VoidCallback onEdit,
    bool isPlaceholder = false,
    Widget? trailingBadge,
  }) {
    return InkWell(
      onTap: onEdit,
      borderRadius: BorderRadius.circular(16),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: isDark ? const Color(0xFF1E293B) : const Color(0xFFF1F5F9),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(
                icon,
                size: 20,
                color: AppColors.primary,
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    label,
                    style: TextStyle(
                      fontSize: 11.5,
                      fontWeight: FontWeight.w600,
                      color: isDark ? const Color(0xFF94A3B8) : AppColors.ink500,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    value,
                    style: TextStyle(
                      fontSize: 14.5,
                      fontWeight: isPlaceholder ? FontWeight.w500 : FontWeight.w700,
                      color: isPlaceholder
                          ? (isDark ? const Color(0xFF64748B) : AppColors.ink400)
                          : (isDark ? Colors.white : AppColors.ink900),
                      fontStyle: isPlaceholder ? FontStyle.italic : FontStyle.normal,
                    ),
                  ),
                ],
              ),
            ),
            if (trailingBadge != null) ...[
              trailingBadge,
              const SizedBox(width: 8),
            ],
            // Edit Pen Button
            IconButton(
              icon: Icon(
                Icons.edit_outlined,
                size: 18,
                color: isDark ? const Color(0xFF94A3B8) : AppColors.primary,
              ),
              tooltip: 'Edit $label',
              onPressed: onEdit,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDivider(bool isDark) {
    return Divider(
      height: 1,
      thickness: 1,
      indent: 16,
      endIndent: 16,
      color: isDark ? const Color(0xFF262626) : AppColors.borderLight,
    );
  }
}
