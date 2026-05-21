import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/api/client';
import { components } from '@/types/api';

type Template = components['schemas']['Template'];

export const useTemplates = () => {
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ['templates'],
    queryFn: async (): Promise<Template[]> => {
      const response = await apiClient.get<Template[]>('/templates.php');
      return response.data;
    },
  });

  const saveTemplate = useMutation({
    mutationFn: async (template: Partial<Template>) => {
      const response = await apiClient.post('/templates.php', {
        id: template.id,
        name: template.name,
        title_template: template.title_template,
        body_template: template.body_template,
        parameter_config: template.parameter_config,
      });
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['templates'] });
    },
  });

  const deleteTemplate = useMutation({
    mutationFn: async (id: number) => {
      const response = await apiClient.delete(`/templates.php?id=${id}`);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['templates'] });
    },
  });

  return {
    ...query,
    saveTemplate: saveTemplate.mutateAsync,
    isSaving: saveTemplate.isPending,
    deleteTemplate: deleteTemplate.mutateAsync,
    isDeleting: deleteTemplate.isPending,
  };
};
