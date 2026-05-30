import React, { useState, useEffect, useMemo } from 'react';
import {
  StyleSheet,
  View,
  Text,
  SafeAreaView,
  ScrollView,
  Switch,
  TouchableOpacity,
  ActivityIndicator,
  TextInput,
  Alert,
} from 'react-native';
import { Picker } from '@react-native-picker/picker';
import { useProject } from '../hooks/useProject';
import { useUser } from '../hooks/useUser';

const statusGrouping = {
  'CREATED': {
    'created': 'Waiting for Agent'
  },
  'PROCESSING': {
    'analyzing': 'Analyzing',
    'planning': 'Planning',
    'executing': 'Executing',
    'verifying': 'Verifying',
    'implemented': 'Implemented',
    'checking': 'Checking'
  },
  'READY': {
    'ready': 'Ready'
  },
  'FINISHED': {
    'finished': 'Finished'
  },
  'FAILED': {
    'failed_jules': 'Jules Failed',
    'failed_pr': 'PR Failed'
  }
};

export default function ProjectSettingsScreen({ route, navigation }: any) {
  const { id } = route.params;
  const { data: project, isLoading: projectLoading, updateSettings, isUpdatingSettings, updateNotifications, isUpdatingNotifications, deleteProject, isDeleting } = useProject(id);
  const { data: user } = useUser();

  const [activeTab, setActiveTab] = useState<'general' | 'notifications'>('general');
  const [repo, setRepo] = useState('');
  const [accountId, setAccountId] = useState<number | ''>('');

  const githubAccounts = user?.github_accounts || [];

  useEffect(() => {
    if (project) {
      setRepo(project.github_repo || '');
      setAccountId(project.github_account_id || '');
    }
  }, [project]);

  const handleUpdateGeneral = async () => {
    try {
      await updateSettings({
        github_repo: repo,
        github_account_id: accountId as number,
      });
      Alert.alert('Success', 'Project settings updated successfully.');
    } catch (err: any) {
      Alert.alert('Error', err.response?.data?.error || 'Failed to update settings.');
    }
  };

  const handleDelete = () => {
    Alert.alert(
      'Delete Project',
      'Are you sure you want to delete this project? This action cannot be undone.',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Delete',
          style: 'destructive',
          onPress: async () => {
            try {
              await deleteProject();
              navigation.navigate('Dashboard');
            } catch (err: any) {
              Alert.alert('Error', err.response?.data?.error || 'Failed to delete project.');
            }
          },
        },
      ]
    );
  };

  if (projectLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#2563eb" />
      </View>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
          <Text style={styles.backButtonText}>← Back</Text>
        </TouchableOpacity>
        <Text style={styles.title}>Project Settings</Text>
        <View style={{ width: 60 }} />
      </View>

      <View style={styles.tabBar}>
        <TouchableOpacity
          style={[styles.tab, activeTab === 'general' && styles.activeTab]}
          onPress={() => setActiveTab('general')}
        >
          <Text style={[styles.tabText, activeTab === 'general' && styles.activeTabText]}>General</Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.tab, activeTab === 'notifications' && styles.activeTab]}
          onPress={() => setActiveTab('notifications')}
        >
          <Text style={[styles.tabText, activeTab === 'notifications' && styles.activeTabText]}>Notifications</Text>
        </TouchableOpacity>
      </View>

      <ScrollView style={styles.content}>
        {activeTab === 'general' && (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>General Configuration</Text>

            <View style={styles.inputGroup}>
              <Text style={styles.label}>GitHub Account</Text>
              <View style={styles.pickerContainer}>
                <Picker
                  selectedValue={accountId}
                  onValueChange={(itemValue) => setAccountId(itemValue as number)}
                  style={styles.picker}
                >
                  {githubAccounts.map((account) => (
                    <Picker.Item
                      key={account.github_account_id}
                      label={account.github_username || ''}
                      value={account.github_account_id}
                    />
                  ))}
                </Picker>
              </View>
            </View>

            <View style={styles.inputGroup}>
              <Text style={styles.label}>Repository (owner/repo)</Text>
              <TextInput
                style={styles.input}
                value={repo}
                onChangeText={setRepo}
                placeholder="owner/repo"
                autoCapitalize="none"
              />
            </View>

            <TouchableOpacity
              style={[styles.saveButton, isUpdatingSettings && styles.disabledButton]}
              onPress={handleUpdateGeneral}
              disabled={isUpdatingSettings}
            >
              {isUpdatingSettings ? (
                <ActivityIndicator color="#ffffff" size="small" />
              ) : (
                <Text style={styles.saveButtonText}>Save Changes</Text>
              )}
            </TouchableOpacity>

            <View style={styles.separator} />

            <Text style={styles.sectionTitle}>Danger Zone</Text>
            <TouchableOpacity
              style={[styles.deleteButton, isDeleting && styles.disabledButton]}
              onPress={handleDelete}
              disabled={isDeleting}
            >
              {isDeleting ? (
                <ActivityIndicator color="#ffffff" size="small" />
              ) : (
                <Text style={styles.deleteButtonText}>Delete Project</Text>
              )}
            </TouchableOpacity>
          </View>
        )}

        {activeTab === 'notifications' && (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Status Subscriptions</Text>
            <Text style={styles.sectionDescription}>
              Choose which status changes trigger a notification for this project.
            </Text>

            {Object.entries(statusGrouping).map(([group, statuses]) => (
              <View key={group} style={styles.groupContainer}>
                <Text style={styles.groupTitle}>{group}</Text>
                {Object.entries(statuses).map(([statusId, label]) => (
                  <View key={statusId} style={styles.settingItem}>
                    <Text style={styles.settingLabel}>{label}</Text>
                    <Switch
                      value={project?.notification_settings?.[statusId]}
                      onValueChange={async () => {
                        const newSettings = {
                          ...project?.notification_settings,
                          [statusId]: !project?.notification_settings?.[statusId],
                        };
                        try {
                          await updateNotifications(newSettings);
                        } catch (err: any) {
                          Alert.alert('Error', 'Failed to update notification settings.');
                        }
                      }}
                      disabled={isUpdatingNotifications}
                      trackColor={{ false: '#d1d5db', true: '#93c5fd' }}
                      thumbColor={project?.notification_settings?.[statusId] ? '#2563eb' : '#f4f3f4'}
                    />
                  </View>
                ))}
              </View>
            ))}
          </View>
        )}
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
  tabBar: {
    flexDirection: 'row',
    backgroundColor: '#ffffff',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  tab: {
    flex: 1,
    paddingVertical: 14,
    alignItems: 'center',
    borderBottomWidth: 2,
    borderBottomColor: 'transparent',
  },
  activeTab: {
    borderBottomColor: '#2563eb',
  },
  tabText: {
    fontSize: 14,
    fontWeight: '500',
    color: '#6b7280',
  },
  activeTabText: {
    color: '#2563eb',
  },
  content: {
    flex: 1,
  },
  section: {
    padding: 16,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#111827',
    marginBottom: 8,
  },
  sectionDescription: {
    fontSize: 14,
    color: '#6b7280',
    marginBottom: 20,
  },
  inputGroup: {
    marginBottom: 16,
  },
  label: {
    fontSize: 12,
    fontWeight: '700',
    color: '#374151',
    textTransform: 'uppercase',
    marginBottom: 6,
  },
  input: {
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
    color: '#111827',
  },
  pickerContainer: {
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    overflow: 'hidden',
  },
  picker: {
    height: 50,
    width: '100%',
  },
  saveButton: {
    backgroundColor: '#2563eb',
    paddingVertical: 14,
    borderRadius: 8,
    alignItems: 'center',
    marginTop: 8,
  },
  saveButtonText: {
    color: '#ffffff',
    fontSize: 16,
    fontWeight: '600',
  },
  disabledButton: {
    opacity: 0.6,
  },
  separator: {
    height: 1,
    backgroundColor: '#e5e7eb',
    marginVertical: 24,
  },
  deleteButton: {
    backgroundColor: '#ef4444',
    paddingVertical: 14,
    borderRadius: 8,
    alignItems: 'center',
  },
  deleteButtonText: {
    color: '#ffffff',
    fontSize: 16,
    fontWeight: '600',
  },
  groupContainer: {
    marginBottom: 24,
    backgroundColor: '#ffffff',
    borderRadius: 12,
    padding: 12,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  groupTitle: {
    fontSize: 12,
    fontWeight: '700',
    color: '#6b7280',
    textTransform: 'uppercase',
    marginBottom: 8,
    marginLeft: 4,
  },
  settingItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 10,
    paddingHorizontal: 4,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
  },
  settingLabel: {
    fontSize: 15,
    color: '#374151',
  },
});
