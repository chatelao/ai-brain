import React from 'react';
import Link from 'next/link';
import { components } from '@/types/api';

type Task = components['schemas']['Task'];
type TaskStatus = Task['status'];

interface TaskStatusSquareProps {
  task: Task;
}

const statusColors: Record<NonNullable<TaskStatus>, string> = {
  created: 'bg-gray-400',
  analyzing: 'bg-blue-600',
  planning: 'bg-blue-600',
  executing: 'bg-yellow-500',
  verifying: 'bg-yellow-500',
  implemented: 'bg-yellow-500',
  checking: 'bg-orange-500',
  ready: 'bg-green-600',
  finished: 'bg-purple-600',
  failed_jules: 'bg-red-600',
  failed_pr: 'bg-red-600',
};

const getStatusEmoji = (status: TaskStatus): string => {
  switch (status) {
    case 'created': return '⏳';
    case 'ready':
    case 'finished': return '✅';
    case 'checking': return '🔍';
    case 'failed_jules':
    case 'failed_pr': return '❌';
    default: return '🚧';
  }
};

const getStatusLabel = (status: TaskStatus): string => {
  if (status === 'created') return 'Waiting for Agent';
  if (!status) return 'Unknown';
  return status.charAt(0).toUpperCase() + status.slice(1).replace('_', ' ');
};

export const TaskStatusSquare: React.FC<TaskStatusSquareProps> = ({ task }) => {
  const status = task.status || 'created';
  const colorClass = statusColors[status] || 'bg-gray-400';
  const emoji = getStatusEmoji(status);
  const label = getStatusLabel(status);

  // Auto-repeat check
  // Note: The OpenAPI Task schema doesn't explicitly have an auto_repeat flag,
  // but the legacy PHP code checks for labels. For now, we'll implement the basic square.
  // In a real scenario, we might need to check if 'Auto-Repeat' is in the task labels
  // if they were included in the API response.

  return (
    <div className="relative group">
      <Link
        href={`/tasks/${task.id}`}
        className={`block w-6 h-6 rounded shadow-sm transition-transform duration-200 hover:scale-125 hover:z-10 ${colorClass}`}
        aria-label={`Task #${task.issue_number}: ${label}`}
      />
      <div className="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-[10px] rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-50 shadow-lg">
        #{task.issue_number}: {emoji} {label} - {task.title}
      </div>
    </div>
  );
};

export default TaskStatusSquare;
