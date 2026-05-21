import React from 'react';
import { components } from '@/types/api';

type TaskStatus = components['schemas']['Task']['status'];

interface TaskFilterBarProps {
  search: string;
  onSearchChange: (value: string) => void;
  statusFilter: TaskStatus | 'all';
  onStatusFilterChange: (value: TaskStatus | 'all') => void;
}

const statuses: { value: TaskStatus | 'all'; label: string }[] = [
  { value: 'all', label: 'All Statuses' },
  { value: 'created', label: 'Waiting for Agent' },
  { value: 'analyzing', label: 'Analyzing' },
  { value: 'planning', label: 'Planning' },
  { value: 'executing', label: 'Executing' },
  { value: 'verifying', label: 'Verifying' },
  { value: 'implemented', label: 'Implemented' },
  { value: 'checking', label: 'Checking' },
  { value: 'ready', label: 'Ready' },
  { value: 'finished', label: 'Finished' },
  { value: 'failed_jules', label: 'Failed Jules' },
  { value: 'failed_pr', label: 'Failed PR' },
];

export const TaskFilterBar: React.FC<TaskFilterBarProps> = ({
  search,
  onSearchChange,
  statusFilter,
  onStatusFilterChange,
}) => {
  return (
    <div className="flex flex-col md:flex-row gap-4 mb-6 bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
      <div className="flex-grow">
        <label htmlFor="search" className="block text-xs font-medium text-gray-700 mb-1">
          Search Repositories
        </label>
        <div className="relative">
          <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg className="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <input
            type="text"
            id="search"
            className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm transition-colors"
            placeholder="e.g. google/jules"
            value={search}
            onChange={(e) => onSearchChange(e.target.value)}
          />
        </div>
      </div>
      <div className="w-full md:w-64">
        <label htmlFor="status" className="block text-xs font-medium text-gray-700 mb-1">
          Status
        </label>
        <select
          id="status"
          className="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md transition-colors"
          value={statusFilter}
          onChange={(e) => onStatusFilterChange(e.target.value as TaskStatus | 'all')}
        >
          {statuses.map((status) => (
            <option key={status.value} value={status.value}>
              {status.label}
            </option>
          ))}
        </select>
      </div>
    </div>
  );
};

export default TaskFilterBar;
