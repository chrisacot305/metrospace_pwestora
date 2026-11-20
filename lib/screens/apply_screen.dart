import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../theme.dart';
import '../services/api_service.dart';
import 'lease_contract_screen.dart';

class _UploadedDoc {
  final Uint8List bytes;
  final String filename;
  final String path;
  _UploadedDoc({required this.bytes, required this.filename, required this.path});
}

class ApplyScreen extends StatefulWidget {
  final int propertyId;
  final String propertyName;
  final double? askingRent;
  final Map<String, dynamic>? contractTemplate;
  final dynamic leaseTerm;
  final int? termMonths;

  const ApplyScreen({
    super.key,
    required this.propertyId,
    required this.propertyName,
    this.askingRent,
    this.contractTemplate,
    this.leaseTerm,
    this.termMonths,
  });

  @override
  State<ApplyScreen> createState() => _ApplyScreenState();
}

class _ApplyScreenState extends State<ApplyScreen> {
  final _formKey = GlobalKey<FormState>();
  final _businessCtrl = TextEditingController();
  final _customRentCtrl = TextEditingController();
  
  // Step State
  int _currentStep = 0; // 0: Commercial Proposal, 1: Verification Documents, 2: Interactive Contract Negotiation Checklist

  // Step 1: Proposal State
  String _selectedCategory = 'Food & Beverage';
  int _selectedTerm = 12; // months (6, 12, 24, 36)
  DateTime? _targetMoveInDate;
  bool _customRateMode = false;
  double _effectiveRent = 0.0;

  bool _loading = false;
  String? _error;
  bool _submitted = false;
  Map<String, dynamic>? _submittedNegotiationPayload;

  // Uploaded documents storage (key -> doc)
  final Map<String, _UploadedDoc> _uploadedDocs = {};

  final List<Map<String, dynamic>> _businessCategories = [
    {'label': 'Food & Beverage', 'icon': Icons.restaurant_rounded},
    {'label': 'Retail & Fashion', 'icon': Icons.shopping_bag_outlined},
    {'label': 'Services & Care', 'icon': Icons.content_cut_rounded},
    {'label': 'General / Other', 'icon': Icons.storefront_outlined},
  ];

  String get _formattedLeaseDuration {
    if (_selectedTerm >= 12 && _selectedTerm % 12 == 0) {
      final years = _selectedTerm ~/ 12;
      return '$years ${years == 1 ? "Year" : "Years"} ($_selectedTerm Months)';
    }
    return '$_selectedTerm Months';
  }

  final List<Map<String, dynamic>> _docFields = [
    {
      'key': 'valid_id',
      'title': 'Valid Government ID',
      'subtitle': "Owner / Authorized Signatory's Passport, Driver's License, UMID, PRC, etc.",
      'icon': Icons.badge_outlined,
      'recommended': true,
    },
    {
      'key': 'sec_dti',
      'title': 'DTI / SEC Registration',
      'subtitle': 'Certificate of Business Name or SEC Registration Certificate.',
      'icon': Icons.assignment_outlined,
      'recommended': true,
    },
    {
      'key': 'business_permit',
      'title': "Mayor's / Business Permit",
      'subtitle': 'Current City / Municipal Business Permit or application official receipt.',
      'icon': Icons.verified_outlined,
      'recommended': false,
    },
    {
      'key': 'bir_cert',
      'title': 'BIR Registration (Form 2303)',
      'subtitle': 'Certificate of Registration issued by the Bureau of Internal Revenue.',
      'icon': Icons.receipt_long_outlined,
      'recommended': false,
    },
  ];

  // Dynamic Commercial Lease Clauses (loaded from the listing's assigned contract template)
  List<Map<String, dynamic>> _checklistClauses = [];
  bool _contractLoading = false;
  String _templateName = 'Pwestora Commercial Lease';
  bool _isCustomTemplate = false;
  Map<String, dynamic>? _savedDraftChecklist;

  static const Set<String> _kPlatformRuleKeys = {
    'rent_grace_period',
    'violations_discipline_policy',
    'rent_restructuring_policy',
  };

  static List<Map<String, dynamic>> _defaultPlatformRules() {
    return [
      {
        'key': 'rent_grace_period',
        'label': '5-Day Rent Grace Period & Default Rules',
        'standard':
            "Monthly rent is due on the contract due date. A 5-calendar-day grace period is granted with zero late penalty. If unpaid by Day 5, any Security Deposit balance is auto-applied to cover rent; remaining unpaid rent past Day 5 carries over to the next billing cycle and triggers 3-strike escalation (Strike 1: ₱0 warning, Strike 2: ₱500 late fee, Strike 3: ₱1,000 fee & Eviction Lock).",
        'ref': 'Civil Code Art. 1169 & Art. 1657; Pwestora Automated Billing Standard',
      },
      {
        'key': 'violations_discipline_policy',
        'label': 'Contractual Compliance & 3-Strike Disciplinary Policy',
        'standard':
            "Violations of lease terms (noise, parking, alterations, unauthorized use) or late rent past grace period trigger the unified 3-Strike Disciplinary Policy. Conduct infractions issued by Admin/Lessor record a strike with ₱0 fine. Automated late rent triggers contract late fees (Strike 2: ₱500, Strike 3: ₱1,000). Reaching Strike 3 under any combination triggers Eviction Standing and the ₱1,000 eviction fee. Maintaining a clean record for 2 consecutive months (60 days) with on-time payments reduces active strikes by 1.",
        'ref': 'Civil Code Art. 1659 & Art. 1673; Pwestora Platform Enforcement Protocol',
      },
      {
        'key': 'rent_restructuring_policy',
        'label': 'Rent Restructuring Hardship Policy',
        'standard':
            "Tenants facing temporary cash-flow difficulty may request to split monthly rent into 2 or 3 installments subject to lessor approval. Late fees are frozen provided agreed installment deadlines are strictly settled.",
        'ref': 'Civil Code Art. 1231 & Art. 1306 (Freedom of Stipulation & Novation)',
      },
    ];
  }

  late final List<Map<String, dynamic>> _platformRules = _defaultPlatformRules();

  static List<Map<String, dynamic>> _defaultClauses() {
    return [
      {
        'key': 'security_deposit',
        'label': 'Security Deposit (2 Months)',
        'standard': "Equivalent to two (2) months' rent, held securely and refundable within 30 days of lease expiration less valid deductions.",
        'ref': 'Pwestora Commercial Lease Standard §3.1',
      },
      {
        'key': 'rent_escalation',
        'label': 'Annual Rent Escalation (5%)',
        'standard': "Monthly rent may increase by up to 5% upon annual lease renewal consistent with Philippine commercial lease standards.",
        'ref': 'Pwestora Commercial Lease Standard §4.2',
      },
      {
        'key': 'lease_term',
        'label': 'Proposed Lease Duration',
        'standard': "Full commercial occupancy term as requested in the proposal with option to renew upon mutual agreement.",
        'ref': 'Pwestora Commercial Lease Standard §2.1',
      },
      {
        'key': 'use_of_premises',
        'label': 'Permitted Commercial Use',
        'standard': "Premises will be used solely for the registered business concept stated in this lease application.",
        'ref': 'Pwestora Commercial Lease Standard §6.1',
      },
      {
        'key': 'maintenance',
        'label': 'Structural & Day-to-Day Maintenance',
        'standard': "Lessor covers structural, roof, and main pipes. Tenant maintains internal fixtures, daily upkeep, and tenant installations.",
        'ref': 'Pwestora Commercial Lease Standard §7.3',
      },
      {
        'key': 'subleasing',
        'label': 'Subleasing & Space Assignment',
        'standard': "No subleasing or assigning space to a third party without express prior written consent from the lessor.",
        'ref': 'Pwestora Commercial Lease Standard §8.1',
      },
      {
        'key': 'termination_notice',
        'label': 'Early Termination Notice (30 Days)',
        'standard': "At least thirty (30) days advance formal written notice required before early termination or vacating.",
        'ref': 'Pwestora Commercial Lease Standard §11.1',
      },
      {
        'key': 'insurance_compliance',
        'label': 'Insurance & Business Compliance',
        'standard': "Lessee is responsible for insuring merchandise/equipment and maintaining valid municipal business permits and fire clearances.",
        'ref': 'Pwestora Commercial Lease Standard §10.2',
      },
    ];
  }

