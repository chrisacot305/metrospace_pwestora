import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../theme.dart';
import '../services/api_service.dart';
import 'messages_screen.dart';

class TicketCreateScreen extends StatefulWidget {
  final Map<String, dynamic>? lease;
  const TicketCreateScreen({super.key, this.lease});

  @override
  State<TicketCreateScreen> createState() => _TicketCreateScreenState();
}

class _TicketCreateScreenState extends State<TicketCreateScreen> {
  // Steps: 1 = Category Selection, 2 = Details & Checklist, 3 = Review, 4 = Success
  int _currentStep = 1;

  String? _selectedCategoryKey;
  String _selectedCategoryLabel = '';
  IconData _selectedCategoryIcon = Icons.build_rounded;

  String _selectedSymptom = 'Water leakage';
  final _descriptionCtrl = TextEditingController();
  String _priority = 'medium'; // low, medium, high, urgent
  bool _loading = false;
  String? _error;
  bool _validateDetails = false;
  final List<XFile> _attachedPhotos = [];
  final ImagePicker _picker = ImagePicker();

  Future<void> _pickPhoto(ImageSource source) async {
    try {
      final picked = await _picker.pickImage(
        source: source,
        maxWidth: 1600,
        imageQuality: 85,
      );
      if (picked != null && mounted) {
        setState(() {
          _attachedPhotos.add(picked);
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not open camera or gallery: $e')),
        );
      }
    }
  }

  final List<Map<String, dynamic>> _categories = [
    {
      'key': 'plumbing',
      'label': 'Plumbing',
      'icon': Icons.water_drop_rounded,
      'symptoms': ['Water leakage', 'Low water pressure', 'Clogged pipe', 'Pipe burst', 'Other'],
    },
    {
      'key': 'electrical',
      'label': 'Electrical',
      'icon': Icons.bolt_rounded,
      'symptoms': ['Power outage', 'Flickering lights', 'Sparking outlet', 'Breaker tripped', 'Other'],
    },
    {
      'key': 'hvac',
      'label': 'Air Conditioning',
      'icon': Icons.ac_unit_rounded,
      'symptoms': ['Not cooling', 'Water leaking', 'Noisy unit', 'Unpleasant odor', 'Won\'t turn on', 'Other'],
    },
    {
      'key': 'structural',
      'label': 'Door / Lock',
      'icon': Icons.lock_rounded,
      'symptoms': ['Key jammed', 'Broken door knob', 'Door won\'t close', 'Hinge broken', 'Other'],
    },
    {
      'key': 'comfort room',
      'label': 'Comfort Room',
      'icon': Icons.wc_rounded,
      'symptoms': ['Water leakage', 'Clogged toilet', 'No water', 'Broken fixture', 'Bad smell', 'Other'],
    },
    {
      'key': 'lighting',
      'label': 'Lighting',
      'icon': Icons.lightbulb_rounded,
      'symptoms': ['Bulb replacement', 'Switch broken', 'Dim lights', 'Fixture loose', 'Other'],
    },
    {
      'key': 'cleanliness',
      'label': 'Cleanliness',
      'icon': Icons.auto_awesome_rounded,
      'symptoms': ['Trash disposal', 'Common area cleaning', 'Stain removal', 'Pest control', 'Other'],
    },
    {
      'key': 'other',
      'label': 'Other',
      'icon': Icons.more_horiz_rounded,
      'symptoms': ['General maintenance', 'Wall damage', 'Window repair', 'Noise complaint', 'Other'],
    },
  ];

  @override
  void dispose() {
    _descriptionCtrl.dispose();
    super.dispose();
  }

  void _onSelectCategory(Map<String, dynamic> cat) {
    setState(() {
      _selectedCategoryKey = cat['key'];
      _selectedCategoryLabel = cat['label'];
      _selectedCategoryIcon = cat['icon'];
      final symptoms = cat['symptoms'] as List<String>;
      _selectedSymptom = symptoms.first;
      _currentStep = 2;
    });
  }

  List<String> _currentSymptoms() {
    final cat = _categories.firstWhere(
      (c) => c['label'] == _selectedCategoryLabel,
      orElse: () => _categories.first,
    );
    return cat['symptoms'] as List<String>;
  }

  Future<void> _submitReport() async {
    if (_descriptionCtrl.text.trim().isEmpty) {
      setState(() => _error = 'Please provide details describing the problem.');
      return;
    }
    if (_attachedPhotos.isEmpty) {
      setState(() => _error = 'Please attach at least 1 photo of the issue.');
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    final title = '[$_selectedCategoryLabel - $_selectedSymptom] ${_descriptionCtrl.text.trim()}';

    final tid = widget.lease?['tenant_id'] ?? widget.lease?['id'];
    final int? tenantId = tid is int ? tid : (tid != null ? int.tryParse(tid.toString()) : null);

    try {
      final ticketId = await ApiService.createTicket(
        category: _selectedCategoryKey ?? 'plumbing',
        priority: _priority,
        title: title,
        tenantId: tenantId,
        photos: _attachedPhotos,
      );

      // Auto-post report details to the chat thread with lessor
      try {
        final ticketFormatted = '#SL-${ticketId.toString().padLeft(5, '0')}';
        final descText = _descriptionCtrl.text.trim();
        final photoNote = '\nAttachments: ${_attachedPhotos.length} photo(s) attached';
        await ApiService.sendMessage(
          '🛠️ Maintenance Report ($ticketFormatted)\n'
          'Category: $_selectedCategoryLabel • $_selectedSymptom\n'
          'Description: $descText'
          '$photoNote\n'
          'Status: Pending Review',
          tenantId: tenantId,
        );
      } catch (_) {
        // Chat notification is complementary; proceed even if message post fails
      }

      if (!mounted) return;
      _showSuccessDialog(ticketId);
    } catch (e) {
      setState(() => _error = ApiService.errorMessage(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _showSuccessDialog(int ticketId) {
    final ticketFormatted = '#SL-${ticketId.toString().padLeft(5, '0')}';
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        backgroundColor: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
        contentPadding: const EdgeInsets.fromLTRB(24, 24, 24, 20),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 56,
              height: 56,
              decoration: BoxDecoration(
                color: const Color(0xFFEFF6FF),
                shape: BoxShape.circle,
                border: Border.all(color: const Color(0xFFBFDBFE), width: 1.5),
              ),
              child: const Icon(
                Icons.check_circle_rounded,
                color: AppColors.electricBlue,
                size: 32,
              ),
            ),
            const SizedBox(height: 16),
            const Text(
              'Report Submitted!',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w900,
                color: AppColors.midnightNavy,
                letterSpacing: -0.3,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Your maintenance report $ticketFormatted has been recorded as Pending. The lessor will review the request and keep you updated.',
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 13,
                color: AppColors.ink500,
                height: 1.4,
              ),
            ),
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () {
                  Navigator.of(ctx).pop();
                  Navigator.of(context).pop(true);
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                  elevation: 0,
                ),
                child: const Text(
                  'Back to Reports',
                  style: TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {

    if (_currentStep == 1) {
      return Scaffold(
        backgroundColor: const Color(0xFFF3F6FB),
        body: SingleChildScrollView(
          physics: const BouncingScrollPhysics(),
          child: _buildCategoryStep(),
        ),
      );
    }

    return Scaffold(
      backgroundColor: AppColors.bg,
      appBar: AppBar(
        backgroundColor: AppColors.bg,
        elevation: 0,
        leading: AppBackButton(
          onPressed: () {
            if (_currentStep > 1) {
              setState(() => _currentStep--);
            } else {
              Navigator.of(context).pop();
            }
          },
        ),
        title: Text(
          _currentStep == 2 ? 'Report Details' : 'Review Report',
          style: const TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.w800,
            color: AppColors.ink900,
          ),
        ),
        actions: [
          if (_currentStep == 3)
            TextButton(
              onPressed: () => setState(() => _currentStep = 2),
              child: const Text(
                'Edit',
                style: TextStyle(
                  color: AppColors.electricBlue,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
        ],
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (_currentStep == 2) _buildDetailsStep(),
              if (_currentStep == 3) _buildReviewStep(),
            ],
          ),
        ),
      ),
    );
  }

  // ================= STEP 1: CATEGORY SELECTION (MATCHING MOCKUP) =================
  Widget _buildCategoryStep() {
    return Column(
      children: [
        // ================= TOP HERO WITH DEEP NAVY CURVED BACKGROUND =================
        ClipRRect(
          borderRadius: const BorderRadius.only(
            bottomLeft: Radius.circular(32),
            bottomRight: Radius.circular(32),
          ),
          child: Container(
            width: double.infinity,
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                colors: [
                  Color(0xFF071739),
                  Color(0xFF0F2C69),
                  Color(0xFF1E6BFF),
                ],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
            ),
            child: Stack(
              children: [
                // Decorative Glow Orb Top-Right
                Positioned(
                  top: -30,
                  right: -30,
                  child: Container(
                    width: 150,
                    height: 150,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: const Color(0xFF00E5FF).withValues(alpha: 0.12),
                    ),
                  ),
                ),
                // Secondary Subtle Glow Orb
                Positioned(
                  bottom: -20,
                  right: 70,
                  child: Container(
                    width: 90,
                    height: 90,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: const Color(0xFF1E6BFF).withValues(alpha: 0.16),
                    ),
                  ),
                ),

                // Header Content
                Padding(
                  padding: EdgeInsets.only(
                    top: MediaQuery.of(context).padding.top + 16,
                    left: 14,
                    right: 22,
                    bottom: 30,
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Official App Back Button
                      const AppBackButton(isDark: true),
                      const SizedBox(height: 12),
                      Padding(
                        padding: const EdgeInsets.only(left: 8.0),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Report a Problem',
                              style: TextStyle(
                                fontSize: 24,
                                fontWeight: FontWeight.w900,
                                color: Colors.white,
                                letterSpacing: -0.4,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'What needs attention?',
                              style: TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w400,
                                color: Colors.white.withValues(alpha: 0.8),
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

        // ================= CATEGORY GRID & CONTACT MANAGEMENT CARD =================
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // 8-item 3-column category grid
              GridView.builder(
                shrinkWrap: true,
                padding: EdgeInsets.zero,
                physics: const NeverScrollableScrollPhysics(),
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 3,
                  crossAxisSpacing: 10,
                  mainAxisSpacing: 10,
                  childAspectRatio: 0.86,
                ),
                itemCount: _categories.length,
                itemBuilder: (context, index) {
                  final cat = _categories[index];
                  final icon = cat['icon'] as IconData;
                  final label = cat['label'] as String;

                  return InkWell(
                    borderRadius: BorderRadius.circular(18),
                    onTap: () => _onSelectCategory(cat),
                    child: Container(
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(18),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                        boxShadow: [
                          BoxShadow(
                            color: const Color(0xFF0A1832).withValues(alpha: 0.04),
                            blurRadius: 10,
                            offset: const Offset(0, 3),
                          ),
                        ],
                      ),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(icon, color: const Color(0xFF0B1B3D), size: 28),
                          const SizedBox(height: 10),
                          Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 6),
                            child: Text(
                              label,
                              textAlign: TextAlign.center,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 11.5,
                                fontWeight: FontWeight.w800,
                                color: Color(0xFF0B1B3D),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),

              const SizedBox(height: 24),

              // Contact Management Card
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
                decoration: BoxDecoration(
                  color: const Color(0xFFEFF6FF),
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: const Color(0xFFDBEAFE)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Need to report something else?',
                      style: TextStyle(
                        fontSize: 13.5,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF0B1B3D),
                      ),
                    ),
                    const SizedBox(height: 3),
                    const Text(
                      'You can also contact management directly.',
                      style: TextStyle(
                        fontSize: 12,
                        color: Color(0xFF64748B),
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    const SizedBox(height: 12),
                    InkWell(
                      onTap: () {
                        Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => MessagesScreen(lease: widget.lease),
                          ),
                        );
                      },
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: const [
                          Text(
                            'Contact Management',
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w800,
                              color: Color(0xFF1E6BFF),
                            ),
                          ),
                          SizedBox(width: 4),
                          Icon(Icons.arrow_forward_rounded, size: 14, color: Color(0xFF1E6BFF)),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 30),
            ],
          ),
        ),
      ],
    );
  }

  // ================= STEP 2: DETAILS & CHECKLIST =================
  Widget _buildDetailsStep() {
    final symptoms = _currentSymptoms();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Stepper Header
        _buildStepper(2),
        const SizedBox(height: 18),

        // Selected Category Banner
        InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () => setState(() => _currentStep = 1),
          child: Container(
            padding: const EdgeInsets.all(14),
            decoration: AppDecorations.card(radius: 16),
            child: Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: AppColors.primary,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(_selectedCategoryIcon, color: Colors.white, size: 20),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Text(
                    _selectedCategoryLabel,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      color: AppColors.ink900,
                    ),
                  ),
                ),
                const Icon(Icons.chevron_right_rounded, color: AppColors.ink400),
              ],
            ),
          ),
        ),

        const SizedBox(height: 22),

        // "What's happening?" checklist
        const Text(
          'What\'s happening?',
          style: TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w800,
            color: AppColors.ink900,
          ),
        ),
        const SizedBox(height: 10),

        ...symptoms.map((symptom) {
          final isSelected = _selectedSymptom == symptom;
          return Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: InkWell(
              borderRadius: BorderRadius.circular(12),
              onTap: () => setState(() => _selectedSymptom = symptom),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                    color: isSelected ? AppColors.electricBlue : AppColors.border,
                    width: isSelected ? 1.5 : 1,
                  ),
                ),
                child: Row(
                  children: [
                    Container(
                      width: 18,
                      height: 18,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: isSelected ? AppColors.electricBlue : AppColors.ink300,
                          width: 2,
                        ),
                        color: isSelected ? AppColors.electricBlue : Colors.transparent,
                      ),
                      child: isSelected
                          ? const Icon(Icons.check, size: 12, color: Colors.white)
                          : null,
                    ),
                    const SizedBox(width: 12),
                    Text(
                      symptom,
                      style: TextStyle(
                        fontSize: 13.5,
                        fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                        color: isSelected ? AppColors.ink900 : AppColors.ink700,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          );
        }),

        const SizedBox(height: 20),

        // Description Box
        Row(
          children: const [
            Text(
              'Description',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w800,
                color: AppColors.ink900,
              ),
            ),
            SizedBox(width: 4),
            Text(
              '*',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w800,
                color: AppColors.error,
              ),
            ),
            SizedBox(width: 6),
            Text(
              '(Required)',
              style: TextStyle(
                fontSize: 11.5,
                fontWeight: FontWeight.w600,
                color: AppColors.ink500,
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        TextField(
          controller: _descriptionCtrl,
          maxLines: 3,
          onChanged: (_) {
            if (_validateDetails) setState(() {});
          },
          decoration: InputDecoration(
            hintText: 'Describe the problem in detail...',
            errorText: _validateDetails && _descriptionCtrl.text.trim().isEmpty
                ? 'Details are required'
                : null,
          ),
        ),

        const SizedBox(height: 20),

        // Attach Photo Box
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Expanded(
              child: Row(
                children: const [
                  Text(
                    'Attach Photo',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: AppColors.ink900,
                    ),
                  ),
                  SizedBox(width: 4),
                  Text(
                    '*',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: AppColors.error,
                    ),
                  ),
                  SizedBox(width: 6),
                  Flexible(
                    child: Text(
                      '(At least 1 required)',
                      style: TextStyle(
                        fontSize: 11.5,
                        fontWeight: FontWeight.w600,
                        color: AppColors.ink500,
                      ),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ],
              ),
            ),
            if (_attachedPhotos.isNotEmpty) ...[
              const SizedBox(width: 8),
              FittedBox(
                fit: BoxFit.scaleDown,
                child: Text(
                  '${_attachedPhotos.length} photo${_attachedPhotos.length > 1 ? 's' : ''} added',
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: AppColors.electricBlue,
                  ),
                ),
              ),
            ],
          ],
        ),
        if (_validateDetails && _attachedPhotos.isEmpty) ...[
          const SizedBox(height: 6),
          const Text(
            'Please attach at least 1 photo of the issue.',
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: AppColors.error,
            ),
          ),
        ],
        const SizedBox(height: 10),

