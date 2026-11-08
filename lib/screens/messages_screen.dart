import 'dart:async';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../theme.dart';
import '../services/api_service.dart';
import 'violation_notice_sheet.dart';
import 'violations_screen.dart';
import 'ticket_detail_screen.dart';
import 'lease_contract_screen.dart';

class MessagesScreen extends StatefulWidget {
  final Map<String, dynamic>? lease;
  final Function(int)? onNavigateTab;
  final ValueChanged<bool>? onTypingChanged;

  const MessagesScreen({
    super.key,
    this.lease,
    this.onNavigateTab,
    this.onTypingChanged,
  });

  @override
  State<MessagesScreen> createState() => _MessagesScreenState();
}

class _MessagesScreenState extends State<MessagesScreen> {
  final TextEditingController _msgCtrl = TextEditingController();
  final ScrollController _scrollCtrl = ScrollController();
  final FocusNode _focusNode = FocusNode();
  bool _loading = true;
  bool _sending = false;
  String _lessorName = 'Building Management';
  List<Map<String, dynamic>> _messages = [];
  List<dynamic> _tickets = [];
  Timer? _pollingTimer;
  bool _hasText = false;
  final Set<int> _expandedMessageIndices = {};

  int? get _currentTenantId {
    if (widget.lease == null) return null;
    final tid = widget.lease!['tenant_id'] ?? widget.lease!['id'];
    if (tid is int) return tid;
    if (tid is String) return int.tryParse(tid);
    return null;
  }

  String get _accountSubtitle {
    if (widget.lease == null) return 'Building Management • Active Lease';
    final propName = (widget.lease!['property_name'] ?? widget.lease!['name'] ?? '').toString();
    final bizName = (widget.lease!['business_name'] ?? '').toString();
    final unit = (widget.lease!['unit_label'] ?? '').toString();

    final parts = <String>[];
    if (propName.isNotEmpty) parts.add(propName);
    if (bizName.isNotEmpty) {
      parts.add(bizName);
    } else if (unit.isNotEmpty) {
      parts.add('Unit $unit');
    }

    if (parts.isEmpty) return 'Building Management • Active Lease';
    return parts.join(' • ');
  }

  @override
  void initState() {
    super.initState();
    _focusNode.addListener(() {
      final isTyping = _focusNode.hasFocus || _msgCtrl.text.trim().isNotEmpty;
      widget.onTypingChanged?.call(isTyping);
    });

    _msgCtrl.addListener(() {
      final hasText = _msgCtrl.text.trim().isNotEmpty;
      if (hasText != _hasText && mounted) {
        setState(() => _hasText = hasText);
        widget.onTypingChanged?.call(_focusNode.hasFocus || hasText);
      }
    });
    _loadData(initial: true);
    _pollingTimer = Timer.periodic(const Duration(seconds: 6), (_) {
      if (mounted && !_sending) _loadData(silent: true);
    });
  }

