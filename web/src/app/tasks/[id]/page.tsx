import React, { use } from 'react';
import TaskDetailView from './TaskDetailView';

export async function generateStaticParams() {
  return Array.from({ length: 100 }, (_, i) => ({ id: (i + 1).toString() }));
}

export default function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  return <TaskDetailView id={id} />;
}
