'use client';

import React, { Suspense } from 'react';
import { useSearchParams } from 'next/navigation';
import { useTasks } from '@/hooks/useTasks';
import { useRelativePath } from '@/hooks/useRelativePath';
import Navbar from '@/components/Navbar';
import StatusBadge from '@/components/StatusBadge';
import Breadcrumbs from '@/components/Breadcrumbs';
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
      <Breadcrumbs items={[{ label: title }]} />

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
