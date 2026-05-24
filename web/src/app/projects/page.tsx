'use client';

import React, { Suspense } from 'react';
import { useSearchParams, useRouter } from 'next/navigation';
import { useRelativePath } from '@/hooks/useRelativePath';
import Navbar from '@/components/Navbar';
import ProjectDetailView from './[id]/ProjectDetailView';
import ProjectSettingsView from './[id]/settings/ProjectSettingsView';

function ProjectContent() {
  const searchParams = useSearchParams();
  const projectId = searchParams.get('id');
  const isSettings = searchParams.get('settings') === 'true';
  const { rel } = useRelativePath();
  const router = useRouter();

  if (!projectId) {
    // Redirect to dashboard if no project ID is provided
    router.replace(rel('/'));
    return null;
  }

  if (isSettings) {
    return <ProjectSettingsView id={projectId} />;
  }

  return <ProjectDetailView id={projectId} />;
}

export default function ProjectPage() {
  return (
    <div className="min-h-screen bg-gray-50 pb-12">
      <Navbar />
      <Suspense fallback={<div className="flex items-center justify-center min-h-screen bg-gray-50"><div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div></div>}>
        <ProjectContent />
      </Suspense>
    </div>
  );
}
