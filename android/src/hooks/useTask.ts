import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import apiClient from '../api/client';
import { components } from '../types/api';

type Task = components['schemas']['Task'];

export const useTask = (id: string | number) => {
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ['task', id],
    queryFn: async (): Promise<Task> => {
      const response = await apiClient.get<Task>(`task.php?id=${id}`);
      return response.data;
    },
    enabled: !!id,
  });

  const mutation = useMutation({
    mutationFn: async (data: { action: string; [key: string]: any }) => {
      const response = await apiClient.post(`task.php?id=${id}`, data);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['task', id] });
    },
  });

  return {
    ...query,
    performAction: mutation.mutate,
    isPerformingAction: mutation.isPending,
  };
};
