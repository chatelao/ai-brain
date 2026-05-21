import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/api/client';
import { components } from '@/types/api';

type Project = components['schemas']['Project'];

export const useProject = (id: string | number) => {
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ['projects', id],
    queryFn: async (): Promise<Project> => {
      const response = await apiClient.get<Project>(`/project.php?id=${id}`);
      return response.data;
    },
    enabled: !!id,
  });

  const syncMutation = useMutation({
    mutationFn: async () => {
      const response = await apiClient.post(`/project.php?id=${id}`, {
        action: 'sync_issues',
      });
      return response.data;
    },
    onSuccess: () => {
      // Invalidate both project detail and task list for this project
      queryClient.invalidateQueries({ queryKey: ['projects', id] });
      queryClient.invalidateQueries({ queryKey: ['tasks', id] });
    },
  });

  return {
    ...query,
    syncIssues: syncMutation.mutate,
    isSyncing: syncMutation.isPending,
    syncError: syncMutation.error,
  };
};
