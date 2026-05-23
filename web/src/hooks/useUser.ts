import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/api/client';
import { components } from '@/types/api';

type User = components['schemas']['User'];

export const useUser = () => {
  return useQuery({
    queryKey: ['user'],
    queryFn: async (): Promise<User> => {
      const response = await apiClient.get<User>('user.php');
      return response.data;
    },
  });
};

export const useUpdateUser = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (data: Partial<User>) => {
      const response = await apiClient.post<User>('user.php', data);
      return response.data;
    },
    onSuccess: (newUser) => {
      queryClient.setQueryData(['user'], newUser);
    },
  });
};
