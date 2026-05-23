import { useQuery } from '@tanstack/react-query';
import apiClient from '@/api/client';
import { components } from '@/types/api';

type TaskLog = components['schemas']['TaskLog'];

export const useTaskLogs = (taskId: number | string | undefined) => {
  return useQuery({
    queryKey: ['task-logs', taskId],
    queryFn: async (): Promise<TaskLog[]> => {
      const response = await apiClient.get<TaskLog[]>(`task-logs.php?id=${taskId}`);
      return response.data;
    },
    enabled: !!taskId,
    refetchInterval: 5000, // Refresh logs every 5 seconds
  });
};
