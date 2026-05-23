import { useQuery } from '@tanstack/react-query';
import apiClient from '@/api/client';
import { components } from '@/types/api';

type AdminUser = components['schemas']['AdminUser'];

export const useAdminUsers = () => {
  return useQuery({
    queryKey: ['admin-users'],
    queryFn: async (): Promise<AdminUser[]> => {
      const response = await apiClient.get<AdminUser[]>('admin-users.php');
      return response.data;
    },
  });
};
