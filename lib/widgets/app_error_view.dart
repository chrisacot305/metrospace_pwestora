import 'package:flutter/material.dart';
import '../theme.dart';
import '../services/api_service.dart';

/// Reusable user-friendly error view that handles No Internet and generic errors gracefully.
class AppErrorView extends StatelessWidget {
  final dynamic error;
  final VoidCallback? onRetry;
  final String? customTitle;
  final String? customSubtitle;
  final bool compact;

  const AppErrorView({
    super.key,
    required this.error,
    this.onRetry,
    this.customTitle,
    this.customSubtitle,
    this.compact = false,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final isNetwork = ApiService.isNetworkError(error);

    final title = customTitle ?? (isNetwork ? 'No Internet Connection' : 'Something Went Wrong');
    final subtitle = customSubtitle ??
        (isNetwork
            ? 'Please check your network and try again.'
            : ApiService.errorMessage(error));

    final titleColor = isDark ? Colors.white : AppColors.ink900;
    final subtitleColor = isDark ? const Color(0xFF94A3B8) : AppColors.ink500;
    final iconColor = isNetwork ? (isDark ? const Color(0xFF94A3B8) : AppColors.ink400) : AppColors.error;
    final iconBgColor = isNetwork
        ? (isDark ? Colors.white : AppColors.ink900).withValues(alpha: 0.06)
        : AppColors.errorSoft;

    return Center(
      child: Padding(
        padding: EdgeInsets.symmetric(
          horizontal: compact ? 20 : 32,
          vertical: compact ? 24 : 44,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: compact ? 54 : 68,
              height: compact ? 54 : 68,
              decoration: BoxDecoration(
                color: iconBgColor,
                shape: BoxShape.circle,
              ),
              child: Icon(
                isNetwork ? Icons.wifi_off_rounded : Icons.error_outline_rounded,
                size: compact ? 28 : 36,
                color: iconColor,
              ),
            ),
            SizedBox(height: compact ? 14 : 18),
            Text(
              title,
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: compact ? 15 : 18,
                fontWeight: FontWeight.w700,
                color: titleColor,
              ),
            ),
            const SizedBox(height: 8),
            ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 320),
              child: Text(
                subtitle,
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: compact ? 12.5 : 13.5,
                  color: subtitleColor,
                  height: 1.4,
                ),
              ),
            ),
            if (onRetry != null) ...[
              SizedBox(height: compact ? 18 : 24),
              ElevatedButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh_rounded, size: 18),
                label: const Text('Try Again'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.electricBlue,
                  foregroundColor: Colors.white,
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  padding: EdgeInsets.symmetric(
                    horizontal: compact ? 20 : 26,
                    vertical: compact ? 10 : 12,
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

