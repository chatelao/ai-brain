import React from 'react';
import { StyleSheet, View, Text, TouchableOpacity, SafeAreaView } from 'react-native';
import * as WebBrowser from 'expo-web-browser';
import * as AuthSession from 'expo-auth-session';
import { useAuth } from '../hooks/useAuth';
import apiClient from '../api/client';
import { theme } from '../theme';

WebBrowser.maybeCompleteAuthSession();

const redirectUri = AuthSession.makeRedirectUri({
  scheme: 'agentcontrol',
});

export default function LoginScreen() {
  const { login } = useAuth();

  const handleLogin = async (provider: 'google' | 'github') => {
    try {
      const baseUrl = apiClient.defaults.baseURL?.replace('/api/', '') || 'https://ai-brain.chatelao.com';
      const authUrl = `${baseUrl}/${provider}/login.php?mobile=1&redirect_uri=${encodeURIComponent(redirectUri)}`;

      const result = await WebBrowser.openAuthSessionAsync(authUrl, redirectUri);

      if (result.type === 'success') {
        const url = new URL(result.url);
        const params = new URLSearchParams(url.search);
        const accessToken = params.get('access_token');
        const refreshToken = params.get('refresh_token');

        if (accessToken && refreshToken) {
          await login(accessToken, refreshToken);
        }
      }
    } catch (error) {
      console.error('Login failed:', error);
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.content}>
        <View style={styles.logoContainer}>
          <Text style={styles.logoEmoji}>🤖</Text>
          <Text style={styles.title}>Agent Control</Text>
        </View>
        <Text style={styles.subtitle}>Manage your AI agents from anywhere.</Text>

        <TouchableOpacity
          style={[styles.button, styles.googleButton]}
          onPress={() => handleLogin('google')}
        >
          <Text style={styles.buttonText}>Login with Google</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.button, styles.githubButton]}
          onPress={() => handleLogin('github')}
        >
          <Text style={styles.githubButtonText}>Login with GitHub</Text>
        </TouchableOpacity>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  content: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: theme.spacing.xl,
  },
  logoContainer: {
    alignItems: 'center',
    marginBottom: theme.spacing.sm,
  },
  logoEmoji: {
    fontSize: 64,
    marginBottom: theme.spacing.sm,
  },
  title: {
    fontSize: theme.typography.xxxl,
    fontWeight: '700',
    color: theme.colors.text,
  },
  subtitle: {
    fontSize: theme.typography.md,
    color: theme.colors.textSecondary,
    marginBottom: theme.spacing.xxl,
    textAlign: 'center',
  },
  button: {
    width: '100%',
    paddingVertical: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
    marginBottom: theme.spacing.md,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 2,
    minHeight: 48,
  },
  googleButton: {
    backgroundColor: theme.colors.primary,
  },
  githubButton: {
    backgroundColor: theme.colors.surface,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  buttonText: {
    fontSize: theme.typography.md,
    fontWeight: '600',
    color: theme.colors.surface,
  },
  githubButtonText: {
    fontSize: theme.typography.md,
    fontWeight: '600',
    color: theme.colors.text,
  },
});
