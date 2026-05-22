import React from 'react';
import { components } from '@/types/api';

type Task = components['schemas']['Task'];

interface InteractionPanelProps {
  task: Task;
  onAction: (action: 'trigger_agent' | 'merge_close' | 'merge_close_duplicate') => void;
  isLoading?: boolean;
}

export const InteractionPanel: React.FC<InteractionPanelProps> = ({ task, onAction, isLoading }) => {
  const isClosed = task.status === 'finished';
  const isReady = task.status === 'ready';
  const isImplemented = task.status === 'implemented';
  const hasPr = !!task.pr_url;

  return (
    <div className="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
      <h3 className="text-lg font-bold text-gray-900 mb-4">Actions</h3>
      <div className="space-y-3">
        {!isClosed && !isReady && !isImplemented && (
          <button
            onClick={() => onAction('trigger_agent')}
            disabled={isLoading}
            className="w-full text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 focus:outline-none disabled:opacity-50"
          >
            {isLoading ? 'Running...' : 'Run Agent'}
          </button>
        )}

        {hasPr && !isClosed && task.github_state === 'open' && (
          <>
            <button
              onClick={() => onAction('merge_close')}
              disabled={isLoading}
              className="w-full text-white bg-purple-600 hover:bg-purple-700 focus:ring-4 focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5 focus:outline-none disabled:opacity-50"
            >
              {isLoading ? 'Merging...' : 'Merge & Close'}
            </button>
            <button
              onClick={() => onAction('merge_close_duplicate')}
              disabled={isLoading}
              className="w-full text-white bg-indigo-600 hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-300 font-medium rounded-lg text-sm px-5 py-2.5 focus:outline-none disabled:opacity-50"
            >
              {isLoading ? 'Processing...' : 'Merge, Close & Duplicate'}
            </button>
          </>
        )}

        {(isReady || isImplemented) && (
          <button
             onClick={() => onAction('trigger_agent')}
             disabled={isLoading}
             className="w-full text-white bg-indigo-600 hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-300 font-medium rounded-lg text-sm px-5 py-2.5 focus:outline-none disabled:opacity-50"
          >
            {isLoading ? 'Running...' : 'Rerun Agent'}
          </button>
        )}

        {isClosed && (
          <p className="text-sm text-gray-500 text-center italic">This task is finished.</p>
        )}
      </div>
    </div>
  );
};

export default InteractionPanel;
