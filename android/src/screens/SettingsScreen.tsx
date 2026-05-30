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

export default function SettingsScreen({ navigation }: any) {
  const { data: user, isLoading } = useUser();
  const updateUser = useUpdateUser();

  if (isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#2563eb" />
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
        <View style={{ width: 60 }} />
      </View>

      <ScrollView style={styles.content}>
        {/* Profile Section */}
        <View style={styles.section}>
          <View style={styles.profileInfo}>
            <Image
              source={{ uri: user?.avatar || 'https://www.gravatar.com/avatar/?d=mp' }}
              style={styles.avatar}
            />
            <View>
              <Text style={styles.userName}>{user?.name}</Text>
              <Text style={styles.userEmail}>{user?.email}</Text>
            </View>
          </View>
        </View>

        {/* Notification Channels */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Notification Channels</Text>
          <View style={styles.settingItem}>
            <View>
              <Text style={styles.settingLabel}>In-App Notifications</Text>
              <Text style={styles.settingDescription}>Receive alerts inside the app</Text>
            </View>
            <Switch
              value={user?.notification_settings?.in_app}
              onValueChange={() => toggleChannel('in_app')}
              trackColor={{ false: '#d1d5db', true: '#93c5fd' }}
              thumbColor={user?.notification_settings?.in_app ? '#2563eb' : '#f4f3f4'}
            />
          </View>

          <View style={styles.settingItem}>
            <View>
              <Text style={styles.settingLabel}>Telegram</Text>
              <Text style={styles.settingDescription}>
                {user?.telegram_bot_name ? `@${user.telegram_bot_name}` : 'Not configured'}
              </Text>
            </View>
            <Switch
              value={user?.notification_settings?.telegram}
              onValueChange={() => toggleChannel('telegram')}
              disabled={!user?.telegram_bot_name}
              trackColor={{ false: '#d1d5db', true: '#93c5fd' }}
              thumbColor={user?.notification_settings?.telegram ? '#2563eb' : '#f4f3f4'}
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
                trackColor={{ false: '#d1d5db', true: '#93c5fd' }}
                thumbColor={user?.notification_event_settings?.[item.id as keyof typeof user.notification_event_settings] ? '#2563eb' : '#f4f3f4'}
              />
            </View>
          ))}
        </View>

        <View style={[styles.section, { marginBottom: 40 }]}>
          <Text style={styles.sectionTitle}>Integrations</Text>
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>GitHub Accounts</Text>
            <Text style={styles.infoValue}>{user?.github_accounts?.length || 0} linked</Text>
          </View>
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Jules AI Key</Text>
            <Text style={[styles.infoValue, { color: user?.has_jules_key ? '#10b981' : '#ef4444' }]}>
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
    backgroundColor: '#f9fafb',
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
    padding: 16,
    backgroundColor: '#ffffff',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  backButton: {
    padding: 8,
  },
  backButtonText: {
    color: '#2563eb',
    fontWeight: '600',
  },
  title: {
    fontSize: 18,
    fontWeight: '700',
    color: '#111827',
  },
  content: {
    flex: 1,
  },
  section: {
    backgroundColor: '#ffffff',
    marginTop: 16,
    paddingHorizontal: 16,
    borderTopWidth: 1,
    borderBottomWidth: 1,
    borderTopColor: '#e5e7eb',
    borderBottomColor: '#e5e7eb',
  },
  sectionTitle: {
    fontSize: 12,
    fontWeight: '700',
    color: '#6b7280',
    textTransform: 'uppercase',
    marginTop: 16,
    marginBottom: 8,
    letterSpacing: 0.5,
  },
  profileInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 16,
  },
  avatar: {
    width: 60,
    height: 60,
    borderRadius: 30,
    marginRight: 16,
  },
  userName: {
    fontSize: 18,
    fontWeight: '700',
    color: '#111827',
  },
  userEmail: {
    fontSize: 14,
    color: '#6b7280',
    marginTop: 2,
  },
  settingItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
  },
  settingLabel: {
    fontSize: 16,
    fontWeight: '500',
    color: '#374151',
  },
  settingDescription: {
    fontSize: 13,
    color: '#6b7280',
    marginTop: 1,
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
  },
  infoLabel: {
    fontSize: 15,
    color: '#374151',
  },
  infoValue: {
    fontSize: 15,
    fontWeight: '600',
    color: '#111827',
  },
});
