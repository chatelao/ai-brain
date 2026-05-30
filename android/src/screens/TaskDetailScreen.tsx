import React from 'react';
import { StyleSheet, View, Text, ScrollView, SafeAreaView, TouchableOpacity, ActivityIndicator } from 'react-native';
import { useTask } from '../hooks/useTask';
import { useTaskLogs } from '../hooks/useTaskLogs';

export default function TaskDetailScreen({ route, navigation }: any) {
  const { id } = route.params;
  const { data: task, isLoading, performAction, isPerformingAction } = useTask(id);
  const { data: logs } = useTaskLogs(id);

  if (isLoading || !task) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.centered}>
          <ActivityIndicator size="large" color="#2563eb" />
        </View>
      </SafeAreaView>
    );
  }

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
          <Text style={styles.backButtonText}>← Back</Text>
        </TouchableOpacity>
        <Text style={styles.headerTitle} numberOfLines={1}>
          #{task.issue_number} - {task.title}
        </Text>
      </View>

      <ScrollView contentContainerStyle={styles.scrollContent}>
        <View style={styles.card}>
          <View style={styles.cardHeader}>
            <View style={[styles.statusBadge, { backgroundColor: getStatusColor(task.status) }]}>
              <Text style={styles.statusText}>{task.status}</Text>
            </View>
          </View>
          <Text style={styles.taskTitle}>{task.title}</Text>
          {task.body ? (
            <Text style={styles.taskBody}>{task.body}</Text>
          ) : (
            <Text style={styles.noBody}>No description provided.</Text>
          )}

          <View style={styles.labelsContainer}>
            {task.labels?.map((label, index) => (
              <View
                key={index}
                style={[
                  styles.label,
                  { backgroundColor: `#${label.color}20`, borderColor: `#${label.color}` },
                ]}
              >
                <Text style={[styles.labelText, { color: `#${label.color}` }]}>{label.name}</Text>
              </View>
            ))}
          </View>
        </View>

        {task.pr_details && (
          <View style={[styles.card, styles.prCard]}>
            <Text style={styles.sectionTitle}>Associated Pull Request</Text>
            <Text style={styles.prTitle}>{task.pr_details.title}</Text>
            <View style={styles.prMeta}>
              <Text style={styles.prMetaText}>State: {task.pr_details.state}</Text>
              <Text style={styles.prMetaText}>Merged: {task.pr_details.merged ? 'Yes' : 'No'}</Text>
            </View>
          </View>
        )}

        <View style={styles.actionsContainer}>
          {task.status === 'failed_jules' && (
            <TouchableOpacity
              style={styles.primaryButton}
              onPress={() => performAction({ action: 'trigger_agent' })}
              disabled={isPerformingAction}
            >
              <Text style={styles.buttonText}>
                {isPerformingAction ? 'Processing...' : 'Retry Task'}
              </Text>
            </TouchableOpacity>
          )}

          {!!task.pr_url && task.status !== 'finished' && task.github_state === 'open' && (
            <>
              <TouchableOpacity
                style={[styles.primaryButton, { backgroundColor: '#9333ea', marginTop: 12 }]}
                onPress={() => performAction({ action: 'merge_close' })}
                disabled={isPerformingAction}
              >
                <Text style={styles.buttonText}>
                  {isPerformingAction ? 'Merging...' : 'Merge & Close'}
                </Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.primaryButton, { backgroundColor: '#4f46e5', marginTop: 12 }]}
                onPress={() => performAction({ action: 'merge_close_duplicate' })}
                disabled={isPerformingAction}
              >
                <Text style={styles.buttonText}>
                  {isPerformingAction ? 'Processing...' : 'Merge, Close & Duplicate'}
                </Text>
              </TouchableOpacity>
            </>
          )}

          {task.status === 'finished' && (
            <Text style={styles.finishedText}>This task is finished.</Text>
          )}
        </View>

        <View style={styles.logsContainer}>
          <Text style={styles.sectionTitle}>Task Logs</Text>
          <View style={styles.logsBox}>
            {!logs || logs.length === 0 ? (
              <Text style={styles.noLogs}>No logs available.</Text>
            ) : (
              logs.map((log, index) => (
                <View key={index} style={styles.logItem}>
                  <Text style={styles.logTime}>
                    [{log.created_at ? new Date(log.created_at).toLocaleTimeString() : '...'}]
                  </Text>
                  <Text style={[styles.logMessage, log.level === 'error' && styles.errorLog]}>
                    {log.message}
                  </Text>
                </View>
              ))
            )}
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
  centered: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 16,
    backgroundColor: '#ffffff',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  backButton: {
    marginRight: 12,
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
  },
  scrollContent: {
    padding: 16,
  },
  card: {
    backgroundColor: '#ffffff',
    padding: 16,
    borderRadius: 12,
    marginBottom: 16,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 2,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    marginBottom: 12,
  },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
  },
  statusText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#ffffff',
    textTransform: 'uppercase',
  },
  taskTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: '#111827',
    marginBottom: 12,
  },
  taskBody: {
    fontSize: 14,
    color: '#4b5563',
    lineHeight: 20,
    marginBottom: 16,
  },
  noBody: {
    fontSize: 14,
    color: '#9ca3af',
    fontStyle: 'italic',
    marginBottom: 16,
  },
  labelsContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  label: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 4,
    borderWidth: 1,
  },
  labelText: {
    fontSize: 12,
    fontWeight: '600',
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#374151',
    marginBottom: 12,
  },
  prCard: {
    backgroundColor: '#f0fdf4',
    borderColor: '#bbf7d0',
  },
  prTitle: {
    fontSize: 15,
    fontWeight: '600',
    color: '#065f46',
    marginBottom: 8,
  },
  prMeta: {
    flexDirection: 'row',
    gap: 16,
  },
  prMetaText: {
    fontSize: 12,
    color: '#047857',
  },
  actionsContainer: {
    marginBottom: 16,
  },
  primaryButton: {
    backgroundColor: '#2563eb',
    paddingVertical: 12,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buttonText: {
    color: '#ffffff',
    fontWeight: '600',
    fontSize: 16,
  },
  logsContainer: {
    marginTop: 8,
  },
  logsBox: {
    backgroundColor: '#111827',
    padding: 12,
    borderRadius: 8,
    minHeight: 200,
  },
  noLogs: {
    color: '#6b7280',
    fontStyle: 'italic',
  },
  logItem: {
    flexDirection: 'row',
    marginBottom: 4,
  },
  logTime: {
    color: '#6b7280',
    fontFamily: 'monospace',
    fontSize: 11,
    marginRight: 8,
    width: 75,
  },
  logMessage: {
    color: '#d1d5db',
    fontFamily: 'monospace',
    fontSize: 11,
    flex: 1,
  },
  errorLog: {
    color: '#f87171',
  },
  finishedText: {
    fontSize: 14,
    color: '#6b7280',
    fontStyle: 'italic',
    textAlign: 'center',
    marginTop: 8,
  },
});