  Map<String, dynamic> _buildClauseItem(Map<String, dynamic> raw) {
    final key = (raw['key'] ?? '').toString();
    String placeholder;
    if (key == 'security_deposit') {
      placeholder = 'e.g. Requesting 1-month deposit instead of 2 months';
    } else if (key == 'rent_escalation') {
      placeholder = 'e.g. Requesting fixed rent with no escalation for 2 years';
    } else if (key == 'lease_term') {
      placeholder = 'e.g. Requesting 6 months trial period instead of 1 year';
    } else if (key == 'termination_notice') {
      placeholder = 'e.g. Requesting 60 days notice period for both parties';
    } else {
      placeholder = 'e.g. Counter-offer or proposed term change...';
    }

    return {
      'key': key,
      'label': (raw['label'] ?? key).toString(),
      'standard': (raw['standard'] ?? raw['text'] ?? '').toString(),
      'ref': (raw['ref'] ?? '').toString(),
      'agreed': true,
      'isMandatory': false,
      'controller': TextEditingController(),
      'defaultCounterPlaceholder': placeholder,
    };
  }

  void _setClauses(List<dynamic> items, {String? templateName, bool? isCustom}) {
    for (final c in _checklistClauses) {
      final ctrl = c['controller'];
      if (ctrl is TextEditingController) {
        ctrl.dispose();
      }
    }

    final newClauses = <Map<String, dynamic>>[];
    for (final item in items) {
      if (item is Map) {
        final key = (item['key'] ?? '').toString();
        if (_kPlatformRuleKeys.contains(key)) continue;
        newClauses.add(_buildClauseItem(Map<String, dynamic>.from(item)));
      }
    }

    if (newClauses.isEmpty) {
      for (final raw in _defaultClauses()) {
        final key = (raw['key'] ?? '').toString();
        if (_kPlatformRuleKeys.contains(key)) continue;
        newClauses.add(_buildClauseItem(raw));
      }
    }

    _checklistClauses = newClauses;
    if (templateName != null && templateName.isNotEmpty) {
      _templateName = templateName;
    }
    if (isCustom != null) {
      _isCustomTemplate = isCustom;
    }

    if (_savedDraftChecklist != null) {
      _applyChecklistDraft(_savedDraftChecklist!);
    }
  }

  void _applyChecklistDraft(Map<String, dynamic> checklistMap) {
    for (final clause in _checklistClauses) {
      final key = clause['key'] as String;
      if (checklistMap.containsKey(key)) {
        final item = checklistMap[key] as Map<String, dynamic>;
        final isMandatory = clause['isMandatory'] == true;
        if (!isMandatory && item['agreed'] != null) {
          clause['agreed'] = item['agreed'] as bool;
        }
        if (item['note'] != null && (item['note'] as String).isNotEmpty) {
          final ctrl = clause['controller'];
          if (ctrl is TextEditingController) {
            ctrl.text = item['note'] as String;
          }
        }
      }
    }
  }

  Future<void> _fetchContractFromApi() async {
    setState(() => _contractLoading = true);
    try {
      final res = await ApiService.fetchChecklistForProperty(widget.propertyId);
      final rawItems = res['items'] as List<dynamic>?;
      if (rawItems != null && rawItems.isNotEmpty && mounted) {
        setState(() {
          _setClauses(
            rawItems,
            templateName: res['template_name'] as String?,
            isCustom: res['is_custom'] == true,
          );
        });
      }
    } catch (e) {
      debugPrint('Error fetching contract template for property: $e');
    } finally {
      if (mounted) {
        setState(() => _contractLoading = false);
      }
    }
  }

  String get _draftStorageKey => 'apply_draft_prop_${widget.propertyId}';

  @override
  void initState() {
    super.initState();
    _effectiveRent = (widget.askingRent != null && widget.askingRent! > 0)
        ? widget.askingRent!
        : 35000.0;
    _customRentCtrl.text = _effectiveRent.toStringAsFixed(0);

    // Resolve fixed lease duration from listing
    int resolvedTerm = 12;
    if (widget.termMonths != null && widget.termMonths! > 0) {
      resolvedTerm = widget.termMonths!;
    } else if (widget.leaseTerm != null) {
      final str = widget.leaseTerm.toString().trim().toLowerCase();
      final numVal = int.tryParse(str.replaceAll(RegExp(r'[^0-9]'), ''));
      if (numVal != null && numVal > 0) {
        if (str.contains('year')) {
          resolvedTerm = numVal * 12;
        } else if (str.contains('month')) {
          resolvedTerm = numVal;
        } else if (numVal <= 5) {
          resolvedTerm = numVal * 12;
        } else {
          resolvedTerm = numVal;
        }
      }
    }
    _selectedTerm = resolvedTerm;

    // Initialize with passed contract template if available, or fetch from API
    if (widget.contractTemplate != null && widget.contractTemplate!['clauses'] is List) {
      final tpl = widget.contractTemplate!;
      final clauses = tpl['clauses'] as List<dynamic>;
      final name = tpl['name'] as String? ?? 'Contract Checklist';
      final isCustom = tpl['is_custom'] == true;
      _setClauses(clauses, templateName: name, isCustom: isCustom);
    } else {
      _setClauses(_defaultClauses());
      _fetchContractFromApi();
    }

    _loadDraft();
  }

  Future<void> _saveDraft() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final Map<String, dynamic> docMap = {};
      for (final entry in _uploadedDocs.entries) {
        docMap[entry.key] = {
          'filename': entry.value.filename,
          'path': entry.value.path,
          'base64': base64Encode(entry.value.bytes),
        };
      }

      final Map<String, dynamic> checklistMap = {};
      for (final clause in _checklistClauses) {
        final key = clause['key'] as String;
        final agreed = clause['agreed'] as bool;
        final note = (clause['controller'] as TextEditingController).text.trim();
        checklistMap[key] = {
          'agreed': agreed,
          'note': note,
        };
      }

      final draft = {
        'business_name': _businessCtrl.text.trim(),
        'category': _selectedCategory,
        'term': _selectedTerm,
        'effective_rent': _effectiveRent,
        'custom_rate_mode': _customRateMode,
        'target_move_in': _targetMoveInDate?.toIso8601String(),
        'docs': docMap,
        'checklist': checklistMap,
        'saved_at': DateTime.now().toIso8601String(),
      };

