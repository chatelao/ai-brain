import { useMutation, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/api/client';

export const useTaskActions = () => {
  const queryClient = useQueryClient();

  const taskActionMutation = useMutation({
    mutationFn: async ({
      id,
      action,
      autorepeat_remaining,
    }: {
      id: number | string;
      action: 'trigger_agent' | 'merge_close' | 'merge_close_duplicate' | 'update_autorepeat';
      autorepeat_remaining?: number;
    }) => {
      const response = await apiClient.post(`task.php?id=${id}`, { action, autorepeat_remaining });
      return response.data;
    },
    onSuccess: (_, variables) => {
      const { id } = variables;
      queryClient.invalidateQueries({ queryKey: ['task', id.toString()] });
      queryClient.invalidateQueries({ queryKey: ['task', Number(id)] });
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
      queryClient.invalidateQueries({ queryKey: ['task-logs', id.toString()] });
      queryClient.invalidateQueries({ queryKey: ['task-logs', Number(id)] });
    },
  });

  return {
    performAction: taskActionMutation.mutate,
    performActionAsync: taskActionMutation.mutateAsync,
    isPerformingAction: taskActionMutation.isPending,
    performingActionId: taskActionMutation.variables?.id,
    actionError: taskActionMutation.error,
    actionSuccess: taskActionMutation.isSuccess,
  };
};
