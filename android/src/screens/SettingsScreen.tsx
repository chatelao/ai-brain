import React from 'react';
import {
  StyleSheet,
  View,
  Text,
  SafeAreaView,
  ScrollView,
  Switch,
  TouchableOpacity,
  ActivityIndicator,
  Image,
} from 'react-native';
import { useUser, useUpdateUser } from '../hooks/useUser';
import { theme } from '../theme';

export default function SettingsScreen({ navigation }: any) {
  const { data: user, isLoading } = useUser();
  const updateUser = useUpdateUser();

  if (isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={theme.colors.primary} />
      </View>
    );
  }

  const toggleChannel = (channel: 'in_app' | 'browser' | 'telegram') => {
    if (!user?.notification_settings) return;
    updateUser.mutate({
      notification_settings: {
        ...user.notification_settings,
        [channel]: !user.notification_settings[channel],
      },
    });
  };

  const toggleEvent = (event: string) => {
    if (!user?.notification_event_settings) return;
    updateUser.mutate({
      notification_event_settings: {
        ...user.notification_event_settings,
        [event]: !user.notification_event_settings[event as keyof typeof user.notification_event_settings],
      },
    });
  };

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
          <Text style={styles.backButtonText}>← Back</Text>
        </TouchableOpacity>
        <Text style={styles.title}>Settings</Text>
        <View style={styles.headerRightPlaceholder} />
      </View>

      <ScrollView style={styles.content}>
        {/* Profile Section */}
        <View style={styles.section}>
          <View style={styles.profileInfo}>
            <Image
              source={{ uri: user?.avatar || 'https://www.gravatar.com/avatar/?d=mp' }}
              style={styles.avatar}
            />
            <View style={styles.userInfo}>
              <Text style={styles.userName}>{user?.name}</Text>
              <Text style={styles.userEmail}>{user?.email}</Text>
            </View>
          </View>
        </View>

        {/* Notification Channels */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Notification Channels</Text>
          <View style={styles.settingItem}>
            <View style={styles.settingTextContainer}>
              <Text style={styles.settingLabel}>In-App Notifications</Text>
              <Text style={styles.settingDescription}>Receive alerts inside the app</Text>
            </View>
            <Switch
              value={user?.notification_settings?.in_app}
              onValueChange={() => toggleChannel('in_app')}
              trackColor={{ false: theme.colors.border, true: '#93c5fd' }}
              thumbColor={user?.notification_settings?.in_app ? theme.colors.primary : '#f4f3f4'}
            />
          </View>

          <View style={styles.settingItem}>
            <View style={styles.settingTextContainer}>
              <Text style={styles.settingLabel}>Telegram</Text>
              <Text style={styles.settingDescription}>
                {user?.telegram_bot_name ? `@${user.telegram_bot_name}` : 'Not configured'}
              </Text>
            </View>
            <Switch
              value={user?.notification_settings?.telegram}
              onValueChange={() => toggleChannel('telegram')}
              disabled={!user?.telegram_bot_name}
              trackColor={{ false: theme.colors.border, true: '#93c5fd' }}
              thumbColor={user?.notification_settings?.telegram ? theme.colors.primary : '#f4f3f4'}
            />
          </View>
        </View>

        {/* Subscriptions */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Status Subscriptions</Text>
          {[
            { id: 'created', label: 'Task Created' },
            { id: 'processing', label: 'Processing' },
            { id: 'ready', label: 'Ready for Review' },
            { id: 'finished', label: 'Finished' },
            { id: 'failed', label: 'Failed' },
          ].map((item) => (
            <View key={item.id} style={styles.settingItem}>
              <Text style={styles.settingLabel}>{item.label}</Text>
              <Switch
                value={user?.notification_event_settings?.[item.id as keyof typeof user.notification_event_settings]}
                onValueChange={() => toggleEvent(item.id)}
                trackColor={{ false: theme.colors.border, true: '#93c5fd' }}
                thumbColor={user?.notification_event_settings?.[item.id as keyof typeof user.notification_event_settings] ? theme.colors.primary : '#f4f3f4'}
              />
            </View>
          ))}
        </View>

        <View style={[styles.section, { marginBottom: theme.spacing.xxl }]}>
          <Text style={styles.sectionTitle}>Integrations</Text>
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>GitHub Accounts</Text>
            <Text style={styles.infoValue}>{user?.github_accounts?.length || 0} linked</Text>
          </View>
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Jules AI Key</Text>
            <Text style={[styles.infoValue, { color: user?.has_jules_key ? theme.colors.success : theme.colors.error }]}>
              {user?.has_jules_key ? 'Configured' : 'Missing'}
            </Text>
          </View>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: theme.spacing.md,
    backgroundColor: theme.colors.surface,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  backButton: {
    padding: theme.spacing.xs,
    minWidth: 60,
  },
  backButtonText: {
    color: theme.colors.primary,
    fontWeight: '600',
    fontSize: theme.typography.md,
  },
  title: {
    fontSize: theme.typography.lg,
    fontWeight: '700',
    color: theme.colors.text,
    textAlign: 'center',
    flex: 1,
  },
  headerRightPlaceholder: {
    minWidth: 60,
  },
  content: {
    flex: 1,
  },
  section: {
    backgroundColor: theme.colors.surface,
    marginTop: theme.spacing.md,
    paddingHorizontal: theme.spacing.md,
    borderTopWidth: 1,
    borderBottomWidth: 1,
    borderTopColor: theme.colors.border,
    borderBottomColor: theme.colors.border,
  },
  sectionTitle: {
    fontSize: theme.typography.xs,
    fontWeight: '700',
    color: theme.colors.textMuted,
    textTransform: 'uppercase',
    marginTop: theme.spacing.md,
    marginBottom: theme.spacing.sm,
    letterSpacing: 0.5,
  },
  profileInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: theme.spacing.md,
  },
  avatar: {
    width: 60,
    height: 60,
    borderRadius: 30,
    marginRight: theme.spacing.md,
  },
  userInfo: {
    flex: 1,
  },
  userName: {
    fontSize: theme.typography.lg,
    fontWeight: '700',
    color: theme.colors.text,
  },
  userEmail: {
    fontSize: theme.typography.base,
    color: theme.colors.textSecondary,
    marginTop: 2,
  },
  settingItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: theme.spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.borderLight,
  },
  settingTextContainer: {
    flex: 1,
    marginRight: theme.spacing.md,
  },
  settingLabel: {
    fontSize: theme.typography.md,
    fontWeight: '500',
    color: theme.colors.textSecondary,
  },
  settingDescription: {
    fontSize: theme.typography.sm,
    color: theme.colors.textMuted,
    marginTop: 2,
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: theme.spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.borderLight,
  },
  infoLabel: {
    fontSize: theme.typography.base,
    color: theme.colors.textSecondary,
  },
  infoValue: {
    fontSize: theme.typography.base,
    fontWeight: '600',
    color: theme.colors.text,
  },
});
