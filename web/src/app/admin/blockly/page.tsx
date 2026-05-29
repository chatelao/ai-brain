'use client';

import React, { useState } from 'react';
import Navbar from '@/components/Navbar';
import Breadcrumbs from '@/components/Breadcrumbs';
import { useBlocklyLogs, useBlocklySummary, useFireBlocklyEvent } from '@/hooks/useBlocklyDebug';
import { useTasks } from '@/hooks/useTasks';
import { useProjects } from '@/hooks/useProjects';
import { useUser } from '@/hooks/useUser';
import { useRouter } from 'next/navigation';
import { useEffect } from 'react';
import { useRelativePath } from '@/hooks/useRelativePath';

const ALL_EVENTS = [
  "ISSUE_OPENED",
  "ISSUE_LABELED",
  "ISSUE_REOPENED",
  "ISSUE_CLOSED",
  "COMMENT_CREATED",
  "PR_CREATED",
  "PR_MERGED",
  "CHECKS_COMPLETED",
  "AGENT_ERROR",
  "STATUS_CHANGED"
];

export default function BlocklyDebugPage() {
  const { rel } = useRelativePath();
  const { data: currentUser, isLoading: isUserLoading } = useUser();
  const router = useRouter();

  useEffect(() => {
    if (!isUserLoading && currentUser && currentUser.role !== 'admin') {
      router.push(rel('/'));
    }
  }, [currentUser, isUserLoading, router, rel]);

  const { data: logs, isLoading: logsLoading } = useBlocklyLogs();
  const { data: summary, isLoading: summaryLoading } = useBlocklySummary();
  const { data: projects } = useProjects();

  const [selectedProjectId, setSelectedProjectId] = useState<number | null>(null);
  const { data: tasks } = useTasks(selectedProjectId || undefined);

  const [selectedTaskId, setSelectedTaskId] = useState<number>(0);
  const [selectedEvent, setSelectedEvent] = useState<string>(ALL_EVENTS[0]);

  const fireEventMutation = useFireBlocklyEvent();

  const handleFireEvent = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedTaskId) return;

    fireEventMutation.mutate({
      task_id: selectedTaskId,
      event_type: selectedEvent,
    });
  };

  if (isUserLoading) {
    return <div className="min-h-screen bg-gray-50"><Navbar /></div>;
  }

  return (
    <div className="min-h-screen bg-gray-50 pb-12">
      <Navbar />

      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
        <Breadcrumbs items={[{ label: 'Admin', href: rel('/admin') }, { label: 'Blockly Debug' }]} />

        <div className="mb-8">
          <h1 className="text-2xl font-bold text-gray-900">Blockly Debugging</h1>
          <p className="text-gray-500 text-sm mt-1">Monitor triggers and test automation flows.</p>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Left Column: Fire Event & Summary */}
          <div className="space-y-8">
            {/* Fire Event Card */}
            <div className="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
              <h2 className="text-lg font-bold text-gray-900 mb-4">Fire Dummy Event</h2>
              <form onSubmit={handleFireEvent} className="space-y-4">
                <div>
                  <label className="block text-xs font-medium text-gray-700 uppercase mb-1">Project</label>
                  <select
                    className="w-full border border-gray-300 rounded-lg p-2 text-sm"
                    onChange={(e) => setSelectedProjectId(Number(e.target.value))}
                    value={selectedProjectId || ''}
                  >
                    <option value="">Select Project...</option>
                    {projects?.map(p => (
                      <option key={p.id} value={p.id}>{p.github_repo}</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-medium text-gray-700 uppercase mb-1">Task</label>
                  <select
                    className="w-full border border-gray-300 rounded-lg p-2 text-sm"
                    onChange={(e) => setSelectedTaskId(Number(e.target.value))}
                    value={selectedTaskId}
                    disabled={!selectedProjectId}
                  >
                    <option value="0">Select Task...</option>
                    {tasks?.map(t => (
                      <option key={t.id} value={t.id}>#{t.issue_number} - {t.title}</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-medium text-gray-700 uppercase mb-1">Event Type</label>
                  <select
                    className="w-full border border-gray-300 rounded-lg p-2 text-sm"
                    onChange={(e) => setSelectedEvent(e.target.value)}
                    value={selectedEvent}
                  >
                    {ALL_EVENTS.map(evt => (
                      <option key={evt} value={evt}>{evt}</option>
                    ))}
                  </select>
                </div>

                <button
                  type="submit"
                  disabled={!selectedTaskId || fireEventMutation.isPending}
                  className="w-full bg-blue-600 text-white font-bold py-2 rounded-lg hover:bg-blue-700 disabled:bg-gray-400 transition-colors"
                >
                  {fireEventMutation.isPending ? 'Firing...' : 'Fire Event'}
                </button>

                {fireEventMutation.isSuccess && (
                  <p className="text-xs text-green-600 font-medium">Event fired successfully!</p>
                )}
                {fireEventMutation.isError && (
                  <p className="text-xs text-red-600 font-medium">Error: {fireEventMutation.error.message}</p>
                )}
              </form>
            </div>

            {/* Event Summary Matrix */}
            <div className="bg-white p-6 rounded-xl border border-gray-200 shadow-sm overflow-hidden">
              <h2 className="text-lg font-bold text-gray-900 mb-4">Event Usage Summary</h2>
              <div className="overflow-x-auto">
                <table className="min-w-full text-xs">
                  <thead>
                    <tr className="border-b">
                      <th className="text-left py-2 font-bold text-gray-500 uppercase">Target</th>
                      {ALL_EVENTS.map(evt => (
                        <th key={evt} className="px-1 py-2 font-bold text-gray-500 uppercase text-center" title={evt}>
                          {evt.substring(0, 1)}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {summaryLoading ? (
                      <tr><td colSpan={ALL_EVENTS.length + 1} className="py-4 text-center">Loading...</td></tr>
                    ) : summary?.map(s => (
                      <tr key={`${s.source}-${s.id}`} className="border-b hover:bg-gray-50">
                        <td className="py-2 font-medium truncate max-w-[100px]" title={`${s.source}: ${s.name}`}>
                          <span className={`mr-1 px-1 rounded ${s.source === 'Global' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'}`}>
                            {s.source.charAt(0)}
                          </span>
                          {s.name}
                        </td>
                        {ALL_EVENTS.map(evt => (
                          <td key={evt} className="text-center">
                            {s.events.includes(evt) ? (
                              <span className="text-green-500 font-bold">●</span>
                            ) : (
                              <span className="text-gray-200">○</span>
                            )}
                          </td>
                        ))}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <p className="text-[10px] text-gray-400 mt-4 italic">
                Legend: {ALL_EVENTS.map((e, i) => `${e.substring(0, 1)}=${e}`).join(', ')}
              </p>
            </div>
          </div>

          {/* Right Column: Logs */}
          <div className="lg:col-span-2">
            <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
              <div className="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                <h2 className="text-lg font-bold text-gray-900">Blockly Execution Logs</h2>
                <div className="flex items-center space-x-2">
                   <span className="flex h-2 w-2 relative">
                    <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                    <span className="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                  </span>
                  <span className="text-[10px] font-bold text-gray-500 uppercase">Live</span>
                </div>
              </div>
              <div className="overflow-x-auto max-h-[800px]">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50 sticky top-0">
                    <tr>
                      <th className="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Time</th>
                      <th className="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Context</th>
                      <th className="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Message</th>
                      <th className="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Level</th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {logsLoading ? (
                      <tr><td colSpan={4} className="p-12 text-center text-gray-500">Loading logs...</td></tr>
                    ) : logs?.length === 0 ? (
                      <tr><td colSpan={4} className="p-12 text-center text-gray-500">No Blockly logs found.</td></tr>
                    ) : logs?.map(log => (
                      <tr key={log.task_log_id} className="hover:bg-gray-50 text-sm">
                        <td className="px-4 py-3 whitespace-nowrap text-gray-500">
                          {new Date(log.created_at).toLocaleTimeString()}
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap">
                          <div className="font-medium text-gray-900">{log.github_repo}</div>
                          <div className="text-xs text-gray-500">Task #{log.issue_number}</div>
                        </td>
                        <td className="px-4 py-3 font-mono text-xs text-gray-700 break-words max-w-md">
                          {log.message}
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap">
                          <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${
                            log.level === 'error' ? 'bg-red-100 text-red-700' :
                            log.level === 'warning' ? 'bg-yellow-100 text-yellow-700' :
                            'bg-blue-100 text-blue-700'
                          }`}>
                            {log.level}
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  );
}
