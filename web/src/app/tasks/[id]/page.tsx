'use client';

import React, { use } from 'react';
import Link from 'next/link';
import { useTask } from '@/hooks/useTask';
import { useTaskLogs } from '@/hooks/useTaskLogs';
import TaskHeader from '@/components/TaskHeader';
import LogViewer from '@/components/LogViewer';
import InteractionPanel from '@/components/InteractionPanel';
import TaskSidebar from '@/components/TaskSidebar';

export default function TaskDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
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
        <Link href="/" className="mt-4 text-blue-600 hover:underline">
          Back to Dashboard
        </Link>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 pb-12">
      <nav className="bg-white border-b border-gray-200 px-4 py-3 sticky top-0 z-10">
        <div className="max-w-7xl mx-auto flex justify-between items-center">
          <div className="flex items-center space-x-4">
            <Link href="/" className="text-gray-500 hover:text-gray-700">
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
              </svg>
            </Link>
            <h1 className="text-xl font-bold text-gray-900">Task Detail</h1>
          </div>
          <div className="w-8 h-8 bg-gray-200 rounded-full"></div>
        </div>
      </nav>

      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
        <div className="mb-8">
          <TaskHeader task={task} />
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <div className="lg:col-span-2 space-y-6">
            <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
              <div className="bg-gray-50 px-4 py-2 border-b border-gray-200">
                <span className="text-sm font-bold text-gray-700">Description</span>
              </div>
              <div className="p-6 prose max-w-none text-gray-800 whitespace-pre-wrap">
                {task.body}
              </div>
            </div>

            <LogViewer logs={logs} />

            {task.agent_response && (
              <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div className="bg-blue-50 px-4 py-3 border-b border-gray-200">
                  <h3 className="text-sm font-bold text-blue-700">Last Agent Analysis</h3>
                </div>
                <div className="p-6 bg-blue-50/30 prose prose-sm max-w-none text-blue-900 whitespace-pre-wrap">
                  {task.agent_response}
                </div>
              </div>
            )}
          </div>

          <div className="lg:col-span-1 space-y-6">
            <InteractionPanel
              task={task}
              onAction={(action) => performAction({ action })}
              isLoading={isPerformingAction}
            />
            <TaskSidebar task={task} />
          </div>
        </div>
      </main>
    </div>
  );
}
