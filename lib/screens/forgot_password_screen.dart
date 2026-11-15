import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../theme.dart';
import '../services/api_service.dart';

class ForgotPasswordScreen extends StatefulWidget {
  final String? initialPhone;

  const ForgotPasswordScreen({super.key, this.initialPhone});

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  // Step 1 = Enter Phone, Step 2 = Enter SMS OTP, Step 3 = Set New Password
  int _currentStep = 1;

  final _phoneCtrl = TextEditingController();
  final _otpCtrl = TextEditingController();
  final _newPasswordCtrl = TextEditingController();
  final _confirmPasswordCtrl = TextEditingController();

  final _phoneFormKey = GlobalKey<FormState>();
  final _passwordFormKey = GlobalKey<FormState>();
  final _otpFocusNode = FocusNode();

  bool _loading = false;
  String? _error;
  String? _successNotice;

  bool _obscureNew = true;
  bool _obscureConfirm = true;

  // Resend OTP countdown
  Timer? _resendTimer;
  int _secondsRemaining = 60;

  @override
  void initState() {
    super.initState();
    if (widget.initialPhone != null && widget.initialPhone!.trim().isNotEmpty) {
      _phoneCtrl.text = widget.initialPhone!.trim();
    }

    _otpCtrl.addListener(() {
      if (mounted) setState(() {});
    });
  }

  @override
  void dispose() {
    _resendTimer?.cancel();
    _phoneCtrl.dispose();
    _otpCtrl.dispose();
    _newPasswordCtrl.dispose();
    _confirmPasswordCtrl.dispose();
    _otpFocusNode.dispose();
    super.dispose();
  }

