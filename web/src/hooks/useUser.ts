import { useQuery } from '@tanstack/react-query';
import apiClient from '@/api/client';
import { components } from '@/types/api';

type User = components['schemas']['User'];

export const useUser = () => {
  return useQuery({
    queryKey: ['user'],
    queryFn: async (): Promise<User> => {
      const response = await apiClient.get<User>('/user.php');
      return response.data;
    },
  });
};
