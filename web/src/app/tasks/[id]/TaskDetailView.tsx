'use client';

import React, { use } from 'react';
import { useTask } from '@/hooks/useTask';
import { useTaskLogs } from '@/hooks/useTaskLogs';
import { useRelativePath } from '@/hooks/useRelativePath';
import StatusBadge from '@/components/StatusBadge';
import TaskStatusSquare from '@/components/TaskStatusSquare';
import TaskSidebar from '@/components/TaskSidebar';
import Navbar from '@/components/Navbar';

export default function TaskDetailView({ id }: { id: string }) {
  const { data: task, isLoading, error, performAction, isPerformingAction } = useTask(id);
  const { data: logs } = useTaskLogs(id);
  const { rel } = useRelativePath();
  const [autorepeatCount, setAutorepeatCount] = React.useState<number>(0);

  React.useEffect(() => {
    if (task?.autorepeat_remaining !== undefined) {
      setAutorepeatCount(task.autorepeat_remaining);
    }
  }, [task?.autorepeat_remaining]);

  React.useEffect(() => {
    const link: HTMLLinkElement | null = document.querySelector("link[rel*='icon']");
    const originalHref = link?.href;

    if (task?.status) {
      let faviconPath = rel('/favicon.ico');
      if (['ready', 'finished'].includes(task.status)) {
        faviconPath = rel('/favicon-success.svg');
      } else if (['failed_jules', 'failed_pr'].includes(task.status)) {
        faviconPath = rel('/favicon-error.svg');
      } else {
        faviconPath = rel('/favicon-pending.svg');
      }

      if (link) {
        link.href = faviconPath;
      } else {
        const newLink = document.createElement('link');
        newLink.rel = 'icon';
        newLink.href = faviconPath;
        document.head.appendChild(newLink);
      }
    }

    return () => {
      if (link && originalHref) {
        link.href = originalHref;
      }
    };
  }, [task?.status, rel]);

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
              <div className="prose prose-sm max-w-none text-gray-600 border-t pt-4 whitespace-pre-wrap">
                {task.body}
              </div>
            </div>

            {/* Associated Pull Request */}
            {task.pr_details && (
              <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div className="bg-green-50 px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                  <div className="flex items-center space-x-2">
                    <svg className="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M11 19.25c0 .414-.336.75-.75.75H8.5a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM11 14.5c0 .414-.336.75-.75.75H8.5a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM11 9.75c0 .414-.336.75-.75.75H8.5a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM16 19.25c0 .414-.336.75-.75.75h-1.75a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM16 14.5c0 .414-.336.75-.75.75h-1.75a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM16 9.75c0 .414-.336.75-.75.75h-1.75a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75h1.75c.414 0 .75.336.75.75v1.5zM20.5 2h-17C2.673 2 2 2.673 2 3.5v17c0 .827.673 1.5 1.5 1.5h17c.827 0 1.5-.673 1.5-1.5v-17C22 2.673 21.327 2 20.5 2zM20 18H4V4h16v14z" />
                    </svg>
                    <span className="text-sm font-bold text-green-900">Associated Pull Request</span>
                  </div>
                  {task.pr_url && (
                    <a
                      href={task.pr_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-xs font-medium text-green-700 hover:underline"
                    >
                      View on GitHub &rarr;
                    </a>
                  )}
                </div>
                <div className="p-6">
                  <div className="flex items-start justify-between mb-2">
                    <h4 className="text-lg font-bold text-gray-900 leading-tight">
                      {task.pr_details.title}
                    </h4>
                    <span className="px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200 shadow-sm">
                      {task.pr_details.state}
                    </span>
                  </div>
                  <div className="text-sm text-gray-500 mb-4 flex items-center space-x-4">
                    <span>Merged: {task.pr_details.merged ? 'Yes' : 'No'}</span>
                    <span>
                      Base: <code className="bg-gray-100 px-1 rounded">{task.pr_details.base?.ref}</code>
                    </span>
                    <span>
                      Head: <code className="bg-gray-100 px-1 rounded">{task.pr_details.head?.ref}</code>
                    </span>
                  </div>
                  {task.pr_details.body && (
                    <div className="prose prose-sm max-w-none text-gray-600 bg-gray-50 p-4 rounded-lg border border-gray-100 whitespace-pre-wrap">
                      {task.pr_details.body}
                    </div>
                  )}
                </div>
              </div>
            )}

            {/* Last Jules Messages */}
            {task.jules_messages && task.jules_messages.length > 0 && (
              <div className="space-y-4">
                <h3 className="text-lg font-bold text-gray-900 px-1 flex items-center">
                  <svg
                    className="w-5 h-5 mr-2 text-blue-600"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
                    />
                  </svg>
                  Last Jules Messages
                </h3>
                {task.jules_messages.map((msg, index) => (
                  <div key={index} className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <div className="bg-blue-50 px-4 py-2 border-b border-gray-200 flex items-center justify-between">
                      <div className="flex items-center space-x-2">
                        <div className="w-6 h-6 rounded-full bg-blue-600 flex items-center justify-center text-[10px] text-white font-bold">
                          J
                        </div>
                        <span className="text-sm font-bold text-gray-700">{msg.user?.login}</span>
                        <span className="text-sm text-gray-500">
                          at {msg.created_at ? new Date(msg.created_at).toLocaleString() : ''}
                        </span>
                      </div>
                      {msg.html_url && (
                        <a
                          href={msg.html_url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="text-xs text-blue-600 hover:underline"
                        >
                          Link
                        </a>
                      )}
                    </div>
                    <div className="p-4">
                      <div className="prose prose-sm max-w-none text-gray-700 whitespace-pre-wrap">
                        {msg.body}
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}

            {/* Interaction Panel */}
            <div className="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-bold text-gray-900">Actions</h3>
                <div className="flex items-center space-x-4">
                  <div className="flex items-center space-x-2 bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-200">
                    <span className="text-xs font-medium text-gray-500 uppercase tracking-wider">Auto-Repeat</span>
                    <input
                      type="number"
                      min="0"
                      max="100"
                      value={autorepeatCount}
                      onChange={(e) => {
                        const val = parseInt(e.target.value) || 0;
                        setAutorepeatCount(val);
                        performAction({ action: 'update_autorepeat', autorepeat_remaining: val });
                      }}
                      className="w-16 px-2 py-1 text-sm border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                    />
                  </div>
                </div>
              </div>
              <div className="flex flex-wrap gap-3">
                {task.status === 'failed_jules' && (
                  <>
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
                  </>
                )}
                {(task.status === 'ready' || task.status === 'implemented') && task.github_state === 'open' && (
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
