import React from 'react';
import Link from 'next/link';
import { useAutorepeatTasks } from '@/hooks/useAutorepeatTasks';
import { useRelativePath } from '@/hooks/useRelativePath';
import StatusBadge from './StatusBadge';

export const AutorepeatTasks: React.FC = () => {
  const { data: tasks, isLoading } = useAutorepeatTasks();
  const { rel } = useRelativePath();

  if (isLoading || !tasks || tasks.length === 0) {
    return null;
  }

  return (
    <div className="mb-8 p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
      <h3 className="text-lg font-bold text-gray-900 mb-4">Running Autorepeat Tasks</h3>
      <div className="overflow-x-auto">
        <table className="min-w-full divide-y divide-gray-200 table-fixed">
          <thead className="bg-gray-50">
            <tr>
              <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
              <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Issue</th>
              <th scope="col" className="w-32 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
            </tr>
          </thead>
          <tbody className="bg-white divide-y divide-gray-200">
            {tasks.map((task) => (
              <tr key={task.id}>
                <td className="px-6 py-4 text-sm font-medium text-gray-900">
                  <a
                    href={`https://github.com/${task.github_repo}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-blue-600 hover:underline break-words"
                  >
                    {task.github_repo}
                  </a>
                </td>
                <td className="px-6 py-4 text-sm text-gray-500">
                  <Link href={rel(`/tasks/?id=${task.id}`)} className="text-blue-600 hover:underline break-words">
                    #{task.issue_number} - {task.title}
                  </Link>
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  <StatusBadge status={task.status} />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
};

export default AutorepeatTasks;
