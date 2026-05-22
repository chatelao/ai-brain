import React, { use } from 'react';
import TaskDetailView from './TaskDetailView';

export async function generateStaticParams() {
  return [{ id: '1' }];
}

export default function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  return <TaskDetailView id={id} />;
}
