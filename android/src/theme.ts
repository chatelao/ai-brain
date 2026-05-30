import { Dimensions, PixelRatio } from 'react-native';

const { width: SCREEN_WIDTH, height: SCREEN_HEIGHT } = Dimensions.get('window');

// Based on a standard 375px width (iPhone 11 Pro / X)
const scale = (size: number) => (SCREEN_WIDTH / 375) * size;

export const theme = {
  colors: {
    primary: '#2563eb',
    primaryDark: '#1e40af',
    primaryLight: '#eff6ff',
    secondary: '#9333ea',
    indigo: '#4f46e5',
    success: '#10b981',
    error: '#ef4444',
    warning: '#f59e0b',
    background: '#f9fafb',
    surface: '#ffffff',
    text: '#111827',
    textSecondary: '#4b5563',
    textMuted: '#9ca3af',
    border: '#e5e7eb',
    borderLight: '#f3f4f6',
  },
  spacing: {
    xs: scale(4),
    sm: scale(8),
    md: scale(16),
    lg: scale(20),
    xl: scale(24),
    xxl: scale(32),
  },
  borderRadius: {
    sm: 4,
    md: 8,
    lg: 12,
    xl: 16,
    full: 9999,
  },
  typography: {
    xs: scale(10),
    sm: scale(12),
    base: scale(14),
    md: scale(16),
    lg: scale(18),
    xl: scale(20),
    xxl: scale(24),
    xxxl: scale(32),
  },
  layout: {
    screenWidth: SCREEN_WIDTH,
    screenHeight: SCREEN_HEIGHT,
    isSmallDevice: SCREEN_WIDTH < 375,
  }
};

export const normalize = (size: number) => {
  const newSize = (SCREEN_WIDTH / 375) * size;
  return Math.round(PixelRatio.roundToNearestPixel(newSize));
};
