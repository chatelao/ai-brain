import { useQuery } from '@tanstack/react-query';
import apiClient from '@/api/client';
import { components } from '@/types/api';

type Task = components['schemas']['Task'];

export const useTasks = (projectId: number | undefined) => {
  return useQuery({
    queryKey: ['tasks', projectId],
    queryFn: async (): Promise<Task[]> => {
      if (!projectId) return [];
      const response = await apiClient.get<Task[]>(`/tasks.php?id=${projectId}`);
      return response.data;
    },
    enabled: !!projectId,
  });
};
