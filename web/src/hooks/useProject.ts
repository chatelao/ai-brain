import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/api/client';
import { components } from '@/types/api';

type Project = components['schemas']['Project'];

export const useProject = (id: string | number) => {
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ['projects', id],
    queryFn: async (): Promise<Project> => {
      const response = await apiClient.get<Project>(`project.php?id=${id}`);
      return response.data;
    },
    enabled: !!id,
  });

  const syncMutation = useMutation({
    mutationFn: async () => {
      const response = await apiClient.post(`project.php?id=${id}`, {
        action: 'sync_issues',
      });
      return response.data;
    },
    onSuccess: () => {
      // Invalidate both project detail and task list for this project
      queryClient.invalidateQueries({ queryKey: ['projects', id] });
      queryClient.invalidateQueries({ queryKey: ['tasks', id] });
    },
  });

  const createFromTemplateMutation = useMutation({
    mutationFn: async ({ templateId, params }: { templateId: number; params: Record<string, string> }) => {
      const response = await apiClient.post(`project.php?id=${id}`, {
        action: 'create_from_template',
        template_id: templateId,
        params,
      });
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tasks', id] });
    },
  });

  const createGithubIssueMutation = useMutation({
    mutationFn: async ({ title, body }: { title: string; body?: string }) => {
      const response = await apiClient.post(`project.php?id=${id}`, {
        action: 'create_github_issue',
        title,
        body,
      });
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tasks', id] });
    },
  });

  const createFromRoadmapMutation = useMutation({
    mutationFn: async (roadmapName: string) => {
      const response = await apiClient.post(`project.php?id=${id}`, {
        action: 'create_from_roadmap',
        roadmap_name: roadmapName,
      });
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tasks', id] });
    },
  });

  const updateSettingsMutation = useMutation({
    mutationFn: async (data: {
      github_repo?: string;
      github_account_id?: number;
      blockly_config?: components['schemas']['User']['blockly_config'];
    }) => {
      const response = await apiClient.post(`project.php?id=${id}`, {
        action: 'update_settings',
        ...data,
      });
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['projects', id] });
      queryClient.invalidateQueries({ queryKey: ['projects'] });
    },
  });

  const updateNotificationsMutation = useMutation({
    mutationFn: async (statusSettings: Record<string, boolean>) => {
      const response = await apiClient.post(`project.php?id=${id}`, {
        action: 'update_notifications',
        status_settings: statusSettings,
      });
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['projects', id] });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async () => {
      const response = await apiClient.delete(`project.php?id=${id}`);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['projects'] });
    },
  });

  return {
    ...query,
    syncIssues: syncMutation.mutate,
    isSyncing: syncMutation.isPending,
    syncError: syncMutation.error,
    createFromTemplate: createFromTemplateMutation.mutateAsync,
    isCreatingFromTemplate: createFromTemplateMutation.isPending,
    createGithubIssue: createGithubIssueMutation.mutateAsync,
    isCreatingGithubIssue: createGithubIssueMutation.isPending,
    createFromRoadmap: createFromRoadmapMutation.mutateAsync,
    isCreatingFromRoadmap: createFromRoadmapMutation.isPending,
    updateSettings: updateSettingsMutation.mutateAsync,
    isUpdatingSettings: updateSettingsMutation.isPending,
    updateNotifications: updateNotificationsMutation.mutateAsync,
    isUpdatingNotifications: updateNotificationsMutation.isPending,
    deleteProject: deleteMutation.mutateAsync,
    isDeleting: deleteMutation.isPending,
  };
};