  @override
  void didUpdateWidget(covariant MessagesScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.lease != oldWidget.lease) {
      _loadData(initial: true);
    }
  }

  @override
  void dispose() {
    _pollingTimer?.cancel();
    _focusNode.dispose();
    _msgCtrl.dispose();
    _scrollCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadData({bool initial = false, bool silent = false}) async {
    if (!silent) setState(() => _loading = true);

    try {
      final tid = _currentTenantId;
      final res = await ApiService.fetchMessagesThread(tenantId: tid);
      final lessor = res['lessor_name'] as String?;
      final rawMsgs = (res['messages'] as List<dynamic>?) ?? [];
      final parsedMsgs = rawMsgs
          .map((m) => Map<String, dynamic>.from(m as Map))
          .toList();

      // Fetch tickets to link live status to report bubbles
      try {
        final t = await ApiService.fetchTickets(tenantId: tid);
        _tickets = t;
      } catch (_) {}

      // Fetch violations for this specific tenant/building so they appear in chat
      try {
        final violations = await ApiService.fetchViolations(
          tenantId: tid,
          lessorName: lessor ?? _lessorName,
        );
        for (final v in violations) {
          // Verify that this violation actually matches the active chat landlord
          if (v['lessor_name'] != null && _lessorName.isNotEmpty) {
            final vLessor = v['lessor_name'].toString().trim().toLowerCase();
            final curLessor = (lessor ?? _lessorName).trim().toLowerCase();
            if (vLessor.isNotEmpty && curLessor.isNotEmpty && vLessor != curLessor) {
              continue;
            }
          }
          if (v['tenant_id'] != null && tid != null) {
            final int? vTid = v['tenant_id'] is int ? v['tenant_id'] as int : int.tryParse(v['tenant_id'].toString());
            if (vTid != null && vTid != tid) {
              continue;
            }
          }
          final strike = v['strike'] is int
              ? v['strike'] as int
              : int.tryParse(v['strike']?.toString() ?? '1') ?? 1;

          // Only attach to real messages in this thread
          final idx = parsedMsgs.indexWhere((m) {
            final b = (m['body'] ?? '').toString();
            final isNotice = m['notice_type'] == 'Infraction Notice' || m['notice_type'] == 'violation';
            return (b.contains('OFFICIAL NOTICE OF INFRACTION') || isNotice) &&
                (b.contains('STRIKE $strike') || b.contains('Strike $strike') || strike == 1);
          });
          if (idx != -1 && parsedMsgs[idx]['violation_data'] == null) {
            parsedMsgs[idx]['violation_data'] = v;
          } else if (idx == -1) {
            // Fallback: If no chat message exists in the database thread for this violation,
            // synthesize an official citation card entry in parsedMsgs so it appears in the chat feed!
            final strikeBadge = 'STRIKE $strike OF 3';
            final penaltyLine = strike == 2
                ? '\nPenalty Injected: ₱500.00 late/infraction penalty added to billing statement.'
                : (strike >= 3
                    ? '\nPenalty Injected: ₱1,500.00 penalty & Formal Lease Termination / Eviction proceedings initiated.'
                    : '\nAction Required: Immediate compliance with commercial property guidelines.');
            final category = v['category']?.toString() ?? 'Lease Violation';
            final msgBody = '⚠️ OFFICIAL NOTICE OF INFRACTION ($strikeBadge)\nCategory: $category$penaltyLine\n\nA formal citation has been officially recorded against your lease agreement. Please review the citation details and disciplinary policy under your account.';

            final vId = v['id'] is int ? v['id'] as int : int.tryParse(v['id']?.toString() ?? '0') ?? 0;
            parsedMsgs.add({
              'id': -1000 - vId,
              'sender': 'lessor',
              'notice_type': 'Infraction Notice',
              'body': msgBody,
              'sent_at': v['issued_at'] ?? DateTime.now().toIso8601String(),
              'violation_data': v,
            });
          }
        }
        // Sort chronologically by sent_at
        parsedMsgs.sort((a, b) {
          final tA = DateTime.tryParse(a['sent_at']?.toString() ?? '') ?? DateTime(1970);
          final tB = DateTime.tryParse(b['sent_at']?.toString() ?? '') ?? DateTime(1970);
          return tA.compareTo(tB);
        });
      } catch (_) {}

      if (mounted) {
        setState(() {
          if (lessor != null && lessor.isNotEmpty) {
            _lessorName = lessor;
          }
          _messages = parsedMsgs;
          _loading = false;
        });

        if (initial && _messages.isNotEmpty) {
          WidgetsBinding.instance.addPostFrameCallback(
            (_) => _scrollToBottom(animated: false),
          );
        }
      }
    } catch (_) {
      if (mounted && !silent) setState(() => _loading = false);
    }
  }

  void _scrollToBottom({bool animated = true}) {
    if (!_scrollCtrl.hasClients) return;
    final pos = _scrollCtrl.position.maxScrollExtent;
    if (animated) {
      _scrollCtrl.animateTo(
        pos,
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeOut,
      );
    } else {
      _scrollCtrl.jumpTo(pos);
    }
  }

  Future<void> _sendMessage() async {
    final text = _msgCtrl.text.trim();
    if (text.isEmpty || _sending) return;

    setState(() => _sending = true);
    _msgCtrl.clear();

    try {
      await ApiService.sendMessage(text, tenantId: _currentTenantId);
      await _loadData(silent: true);
      _scrollToBottom(animated: true);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(ApiService.errorMessage(e))));
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }


  Future<void> _pickPhoto() async {
    try {
      final picker = ImagePicker();
      final picked = await picker.pickImage(
        source: ImageSource.gallery,
        maxWidth: 1600,
        imageQuality: 85,
      );
      if (picked != null && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            backgroundColor: const Color(0xFF0B1B3D),
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
            content: Row(
              children: [
                const Icon(
                  Icons.check_circle_rounded,
                  color: Color(0xFF10B981),
                  size: 18,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Selected photo: ${picked.name}',
                    style: const TextStyle(fontWeight: FontWeight.w600),
                  ),
                ),
              ],
            ),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not open photos: $e')),
        );
      }
    }
  }

  bool _isViolationMessage(String body, String? noticeType) {
    if (noticeType == 'Infraction Notice' || noticeType == 'violation') return true;
    return body.contains('OFFICIAL NOTICE OF INFRACTION') ||
        body.contains('Infraction Notice') ||
        (body.contains('⚠️') && body.contains('Strike'));
  }

  bool _isReportMessage(String body) {
    return body.contains('🛠️ Maintenance Report') ||
        body.contains('Maintenance Report') ||
        body.contains('#SL-') ||
        body.contains('#MT-');
  }

  bool _isRestructuringMessage(String body) {
    return body.contains('📋 Payment Restructuring') ||
        body.contains('Restructuring Request') ||
        body.contains('Restructuring Proposal') ||
        body.contains('Restructuring Request Revoked');
  }

  bool _isReceiptMessage(String body, String? noticeType) {
    return noticeType == 'Payment Receipt' ||
        body.contains('PAYMENT RECEIPT') ||
        body.contains('Receipt Ref:') ||
        body.contains('Payment Ref:');
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF000000) : const Color(0xFFF3F6FB),
      appBar: AppBar(
        automaticallyImplyLeading: false,
        backgroundColor: isDark ? const Color(0xFF121212) : Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        leading: Navigator.canPop(context)
            ? IconButton(
                icon: Icon(
                  Icons.arrow_back_ios_new_rounded,
                  size: 20,
                  color: isDark ? Colors.white : const Color(0xFF0B1B3D),
                ),
                onPressed: () => Navigator.of(context).pop(),
              )
            : null,
        titleSpacing: Navigator.canPop(context) ? 0 : 16,
        title: Row(
          children: [
            // Avatar matching profile_screen.dart
            Stack(
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    gradient: const LinearGradient(
                      colors: [Color(0xFF1E3A8A), Color(0xFF3B82F6)],
                    ),
                    border: Border.all(color: isDark ? const Color(0xFF262626) : Colors.white, width: 2),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.08),
                        blurRadius: 6,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Center(
                    child: Text(
                      _lessorName.trim().isNotEmpty
                          ? _lessorName.trim()[0].toUpperCase()
                          : 'M',
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w900,
                        fontSize: 18,
                      ),
                    ),
                  ),
                ),
                Positioned(
                  bottom: 0,
                  right: 0,
                  child: Container(
                    width: 12,
                    height: 12,
                    decoration: BoxDecoration(
                      color: const Color(0xFF10B981), // Online green
                      shape: BoxShape.circle,
                      border: Border.all(color: isDark ? const Color(0xFF121212) : Colors.white, width: 2),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(width: 12),

            // Title & Subtitle
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _lessorName,
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                      color: isDark ? Colors.white : const Color(0xFF0B1B3D),
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 1),
                  Text(
                    _accountSubtitle,
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w500,
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
      ),
      body: SafeArea(
        child: Stack(
          children: [
            Column(
              children: [
                // Messages List / Empty State
                Expanded(
                  child: _loading && _messages.isEmpty
                      ? const Center(
                          child: CircularProgressIndicator(
                            color: AppColors.electricBlue,
                          ),
                        )
                      : _messages.isEmpty
                          ? _buildEmptyState()
                          : ListView.builder(
                              controller: _scrollCtrl,
                              padding: const EdgeInsets.symmetric(
                                horizontal: 16,
                                vertical: 16,
                              ),
                              itemCount: _messages.length,
                              itemBuilder: (context, index) {
                                final msg = _messages[index];
                                final isMe = msg['sender'] == 'tenant';
                                final body = msg['body'] ?? '';
                                final sentAt = msg['sent_at']?.toString();
                                final noticeType = msg['notice_type']?.toString();
                                final isViolation =
                                    _isViolationMessage(body, noticeType);
                                final isReport = _isReportMessage(body);
                                final isRestructure =
                                    _isRestructuringMessage(body);
                                final isExpanded =
                                    _expandedMessageIndices.contains(index);
                                final isReceipt =
                                    _isReceiptMessage(body, noticeType);

                                Widget bubbleContent;
                                if (isReceipt) {
                                  bubbleContent =
                                      _buildSpecialReceiptBubble(body, isMe);
                                } else if (isViolation) {
                                  bubbleContent =
                                      _buildSpecialViolationBubble(msg, body);
                                } else if (isReport) {
                                  bubbleContent =
                                      _buildSpecialReportBubble(body, isMe);
                                } else if (isRestructure) {
                                  bubbleContent =
                                      _buildSpecialRestructuringBubble(
                                          body, isMe);
                                } else {
                                  bubbleContent =
                                      _buildStandardMessageBubble(body, isMe, noticeType: noticeType);
                                }

                                return GestureDetector(
                                  onTap: () {
                                    setState(() {
                                      if (_expandedMessageIndices
                                          .contains(index)) {
                                        _expandedMessageIndices.remove(index);
                                      } else {
                                        _expandedMessageIndices.add(index);
                                      }
                                    });
                                  },
                                  behavior: HitTestBehavior.opaque,
                                  child: Column(
                                    crossAxisAlignment: isMe
                                        ? CrossAxisAlignment.end
                                        : CrossAxisAlignment.start,
                                    children: [
                                      bubbleContent,
                                      if (isExpanded)
                                        Padding(
                                          padding: EdgeInsets.only(
                                            top: 2,
                                            bottom: 8,
                                            left: isMe ? 0 : 8,
                                            right: isMe ? 8 : 0,
                                          ),
                                          child: Text(
                                            _formatMessengerTimestamp(
                                                sentAt, isMe),
                                            style: const TextStyle(
                                              fontSize: 11,
                                              fontWeight: FontWeight.w600,
                                              color: Color(0xFF94A3B8),
                                            ),
                                          ),
                                        ),
                                    ],
                                  ),
                                );
                              },
                            ),
                ),

                // Bottom Input Bar (iOS SMS style with + options and pop-in send button)
                Container(
                  padding: const EdgeInsets.fromLTRB(10, 10, 12, 14),
                  decoration: BoxDecoration(
                    color: isDark ? const Color(0xFF121212) : Colors.white,
                    border: Border(top: BorderSide(color: isDark ? const Color(0xFF262626) : const Color(0xFFE2E8F0))),
                  ),
                  child: Row(
                    children: [
                      // Photo Action Button (replaces plus button)
                      InkWell(
                        onTap: _pickPhoto,
                        borderRadius: BorderRadius.circular(20),
                        child: Container(
                          width: 36,
                          height: 36,
                          decoration: BoxDecoration(
                            color: isDark ? const Color(0xFF1E1E1E) : const Color(0xFFF1F5F9),
                            shape: BoxShape.circle,
                          ),
                          child: const Icon(
                            Icons.photo_library_outlined,
                            color: Color(0xFF1E6BFF),
                            size: 20,
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),

                      // Pill TextField
                      Expanded(
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 14),
                          decoration: BoxDecoration(
                            color: isDark ? const Color(0xFF1E1E1E) : Colors.white,
                            borderRadius: BorderRadius.circular(24),
                            border: Border.all(
                              color: isDark ? const Color(0xFF333333) : const Color(0xFFCBD5E1),
                              width: 1,
                            ),
                          ),
                          child: TextField(
                            controller: _msgCtrl,
                            focusNode: _focusNode,
                            minLines: 1,
                            maxLines: 4,
                            textInputAction: TextInputAction.send,
                            onSubmitted: (_) => _sendMessage(),
                            style: TextStyle(
                              fontSize: 14,
                              color: isDark ? Colors.white : const Color(0xFF0B1B3D),
                            ),
                            decoration: InputDecoration(
                              filled: true,
                              fillColor: isDark ? const Color(0xFF1E1E1E) : Colors.white,
                              isDense: true,
                              hintText: 'Message...',
                              hintStyle: TextStyle(
                                color: isDark ? const Color(0xFF64748B) : const Color(0xFF94A3B8),
                                fontSize: 13.5,
                              ),
                              border: InputBorder.none,
                              enabledBorder: InputBorder.none,
                              focusedBorder: InputBorder.none,
                              disabledBorder: InputBorder.none,
                              contentPadding:
                                  const EdgeInsets.symmetric(vertical: 10),
                            ),
                          ),
                        ),
                      ),

                      // Send button that appears ONLY when typing
                      AnimatedSwitcher(
                        duration: const Duration(milliseconds: 180),
                        transitionBuilder: (child, anim) =>
                            ScaleTransition(scale: anim, child: child),
                        child: _hasText
                            ? Padding(
                                key: const ValueKey('send_active'),
                                padding: const EdgeInsets.only(left: 8),
                                child: InkWell(
                                  borderRadius: BorderRadius.circular(20),
                                  onTap: _sending ? null : _sendMessage,
                                  child: Container(
                                    width: 36,
                                    height: 36,
                                    decoration: const BoxDecoration(
                                      color: Color(0xFF1E6BFF),
                                      shape: BoxShape.circle,
                                    ),
                                    child: Center(
                                      child: _sending
                                          ? const SizedBox(
                                              width: 15,
                                              height: 15,
                                              child: CircularProgressIndicator(
                                                strokeWidth: 2,
                                                color: Colors.white,
                                              ),
                                            )
                                          : const Icon(
                                              Icons.arrow_upward_rounded,
                                              color: Colors.white,
                                              size: 20,
                                            ),
                                    ),
                                  ),
                                ),
                              )
                            : const SizedBox.shrink(
                                key: ValueKey('send_hidden'),
                              ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEmptyState() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Center(
      child: SingleChildScrollView(
        physics: const BouncingScrollPhysics(),
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 72,
              height: 72,
              decoration: BoxDecoration(
                color: isDark ? const Color(0xFF1E3A8A).withValues(alpha: 0.25) : AppColors.accentSoft,
                borderRadius: BorderRadius.circular(22),
              ),
              child: const Icon(
                Icons.chat_bubble_outline_rounded,
                size: 34,
                color: AppColors.electricBlue,
              ),
            ),
            const SizedBox(height: 18),
            Text(
              'Direct Management Chat',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w800,
                color: isDark ? Colors.white : AppColors.ink900,
                letterSpacing: -0.3,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'No messages yet. Send a direct inquiry or message below to start communicating with your lessor.',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 13,
                color: isDark ? const Color(0xFF94A3B8) : AppColors.ink500,
                height: 1.45,
              ),
            ),
            const SizedBox(height: 20),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              alignment: WrapAlignment.center,
              children: [
                _buildQuickPromptChip('👋 Hello! Just checking in.'),
                _buildQuickPromptChip('📄 Lease contract question'),
                _buildQuickPromptChip('🔧 Maintenance assistance'),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildQuickPromptChip(String prompt) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return ActionChip(
      label: Text(
        prompt,
        style: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: isDark ? const Color(0xFF60A5FA) : AppColors.primary,
        ),
      ),
      backgroundColor: isDark ? const Color(0xFF161616) : Colors.white,
      side: BorderSide(color: isDark ? const Color(0xFF333333) : const Color(0xFFE2E8F0)),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      onPressed: () {
        _msgCtrl.text = prompt;
        _sendMessage();
      },
    );
  }

  // ================= SPECIAL DIGITAL PAYMENT RECEIPT BUBBLE =================
  Widget _buildSpecialReceiptBubble(String body, bool isMe) {
    final isVerified = body.contains('VERIFIED') || body.contains('POSTED');
    final primaryColor = isVerified ? const Color(0xFF059669) : const Color(0xFF1E6BFF);
    final headerBgColor = isVerified ? const Color(0xFFECFDF5) : const Color(0xFFEFF6FF);
    final borderColor = isVerified ? const Color(0xFFA7F3D0) : const Color(0xFFBFDBFE);

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      constraints: BoxConstraints(
        maxWidth: MediaQuery.of(context).size.width * 0.85,
      ),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: borderColor, width: 1.2),
        boxShadow: [
          BoxShadow(
            color: primaryColor.withValues(alpha: 0.08),
            blurRadius: 14,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              color: headerBgColor,
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(6),
                    decoration: BoxDecoration(
                      color: primaryColor.withValues(alpha: 0.14),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Icon(Icons.receipt_long_rounded, size: 16, color: primaryColor),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      isVerified ? 'VERIFIED PAYMENT RECEIPT' : 'DIGITAL PAYMENT RECEIPT',
                      style: TextStyle(
                        fontSize: 11.5,
                        fontWeight: FontWeight.w900,
                        color: primaryColor,
                        letterSpacing: 0.2,
                      ),
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                    decoration: BoxDecoration(
                      color: isVerified ? const Color(0xFF10B981) : const Color(0xFF3B82F6),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      isVerified ? 'VERIFIED' : 'PENDING',
                      style: const TextStyle(
                        fontSize: 9.5,
                        fontWeight: FontWeight.w800,
                        color: Colors.white,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(14),
              child: Text(
                body,
                style: const TextStyle(
                  fontSize: 12.5,
                  height: 1.45,
                  color: Color(0xFF0F172A),
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ================= SPECIAL INTERACTIVE CITATION / VIOLATION BUBBLE =================
  Widget _buildSpecialViolationBubble(Map<String, dynamic> msg, String body) {
    final vData = msg['violation_data'] as Map<String, dynamic>?;
    int strike = 1;
    if (vData != null && vData['strike'] != null) {
      strike = vData['strike'] is int
          ? vData['strike'] as int
          : int.tryParse(vData['strike'].toString()) ?? 1;
    } else if (body.contains('STRIKE 3') || body.contains('Strike 3')) {
      strike = 3;
    } else if (body.contains('STRIKE 2') || body.contains('Strike 2')) {
      strike = 2;
    }

    String category = 'Lease Violation';
    if (vData != null && vData['category'] != null) {
      category = vData['category'].toString();
    } else if (body.contains('Category:')) {
      final catPart = body.split('Category:').last.split('\n').first.trim();
      if (catPart.isNotEmpty) category = catPart;
    }

    final isEviction = strike >= 3;
    final isSecond = strike == 2;

    final primaryColor = isEviction
        ? const Color(0xFFDC2626) // Deep Red
        : (isSecond ? const Color(0xFFD97706) : const Color(0xFF1E6BFF)); // Amber vs Electric Blue

    final headerBgColor = isEviction
        ? const Color(0xFFFEF2F2)
        : (isSecond ? const Color(0xFFFFFBEB) : const Color(0xFFEFF6FF));

    final borderColor = isEviction
        ? const Color(0xFFFCA5A5)
        : (isSecond ? const Color(0xFFFCD34D) : const Color(0xFFBFDBFE));

    final badgeBg = isEviction
        ? const Color(0xFFEF4444)
        : (isSecond ? const Color(0xFFF59E0B) : const Color(0xFF2563EB));

    final headerTitle = isEviction
        ? 'Final Notice • Eviction Proceeding'
        : (isSecond
            ? '2nd Notice • ₱500 Penalty Injected'
            : 'Official Notice • Formal Warning');

    final penaltyAmount =
        isEviction ? '₱1,500.00' : (isSecond ? '₱500.00' : '₱0.00');

    return Container(
      margin: const EdgeInsets.symmetric(vertical: 10),
      child: Center(
        child: Container(
          width: double.infinity,
          constraints: const BoxConstraints(maxWidth: 420),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: borderColor, width: 1.2),
            boxShadow: [
              BoxShadow(
                color: primaryColor.withValues(alpha: 0.08),
                blurRadius: 16,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Top Banner
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 12,
                ),
                decoration: BoxDecoration(
                  color: headerBgColor,
                  borderRadius: const BorderRadius.only(
                    topLeft: Radius.circular(19),
                    topRight: Radius.circular(19),
                  ),
                ),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(6),
                      decoration: BoxDecoration(
                        color: badgeBg,
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        isEviction
                            ? Icons.gavel_rounded
                            : (isSecond
                                ? Icons.warning_amber_rounded
                                : Icons.shield_outlined),
                        color: Colors.white,
                        size: 14,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        headerTitle,
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                          color: primaryColor,
                          letterSpacing: -0.2,
                        ),
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 3,
                      ),
                      decoration: BoxDecoration(
                        color: badgeBg,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        'Strike $strike of 3',
                        style: const TextStyle(
                          fontSize: 10.5,
                          fontWeight: FontWeight.w800,
                          color: Colors.white,
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              // Card Body
              Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF1F5F9),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Icon(
                            category.toLowerCase().contains('rent') ||
                                    category.toLowerCase().contains('payment')
                                ? Icons.receipt_long_rounded
                                : Icons.warning_rounded,
                            size: 18,
                            color: primaryColor,
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'Offense Classification',
                                style: TextStyle(
                                  fontSize: 10.5,
                                  fontWeight: FontWeight.w600,
                                  color: Color(0xFF64748B),
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                category,
                                style: const TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w800,
                                  color: Color(0xFF0B1B3D),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),

                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF8FAFC),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text(
                                'Contractual Disciplinary Impact',
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w700,
                                  color: Color(0xFF334155),
                                ),
                              ),
                              if (strike > 1)
                                Text(
                                  penaltyAmount,
                                  style: TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.w900,
                                    color: primaryColor,
                                  ),
                                ),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text(
                            isEviction
                                ? 'Three cumulative strikes recorded. Commercial premises possession is revoked subject to immediate formal eviction under lease default terms.'
                                : (isSecond
                                    ? 'Second infraction recorded against this lease. An automated ₱500 penalty surcharge has been billed to your statement of account.'
                                    : 'First formal warning citation. Acknowledgment receipt is required to verify notice delivery. Further violations escalate penalties.'),
                            style: const TextStyle(
                              fontSize: 11.5,
                              color: Color(0xFF64748B),
                              height: 1.4,
                            ),
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 14),
                    const Divider(height: 1, color: Color(0xFFF1F5F9)),
                    const SizedBox(height: 10),

                    // Actions row
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        InkWell(
                          onTap: () {
                            Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) =>
                                    ViolationsScreen(lease: widget.lease),
                              ),
                            );
                          },
                          child: Row(
                            children: const [
                              Icon(
                                Icons.history_rounded,
                                size: 14,
                                color: Color(0xFF64748B),
                              ),
                              SizedBox(width: 4),
                              Text(
                                'All Records',
                                style: TextStyle(
                                  fontSize: 11.5,
                                  fontWeight: FontWeight.w700,
                                  color: Color(0xFF475569),
                                ),
                              ),
                            ],
                          ),
                        ),
                        InkWell(
                          onTap: () async {
                            final violationMap = vData ?? {
                              'id': 100 + strike,
                              'category': category,
                              'strike': strike,
                              'penalty_amount': strike == 2
                                  ? 500.0
                                  : (strike >= 3 ? 1500.0 : 0.0),
                              'issued_at': msg['sent_at'] ?? 'Recently',
                              'acknowledged': 1,
                            };
                            await ViolationNoticeSheet.show(
                              context,
                              violation: violationMap,
                              isGated: false,
                            );
                            _loadData(silent: true);
                          },
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 12,
                              vertical: 6,
                            ),
                            decoration: BoxDecoration(
                              color: primaryColor,
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: Row(
                              children: const [
                                Text(
                                  'View Citation',
                                  style: TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.w800,
                                    color: Colors.white,
                                  ),
                                ),
                                SizedBox(width: 4),
                                Icon(
                                  Icons.arrow_forward_rounded,
                                  size: 13,
                                  color: Colors.white,
                                ),
                              ],
                            ),
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
      ),
    );
  }

  // ================= SPECIAL INTERACTIVE REPORT BUBBLE =================
  IconData _getReportCategoryIcon(String category) {
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

  void _openTicketDetail(Map<String, dynamic> ticket) {
    Navigator.of(context)
        .push(
          MaterialPageRoute(
            builder: (_) => TicketDetailScreen(ticket: ticket),
          ),
        )
        .then((_) => _loadData());
  }

  Widget _buildSpecialReportBubble(String body, bool isMe) {
    bool isCompleted = body.toLowerCase().contains('completed') ||
        _tickets.any((t) => (t['stage'] as num? ?? 0) >= 3);
    bool isApproved = !isCompleted &&
        (body.contains('Approved') ||
            body.contains('Scheduled') ||
            _tickets.any((t) => (t['stage'] as num? ?? 0) >= 2));

    String ticketNumber = '';
    if (body.contains('#SL-')) {
      ticketNumber = body.substring(
        body.indexOf('#SL-'),
        (body.indexOf('#SL-') + 9).clamp(0, body.length),
      );
    } else if (body.contains('#MT-')) {
      ticketNumber = body.substring(
        body.indexOf('#MT-'),
        (body.indexOf('#MT-') + 9).clamp(0, body.length),
      );
    }

    String description = '';
    if (body.contains('Description:')) {
      final descPart = body.split('Description:').last.split('\n').first.trim();
      if (descPart.isNotEmpty) description = descPart;
    }

    String category = '';
    if (body.contains('Category:')) {
      final catPart = body.split('Category:').last.split('\n').first.trim();
      if (catPart.isNotEmpty) category = catPart;
    }

    // Match or construct dynamic ticket object
    int? parsedId;
    final numMatch = RegExp(r'\d+').firstMatch(ticketNumber);
    if (numMatch != null) {
      parsedId = int.tryParse(numMatch.group(0)!);
    }

    Map<String, dynamic>? matchingTicket;
    if (parsedId != null && _tickets.isNotEmpty) {
      for (final t in _tickets) {
        final tid = t['id'] is int ? t['id'] : int.tryParse(t['id']?.toString() ?? '');
        if (tid != null && tid == parsedId) {
          matchingTicket = Map<String, dynamic>.from(t as Map);
          break;
        }
      }
    }

    if (matchingTicket == null && _tickets.isNotEmpty) {
      for (final t in _tickets) {
        final cat = t['category']?.toString().toLowerCase() ?? '';
        if (cat.isNotEmpty && category.toLowerCase().contains(cat)) {
          matchingTicket = Map<String, dynamic>.from(t as Map);
          break;
        }
      }
      matchingTicket ??= Map<String, dynamic>.from(_tickets.first as Map);
    }

    if (matchingTicket != null) {
      if (ticketNumber.isEmpty && matchingTicket['id'] != null) {
        ticketNumber = '#MT-${matchingTicket['id']}';
      }
      if (category.isEmpty && matchingTicket['category'] != null) {
        category = matchingTicket['category'].toString();
      }
      if (description.isEmpty &&
          (matchingTicket['description'] ?? matchingTicket['title']) != null) {
        description =
            (matchingTicket['description'] ?? matchingTicket['title']).toString();
      }
      final stage = (matchingTicket['stage'] as num?)?.toInt() ?? 0;
      if (stage >= 4) {
        isCompleted = true;
        isApproved = false;
      } else if (stage >= 2) {
        isApproved = true;
      }
    }

    if (category.isEmpty) category = 'Maintenance';
    if (description.isEmpty) description = body;

    matchingTicket ??= {
      'id': parsedId ?? 0,
      'category': category.split('•').first.trim(),
      'title': category,
      'description': description,
      'stage': isCompleted ? 4 : (isApproved ? 2 : 0),
      'priority': 'medium',
      'created_at': DateTime.now().toIso8601String(),
    };

    final catIcon = _getReportCategoryIcon(category);

    final primaryColor = isCompleted
        ? const Color(0xFF10B981) // Emerald Green
        : (isApproved
            ? const Color(0xFF1E6BFF) // Electric Blue
            : const Color(0xFFD97706)); // Amber / Warm Orange

    final headerBgColor = isCompleted
        ? const Color(0xFFECFDF5)
        : (isApproved
            ? const Color(0xFFEFF6FF)
            : const Color(0xFFFFFBEB));

    final borderColor = isCompleted
        ? const Color(0xFFA7F3D0)
        : (isApproved
            ? const Color(0xFFBFDBFE)
            : const Color(0xFFFCD34D));

    final badgeBg = isCompleted
        ? const Color(0xFF10B981)
        : (isApproved
            ? const Color(0xFF2563EB)
            : const Color(0xFFF59E0B));

    final headerTitle = isCompleted
        ? 'Maintenance Resolved • Work Signed Off'
        : (isApproved
            ? 'Report Approved • Maintenance Scheduled'
            : 'Maintenance Request • Pending Review');

    final statusPillText = isCompleted
        ? 'Completed'
        : (isApproved ? 'Scheduled' : 'Pending Review');

    final headerIcon = isCompleted
        ? Icons.check_rounded
        : (isApproved ? Icons.event_available_rounded : Icons.access_time_rounded);

    final scheduleDate = isCompleted
        ? 'Signed Off: Today'
        : (isApproved ? 'Scheduled: In Calendar' : 'Expected: 24–48 hrs');

    return Container(
      margin: const EdgeInsets.symmetric(vertical: 10),
      child: Center(
        child: Container(
              width: double.infinity,
              constraints: const BoxConstraints(maxWidth: 420),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: borderColor, width: 1.2),
                boxShadow: [
                  BoxShadow(
                    color: primaryColor.withValues(alpha: 0.08),
                    blurRadius: 16,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Top Banner
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 12,
                    ),
                    decoration: BoxDecoration(
                      color: headerBgColor,
                      borderRadius: const BorderRadius.only(
                        topLeft: Radius.circular(19),
                        topRight: Radius.circular(19),
                      ),
                    ),
                    child: Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(6),
                          decoration: BoxDecoration(
                            color: badgeBg,
                            shape: BoxShape.circle,
                          ),
                          child: Icon(
                            headerIcon,
                            color: Colors.white,
                            size: 14,
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            headerTitle,
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w800,
                              color: primaryColor,
                              letterSpacing: -0.2,
                            ),
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 3,
                          ),
                          decoration: BoxDecoration(
                            color: badgeBg,
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Text(
                            statusPillText,
                            style: const TextStyle(
                              fontSize: 10.5,
                              fontWeight: FontWeight.w800,
                              color: Colors.white,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),

              // Card Body
              Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF1F5F9),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Icon(
                            catIcon,
                            size: 18,
                            color: primaryColor,
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'Issue Classification',
                                style: TextStyle(
                                  fontSize: 10.5,
                                  fontWeight: FontWeight.w600,
                                  color: Color(0xFF64748B),
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                category,
                                style: const TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w800,
                                  color: Color(0xFF0B1B3D),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),

                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF8FAFC),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text(
                                'Maintenance Scope & Description',
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w700,
                                  color: Color(0xFF334155),
                                ),
                              ),
                              Text(
                                ticketNumber,
                                style: const TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w800,
                                  color: Color(0xFF64748B),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text(
                            description,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 11.5,
                              color: Color(0xFF64748B),
                              height: 1.4,
                            ),
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 14),
                    const Divider(height: 1, color: Color(0xFFF1F5F9)),
                    const SizedBox(height: 10),

                    // Actions row
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: [
                            Icon(
                              Icons.calendar_month_rounded,
                              size: 14,
                              color: primaryColor,
                            ),
                            const SizedBox(width: 5),
                            Text(
                              scheduleDate,
                              style: const TextStyle(
                                fontSize: 11.5,
                                fontWeight: FontWeight.w700,
                                color: Color(0xFF475569),
                              ),
                            ),
                          ],
                        ),
                        InkWell(
                          borderRadius: BorderRadius.circular(10),
                          onTap: () => _openTicketDetail(matchingTicket!),
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 12,
                              vertical: 6,
                            ),
                            decoration: BoxDecoration(
                              color: primaryColor,
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: Row(
                              children: [
                                Text(
                                  isCompleted ? 'View Ticket' : 'View Progress',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.w800,
                                    color: Colors.white,
                                  ),
                                ),
                                const SizedBox(width: 4),
                                const Icon(
                                  Icons.arrow_forward_rounded,
                                  size: 13,
                                  color: Colors.white,
                                ),
                              ],
                            ),
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
      ),
    );
  }

  Widget _buildSpecialRestructuringBubble(String body, bool isMe) {
    final isRevoked = body.contains('Revoked') || body.contains('canceled');

    String plan = 'Payment Plan';
    if (body.contains('Plan:')) {
      plan = body.split('Plan:').last.split('\n').first.trim();
    }

    String reason = 'Cash flow delay';
    if (body.contains('Reason:')) {
      final reasonPart = body.split('Reason:').last.split('\n').first.trim();
      if (reasonPart.isNotEmpty) reason = reasonPart;
    }

    String? note;
    if (body.contains('Note:')) {
      final notePart = body
          .split('Note:')
          .last
          .split('\n')
          .first
          .trim()
          .replaceAll('"', '');
      if (notePart.isNotEmpty) note = notePart;
    }

    List<String> scheduleItems = [];
    if (body.contains('Schedule:')) {
      final afterSched = body.split('Schedule:').last;
      final lines = afterSched.split('\n');
      for (final l in lines) {
        final trimmed = l.trim();
        if (trimmed.startsWith('•') || trimmed.startsWith('-')) {
          scheduleItems.add(trimmed);
        } else if (trimmed.startsWith('Status:')) {
          break;
        }
      }
    }

    return Container(
      margin: const EdgeInsets.symmetric(vertical: 10),
      child: Center(
        child: Container(
          width: double.infinity,
          constraints: const BoxConstraints(maxWidth: 420),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(
              color: isRevoked
                  ? const Color(0xFFFCA5A5)
                  : const Color(0xFF1E6BFF).withValues(alpha: 0.3),
              width: 1.2,
            ),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF0B1B3D).withValues(alpha: 0.06),
                blurRadius: 16,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Top Banner
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 12,
                ),
                decoration: BoxDecoration(
                  color: isRevoked
                      ? const Color(0xFFFEF2F2)
                      : const Color(0xFFEFF6FF),
                  borderRadius: const BorderRadius.only(
                    topLeft: Radius.circular(20),
                    topRight: Radius.circular(20),
                  ),
                ),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(6),
                      decoration: BoxDecoration(
                        color: isRevoked
                            ? const Color(0xFFDC2626)
                            : const Color(0xFF1E6BFF),
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        isRevoked
                            ? Icons.history_toggle_off_rounded
                            : Icons.handshake_rounded,
                        color: Colors.white,
                        size: 14,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        isRevoked
                            ? 'Restructuring Request Revoked'
                            : 'Payment Restructuring Proposal',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                          color: isRevoked
                              ? const Color(0xFF991B1B)
                              : const Color(0xFF1E3A8A),
                        ),
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 7,
                        vertical: 3,
                      ),
                      decoration: BoxDecoration(
                        color: isRevoked
                            ? const Color(0xFFFEE2E2)
                            : const Color(0xFFDBEAFE),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        isRevoked ? 'REVOKED' : '48H COOLING-OFF',
                        style: TextStyle(
                          fontSize: 9.5,
                          fontWeight: FontWeight.w800,
                          color: isRevoked
                              ? const Color(0xFFDC2626)
                              : const Color(0xFF1E40AF),
                          letterSpacing: 0.3,
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              // Content Body
              Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (!isRevoked) ...[
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Expanded(
                            child: Text(
                              plan,
                              style: const TextStyle(
                                fontSize: 14.5,
                                fontWeight: FontWeight.w800,
                                color: Color(0xFF0B1B3D),
                              ),
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 3,
                            ),
                            decoration: BoxDecoration(
                              color: const Color(0xFFF1F5F9),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: const Text(
                              'Concession Request',
                              style: TextStyle(
                                fontSize: 10.5,
                                fontWeight: FontWeight.w700,
                                color: Color(0xFF64748B),
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Icon(
                            Icons.assignment_outlined,
                            size: 14,
                            color: Color(0xFF64748B),
                          ),
                          const SizedBox(width: 6),
                          Expanded(
                            child: Text(
                              'Reason: $reason',
                              style: const TextStyle(
                                fontSize: 12,
                                color: Color(0xFF475569),
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ],
                      ),
                      if (note != null && note.isNotEmpty) ...[
                        const SizedBox(height: 8),
                        Container(
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF8FAFC),
                            borderRadius: BorderRadius.circular(10),
                            border: Border.all(color: const Color(0xFFE2E8F0)),
                          ),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Icon(
                                Icons.format_quote_rounded,
                                size: 14,
                                color: Color(0xFF1E6BFF),
                              ),
                              const SizedBox(width: 6),
                              Expanded(
                                child: Text(
                                  '"$note"',
                                  style: const TextStyle(
                                    fontSize: 11.5,
                                    fontStyle: FontStyle.italic,
                                    color: Color(0xFF1E293B),
                                    fontWeight: FontWeight.w500,
                                    height: 1.35,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                      if (scheduleItems.isNotEmpty) ...[
                        const SizedBox(height: 10),
                        Container(
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF1F5F9),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: scheduleItems.map((item) {
                              return Padding(
                                padding: const EdgeInsets.symmetric(
                                  vertical: 2,
                                ),
                                child: Text(
                                  item,
                                  style: const TextStyle(
                                    fontSize: 11.5,
                                    fontWeight: FontWeight.w700,
                                    color: Color(0xFF0B1B3D),
                                  ),
                                ),
                              );
                            }).toList(),
                          ),
                        ),
                      ],
                    ] else ...[
                      const Text(
                        'This restructuring request has been cancelled by the tenant within the 48-hour cooling-off window. The regular lease payment schedule remains active.',
                        style: TextStyle(
                          fontSize: 12,
                          color: Color(0xFF64748B),
                          height: 1.4,
                        ),
                      ),
                    ],

                    const SizedBox(height: 12),
                    const Divider(height: 1, color: Color(0xFFF1F5F9)),
                    const SizedBox(height: 10),

                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: const [
                            Icon(
                              Icons.shield_outlined,
                              size: 13,
                              color: Color(0xFF10B981),
                            ),
                            SizedBox(width: 4),
                            Text(
                              'Audited Proposal',
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w600,
                                color: Color(0xFF334155),
                              ),
                            ),
                          ],
                        ),
                        InkWell(
                          onTap: () {
                            if (widget.onNavigateTab != null) {
                              widget.onNavigateTab!(1); // Go to Schedule tab
                            }
                          },
                          child: Row(
                            children: const [
                              Text(
                                'View in Schedule',
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w800,
                                  color: Color(0xFF1E6BFF),
                                ),
                              ),
                              SizedBox(width: 3),
                              Icon(
                                Icons.arrow_forward_rounded,
                                size: 13,
                                color: Color(0xFF1E6BFF),
                              ),
                            ],
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
      ),
    );
  }

  // Standard chat bubble (iOS/Messenger style with Pwestora colors)
  Widget _buildStandardMessageBubble(String body, bool isMe, {String? noticeType}) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final isContractNotice = noticeType == 'Contract Notice' ||
        noticeType == 'Contract Revision Notice' ||
        body.contains('OFFICIAL NOTICE: LEASE CONTRACT FINALIZED') ||
        body.contains('NOTICE: CONTRACT REVISION');

    return Align(
      alignment: isMe ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 4),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        constraints: BoxConstraints(
          maxWidth: MediaQuery.of(context).size.width * 0.75,
        ),
        decoration: BoxDecoration(
          color: isMe
              ? const Color(0xFF1E6BFF)
              : (isDark ? const Color(0xFF1C1C1E) : const Color(0xFFE9EEF5)),
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(19),
            topRight: const Radius.circular(19),
            bottomLeft: Radius.circular(isMe ? 19 : 5),
            bottomRight: Radius.circular(isMe ? 5 : 19),
          ),
          border: isMe
              ? null
              : Border.all(
                  color: isDark ? const Color(0xFF2C2C2E) : const Color(0xFFE2E8F0),
                  width: 0.6,
                ),
        ),
        child: Column(
          crossAxisAlignment: isMe ? CrossAxisAlignment.end : CrossAxisAlignment.start,
          children: [
            Text(
              body,
              style: TextStyle(
                fontSize: 14,
                height: 1.35,
                color: isMe
                    ? Colors.white
                    : (isDark ? Colors.white : const Color(0xFF0B1B3D)),
                fontWeight: FontWeight.w500,
              ),
            ),
            if (!isMe && isContractNotice) ...[
              const SizedBox(height: 10),
              InkWell(
                onTap: () {
                  Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => LeaseContractScreen(
                        lease: {'tenant_id': _currentTenantId},
                        onLeaseMayHaveChanged: () => _loadData(silent: true),
                      ),
                    ),
                  );
                },
                borderRadius: BorderRadius.circular(8),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  decoration: BoxDecoration(
                    color: isDark ? const Color(0xFF2A2A2E) : Colors.white,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(
                      color: isDark ? const Color(0xFF3E3E42) : const Color(0xFFCBD5E1),
                      width: 0.8,
                    ),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        Icons.description_outlined,
                        size: 15,
                        color: isDark ? const Color(0xFF60A5FA) : const Color(0xFF1E6BFF),
                      ),
                      const SizedBox(width: 6),
                      Text(
                        'Open Lease Agreement',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: isDark ? const Color(0xFF60A5FA) : const Color(0xFF1E6BFF),
                        ),
                      ),
                      const SizedBox(width: 4),
                      Icon(
                        Icons.arrow_forward_rounded,
                        size: 13,
                        color: isDark ? const Color(0xFF60A5FA) : const Color(0xFF1E6BFF),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  // Messenger-style timestamp formatter
  String _formatMessengerTimestamp(String? rawSentAt, bool isMe) {
    if (rawSentAt == null || rawSentAt.isEmpty) {
      return isMe ? 'Sent just now • Delivered' : 'Just now';
    }
    DateTime? dt;
    try {
      dt = DateTime.parse(rawSentAt);
    } catch (_) {
      dt = DateTime.now();
    }
    final localDt = dt.toLocal();
    final now = DateTime.now();

    final isToday =
        localDt.year == now.year &&
        localDt.month == now.month &&
        localDt.day == now.day;

    final hour = localDt.hour > 12
        ? localDt.hour - 12
        : (localDt.hour == 0 ? 12 : localDt.hour);
    final minute = localDt.minute.toString().padLeft(2, '0');
    final ampm = localDt.hour >= 12 ? 'PM' : 'AM';
    final timeStr = '$hour:$minute $ampm';

    final deliveredSuffix = isMe ? ' • Delivered' : '';

    if (isToday) {
      return '$timeStr$deliveredSuffix';
    }

    final diffDays = now.difference(localDt).inDays;
    const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    final dayName = days[localDt.weekday - 1];

    if (diffDays <= 6) {
      return '$dayName at $timeStr$deliveredSuffix';
    }

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
    final monthStr = months[localDt.month - 1];
    return '$monthStr ${localDt.day} AT $timeStr$deliveredSuffix';
  }
}
