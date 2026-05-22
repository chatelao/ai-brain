'use client';

import React, { useState, useMemo } from 'react';
import { useProjects } from '@/hooks/useProjects';
import { useTasks } from '@/hooks/useTasks';
import ProjectCard from '@/components/ProjectCard';
import TaskFilterBar from '@/components/TaskFilterBar';
import Navbar from '@/components/Navbar';
import LinkRepositoryModal from '@/components/LinkRepositoryModal';
import { components } from '@/types/api';

type Project = components['schemas']['Project'];
type TaskStatus = components['schemas']['Task']['status'];

const ProjectCardWrapper = ({
  project,
  statusFilter,
}: {
  project: Project;
  statusFilter: TaskStatus | 'all';
}) => {
  const { data: tasks, isLoading } = useTasks(project.id);

  const filteredTasks = useMemo(() => {
    if (!tasks) return [];
    if (statusFilter === 'all') return tasks;
    return tasks.filter((t) => t.status === statusFilter);
  }, [tasks, statusFilter]);

  if (statusFilter !== 'all' && filteredTasks.length === 0 && !isLoading) {
    return null;
  }

  return <ProjectCard project={project} tasks={filteredTasks} />;
};

export default function Dashboard() {
  const { data: projects, isLoading, error } = useProjects();
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<TaskStatus | 'all'>('all');
  const [isModalOpen, setIsModalOpen] = useState(false);

  const filteredProjects = useMemo(() => {
    if (!projects) return [];
    return projects.filter((p) =>
      p.github_repo?.toLowerCase().includes(search.toLowerCase())
    );
  }, [projects, search]);

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-50">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen p-4 bg-gray-50">
        <div className="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg max-w-md w-full">
          <p className="font-bold">Error loading projects</p>
          <p className="text-sm">Please try again later or check your connection.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 pb-12">
      <Navbar />

      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
        <div className="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
          <div>
            <h2 className="text-2xl font-bold text-gray-900">Project Dashboard</h2>
            <p className="text-gray-500 text-sm mt-1">Manage your agents and track task progress.</p>
          </div>
          <button
            onClick={() => setIsModalOpen(true)}
            className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            Link New Repository
          </button>
        </div>

        <TaskFilterBar
          search={search}
          onSearchChange={setSearch}
          statusFilter={statusFilter}
          onStatusFilterChange={setStatusFilter}
        />

        {filteredProjects.length === 0 ? (
          <div className="text-center py-12 bg-white rounded-lg border border-gray-200">
            <svg
              className="mx-auto h-12 w-12 text-gray-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="2"
                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
              />
            </svg>
            <h3 className="mt-2 text-sm font-medium text-gray-900">No projects found</h3>
            <p className="mt-1 text-sm text-gray-500">Get started by linking a new GitHub repository.</p>
            <div className="mt-6">
              <button
                onClick={() => setIsModalOpen(true)}
                className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none"
              >
                Link New Repository
              </button>
            </div>
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {filteredProjects.map((project) => (
              <ProjectCardWrapper key={project.id} project={project} statusFilter={statusFilter} />
            ))}
          </div>
        )}
      </main>

      <LinkRepositoryModal isOpen={isModalOpen} onClose={() => setIsModalOpen(false)} />
    </div>
  );
}