  void _startResendTimer() {
    _resendTimer?.cancel();
    setState(() => _secondsRemaining = 60);
    _resendTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_secondsRemaining > 0) {
        if (mounted) setState(() => _secondsRemaining--);
      } else {
        timer.cancel();
      }
    });
  }

  void _focusOtpInput() {
    if (!mounted) return;
    if (_otpFocusNode.hasFocus) {
      _otpFocusNode.unfocus();
      Future.delayed(const Duration(milliseconds: 50), () {
        if (mounted) {
          _otpFocusNode.requestFocus();
          SystemChannels.textInput.invokeMethod('TextInput.show');
        }
      });
    } else {
      _otpFocusNode.requestFocus();
      SystemChannels.textInput.invokeMethod('TextInput.show');
    }
  }

  // =========================================================================
  // STEP 1: REQUEST OTP
  // =========================================================================
  Future<void> _requestOtp() async {
    if (!_phoneFormKey.currentState!.validate()) return;
    setState(() {
      _loading = true;
      _error = null;
      _successNotice = null;
    });

    try {
      final phone = _phoneCtrl.text.replaceAll(RegExp(r'[^0-9]'), '');
      await ApiService.sendForgotPasswordOtp(phone: phone);

      if (!mounted) return;
      setState(() {
        _loading = false;
        _currentStep = 2;
        _otpCtrl.clear();
      });
      _startResendTimer();
      Future.delayed(const Duration(milliseconds: 250), _focusOtpInput);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = e.toString().replaceAll('Exception: ', '');
      });
    }
  }

  // =========================================================================
  // RESEND OTP
  // =========================================================================
  Future<void> _resendOtp() async {
    if (_secondsRemaining > 0 || _loading) return;
    setState(() {
      _loading = true;
      _error = null;
      _successNotice = null;
    });

    try {
      final phone = _phoneCtrl.text.replaceAll(RegExp(r'[^0-9]'), '');
      await ApiService.sendForgotPasswordOtp(phone: phone);

      if (!mounted) return;
      setState(() {
        _loading = false;
        _successNotice = 'A new 6-digit OTP code has been sent.';
      });
      _startResendTimer();
      _focusOtpInput();
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = e.toString().replaceAll('Exception: ', '');
      });
    }
  }

  // =========================================================================
  // STEP 2: VERIFY OTP
  // =========================================================================
  Future<void> _verifyOtp() async {
    final otp = _otpCtrl.text.trim();
    if (otp.length != 6) {
      setState(() => _error = 'Please enter the full 6-digit code.');
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
      _successNotice = null;
    });

    try {
      final phone = _phoneCtrl.text.replaceAll(RegExp(r'[^0-9]'), '');
      await ApiService.verifyForgotPasswordOtp(phone: phone, otp: otp);

      if (!mounted) return;
      setState(() {
        _loading = false;
        _currentStep = 3;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = e.toString().replaceAll('Exception: ', '');
      });
      _focusOtpInput();
    }
  }

  // =========================================================================
  // STEP 3: RESET PASSWORD
  // =========================================================================
  Future<void> _submitNewPassword() async {
    if (!_passwordFormKey.currentState!.validate()) return;

    final newPass = _newPasswordCtrl.text;
    final confirmPass = _confirmPasswordCtrl.text;
    if (newPass != confirmPass) {
      setState(() => _error = 'Passwords do not match.');
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
      _successNotice = null;
    });

    try {
      final phone = _phoneCtrl.text.replaceAll(RegExp(r'[^0-9]'), '');
      final otp = _otpCtrl.text.trim();
      final msg = await ApiService.resetPasswordWithOtp(
        phone: phone,
        otp: otp,
        newPassword: newPass,
      );

      if (!mounted) return;
      setState(() => _loading = false);

      // Show success bottom sheet or snackbar
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: const Color(0xFF10B981),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          content: Row(
            children: [
              const Icon(Icons.check_circle_rounded, color: Colors.white, size: 20),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  msg,
                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 13),
                ),
              ),
            ],
          ),
        ),
      );

      Navigator.of(context).pop(true);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = e.toString().replaceAll('Exception: ', '');
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_currentStep == 2) {
      return _buildOtpScreenView();
    }
    if (_currentStep == 3) {
      return _buildNewPasswordView();
    }
    return _buildPhoneInputView();
  }

  // =========================================================================
  // VIEW: STEP 1 - PHONE INPUT
  // =========================================================================
  Widget _buildPhoneInputView() {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: const AppBackButton(),
      ),
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: SingleChildScrollView(
              physics: const BouncingScrollPhysics(),
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
              child: Form(
                key: _phoneFormKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Brand Logo
                    Center(
                      child: Container(
                        width: 76,
                        height: 76,
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          shape: BoxShape.circle,
                          border: Border.all(
                            color: AppColors.border.withValues(alpha: 0.8),
                            width: 1.5,
                          ),
                          boxShadow: [
                            BoxShadow(
                              color: AppColors.primary.withValues(alpha: 0.08),
                              blurRadius: 20,
                              offset: const Offset(0, 6),
                            ),
                          ],
                        ),
                        child: Image.asset(
                          Theme.of(context).brightness == Brightness.dark
                              ? 'img/invert.png'
                              : 'img/logo.png',
                          fit: BoxFit.contain,
                          errorBuilder: (context, error, stackTrace) => Container(
                            alignment: Alignment.center,
                            child: const Text('P', style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.bold, fontSize: 24)),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 24),

                    const Center(
                      child: Text(
                        'Forgot Password',
                        style: TextStyle(
                          fontSize: 24,
                          fontWeight: FontWeight.w800,
                          color: AppColors.ink900,
                          letterSpacing: -0.4,
                        ),
                      ),
                    ),
                    const SizedBox(height: 10),
                    const Center(
                      child: Text(
                        'Enter your registered Philippine mobile number. We will send you a 6-digit SMS OTP code to reset your password.',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 14,
                          color: AppColors.ink500,
                          height: 1.45,
                        ),
                      ),
                    ),
                    const SizedBox(height: 28),

                    if (_error != null) ...[
                      Container(
                        width: double.infinity,
                        margin: const EdgeInsets.only(bottom: 20),
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                        decoration: BoxDecoration(
                          color: AppColors.errorSoft,
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: AppColors.error.withValues(alpha: 0.3)),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.error_outline, size: 18, color: AppColors.error),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                _error!,
                                style: const TextStyle(color: AppColors.error, fontSize: 12.5, fontWeight: FontWeight.w600),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],

                    const Text(
                      'Mobile Number',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: AppColors.ink900,
                      ),
                    ),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _phoneCtrl,
                      keyboardType: TextInputType.phone,
                      autofocus: true,
                      decoration: InputDecoration(
                        hintText: '0917 123 4567',
                        prefixIcon: const Icon(Icons.phone_android_rounded, color: AppColors.ink500, size: 20),
                        helperText: 'Must be an 11-digit Philippine mobile number',
                        helperStyle: const TextStyle(fontSize: 11.5, color: Color(0xFF64748B)),
                        filled: true,
                        fillColor: const Color(0xFFF8FAFC),
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(14),
                          borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
                        ),
                        enabledBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(14),
                          borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
                        ),
                        focusedBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(14),
                          borderSide: const BorderSide(color: AppColors.electricBlue, width: 1.8),
                        ),
                      ),
                      validator: (v) {
                        if (v == null || v.trim().isEmpty) return 'Please enter your mobile number';
                        final cleaned = v.replaceAll(RegExp(r'[^0-9]'), '');
                        if (cleaned.length != 11 || !cleaned.startsWith('09')) {
                          return 'Enter a valid 11-digit number starting with 09';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 32),

                    SizedBox(
                      width: double.infinity,
                      height: 50,
                      child: ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.primary, // Midnight Navy
                          foregroundColor: Colors.white,
                          elevation: 0,
                          shape: const StadiumBorder(),
                        ),
                        onPressed: _loading ? null : _requestOtp,
                        child: _loading
                            ? const SizedBox(
                                width: 22,
                                height: 22,
                                child: CircularProgressIndicator(strokeWidth: 2.2, color: Colors.white),
                              )
                            : const Text(
                                'Send OTP Code',
                                style: TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w700,
                                  letterSpacing: 0.2,
                                ),
                              ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  // =========================================================================
  // VIEW: STEP 2 - EXACT MATCHING OTP SCREEN DESIGN
  // =========================================================================
  Widget _buildOtpScreenView() {
    return Scaffold(
      backgroundColor: Colors.white,
      resizeToAvoidBottomInset: true,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: AppBackButton(
          onPressed: () {
            setState(() {
              _currentStep = 1;
              _error = null;
              _successNotice = null;
            });
          },
        ),
      ),
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Column(
                children: [
                  Expanded(
                    child: SingleChildScrollView(
                      physics: const BouncingScrollPhysics(),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.center,
                        children: [
                          const SizedBox(height: 16),

                          // 1. Centered Pwestora Logo matching registration
                          Center(
                            child: Container(
                              width: 76,
                              height: 76,
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                shape: BoxShape.circle,
                                border: Border.all(
                                  color: AppColors.border.withValues(alpha: 0.8),
                                  width: 1.5,
                                ),
                                boxShadow: [
                                  BoxShadow(
                                    color: AppColors.primary.withValues(alpha: 0.08),
                                    blurRadius: 20,
                                    offset: const Offset(0, 6),
                                  ),
                                ],
                              ),
                              child: Image.asset(
                                Theme.of(context).brightness == Brightness.dark
                                    ? 'img/invert.png'
                                    : 'img/logo.png',
                                fit: BoxFit.contain,
                                errorBuilder: (context, error, stackTrace) => Container(
                                  alignment: Alignment.center,
                                  child: const Text('P', style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.bold, fontSize: 24)),
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(height: 18),

                          // 2. Title matching reference
                          const Text(
                            'Enter Your code',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              fontSize: 24,
                              fontWeight: FontWeight.w800,
                              color: AppColors.ink900,
                              letterSpacing: -0.4,
                            ),
                          ),
                          const SizedBox(height: 10),

                          // 3. Subtitle matching reference
                          const Text(
                            'A 6 digit code has been sent to\nyour cell phone.',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              fontSize: 14,
                              color: AppColors.ink500,
                              height: 1.4,
                            ),
                          ),
                          const SizedBox(height: 8),

                          // Phone number with inline pen icon to edit
                          InkWell(
                            onTap: () {
                              setState(() {
                                _currentStep = 1;
                                _error = null;
                                _successNotice = null;
                              });
                            },
                            borderRadius: BorderRadius.circular(20),
                            child: Padding(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Text(
                                    _phoneCtrl.text.trim(),
                                    style: const TextStyle(
                                      fontSize: 14.5,
                                      fontWeight: FontWeight.w700,
                                      color: AppColors.ink900,
                                      letterSpacing: 0.3,
                                    ),
                                  ),
                                  const SizedBox(width: 6),
                                  const Icon(
                                    Icons.edit_outlined,
                                    size: 15,
                                    color: AppColors.electricBlue,
                                  ),
                                ],
                              ),
                            ),
                          ),
                          const SizedBox(height: 20),

                          // 4. Error banner if any
                          if (_error != null) ...[
                            Container(
                              width: double.infinity,
                              margin: const EdgeInsets.only(bottom: 20),
                              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                              decoration: BoxDecoration(
                                color: AppColors.errorSoft,
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(color: AppColors.error.withValues(alpha: 0.3)),
                              ),
                              child: Row(
                                children: [
                                  const Icon(Icons.error_outline, size: 18, color: AppColors.error),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      _error!,
                                      style: const TextStyle(color: AppColors.error, fontSize: 12.5, fontWeight: FontWeight.w600),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],

                          // 5. Success banner if resent
                          if (_successNotice != null) ...[
                            Container(
                              width: double.infinity,
                              margin: const EdgeInsets.only(bottom: 20),
                              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                              decoration: BoxDecoration(
                                color: const Color(0xFFE8F5E9),
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(color: const Color(0xFF4CAF50).withValues(alpha: 0.4)),
                              ),
                              child: Row(
                                children: [
                                  const Icon(Icons.check_circle_outline, size: 18, color: Color(0xFF2E7D32)),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      _successNotice!,
                                      style: const TextStyle(color: Color(0xFF2E7D32), fontSize: 12.5, fontWeight: FontWeight.w600),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],

                          // 6. Interactive 6-Digit Circular Bubbles Row matching reference
                          GestureDetector(
                            behavior: HitTestBehavior.opaque,
                            onTap: _focusOtpInput,
                            child: FittedBox(
                              fit: BoxFit.scaleDown,
                              child: Stack(
                                alignment: Alignment.center,
                                children: [
                                  // Visual Row of 6 Circular Bubbles
                                  Row(
                                    mainAxisSize: MainAxisSize.min,
                                    mainAxisAlignment: MainAxisAlignment.center,
                                    children: List.generate(6, (index) {
                                      return Padding(
                                        padding: const EdgeInsets.symmetric(horizontal: 4),
                                        child: _buildOtpBubble(index),
                                      );
                                    }),
                                  ),

                                  // Transparent TextField spanning the entire bubble area
                                  Positioned.fill(
                                    child: Opacity(
                                      opacity: 0.0,
                                      child: TextField(
                                        controller: _otpCtrl,
                                        focusNode: _otpFocusNode,
                                        keyboardType: TextInputType.number,
                                        autofocus: true,
                                        showCursor: false,
                                        enableInteractiveSelection: false,
                                        maxLength: 6,
                                        inputFormatters: [
                                          FilteringTextInputFormatter.digitsOnly,
                                          LengthLimitingTextInputFormatter(6),
                                        ],
                                        decoration: const InputDecoration(
                                          border: InputBorder.none,
                                          counterText: '',
                                          fillColor: Colors.transparent,
                                          filled: true,
                                          contentPadding: EdgeInsets.zero,
                                        ),
                                        onTap: () {
                                          SystemChannels.textInput.invokeMethod('TextInput.show');
                                        },
                                        onChanged: (val) {
                                          if (val.trim().length == 6) {
                                            _verifyOtp();
                                          }
                                        },
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                          const SizedBox(height: 24),

                          // 7. "Didn't receive the code? Resend." Text
                          Wrap(
                            alignment: WrapAlignment.center,
                            crossAxisAlignment: WrapCrossAlignment.center,
                            spacing: 4,
                            runSpacing: 4,
                            children: [
                              const Text(
                                "Didn't receive the code? ",
                                style: TextStyle(
                                  fontSize: 13.5,
                                  color: AppColors.ink500,
                                ),
                              ),
                              GestureDetector(
                                onTap: _secondsRemaining > 0 ? null : _resendOtp,
                                child: Text(
                                  _secondsRemaining > 0
                                      ? 'Resend (${_secondsRemaining}s)'
                                      : 'Resend.',
                                  style: TextStyle(
                                    fontSize: 13.5,
                                    fontWeight: FontWeight.w800,
                                    color: _secondsRemaining > 0
                                        ? AppColors.ink400
                                        : AppColors.electricBlue,
                                    decoration: _secondsRemaining > 0 ? null : TextDecoration.underline,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 16),
                        ],
                      ),
                    ),
                  ),

                  // 8. Large Bottom Pill Submit Button matching reference
                  Padding(
                    padding: const EdgeInsets.only(bottom: 16, top: 8),
                    child: SizedBox(
                      width: double.infinity,
                      height: 50,
                      child: ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.primary, // Midnight Navy
                          foregroundColor: Colors.white,
                          elevation: 0,
                          shape: const StadiumBorder(),
                          shadowColor: Colors.transparent,
                        ),
                        onPressed: _loading ? null : _verifyOtp,
                        child: _loading
                            ? const SizedBox(
                                width: 22,
                                height: 22,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2.2,
                                  color: Colors.white,
                                ),
                              )
                            : const Text(
                                'Submit',
                                style: TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w700,
                                  letterSpacing: 0.2,
                                ),
                              ),
                      ),
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

  /// Builds each circular bubble matching the user's provided UI reference
  Widget _buildOtpBubble(int index) {
    final text = _otpCtrl.text;
    final isEntered = index < text.length;
    final isFocused = index == text.length;
    final char = isEntered ? text[index] : (isFocused ? '' : '0');

    if (isEntered) {
      return Container(
        width: 44,
        height: 44,
        decoration: const BoxDecoration(
          color: Color(0xFF2B2B2B), // Solid dark charcoal/navy
          shape: BoxShape.circle,
        ),
        alignment: Alignment.center,
        child: Text(
          char,
          style: const TextStyle(
            fontSize: 17,
            fontWeight: FontWeight.w700,
            color: Colors.white,
          ),
        ),
      );
    }

    if (isFocused) {
      return Container(
        width: 44,
        height: 44,
        decoration: BoxDecoration(
          color: Colors.white,
          shape: BoxShape.circle,
          border: Border.all(
            color: const Color(0xFF2B2B2B),
            width: 1.8,
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.08),
              blurRadius: 6,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        alignment: Alignment.center,
        child: Container(
          width: 2,
          height: 18,
          color: AppColors.primary,
        ),
      );
    }

    // Unfilled bubbles: faint grey background, subtle placeholder '0'
    return Container(
      width: 44,
      height: 44,
      decoration: BoxDecoration(
        color: const Color(0xFFF7F8F9),
        shape: BoxShape.circle,
        border: Border.all(
          color: const Color(0xFFE5E7EB),
          width: 1.0,
        ),
      ),
      alignment: Alignment.center,
      child: Text(
        '0',
        style: TextStyle(
          fontSize: 15,
          fontWeight: FontWeight.w400,
          color: Colors.black.withValues(alpha: 0.18),
        ),
      ),
    );
  }

  // =========================================================================
  // VIEW: STEP 3 - SET NEW PASSWORD
  // =========================================================================
  Widget _buildNewPasswordView() {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: AppBackButton(
          onPressed: () {
            setState(() {
              _currentStep = 2;
              _error = null;
            });
          },
        ),
      ),
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: SingleChildScrollView(
              physics: const BouncingScrollPhysics(),
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
              child: Form(
                key: _passwordFormKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Center(
                      child: Container(
                        width: 76,
                        height: 76,
                        decoration: BoxDecoration(
                          color: const Color(0xFFEFF6FF),
                          shape: BoxShape.circle,
                          border: Border.all(color: const Color(0xFFBFDBFE), width: 1.5),
                        ),
                        child: const Icon(
                          Icons.lock_reset_rounded,
                          size: 38,
                          color: AppColors.electricBlue,
                        ),
                      ),
                    ),
                    const SizedBox(height: 20),

                    const Center(
                      child: Text(
                        'Set New Password',
                        style: TextStyle(
                          fontSize: 24,
                          fontWeight: FontWeight.w800,
                          color: AppColors.ink900,
                          letterSpacing: -0.4,
                        ),
                      ),
                    ),
                    const SizedBox(height: 8),
                    const Center(
                      child: Text(
                        'Enter a strong new password for your account. Must be at least 6 characters.',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 14,
                          color: AppColors.ink500,
                          height: 1.45,
                        ),
                      ),
                    ),
                    const SizedBox(height: 28),

                    if (_error != null) ...[
                      Container(
                        width: double.infinity,
                        margin: const EdgeInsets.only(bottom: 20),
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                        decoration: BoxDecoration(
                          color: AppColors.errorSoft,
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: AppColors.error.withValues(alpha: 0.3)),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.error_outline, size: 18, color: AppColors.error),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                _error!,
                                style: const TextStyle(color: AppColors.error, fontSize: 12.5, fontWeight: FontWeight.w600),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],

                    const Text(
                      'New Password',
                      style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.ink900),
                    ),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _newPasswordCtrl,
                      obscureText: _obscureNew,
                      decoration: InputDecoration(
                        hintText: 'Enter new password',
                        prefixIcon: const Icon(Icons.lock_outline_rounded, color: AppColors.ink500, size: 20),
                        suffixIcon: IconButton(
                          icon: Icon(_obscureNew ? Icons.visibility_off_outlined : Icons.visibility_outlined, size: 20, color: AppColors.ink500),
                          onPressed: () => setState(() => _obscureNew = !_obscureNew),
                        ),
                        filled: true,
                        fillColor: const Color(0xFFF8FAFC),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AppColors.electricBlue, width: 1.8)),
                      ),
                      validator: (v) {
                        if (v == null || v.isEmpty) return 'Please enter your new password';
                        if (v.length < 6) return 'Password must be at least 6 characters';
                        return null;
                      },
                    ),
                    const SizedBox(height: 18),

                    const Text(
                      'Confirm New Password',
                      style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.ink900),
                    ),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _confirmPasswordCtrl,
                      obscureText: _obscureConfirm,
                      decoration: InputDecoration(
                        hintText: 'Re-enter new password',
                        prefixIcon: const Icon(Icons.lock_outline_rounded, color: AppColors.ink500, size: 20),
                        suffixIcon: IconButton(
                          icon: Icon(_obscureConfirm ? Icons.visibility_off_outlined : Icons.visibility_outlined, size: 20, color: AppColors.ink500),
                          onPressed: () => setState(() => _obscureConfirm = !_obscureConfirm),
                        ),
                        filled: true,
                        fillColor: const Color(0xFFF8FAFC),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AppColors.electricBlue, width: 1.8)),
                      ),
                      validator: (v) {
                        if (v == null || v.isEmpty) return 'Please confirm your new password';
                        if (v != _newPasswordCtrl.text) return 'Passwords do not match';
                        return null;
                      },
                    ),
                    const SizedBox(height: 32),

                    SizedBox(
                      width: double.infinity,
                      height: 50,
                      child: ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.primary,
                          foregroundColor: Colors.white,
                          elevation: 0,
                          shape: const StadiumBorder(),
                        ),
                        onPressed: _loading ? null : _submitNewPassword,
                        child: _loading
                            ? const SizedBox(
                                width: 22,
                                height: 22,
                                child: CircularProgressIndicator(strokeWidth: 2.2, color: Colors.white),
                              )
                            : const Text(
                                'Reset Password',
                                style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, letterSpacing: 0.2),
                              ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
