import React from 'react';
import { StyleSheet, View, Text, FlatList, SafeAreaView, TouchableOpacity, ActivityIndicator } from 'react-native';
import { useProjects } from '../hooks/useProjects';
import { useAutorepeatTasks } from '../hooks/useAutorepeatTasks';
import { useAuth } from '../hooks/useAuth';
import { useNotifications } from '../hooks/useNotifications';
import { theme } from '../theme';

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
          <TouchableOpacity onPress={() => navigation.navigate('Settings')}>
            <Text style={styles.settingsEmoji}>⚙️</Text>
          </TouchableOpacity>
          <TouchableOpacity onPress={logout} style={styles.logoutButton}>
            <Text style={styles.logoutText}>Logout</Text>
          </TouchableOpacity>
        </View>
      </View>

      <FlatList
        data={projects}
        renderItem={renderProjectItem}
        keyExtractor={item => item.id?.toString() || Math.random().toString()}
        contentContainerStyle={styles.listContent}
        ListHeaderComponent={
          <>
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
                <TouchableOpacity onPress={() => navigation.navigate('GlobalTasks')} style={styles.viewAllButton}>
                  <Text style={styles.viewAllTasks}>View All Tasks →</Text>
                </TouchableOpacity>
              </View>
              {projectsLoading && <ActivityIndicator size="large" color={theme.colors.primary} />}
            </View>
          </>
        }
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: theme.spacing.lg,
    paddingVertical: theme.spacing.md,
    backgroundColor: theme.colors.surface,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  title: {
    fontSize: theme.typography.xxl,
    fontWeight: '700',
    color: theme.colors.text,
  },
  headerActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.md,
  },
  notifButton: {
    position: 'relative',
    padding: theme.spacing.xs,
  },
  notifEmoji: {
    fontSize: theme.typography.xl,
  },
  settingsEmoji: {
    fontSize: theme.typography.xl,
  },
  badge: {
    position: 'absolute',
    top: -2,
    right: -2,
    backgroundColor: theme.colors.error,
    borderRadius: theme.borderRadius.full,
    minWidth: theme.spacing.md,
    height: theme.spacing.md,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 4,
  },
  badgeText: {
    color: theme.colors.surface,
    fontSize: theme.typography.xs,
    fontWeight: 'bold',
  },
  logoutButton: {
    paddingVertical: theme.spacing.xs,
  },
  logoutText: {
    color: theme.colors.error,
    fontWeight: '600',
    fontSize: theme.typography.base,
  },
  section: {
    paddingHorizontal: theme.spacing.lg,
    paddingTop: theme.spacing.lg,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.md,
  },
  sectionTitle: {
    fontSize: theme.typography.lg,
    fontWeight: '600',
    color: theme.colors.textSecondary,
  },
  viewAllButton: {
    padding: theme.spacing.xs,
  },
  viewAllTasks: {
    fontSize: theme.typography.base,
    color: theme.colors.primary,
    fontWeight: '600',
  },
  projectCard: {
    backgroundColor: theme.colors.surface,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
    marginBottom: theme.spacing.md,
    marginHorizontal: theme.spacing.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 2,
  },
  projectRepo: {
    fontSize: theme.typography.md,
    fontWeight: '600',
    color: theme.colors.text,
  },
  projectUser: {
    fontSize: theme.typography.base,
    color: theme.colors.textMuted,
    marginTop: theme.spacing.xs,
  },
  taskItem: {
    backgroundColor: theme.colors.primaryLight,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
    marginBottom: theme.spacing.sm,
    borderLeftWidth: 4,
    borderLeftColor: theme.colors.primary,
  },
  taskTitle: {
    fontSize: theme.typography.base,
    fontWeight: '600',
    color: theme.colors.primaryDark,
  },
  taskRepo: {
    fontSize: theme.typography.sm,
    color: theme.colors.primary,
    marginTop: 2,
  },
  listContent: {
    paddingBottom: theme.spacing.xxl,
  },
});
