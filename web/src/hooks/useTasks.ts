import { useQuery } from '@tanstack/react-query';
import apiClient from '@/api/client';
import { components } from '@/types/api';

type Task = components['schemas']['Task'];

export const useTasks = (projectId: number | undefined, filter: string = 'all_open', all: boolean = false) => {
  return useQuery({
    queryKey: ['tasks', projectId, filter, all],
    queryFn: async (): Promise<Task[]> => {
      let url = 'tasks.php';
      const params = new URLSearchParams();

      if (projectId) {
        params.append('id', projectId.toString());
        if (all) {
          params.append('all', 'true');
        }
      } else {
        params.append('filter', filter);
      }

      if (params.toString()) {
        url += `?${params.toString()}`;
      }

      const response = await apiClient.get<Task[]>(url);
      return response.data;
    },
  });
};
