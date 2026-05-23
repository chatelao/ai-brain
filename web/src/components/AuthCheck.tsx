'use client';

import React, { useEffect, useState } from 'react';
import { useUser } from '@/hooks/useUser';
import { useRelativePath } from '@/hooks/useRelativePath';

const AuthCheck = ({ children }: { children: React.ReactNode }) => {
  const { data: user, isLoading, error } = useUser();
  const { rel } = useRelativePath();
  const [isClient, setIsClient] = useState(false);

  useEffect(() => {
    setIsClient(true);
  }, []);

  if (!isClient) {
    return null;
  }

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-50">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
      </div>
    );
  }

  if (error || !user) {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen p-4 bg-gray-50 text-center">
        <div className="bg-white p-8 rounded-xl shadow-lg max-w-md w-full border border-gray-100">
           <div className="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg className="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
           </div>
           <h2 className="text-2xl font-bold text-gray-900 mb-2">Login Required</h2>
           <p className="text-gray-600 mb-8">Please login to access the Agent Control dashboard.</p>

           <div className="flex flex-col space-y-3">
             <a
               href={rel('/google/login.php')}
               className="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
             >
               Login with Google
             </a>
             <a
               href={rel('/github/login.php')}
               className="w-full flex justify-center py-2.5 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
             >
               Login with GitHub
             </a>
           </div>
        </div>
      </div>
    );
  }

  return <>{children}</>;
};

export default AuthCheck;
