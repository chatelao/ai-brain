import { useQuery } from '@tanstack/react-query';
import apiClient from '../api/client';
import { components } from '../types/api';

type TaskLog = components['schemas']['TaskLog'];

export const useTaskLogs = (id: string | number) => {
  return useQuery({
    queryKey: ['task-logs', id],
    queryFn: async (): Promise<TaskLog[]> => {
      const response = await apiClient.get<TaskLog[]>(`task-logs.php?id=${id}`);
      return response.data;
    },
    enabled: !!id,
    refetchInterval: 5000, // Poll logs every 5 seconds
  });
};
