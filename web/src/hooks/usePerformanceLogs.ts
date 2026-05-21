import { useQuery } from '@tanstack/react-query';
import apiClient from '@/api/client';
import { components } from '@/types/api';

type PerformanceLog = components['schemas']['PerformanceLog'];

export const usePerformanceLogs = () => {
  return useQuery({
    queryKey: ['performance-logs'],
    queryFn: async (): Promise<PerformanceLog[]> => {
      const response = await apiClient.get<PerformanceLog[]>('/performance-logs.php');
      return response.data;
    },
  });
};
