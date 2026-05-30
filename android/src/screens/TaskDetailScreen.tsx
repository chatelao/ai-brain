import React from 'react';
import { StyleSheet, View, Text, ScrollView, SafeAreaView, TouchableOpacity, ActivityIndicator } from 'react-native';
import { useTask } from '../hooks/useTask';
import { useTaskLogs } from '../hooks/useTaskLogs';
import { theme } from '../theme';

export default function TaskDetailScreen({ route, navigation }: any) {
  const { id } = route.params;
  const { data: task, isLoading, performAction, isPerformingAction } = useTask(id);
  const { data: logs } = useTaskLogs(id);

  if (isLoading || !task) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.centered}>
          <ActivityIndicator size="large" color={theme.colors.primary} />
        </View>
      </SafeAreaView>
    );
  }

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
                style={[styles.primaryButton, { backgroundColor: theme.colors.secondary }]}
                onPress={() => performAction({ action: 'merge_close' })}
                disabled={isPerformingAction}
              >
                <Text style={styles.buttonText}>
                  {isPerformingAction ? 'Merging...' : 'Merge & Close'}
                </Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.primaryButton, { backgroundColor: theme.colors.indigo, marginTop: theme.spacing.md }]}
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
    padding: theme.spacing.md,
    backgroundColor: theme.colors.surface,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  backButton: {
    marginRight: theme.spacing.md,
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
  },
  scrollContent: {
    padding: theme.spacing.md,
    paddingBottom: theme.spacing.xxl,
  },
  card: {
    backgroundColor: theme.colors.surface,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.lg,
    marginBottom: theme.spacing.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 2,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    marginBottom: theme.spacing.sm,
  },
  statusBadge: {
    paddingHorizontal: theme.spacing.sm,
    paddingVertical: 4,
    borderRadius: theme.borderRadius.full,
  },
  statusText: {
    fontSize: theme.typography.sm,
    fontWeight: '700',
    color: theme.colors.surface,
    textTransform: 'uppercase',
  },
  taskTitle: {
    fontSize: theme.typography.xl,
    fontWeight: '700',
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  taskBody: {
    fontSize: theme.typography.base,
    color: theme.colors.textSecondary,
    lineHeight: theme.typography.xl,
    marginBottom: theme.spacing.md,
  },
  noBody: {
    fontSize: theme.typography.base,
    color: theme.colors.textMuted,
    fontStyle: 'italic',
    marginBottom: theme.spacing.md,
  },
  labelsContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.spacing.sm,
  },
  label: {
    paddingHorizontal: theme.spacing.sm,
    paddingVertical: 2,
    borderRadius: theme.borderRadius.sm,
    borderWidth: 1,
  },
  labelText: {
    fontSize: theme.typography.sm,
    fontWeight: '600',
  },
  sectionTitle: {
    fontSize: theme.typography.md,
    fontWeight: '700',
    color: theme.colors.textSecondary,
    marginBottom: theme.spacing.md,
  },
  prCard: {
    backgroundColor: '#f0fdf4',
    borderColor: '#bbf7d0',
  },
  prTitle: {
    fontSize: theme.typography.md,
    fontWeight: '600',
    color: '#065f46',
    marginBottom: theme.spacing.sm,
  },
  prMeta: {
    flexDirection: 'row',
    gap: theme.spacing.md,
  },
  prMetaText: {
    fontSize: theme.typography.sm,
    color: '#047857',
  },
  actionsContainer: {
    marginBottom: theme.spacing.lg,
  },
  primaryButton: {
    backgroundColor: theme.colors.primary,
    paddingVertical: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 48,
  },
  buttonText: {
    color: theme.colors.surface,
    fontWeight: '600',
    fontSize: theme.typography.md,
  },
  logsContainer: {
    marginTop: theme.spacing.sm,
  },
  logsBox: {
    backgroundColor: '#111827',
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
    minHeight: 200,
  },
  noLogs: {
    color: theme.colors.textMuted,
    fontStyle: 'italic',
    fontSize: theme.typography.base,
  },
  logItem: {
    flexDirection: 'row',
    marginBottom: 4,
  },
  logTime: {
    color: '#6b7280',
    fontFamily: 'monospace',
    fontSize: theme.typography.xs,
    marginRight: 8,
    width: 75,
  },
  logMessage: {
    color: '#d1d5db',
    fontFamily: 'monospace',
    fontSize: theme.typography.xs,
    flex: 1,
  },
  errorLog: {
    color: '#f87171',
  },
  finishedText: {
    fontSize: theme.typography.base,
    color: theme.colors.textMuted,
    fontStyle: 'italic',
    textAlign: 'center',
    marginTop: theme.spacing.sm,
  },
});
