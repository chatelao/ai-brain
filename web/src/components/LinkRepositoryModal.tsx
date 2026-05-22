'use client';

import React, { useState, useMemo } from 'react';
import { useUser } from '@/hooks/useUser';
import { useCreateProject } from '@/hooks/useProjects';

interface LinkRepositoryModalProps {
  isOpen: boolean;
  onClose: () => void;
}

const LinkRepositoryModal: React.FC<LinkRepositoryModalProps> = ({ isOpen, onClose }) => {
  const { data: user } = useUser();
  const createProject = useCreateProject();
  const [repo, setRepo] = useState('');
  const [accountId, setAccountId] = useState<number | ''>('');

  const githubAccounts = user?.github_accounts || [];

  const recommendedId = useMemo(() => {
    const owner = repo.split('/')[0]?.trim();
    if (!owner) return null;
    const match = githubAccounts.find(
      (a) => a.github_username?.toLowerCase() === owner.toLowerCase()
    );
    return match ? match.github_account_id : null;
  }, [repo, githubAccounts]);

  // Update selected account if recommendation changes
  React.useEffect(() => {
    if (recommendedId && accountId === '') {
      setAccountId(recommendedId);
    }
  }, [recommendedId, accountId]);

  // Set default account if none selected
  React.useEffect(() => {
    if (githubAccounts.length > 0 && accountId === '') {
      setAccountId(githubAccounts[0].github_account_id as number);
    }
  }, [githubAccounts, accountId]);

  if (!isOpen) return null;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!repo || !accountId) return;

    try {
      await createProject.mutateAsync({
        github_repo: repo,
        github_account_id: accountId as number,
      });
      onClose();
      setRepo('');
    } catch (err) {
      console.error('Failed to create project:', err);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50">
      <div className="bg-white rounded-xl shadow-xl max-w-md w-full overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
          <h3 className="text-lg font-bold text-gray-900">Link New Repository</h3>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">GitHub Account</label>
            <select
              value={accountId}
              onChange={(e) => setAccountId(parseInt(e.target.value))}
              className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
              required
            >
              {githubAccounts.map((account) => (
                <option key={account.github_account_id} value={account.github_account_id}>
                  {account.github_username}{' '}
                  {recommendedId === account.github_account_id ? '(Recommended)' : ''}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Repository (owner/repo)</label>
            <input
              type="text"
              placeholder="google/jules"
              value={repo}
              onChange={(e) => setRepo(e.target.value)}
              className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
              required
            />
          </div>

          <div className="pt-4 flex gap-3">
            <button
              type="button"
              onClick={onClose}
              className="flex-1 px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={createProject.isPending}
              className="flex-1 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
            >
              {createProject.isPending ? 'Linking...' : 'Link Repository'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default LinkRepositoryModal;