        SizedBox(
          height: 72,
          child: ListView(
            scrollDirection: Axis.horizontal,
            physics: const BouncingScrollPhysics(),
            children: [
              // Direct Camera Button
              InkWell(
                borderRadius: BorderRadius.circular(14),
                onTap: () => _pickPhoto(ImageSource.camera),
                child: Container(
                  width: 72,
                  height: 72,
                  margin: const EdgeInsets.only(right: 10),
                  decoration: BoxDecoration(
                    color: const Color(0xFFEFF6FF),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: const Color(0xFF93C5FD), width: 1.2),
                  ),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: const [
                      Icon(Icons.camera_alt_rounded, color: Color(0xFF1E6BFF), size: 22),
                      SizedBox(height: 3),
                      Text(
                        'Camera',
                        style: TextStyle(
                          fontSize: 10.5,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF1E40AF),
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              // Upload from Gallery Button
              InkWell(
                borderRadius: BorderRadius.circular(14),
                onTap: () => _pickPhoto(ImageSource.gallery),
                child: Container(
                  width: 72,
                  height: 72,
                  margin: const EdgeInsets.only(right: 10),
                  decoration: BoxDecoration(
                    color: AppColors.surface,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: AppColors.border, width: 1.2),
                  ),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: const [
                      Icon(Icons.photo_library_outlined, color: AppColors.ink700, size: 22),
                      SizedBox(height: 3),
                      Text(
                        'Gallery',
                        style: TextStyle(
                          fontSize: 10.5,
                          fontWeight: FontWeight.w700,
                          color: AppColors.ink700,
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              // Attached Photos with Delete button
              ..._attachedPhotos.asMap().entries.map((entry) {
                final index = entry.key;
                final file = entry.value;

                return Stack(
                  clipBehavior: Clip.none,
                  children: [
                    Container(
                      width: 72,
                      height: 72,
                      margin: const EdgeInsets.only(right: 12),
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: AppColors.electricBlue, width: 1.5),
                      ),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(12),
                        child: FutureBuilder<Uint8List>(
                          future: file.readAsBytes(),
                          builder: (context, snap) {
                            if (snap.connectionState == ConnectionState.done && snap.hasData) {
                              return Image.memory(
                                snap.data!,
                                fit: BoxFit.cover,
                                width: 72,
                                height: 72,
                              );
                            }
                            return Container(
                              color: const Color(0xFF1E293B),
                              child: const Center(
                                child: SizedBox(
                                  width: 16,
                                  height: 16,
                                  child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                ),
                              ),
                            );
                          },
                        ),
                      ),
                    ),
                    Positioned(
                      top: -6,
                      right: 4,
                      child: GestureDetector(
                        onTap: () {
                          setState(() {
                            _attachedPhotos.removeAt(index);
                          });
                        },
                        child: Container(
                          padding: const EdgeInsets.all(3),
                          decoration: const BoxDecoration(
                            color: Color(0xFFEF4444),
                            shape: BoxShape.circle,
                          ),
                          child: const Icon(Icons.close_rounded, size: 12, color: Colors.white),
                        ),
                      ),
                    ),
                  ],
                );
              }),
            ],
          ),
        ),

        const SizedBox(height: 32),

        // Continue button with Gradient and Glow
        InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () {
            setState(() => _validateDetails = true);
            if (_descriptionCtrl.text.trim().isEmpty) {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('Please add details describing the problem.'),
                  backgroundColor: AppColors.error,
                  behavior: SnackBarBehavior.floating,
                ),
              );
              return;
            }
            if (_attachedPhotos.isEmpty) {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('Please attach at least 1 photo of the issue.'),
                  backgroundColor: AppColors.error,
                  behavior: SnackBarBehavior.floating,
                ),
              );
              return;
            }
            setState(() => _currentStep = 3);
          },
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 16),
            decoration: AppDecorations.glowButton(radius: 16),
            child: const Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  'Continue',
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: Colors.white),
                ),
                SizedBox(width: 8),
                Icon(Icons.arrow_forward_rounded, size: 18, color: Colors.white),
              ],
            ),
          ),
        ),
        const SizedBox(height: 24),
      ],
    );
  }

  // ================= STEP 3: REVIEW REPORT =================
  Widget _buildReviewStep() {
    final propertyName = (widget.lease?['property_name'] ?? '').toString().trim();
    final desc = _descriptionCtrl.text.trim().isEmpty
        ? 'No additional description provided.'
        : _descriptionCtrl.text.trim();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (_error != null) ...[
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppColors.errorSoft,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              children: [
                const Icon(Icons.error_outline, color: AppColors.error, size: 20),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    _error!,
                    style: const TextStyle(color: AppColors.error, fontSize: 13, fontWeight: FontWeight.w600),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
        ],

        // Summary Card
        Container(
          padding: const EdgeInsets.all(18),
          decoration: AppDecorations.card(radius: 20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: AppColors.primary,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(_selectedCategoryIcon, color: Colors.white, size: 22),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _selectedCategoryLabel,
                          style: const TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                            color: AppColors.ink900,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 2),
                        Text(
                          _selectedSymptom,
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: AppColors.ink500,
                          ),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),

              if (propertyName.isNotEmpty) ...[
                Row(
                  children: [
                    const Icon(Icons.location_on_outlined, size: 16, color: AppColors.electricBlue),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        propertyName,
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: AppColors.ink700,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
              ],

              // Attached Photo Thumbnail / Previews
              if (_attachedPhotos.isNotEmpty) ...[
                SizedBox(
                  height: 120,
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    physics: const BouncingScrollPhysics(),
                    itemCount: _attachedPhotos.length,
                    separatorBuilder: (_, index) => const SizedBox(width: 10),
                    itemBuilder: (context, i) {
                      return ClipRRect(
                        borderRadius: BorderRadius.circular(14),
                        child: FutureBuilder<Uint8List>(
                          future: _attachedPhotos[i].readAsBytes(),
                          builder: (context, snap) {
                            if (snap.connectionState == ConnectionState.done && snap.hasData) {
                              return Image.memory(
                                snap.data!,
                                width: 140,
                                height: 120,
                                fit: BoxFit.cover,
                              );
                            }
                            return Container(
                              width: 140,
                              height: 120,
                              color: const Color(0xFF1E293B),
                              child: const Center(
                                child: SizedBox(
                                  width: 20,
                                  height: 20,
                                  child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                ),
                              ),
                            );
                          },
                        ),
                      );
                    },
                  ),
                ),
              ] else ...[
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(vertical: 20),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFEF2F2),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: AppColors.error.withValues(alpha: 0.3)),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: const [
                      Icon(Icons.photo_outlined, size: 20, color: AppColors.error),
                      SizedBox(width: 8),
                      Text(
                        'At least 1 photo is required',
                        style: TextStyle(fontSize: 12.5, color: AppColors.error, fontWeight: FontWeight.w700),
                      ),
                    ],
                  ),
                ),
              ],

              const SizedBox(height: 18),

              // Description Text
              const Text(
                'Description',
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: AppColors.ink400,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                desc,
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                  color: AppColors.ink900,
                ),
              ),

              const SizedBox(height: 18),

              // Priority Selector & Badge
              const Text(
                'Priority',
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: AppColors.ink400,
                ),
              ),
              const SizedBox(height: 8),

              Row(
                children: [
                  Expanded(child: _buildPriorityChip('medium', 'Normal', AppColors.electricBlue)),
                  const SizedBox(width: 8),
                  Expanded(child: _buildPriorityChip('high', 'High', AppColors.warning)),
                  const SizedBox(width: 8),
                  Expanded(child: _buildPriorityChip('urgent', 'Urgent', AppColors.error)),
                ],
              ),
            ],
          ),
        ),

        const SizedBox(height: 32),

        // Submit button with Gradient and Glow
        InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: _loading ? null : _submitReport,
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 16),
            decoration: AppDecorations.glowButton(radius: 16),
            alignment: Alignment.center,
            child: _loading
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                  )
                : const Text(
                    'Submit Report',
                    style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: Colors.white),
                  ),
          ),
        ),
        const SizedBox(height: 24),
      ],
    );
  }

  Widget _buildPriorityChip(String key, String label, Color color) {
    final isSelected = _priority == key;
    return GestureDetector(
      onTap: () => setState(() => _priority = key),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
        decoration: BoxDecoration(
          color: isSelected ? color.withValues(alpha: 0.15) : AppColors.bg,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: isSelected ? color : AppColors.border,
            width: isSelected ? 1.5 : 1,
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 12,
            fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
            color: isSelected ? color : AppColors.ink500,
          ),
        ),
      ),
    );
  }



  Widget _buildStepper(int step) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        _buildStepIndicator(1, 'Category', step >= 1, step == 1),
        _buildStepLine(step >= 2),
        _buildStepIndicator(2, 'Details', step >= 2, step == 2),
        _buildStepLine(step >= 3),
        _buildStepIndicator(3, 'Confirm', step >= 3, step == 3),
      ],
    );
  }

  Widget _buildStepIndicator(int num, String label, bool isDone, bool isCurrent) {
    return Column(
      children: [
        Container(
          width: 24,
          height: 24,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: isCurrent || isDone ? AppColors.primary : AppColors.surface,
            border: Border.all(
              color: isCurrent || isDone ? AppColors.primary : AppColors.border,
            ),
          ),
          alignment: Alignment.center,
          child: Text(
            '$num',
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w800,
              color: isCurrent || isDone ? Colors.white : AppColors.ink400,
            ),
          ),
        ),
        const SizedBox(height: 4),
        Text(
          label,
          style: TextStyle(
            fontSize: 11,
            fontWeight: isCurrent ? FontWeight.w700 : FontWeight.w500,
            color: isCurrent ? AppColors.ink900 : AppColors.ink400,
          ),
        ),
      ],
    );
  }

  Widget _buildStepLine(bool isActive) {
    return Container(
      width: 40,
      height: 1.5,
      margin: const EdgeInsets.only(bottom: 16, left: 6, right: 6),
      color: isActive ? AppColors.primary : AppColors.border,
    );
  }
}
