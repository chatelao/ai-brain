'use client';

import React from 'react';
import { useNotifications, useNotificationActions } from '@/hooks/useNotifications';
import { useRelativePath } from '@/hooks/useRelativePath';
import Navbar from '@/components/Navbar';
import Breadcrumbs from '@/components/Breadcrumbs';
import Link from 'next/link';

export default function NotificationsPage() {
  const { data, isLoading } = useNotifications('list');
  const { markAllAsRead, markAsRead } = useNotificationActions();
  const { rel } = useRelativePath();

  const notifications = data?.notifications || [];

  return (
    <div className="min-h-screen bg-gray-50 pb-12">
      <Navbar />

      <main className="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8 pt-8">
        <Breadcrumbs items={[{ label: 'Notifications' }]} />

        <div className="mb-6 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 sm:gap-0">
          <h1 className="text-2xl font-bold text-gray-900 px-2 sm:px-0">Notifications</h1>
          {notifications.length > 0 && (
            <div className="px-2 sm:px-0">
              <button
                onClick={() => markAllAsRead()}
                className="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none"
              >
                Mark all as read
              </button>
            </div>
          )}
        </div>

        <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
          {isLoading ? (
            <div className="p-8 text-center text-gray-500">Loading notifications...</div>
          ) : notifications.length === 0 ? (
            <div className="p-12 text-center text-gray-500 italic">No notifications found.</div>
          ) : (
            <ul className="divide-y divide-gray-100">
              {notifications.map((n) => (
                <li
                  key={n.notification_id}
                  className={`p-4 sm:p-6 hover:bg-gray-50 transition-colors ${!n.is_read ? 'bg-blue-50/30' : ''}`}
                >
                  <div className="flex justify-between items-start gap-4">
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center mb-1 flex-wrap gap-2">
                        <div
                          className="text-sm font-bold text-gray-900 prose prose-sm max-w-none break-words"
                          dangerouslySetInnerHTML={{ __html: n.title || '' }}
                        />
                        {!n.is_read && <span className="flex-shrink-0 w-2 h-2 bg-blue-600 rounded-full"></span>}
                      </div>
                      {n.github_repo && (
                        <div className="text-xs text-gray-500 italic mb-2 truncate" title={n.github_repo}>
                          {n.github_repo}
                        </div>
                      )}
                      <div
                        className="text-sm text-gray-600 prose prose-sm max-w-none break-words"
                        dangerouslySetInnerHTML={{ __html: n.message || '' }}
                      />
                      <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2">
                        <span className="text-xs text-gray-400 font-mono whitespace-nowrap">
                          {n.created_at ? new Date(n.created_at).toLocaleString() : ''}
                        </span>
                        {n.data?.source_url && (
                          <a
                            href={n.data.source_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-xs text-blue-600 hover:underline flex items-center whitespace-nowrap"
                          >
                            View Source
                            <svg className="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                              ></path>
                            </svg>
                          </a>
                        )}
                      </div>
                    </div>
                    {!n.is_read && n.notification_id && (
                      <button
                        onClick={() => markAsRead(n.notification_id as number)}
                        className="flex-shrink-0 p-2 text-gray-400 hover:text-blue-600 rounded-full hover:bg-blue-50 transition-colors"
                        title="Mark as read"
                      >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                        </svg>
                      </button>
                    )}
                  </div>
                </li>
              ))}
            </ul>
          )}
        </div>
      </main>
    </div>
  );
}
