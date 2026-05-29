import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/api/client';

export interface BlocklyLog {
  task_log_id: number;
  user_id: number;
  task_id: number;
  message: string;
  level: string;
  created_at: string;
  issue_number: number;
  github_repo: string;
  user_email: string;
}

export interface BlocklySummary {
  source: 'Project' | 'Global';
  id: number;
  name: string;
  events: string[];
}

export const useBlocklyLogs = (limit: number = 100) => {
  return useQuery({
    queryKey: ['blockly-logs', limit],
    queryFn: async (): Promise<BlocklyLog[]> => {
      const response = await apiClient.get<BlocklyLog[]>(`admin-blockly-debug.php?type=logs&limit=${limit}`);
      return response.data;
    },
    refetchInterval: 5000, // Auto-refresh every 5 seconds
  });
};

export const useBlocklySummary = () => {
  return useQuery({
    queryKey: ['blockly-summary'],
    queryFn: async (): Promise<BlocklySummary[]> => {
      const response = await apiClient.get<BlocklySummary[]>('admin-blockly-debug.php?type=summary');
      return response.data;
    },
  });
};

export const useFireBlocklyEvent = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: { task_id: number; event_type: string; payload?: any }) => {
      const response = await apiClient.post('admin-blockly-debug.php', {
        action: 'fire_event',
        ...data,
      });
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['blockly-logs'] });
    },
  });
};
