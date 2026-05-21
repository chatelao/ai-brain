'use client';

import React from 'react';
import Link from 'next/link';
import { useUser } from '@/hooks/useUser';

const Navbar = () => {
  const { data: user } = useUser();

  return (
    <nav className="bg-white border-b border-gray-200 px-4 py-3 sticky top-0 z-10">
      <div className="max-w-7xl mx-auto flex justify-between items-center">
        <div className="flex items-center space-x-8">
          <Link href="/" className="text-xl font-bold text-gray-900 hover:text-blue-600 transition-colors">
            Agent Control
          </Link>
          <div className="hidden md:flex items-center space-x-4">
            <Link href="/" className="text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors">
              Dashboard
            </Link>
            <Link href="/logs" className="text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors">
              Logs
            </Link>
            <Link href="/settings" className="text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors">
              Settings
            </Link>
          </div>
        </div>
        <div className="flex items-center space-x-4">
          {user && (
            <div className="flex items-center space-x-3">
              <div className="text-right hidden sm:block">
                <p className="text-sm font-medium text-gray-900">{user.name}</p>
                <p className="text-xs text-gray-500">{user.role === 'admin' ? 'Administrator' : 'User'}</p>
              </div>
              <img
                src={user.avatar || 'https://www.gravatar.com/avatar/?d=mp'}
                alt={user.name}
                className="w-8 h-8 rounded-full border border-gray-200"
              />
            </div>
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
