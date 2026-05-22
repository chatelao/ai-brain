'use client';

import React, { use } from 'react';
import { useTask } from '@/hooks/useTask';
import { useTaskLogs } from '@/hooks/useTaskLogs';
import StatusBadge from '@/components/StatusBadge';
import TaskStatusSquare from '@/components/TaskStatusSquare';
import TaskSidebar from '@/components/TaskSidebar';
import Navbar from '@/components/Navbar';

export default function TaskDetailView({ id }: { id: string }) {
  const { data: task, isLoading, error, performAction, isPerformingAction } = useTask(id);
  const { data: logs } = useTaskLogs(id);

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-50">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
      </div>
    );
  }

  if (error || !task) {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen p-4 bg-gray-50">
        <div className="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg max-w-md w-full">
          <p className="font-bold">Error loading task</p>
          <p className="text-sm">Please try again later or check your connection.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 pb-12">
      <Navbar />

      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
        <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">
          <div className="lg:col-span-3 space-y-6">
            {/* Header */}
            <div className="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
              <div className="flex items-center justify-between mb-4">
                <div className="flex items-center space-x-3">
                  <TaskStatusSquare task={task} />
                  <h2 className="text-2xl font-bold text-gray-900">
                    <span className="text-gray-400 mr-2">#{task.issue_number}</span>
                    {task.title}
                  </h2>
                </div>
                <StatusBadge status={task.status} />
              </div>
              <div className="flex flex-wrap gap-2 mb-6">
                {task.labels?.map((label, index) => (
                  <span
                    key={index}
                    className="px-2 py-1 rounded text-xs font-medium border"
                    style={{
                      backgroundColor: `#${label.color}20`,
                      borderColor: `#${label.color}`,
                      color: `#${label.color}`,
                    }}
                  >
                    {label.name}
                  </span>
                ))}
              </div>
              <div className="prose prose-sm max-w-none text-gray-600 border-t pt-4">
                {task.body}
              </div>
            </div>

            {/* Interaction Panel */}
            <div className="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
              <h3 className="text-lg font-bold text-gray-900 mb-4">Actions</h3>
              <div className="flex flex-wrap gap-3">
                <button
                  onClick={() => performAction({ action: 'trigger_agent' })}
                  disabled={isPerformingAction}
                  className="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition-colors"
                >
                  Retry Task
                </button>
                <button
                  onClick={() => performAction({ action: 'trigger_agent' })}
                  disabled={isPerformingAction}
                  className="px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50 disabled:opacity-50 transition-colors"
                >
                  Restart from Scratch
                </button>
                {(task.status === 'ready' || task.status === 'implemented') && (
                  <>
                    <button
                      onClick={() => performAction({ action: 'merge_close' })}
                      disabled={isPerformingAction}
                      className="px-4 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700 disabled:opacity-50 transition-colors"
                    >
                      Merge PR
                    </button>
                    <button
                      onClick={() => performAction({ action: 'merge_close_duplicate' })}
                      disabled={isPerformingAction}
                      className="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                    >
                      Merge, Close & Duplicate
                    </button>
                  </>
                )}
              </div>
            </div>

            {/* Log Viewer */}
            <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
              <div className="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                <h3 className="text-lg font-bold text-gray-900">Task Logs</h3>
                <span className="text-xs text-gray-500 italic">Auto-refreshing...</span>
              </div>
              <div className="p-6 bg-gray-900 font-mono text-sm h-96 overflow-y-auto">
                {!logs || logs.length === 0 ? (
                  <div className="text-gray-500 italic">No logs available for this task.</div>
                ) : (
                  <div className="space-y-2">
                    {logs.map((log, index) => (
                      <div key={index} className="flex space-x-4">
                        <span className="text-gray-500 shrink-0">[{log.created_at ? new Date(log.created_at).toLocaleTimeString() : '...'}]</span>
                        <span className={log.level === 'error' ? 'text-red-400' : 'text-gray-300'}>
                          {log.message}
                        </span>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Sidebar */}
          <div className="lg:col-span-1">
            <TaskSidebar task={task} />
          </div>
        </div>
      </main>
    </div>
  );
}
