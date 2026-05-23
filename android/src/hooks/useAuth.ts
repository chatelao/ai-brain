import { useState, useEffect } from 'react';
import * as SecureStore from 'expo-secure-store';
import apiClient from '../api/client';

export const useAuth = () => {
  const [isAuthenticated, setIsAuthenticated] = useState<boolean | null>(null);

  useEffect(() => {
    checkToken();
  }, []);

  const checkToken = async () => {
    const token = await SecureStore.getItemAsync('access_token');
    setIsAuthenticated(!!token);
  };

  const login = async (accessToken: string, refreshToken: string) => {
    await SecureStore.setItemAsync('access_token', accessToken);
    await SecureStore.setItemAsync('refresh_token', refreshToken);
    setIsAuthenticated(true);
  };

  const logout = async () => {
    await SecureStore.deleteItemAsync('access_token');
    await SecureStore.deleteItemAsync('refresh_token');
    setIsAuthenticated(false);
  };

  return { isAuthenticated, login, logout, checkToken };
};
