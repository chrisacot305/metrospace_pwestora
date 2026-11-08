import 'package:flutter/material.dart';
import '../theme.dart';
import 'tickets_list_screen.dart';

class TicketDetailScreen extends StatelessWidget {
  final Map<String, dynamic> ticket;
  const TicketDetailScreen({super.key, required this.ticket});

  @override
  Widget build(BuildContext context) {
    final stage = (ticket['stage'] as num?)?.toInt() ?? 0;
    final priority = (ticket['priority'] as String?) ?? 'medium';
    final (pColor, pBg) = kPriorityColors[priority] ?? kPriorityColors['medium']!;
    final icon = kCategoryIcons[ticket['category']] ?? Icons.build_outlined;
    final completed = stage == kTicketStages.length - 1;

    return Scaffold(
      backgroundColor: AppColors.bg,
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text('Ticket #MT-${ticket['id']}'),
        backgroundColor: AppColors.surface,
        elevation: 0,
      ),
      body: SingleChildScrollView(
        physics: const BouncingScrollPhysics(),
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Overview Header Card
            Container(
              padding: const EdgeInsets.all(18),
              decoration: AppDecorations.card(radius: 20, shadow: true),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        width: 44,
                        height: 44,
                        decoration: AppDecorations.squircle(color: AppColors.accentSoft),
                        child: Icon(icon, size: 22, color: AppColors.primary),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              (ticket['category'] ?? 'GENERAL').toString().toUpperCase(),
                              style: const TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w800,
                                color: AppColors.accent,
                                letterSpacing: 0.5,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              ticket['title']?.toString() ?? 'Issue Details',
                              style: const TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w800,
                                color: AppColors.ink900,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: AppDecorations.badge(bg: pBg),
                        child: Text(
                          priority.toUpperCase(),
                          style: TextStyle(
                            fontSize: 10.5,
                            fontWeight: FontWeight.w800,
                            color: pColor,
                          ),
                        ),
                      ),
                    ],
                  ),
                  if (ticket['contractor'] != null && ticket['contractor'].toString().isNotEmpty) ...[
                    const SizedBox(height: 14),
                    const Divider(color: AppColors.borderLight),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        const Icon(Icons.engineering_outlined, size: 16, color: AppColors.ink500),
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            'Contractor: ${ticket['contractor']}',
                            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.ink700),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                  ],
                  if (ticket['photos'] is List && (ticket['photos'] as List).isNotEmpty) ...[
                    const SizedBox(height: 14),
                    const Divider(color: AppColors.borderLight),
                    const SizedBox(height: 10),
                    const Text(
                      'Attached Photos',
                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.ink500),
                    ),
                    const SizedBox(height: 8),
                    SizedBox(
                      height: 80,
                      child: ListView.separated(
                        scrollDirection: Axis.horizontal,
                        physics: const BouncingScrollPhysics(),
                        itemCount: (ticket['photos'] as List).length,
                        separatorBuilder: (context, i) => const SizedBox(width: 8),
                        itemBuilder: (context, idx) {
                          final path = (ticket['photos'] as List)[idx].toString();
                          final url = path.startsWith('http') ? path : 'https://pwestora.com/$path';
                          return ClipRRect(
                            borderRadius: BorderRadius.circular(10),
                            child: Image.network(
                              url,
                              width: 80,
                              height: 80,
                              fit: BoxFit.cover,
                              errorBuilder: (context, error, stackTrace) => Container(
                                width: 80,
                                height: 80,
                                color: AppColors.bg,
                                child: const Icon(Icons.broken_image_rounded, color: AppColors.ink300),
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 24),

            // Progress Timeline
            const Text(
              'Resolution Audit Trail',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: AppColors.ink900),
            ),
            const SizedBox(height: 14),

            Container(
              padding: const EdgeInsets.all(20),
              decoration: AppDecorations.card(radius: 20),
              child: Column(
                children: List.generate(kTicketStages.length, (i) {
                  final isDone = i <= stage;
                  final isCurrent = i == stage;
                  final isLast = i == kTicketStages.length - 1;

                  return Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Column(
                        children: [
                          Container(
                            width: 24,
                            height: 24,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: isDone
                                  ? (isCurrent && !completed ? AppColors.primaryLight : AppColors.primary)
                                  : Colors.white,
                              border: Border.all(
                                color: isDone ? AppColors.primary : AppColors.border,
                                width: 2,
                              ),
                            ),
                            child: isDone
                                ? const Icon(Icons.check, size: 14, color: Colors.white)
                                : null,
                          ),
                          if (!isLast)
                            Container(
                              width: 2,
                              height: 32,
                              color: isDone ? AppColors.primary : AppColors.borderLight,
                            ),
                        ],
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Padding(
                          padding: const EdgeInsets.only(top: 2, bottom: 16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                kTicketStages[i],
                                style: TextStyle(
                                  fontSize: 14,
                                  fontWeight: isCurrent ? FontWeight.w800 : (isDone ? FontWeight.w700 : FontWeight.w500),
                                  color: isDone ? AppColors.ink900 : AppColors.ink300,
                                ),
                              ),
                              if (isCurrent && !completed)
                                const Padding(
                                  padding: EdgeInsets.only(top: 2),
                                  child: Text(
                                    'Currently in progress by management',
                                    style: TextStyle(fontSize: 11.5, color: AppColors.accent, fontWeight: FontWeight.w600),
                                  ),
                                ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  );
                }),
              ),
            ),

            if (completed) ...[
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: AppColors.successSoft,
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: AppColors.success.withValues(alpha: 0.3)),
                ),
                child: const Row(
                  children: [
                    Icon(Icons.check_circle, color: AppColors.success, size: 28),
                    SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Repair Completed & Verified',
                            style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: AppColors.success),
                          ),
                          SizedBox(height: 2),
                          Text(
                            'This maintenance ticket has been marked as fully resolved.',
                            style: TextStyle(fontSize: 12, color: AppColors.ink700),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 30),
          ],
        ),
      ),
    );
  }
}
