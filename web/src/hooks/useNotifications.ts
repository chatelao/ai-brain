import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/api/client';
import { components } from '@/types/api';

type Notification = components['schemas']['Notification'];

interface NotificationResponse {
  status: string;
  notifications?: Notification[];
  unread_count?: number;
  settings?: any;
}

export const useNotifications = (action: 'list' | 'unread_count' = 'list') => {
  return useQuery({
    queryKey: ['notifications', action],
    queryFn: async (): Promise<NotificationResponse> => {
      const response = await apiClient.get<NotificationResponse>(`/notifications.php?action=${action}`);
      return response.data;
    },
    // Poll for unread count every 15 seconds
    refetchInterval: action === 'unread_count' ? 15000 : false,
  });
};

export const useNotificationActions = () => {
  const queryClient = useQueryClient();

  const markReadMutation = useMutation({
    mutationFn: async (notificationId: number) => {
      await apiClient.post('/notifications.php', {
        action: 'mark_read',
        notification_id: notificationId,
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
  });

  const markAllReadMutation = useMutation({
    mutationFn: async () => {
      await apiClient.post('/notifications.php', {
        action: 'mark_all_read',
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
  });

  const clearAllMutation = useMutation({
    mutationFn: async () => {
      await apiClient.post('/notifications.php', {
        action: 'clear_all',
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
  });

  return {
    markAsRead: markReadMutation.mutate,
    markAllAsRead: markAllReadMutation.mutate,
    clearAll: clearAllMutation.mutate,
  };
};
