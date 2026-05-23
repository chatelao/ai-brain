import { useQuery } from '@tanstack/react-query';
import apiClient from '@/api/client';
import { components } from '@/types/api';

type Task = components['schemas']['Task'];

export const useAutorepeatTasks = () => {
  return useQuery({
    queryKey: ['autorepeat-tasks'],
    queryFn: async (): Promise<Task[]> => {
      const response = await apiClient.get<Task[]>('/autorepeat-tasks.php');
      return response.data;
    },
  });
};
