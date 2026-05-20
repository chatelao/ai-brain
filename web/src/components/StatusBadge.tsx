import React from 'react';
import { components } from '@/types/api';

type TaskStatus = components['schemas']['Task']['status'];

interface StatusBadgeProps {
  status: TaskStatus;
  className?: string;
}

const statusConfig: Record<
  NonNullable<TaskStatus>,
  { label: string; emoji: string; colorClass: string }
> = {
  created: {
    label: 'Waiting for Agent',
    emoji: '⏳',
    colorClass: 'bg-gray-100 text-gray-800 border-gray-200',
  },
  analyzing: {
    label: 'Analyzing',
    emoji: '🚧',
    colorClass: 'bg-blue-100 text-blue-800 border-blue-200',
  },
  planning: {
    label: 'Planning',
    emoji: '🚧',
    colorClass: 'bg-blue-100 text-blue-800 border-blue-200',
  },
  executing: {
    label: 'Executing',
    emoji: '🚧',
    colorClass: 'bg-yellow-100 text-yellow-800 border-yellow-200',
  },
  verifying: {
    label: 'Verifying',
    emoji: '🚧',
    colorClass: 'bg-yellow-100 text-yellow-800 border-yellow-200',
  },
  implemented: {
    label: 'Implemented',
    emoji: '🚧',
    colorClass: 'bg-yellow-100 text-yellow-800 border-yellow-200',
  },
  checking: {
    label: 'Checking',
    emoji: '🔍',
    colorClass: 'bg-orange-100 text-orange-800 border-orange-200',
  },
  ready: {
    label: 'Ready',
    emoji: '✅',
    colorClass: 'bg-green-100 text-green-800 border-green-200',
  },
  finished: {
    label: 'Finished',
    emoji: '✅',
    colorClass: 'bg-purple-100 text-purple-800 border-purple-200',
  },
  failed_jules: {
    label: 'Failed Jules',
    emoji: '❌',
    colorClass: 'bg-red-100 text-red-800 border-red-200',
  },
  failed_pr: {
    label: 'Failed PR',
    emoji: '❌',
    colorClass: 'bg-red-100 text-red-800 border-red-200',
  },
};

export const StatusBadge: React.FC<StatusBadgeProps> = ({ status, className = '' }) => {
  if (!status) return null;

  const config = statusConfig[status];
  if (!config) return null;

  return (
    <span
      className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${config.colorClass} ${className}`}
    >
      <span className="mr-1">{config.emoji}</span>
      {config.label}
    </span>
  );
};

export default StatusBadge;
