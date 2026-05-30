'use client';

import React, { useMemo, Suspense } from 'react';
import Link from 'next/link';
import { usePathname, useSearchParams } from 'next/navigation';
import { useUser } from '@/hooks/useUser';
import { useRelativePath } from '@/hooks/useRelativePath';
import StatusIndicators from './StatusIndicators';
import NotificationBell from './NotificationBell';

const NavbarContent = () => {
  const { data: user } = useUser();
  const { rel } = useRelativePath();
  const pathname = usePathname() || '/';
  const searchParams = useSearchParams();

  const legacyUrl = useMemo(() => {
    // pathname example: /projects/13/ or /tasks/42/ or /
    const segments = pathname.split('/').filter(Boolean);
    const queryId = searchParams.get('id');

    if ((segments[0] === 'projects' && segments[1]) || (segments[0] === 'projects' && queryId)) {
      const id = queryId || segments[1];
      if (searchParams.get('settings') === 'true') {
        return rel(`/project-settings.php?id=${id}&legacy=1`);
      }
      return rel(`/project.php?id=${id}&legacy=1`);
    }
    if ((segments[0] === 'tasks' && segments[1]) || (segments[0] === 'tasks' && queryId)) {
      const id = queryId || segments[1];
      return rel(`/task.php?id=${id}&legacy=1`);
    }
    if (segments[0] === 'settings') {
      return rel('/settings.php?legacy=1');
    }
    if (segments[0] === 'templates') {
      return rel('/templates.php?legacy=1');
    }
    if (segments[0] === 'logs') {
      return rel('/logs.php?legacy=1');
    }
    if (segments[0] === 'admin') {
      return rel('/admin/index.php?legacy=1');
    }

    return rel('/index.php?legacy=1');
  }, [pathname, rel]);

  const handleLogout = () => {
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
    window.location.href = rel('/logout.php');
  };

  return (
    <nav className="bg-white border-b border-gray-200 px-4 py-3 sticky top-0 z-[100]">
      <div className="max-w-7xl mx-auto flex justify-between items-center">
        <div className="flex items-center space-x-8">
          <Link href={rel('/')} className="text-xl font-bold text-gray-900 hover:text-blue-600 transition-colors">
            Agent Control
          </Link>
          <div className="hidden lg:flex items-center space-x-4">
            <Link href={rel('/')} className="text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors">
              Dashboard
            </Link>
            <Link href={rel('/tasks')} className="text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors">
              Tasks
            </Link>
            <Link href={rel('/logs')} className="text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors">
              Logs
            </Link>
            <Link href={rel('/settings')} className="text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors">
              Settings
            </Link>
            {user?.role === 'admin' && (
              <Link href={rel('/admin')} className="text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors">
                Admin
              </Link>
            )}
            <a href={legacyUrl} className="text-sm font-medium text-blue-600 hover:text-blue-800 transition-colors border border-blue-200 px-2 py-0.5 rounded bg-blue-50">
              Legacy UI
            </a>
          </div>
        </div>

        <div className="flex items-center space-x-2 sm:space-x-6">
          {user && (
            <>
              <div className="hidden md:block">
                <StatusIndicators />
              </div>
              <NotificationBell />
              <div className="flex items-center space-x-2 sm:space-x-3 border-l pl-2 sm:pl-6 border-gray-100">
                <div className="text-right hidden sm:block">
                  <p className="text-sm font-medium text-gray-900 leading-tight">{user.name}</p>
                  <p className="text-[10px] text-gray-500">{user.role === 'admin' ? 'Administrator' : 'User'}</p>
                </div>
                <img
                  src={user.avatar || 'https://www.gravatar.com/avatar/?d=mp'}
                  alt={user.name}
                  className="w-8 h-8 rounded-full border border-gray-200 shrink-0"
                />
                <button
                  onClick={handleLogout}
                  className="inline-flex items-center text-xs font-bold text-red-600 hover:text-red-800 transition-colors border border-red-100 px-2 py-1 rounded bg-red-50"
                  title="Logout from system"
                >
                  <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                  </svg>
                  <span className="hidden sm:inline ml-1">Logout</span>
                </button>
              </div>
            </>
          )}
          {!user && (
            <div className="w-8 h-8 bg-gray-200 rounded-full animate-pulse"></div>
          )}
        </div>
      </div>
    </nav>
  );
};

const Navbar = () => {
  return (
    <Suspense fallback={
      <nav className="bg-white border-b border-gray-200 px-4 py-3 sticky top-0 z-[100]">
        <div className="max-w-7xl mx-auto flex justify-between items-center">
          <div className="text-xl font-bold text-gray-900">Agent Control</div>
        </div>
      </nav>
    }>
      <NavbarContent />
    </Suspense>
  );
};

export default Navbar;
