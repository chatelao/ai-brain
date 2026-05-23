import { useQuery } from '@tanstack/react-query';
import apiClient from '@/api/client';
import { components } from '@/types/api';

type SyncStatus = components['schemas']['SyncStatus'];

export const useSyncStatus = (fast: boolean = true) => {
  return useQuery({
    queryKey: ['sync-status', fast],
    queryFn: async (): Promise<SyncStatus> => {
      const response = await apiClient.get<SyncStatus>(`/sync-status.php?fast=${fast ? 1 : 0}`);
      return response.data;
    },
    // Poll every 30 seconds for status updates
    refetchInterval: 30000,
  });
};
