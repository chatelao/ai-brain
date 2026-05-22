import React from 'react';
import { components } from '@/types/api';
import { useRelativePath } from '@/hooks/useRelativePath';

type Task = components['schemas']['Task'];

interface TaskSidebarProps {
  task: Task;
}

export const TaskSidebar: React.FC<TaskSidebarProps> = ({ task }) => {
  const { rel } = useRelativePath();
  return (
    <div className="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
      <h3 className="text-lg font-bold text-gray-900 mb-4">Links & Info</h3>
      <div className="space-y-4">
        {/* GitHub Issue */}
        <div className="flex items-center justify-between">
          <span className="text-sm text-gray-600">GitHub Issue</span>
          <a
            href={`https://github.com/TODO_OWNER/TODO_REPO/issues/${task.issue_number}`}
            target="_blank"
            rel="noopener noreferrer"
            className="text-sm text-blue-600 hover:underline"
          >
            #{task.issue_number}
          </a>
        </div>

        {/* Jules Session */}
        <div className="flex items-center justify-between">
          <span className="text-sm text-gray-600">Jules Session</span>
          {task.jules_url ? (
            <a
              href={task.jules_url}
              target="_blank"
              rel="noopener noreferrer"
              className="text-sm text-purple-600 hover:underline"
            >
              View
            </a>
          ) : (
            <span className="text-sm text-gray-400">None</span>
          )}
        </div>

        {/* Pull Request */}
        <div className="flex items-center justify-between">
          <span className="text-sm text-gray-600">Pull Request</span>
          {task.pr_url ? (
            <a
              href={task.pr_url}
              target="_blank"
              rel="noopener noreferrer"
              className="text-sm text-green-600 hover:underline"
            >
              View
            </a>
          ) : (
            <span className="text-sm text-gray-400">None</span>
          )}
        </div>
      </div>

      <div className="mt-6 pt-6 border-t border-gray-100">
        <div className="text-[10px] text-gray-400 space-y-1 font-mono uppercase tracking-wider">
          <div>Last Synced: {task.last_synced_at ? new Date(task.last_synced_at).toLocaleString() : 'Never'}</div>
          <div>Project ID: {task.project_id}</div>
          <div>Task ID: {task.id}</div>
        </div>
      </div>
    </div>
  );
};

export default TaskSidebar;
