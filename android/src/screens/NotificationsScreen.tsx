import React from 'react';
import { StyleSheet, View, Text, FlatList, SafeAreaView, TouchableOpacity, ActivityIndicator, RefreshControl } from 'react-native';
import { useNotifications, useNotificationActions } from '../hooks/useNotifications';
import { theme } from '../theme';

export default function NotificationsScreen({ navigation }: any) {
  const { data, isLoading, refetch, isRefetching } = useNotifications('list');
  const { markAsRead, markAllAsRead, isMarkingAllRead } = useNotificationActions();

  const notifications = data?.notifications || [];

  const renderNotificationItem = ({ item }: any) => (
    <View style={[styles.notificationCard, item.is_read === 0 && styles.unreadCard]}>
      <View style={styles.notificationContent}>
        <View style={styles.titleRow}>
          <Text style={styles.notificationTitle}>
            {item.title_plain || item.title}
          </Text>
          {item.is_read === 0 && <View style={styles.unreadDot} />}
        </View>

        {item.github_repo && (
          <Text style={styles.repoText}>{item.github_repo}</Text>
        )}

        <Text style={styles.notificationMessage}>
          {item.message_plain || item.message}
        </Text>

        <View style={styles.footer}>
          <Text style={styles.dateText}>
            {item.created_at ? new Date(item.created_at).toLocaleString() : ''}
          </Text>

          <View style={styles.actions}>
            {item.is_read === 0 && (
              <TouchableOpacity
                onPress={() => item.notification_id && markAsRead(item.notification_id)}
                style={styles.markReadButton}
              >
                <Text style={styles.markReadText}>Mark as Read</Text>
              </TouchableOpacity>
            )}
          </View>
        </View>
      </View>
    </View>
  );

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
          <Text style={styles.backButtonText}>← Back</Text>
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Notifications</Text>
        <View style={styles.headerRight}>
          {notifications.length > 0 && (
            <TouchableOpacity
              onPress={() => markAllAsRead()}
              disabled={isMarkingAllRead}
              style={styles.markAllButton}
            >
              <Text style={[styles.markAllText, isMarkingAllRead && { opacity: 0.5 }]}>
                Mark all
              </Text>
            </TouchableOpacity>
          )}
        </View>
      </View>

      {isLoading ? (
        <View style={styles.centered}>
          <ActivityIndicator size="large" color={theme.colors.primary} />
        </View>
      ) : (
        <FlatList
          data={notifications}
          renderItem={renderNotificationItem}
          keyExtractor={item => item.notification_id?.toString() || Math.random().toString()}
          contentContainerStyle={styles.listContent}
          refreshControl={
            <RefreshControl refreshing={isRefetching} onRefresh={refetch} />
          }
          ListEmptyComponent={
            <View style={styles.emptyContainer}>
              <Text style={styles.emptyText}>No notifications found.</Text>
            </View>
          }
        />
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  centered: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: theme.spacing.md,
    backgroundColor: theme.colors.surface,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  backButton: {
    minWidth: 60,
    padding: theme.spacing.xs,
  },
  backButtonText: {
    color: theme.colors.primary,
    fontWeight: '600',
    fontSize: theme.typography.md,
  },
  headerTitle: {
    fontSize: theme.typography.lg,
    fontWeight: '700',
    color: theme.colors.text,
    flex: 1,
    textAlign: 'center',
  },
  headerRight: {
    minWidth: 60,
    alignItems: 'flex-end',
  },
  markAllButton: {
    padding: theme.spacing.xs,
  },
  markAllText: {
    color: theme.colors.primary,
    fontSize: theme.typography.sm,
    fontWeight: '600',
  },
  listContent: {
    padding: theme.spacing.md,
    paddingBottom: theme.spacing.xxl,
  },
  notificationCard: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.borderRadius.lg,
    marginBottom: theme.spacing.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 2,
    overflow: 'hidden',
  },
  unreadCard: {
    backgroundColor: theme.colors.primaryLight,
    borderColor: '#bfdbfe',
  },
  notificationContent: {
    padding: theme.spacing.md,
  },
  titleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 4,
  },
  notificationTitle: {
    fontSize: theme.typography.md,
    fontWeight: '700',
    color: theme.colors.text,
    flex: 1,
  },
  unreadDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: theme.colors.primary,
    marginLeft: theme.spacing.sm,
  },
  repoText: {
    fontSize: theme.typography.sm,
    color: theme.colors.textMuted,
    fontStyle: 'italic',
    marginBottom: theme.spacing.sm,
  },
  notificationMessage: {
    fontSize: theme.typography.base,
    color: theme.colors.textSecondary,
    lineHeight: theme.typography.xl,
    marginBottom: theme.spacing.md,
  },
  footer: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  dateText: {
    fontSize: theme.typography.xs,
    color: theme.colors.textMuted,
  },
  actions: {
    flexDirection: 'row',
  },
  markReadButton: {
    paddingVertical: theme.spacing.xs,
    paddingHorizontal: theme.spacing.sm,
    borderRadius: theme.borderRadius.sm,
    backgroundColor: theme.colors.surface,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  markReadText: {
    fontSize: theme.typography.xs,
    fontWeight: '600',
    color: theme.colors.textSecondary,
  },
  emptyContainer: {
    padding: 40,
    alignItems: 'center',
  },
  emptyText: {
    color: theme.colors.textMuted,
    fontStyle: 'italic',
    fontSize: theme.typography.base,
  },
});
