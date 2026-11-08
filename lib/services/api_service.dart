import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Thrown whenever the API responds with ok:false, a non-2xx status, or network failure.
class ApiException implements Exception {
  final String message;
  ApiException(this.message);
  @override
  String toString() => message;
}

class ApiService {
  // Change this if your domain is ever different.
  static const String baseUrl = 'https://pwestora.com/api';

  static const String noInternetMessage = 'No internet connection. Please check your network and try again.';
  static const String serverErrorMessage = 'Unable to reach the server right now. Please try again later.';

  /// Detects whether an exception or error message is caused by network issues or no internet connection.
  static bool isNetworkError(dynamic error) {
    if (error == null) return false;
    if (error is SocketException ||
        error is http.ClientException ||
        error is TimeoutException ||
        error is HandshakeException) {
      return true;
    }
    final str = error.toString().toLowerCase();
    return str.contains('socketexception') ||
        str.contains('clientexception') ||
        str.contains('failed host lookup') ||
        str.contains('no address associated with hostname') ||
        str.contains('connection refused') ||
        str.contains('connection reset') ||
        str.contains('connection closed') ||
        str.contains('timed out') ||
        str.contains('handshakeexception') ||
        str.contains('no internet') ||
        str.contains('network') ||
        str.contains('offline') ||
        str.contains('os error');
  }

  /// Converts any exception into a clean, human-readable user message.
  static String errorMessage(dynamic error) {
    if (error == null) return 'Something went wrong. Please try again.';
    if (isNetworkError(error)) {
      return noInternetMessage;
    }

    final raw = error.toString().replaceAll('ApiException: ', '').replaceAll('Exception: ', '').trim();
    final lower = raw.toLowerCase();

    if (lower.contains('api endpoint not found') ||
        lower.contains('php files') ||
        lower.contains('server error') ||
        lower.contains('unexpected server response') ||
        lower.contains('syntax error') ||
        lower.contains('fatal error') ||
        lower.contains('500') ||
        lower.contains('502') ||
        lower.contains('503') ||
        lower.contains('504')) {
      return serverErrorMessage;
    }

    if (raw.isEmpty) {
      return 'Something went wrong. Please try again.';
    }

    return raw;
  }

  /// Wraps any raw exception into an ApiException with clean user-facing copy.
  static ApiException wrapError(dynamic e) {
    if (e is ApiException) return e;
    if (isNetworkError(e)) {
      return ApiException(noInternetMessage);
    }
    return ApiException(errorMessage(e));
  }

  // ---------------- Token storage ----------------

