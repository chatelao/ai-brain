import React from 'react';
import { components } from '@/types/api';

type TaskLog = components['schemas']['TaskLog'];

interface LogViewerProps {
  logs?: TaskLog[];
}

export const LogViewer: React.FC<LogViewerProps> = ({ logs = [] }) => {
  return (
    <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
      <div className="bg-gray-50 px-4 py-3 border-b border-gray-200">
        <h3 className="text-sm font-bold text-gray-700">Task Logs</h3>
      </div>
      <div className="p-4 space-y-1 bg-gray-900 min-h-[100px] max-h-[400px] overflow-y-auto">
        {logs.length === 0 ? (
          <p className="text-sm text-gray-400 italic">No logs available for this task.</p>
        ) : (
          logs.map((log) => (
            <div
              key={log.id}
              className={`flex items-start text-xs font-mono p-1 rounded ${
                log.level === 'error' ? 'bg-red-900/30 text-red-300' : 'text-gray-300 hover:bg-gray-800'
              }`}
            >
              <span className="text-gray-500 mr-3 shrink-0">
                [{log.created_at ? new Date(log.created_at).toLocaleTimeString() : '--:--:--'}]
              </span>
              <span className="flex-1">{log.message}</span>
            </div>
          ))
        )}
      </div>
    </div>
  );
};

export default LogViewer;