      await prefs.setString(_draftStorageKey, jsonEncode(draft));
    } catch (e) {
      debugPrint('Error saving application draft: $e');
    }
  }

  Future<void> _loadDraft() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_draftStorageKey);
      if (raw == null || raw.isEmpty) return;

      final Map<String, dynamic> draft = jsonDecode(raw);
      final docMap = draft['docs'] as Map<String, dynamic>?;

      int restoredDocCount = 0;
      if (docMap != null && docMap.isNotEmpty) {
        for (final entry in docMap.entries) {
          final data = entry.value as Map<String, dynamic>;
          final base64Str = data['base64'] as String?;
          final filename = data['filename'] as String? ?? '${entry.key}.jpg';
          final path = data['path'] as String? ?? '';
          if (base64Str != null && base64Str.isNotEmpty) {
            try {
              final bytes = base64Decode(base64Str);
              _uploadedDocs[entry.key] = _UploadedDoc(
                bytes: bytes,
                filename: filename,
                path: path,
              );
              restoredDocCount++;
            } catch (_) {}
          }
        }
      }

      if (_businessCtrl.text.isEmpty && draft['business_name'] != null && (draft['business_name'] as String).isNotEmpty) {
        _businessCtrl.text = draft['business_name'] as String;
      }
      if (draft['category'] != null) {
        _selectedCategory = draft['category'] as String;
      }
      if (draft['term'] != null && widget.termMonths == null && widget.leaseTerm == null) {
        _selectedTerm = draft['term'] as int;
      }
      if (draft['effective_rent'] != null) {
        _effectiveRent = (draft['effective_rent'] as num).toDouble();
        _customRentCtrl.text = _effectiveRent.toStringAsFixed(0);
      }
      if (draft['custom_rate_mode'] != null) {
        _customRateMode = draft['custom_rate_mode'] as bool;
      }
      if (draft['target_move_in'] != null) {
        final parsedDate = DateTime.tryParse(draft['target_move_in'] as String);
        if (parsedDate != null) _targetMoveInDate = parsedDate;
      }

      final checklistMap = draft['checklist'] as Map<String, dynamic>?;
      if (checklistMap != null) {
        _savedDraftChecklist = checklistMap;
        _applyChecklistDraft(checklistMap);
      }

      if (mounted) {
        setState(() {});
        if (restoredDocCount > 0) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              behavior: SnackBarBehavior.floating,
              backgroundColor: AppColors.primary,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              duration: const Duration(seconds: 4),
              content: Row(
                children: [
                  const Icon(Icons.history_toggle_off_rounded, color: AppColors.cyanGlow, size: 20),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Restored $restoredDocCount uploaded document(s) from your saved draft.',
                      style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600, color: Colors.white),
                    ),
                  ),
                ],
              ),
            ),
          );
        }
      }
    } catch (e) {
      debugPrint('Error loading application draft: $e');
    }
  }

  Future<void> _clearDraft() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_draftStorageKey);
    } catch (e) {
      debugPrint('Error clearing application draft: $e');
    }
  }

  @override
  void dispose() {
    _businessCtrl.dispose();
    _customRentCtrl.dispose();
    for (final c in _checklistClauses) {
      (c['controller'] as TextEditingController).dispose();
    }
    super.dispose();
  }

  void _proceedToDocuments() {
    if (!_formKey.currentState!.validate()) return;
    if (_targetMoveInDate == null) {
      setState(() => _error = 'Please select your target move-in date before proceeding');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Row(
            children: [
              Icon(Icons.calendar_today_rounded, color: Colors.white, size: 18),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Target move-in date is required. Please pick a date.',
                  style: TextStyle(fontWeight: FontWeight.w600),
                ),
              ),
            ],
          ),
          backgroundColor: AppColors.error,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      );
      return;
    }
    if (_customRateMode) {
      final parsed = double.tryParse(_customRentCtrl.text.trim());
      if (parsed == null || parsed <= 0) {
        setState(() => _error = 'Please enter a valid monthly rent amount');
        return;
      }
      _effectiveRent = parsed;
    }
    _saveDraft();
    HapticFeedback.lightImpact();
    setState(() {
      _error = null;
      _currentStep = 1;
    });
  }

  void _proceedToChecklist() {
    _saveDraft();
    HapticFeedback.lightImpact();
    setState(() => _currentStep = 2);
  }

  Future<void> _selectTargetDate() async {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final now = DateTime.now();
    final initial = _targetMoveInDate ?? now.add(const Duration(days: 14));
    final picked = await showDatePicker(
      context: context,
      initialDate: initial.isBefore(now) ? now : initial,
      firstDate: now,
      lastDate: now.add(const Duration(days: 365)),
      builder: (context, child) {
        return Theme(
          data: isDark
              ? ThemeData.dark().copyWith(
                  colorScheme: const ColorScheme.dark(
                    primary: AppColors.electricBlue,
                    onPrimary: Colors.white,
                    surface: AppColors.navyCard,
                    onSurface: Colors.white,
                  ),
                )
              : Theme.of(context).copyWith(
                  colorScheme: const ColorScheme.light(
                    primary: AppColors.primary,
                    onPrimary: Colors.white,
                    onSurface: AppColors.ink900,
                  ),
                ),
          child: child!,
        );
      },
    );
    if (picked != null) {
      setState(() {
        _targetMoveInDate = picked;
        _error = null;
      });
      _saveDraft();
    }
  }

  Future<void> _pickDocument(String key, ImageSource source) async {
    try {
      final picker = ImagePicker();
      final picked = await picker.pickImage(source: source, imageQuality: 85);
      if (picked == null) return;

      final bytes = await picked.readAsBytes();
      setState(() {
        _uploadedDocs[key] = _UploadedDoc(
          bytes: bytes,
          filename: picked.name.isNotEmpty ? picked.name : '$key.jpg',
          path: picked.path,
        );
      });
      await _saveDraft();
      HapticFeedback.selectionClick();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Could not attach image: $e')),
      );
    }
  }

  void _showPickOptions(String key, String title) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    showModalBottomSheet(
      context: context,
      backgroundColor: isDark ? AppColors.navyCard : AppColors.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Attach $title',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                  color: isDark ? Colors.white : AppColors.ink900,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                'Upload a clear photo or scanned image of your document.',
                style: TextStyle(
                  fontSize: 12.5,
                  color: isDark ? AppColors.ink400 : AppColors.ink500,
                ),
              ),
              const SizedBox(height: 18),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: isDark
                        ? const Color(0xFF1E3A8A).withValues(alpha: 0.35)
                        : AppColors.accentSoft,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(Icons.camera_alt_outlined, color: AppColors.electricBlue),
                ),
                title: Text(
                  'Take Photo with Camera',
                  style: TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 14,
                    color: isDark ? Colors.white : AppColors.ink900,
                  ),
                ),
                onTap: () {
                  Navigator.of(ctx).pop();
                  _pickDocument(key, ImageSource.camera);
                },
              ),
              Divider(
                height: 1,
                color: isDark ? AppColors.navyBorder : AppColors.border,
              ),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: isDark
                        ? const Color(0xFF1E3A8A).withValues(alpha: 0.35)
                        : AppColors.accentSoft,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(Icons.photo_library_outlined, color: AppColors.electricBlue),
                ),
                title: Text(
                  'Choose from Photo Gallery',
                  style: TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 14,
                    color: isDark ? Colors.white : AppColors.ink900,
                  ),
                ),
                onTap: () {
                  Navigator.of(ctx).pop();
                  _pickDocument(key, ImageSource.gallery);
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _formatMoney(num val) {
    final str = val.toStringAsFixed(0);
    final buffer = StringBuffer();
    for (int i = 0; i < str.length; i++) {
      if (i > 0 && (str.length - i) % 3 == 0) buffer.write(',');
      buffer.write(str[i]);
    }
    return buffer.toString();
  }

  Future<bool?> _showProposalConfirmationSheet() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final fullBusinessName = _businessCtrl.text.trim();
    final moveInStr = _targetMoveInDate != null
        ? '${_targetMoveInDate!.month}/${_targetMoveInDate!.day}/${_targetMoveInDate!.year}'
        : 'Not specified';
    final agreedCount = _checklistClauses.where((c) => c['agreed'] == true).length;
    final counterCount = _checklistClauses.where((c) => c['agreed'] == false).length;

    return showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return Container(
          constraints: BoxConstraints(
            maxHeight: MediaQuery.of(ctx).size.height * 0.88,
          ),
          decoration: BoxDecoration(
            color: isDark ? AppColors.navyCard : Colors.white,
            borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  margin: const EdgeInsets.only(top: 12, bottom: 8),
                  decoration: BoxDecoration(
                    color: isDark ? Colors.white24 : Colors.black12,
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
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(8),
                            decoration: BoxDecoration(
                              color: isDark
                                  ? const Color(0xFF1E3A8A).withValues(alpha: 0.35)
                                  : const Color(0xFFEFF6FF),
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: const Icon(
                              Icons.assignment_turned_in_rounded,
                              size: 22,
                              color: AppColors.electricBlue,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Confirm Commercial Proposal',
                                  style: TextStyle(
                                    fontSize: 16,
                                    fontWeight: FontWeight.w800,
                                    color: isDark ? Colors.white : AppColors.ink900,
                                    letterSpacing: -0.2,
                                  ),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  'Is everything correct before submitting to the lessor?',
                                  style: TextStyle(
                                    fontSize: 12,
                                    color: isDark ? AppColors.ink400 : AppColors.ink500,
                                  ),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 18),

                      // Proposal Summary Card
                      Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: isDark ? AppColors.navySurface : const Color(0xFFF8FAFC),
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(
                            color: isDark ? AppColors.navyBorder : const Color(0xFFE2E8F0),
                          ),
                        ),
                        child: Column(
                          children: [
                            _buildConfirmationRow('Commercial Stall', widget.propertyName, isDark: isDark),
                            const Divider(height: 16),
                            _buildConfirmationRow(
                              'Business Name',
                              fullBusinessName.isNotEmpty ? fullBusinessName : 'Commercial Tenant',
                              isDark: isDark,
                            ),
                            const Divider(height: 16),
                            _buildConfirmationRow('Category', _selectedCategory, isDark: isDark),
                            const Divider(height: 16),
                            _buildConfirmationRow('Lease Term', '$_selectedTerm Months', isDark: isDark),
                            const Divider(height: 16),
                            _buildConfirmationRow(
                              'Proposed Rent',
                              '₱${_formatMoney(_effectiveRent)} / month',
                              isDark: isDark,
                              isHighlight: true,
                            ),
                            const Divider(height: 16),
                            _buildConfirmationRow('Target Move-In', moveInStr, isDark: isDark),
                            const Divider(height: 16),
                            _buildConfirmationRow(
                              'Attached Docs',
                              '${_uploadedDocs.length} verification file(s)',
                              isDark: isDark,
                            ),
                            const Divider(height: 16),
                            _buildConfirmationRow(
                              'Contract Checklist',
                              '$agreedCount Agreed • $counterCount Countered',
                              isDark: isDark,
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 14),

                      // Audit Notice Box
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: isDark ? const Color(0xFF1E293B) : const Color(0xFFEFF6FF),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(
                            color: isDark ? const Color(0xFF334155) : const Color(0xFFDBEAFE),
                          ),
                        ),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Icon(Icons.info_outline_rounded, size: 16, color: AppColors.electricBlue),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                'Once submitted, your commercial proposal and uploaded documents are formally logged and dispatched to property management for leasing evaluation.',
                                style: TextStyle(
                                  fontSize: 11.5,
                                  color: isDark ? const Color(0xFF93C5FD) : const Color(0xFF1E40AF),
                                  height: 1.35,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 20),

                      // Actions
                      SizedBox(
                        width: double.infinity,
                        height: 48,
                        child: ElevatedButton(
                          onPressed: () => Navigator.of(ctx).pop(true),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: isDark ? AppColors.electricBlue : AppColors.primary,
                            foregroundColor: Colors.white,
                            elevation: 0,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                          ),
                          child: const Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(Icons.check_circle_rounded, size: 18),
                              SizedBox(width: 8),
                              Flexible(
                                child: Text(
                                  'Confirm & Submit Proposal',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w800),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 10),
                      SizedBox(
                        width: double.infinity,
                        height: 44,
                        child: OutlinedButton(
                          onPressed: () => Navigator.of(ctx).pop(false),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: isDark ? Colors.white70 : AppColors.ink700,
                            side: BorderSide(
                              color: isDark ? AppColors.navyBorder : const Color(0xFFCBD5E1),
                            ),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                          ),
                          child: const Text('Go Back & Edit', style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700)),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildConfirmationRow(String label, String value, {bool isDark = false, bool isHighlight = false}) {
    return Row(
      children: [
        Text(
          label,
          style: TextStyle(
            fontSize: 12,
            color: isDark ? AppColors.ink400 : AppColors.ink500,
            fontWeight: FontWeight.w500,
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: TextStyle(
              fontSize: 12.5,
              fontWeight: isHighlight ? FontWeight.w900 : FontWeight.w700,
              color: isHighlight
                  ? AppColors.electricBlue
                  : (isDark ? Colors.white : AppColors.ink900),
            ),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ],
    );
  }

  Future<void> _submit() async {
    final confirmed = await _showProposalConfirmationSheet();
    if (confirmed != true) return;

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      // Build structured checklist negotiation payload
      final Map<String, dynamic> negotiationPayload = {};

      // 1. Include built-in platform rules (agreed)
      for (final rule in _platformRules) {
        final key = (rule['key'] ?? '').toString();
        if (key.isNotEmpty) {
          negotiationPayload[key] = {
            'agreed': true,
            'rebuttal_note': null,
            'is_platform_rule': true,
          };
        }
      }

      // 2. Include building commercial clauses with tenant counter-offers
      for (final clause in _checklistClauses) {
        final key = clause['key'] as String;
        final agreed = clause['agreed'] as bool;
        final note = (clause['controller'] as TextEditingController).text.trim();
        negotiationPayload[key] = {
          'agreed': agreed,
          'rebuttal_note': agreed ? null : note,
        };
      }

      // Append metadata to negotiation payload for lessor context
      negotiationPayload['_proposal_meta'] = {
        'category': _selectedCategory,
        'target_move_in': (_targetMoveInDate ?? DateTime.now().add(const Duration(days: 14)))
            .toIso8601String()
            .split('T')
            .first,
      };

      final Map<String, Map<String, dynamic>> docPayload = {};
      for (final entry in _uploadedDocs.entries) {
        docPayload[entry.key] = {
          'bytes': entry.value.bytes,
          'filename': entry.value.filename,
        };
      }

      final fullBusinessName = _businessCtrl.text.trim();

      await ApiService.submitApplication(
        propertyId: widget.propertyId,
        businessName: fullBusinessName,
        termMonths: _selectedTerm,
        rent: _effectiveRent,
        checklistNegotiation: negotiationPayload,
        documents: docPayload.isNotEmpty ? docPayload : null,
      );

      if (!mounted) return;
      _submittedNegotiationPayload = negotiationPayload;
      await _clearDraft();
      HapticFeedback.mediumImpact();
      setState(() => _submitted = true);
    } catch (e) {
      setState(() => _error = ApiService.errorMessage(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    if (_submitted) {
      return _buildSuccessScreen(isDark);
    }

    String appBarTitle = 'Commercial Proposal';
    if (_currentStep == 1) appBarTitle = 'Verification Documents';
    if (_currentStep == 2) appBarTitle = 'Negotiate Contract Terms';

    return Scaffold(
      backgroundColor: isDark ? AppColors.midnightNavy : AppColors.bg,
      appBar: AppBar(
        title: Text(
          appBarTitle,
          style: TextStyle(
            color: isDark ? Colors.white : AppColors.ink900,
            fontWeight: FontWeight.w800,
          ),
        ),
        backgroundColor: isDark ? AppColors.navySurface : AppColors.surface,
        elevation: 0,
        leading: AppBackButton(
          onPressed: () {
            if (_currentStep == 2) {
              setState(() => _currentStep = 1);
            } else if (_currentStep == 1) {
              setState(() => _currentStep = 0);
            } else {
              Navigator.of(context).pop();
            }
          },
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          physics: const BouncingScrollPhysics(),
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Step Timeline Header (1 ─── 2 ─── 3)
              _buildTimelineStepper(isDark),
              const SizedBox(height: 20),

              if (_currentStep == 0)
                _buildStepOneForm(isDark)
              else if (_currentStep == 1)
                _buildStepTwoDocuments(isDark)
              else
                _buildStepThreeChecklist(isDark),
            ],
          ),
        ),
      ),
    );
  }

  // Connected Timeline Stepper (1 ─── 2 ─── 3)
  Widget _buildTimelineStepper(bool isDark) {
    final steps = [
      {'num': 1, 'label': 'Proposal'},
      {'num': 2, 'label': 'Documents'},
      {'num': 3, 'label': 'Checklist'},
    ];

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      decoration: BoxDecoration(
        color: isDark ? AppColors.navyCard : AppColors.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: isDark ? AppColors.navyBorder : AppColors.border),
        boxShadow: [
          BoxShadow(
            color: isDark ? Colors.black.withValues(alpha: 0.3) : AppColors.primary.withValues(alpha: 0.03),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Row(
        children: List.generate(steps.length * 2 - 1, (index) {
          if (index.isOdd) {
            // Connecting branch line
            final stepIdx = index ~/ 2;
            final isLineActive = _currentStep > stepIdx;
            return Expanded(
              child: Container(
                height: 3,
                margin: const EdgeInsets.symmetric(horizontal: 8),
                decoration: BoxDecoration(
                  color: isLineActive
                      ? AppColors.electricBlue
                      : (isDark ? AppColors.navyBorder : AppColors.border),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            );
          }

          // Step node
          final stepIdx = index ~/ 2;
          final stepData = steps[stepIdx];
          final isActive = _currentStep == stepIdx;
          final isDone = _currentStep > stepIdx;

          return GestureDetector(
            onTap: isDone ? () => setState(() => _currentStep = stepIdx) : null,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 34,
                  height: 34,
                  decoration: BoxDecoration(
                    color: isDone
                        ? AppColors.electricBlue
                        : isActive
                            ? (isDark ? AppColors.electricBlue : AppColors.primary)
                            : (isDark ? AppColors.navySurface : AppColors.borderLight),
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: isActive ? (isDark ? Colors.white : AppColors.electricBlue) : Colors.transparent,
                      width: 2,
                    ),
                    boxShadow: (isActive || isDone)
                        ? [
                            BoxShadow(
                              color: AppColors.electricBlue.withValues(alpha: 0.25),
                              blurRadius: 8,
                              offset: const Offset(0, 2),
                            ),
                          ]
                        : null,
                  ),
                  child: Center(
                    child: isDone
                        ? const Icon(Icons.check_rounded, color: Colors.white, size: 18)
                        : Text(
                            '${stepData['num']}',
                            style: TextStyle(
                              color: isActive ? Colors.white : (isDark ? AppColors.ink300 : AppColors.ink500),
                              fontWeight: FontWeight.w900,
                              fontSize: 13,
                            ),
                          ),
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  stepData['label'] as String,
                  style: TextStyle(
                    fontSize: 11.5,
                    fontWeight: (isActive || isDone) ? FontWeight.w800 : FontWeight.w600,
                    color: (isActive || isDone)
                        ? (isDark ? Colors.white : AppColors.ink900)
                        : (isDark ? AppColors.ink400 : AppColors.ink400),
                  ),
                ),
              ],
            ),
          );
        }),
      ),
    );
  }

  Widget _buildStepOneForm(bool isDark) {
    final formattedDate = _targetMoveInDate != null
        ? '${_targetMoveInDate!.month}/${_targetMoveInDate!.day}/${_targetMoveInDate!.year}'
        : 'Select planned move-in date (Required)';

    return Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // 1. Property & Fixed Rate Hero Card
          Container(
            padding: const EdgeInsets.all(18),
            decoration: AppDecorations.card(
              color: isDark ? AppColors.navyCard : AppColors.surface,
              border: Border.all(color: isDark ? AppColors.navyBorder : AppColors.border.withValues(alpha: 0.85)),
              radius: 20,
              shadow: true,
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
                        gradient: const LinearGradient(
                          colors: [AppColors.primary, AppColors.primaryLight],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        borderRadius: BorderRadius.circular(13),
                      ),
                      child: const Icon(Icons.apartment_rounded, color: Colors.white, size: 22),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'APPLYING FOR SPACE',
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.w800,
                              color: AppColors.electricBlue,
                              letterSpacing: 0.6,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            widget.propertyName,
                            style: TextStyle(
                              fontSize: 15.5,
                              fontWeight: FontWeight.w800,
                              color: isDark ? Colors.white : AppColors.ink900,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),

                // Base Monthly Rent Card (Fixed Rate with counter-offer toggle)
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: isDark ? AppColors.navySurface : AppColors.bg,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: isDark ? AppColors.navyBorder : AppColors.border),
                  ),
                  child: Column(
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Monthly Base Rent',
                                style: TextStyle(
                                  fontSize: 11.5,
                                  color: isDark ? AppColors.ink300 : AppColors.ink500,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                '₱${_effectiveRent.toStringAsFixed(0)} / mo',
                                style: TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w900,
                                  color: isDark ? Colors.white : AppColors.ink900,
                                  letterSpacing: -0.3,
                                ),
                              ),
                            ],
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                            decoration: BoxDecoration(
                              color: isDark ? const Color(0xFF1E3A8A).withValues(alpha: 0.4) : AppColors.accentSoft,
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const Icon(Icons.verified_rounded, size: 13, color: AppColors.electricBlue),
                                const SizedBox(width: 4),
                                Text(
                                  'Fixed Listed Rate',
                                  style: TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w800,
                                    color: isDark ? const Color(0xFF60A5FA) : AppColors.electricBlue,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                      if (_customRateMode) ...[
                        const SizedBox(height: 12),
                        Divider(height: 1, color: isDark ? AppColors.navyBorder : AppColors.border),
                        const SizedBox(height: 10),
                        TextFormField(
                          controller: _customRentCtrl,
                          keyboardType: const TextInputType.numberWithOptions(decimal: true),
                          style: TextStyle(color: isDark ? Colors.white : AppColors.ink900),
                          decoration: InputDecoration(
                            isDense: true,
                            labelText: 'Propose Counter-Offer Rate (₱ PHP)',
                            labelStyle: TextStyle(color: isDark ? AppColors.ink300 : AppColors.ink500),
                            prefixText: '₱ ',
                            prefixStyle: TextStyle(color: isDark ? Colors.white : AppColors.ink900),
                            suffixIcon: IconButton(
                              icon: const Icon(Icons.check_circle, color: AppColors.electricBlue, size: 20),
                              onPressed: () {
                                final parsed = double.tryParse(_customRentCtrl.text.trim());
                                if (parsed != null && parsed > 0) {
                                  setState(() {
                                    _effectiveRent = parsed;
                                    _customRateMode = false;
                                  });
                                }
                              },
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: 8),
                Align(
                  alignment: Alignment.centerRight,
                  child: GestureDetector(
                    onTap: () {
                      setState(() => _customRateMode = !_customRateMode);
                    },
                    child: Text(
                      _customRateMode ? 'Cancel custom rate' : 'Propose counter-offer rate / discount',
                      style: TextStyle(
                        fontSize: 11.5,
                        fontWeight: FontWeight.w700,
                        color: isDark ? const Color(0xFF60A5FA) : AppColors.electricBlue,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),

          if (_error != null) ...[
            _buildErrorBox(_error!),
            const SizedBox(height: 16),
          ],

          // 2. Business Concept & Category Card
          Container(
            padding: const EdgeInsets.all(20),
            decoration: AppDecorations.card(
              color: isDark ? AppColors.navyCard : AppColors.surface,
              border: Border.all(color: isDark ? AppColors.navyBorder : AppColors.border.withValues(alpha: 0.85)),
              radius: 20,
              shadow: true,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Business Concept Category',
                  style: TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w800,
                    color: isDark ? Colors.white : AppColors.ink900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Helps the lessor prevent tenant conflict and verify stall suitability.',
                  style: TextStyle(
                    fontSize: 11.5,
                    color: isDark ? AppColors.ink400 : AppColors.ink500,
                  ),
                ),
                const SizedBox(height: 12),

                // Category Chips
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: _businessCategories.map((cat) {
                    final label = cat['label'] as String;
                    final icon = cat['icon'] as IconData;
                    final isSelected = _selectedCategory == label;

                    return ChoiceChip(
                      label: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            icon,
                            size: 15,
                            color: isSelected
                                ? (isDark ? const Color(0xFF60A5FA) : AppColors.electricBlue)
                                : (isDark ? AppColors.ink400 : AppColors.ink500),
                          ),
                          const SizedBox(width: 6),
                          Text(label),
                        ],
                      ),
                      selected: isSelected,
                      onSelected: (selected) {
                        if (selected) setState(() => _selectedCategory = label);
                      },
                      selectedColor: isDark ? const Color(0xFF1E3A8A).withValues(alpha: 0.5) : AppColors.accentSoft,
                      backgroundColor: isDark ? AppColors.navySurface : AppColors.bg,
                      labelStyle: TextStyle(
                        fontSize: 12,
                        fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                        color: isSelected
                            ? (isDark ? const Color(0xFF60A5FA) : AppColors.electricBlue)
                            : (isDark ? Colors.white70 : AppColors.ink700),
                      ),
                      side: BorderSide(
                        color: isSelected
                            ? AppColors.electricBlue
                            : (isDark ? AppColors.navyBorder : AppColors.border),
                        width: isSelected ? 1.4 : 1,
                      ),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      showCheckmark: false,
                    );
                  }).toList(),
                ),
                const SizedBox(height: 20),

                Text(
                  'Brand / Store Name',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: isDark ? Colors.white : AppColors.ink900,
                  ),
                ),
                const SizedBox(height: 6),
                TextFormField(
                  controller: _businessCtrl,
                  textCapitalization: TextCapitalization.words,
                  style: TextStyle(color: isDark ? Colors.white : AppColors.ink900),
                  decoration: InputDecoration(
                    hintText: 'e.g. Daily Grind Cafe & Bakes',
                    hintStyle: TextStyle(color: isDark ? AppColors.ink400 : AppColors.ink500),
                    prefixIcon: Icon(
                      Icons.storefront_outlined,
                      color: isDark ? AppColors.ink300 : AppColors.ink500,
                      size: 20,
                    ),
                    filled: isDark,
                    fillColor: isDark ? AppColors.navySurface : null,
                  ),
                  validator: (v) => (v == null || v.isEmpty) ? 'Please enter your brand or business name' : null,
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),

          // 3. Lease Duration & Target Move-In Date Card
          Container(
            padding: const EdgeInsets.all(20),
            decoration: AppDecorations.card(
              color: isDark ? AppColors.navyCard : AppColors.surface,
              border: Border.all(color: isDark ? AppColors.navyBorder : AppColors.border.withValues(alpha: 0.85)),
              radius: 20,
              shadow: true,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Lease Duration',
                  style: TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w800,
                    color: isDark ? Colors.white : AppColors.ink900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Commercial occupancy commitment established in property listing.',
                  style: TextStyle(
                    fontSize: 11.5,
                    color: isDark ? AppColors.ink400 : AppColors.ink500,
                  ),
                ),
                const SizedBox(height: 12),

                // Fixed Lease Duration Display
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  decoration: BoxDecoration(
                    color: isDark ? AppColors.navySurface : const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(
                      color: isDark ? AppColors.navyBorder : const Color(0xFFE2E8F0),
                    ),
                  ),
                  child: Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: isDark
                              ? const Color(0xFF1E3A8A).withValues(alpha: 0.3)
                              : const Color(0xFFEFF6FF),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: const Icon(
                          Icons.date_range_rounded,
                          size: 20,
                          color: AppColors.electricBlue,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _formattedLeaseDuration,
                              style: TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w800,
                                color: isDark ? Colors.white : AppColors.ink900,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              'Standard term specified by lessor',
                              style: TextStyle(
                                fontSize: 11.5,
                                color: isDark ? AppColors.ink400 : AppColors.ink500,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                        decoration: BoxDecoration(
                          color: isDark
                              ? const Color(0xFF064E3B).withValues(alpha: 0.3)
                              : const Color(0xFFECFDF5),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(
                            color: const Color(0xFF10B981).withValues(alpha: 0.3),
                          ),
                        ),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.lock_outline_rounded, size: 12, color: Color(0xFF10B981)),
                            SizedBox(width: 4),
                            Text(
                              'Fixed by Listing',
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w700,
                                color: Color(0xFF10B981),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),

                // Target Move-in Date Tile
                Row(
                  children: [
                    Text(
                      'Target Move-In / Turnover Date',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: isDark ? Colors.white : AppColors.ink900,
                      ),
                    ),
                    const SizedBox(width: 4),
                    const Text(
                      '*',
                      style: TextStyle(
                        color: AppColors.error,
                        fontWeight: FontWeight.w900,
                        fontSize: 15,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                InkWell(
                  onTap: _selectTargetDate,
                  borderRadius: BorderRadius.circular(12),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                    decoration: BoxDecoration(
                      color: isDark ? AppColors.navySurface : AppColors.bg,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: _targetMoveInDate == null && _error != null
                            ? AppColors.error
                            : (isDark ? AppColors.navyBorder : AppColors.border),
                        width: _targetMoveInDate == null && _error != null ? 1.5 : 1,
                      ),
                    ),
                    child: Row(
                      children: [
                        Icon(
                          Icons.calendar_today_rounded,
                          size: 18,
                          color: _targetMoveInDate == null
                              ? (_error != null ? AppColors.error : AppColors.ink400)
                              : (isDark ? const Color(0xFF60A5FA) : AppColors.primary),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Planned Occupancy',
                                style: TextStyle(
                                  fontSize: 10.5,
                                  color: isDark ? AppColors.ink400 : AppColors.ink500,
                                ),
                              ),
                              Text(
                                formattedDate,
                                style: TextStyle(
                                  fontSize: 13.5,
                                  fontWeight: _targetMoveInDate != null ? FontWeight.w800 : FontWeight.w600,
                                  color: _targetMoveInDate != null
                                      ? (isDark ? Colors.white : AppColors.ink900)
                                      : (_error != null ? AppColors.error : AppColors.ink400),
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ],
                          ),
                        ),
                        Icon(
                          Icons.arrow_forward_ios_rounded,
                          size: 14,
                          color: isDark ? AppColors.ink400 : AppColors.ink400,
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),

          // Continue Button
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: _proceedToDocuments,
              style: ElevatedButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 16),
                backgroundColor: isDark ? AppColors.electricBlue : AppColors.primary,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
              child: const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text('Continue to Verification Docs', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
                  SizedBox(width: 8),
                  Icon(Icons.arrow_forward_rounded, size: 18),
                ],
              ),
            ),
          ),
          const SizedBox(height: 20),
        ],
      ),
    );
  }

  Widget _buildStepTwoDocuments(bool isDark) {
    final attachedCount = _uploadedDocs.length;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Clean Info Banner
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: isDark ? AppColors.navyCard : AppColors.surface,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: isDark ? AppColors.navyBorder : AppColors.border),
            boxShadow: [
              BoxShadow(
                color: isDark ? Colors.black.withValues(alpha: 0.25) : AppColors.primary.withValues(alpha: 0.03),
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
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: isDark ? const Color(0xFF1E3A8A).withValues(alpha: 0.4) : AppColors.accentSoft,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Icon(Icons.shield_outlined, size: 20, color: AppColors.electricBlue),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Lessor Screening & Verification',
                          style: TextStyle(
                            fontSize: 13.5,
                            fontWeight: FontWeight.w800,
                            color: isDark ? Colors.white : AppColors.ink900,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'Attaching verification documents speeds up review.',
                          style: TextStyle(
                            fontSize: 11.5,
                            color: isDark ? AppColors.ink400 : AppColors.ink500,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: attachedCount > 0
                          ? (isDark ? const Color(0xFF1E3A8A).withValues(alpha: 0.4) : AppColors.accentSoft)
                          : (isDark ? AppColors.navySurface : AppColors.borderLight),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          attachedCount > 0 ? Icons.check_circle : Icons.attach_file_rounded,
                          size: 13,
                          color: attachedCount > 0
                              ? (isDark ? const Color(0xFF60A5FA) : AppColors.electricBlue)
                              : (isDark ? AppColors.ink400 : AppColors.ink500),
                        ),
                        const SizedBox(width: 5),
                        Text(
                          '$attachedCount of ${_docFields.length} Attached',
                          style: TextStyle(
                            fontSize: 11.5,
                            fontWeight: FontWeight.w700,
                            color: attachedCount > 0
                                ? (isDark ? const Color(0xFF60A5FA) : AppColors.electricBlue)
                                : (isDark ? Colors.white70 : AppColors.ink700),
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (attachedCount > 0) ...[
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(
                        color: isDark ? AppColors.navySurface : AppColors.borderLight,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            Icons.cloud_done_outlined,
                            size: 12,
                            color: isDark ? AppColors.ink400 : AppColors.ink500,
                          ),
                          const SizedBox(width: 4),
                          Text(
                            'Draft auto-saved',
                            style: TextStyle(
                              fontSize: 10.5,
                              fontWeight: FontWeight.w600,
                              color: isDark ? AppColors.ink400 : AppColors.ink500,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),

        if (_error != null) ...[
          _buildErrorBox(_error!),
          const SizedBox(height: 18),
        ],

        // Document Cards
        ..._docFields.map((field) {
          final key = field['key'] as String;
          final title = field['title'] as String;
          final subtitle = field['subtitle'] as String;
          final icon = field['icon'] as IconData;
          final doc = _uploadedDocs[key];
          final isUploaded = doc != null;

          return Container(
            margin: const EdgeInsets.only(bottom: 14),
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: isDark ? AppColors.navyCard : AppColors.surface,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(
                color: isUploaded
                    ? AppColors.electricBlue.withValues(alpha: 0.6)
                    : (isDark ? AppColors.navyBorder : AppColors.border),
                width: isUploaded ? 1.5 : 1,
              ),
              boxShadow: [
                BoxShadow(
                  color: isUploaded
                      ? AppColors.electricBlue.withValues(alpha: 0.08)
                      : (isDark ? Colors.black.withValues(alpha: 0.2) : AppColors.primary.withValues(alpha: 0.03)),
                  blurRadius: 10,
                  offset: const Offset(0, 3),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: isUploaded
                            ? (isDark ? const Color(0xFF1E3A8A).withValues(alpha: 0.4) : AppColors.accentSoft)
                            : (isDark ? AppColors.navySurface : AppColors.borderLight),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Icon(
                        isUploaded ? Icons.check_circle_rounded : icon,
                        color: isUploaded ? AppColors.electricBlue : (isDark ? const Color(0xFF60A5FA) : AppColors.primary),
                        size: 22,
                      ),
                    ),
                    const SizedBox(width: 12),
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
                                    color: isDark ? Colors.white : AppColors.ink900,
                                  ),
                                ),
                              ),
                              if (isUploaded)
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2.5),
                                  decoration: BoxDecoration(
                                    color: isDark ? const Color(0xFF1E3A8A).withValues(alpha: 0.4) : AppColors.accentSoft,
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      const Icon(Icons.check_rounded, color: AppColors.electricBlue, size: 12),
                                      const SizedBox(width: 3),
                                      Text(
                                        'Attached',
                                        style: TextStyle(
                                          fontSize: 10.5,
                                          fontWeight: FontWeight.w800,
                                          color: isDark ? const Color(0xFF60A5FA) : AppColors.electricBlue,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                            ],
                          ),
                          const SizedBox(height: 3),
                          Text(
                            subtitle,
                            style: TextStyle(
                              fontSize: 11.5,
                              color: isDark ? AppColors.ink400 : AppColors.ink500,
                              height: 1.35,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),

                if (isUploaded) ...[
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: isDark ? AppColors.navySurface : AppColors.bg,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: isDark ? AppColors.navyBorder : AppColors.border),
                    ),
                    child: Row(
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(8),
                          child: Image.memory(
                            doc.bytes,
                            width: 42,
                            height: 42,
                            fit: BoxFit.cover,
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                doc.filename,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w700,
                                  color: isDark ? Colors.white : AppColors.ink900,
                                ),
                              ),
                              const SizedBox(height: 2),
                              const Row(
                                children: [
                                  Icon(Icons.check_circle_rounded, color: AppColors.electricBlue, size: 12),
                                  SizedBox(width: 4),
                                  Text(
                                    'Ready to submit',
                                    style: TextStyle(fontSize: 10.5, color: AppColors.electricBlue, fontWeight: FontWeight.w700),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                        TextButton(
                          onPressed: () => _showPickOptions(key, title),
                          style: TextButton.styleFrom(
                            padding: const EdgeInsets.symmetric(horizontal: 8),
                            visualDensity: VisualDensity.compact,
                          ),
                          child: Text(
                            'Replace',
                            style: TextStyle(
                              fontSize: 11.5,
                              fontWeight: FontWeight.w700,
                              color: isDark ? const Color(0xFF60A5FA) : AppColors.electricBlue,
                            ),
                          ),
                        ),
                        IconButton(
                          icon: Icon(
                            Icons.close_rounded,
                            color: isDark ? AppColors.ink300 : AppColors.ink500,
                            size: 18,
                          ),
                          tooltip: 'Remove',
                          visualDensity: VisualDensity.compact,
                          onPressed: () async {
                            setState(() => _uploadedDocs.remove(key));
                            await _saveDraft();
                            HapticFeedback.lightImpact();
                          },
                        ),
                      ],
                    ),
                  ),
                ] else ...[
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      onPressed: () => _showPickOptions(key, title),
                      icon: Icon(
                        Icons.cloud_upload_outlined,
                        size: 18,
                        color: isDark ? const Color(0xFF60A5FA) : AppColors.primary,
                      ),
                      label: Text(
                        'Attach $title',
                        style: TextStyle(
                          fontSize: 12.5,
                          fontWeight: FontWeight.w700,
                          color: isDark ? Colors.white : AppColors.ink900,
                        ),
                      ),
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        backgroundColor: isDark ? AppColors.navySurface : AppColors.bg,
                        side: BorderSide(color: isDark ? AppColors.navyBorder : AppColors.border),
                        foregroundColor: isDark ? Colors.white : AppColors.primary,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      ),
                    ),
                  ),
                ],
              ],
            ),
          );
        }),

        const SizedBox(height: 12),

        SizedBox(
          width: double.infinity,
          child: ElevatedButton(
            onPressed: _proceedToChecklist,
            style: ElevatedButton.styleFrom(
              padding: const EdgeInsets.symmetric(vertical: 16),
              backgroundColor: isDark ? AppColors.electricBlue : AppColors.primary,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            ),
            child: const Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text('Continue to Contract Checklist', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
                SizedBox(width: 8),
                Icon(Icons.arrow_forward_rounded, size: 18),
              ],
            ),
          ),
        ),
        const SizedBox(height: 10),
        const Center(
          child: Text(
            'You can proceed and attach missing documents later if needed.',
            style: TextStyle(fontSize: 11.5, color: AppColors.ink400),
          ),
        ),
        const SizedBox(height: 20),
      ],
    );
  }

  Widget _buildStepThreeChecklist(bool isDark) {
    final agreedCount = _checklistClauses.where((c) => c['agreed'] == true).length;
    final rebuttalCount = _checklistClauses.length - agreedCount;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Interactive Info Banner
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: isDark ? AppColors.navyCard : AppColors.surface,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: isDark ? AppColors.navyBorder : AppColors.border),
            boxShadow: [
              BoxShadow(
                color: isDark ? Colors.black.withValues(alpha: 0.25) : AppColors.primary.withValues(alpha: 0.03),
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
                    padding: const EdgeInsets.all(6),
                    decoration: BoxDecoration(
                      color: isDark ? const Color(0xFF1E3A8A).withValues(alpha: 0.4) : AppColors.accentSoft,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Icon(Icons.handshake_outlined, size: 18, color: isDark ? const Color(0xFF60A5FA) : AppColors.primary),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Interactive Contract Negotiation',
                          style: TextStyle(
                            fontSize: 13.5,
                            fontWeight: FontWeight.w800,
                            color: isDark ? Colors.white : AppColors.ink900,
                          ),
                        ),
                        if (_templateName.isNotEmpty) ...[
                          const SizedBox(height: 2),
                          Row(
                            children: [
                              Flexible(
                                child: Text(
                                  _templateName,
                                  style: TextStyle(
                                    fontSize: 11.5,
                                    fontWeight: FontWeight.w700,
                                    color: isDark ? const Color(0xFF93C5FD) : AppColors.primary,
                                  ),
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                              if (_isCustomTemplate) ...[
                                const SizedBox(width: 6),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                                  decoration: BoxDecoration(
                                    color: isDark ? const Color(0xFF10B981).withValues(alpha: 0.2) : const Color(0xFFD1FAE5),
                                    borderRadius: BorderRadius.circular(4),
                                  ),
                                  child: Text(
                                    'BUILDING CONTRACT',
                                    style: TextStyle(
                                      fontSize: 9,
                                      fontWeight: FontWeight.w800,
                                      color: isDark ? const Color(0xFF34D399) : const Color(0xFF065F46),
                                      letterSpacing: 0.3,
                                    ),
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ],
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                'Check each clause to accept proposed lease terms. If you disagree with any item, uncheck it and input your counter-offer note directly.',
                style: TextStyle(
                  fontSize: 12,
                  color: isDark ? AppColors.ink400 : AppColors.ink500,
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                    decoration: BoxDecoration(
                      color: isDark ? const Color(0xFF1E3A8A).withValues(alpha: 0.4) : AppColors.accentSoft,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      '$agreedCount Agreed',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                        color: isDark ? const Color(0xFF60A5FA) : AppColors.electricBlue,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  if (rebuttalCount > 0)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                      decoration: BoxDecoration(
                        color: isDark ? AppColors.electricBlue : AppColors.primary,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        '$rebuttalCount Counter-Offer(s)',
                        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: Colors.white),
                      ),
                    ),
                  if (_contractLoading) ...[
                    const Spacer(),
                    const SizedBox(
                      width: 14,
                      height: 14,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    ),
                  ],
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 20),

        if (_error != null) ...[
          _buildErrorBox(_error!),
          const SizedBox(height: 18),
        ],

        // --- SECTION 1: BUILT-IN RULES (AT THE TOP, UNCHECKABLE) ---
        Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: Text(
            'Built-in Rules (Platform Standard)',
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: isDark ? AppColors.ink400 : AppColors.ink500,
            ),
          ),
        ),

        ..._platformRules.map((rule) {
          final label = rule['label'] as String? ?? 'Built-in Rule';
          final standard = rule['standard'] as String? ?? '';
          final ref = rule['ref'] as String? ?? '';

          return Container(
            margin: const EdgeInsets.only(bottom: 14),
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: isDark ? AppColors.navyCard : AppColors.surface,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(
                color: isDark ? AppColors.navyBorder : AppColors.border,
                width: 1,
              ),
              boxShadow: [
                BoxShadow(
                  color: isDark ? Colors.black.withValues(alpha: 0.2) : AppColors.primary.withValues(alpha: 0.03),
                  blurRadius: 10,
                  offset: const Offset(0, 3),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    InkWell(
                      borderRadius: BorderRadius.circular(8),
                      onTap: () {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(
                            content: Text('Built-in platform standard rule (non-negotiable).'),
                            duration: Duration(seconds: 1),
                          ),
                        );
                      },
                      child: Container(
                        width: 26,
                        height: 26,
                        decoration: BoxDecoration(
                          color: isDark ? AppColors.electricBlue : AppColors.primary,
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(
                            color: isDark ? AppColors.electricBlue : AppColors.primary,
                            width: 1.8,
                          ),
                        ),
                        child: const Icon(Icons.check, color: Colors.white, size: 17),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  label,
                                  style: TextStyle(
                                    fontSize: 13.5,
                                    fontWeight: FontWeight.w800,
                                    color: isDark ? Colors.white : AppColors.ink900,
                                  ),
                                ),
                              ),
                              const SizedBox(width: 8),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2.5),
                                decoration: BoxDecoration(
                                  color: isDark ? const Color(0xFF1E3A8A).withValues(alpha: 0.4) : AppColors.accentSoft,
                                  borderRadius: BorderRadius.circular(6),
                                ),
                                child: Text(
                                  'Built-in Rule',
                                  style: TextStyle(
                                    fontSize: 10.5,
                                    fontWeight: FontWeight.w800,
                                    color: isDark ? const Color(0xFF60A5FA) : AppColors.electricBlue,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          if (ref.isNotEmpty) ...[
                            const SizedBox(height: 2),
                            Text(
                              ref,
                              style: TextStyle(
                                fontSize: 10.5,
                                fontWeight: FontWeight.w600,
                                color: isDark ? const Color(0xFF93C5FD) : AppColors.primary,
                              ),
                            ),
                          ],
                          const SizedBox(height: 6),
                          Text(
                            standard,
                            style: TextStyle(
                              fontSize: 12,
                              color: isDark ? AppColors.ink300 : AppColors.ink500,
                              height: 1.4,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ],
            ),
          );
        }),

        // --- SECTION 2: LESSOR CONTRACT RULES (CHECKABLE) ---
        const SizedBox(height: 8),
        Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: Text(
            'Building Contract Rules (${_checklistClauses.length})',
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: isDark ? AppColors.ink400 : AppColors.ink500,
            ),
          ),
        ),

        // List of Interactive Clauses
        ...List.generate(_checklistClauses.length, (index) {
          final clause = _checklistClauses[index];
          final isAgreed = clause['agreed'] as bool;
          final isMandatory = clause['isMandatory'] == true;
          final ctrl = clause['controller'] as TextEditingController;

          return Container(
            margin: const EdgeInsets.only(bottom: 14),
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: isDark ? AppColors.navyCard : AppColors.surface,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(
                color: isAgreed
                    ? (isDark ? AppColors.navyBorder : AppColors.border)
                    : AppColors.electricBlue,
                width: isAgreed ? 1 : 1.5,
              ),
              boxShadow: [
                BoxShadow(
                  color: isAgreed
                      ? (isDark ? Colors.black.withValues(alpha: 0.2) : AppColors.primary.withValues(alpha: 0.03))
                      : AppColors.electricBlue.withValues(alpha: 0.06),
                  blurRadius: 10,
                  offset: const Offset(0, 3),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    InkWell(
                      borderRadius: BorderRadius.circular(8),
                      onTap: () {
                        if (isMandatory) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(
                              content: Text('This is a required platform compliance rule.'),
                              duration: Duration(seconds: 1),
                            ),
                          );
                          return;
                        }
                        HapticFeedback.selectionClick();
                        setState(() {
                          clause['agreed'] = !isAgreed;
                        });
                        _saveDraft();
                      },
                      child: Container(
                        width: 26,
                        height: 26,
                        decoration: BoxDecoration(
                          color: isAgreed
                              ? (isDark ? AppColors.electricBlue : AppColors.primary)
                              : (isDark ? AppColors.navySurface : Colors.white),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(
                            color: isAgreed
                                ? (isDark ? AppColors.electricBlue : AppColors.primary)
                                : AppColors.electricBlue,
                            width: 1.8,
                          ),
                        ),
                        child: isAgreed ? const Icon(Icons.check, color: Colors.white, size: 17) : null,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  clause['label'] as String,
                                  style: TextStyle(
                                    fontSize: 13.5,
                                    fontWeight: FontWeight.w800,
                                    color: isDark ? Colors.white : AppColors.ink900,
                                  ),
                                ),
                              ),
                              const SizedBox(width: 8),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2.5),
                                decoration: BoxDecoration(
                                  color: (isMandatory || isAgreed)
                                      ? (isDark ? const Color(0xFF1E3A8A).withValues(alpha: 0.4) : AppColors.accentSoft)
                                      : (isDark ? AppColors.electricBlue : AppColors.primary),
                                  borderRadius: BorderRadius.circular(6),
                                ),
                                child: Text(
                                  isMandatory
                                      ? 'Required'
                                      : (isAgreed ? 'Standard' : 'Counter-Offer'),
                                  style: TextStyle(
                                    fontSize: 10.5,
                                    fontWeight: FontWeight.w800,
                                    color: (isMandatory || isAgreed)
                                        ? (isDark ? const Color(0xFF60A5FA) : AppColors.electricBlue)
                                        : Colors.white,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          if (clause['ref'] != null && (clause['ref'] as String).isNotEmpty) ...[
                            const SizedBox(height: 2),
                            Text(
                              clause['ref'] as String,
                              style: TextStyle(
                                fontSize: 10.5,
                                fontWeight: FontWeight.w600,
                                color: isDark ? const Color(0xFF93C5FD) : AppColors.primary,
                              ),
                            ),
                          ],
                          const SizedBox(height: 6),
                          Text(
                            clause['standard'] as String,
                            style: TextStyle(
                              fontSize: 12,
                              color: isDark ? AppColors.ink300 : AppColors.ink500,
                              height: 1.4,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),

                // Rebuttal/Counter-proposal text input when unchecked
                if (!isAgreed) ...[
                  const SizedBox(height: 14),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: isDark ? AppColors.navySurface : AppColors.bg,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: AppColors.electricBlue.withValues(alpha: 0.35)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Icon(Icons.edit_note_rounded, size: 16, color: isDark ? const Color(0xFF60A5FA) : AppColors.electricBlue),
                            const SizedBox(width: 6),
                            Expanded(
                              child: Text(
                                'Propose Counter-Terms / Custom Request:',
                                style: TextStyle(
                                  fontSize: 11.5,
                                  fontWeight: FontWeight.w700,
                                  color: isDark ? const Color(0xFF60A5FA) : AppColors.primary,
                                ),
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        TextFormField(
                          controller: ctrl,
                          maxLines: 2,
                          style: TextStyle(fontSize: 12.5, color: isDark ? Colors.white : AppColors.ink900),
                          onChanged: (_) => _saveDraft(),
                          decoration: InputDecoration(
                            isDense: true,
                            hintText: clause['defaultCounterPlaceholder'] as String? ?? 'Specify your requested terms...',
                            hintStyle: TextStyle(fontSize: 12, color: isDark ? AppColors.ink400 : AppColors.ink500),
                            fillColor: isDark ? AppColors.navyCard : Colors.white,
                            filled: true,
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(8),
                              borderSide: BorderSide(color: isDark ? AppColors.navyBorder : AppColors.border),
                            ),
                            enabledBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(8),
                              borderSide: BorderSide(color: isDark ? AppColors.navyBorder : AppColors.border),
                            ),
                            focusedBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(8),
                              borderSide: const BorderSide(color: AppColors.electricBlue, width: 1.5),
                            ),
                            contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          );
        }),

        const SizedBox(height: 12),

        // Summary of attached docs before final submission
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: isDark ? AppColors.navyCard : AppColors.surface,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: isDark ? AppColors.navyBorder : AppColors.border),
          ),
          child: Row(
            children: [
              Icon(
                Icons.attach_file_rounded,
                size: 18,
                color: isDark ? const Color(0xFF60A5FA) : AppColors.primary,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  '${_uploadedDocs.length} verification document(s) will be submitted with this application.',
                  style: TextStyle(
                    fontSize: 12,
                    color: isDark ? AppColors.ink300 : AppColors.ink700,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 20),

        SizedBox(
          width: double.infinity,
          child: ElevatedButton(
            onPressed: _loading ? null : _submit,
            style: ElevatedButton.styleFrom(
              padding: const EdgeInsets.symmetric(vertical: 16),
              backgroundColor: isDark ? AppColors.electricBlue : AppColors.primary,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            ),
            child: _loading
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                  )
                : const Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.send_rounded, size: 18),
                      SizedBox(width: 8),
                      Text('Submit Application & Checklist', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
                    ],
                  ),
          ),
        ),
        const SizedBox(height: 24),
      ],
    );
  }

  Widget _buildErrorBox(String msg) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.errorSoft,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.error.withValues(alpha: 0.3)),
      ),
      child: Row(
        children: [
          const Icon(Icons.error_outline, size: 20, color: AppColors.error),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              msg,
              style: const TextStyle(color: AppColors.error, fontSize: 13, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSuccessScreen(bool isDark) {
    return Scaffold(
      backgroundColor: isDark ? AppColors.midnightNavy : AppColors.bg,
      appBar: AppBar(
        title: Text(
          'Proposal Sent',
          style: TextStyle(
            color: isDark ? Colors.white : AppColors.ink900,
            fontWeight: FontWeight.w800,
          ),
        ),
        backgroundColor: isDark ? AppColors.navySurface : AppColors.surface,
        elevation: 0,
      ),
      body: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 80,
              height: 80,
              decoration: AppDecorations.squircle(
                color: isDark ? const Color(0xFF064E3B) : AppColors.successSoft,
                radius: 24,
              ),
              child: const Icon(Icons.check_circle_rounded, color: AppColors.success, size: 48),
            ),
            const SizedBox(height: 24),
            Text(
              'Application & Checklist Sent!',
              style: TextStyle(
                fontSize: 22,
                fontWeight: FontWeight.w900,
                color: isDark ? Colors.white : AppColors.ink900,
                letterSpacing: -0.4,
              ),
            ),
            const SizedBox(height: 10),
            Text(
              'Your commercial lease proposal, verification documents, and negotiated terms for ${widget.propertyName} have been submitted to the lessor.',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 13.5,
                color: isDark ? AppColors.ink400 : AppColors.ink500,
                height: 1.45,
              ),
            ),
            const SizedBox(height: 20),
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: isDark ? AppColors.navyCard : AppColors.surface,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: isDark ? AppColors.navyBorder : AppColors.border),
              ),
              child: Row(
                children: [
                  const Icon(Icons.lock_outline_rounded, size: 18, color: AppColors.warning),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Confidentiality Lock is active on the auto-generated contract until initial deposit is finalized.',
                      style: TextStyle(
                        fontSize: 11.5,
                        color: isDark ? AppColors.ink300 : AppColors.ink700,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 28),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () {
                  Navigator.of(context).pushReplacement(
                    MaterialPageRoute(
                      builder: (_) => LeaseContractScreen(
                        applicationData: {
                          'property_name': widget.propertyName,
                          'rent': _effectiveRent,
                          'term_months': _selectedTerm,
                          'business_name': _businessCtrl.text.trim(),
                          'checklist_negotiation': _submittedNegotiationPayload,
                        },
                        forceInitialLock: true,
                      ),
                    ),
                  );
                },
                icon: const Icon(Icons.checklist_rtl_rounded, size: 20),
                label: const Text('View Negotiation & Contract Status', style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w700)),
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  backgroundColor: isDark ? AppColors.electricBlue : AppColors.primary,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
              ),
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: TextButton(
                onPressed: () => Navigator.of(context)..pop()..pop(),
                child: Text(
                  'Back to Browse Spaces',
                  style: TextStyle(
                    fontSize: 14,
                    color: isDark ? const Color(0xFF60A5FA) : AppColors.ink500,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
