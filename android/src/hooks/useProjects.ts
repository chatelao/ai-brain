import { useQuery } from '@tanstack/react-query';
import apiClient from '../api/client';
import { components } from '../types/api';

type Project = components['schemas']['Project'];

export const useProjects = () => {
  return useQuery({
    queryKey: ['projects'],
    queryFn: async (): Promise<Project[]> => {
      const response = await apiClient.get<Project[]>('projects.php');
      return response.data;
    },
  });
};
