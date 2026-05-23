'use client';

import React from 'react';
import Navbar from '@/components/Navbar';
import { useDbCheck } from '@/hooks/useAdminTools';
import { useUser } from '@/hooks/useUser';
import { useRouter } from 'next/navigation';
import { useEffect } from 'react';
import { useRelativePath } from '@/hooks/useRelativePath';
import Link from 'next/link';

export default function DbCheckPage() {
  const { rel } = useRelativePath();
  const { data: currentUser, isLoading: isUserLoading } = useUser();
  const { data: health, isLoading: isHealthLoading, error } = useDbCheck();
  const router = useRouter();

  useEffect(() => {
    if (!isUserLoading && currentUser && currentUser.role !== 'admin') {
      router.push(rel('/'));
    }
  }, [currentUser, isUserLoading, router, rel]);

  if (isUserLoading || isHealthLoading) {
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
            <p className="font-bold">Error loading database health data</p>
            <p className="text-sm">You may not have sufficient permissions or there was a connection error.</p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 pb-12">
      <Navbar />

      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
        <nav className="flex mb-5" aria-label="Breadcrumb">
          <ol className="inline-flex items-center space-x-1 md:space-x-2">
            <li className="inline-flex items-center">
              <Link href={rel('/admin')} className="inline-flex items-center text-gray-700 hover:text-gray-900 text-sm">
                <svg className="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path></svg>
                Admin Dashboard
              </Link>
            </li>
            <li>
              <div className="flex items-center">
                <svg className="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clipRule="evenodd"></path></svg>
                <span className="ml-1 text-sm font-medium text-gray-400 md:ml-2">Database Check</span>
              </div>
            </li>
          </ol>
        </nav>

        <div className="mb-8">
          <h1 className="text-2xl font-bold text-gray-900">Database Health Check</h1>
          <p className="text-gray-500 text-sm mt-1">Comprehensive validation of database schema, patches, and basic data.</p>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Connection Status */}
          <section className="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <svg className="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
              Connection Status
            </h3>
            {health && health.connection_status?.status === 'OK' ? (
              <div className="flex items-center p-4 text-green-800 border-t-4 border-green-300 bg-green-50 rounded-lg">
                <svg className="flex-shrink-0 w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd"></path></svg>
                <div className="ml-3 text-sm font-medium">
                  Connected to {health.connection_status.driver} (v{health.connection_status.version})
                </div>
              </div>
            ) : (
              <div className="flex items-center p-4 text-red-800 border-t-4 border-red-300 bg-red-50 rounded-lg">
                <svg className="flex-shrink-0 w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd"></path></svg>
                <div className="ml-3 text-sm font-medium">
                  Connection Error: {health?.connection_status?.message || 'Unknown'}
                </div>
              </div>
            )}
          </section>

          {/* Migration Status */}
          <section className="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <svg className="w-5 h-5 mr-2 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
              Migration Status
            </h3>
            {!health?.missing_patches || health.missing_patches.length === 0 ? (
              <div className="flex items-center p-4 text-green-800 border-t-4 border-green-300 bg-green-50 rounded-lg">
                <svg className="flex-shrink-0 w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd"></path></svg>
                <div className="ml-3 text-sm font-medium">Database is up to date.</div>
              </div>
            ) : (
              <div className="p-4 text-orange-800 border-t-4 border-orange-300 bg-orange-50 rounded-lg">
                <div className="flex items-center mb-2">
                  <svg className="flex-shrink-0 w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd"></path></svg>
                  <div className="text-sm font-bold">{health.missing_patches.length} Patches Pending</div>
                </div>
                <ul className="text-xs list-disc list-inside mb-4 max-h-24 overflow-y-auto">
                  {health.missing_patches.map((patch) => (
                    <li key={patch}>{patch}</li>
                  ))}
                </ul>
                <Link href={rel('/admin/upgrade')} className="text-white bg-orange-700 hover:bg-orange-800 focus:ring-4 focus:ring-orange-300 font-medium rounded-lg text-xs px-3 py-1.5 inline-flex items-center transition-colors">
                  Go to Upgrade Page
                </Link>
              </div>
            )}
          </section>

          {/* Basic Data Validation */}
          <section className="bg-white border border-gray-200 rounded-xl shadow-sm p-6 lg:col-span-2">
            <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <svg className="w-5 h-5 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
              Basic Data Availability
            </h3>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              {health?.basic_data_status?.map((check) => (
                <div key={check.name} className={`p-4 border rounded-xl ${
                  check.status === 'OK' ? 'bg-green-50 border-green-200' :
                  (check.status === 'WARNING' ? 'bg-yellow-50 border-yellow-200' : 'bg-red-50 border-red-200')
                }`}>
                  <div className="flex items-center justify-between mb-2">
                    <span className="text-xs font-bold uppercase tracking-wider text-gray-500">{check.name}</span>
                    <span className={`px-2 py-0.5 text-[10px] font-bold rounded-full uppercase ${
                      check.status === 'OK' ? 'bg-green-100 text-green-800' :
                      (check.status === 'WARNING' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800')
                    }`}>
                      {check.status}
                    </span>
                  </div>
                  <p className="text-sm text-gray-700 font-medium">{check.message}</p>
                </div>
              ))}
            </div>
          </section>

          {/* Table Status */}
          <section className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden lg:col-span-2">
            <div className="p-6 border-b border-gray-100">
              <h3 className="text-lg font-semibold text-gray-900 flex items-center">
                <svg className="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                Table Validation
              </h3>
            </div>
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th scope="col" className="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Table Name</th>
                    <th scope="col" className="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                    <th scope="col" className="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Row Count</th>
                    <th scope="col" className="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Details</th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {health?.table_status?.map((table) => (
                    <tr key={table.table} className="hover:bg-gray-50 transition-colors">
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">{table.table}</td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm">
                        {table.exists ? (
                          <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Exists</span>
                        ) : (
                          <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Missing</span>
                        )}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{table.exists ? table.rows : '-'}</td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {table.error ? (
                          <span className="text-red-600 font-mono text-[10px]">{table.error}</span>
                        ) : (
                          <span className="text-green-600 font-medium italic">No issues detected</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
        </div>
      </main>
    </div>
  );
}
