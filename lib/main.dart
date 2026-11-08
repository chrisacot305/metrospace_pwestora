import 'package:flutter/material.dart';
import 'theme.dart';
import 'services/api_service.dart';
import 'services/theme_service.dart';
import 'screens/login_screen.dart';
import 'screens/home_shell.dart';
import 'screens/splash_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await ThemeService.init();
  runApp(const PwestoraApp());
}

class PwestoraApp extends StatelessWidget {
  const PwestoraApp({super.key});

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<ThemeMode>(
      valueListenable: ThemeService.themeModeNotifier,
      builder: (context, currentThemeMode, _) {
        return MaterialApp(
          title: 'Pwestora',
          debugShowCheckedModeBanner: false,
          theme: buildAppTheme(),
          darkTheme: buildDarkAppTheme(),
          themeMode: currentThemeMode,
          home: const SplashScreen(),
        );
      },
    ); 
  }
}

/// Decides whether to show the login screen or jump straight into the app,
/// based on whether a saved session token exists.
class AuthGate extends StatefulWidget {
  const AuthGate({super.key});

  @override
  State<AuthGate> createState() => _AuthGateState();
}

class _AuthGateState extends State<AuthGate> {
  late Future<String?> _tokenFuture;

  @override
  void initState() {
    super.initState();
    _tokenFuture = ApiService.getToken();
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<String?>(
      future: _tokenFuture,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Scaffold(
            body: Center(child: CircularProgressIndicator()),
          );
        }
        final hasToken = snapshot.data != null && snapshot.data!.isNotEmpty;
        return hasToken ? const HomeShell() : const LoginScreen();
      },
    );
  }
}