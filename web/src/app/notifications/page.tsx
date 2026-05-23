'use client';

import React from 'react';
import { useNotifications, useNotificationActions } from '@/hooks/useNotifications';
import { useRelativePath } from '@/hooks/useRelativePath';
import Navbar from '@/components/Navbar';
import Link from 'next/link';

export default function NotificationsPage() {
  const { data, isLoading } = useNotifications('list');
  const { markAllAsRead } = useNotificationActions();
  const { rel } = useRelativePath();

  const notifications = data?.notifications || [];

  return (
    <div className="min-h-screen bg-gray-50 pb-12">
      <Navbar />

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
                <span className="text-gray-400 ml-1 md:ml-2 font-medium text-sm">Notifications</span>
              </div>
            </li>
          </ol>
        </nav>

        <div className="mb-6 flex justify-between items-center">
          <h1 className="text-2xl font-bold text-gray-900">Notifications</h1>
          {notifications.length > 0 && (
            <button
              onClick={() => markAllAsRead()}
              className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none"
            >
              Mark all as read
            </button>
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
                  className={`p-6 hover:bg-gray-50 transition-colors ${!n.is_read ? 'bg-blue-50/30' : ''}`}
                >
                  <div className="flex justify-between items-start">
                    <div className="flex-1">
                      <div className="flex items-center mb-1">
                        <div
                          className="text-sm font-bold text-gray-900 prose prose-sm max-w-none"
                          dangerouslySetInnerHTML={{ __html: n.title || '' }}
                        />
                        {!n.is_read && <span className="ml-2 w-2 h-2 bg-blue-600 rounded-full"></span>}
                      </div>
                      {n.github_repo && (
                        <div className="text-xs text-gray-500 italic mb-2">{n.github_repo}</div>
                      )}
                      <div
                        className="text-sm text-gray-600 prose prose-sm max-w-none"
                        dangerouslySetInnerHTML={{ __html: n.message || '' }}
                      />
                      <div className="mt-4 flex items-center space-x-4">
                        <span className="text-xs text-gray-400 font-mono">
                          {n.created_at ? new Date(n.created_at).toLocaleString() : ''}
                        </span>
                        {n.data?.source_url && (
                          <a
                            href={n.data.source_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-xs text-blue-600 hover:underline flex items-center"
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
