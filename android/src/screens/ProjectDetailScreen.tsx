import React from 'react';
import { StyleSheet, View, Text, FlatList, SafeAreaView, ActivityIndicator, TouchableOpacity } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import apiClient from '../api/client';
import { components } from '../types/api';
import { theme } from '../theme';

type Task = components['schemas']['Task'];

export default function ProjectDetailScreen({ route, navigation }: any) {
  const { id, name } = route.params;

  const { data: tasks, isLoading } = useQuery({
    queryKey: ['project-tasks', id],
    queryFn: async (): Promise<Task[]> => {
      const response = await apiClient.get<Task[]>(`tasks.php?id=${id}`);
      return response.data;
    },
  });

  const getStatusColor = (status: string | undefined) => {
    switch (status) {
      case 'ready':
      case 'finished':
      case 'implemented':
        return theme.colors.success;
      case 'failed_jules':
      case 'failed_pr':
        return theme.colors.error;
      case 'analyzing':
      case 'planning':
      case 'executing':
      case 'verifying':
      case 'checking':
        return theme.colors.primary;
      default:
        return theme.colors.textSecondary;
    }
  };

  const renderTaskItem = ({ item }: { item: Task }) => (
    <TouchableOpacity
      style={styles.taskCard}
      onPress={() => navigation.navigate('TaskDetail', { id: item.id })}
    >
      <View style={styles.taskHeader}>
        <Text style={styles.taskNumber}>#{item.issue_number}</Text>
        <View style={[styles.statusBadge, { backgroundColor: getStatusColor(item.status) }]}>
          <Text style={styles.statusText}>{item.status}</Text>
        </View>
      </View>
      <Text style={styles.taskTitle}>{item.title}</Text>
    </TouchableOpacity>
  );

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
          <Text style={styles.backButtonText}>←</Text>
        </TouchableOpacity>
        <Text style={styles.title} numberOfLines={1}>{name}</Text>
        <TouchableOpacity
          onPress={() => navigation.navigate('ProjectSettings', { id, name })}
          style={styles.settingsButton}
        >
          <Text style={styles.settingsEmoji}>⚙️</Text>
        </TouchableOpacity>
      </View>

      {isLoading ? (
        <ActivityIndicator size="large" color={theme.colors.primary} style={{ marginTop: 40 }} />
      ) : (
        <FlatList
          data={tasks}
          renderItem={renderTaskItem}
          keyExtractor={item => item.id!.toString()}
          contentContainerStyle={styles.listContent}
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
    padding: theme.spacing.xs,
    minWidth: 40,
  },
  backButtonText: {
    fontSize: theme.typography.xxl,
    color: theme.colors.primary,
    fontWeight: '600',
  },
  title: {
    fontSize: theme.typography.lg,
    fontWeight: '700',
    color: theme.colors.text,
    flex: 1,
    marginHorizontal: theme.spacing.sm,
    textAlign: 'center',
  },
  settingsButton: {
    padding: theme.spacing.xs,
    minWidth: 40,
    alignItems: 'flex-end',
  },
  settingsEmoji: {
    fontSize: theme.typography.xl,
  },
  taskCard: {
    backgroundColor: theme.colors.surface,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
    marginHorizontal: theme.spacing.lg,
    marginTop: theme.spacing.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 2,
  },
  taskHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.sm,
  },
  taskNumber: {
    fontSize: theme.typography.sm,
    fontWeight: '600',
    color: theme.colors.textMuted,
  },
  statusBadge: {
    paddingHorizontal: theme.spacing.sm,
    paddingVertical: 2,
    borderRadius: theme.borderRadius.full,
  },
  statusText: {
    fontSize: theme.typography.xs,
    fontWeight: '700',
    color: theme.colors.surface,
    textTransform: 'uppercase',
  },
  taskTitle: {
    fontSize: theme.typography.md,
    color: theme.colors.text,
    fontWeight: '500',
  },
  listContent: {
    paddingBottom: theme.spacing.xxl,
  },
});
