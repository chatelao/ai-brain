import React from 'react';
import { components } from '@/types/api';
import StatusBadge from './StatusBadge';

type Task = components['schemas']['Task'];

interface TaskHeaderProps {
  task: Task;
}

export const TaskHeader: React.FC<TaskHeaderProps> = ({ task }) => {
  return (
    <div>
      <div className="flex flex-wrap items-center justify-between gap-4 mb-4">
        <h2 className="text-3xl font-bold text-gray-900 flex-1 min-w-[300px]">
          {task.title}
          <span className="text-gray-400 font-normal ml-2">#{task.issue_number}</span>
        </h2>
        <div className="flex items-center space-x-2">
          <StatusBadge status={task.status} />
        </div>
      </div>
      <div className="text-sm text-gray-500">
        Created on {task.created_at ? new Date(task.created_at).toLocaleDateString() : 'Unknown'}
      </div>
    </div>
  );
};

export default TaskHeader;
