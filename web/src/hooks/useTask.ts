import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/api/client';
import { components } from '@/types/api';

type Task = components['schemas']['Task'];

export const useTask = (id: number | string | undefined) => {
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ['task', id],
    queryFn: async (): Promise<Task> => {
      const response = await apiClient.get<Task>(`/task.php?id=${id}`);
      return response.data;
    },
    enabled: !!id,
  });

  const taskActionMutation = useMutation({
    mutationFn: async ({ action }: { action: 'trigger_agent' | 'merge_close' }) => {
      const response = await apiClient.post(`/task.php?id=${id}`, { action });
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['task', id] });
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
      queryClient.invalidateQueries({ queryKey: ['task-logs', id] });
    },
  });

  return {
    ...query,
    performAction: taskActionMutation.mutate,
    isPerformingAction: taskActionMutation.isPending,
    actionError: taskActionMutation.error,
    actionSuccess: taskActionMutation.isSuccess,
  };
};