  static Future<void> saveSession(
    String token,
    Map<String, dynamic> user,
  ) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('api_token', token);
    await prefs.setString('user_name', user['full_name'] ?? '');
    await prefs.setString('company_name', user['company_name'] ?? '');
    await prefs.setString('user_email', user['email'] ?? '');
    await prefs.setString('user_phone', user['phone'] ?? '');
  }

  static Future<String?> getToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('api_token');
  }

  static Future<String?> getUserName() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('user_name');
  }

  static Future<String?> getCompanyName() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('company_name');
  }

  static Future<String?> getUserEmail() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('user_email');
  }

  static Future<String?> getUserPhone() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('user_phone');
  }

  static Future<Map<String, dynamic>> fetchUserProfile() async {
    // 1. Try profile_me.php
    try {
      final res = await _getJson('profile_me.php', auth: true);
      if (res['user'] != null) {
        final u = res['user'] as Map<String, dynamic>;
        final prefs = await SharedPreferences.getInstance();
        if (u['full_name'] != null) await prefs.setString('user_name', u['full_name']);
        if (u['company_name'] != null) await prefs.setString('company_name', u['company_name']);
        if (u['email'] != null) await prefs.setString('user_email', u['email']);
        if (u['phone'] != null) await prefs.setString('user_phone', u['phone']);
        return u;
      }
    } catch (_) {}

    // 2. Try lease_status.php
    try {
      final lRes = await _getJson('lease_status.php', auth: true);
      if (lRes['user'] != null) {
        final u = lRes['user'] as Map<String, dynamic>;
        final prefs = await SharedPreferences.getInstance();
        if (u['full_name'] != null) await prefs.setString('user_name', u['full_name']);
        if (u['company_name'] != null) await prefs.setString('company_name', u['company_name']);
        if (u['email'] != null) await prefs.setString('user_email', u['email']);
        if (u['phone'] != null) await prefs.setString('user_phone', u['phone']);
        return u;
      }
    } catch (_) {}

    final name = await getUserName();
    final company = await getCompanyName();
    final email = await getUserEmail();
    final phone = await getUserPhone();
    return {
      'full_name': name ?? '',
      'company_name': company ?? '',
      'email': email ?? '',
      'phone': phone ?? '',
    };
  }

  static Future<void> updateUserProfile({
    required String fullName,
    String? companyName,
    required String email,
    required String phone,
  }) async {
    try {
      await _postJson('profile_update.php', {
        'full_name': fullName,
        'company_name': ?companyName,
        'email': email,
        'phone': phone,
      }, auth: true);
    } catch (_) {
      // Graceful fallback to local cache
    }
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('user_name', fullName);
    if (companyName != null) await prefs.setString('company_name', companyName);
    await prefs.setString('user_email', email);
    await prefs.setString('user_phone', phone);
  }

  static Future<void> clearSession() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('api_token');
    await prefs.remove('user_name');
    await prefs.remove('company_name');
    await prefs.remove('user_email');
    await prefs.remove('user_phone');
  }

  // ---------------- Recently viewed (stored locally on-device) ----------------

  static Future<List<Map<String, dynamic>>> getRecentlyViewed() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getStringList('recently_viewed') ?? [];
    return raw.map((s) => jsonDecode(s) as Map<String, dynamic>).toList();
  }

  static Future<void> addRecentlyViewed(Map<String, dynamic> property) async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getStringList('recently_viewed') ?? [];
    final list = raw.map((s) => jsonDecode(s) as Map<String, dynamic>).toList();
    list.removeWhere((p) => p['id'] == property['id']);
    list.insert(0, property);
    final trimmed = list.take(8).toList();
    await prefs.setStringList(
      'recently_viewed',
      trimmed.map((p) => jsonEncode(p)).toList(),
    );
  }

  // ---------------- Internal helpers ----------------

  static Future<Map<String, dynamic>> _postJson(
    String path,
    Map<String, dynamic> body, {
    bool auth = false,
  }) async {
    final headers = {'Content-Type': 'application/json'};
    if (auth) {
      final token = await getToken();
      if (token != null) headers['Authorization'] = 'Bearer $token';
    }
    try {
      final res = await http.post(
        Uri.parse('$baseUrl/$path'),
        headers: headers,
        body: jsonEncode(body),
      ).timeout(const Duration(seconds: 25));
      return _handleResponse(res);
    } on ApiException {
      rethrow;
    } catch (e) {
      throw wrapError(e);
    }
  }

  static Future<Map<String, dynamic>> _getJson(
    String path, {
    bool auth = false,
  }) async {
    final headers = <String, String>{};
    if (auth) {
      final token = await getToken();
      if (token != null) headers['Authorization'] = 'Bearer $token';
    }
    try {
      final res = await http.get(
        Uri.parse('$baseUrl/$path'),
        headers: headers,
      ).timeout(const Duration(seconds: 25));
      return _handleResponse(res);
    } on ApiException {
      rethrow;
    } catch (e) {
      throw wrapError(e);
    }
  }

  static Map<String, dynamic> _handleResponse(http.Response res) {
    Map<String, dynamic> data;
    try {
      data = jsonDecode(res.body) as Map<String, dynamic>;
    } catch (_) {
      if (res.statusCode == 404) {
        throw ApiException('Unable to reach the service right now. Please try again later.');
      }
      if (res.statusCode >= 500) {
        throw ApiException('Server is temporarily unavailable. Please try again later.');
      }
      throw ApiException(
        'Unable to connect to the server. Please try again.',
      );
    }
    if (res.statusCode >= 200 && res.statusCode < 300 && data['ok'] == true) {
      return data;
    }
    throw ApiException(
      data['error'] ?? 'Something went wrong. Please try again.',
    );
  }

  // ---------------- Auth ----------------

  static Future<void> sendRegistrationOtp({
    required String phone,
    required String email,
  }) async {
    await _postJson('auth_send_otp.php', {
      'phone': phone,
      'email': email,
    });
  }

  static Future<void> register({
    required String fullName,
    required String email,
    required String phone,
    required String password,
    required String otpCode,
  }) async {
    final data = await _postJson('auth_register.php', {
      'full_name': fullName,
      'email': email,
      'phone': phone,
      'password': password,
      'otp_code': otpCode,
    });
    await saveSession(data['token'], data['user']);
  }

  static Future<void> login({
    required String email,
    required String password,
  }) async {
    final data = await _postJson('auth_login.php', {
      'email': email,
      'password': password,
    });
    await saveSession(data['token'], data['user']);
  }

  // ---------------- Properties ----------------

  static Future<List<dynamic>> fetchProperties() async {
    final data = await _getJson('properties_list.php');
    return data['properties'] as List<dynamic>;
  }

  static Future<Map<String, dynamic>> fetchPropertyDetail(int id) async {
    final data = await _getJson('properties_show.php?id=$id');
    return data['property'] as Map<String, dynamic>;
  }

  // ---------------- Applications & Checklist ----------------

  static Future<int> submitApplication({
    required int propertyId,
    required String businessName,
    required int termMonths,
    required double rent,
    Map<String, dynamic>? checklistNegotiation,
    Map<String, Map<String, dynamic>>? documents,
  }) async {
    final token = await getToken();
    final uri = Uri.parse('$baseUrl/applications_create.php');
    final request = http.MultipartRequest('POST', uri);

    if (token != null) {
      request.headers['Authorization'] = 'Bearer $token';
    }

    request.fields['property_id'] = propertyId.toString();
    request.fields['business_name'] = businessName;
    request.fields['term_months'] = termMonths.toString();
    request.fields['rent'] = rent.toString();
    if (checklistNegotiation != null) {
      request.fields['checklist_negotiation'] = jsonEncode(checklistNegotiation);
    }

    if (documents != null) {
      for (final entry in documents.entries) {
        final key = entry.key;
        final doc = entry.value;
        final bytes = doc['bytes'] as List<int>?;
        final filename = doc['filename'] as String? ?? '$key.jpg';
        if (bytes != null && bytes.isNotEmpty) {
          request.files.add(
            http.MultipartFile.fromBytes(key, bytes, filename: filename),
          );
        }
      }
    }

    try {
      final streamedRes = await request.send().timeout(const Duration(seconds: 40));
      final res = await http.Response.fromStream(streamedRes);
      final data = _handleResponse(res);
      return data['application_id'] as int;
    } on ApiException {
      rethrow;
    } catch (e) {
      throw wrapError(e);
    }
  }

  static Future<List<dynamic>> fetchMyApplications() async {
    final data = await _getJson('applications_mine.php', auth: true);
    return data['applications'] as List<dynamic>;
  }

  static Future<bool> replyToApplication({
    required int applicationId,
    required String message,
  }) async {
    final body = <String, dynamic>{
      'application_id': applicationId,
      'message': message,
    };
    final data = await _postJson('applications_reply.php', body, auth: true);
    return data['ok'] == true;
  }

  static Future<List<Map<String, dynamic>>> fetchChecklistItems() async {
    try {
      final data = await _getJson('checklist_items.php');
      final raw = (data['items'] as List<dynamic>?) ?? [];
      return raw.map((e) => Map<String, dynamic>.from(e as Map)).toList();
    } catch (_) {
      return [];
    }
  }

  static Future<Map<String, dynamic>> fetchContractView({int? tenantId}) async {
    final query = (tenantId != null && tenantId > 0) ? '?tenant_id=$tenantId' : '';
    final data = await _getJson('contract_view.php$query', auth: true);
    return data;
  }

  static Future<Map<String, dynamic>> acceptContract({
    required int tenantId,
    required int version,
  }) async {
    final res = await _postJson(
      'contract_accept.php',
      {
        'tenant_id': tenantId,
        'version': version,
      },
      auth: true,
    );
    return res;
  }

  static Future<Map<String, dynamic>> rejectContract({
    required int tenantId,
    required int version,
    required String reason,
  }) async {
    final res = await _postJson(
      'contract_reject.php',
      {
        'tenant_id': tenantId,
        'version': version,
        'reason': reason,
      },
      auth: true,
    );
    return res;
  }

  // ---------------- Lease status ----------------

  /// Returns null if no active lease, otherwise the lease details map.
  static Future<Map<String, dynamic>?> fetchLeaseStatus() async {
    try {
      final data = await _getJson('lease_status.php', auth: true);
      if (data['user'] != null && data['user'] is Map) {
        final u = data['user'] as Map<String, dynamic>;
        final prefs = await SharedPreferences.getInstance();
        if (u['full_name'] != null) await prefs.setString('user_name', u['full_name']);
        if (u['email'] != null) await prefs.setString('user_email', u['email']);
        if (u['phone'] != null) await prefs.setString('user_phone', u['phone']);
      }
      if (data['has_lease'] == true && data['lease'] is Map) {
        return Map<String, dynamic>.from(data['lease'] as Map);
      }
    } catch (_) {}

    // Fallback: Check if user has an approved application in applications_mine.php
    try {
      final apps = await fetchMyApplications();
      for (final item in apps) {
        if (item is Map && (item['status'] ?? '').toString().toLowerCase() == 'approved') {
          return {
            'tenant_id': item['id'],
            'application_id': item['id'],
            'property_id': item['property_id'],
            'property_name': item['property_name'] ?? 'Commercial Stall',
            'property_address': item['property_address'] ?? 'Commercial Space',
            'business_name': item['business_name'] ?? '',
            'rent': (item['rent'] as num?)?.toDouble() ?? 12000.0,
            'term_months': (item['term_months'] as num?)?.toInt() ?? 12,
            'is_approved_application': true,
          };
        }
      }
    } catch (_) {}

    return null;
  }

  /// Returns all active commercial properties/stalls for the user (GlobeOne-style multi-account switcher)
  static Future<List<Map<String, dynamic>>> fetchAllActiveLeases({Map<String, dynamic>? initialLease}) async {
    final list = <Map<String, dynamic>>[];
    final seen = <String>{};

    void addLease(Map<String, dynamic> l) {
      final name = (l['property_name'] ?? l['name'] ?? '').toString().trim().toLowerCase();
      final pId = (l['property_id'] ?? l['tenant_id'] ?? '').toString();
      final key = name.isNotEmpty ? name : pId;
      if (key.isNotEmpty && !seen.contains(key)) {
        seen.add(key);
        list.add(Map<String, dynamic>.from(l));
      }
    }

    if (initialLease != null && initialLease.isNotEmpty) {
      addLease(initialLease);
    }

    // 1. Try lease_status.php for multiple leases
    try {
      final data = await _getJson('lease_status.php', auth: true);
      if (data['leases'] is List) {
        for (final item in data['leases']) {
          if (item is Map) addLease(Map<String, dynamic>.from(item));
        }
      } else if (data['lease'] is Map) {
        addLease(Map<String, dynamic>.from(data['lease'] as Map));
      }
    } catch (_) {}

    // 2. Query approved applications from applications_mine.php
    try {
      final apps = await fetchMyApplications();
      for (final app in apps) {
        if (app is Map && (app['status'] ?? '').toString().toLowerCase() == 'approved') {
          addLease({
            'tenant_id': app['id'],
            'application_id': app['id'],
            'property_id': app['property_id'],
            'property_name': app['property_name'] ?? 'Commercial Stall',
            'property_address': app['property_address'] ?? 'Commercial Space',
            'business_name': app['business_name'] ?? '',
            'rent': (app['rent'] as num?)?.toDouble() ?? 12000.0,
            'term_months': (app['term_months'] as num?)?.toInt() ?? 12,
            'cover_photo': app['cover_photo'] ?? '',
            'is_approved_application': true,
          });
        }
      }
    } catch (_) {}

    // If still empty but initialLease was given, ensure it is present
    if (list.isEmpty && initialLease != null) {
      list.add(Map<String, dynamic>.from(initialLease));
    }

    return list;
  }

  // ---------------- Maintenance tickets ----------------

  static Future<List<dynamic>> fetchTickets({int? tenantId}) async {
    try {
      final query = (tenantId != null && tenantId > 0) ? '?tenant_id=$tenantId' : '';
      final data = await _getJson('tickets_list.php$query', auth: true);
      return (data['tickets'] as List<dynamic>?) ?? [];
    } catch (_) {
      return [];
    }
  }

  static Future<int> createTicket({
    required String category,
    required String priority,
    required String title,
    int? tenantId,
    List<XFile>? photos,
  }) async {
    if (photos != null && photos.isNotEmpty) {
      final token = await getToken();
      final uri = Uri.parse('$baseUrl/tickets_create.php');
      final request = http.MultipartRequest('POST', uri);
      if (token != null) {
        request.headers['Authorization'] = 'Bearer $token';
      }
      request.fields['category'] = category;
      request.fields['priority'] = priority;
      request.fields['title'] = title;
      if (tenantId != null && tenantId > 0) {
        request.fields['tenant_id'] = tenantId.toString();
      }

      for (int i = 0; i < photos.length; i++) {
        final photo = photos[i];
        final bytes = await photo.readAsBytes();
        final filename = photo.name.isNotEmpty ? photo.name : 'photo_$i.jpg';
        request.files.add(
          http.MultipartFile.fromBytes('photos[]', bytes, filename: filename),
        );
      }

      try {
        final streamedRes = await request.send().timeout(const Duration(seconds: 40));
        final res = await http.Response.fromStream(streamedRes);
        final data = _handleResponse(res);
        return data['ticket_id'] as int;
      } on ApiException {
        rethrow;
      } catch (e) {
        throw wrapError(e);
      }
    }

    final payload = <String, dynamic>{
      'category': category,
      'priority': priority,
      'title': title,
    };
    if (tenantId != null && tenantId > 0) {
      payload['tenant_id'] = tenantId;
    }
    final data = await _postJson('tickets_create.php', payload, auth: true);
    return data['ticket_id'] as int;
  }

  // ---------------- Payments ----------------

  static Future<List<dynamic>> fetchPayments({int? tenantId}) async {
    try {
      final query = (tenantId != null && tenantId > 0) ? '?tenant_id=$tenantId' : '';
      final data = await _getJson('payments_list.php$query', auth: true);
      return (data['payments'] as List<dynamic>?) ?? [];
    } catch (_) {
      return [];
    }
  }

  static Future<Map<String, dynamic>> submitRentPayment({
    required double amount,
    required String target,
    required String referenceNo,
    String? note,
    int? tenantId,
  }) async {
    final payload = <String, dynamic>{
      'amount': amount,
      'method': 'bank_transfer',
      'target': target,
      'reference_no': referenceNo,
      'note': note ?? '',
    };
    if (tenantId != null && tenantId > 0) {
      payload['tenant_id'] = tenantId;
    }

    // Post directly to the live server
    final res = await _postJson('payments_submit.php', payload, auth: true);
    return res;
  }

  // ---------------- Messages ----------------

  static Future<Map<String, dynamic>> fetchMessagesThread({int? tenantId}) async {
    final query = (tenantId != null && tenantId > 0) ? '?tenant_id=$tenantId' : '';
    final data = await _getJson('messages_thread.php$query', auth: true);
    return data;
  }

  static Future<void> sendMessage(String text, {int? tenantId}) async {
    final payload = <String, dynamic>{'body': text};
    if (tenantId != null && tenantId > 0) {
      payload['tenant_id'] = tenantId;
    }
    await _postJson('messages_send.php', payload, auth: true);
  }

  // ---------------- Installment / Payment Arrangements ----------------

  static const String _kDemoRestructuringKey = 'demo_restructuring_plan';

  static Future<String> getDemoRestructuringPlan() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_kDemoRestructuringKey) ?? 'none';
  }

  static Future<void> setDemoRestructuringPlan(String planKey) async {
    final prefs = await SharedPreferences.getInstance();
    if (planKey == 'none') {
      await prefs.remove(_kDemoRestructuringKey);
    } else {
      await prefs.setString(_kDemoRestructuringKey, planKey);
    }
  }

  static Future<List<dynamic>> fetchInstallmentRequests({int? tenantId}) async {
    // Check demo override first
    final demoPlan = await getDemoRestructuringPlan();
    if (demoPlan == '2_payments_part1' || demoPlan == '2_payments') {
      return [
        {
          'id': 999,
          'plan_label': '2 payments',
          'status': 'approved',
          'current_part': 1,
          'total_parts': 2,
          'due_month': 'October',
          'reason': 'Cash flow delay this month',
          'amount': 2500,
          'created_at': 'Today (Demo)',
        }
      ];
    } else if (demoPlan == '2_payments_part2') {
      return [
        {
          'id': 999,
          'plan_label': '2 payments',
          'status': 'approved',
          'current_part': 2,
          'total_parts': 2,
          'due_month': 'November',
          'reason': 'Cash flow delay this month',
          'amount': 2500,
          'created_at': 'Today (Demo)',
        }
      ];
    } else if (demoPlan == '3_payments') {
      return [
        {
          'id': 998,
          'plan_label': '3 payments',
          'status': 'approved',
          'current_part': 1,
          'total_parts': 3,
          'due_month': 'October',
          'reason': 'Seasonal slowdown in business',
          'amount': 2500,
          'created_at': 'Today (Demo)',
        }
      ];
    } else if (demoPlan == 'pending') {
      return [
        {
          'id': 997,
          'plan_label': '2 payments',
          'status': 'pending',
          'current_part': 1,
          'total_parts': 2,
          'due_month': 'October',
          'reason': 'Cash flow delay this month',
          'amount': 2500,
          'created_at': 'Today (Demo)',
        }
      ];
    }

    try {
      final query = (tenantId != null && tenantId > 0) ? '?tenant_id=$tenantId' : '';
      final data = await _getJson('requests_list.php$query', auth: true);
      return data['requests'] as List<dynamic>? ?? [];
    } catch (_) {
      return [];
    }
  }

  static Future<int> createInstallmentRequest({
    required String reason,
    required String planLabel,
    int? tenantId,
  }) async {
    final payload = <String, dynamic>{
      'reason': reason,
      'plan_label': planLabel,
    };
    if (tenantId != null && tenantId > 0) {
      payload['tenant_id'] = tenantId;
    }
    final data = await _postJson('requests_create.php', payload, auth: true);
    return data['request_id'] as int;
  }

  static Future<bool> cancelInstallmentRequest(int requestId) async {
    // If demo mode is active or request is a demo ID, clear it immediately
    final demoPlan = await getDemoRestructuringPlan();
    if (demoPlan != 'none' || requestId == 997 || requestId == 998 || requestId == 999) {
      await setDemoRestructuringPlan('none');
      return true;
    }
    try {
      final data = await _postJson('requests_cancel.php', {
        'request_id': requestId,
      }, auth: true);
      return data['ok'] == true;
    } catch (_) {
      return false;
    }
  }

  // ---------------- 7-Day Grace Period & Rent Arrears Status ----------------
  static const String _kDemoRentStatusKey = 'demo_rent_grace_status';

  static Future<String> getDemoRentStatus() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_kDemoRentStatusKey) ?? 'normal';
  }

  static Future<void> setDemoRentStatus(String status) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kDemoRentStatusKey, status);
  }

  // ---------------- Conflict Resolution / Violations (Section C) ----------------

  static String _demoStrikeKey(int? tenantId) =>
      tenantId != null && tenantId > 0
          ? 'demo_violation_strike_level_$tenantId'
          : 'demo_violation_strike_level';

  static String _demoAckKey(int? tenantId) =>
      tenantId != null && tenantId > 0
          ? 'demo_acknowledged_strikes_$tenantId'
          : 'demo_acknowledged_strikes';

  static String _demoStrikeCatKey(int? tenantId) =>
      tenantId != null && tenantId > 0
          ? 'demo_violation_strike_category_$tenantId'
          : 'demo_violation_strike_category';

  static Future<int> getDemoStrikeLevel({int? tenantId}) async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getInt(_demoStrikeKey(tenantId)) ?? 0;
  }

  static Future<void> setDemoStrikeLevel(int level, {int? tenantId}) async {
    final prefs = await SharedPreferences.getInstance();
    final strikeKey = _demoStrikeKey(tenantId);
    await prefs.setInt(strikeKey, level);
    // Reset acknowledged set if we switch levels
    final ackKey = _demoAckKey(tenantId);
    final ackList = prefs.getStringList(ackKey) ?? [];
    if (level == 0) {
      await prefs.remove(ackKey);
    } else {
      // Remove this level from acknowledged list so modal triggers
      ackList.remove(level.toString());
      await prefs.setStringList(ackKey, ackList);
    }
  }

  static Future<String> getDemoStrikeCategory({int? tenantId}) async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_demoStrikeCatKey(tenantId)) ?? 'past_due';
  }

  static Future<void> setDemoStrikeCategory(String cat, {int? tenantId}) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_demoStrikeCatKey(tenantId), cat);
  }

  static Future<List<Map<String, dynamic>>> fetchViolations({
    int? tenantId,
    String? lessorName,
  }) async {
    try {
      final query = (tenantId != null && tenantId > 0) ? '?tenant_id=$tenantId' : '';
      final data = await _getJson('violations_list.php$query', auth: true);
      if (data['violations'] is List) {
        final raw = data['violations'] as List<dynamic>;
        var list = raw.map((e) => Map<String, dynamic>.from(e as Map)).toList();

        // 1. Strict client-side filter by tenantId (if present in the record)
        if (tenantId != null && tenantId > 0) {
          list = list.where((v) {
            final vTid = v['tenant_id'];
            if (vTid == null) return true;
            final int? vid = vTid is int ? vTid : int.tryParse(vTid.toString());
            return vid == null || vid == tenantId;
          }).toList();
        }

        // 2. Strict client-side filter by lessorName (prevents violations from other landlords leaking)
        if (lessorName != null && lessorName.trim().isNotEmpty) {
          final target = lessorName.trim().toLowerCase();
          list = list.where((v) {
            final vLessor = (v['lessor_name'] ?? '').toString().trim().toLowerCase();
            if (vLessor.isEmpty) return true;
            return vLessor == target;
          }).toList();
        }

        return list;
      }
    } catch (_) {
      // Offline or server connection fallback
    }

    // Fallback based on Demo Strike Level (isolated per building / tenantId)
    final level = await getDemoStrikeLevel(tenantId: tenantId);
    if (level <= 0) return [];

    final prefs = await SharedPreferences.getInstance();
    final ackList = prefs.getStringList(_demoAckKey(tenantId)) ?? [];
    final isAck = ackList.contains(level.toString());
    final cat = await getDemoStrikeCategory(tenantId: tenantId);
    final isPastDue = cat == 'past_due';

    final list = <Map<String, dynamic>>[];
    if (level >= 1) {
      list.add({
        'id': 101,
        'category': isPastDue ? 'Unpaid Rent Default (Past Due)' : 'Late Operating Hours',
        'strike': 1,
        'clause': isPastDue
            ? 'Clause 3.1 — 7-Day Grace Period Expiration & Payment Default'
            : 'Clause 8.2 — Standard Operating Hours & Curfew',
        'description': isPastDue
            ? 'October monthly rent remained unpaid after the 7-calendar-day grace period expired on November 6 without an approved arrangement. First formal default citation recorded.'
            : 'Stall was observed operating past the mandated commercial closing time of 10:00 PM without prior written permit.',
        'penalty_amount': 0.0,
        'issued_at': '2026-11-07 08:30:00',
        'acknowledged': (level > 1 || isAck) ? 1 : 0,
        'image_url': isPastDue
            ? 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?w=500&q=80'
            : 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=500&q=80',
      });
    }
    if (level >= 2) {
      list.add({
        'id': 102,
        'category': isPastDue ? 'Recurrent Payment Default (2nd Past Due)' : 'Improper Waste Disposal',
        'strike': 2,
        'clause': isPastDue
            ? 'Clause 3.2 — Contractual Late Penalties & Arrears Default'
            : 'Clause 12.4 — Common Area Sanitation & Waste Disposal',
        'description': isPastDue
            ? 'Second consecutive monthly rent default recorded. Contractual late penalty fee of ₱500 automatically injected into billing statement.'
            : 'Commercial garbage bins left outside stall perimeter during non-collection hours, obstructing common hallway walkway.',
        'penalty_amount': 500.0,
        'issued_at': '2026-12-07 09:15:00',
        'acknowledged': (level > 2 || isAck) ? 1 : 0,
        'image_url': isPastDue
            ? 'https://images.unsplash.com/photo-1450133064473-71024230f91b?w=500&q=80'
            : 'https://images.unsplash.com/photo-1611284446314-60a58ac0deb9?w=500&q=80',
      });
    }
    if (level >= 3) {
      list.add({
        'id': 103,
        'category': isPastDue ? 'Chronic Rent Default & Arrears (Eviction Grounds)' : 'Unauthorized Structural Alteration',
        'strike': 3,
        'clause': isPastDue
            ? 'Clause 3.3 — Material Lease Default & Immediate Contract Termination'
            : 'Clause 15.1 — Structural Modifications & Safety Standards',
        'description': isPastDue
            ? 'Account reached the 3rd payment default threshold with accumulated arrears. Formal Notice of Eviction initiated under governing commercial lease code.'
            : 'Heavy electrical wiring installation performed without management authorization and safety inspection permit. Chronic breach threshold reached.',
        'penalty_amount': 1500.0,
        'issued_at': '2027-01-07 14:30:00',
        'acknowledged': isAck ? 1 : 0,
        'image_url': isPastDue
            ? 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?w=500&q=80'
            : 'https://images.unsplash.com/photo-1581092160607-ee22621dd758?w=500&q=80',
      });
    }
    return list.reversed.toList();
  }

  static Future<bool> acknowledgeViolation(int violationId, {int? strike, int? tenantId}) async {
    try {
      final res = await _postJson('violations_acknowledge.php', {
        'violation_id': violationId,
      }, auth: true);
      if (res['ok'] == true) return true;
    } catch (_) {}

    // Save locally for demo mode (isolated per tenantId)
    final prefs = await SharedPreferences.getInstance();
    final ackKey = _demoAckKey(tenantId);
    final ackList = prefs.getStringList(ackKey) ?? [];
    if (strike != null) {
      if (!ackList.contains(strike.toString())) {
        ackList.add(strike.toString());
        await prefs.setStringList(ackKey, ackList);
      }
    }
    return true;
  }
}
