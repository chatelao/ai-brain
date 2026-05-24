'use client';

import React, { useState, useMemo, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useProject } from '@/hooks/useProject';
import { useUser } from '@/hooks/useUser';
import { useRelativePath } from '@/hooks/useRelativePath';
import Navbar from '@/components/Navbar';
import Breadcrumbs from '@/components/Breadcrumbs';
import BlocklyEditor from '@/components/blockly/BlocklyEditor';

export default function ProjectSettingsView({ id }: { id: string }) {
  const { rel } = useRelativePath();
  const router = useRouter();
  const projectId = parseInt(id);
  const { data: project, isLoading: projectLoading, error: projectError, updateSettings, isUpdatingSettings, updateNotifications, isUpdatingNotifications, deleteProject, isDeleting } = useProject(projectId);
  const { data: user } = useUser();

  const [activeTab, setActiveTab] = useState<'general' | 'webhook' | 'automation' | 'danger'>('general');
  const [repo, setRepo] = useState('');
  const [accountId, setAccountId] = useState<number | ''>('');
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const githubAccounts = user?.github_accounts || [];

  useEffect(() => {
    if (project) {
      setRepo(project.github_repo || '');
      setAccountId(project.github_account_id || '');
    }
  }, [project]);

  const recommendedId = useMemo(() => {
    const owner = repo.split('/')[0]?.trim();
    if (!owner) return null;
    const match = githubAccounts.find(
      (a) => a.github_username?.toLowerCase() === owner.toLowerCase()
    );
    return match ? match.github_account_id : null;
  }, [repo, githubAccounts]);

  const handleUpdateGeneral = async (e: React.FormEvent) => {
    e.preventDefault();
    setSuccessMessage(null);
    setErrorMessage(null);
    try {
      await updateSettings({
        github_repo: repo,
        github_account_id: accountId as number,
      });
      setSuccessMessage('Project settings updated successfully.');
    } catch (err: any) {
      setErrorMessage(err.response?.data?.error || 'Failed to update settings.');
    }
  };

  const handleDelete = async () => {
    if (!confirm('Are you sure you want to delete this project? This action cannot be undone.')) return;
    try {
      await deleteProject();
      router.push(rel('/'));
    } catch (err: any) {
      setErrorMessage(err.response?.data?.error || 'Failed to delete project.');
    }
  };

  if (projectLoading) {
    return (
      <div className="min-h-screen bg-gray-50">
        <Navbar />
        <div className="flex items-center justify-center h-[calc(100vh-64px)]">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
        </div>
      </div>
    );
  }

  if (projectError || !project) {
    return (
      <div className="min-h-screen bg-gray-50">
        <Navbar />
        <div className="flex flex-col items-center justify-center h-[calc(100vh-64px)] p-4">
          <div className="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg max-w-md w-full">
            <p className="font-bold">Error loading project</p>
            <p className="text-sm">Please try again later or check your connection.</p>
          </div>
          <Link href={rel('/')} className="mt-4 text-blue-600 hover:underline">
            Back to Dashboard
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 pb-12">
      <main className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
        <div className="mb-6">
          <Breadcrumbs
            items={[
              { label: project?.github_repo || 'Project', href: `/projects/?id=${id}` },
              { label: 'Settings' },
            ]}
          />
          <h1 className="text-2xl font-bold text-gray-900">Project Settings</h1>
        </div>

        <div className="mb-6 border-b border-gray-200">
          <nav className="flex space-x-8" aria-label="Tabs">
            {[
              { id: 'general', label: 'General' },
              { id: 'webhook', label: 'Webhook' },
              { id: 'automation', label: 'Automation' },
              { id: 'danger', label: 'Danger Zone' },
            ].map((tab) => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id as any)}
                className={`py-4 px-1 border-b-2 font-medium text-sm transition-colors ${
                  activeTab === tab.id
                    ? 'border-blue-500 text-blue-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                }`}
              >
                {tab.label}
              </button>
            ))}
          </nav>
        </div>

        {successMessage && (
          <div className="p-4 mb-6 text-sm text-green-800 rounded-lg bg-green-50 border border-green-100">
            {successMessage}
          </div>
        )}

        {errorMessage && (
          <div className="p-4 mb-6 text-sm text-red-800 rounded-lg bg-red-50 border border-red-100">
            {errorMessage}
          </div>
        )}

        <div className="space-y-6">
          {activeTab === 'general' && (
            <section className="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">General Configuration</h3>
              <form onSubmit={handleUpdateGeneral} className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-bold text-gray-700 uppercase mb-1">GitHub Account</label>
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
                    <label className="block text-xs font-bold text-gray-700 uppercase mb-1">Repository (owner/repo)</label>
                    <input
                      type="text"
                      value={repo}
                      onChange={(e) => setRepo(e.target.value)}
                      className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                      required
                    />
                  </div>
                </div>
                <div className="flex justify-end">
                  <button
                    type="submit"
                    disabled={isUpdatingSettings}
                    className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                  >
                    {isUpdatingSettings ? 'Saving...' : 'Save Changes'}
                  </button>
                </div>
              </form>
            </section>
          )}

          {activeTab === 'webhook' && (
            <section className="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-2">Webhook Status</h3>
              <div className="flex items-center gap-3 mb-4">
                <span className="px-2.5 py-0.5 text-xs font-bold rounded-full bg-green-100 text-green-800">
                  Connected
                </span>
                <span className="text-sm text-gray-500">
                  Webhook is active and receiving events.
                </span>
              </div>
              <div className="space-y-3">
                <div>
                  <label className="block text-xs font-bold text-gray-700 uppercase mb-1">Payload URL</label>
                  <input
                    type="text"
                    readOnly
                    value={`${window.location.origin}/github/webhook.php?project_id=${projectId}`}
                    className="w-full bg-gray-50 rounded-lg border-gray-200 text-sm text-gray-500"
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-gray-700 uppercase mb-1">Secret</label>
                  <input
                    type="text"
                    readOnly
                    value={project?.webhook_secret || ''}
                    className="w-full bg-gray-50 rounded-lg border-gray-200 text-sm text-gray-500"
                  />
                </div>
              </div>
            </section>
          )}

          {activeTab === 'automation' && (
            <section className="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-2">Project Automation (Experimental)</h3>
              <p className="text-sm text-gray-500 mb-6">
                Define workflows specific to this project using Blockly.
                These scripts are executed when events occur in this repository.
              </p>

              <BlocklyEditor
                initialXml={project?.blockly_config?.xml}
                onSave={async (config) => {
                  setSuccessMessage(null);
                  setErrorMessage(null);
                  try {
                    await updateSettings({ blockly_config: config });
                    setSuccessMessage('Automation workflow saved successfully.');
                  } catch (err: any) {
                    setErrorMessage(err.response?.data?.error || 'Failed to save workflow.');
                  }
                }}
              />
            </section>
          )}

          {activeTab === 'danger' && (
            <section className="bg-white border border-red-200 rounded-xl shadow-sm p-6">
              <h3 className="text-lg font-semibold text-red-900 mb-2">Danger Zone</h3>
              <p className="text-sm text-gray-600 mb-4">
                Deleting a project will remove all associated tasks and logs from the dashboard. This action cannot be undone.
              </p>
              <button
                onClick={handleDelete}
                disabled={isDeleting}
                className="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
              >
                {isDeleting ? 'Deleting...' : 'Delete Project'}
              </button>
            </section>
          )}
        </div>
      </main>
    </div>
  );
}
