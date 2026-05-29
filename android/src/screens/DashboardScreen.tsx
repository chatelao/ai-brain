import React from 'react';
import { StyleSheet, View, Text, FlatList, SafeAreaView, TouchableOpacity, ActivityIndicator } from 'react-native';
import { useProjects } from '../hooks/useProjects';
import { useAutorepeatTasks } from '../hooks/useAutorepeatTasks';
import { useAuth } from '../hooks/useAuth';

export default function DashboardScreen({ navigation }: any) {
  const { data: projects, isLoading: projectsLoading } = useProjects();
  const { data: autorepeatTasks } = useAutorepeatTasks();
  const { logout } = useAuth();

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
        <TouchableOpacity onPress={logout}>
          <Text style={styles.logoutText}>Logout</Text>
        </TouchableOpacity>
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
        <Text style={styles.sectionTitle}>Your Projects</Text>
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
  logoutText: {
    color: '#ef4444',
    fontWeight: '600',
  },
  section: {
    padding: 20,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 12,
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
