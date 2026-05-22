import React from 'react';
import Link from 'next/link';
import { components } from '@/types/api';
import TaskStatusGrid from './TaskStatusGrid';
import { useRelativePath } from '@/hooks/useRelativePath';

type Project = components['schemas']['Project'];
type Task = components['schemas']['Task'];

interface ProjectCardProps {
  project: Project;
  tasks?: Task[];
}

export const ProjectCard: React.FC<ProjectCardProps> = ({ project, tasks = [] }) => {
  const { rel } = useRelativePath();

  return (
    <div className="bg-white border border-gray-200 rounded-lg shadow-sm p-5 flex flex-col h-full">
      <div className="flex justify-between items-start mb-2">
        <h3 className="text-lg font-bold text-gray-900 truncate pr-4">
          <Link
            href={rel(`/projects/${project.id}`)}
            className="hover:text-blue-600 transition-colors"
          >
            {project.github_repo}
          </Link>
        </h3>
        <Link
          href={rel(`/projects/${project.id}/settings`)}
          className="text-gray-400 hover:text-gray-600 focus:outline-none p-1 rounded-md hover:bg-gray-100 transition-colors"
          title="Project Settings"
        >
          <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
          </svg>
        </Link>
      </div>

      <div className="flex-grow">
        {project.github_username && (
          <p className="text-xs text-gray-500 mb-4">
            Linked as <span className="font-medium">{project.github_username}</span>
          </p>
        )}

        <div className="mt-auto">
          <TaskStatusGrid tasks={tasks} />
        </div>
      </div>

      <div className="mt-4 pt-4 border-t border-gray-100">
        <Link
          href={rel(`/projects/${project.id}`)}
          className="text-blue-600 hover:text-blue-800 text-sm font-semibold inline-flex items-center group"
        >
          View Details
          <svg className="w-4 h-4 ml-1 transform transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7"></path>
          </svg>
        </Link>
      </div>
    </div>
  );
};

export default ProjectCard;
