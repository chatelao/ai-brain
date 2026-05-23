'use client';

import React, { useMemo } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useUser } from '@/hooks/useUser';
import { useRelativePath } from '@/hooks/useRelativePath';
import StatusIndicators from './StatusIndicators';
import NotificationBell from './NotificationBell';

const Navbar = () => {
  const { data: user } = useUser();
  const { rel } = useRelativePath();
  const pathname = usePathname() || '/';

  const legacyUrl = useMemo(() => {
    // pathname example: /projects/13/ or /tasks/42/ or /
    const segments = pathname.split('/').filter(Boolean);

    if (segments[0] === 'projects' && segments[1]) {
      return rel(`/project.php?id=${segments[1]}&legacy=1`);
    }
    if (segments[0] === 'tasks' && segments[1]) {
      return rel(`/task.php?id=${segments[1]}&legacy=1`);
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
    <nav className="bg-white border-b border-gray-200 px-4 py-3 sticky top-0 z-50">
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
            <Link href={rel('/templates')} className="text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors">
              Templates
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

        <div className="flex items-center space-x-6">
          {user && (
            <>
              <div className="hidden md:block">
                <StatusIndicators />
              </div>
              <NotificationBell />
              <div className="flex items-center space-x-3 border-l pl-6 border-gray-100">
                <div className="text-right hidden sm:block">
                  <p className="text-sm font-medium text-gray-900">{user.name}</p>
                  <p className="text-xs text-gray-500">{user.role === 'admin' ? 'Administrator' : 'User'}</p>
                </div>
                <img
                  src={user.avatar || 'https://www.gravatar.com/avatar/?d=mp'}
                  alt={user.name}
                  className="w-8 h-8 rounded-full border border-gray-200"
                />
                <button
                  onClick={handleLogout}
                  className="text-xs font-bold text-red-600 hover:text-red-800 transition-colors border border-red-100 px-2 py-1 rounded bg-red-50"
                  title="Logout from system"
                >
                  Logout
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

export default Navbar;
