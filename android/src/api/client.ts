import axios from 'axios';
import * as SecureStore from 'expo-secure-store';

const apiClient = axios.create({
  baseURL: process.env.EXPO_PUBLIC_API_BASE_URL || 'https://ai-brain.chatelao.com/api/',
  headers: {
    'Content-Type': 'application/json',
  },
});

// Request interceptor to attach JWT token
apiClient.interceptors.request.use(
  async (config) => {
    const token = await SecureStore.getItemAsync('access_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor to handle token refresh
apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config;
    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true;
      const refreshToken = await SecureStore.getItemAsync('refresh_token');
      if (refreshToken) {
        try {
          const response = await axios.get(`${apiClient.defaults.baseURL}refresh.php`, {
            headers: { Authorization: `Bearer ${refreshToken}` },
          });
          const { access_token, refresh_token } = response.data;
          await SecureStore.setItemAsync('access_token', access_token);
          await SecureStore.setItemAsync('refresh_token', refresh_token);
          apiClient.defaults.headers.common.Authorization = `Bearer ${access_token}`;
          return apiClient(originalRequest);
        } catch (refreshError) {
          await SecureStore.deleteItemAsync('access_token');
          await SecureStore.deleteItemAsync('refresh_token');
          // Handle redirect to login in the UI layer or via a global event
        }
      }
    }
    return Promise.reject(error);
  }
);

export default apiClient;
