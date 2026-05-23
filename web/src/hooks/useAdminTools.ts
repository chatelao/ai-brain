import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/api/client';
import { components } from '@/types/api';

type DbCheckResults = components['schemas']['DbCheckResults'];
type MigrationStatus = components['schemas']['MigrationStatus'];

export const useDbCheck = () => {
  return useQuery({
    queryKey: ['admin', 'db-check'],
    queryFn: async (): Promise<DbCheckResults> => {
      const response = await apiClient.get<DbCheckResults>('admin-db-check.php');
      return response.data;
    },
  });
};

export const useMigrationStatus = () => {
  return useQuery({
    queryKey: ['admin', 'migration-status'],
    queryFn: async (): Promise<MigrationStatus> => {
      const response = await apiClient.get<MigrationStatus>('admin-upgrade.php');
      return response.data;
    },
  });
};

export const useApplyPatch = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (patch: string = 'all') => {
      const response = await apiClient.post<{
        status: string;
        message: string;
        logs: string[];
      }>('admin-upgrade.php', { patch });
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'migration-status'] });
      queryClient.invalidateQueries({ queryKey: ['admin', 'db-check'] });
    },
  });
};
