import React from 'react';
import { StyleSheet, View, Text, FlatList, SafeAreaView, TouchableOpacity, ActivityIndicator } from 'react-native';
import { useProjects } from '../hooks/useProjects';
import { useAutorepeatTasks } from '../hooks/useAutorepeatTasks';
import { useAuth } from '../hooks/useAuth';
import { useNotifications } from '../hooks/useNotifications';

export default function DashboardScreen({ navigation }: any) {
  const { data: projects, isLoading: projectsLoading } = useProjects();
  const { data: autorepeatTasks } = useAutorepeatTasks();
  const { data: notificationData } = useNotifications('unread_count');
  const { logout } = useAuth();

  const unreadCount = notificationData?.unread_count || 0;

  const renderProjectItem = ({ item }: any) => (
    <TouchableOpacity
      style={styles.projectCard}
      onPress={() => navigation.navigate('ProjectDetail', { id: item.id, name: item.github_repo })}
    >
      <Text style={styles.projectRepo}>{item.github_repo}</Text>
      <Text style={styles.projectUser}>{item.github_username}</Text>
    </TouchableOpacity>
  );

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.title}>Dashboard</Text>
        <View style={styles.headerActions}>
          <TouchableOpacity
            onPress={() => navigation.navigate('Notifications')}
            style={styles.notifButton}
          >
            <Text style={styles.notifEmoji}>🔔</Text>
            {unreadCount > 0 && (
              <View style={styles.badge}>
                <Text style={styles.badgeText}>{unreadCount > 99 ? '99+' : unreadCount}</Text>
              </View>
            )}
          </TouchableOpacity>
          <TouchableOpacity onPress={logout}>
            <Text style={styles.logoutText}>Logout</Text>
          </TouchableOpacity>
        </View>
      </View>

      {autorepeatTasks && autorepeatTasks.length > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Running Autorepeat</Text>
          {autorepeatTasks.map(task => (
            <TouchableOpacity
              key={task.id}
              style={styles.taskItem}
              onPress={() => navigation.navigate('TaskDetail', { id: task.id })}
            >
              <Text style={styles.taskTitle}>{task.title}</Text>
              <Text style={styles.taskRepo}>{task.github_repo}</Text>
            </TouchableOpacity>
          ))}
        </View>
      )}

      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Your Projects</Text>
          <TouchableOpacity onPress={() => navigation.navigate('GlobalTasks')}>
            <Text style={styles.viewAllTasks}>View All Tasks →</Text>
          </TouchableOpacity>
        </View>
        {projectsLoading ? (
          <ActivityIndicator size="large" color="#2563eb" />
        ) : (
          <FlatList
            data={projects}
            renderItem={renderProjectItem}
            keyExtractor={item => item.id?.toString() || Math.random().toString()}
            contentContainerStyle={styles.listContent}
          />
        )}
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f9fafb',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    backgroundColor: '#ffffff',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  title: {
    fontSize: 24,
    fontWeight: '700',
    color: '#111827',
  },
  headerActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 16,
  },
  notifButton: {
    position: 'relative',
    padding: 4,
  },
  notifEmoji: {
    fontSize: 20,
  },
  badge: {
    position: 'absolute',
    top: -2,
    right: -2,
    backgroundColor: '#ef4444',
    borderRadius: 8,
    minWidth: 16,
    height: 16,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 4,
  },
  badgeText: {
    color: '#ffffff',
    fontSize: 10,
    fontWeight: 'bold',
  },
  logoutText: {
    color: '#ef4444',
    fontWeight: '600',
  },
  section: {
    padding: 20,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: '#374151',
  },
  viewAllTasks: {
    fontSize: 14,
    color: '#2563eb',
    fontWeight: '600',
  },
  projectCard: {
    backgroundColor: '#ffffff',
    padding: 16,
    borderRadius: 8,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  projectRepo: {
    fontSize: 16,
    fontWeight: '600',
    color: '#111827',
  },
  projectUser: {
    fontSize: 14,
    color: '#6b7280',
    marginTop: 4,
  },
  taskItem: {
    backgroundColor: '#eff6ff',
    padding: 12,
    borderRadius: 8,
    marginBottom: 8,
    borderLeftWidth: 4,
    borderLeftColor: '#3b82f6',
  },
  taskTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: '#1e40af',
  },
  taskRepo: {
    fontSize: 12,
    color: '#3b82f6',
    marginTop: 2,
  },
  listContent: {
    paddingBottom: 20,
  },
});
