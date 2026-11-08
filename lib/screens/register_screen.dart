import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../theme.dart';
import '../services/api_service.dart';
import 'home_shell.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> with WidgetsBindingObserver {
  final _formKey = GlobalKey<FormState>();
  final _nameCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  final _otpCtrl = TextEditingController();
  final _otpFocusNode = FocusNode();

  // 1 = Enter credentials, 2 = Enter SMS OTP
  int _currentStep = 1;
  bool _loading = false;
  bool _obscure = true;
  String? _error;
  String? _successNotice;

  // Resend OTP countdown
  Timer? _countdownTimer;
  int _secondsRemaining = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _otpCtrl.addListener(() {
      if (mounted) setState(() {});
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _countdownTimer?.cancel();
    _nameCtrl.dispose();
    _emailCtrl.dispose();
    _phoneCtrl.dispose();
    _passwordCtrl.dispose();
    _otpCtrl.dispose();
    _otpFocusNode.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && _currentStep == 2) {
      Future.delayed(const Duration(milliseconds: 120), () {
        if (mounted && _currentStep == 2) {
          _focusOtpInput();
        }
      });
    }
  }

  /// Ensures native soft-keyboard is always brought up when touching OTP type
  void _focusOtpInput() {
    if (!mounted || _currentStep != 2) return;
    if (_otpFocusNode.hasFocus) {
      _otpFocusNode.unfocus();
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && _currentStep == 2) {
          _otpFocusNode.requestFocus();
          SystemChannels.textInput.invokeMethod('TextInput.show');
        }
      });
    } else {
      _otpFocusNode.requestFocus();
      SystemChannels.textInput.invokeMethod('TextInput.show');
    }
  }

  void _startCountdown() {
    _countdownTimer?.cancel();
    setState(() => _secondsRemaining = 60);
    _countdownTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }
      if (_secondsRemaining <= 1) {
        timer.cancel();
        setState(() => _secondsRemaining = 0);
      } else {
        setState(() => _secondsRemaining--);
      }
    });
  }

  /// Step 1: Request SMS OTP to the tenant's phone
  Future<void> _requestOtp() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _loading = true;
      _error = null;
      _successNotice = null;
    });

    try {
      await ApiService.sendRegistrationOtp(
        phone: _phoneCtrl.text.trim(),
        email: _emailCtrl.text.trim(),
      );
      if (!mounted) return;
      setState(() {
        _currentStep = 2;
        _otpCtrl.clear();
      });
      _startCountdown();
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _focusOtpInput();
      });
    } catch (e) {
      setState(() => _error = ApiService.errorMessage(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  /// Resend SMS OTP
  Future<void> _resendOtp() async {
    if (_secondsRemaining > 0 || _loading) return;
    setState(() {
      _loading = true;
      _error = null;
      _successNotice = null;
    });

    try {
      await ApiService.sendRegistrationOtp(
        phone: _phoneCtrl.text.trim(),
        email: _emailCtrl.text.trim(),
      );
      if (!mounted) return;
      setState(() {
        _successNotice = 'A new verification code has been sent!';
      });
      _startCountdown();
      _focusOtpInput();
    } catch (e) {
      setState(() => _error = ApiService.errorMessage(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  /// Step 2: Verify OTP and activate account
  Future<void> _verifyAndRegister() async {
    final otp = _otpCtrl.text.trim();
    if (otp.length != 6) {
      setState(() => _error = 'Please enter all 6 digits of the verification code.');
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
      _successNotice = null;
    });

    try {
      await ApiService.register(
        fullName: _nameCtrl.text.trim(),
        email: _emailCtrl.text.trim(),
        phone: _phoneCtrl.text.trim(),
        password: _passwordCtrl.text,
        otpCode: otp,
      );
      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const HomeShell()),
        (route) => false,
      );
    } catch (e) {
      setState(() => _error = ApiService.errorMessage(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_currentStep == 2) {
      return _buildOtpScreenView();
    }

    return _buildCredentialsFormView();
  }

  /// =========================================================================
  /// STEP 1: Registration Credentials Form View
  /// =========================================================================
  Widget _buildCredentialsFormView() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF000000) : AppColors.bg,
      appBar: AppBar(
        leading: AppBackButton(isDark: isDark),
        title: const Text('Create Account'),
        backgroundColor: isDark ? const Color(0xFF121212) : AppColors.surface,
        elevation: 0,
      ),
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 480),
            child: SingleChildScrollView(
              physics: const BouncingScrollPhysics(),
              padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 20),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Center(
                      child: Container(
                        width: 68,
                        height: 68,
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: isDark ? const Color(0xFF161616) : Colors.white,
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(
                            color: isDark ? const Color(0xFF262626) : AppColors.border.withValues(alpha: 0.8),
                          ),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: isDark ? 0.4 : 0.08),
                              blurRadius: 16,
                              offset: const Offset(0, 4),
                            ),
                          ],
                        ),
                        child: Image.asset(
                          isDark ? 'img/invert.png' : 'img/Frame 2.png',
                          fit: BoxFit.contain,
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    const Center(
                      child: Text(
                        'Join Pwestora Commercial',
                        style: TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                          color: AppColors.ink900,
                          letterSpacing: -0.4,
                        ),
                      ),
                    ),
                    const SizedBox(height: 4),
                    const Center(
                      child: Text(
                        'Discover prime spaces and submit direct lease applications.',
                        textAlign: TextAlign.center,
                        style: TextStyle(fontSize: 13.5, color: AppColors.ink500),
                      ),
                    ),
                    const SizedBox(height: 22),

                    if (_error != null) ...[
                      Container(
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
                                _error!,
                                style: const TextStyle(color: AppColors.error, fontSize: 13, fontWeight: FontWeight.w600),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 18),
                    ],

                    Container(
                      padding: const EdgeInsets.all(22),
                      decoration: AppDecorations.card(radius: 20, shadow: true),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Full Name', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.ink900)),
                          const SizedBox(height: 6),
                          TextFormField(
                            controller: _nameCtrl,
                            decoration: const InputDecoration(
                              hintText: 'Juan Dela Cruz',
                              prefixIcon: Icon(Icons.person_outline, color: AppColors.ink500, size: 20),
                            ),
                            validator: (v) => (v == null || v.trim().isEmpty) ? 'Please enter your full name' : null,
                          ),
                          const SizedBox(height: 16),

                          const Text('Email Address', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.ink900)),
                          const SizedBox(height: 6),
                          TextFormField(
                            controller: _emailCtrl,
                            keyboardType: TextInputType.emailAddress,
                            decoration: const InputDecoration(
                              hintText: 'you@example.com',
                              prefixIcon: Icon(Icons.email_outlined, color: AppColors.ink500, size: 20),
                            ),
                            validator: (v) {
                              if (v == null || v.trim().isEmpty) return 'Please enter your email';
                              if (!RegExp(r'^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$').hasMatch(v.trim())) {
                                return 'Please enter a valid email address';
                              }
                              return null;
                            },
                          ),
                          const SizedBox(height: 16),

                          const Text('Philippine Mobile Number', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.ink900)),
                          const SizedBox(height: 6),
                          TextFormField(
                            controller: _phoneCtrl,
                            keyboardType: TextInputType.phone,
                            inputFormatters: [
                              FilteringTextInputFormatter.digitsOnly,
                              LengthLimitingTextInputFormatter(11),
                            ],
                            decoration: const InputDecoration(
                              hintText: '09171234567',
                              prefixIcon: Icon(Icons.phone_android_rounded, color: AppColors.ink500, size: 20),
                              helperText: 'We will send a 6-digit SMS OTP here',
                              helperStyle: TextStyle(fontSize: 11, color: AppColors.ink500),
                            ),
                            validator: (v) {
                              if (v == null || v.trim().isEmpty) return 'Please enter your phone number';
                              final clean = v.replaceAll(RegExp(r'\D'), '');
                              if (clean.length != 11 || !clean.startsWith('09')) {
                                return 'Must be an 11-digit number starting with 09 (e.g. 09171234567)';
                              }
                              return null;
                            },
                          ),
                          const SizedBox(height: 16),

                          const Text('Password (min. 8 characters)', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.ink900)),
                          const SizedBox(height: 6),
                          TextFormField(
                            controller: _passwordCtrl,
                            obscureText: _obscure,
                            decoration: InputDecoration(
                              hintText: '••••••••',
                              prefixIcon: const Icon(Icons.lock_outline, color: AppColors.ink500, size: 20),
                              suffixIcon: IconButton(
                                icon: Icon(_obscure ? Icons.visibility_off : Icons.visibility, color: AppColors.ink500, size: 20),
                                onPressed: () => setState(() => _obscure = !_obscure),
                              ),
                            ),
                            validator: (v) {
                              if (v == null || v.isEmpty) return 'Enter a password';
                              if (v.length < 8) return 'Must be at least 8 characters';
                              return null;
                            },
                          ),
                          const SizedBox(height: 24),

                          SizedBox(
                            width: double.infinity,
                            height: 52,
                            child: ElevatedButton(
                              style: ElevatedButton.styleFrom(
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                              ),
                              onPressed: _loading ? null : _requestOtp,
                              child: _loading
                                  ? const SizedBox(
                                      height: 20,
                                      width: 20,
                                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                    )
                                  : const Row(
                                      mainAxisAlignment: MainAxisAlignment.center,
                                      children: [
                                        Text('Continue & Send Code', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
                                        SizedBox(width: 8),
                                        Icon(Icons.arrow_forward_rounded, size: 18),
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
            ),
          ),
        ),
      ),
    );
  }

  /// =========================================================================
  /// STEP 2: Custom Minimal OTP Screen View matching user's design reference
  /// =========================================================================
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

                          // 1. Centered Pwestora Logo (Replacing the mandala reference)
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
                                    : 'img/Frame 2.png',
                                fit: BoxFit.contain,
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
                                            _verifyAndRegister();
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
                          backgroundColor: AppColors.primary, // Exact Midnight Navy brand color from Register Screen
                          foregroundColor: Colors.white,
                          elevation: 0,
                          shape: const StadiumBorder(),
                          shadowColor: Colors.transparent,
                        ),
                        onPressed: _loading ? null : _verifyAndRegister,
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

  /// Builds each circular bubble matching the user's provided UI screenshot
  Widget _buildOtpBubble(int index) {
    final text = _otpCtrl.text;
    final isEntered = index < text.length;
    final isFocused = index == text.length;
    final char = isEntered ? text[index] : (isFocused ? '' : '0');

    // Visual styles matching the reference:
    // - Entered digits: Solid dark circle, white text
    // - Focused position: White circle with dark outline
    // - Future unfilled digits: Very soft off-white/grey circle with faint border
    if (isEntered) {
      return Container(
        width: 44,
        height: 44,
        decoration: const BoxDecoration(
          color: Color(0xFF2B2B2B), // Solid dark charcoal/navy from screenshot
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
          height: 16,
          color: AppColors.primary,
        ),
      );
    }

    // Unfilled bubbles: faint grey background, subtle placeholder dot or '0'
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
}
