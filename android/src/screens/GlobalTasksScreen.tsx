import React, { useState } from 'react';
import { StyleSheet, View, Text, FlatList, SafeAreaView, TouchableOpacity, ActivityIndicator, RefreshControl } from 'react-native';
import { useTasks } from '../hooks/useTasks';

const FILTERS = [
  { id: 'all_open', label: 'All Open' },
  { id: 'github_running', label: 'Running' },
  { id: 'github_failed', label: 'Failed' },
  { id: 'open_issues', label: 'Issues' },
];

export default function GlobalTasksScreen({ navigation }: any) {
  const [filter, setFilter] = useState('all_open');
  const { data: tasks, isLoading, refetch, isRefetching } = useTasks(undefined, filter);

  const getStatusColor = (status: string | undefined) => {
    switch (status) {
      case 'ready':
      case 'finished':
      case 'implemented':
        return '#10b981';
      case 'failed_jules':
      case 'failed_pr':
        return '#ef4444';
      case 'analyzing':
      case 'planning':
      case 'executing':
      case 'verifying':
      case 'checking':
        return '#3b82f6';
      default:
        return '#6b7280';
    }
  };

  const renderTaskItem = ({ item }: any) => (
    <TouchableOpacity
      style={styles.taskCard}
      onPress={() => navigation.navigate('TaskDetail', { id: item.id })}
    >
      <View style={styles.taskHeader}>
        <Text style={styles.repoText}>{item.github_repo}</Text>
        <View style={[styles.statusBadge, { backgroundColor: getStatusColor(item.status) }]}>
          <Text style={styles.statusText}>{item.status}</Text>
        </View>
      </View>
      <Text style={styles.taskTitle}>#{item.issue_number} - {item.title}</Text>
      <Text style={styles.dateText}>
        Created: {item.created_at ? new Date(item.created_at).toLocaleDateString() : ''}
      </Text>
    </TouchableOpacity>
  );

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
          <Text style={styles.backButtonText}>← Back</Text>
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Global Tasks</Text>
        <View style={{ width: 40 }} />
      </View>

      <View style={styles.filterBar}>
        <FlatList
          data={FILTERS}
          horizontal
          showsHorizontalScrollIndicator={false}
          keyExtractor={item => item.id}
          renderItem={({ item }) => (
            <TouchableOpacity
              style={[styles.filterChip, filter === item.id && styles.activeFilterChip]}
              onPress={() => setFilter(item.id)}
            >
              <Text style={[styles.filterText, filter === item.id && styles.activeFilterText]}>
                {item.label}
              </Text>
            </TouchableOpacity>
          )}
          contentContainerStyle={styles.filterList}
        />
      </View>

      {isLoading ? (
        <View style={styles.centered}>
          <ActivityIndicator size="large" color="#2563eb" />
        </View>
      ) : (
        <FlatList
          data={tasks}
          renderItem={renderTaskItem}
          keyExtractor={item => item.id?.toString() || Math.random().toString()}
          contentContainerStyle={styles.listContent}
          refreshControl={
            <RefreshControl refreshing={isRefetching} onRefresh={refetch} />
          }
          ListEmptyComponent={
            <View style={styles.emptyContainer}>
              <Text style={styles.emptyText}>No tasks found matching this filter.</Text>
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
    backgroundColor: '#f9fafb',
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
    padding: 16,
    backgroundColor: '#ffffff',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  backButton: {
    paddingRight: 8,
  },
  backButtonText: {
    color: '#2563eb',
    fontWeight: '600',
    fontSize: 16,
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#111827',
    flex: 1,
    textAlign: 'center',
  },
  filterBar: {
    backgroundColor: '#ffffff',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  filterList: {
    padding: 12,
    gap: 8,
  },
  filterChip: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: '#f3f4f6',
    marginRight: 8,
  },
  activeFilterChip: {
    backgroundColor: '#2563eb',
  },
  filterText: {
    fontSize: 14,
    color: '#4b5563',
    fontWeight: '600',
  },
  activeFilterText: {
    color: '#ffffff',
  },
  listContent: {
    padding: 12,
  },
  taskCard: {
    backgroundColor: '#ffffff',
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 1,
  },
  taskHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  repoText: {
    fontSize: 12,
    color: '#6b7280',
    fontWeight: '600',
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 999,
  },
  statusText: {
    fontSize: 10,
    fontWeight: '700',
    color: '#ffffff',
    textTransform: 'uppercase',
  },
  taskTitle: {
    fontSize: 15,
    fontWeight: '700',
    color: '#111827',
    marginBottom: 4,
  },
  dateText: {
    fontSize: 11,
    color: '#9ca3af',
  },
  emptyContainer: {
    padding: 40,
    alignItems: 'center',
  },
  emptyText: {
    color: '#6b7280',
    fontStyle: 'italic',
  },
});
