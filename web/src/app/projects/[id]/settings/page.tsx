import React, { use } from 'react';
import ProjectSettingsView from './ProjectSettingsView';

export async function generateStaticParams() {
  return [{ id: '1' }];
}

export default function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  return <ProjectSettingsView id={id} />;
}
