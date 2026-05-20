import React from 'react';
import { components } from '@/types/api';
import TaskStatusSquare from './TaskStatusSquare';

type Task = components['schemas']['Task'];

interface TaskStatusGridProps {
  tasks: Task[];
  maxDisplay?: number;
}

export const TaskStatusGrid: React.FC<TaskStatusGridProps> = ({ tasks, maxDisplay = 50 }) => {
  const displayedTasks = tasks.slice(0, maxDisplay);

  if (tasks.length === 0) {
    return (
      <div className="text-sm text-gray-400 italic py-2">
        No active tasks
      </div>
    );
  }

  return (
    <div className="flex flex-wrap gap-1.5 py-2">
      {displayedTasks.map((task) => (
        <TaskStatusSquare key={task.id} task={task} />
      ))}
      {tasks.length > maxDisplay && (
        <div className="flex items-center justify-center w-6 h-6 text-[10px] text-gray-500 font-medium">
          +{tasks.length - maxDisplay}
        </div>
      )}
    </div>
  );
};

export default TaskStatusGrid;
