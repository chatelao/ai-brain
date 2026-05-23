import React from 'react';
import { StyleSheet, View, Text, TouchableOpacity, SafeAreaView } from 'react-native';
import * as WebBrowser from 'expo-web-browser';
import * as AuthSession from 'expo-auth-session';
import { useAuth } from '../hooks/useAuth';
import apiClient from '../api/client';

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
        <Text style={styles.title}>Agent Control</Text>
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
          <Text style={styles.buttonText}>Login with GitHub</Text>
        </TouchableOpacity>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f9fafb',
  },
  content: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  title: {
    fontSize: 32,
    fontWeight: '700',
    color: '#111827',
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 16,
    color: '#6b7280',
    marginBottom: 40,
    textAlign: 'center',
  },
  button: {
    width: '100%',
    paddingVertical: 12,
    borderRadius: 8,
    marginBottom: 12,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 2,
  },
  googleButton: {
    backgroundColor: '#2563eb',
  },
  githubButton: {
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: '#d1d5db',
  },
  buttonText: {
    fontSize: 16,
    fontWeight: '600',
    color: '#ffffff',
  },
});
