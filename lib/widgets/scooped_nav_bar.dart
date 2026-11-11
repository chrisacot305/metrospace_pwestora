import 'package:flutter/material.dart';
import '../theme.dart';

/// Custom Painter that renders a white dock with a smooth concave cradle scoop
/// in the top center for an elevated circular action button.
class ScoopedCradlePainter extends CustomPainter {
  final Color color;
  final Color shadowColor;
  final Color? borderColor;
  final double cornerRadius;
  final double cradleWidth;
  final double cradleDepth;
  final double barTop;

  ScoopedCradlePainter({
    this.color = Colors.white,
    this.shadowColor = const Color(0xFF0A1832),
    this.borderColor,
    this.cornerRadius = 32.0,
    this.cradleWidth = 86.0,
    this.cradleDepth = 36.0,
    this.barTop = 20.0,
  });

  Path _buildPath(Size size) {
    final path = Path();
    final w = size.width;
    final h = size.height;
    final cx = w / 2;
    final halfCradle = cradleWidth / 2;

    // Start at bottom-left corner
    path.moveTo(0, h - cornerRadius);
    // Bottom-left rounded corner
    path.quadraticBezierTo(0, h, cornerRadius, h);
    // Bottom edge
    path.lineTo(w - cornerRadius, h);
    // Bottom-right rounded corner
    path.quadraticBezierTo(w, h, w, h - cornerRadius);
    // Right edge
    path.lineTo(w, barTop + cornerRadius);
    // Top-right rounded corner
    path.quadraticBezierTo(w, barTop, w - cornerRadius, barTop);

    // Flat top edge to the right edge of the scoop
    path.lineTo(cx + halfCradle, barTop);

    // Right side of scoop: smooth S-curve down into bottom of cradle
    path.cubicTo(
      cx + halfCradle * 0.52,
      barTop,
      cx + halfCradle * 0.52,
      barTop + cradleDepth,
      cx,
      barTop + cradleDepth,
    );

    // Left side of scoop: smooth S-curve back up to the flat top edge
    path.cubicTo(
      cx - halfCradle * 0.52,
      barTop + cradleDepth,
      cx - halfCradle * 0.52,
      barTop,
      cx - halfCradle,
      barTop,
    );

    // Flat top edge to top-left corner
    path.lineTo(cornerRadius, barTop);
    // Top-left rounded corner
    path.quadraticBezierTo(0, barTop, 0, barTop + cornerRadius);

    path.close();
    return path;
  }

  @override
  void paint(Canvas canvas, Size size) {
    final path = _buildPath(size);

    // Soft multi-layered ambient and directional drop shadows
    final ambientShadow = Paint()
      ..color = shadowColor.withValues(alpha: 0.08)
      ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 20);
    canvas.drawPath(path.shift(const Offset(0, 6)), ambientShadow);

    final crispShadow = Paint()
      ..color = shadowColor.withValues(alpha: 0.04)
      ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 6);
    canvas.drawPath(path.shift(const Offset(0, 2)), crispShadow);

    // Main surface fill
    final fillPaint = Paint()
      ..color = color
      ..style = PaintingStyle.fill;
    canvas.drawPath(path, fillPaint);

    // Hairline border
    final borderPaint = Paint()
      ..color = borderColor ?? AppColors.border.withValues(alpha: 0.75)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.0;
    canvas.drawPath(path, borderPaint);
  }

  @override
  bool shouldRepaint(covariant ScoopedCradlePainter oldDelegate) {
    return oldDelegate.color != color ||
        oldDelegate.shadowColor != shadowColor ||
        oldDelegate.borderColor != borderColor ||
        oldDelegate.cradleWidth != cradleWidth ||
        oldDelegate.cradleDepth != cradleDepth ||
        oldDelegate.barTop != barTop;
  }
}

/// Interactive Navigation Item supporting Navy Blue active state,
/// mouse hover highlights, and subtle micro-feedback.
class ScoopedNavItem extends StatefulWidget {
  final int index;
  final int currentIndex;
  final IconData icon;
  final IconData outlineIcon;
  final String label;
  final ValueChanged<int> onTap;
  final bool isLocked;

  const ScoopedNavItem({
    super.key,
    required this.index,
    required this.currentIndex,
    required this.icon,
    required this.outlineIcon,
    required this.label,
    required this.onTap,
    this.isLocked = false,
  });

  @override
  State<ScoopedNavItem> createState() => _ScoopedNavItemState();
}

class _ScoopedNavItemState extends State<ScoopedNavItem> {
  bool _isHovered = false;

  static const Color navyActive = Color(0xFF0A1832);

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final isSelected = !widget.isLocked && widget.currentIndex == widget.index;
    final isActiveOrHovered = isSelected || (!widget.isLocked && _isHovered);

    final activeColor = isDark ? Colors.white : navyActive;
    final inactiveColor = isDark ? const Color(0xFF94A3B8) : AppColors.ink400;
    final itemColor = (isActiveOrHovered ? activeColor : inactiveColor)
        .withValues(alpha: widget.isLocked ? 0.42 : 1.0);

    return MouseRegion(
      onEnter: (_) => setState(() => _isHovered = true),
      onExit: (_) => setState(() => _isHovered = false),
      cursor: widget.isLocked ? SystemMouseCursors.forbidden : SystemMouseCursors.click,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: () => widget.onTap(widget.index),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          color: Colors.transparent,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Stack(
                clipBehavior: Clip.none,
                children: [
                  AnimatedScale(
                    scale: isSelected ? 1.06 : 1.0,
                    duration: const Duration(milliseconds: 180),
                    child: Icon(
                      isSelected ? widget.icon : widget.outlineIcon,
                      color: itemColor,
                      size: 23,
                    ),
                  ),
                  if (widget.isLocked)
                    Positioned(
                      right: -6,
                      top: -3,
                      child: Container(
                        padding: const EdgeInsets.all(2),
                        decoration: BoxDecoration(
                          color: isDark ? const Color(0xFF1F2937) : const Color(0xFFFEE2E2),
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(
                          Icons.lock_rounded,
                          size: 9,
                          color: AppColors.error,
                        ),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 3),
              Text(
                widget.label,
                style: TextStyle(
                  fontSize: 10.5,
                  fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                  color: itemColor,
                  letterSpacing: -0.2,
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
