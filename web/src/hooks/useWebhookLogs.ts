import { useQuery } from '@tanstack/react-query';
import apiClient from '@/api/client';
import { components } from '@/types/api';

type WebhookLog = components['schemas']['WebhookLog'];

export const useWebhookLogs = () => {
  return useQuery({
    queryKey: ['webhook-logs'],
    queryFn: async (): Promise<WebhookLog[]> => {
      const response = await apiClient.get<WebhookLog[]>('/webhook-logs.php');
      return response.data;
    },
  });
};
