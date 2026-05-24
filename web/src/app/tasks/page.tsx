'use client';

import React, { Suspense } from 'react';
import { useSearchParams } from 'next/navigation';
import { useTasks } from '@/hooks/useTasks';
import { useRelativePath } from '@/hooks/useRelativePath';
import Navbar from '@/components/Navbar';
import StatusBadge from '@/components/StatusBadge';
import Link from 'next/link';
import TaskDetailView from './[id]/TaskDetailView';
import { ReadonlyURLSearchParams } from 'next/navigation';

function TasksContent() {
  const searchParams = useSearchParams();
  const taskId = searchParams.get('id');
  const { rel } = useRelativePath();

  if (taskId) {
    return <TaskDetailView id={taskId} />;
  }

  return <TasksList rel={rel} searchParams={searchParams} />;
}

interface TasksListProps {
  rel: (path: string) => string;
  searchParams: ReadonlyURLSearchParams;
}

function TasksList({ rel, searchParams }: TasksListProps) {
  const filter = searchParams.get('filter') || 'all_open';
  const { data: tasks, isLoading } = useTasks(undefined, filter);

  const filterLabels: Record<string, string> = {
    all_open: 'All Open Tasks',
    github_running: 'GitHub: Running Checks',
    github_passed: 'GitHub: Checks Passed',
    github_failed: 'GitHub: Checks Failed',
    jules_analyzing: 'Jules: Sessions Analyzing',
    jules_executing: 'Jules: Sessions Executing',
    jules_failed: 'Jules: Sessions Failed',
    open_issues: 'All Open Issues',
  };

  const title = filterLabels[filter] || 'Tasks';

  return (
    <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
      <nav className="flex mb-5" aria-label="Breadcrumb">
        <ol className="inline-flex items-center space-x-1 md:space-x-2">
          <li className="inline-flex items-center">
            <Link href={rel('/')} className="text-gray-700 hover:text-gray-900 inline-flex items-center text-sm">
              <svg className="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
              </svg>
              Dashboard
            </Link>
          </li>
          <li>
            <div className="flex items-center">
              <svg className="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                <path
                  fillRule="evenodd"
                  d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                  clipRule="evenodd"
                ></path>
              </svg>
              <span className="text-gray-400 ml-1 md:ml-2 font-medium text-sm">{title}</span>
            </div>
          </li>
        </ol>
      </nav>

      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">{title}</h1>
      </div>

      <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        {isLoading ? (
          <div className="p-8 text-center text-gray-500">Loading tasks...</div>
        ) : !tasks || tasks.length === 0 ? (
          <div className="p-12 text-center text-gray-500 italic">No tasks matching this filter.</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Issue</th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {tasks.map((task) => (
                  <tr key={task.id} className="hover:bg-gray-50 transition-colors">
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      <Link href={rel(`/projects/?id=${task.project_id}`)} className="text-blue-600 hover:underline">
                        {task.github_repo}
                      </Link>
                    </td>
                    <td className="px-6 py-4 text-sm text-gray-900">
                      <Link href={rel(`/tasks/?id=${task.id}`)} className="font-medium hover:underline block">
                        #{task.issue_number} - {task.title}
                      </Link>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <StatusBadge status={task.status} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </main>
  );
}

export default function GlobalTasksPage() {
  return (
    <div className="min-h-screen bg-gray-50 pb-12">
      <Navbar />
      <Suspense fallback={<div className="p-8 text-center">Loading...</div>}>
        <TasksContent />
      </Suspense>
    </div>
  );
}
