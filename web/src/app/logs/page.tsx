'use client';

import React, { useState } from 'react';
import Navbar from '@/components/Navbar';
import { usePerformanceLogs } from '@/hooks/usePerformanceLogs';
import { useWebhookLogs } from '@/hooks/useWebhookLogs';
import { useUser } from '@/hooks/useUser';
import { components } from '@/types/api';
import Breadcrumbs from '@/components/Breadcrumbs';

type PerformanceLog = components['schemas']['PerformanceLog'];
type WebhookLog = components['schemas']['WebhookLog'];

const WebhookLogRow = ({ log, isAdmin }: { log: WebhookLog; isAdmin: boolean }) => {
  const [isOpen, setIsOpen] = useState(false);

  let headers: any = {};
  let payload: any = {};
  try {
    headers = typeof log.headers === 'string' ? JSON.parse(log.headers) : log.headers;
    payload = typeof log.payload === 'string' ? JSON.parse(log.payload) : log.payload;
  } catch (e) {
    // Keep as is if parsing fails
  }

  return (
    <>
      <tr className="hover:bg-gray-50 transition-colors">
        {isAdmin && (
          <td className="p-4 text-sm font-normal text-gray-900 whitespace-nowrap">
            {log.user_email || 'Unknown'}
          </td>
        )}
        <td className="p-4 text-sm font-normal text-gray-900 whitespace-nowrap">
          {log.endpoint}
        </td>
        <td className="p-4 whitespace-nowrap">
          <span className={`px-2 py-1 text-xs font-medium rounded-full ${
            (log.status_code ?? 0) >= 200 && (log.status_code ?? 0) < 300
              ? 'bg-green-100 text-green-800'
              : 'bg-red-100 text-red-800'
          }`}>
            {log.status_code}
          </span>
        </td>
        <td className="p-4 text-sm font-normal text-red-500 max-w-xs truncate" title={log.error_message || ''}>
          {log.error_message || '-'}
        </td>
        <td className="p-4">
          <button
            onClick={() => setIsOpen(!isOpen)}
            className="text-blue-600 hover:underline text-sm font-medium"
          >
            {isOpen ? 'Hide Details' : 'View Payload'}
          </button>
        </td>
        <td className="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
          {log.created_at ? new Date(log.created_at).toLocaleString() : '-'}
        </td>
      </tr>
      {isOpen && (
        <tr className="bg-gray-50">
          <td colSpan={isAdmin ? 6 : 5} className="p-4">
            <div className="bg-gray-900 text-green-400 rounded-lg p-4 text-xs font-mono overflow-auto max-h-96 shadow-inner">
              <div className="mb-2 text-gray-400 border-b border-gray-700 pb-1 uppercase tracking-wider font-bold">Headers</div>
              <pre>{JSON.stringify(headers, null, 2)}</pre>
              <div className="mt-4 mb-2 text-gray-400 border-b border-gray-700 pb-1 uppercase tracking-wider font-bold">Payload</div>
              <pre>{JSON.stringify(payload, null, 2)}</pre>
            </div>
          </td>
        </tr>
      )}
    </>
  );
};

export default function LogsPage() {
  const [activeTab, setActiveTab] = useState<'performance' | 'webhooks'>('performance');
  const { data: user } = useUser();
  const { data: performanceLogs, isLoading: perfLoading } = usePerformanceLogs();
  const { data: webhookLogs, isLoading: webhookLoading } = useWebhookLogs();

  const isAdmin = user?.role === 'admin';

  return (
    <div className="min-h-screen bg-gray-50 pb-12">
      <Navbar />

      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
        <Breadcrumbs items={[{ label: 'System Logs' }]} />
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-gray-900">System Logs</h1>
          <p className="text-sm text-gray-500 mt-1">Monitor system performance and external integrations.</p>
        </div>

        <div className="mb-6 border-b border-gray-200">
          <nav className="flex space-x-8" aria-label="Tabs">
            <button
              onClick={() => setActiveTab('performance')}
              className={`py-4 px-1 border-b-2 font-medium text-sm transition-colors ${
                activeTab === 'performance'
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              API & Performance Logs
            </button>
            <button
              onClick={() => setActiveTab('webhooks')}
              className={`py-4 px-1 border-b-2 font-medium text-sm transition-colors ${
                activeTab === 'webhooks'
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              Webhook Logs
            </button>
          </nav>
        </div>

        {activeTab === 'performance' && (
          <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    {isAdmin && <th scope="col" className="p-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">User</th>}
                    <th scope="col" className="p-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Type</th>
                    <th scope="col" className="p-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Target</th>
                    <th scope="col" className="p-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Duration</th>
                    <th scope="col" className="p-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                    <th scope="col" className="p-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Error</th>
                    <th scope="col" className="p-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Time</th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {perfLoading ? (
                    <tr><td colSpan={isAdmin ? 7 : 6} className="p-12 text-center text-gray-500">Loading...</td></tr>
                  ) : performanceLogs?.length === 0 ? (
                    <tr><td colSpan={isAdmin ? 7 : 6} className="p-12 text-center text-gray-500">No logs found.</td></tr>
                  ) : (
                    performanceLogs?.map((log) => (
                      <tr key={log.id} className={`hover:bg-gray-50 transition-colors ${(log.status_code ?? 0) >= 400 ? 'bg-red-50/50' : ''}`}>
                        {isAdmin && (
                          <td className="p-4 text-sm font-normal text-gray-900 whitespace-nowrap">
                            {log.user_email || 'System'}
                          </td>
                        )}
                        <td className="p-4 text-sm font-normal text-gray-900 whitespace-nowrap">
                          <span className="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                            {log.type}
                          </span>
                        </td>
                        <td className="p-4 text-sm font-normal text-gray-500 max-w-xs truncate" title={log.target || ''}>
                          {log.target}
                        </td>
                        <td className={`p-4 text-sm font-semibold whitespace-nowrap ${(log.duration ?? 0) > 1.0 ? 'text-red-600' : 'text-gray-900'}`}>
                          {(log.duration ?? 0).toFixed(3)}s
                        </td>
                        <td className="p-4 whitespace-nowrap">
                          {log.status_code ? (
                            <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                              log.status_code >= 200 && log.status_code < 300
                                ? 'bg-green-100 text-green-800'
                                : 'bg-red-100 text-red-800'
                            }`}>
                              {log.status_code}
                            </span>
                          ) : (
                            <span className="text-gray-400">-</span>
                          )}
                        </td>
                        <td className="p-4 text-sm font-normal text-red-500 max-w-xs truncate" title={log.error_message || ''}>
                          {log.error_message || '-'}
                        </td>
                        <td className="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                          {log.created_at ? new Date(log.created_at).toLocaleString() : '-'}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {activeTab === 'webhooks' && (
          <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    {isAdmin && <th scope="col" className="p-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">User</th>}
                    <th scope="col" className="p-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Endpoint</th>
                    <th scope="col" className="p-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                    <th scope="col" className="p-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Error</th>
                    <th scope="col" className="p-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Details</th>
                    <th scope="col" className="p-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Time</th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {webhookLoading ? (
                    <tr><td colSpan={isAdmin ? 6 : 5} className="p-12 text-center text-gray-500">Loading...</td></tr>
                  ) : webhookLogs?.length === 0 ? (
                    <tr><td colSpan={isAdmin ? 6 : 5} className="p-12 text-center text-gray-500">No logs found.</td></tr>
                  ) : (
                    webhookLogs?.map((log) => (
                      <WebhookLogRow key={log.id} log={log} isAdmin={isAdmin} />
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </main>
    </div>
  );
}
