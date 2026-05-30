import React from 'react';
import { StyleSheet, View, Text, FlatList, SafeAreaView, ActivityIndicator, TouchableOpacity } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import apiClient from '../api/client';
import { components } from '../types/api';

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

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
          <Text style={styles.backButtonText}>←</Text>
        </TouchableOpacity>
        <Text style={styles.title} numberOfLines={1}>{name}</Text>
        <TouchableOpacity
          onPress={() => alert('Project settings coming soon to mobile!')}
          style={styles.settingsButton}
        >
          <Text style={styles.settingsEmoji}>⚙️</Text>
        </TouchableOpacity>
      </View>

      {isLoading ? (
        <ActivityIndicator size="large" color="#2563eb" style={{ marginTop: 40 }} />
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
    backgroundColor: '#f9fafb',
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
    padding: 4,
  },
  backButtonText: {
    fontSize: 20,
    color: '#2563eb',
    fontWeight: '600',
  },
  title: {
    fontSize: 18,
    fontWeight: '700',
    color: '#111827',
    flex: 1,
    marginHorizontal: 12,
  },
  settingsButton: {
    padding: 4,
  },
  settingsEmoji: {
    fontSize: 20,
  },
  taskCard: {
    backgroundColor: '#ffffff',
    padding: 16,
    borderRadius: 8,
    marginHorizontal: 20,
    marginTop: 12,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  taskHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  taskNumber: {
    fontSize: 14,
    fontWeight: '600',
    color: '#6b7280',
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
    fontSize: 16,
    color: '#111827',
    fontWeight: '500',
  },
  listContent: {
    paddingBottom: 20,
  },
});
