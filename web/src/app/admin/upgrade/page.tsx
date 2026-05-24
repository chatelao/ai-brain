'use client';

import React, { useState } from 'react';
import Navbar from '@/components/Navbar';
import { useMigrationStatus, useApplyPatch } from '@/hooks/useAdminTools';
import { useUser } from '@/hooks/useUser';
import { useRouter } from 'next/navigation';
import { useEffect } from 'react';
import { useRelativePath } from '@/hooks/useRelativePath';
import Link from 'next/link';
import Breadcrumbs from '@/components/Breadcrumbs';

export default function SystemUpgradePage() {
  const { rel } = useRelativePath();
  const { data: currentUser, isLoading: isUserLoading } = useUser();
  const { data: status, isLoading: isStatusLoading, error } = useMigrationStatus();
  const applyPatch = useApplyPatch();
  const router = useRouter();

  const [selectedPatch, setSelectedPatch] = useState<string>('all');
  const [logs, setLogs] = useState<string[]>([]);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  useEffect(() => {
    if (!isUserLoading && currentUser && currentUser.role !== 'admin') {
      router.push(rel('/'));
    }
  }, [currentUser, isUserLoading, router, rel]);

  if (isUserLoading || isStatusLoading) {
    return (
      <div className="min-h-screen bg-gray-50">
        <Navbar />
        <div className="flex items-center justify-center h-[calc(100vh-64px)]">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gray-50">
        <Navbar />
        <div className="max-w-7xl mx-auto px-4 pt-8">
          <div className="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
            <p className="font-bold">Error loading migration data</p>
            <p className="text-sm">You may not have sufficient permissions or there was a connection error.</p>
          </div>
        </div>
      </div>
    );
  }

  const handleRunMigrations = async () => {
    setLogs([]);
    setSuccessMessage(null);
    try {
      const result = await applyPatch.mutateAsync(selectedPatch);
      setLogs(result.logs);
      setSuccessMessage(result.message);
    } catch (e: any) {
      // Error handled by TanStack query or shown in logs if returned in 200
    }
  };

  const pendingPatches = status?.pending || [];
  const appliedPatches = status?.applied || [];

  return (
    <div className="min-h-screen bg-gray-50 pb-12">
      <Navbar />

      <main className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
        <Breadcrumbs
          items={[
            { label: 'User Management', href: '/admin' },
            { label: 'System Upgrade' },
          ]}
        />

        <div className="text-center mb-10">
          <h1 className="text-3xl font-extrabold text-gray-900 mb-2">System Upgrade</h1>
          <p className="text-sm text-gray-500">Apply pending SQL patches to your database.</p>
        </div>

        {successMessage && (
          <div className="p-4 mb-8 text-sm text-green-800 rounded-xl bg-green-50 border border-green-200 shadow-sm" role="alert">
            <span className="font-bold">Success!</span> {successMessage}
          </div>
        )}

        <div className="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
          <section>
            <h2 className="text-lg font-bold text-gray-900 mb-4 flex items-center">
              <span className="w-3 h-3 bg-yellow-400 rounded-full mr-2"></span>
              Pending Patches ({pendingPatches.length})
            </h2>
            <div className="bg-white border border-gray-200 rounded-xl p-4 max-h-64 overflow-y-auto shadow-sm">
              {pendingPatches.length === 0 ? (
                <p className="text-sm text-gray-500 italic text-center py-4">No pending patches. Your database is up to date.</p>
              ) : (
                <ul className="space-y-2">
                  {pendingPatches.map((patch) => (
                    <li key={patch} className="text-xs font-mono text-gray-700 bg-gray-50 p-2 rounded border border-gray-100">{patch}</li>
                  ))}
                </ul>
              )}
            </div>

            {pendingPatches.length > 0 && (
              <div className="mt-6 space-y-4">
                <div>
                  <label htmlFor="patch" className="block text-xs font-bold text-gray-700 uppercase mb-1">Select Patch</label>
                  <select
                    id="patch"
                    value={selectedPatch}
                    onChange={(e) => setSelectedPatch(e.target.value)}
                    className="mt-1 block w-full pl-3 pr-10 py-2 text-sm border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-lg border shadow-sm"
                  >
                    <option value="all">Apply All Pending Patches</option>
                    {pendingPatches.map((patch) => (
                      <option key={patch} value={patch}>{patch}</option>
                    ))}
                  </select>
                </div>

                <button
                  onClick={handleRunMigrations}
                  disabled={applyPatch.isPending}
                  className="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 transition-colors"
                >
                  {applyPatch.isPending ? 'Running Migrations...' : 'Run Migrations'}
                </button>
              </div>
            )}
          </section>

          <section>
            <h2 className="text-lg font-bold text-gray-900 mb-4 flex items-center">
              <span className="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
              Applied Patches ({appliedPatches.length})
            </h2>
            <div className="bg-white border border-gray-200 rounded-xl p-4 max-h-64 overflow-y-auto shadow-sm">
              {appliedPatches.length === 0 ? (
                <p className="text-sm text-gray-500 italic text-center py-4">No patches applied yet.</p>
              ) : (
                <ul className="space-y-2">
                  {[...appliedPatches].reverse().map((patch) => (
                    <li key={patch} className="text-xs font-mono text-gray-400 bg-gray-50/50 p-2 rounded border border-gray-100">{patch}</li>
                  ))}
                </ul>
              )}
            </div>
          </section>
        </div>

        {logs.length > 0 && (
          <div className="mt-8">
            <h3 className="text-xs font-bold text-gray-700 mb-2 uppercase tracking-wider">Migration Logs</h3>
            <div className="bg-gray-900 rounded-xl p-5 border border-gray-700 shadow-xl">
              <pre className="text-xs text-green-400 font-mono whitespace-pre-wrap leading-relaxed">
                {logs.join('\n')}
              </pre>
            </div>
          </div>
        )}

        <div className="mt-12 pt-6 border-t border-gray-200 flex justify-between items-center text-xs text-gray-500">
          <p>Database Status: <span className={pendingPatches.length === 0 ? 'text-green-600 font-bold uppercase' : 'text-yellow-600 font-bold uppercase'}>{pendingPatches.length === 0 ? 'Up to date' : 'Updates pending'}</span></p>
          <Link href={rel('/')} className="text-blue-600 hover:underline font-medium">Back to Dashboard</Link>
        </div>
      </main>
    </div>
  );
}
