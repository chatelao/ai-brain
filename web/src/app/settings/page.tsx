'use client';

import React, { useState, useEffect } from 'react';
import Navbar from '@/components/Navbar';
import { useUser, useUpdateUser } from '@/hooks/useUser';
import apiClient from '@/api/client';
import { useRelativePath } from '@/hooks/useRelativePath';

export default function SettingsPage() {
  const { rel } = useRelativePath();
  const { data: user, isLoading } = useUser();
  const updateUser = useUpdateUser();
  const [activeTab, setActiveTab] = useState<'general' | 'notifications'>('general');
  const [testStatus, setTestStatus] = useState<{ status: 'success' | 'error'; message: string } | null>(null);
  const [isTesting, setIsTesting] = useState(false);

  // Form states
  const [julesKey, setJulesKey] = useState('');
  const [tgBotName, setTgBotName] = useState('');
  const [tgBotToken, setTgBotToken] = useState('');
  const [tgWebhookSecret, setTgWebhookSecret] = useState('');

  // Initialize form states when user data is loaded
  useEffect(() => {
    if (user) {
      setTgBotName(user.telegram_bot_name || '');
    }
  }, [user]);

  if (isLoading) {
    return (
      <div className="min-h-screen bg-gray-50">
        <Navbar />
        <div className="flex items-center justify-center h-[calc(100vh-64px)]">
          <div className="text-gray-500">Loading settings...</div>
        </div>
      </div>
    );
  }

  const handleUpdateJulesKey = (e: React.FormEvent) => {
    e.preventDefault();
    updateUser.mutate({ jules_api_key: julesKey });
    setJulesKey(''); // Clear after save since it will show as masked if we reload
  };

  const handleUpdateTelegram = (e: React.FormEvent) => {
    e.preventDefault();
    updateUser.mutate({
      telegram_bot_name: tgBotName,
      telegram_bot_token: tgBotToken || undefined,
      telegram_webhook_secret: tgWebhookSecret || undefined,
    });
    setTgBotToken('');
    setTgWebhookSecret('');
  };

  const toggleChannel = (channel: 'in_app' | 'browser' | 'telegram') => {
    if (!user?.notification_settings) return;
    updateUser.mutate({
      notification_settings: {
        ...user.notification_settings,
        [channel]: !user.notification_settings[channel],
      },
    });
  };

  const toggleEvent = (event: string) => {
    if (!user?.notification_event_settings) return;
    updateUser.mutate({
      notification_event_settings: {
        ...user.notification_event_settings,
        [event]: !user.notification_event_settings[event as keyof typeof user.notification_event_settings],
      },
    });
  };

  const statusGrouping = {
    'CREATED': {
      'created': 'Waiting for Agent'
    },
    'PROCESSING': {
      'analyzing': 'Analyzing',
      'planning': 'Planning',
      'executing': 'Executing',
      'verifying': 'Verifying',
      'implemented': 'Implemented',
      'checking': 'Checking'
    },
    'READY': {
      'ready': 'Ready'
    },
    'FINISHED': {
      'finished': 'Finished'
    },
    'FAILED': {
      'failed_jules': 'Jules Failed',
      'failed_pr': 'PR Failed'
    }
  };

  const sendTestBroadcast = async () => {
    setIsTesting(true);
    setTestStatus(null);
    try {
      // API call should be absolute to domain root, as per user requirement "(not the ones to the API)"
      const response = await apiClient.post('/ajax-notifications.php?action=test_broadcast', {});
      if (response.data.status === 'success') {
        const channels = Object.keys(response.data.channels).filter((c: any) => response.data.channels[c]);
        setTestStatus({ status: 'success', message: `Test broadcast sent to: ${channels.join(', ')}` });
      } else {
        setTestStatus({ status: 'error', message: response.data.error || 'Test failed' });
      }
    } catch (err) {
      setTestStatus({ status: 'error', message: 'Connection error' });
    } finally {
      setIsTesting(false);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 pb-12">
      <Navbar />

      <main className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-gray-900">Account Settings</h1>
          <p className="text-sm text-gray-500 mt-1">Manage your AI keys, integrations, and notification preferences.</p>
        </div>

        <div className="mb-6 border-b border-gray-200">
          <nav className="flex space-x-8" aria-label="Tabs">
            <button
              onClick={() => setActiveTab('general')}
              className={`py-4 px-1 border-b-2 font-medium text-sm transition-colors ${
                activeTab === 'general'
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              General & Integrations
            </button>
            <button
              onClick={() => setActiveTab('notifications')}
              className={`py-4 px-1 border-b-2 font-medium text-sm transition-colors ${
                activeTab === 'notifications'
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              Notifications
            </button>
          </nav>
        </div>

        {activeTab === 'general' && (
          <div className="space-y-6">
            {/* Jules API Key */}
            <section className="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-2">Jules API Key</h3>
              <p className="text-sm text-gray-500 mb-4">Your personal Google AI (Gemini) API key. Required for agent features.</p>
              <form onSubmit={handleUpdateJulesKey} className="flex gap-3">
                <input
                  type="password"
                  placeholder={user?.has_jules_key ? '********' : 'Enter API Key'}
                  value={julesKey}
                  onChange={(e) => setJulesKey(e.target.value)}
                  className="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                />
                <button
                  type="submit"
                  disabled={updateUser.isPending || !julesKey}
                  className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                >
                  {updateUser.isPending ? 'Saving...' : 'Save'}
                </button>
              </form>
              {user?.has_jules_key && (
                <div className="mt-3 flex items-center text-xs text-green-600">
                  <svg className="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l5-5z" clipRule="evenodd"></path></svg>
                  API Key is configured
                  {user.jules_quota_limit && (
                    <span className="ml-2 text-gray-500">
                      Quota: {user.jules_quota_usage} / {user.jules_quota_limit}
                    </span>
                  )}
                </div>
              )}
            </section>

            {/* Telegram Bot */}
            <section className="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-2">Telegram Custom Bot</h3>
              <p className="text-sm text-gray-500 mb-4">Use your own Telegram Bot for notifications and interactions.</p>
              <form onSubmit={handleUpdateTelegram} className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-bold text-gray-700 uppercase mb-1">Bot Name</label>
                    <input
                      type="text"
                      placeholder="e.g. MyAgentBot"
                      value={tgBotName}
                      onChange={(e) => setTgBotName(e.target.value)}
                      className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-gray-700 uppercase mb-1">Bot Token</label>
                    <input
                      type="password"
                      placeholder={user?.telegram_bot_token ? '********' : 'Bot Token'}
                      value={tgBotToken}
                      onChange={(e) => setTgBotToken(e.target.value)}
                      className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    />
                  </div>
                </div>
                <div>
                  <label className="block text-xs font-bold text-gray-700 uppercase mb-1">Webhook Secret</label>
                  <input
                    type="password"
                    placeholder={user?.telegram_webhook_secret ? '********' : 'Secret Token'}
                    value={tgWebhookSecret}
                    onChange={(e) => setTgWebhookSecret(e.target.value)}
                    className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                  />
                </div>
                <div className="flex justify-end">
                  <button
                    type="submit"
                    disabled={updateUser.isPending}
                    className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                  >
                    {updateUser.isPending ? 'Saving...' : 'Update Bot Config'}
                  </button>
                </div>
              </form>
              {user?.telegram_chat_id ? (
                <div className="mt-4 p-3 bg-green-50 border border-green-100 rounded-lg flex items-center text-sm text-green-700">
                  <svg className="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l5-5z" clipRule="evenodd"></path></svg>
                  Linked to Telegram account
                </div>
              ) : user?.telegram_bot_name && (
                <div className="mt-4 p-4 bg-blue-50 border border-blue-100 rounded-lg">
                  <p className="text-sm text-blue-700 mb-3">Your account is not linked yet. Message your bot to start.</p>
                  <a
                    href={`https://t.me/${user.telegram_bot_name}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-xs font-bold rounded hover:bg-blue-700 transition-colors"
                  >
                    Open @{user.telegram_bot_name}
                  </a>
                </div>
              )}
            </section>

            {/* Linked Accounts */}
            <section className="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Linked GitHub Accounts</h3>
              <div className="space-y-3">
                {user?.github_accounts?.length === 0 ? (
                  <p className="text-sm text-gray-500 italic">No GitHub accounts linked yet.</p>
                ) : (
                  user?.github_accounts?.map((acc) => (
                    <div key={acc.github_username} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-100">
                      <div className="flex items-center">
                        <svg className="w-5 h-5 mr-3 text-gray-900" fill="currentColor" viewBox="0 0 24 24"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.43.372.823 1.102.823 2.222 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
                        <span className="text-sm font-medium text-gray-900">{acc.github_username}</span>
                      </div>
                      <span className="text-[10px] uppercase font-bold text-green-600 bg-green-50 px-2 py-0.5 rounded">Linked</span>
                    </div>
                  ))
                )}
                <div className="pt-2">
                  <a
                    href={rel('/github/login.php')}
                    className="inline-flex items-center text-sm font-medium text-blue-600 hover:underline"
                  >
                    Link another GitHub account →
                  </a>
                </div>
              </div>
            </section>

            {/* UI Preference */}
            <section className="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-2">Interface Preference</h3>
              <p className="text-sm text-gray-500 mb-4">Switch back to the legacy PHP-based interface if you encounter issues or prefer the old design.</p>
              <button
                onClick={() => {
                  document.cookie = "prefer_legacy=1; path=/; max-age=" + (30 * 24 * 60 * 60);
                  window.location.href = rel('/index.php?legacy=1');
                }}
                className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              >
                Switch to Legacy UI
              </button>
            </section>
          </div>
        )}

        {activeTab === 'notifications' && (
          <div className="space-y-6">
            {testStatus && (
              <div className={`p-4 rounded-lg text-sm border ${
                testStatus.status === 'success' ? 'bg-green-50 border-green-100 text-green-800' : 'bg-red-50 border-red-100 text-red-800'
              }`}>
                {testStatus.message}
              </div>
            )}

            <section className="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
              <div className="flex justify-between items-center mb-6">
                <div>
                  <h3 className="text-lg font-semibold text-gray-900">Notification Channels</h3>
                  <p className="text-sm text-gray-500">Choose where you want to receive alerts.</p>
                </div>
                <button
                  onClick={sendTestBroadcast}
                  disabled={isTesting}
                  className="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-lg transition-colors disabled:opacity-50"
                >
                  {isTesting ? 'Sending...' : 'Test Broadcast'}
                </button>
              </div>

              <div className="space-y-4">
                {(['in_app', 'browser', 'telegram'] as const).map((channel) => (
                  <div key={channel} className="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-gray-100">
                    <div>
                      <h4 className="text-sm font-bold text-gray-900 capitalize">
                        {channel.replace('_', '-')}
                      </h4>
                      <p className="text-xs text-gray-500">
                        {channel === 'in_app' && 'Notifications in the sidebar bell.'}
                        {channel === 'browser' && 'Desktop push notifications.'}
                        {channel === 'telegram' && 'Direct messages to your Telegram bot.'}
                      </p>
                    </div>
                    <button
                      onClick={() => toggleChannel(channel)}
                      disabled={updateUser.isPending}
                      className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none ring-offset-2 focus:ring-2 focus:ring-blue-500 ${
                        user?.notification_settings?.[channel] ? 'bg-blue-600' : 'bg-gray-200'
                      }`}
                    >
                      <span
                        className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
                          user?.notification_settings?.[channel] ? 'translate-x-5' : 'translate-x-0'
                        }`}
                      />
                    </button>
                  </div>
                ))}
              </div>
            </section>

            <section className="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-2">Global State Subscriptions</h3>
              <p className="text-sm text-gray-500 mb-6">Choose which status changes trigger a notification across all projects.</p>

              <div className="space-y-8">
                {(Object.entries(statusGrouping) as [string, Record<string, string>][]).map(([group, statuses]) => (
                  <div key={group} className="border-t border-gray-100 pt-4 first:border-0 first:pt-0">
                    <h4 className="text-xs font-bold text-gray-900 mb-3 uppercase tracking-wider">{group}</h4>
                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                      {Object.entries(statuses).map(([id, label]) => (
                        <div key={id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-100">
                          <span className="text-xs font-medium text-gray-600">{label}</span>
                          <button
                            onClick={() => toggleEvent(id)}
                            disabled={updateUser.isPending}
                            className={`relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none ${
                              user?.notification_event_settings?.[id as keyof typeof user.notification_event_settings] ? 'bg-blue-600' : 'bg-gray-200'
                            }`}
                          >
                            <span
                              className={`pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
                                user?.notification_event_settings?.[id as keyof typeof user.notification_event_settings] ? 'translate-x-4' : 'translate-x-0'
                              }`}
                            />
                          </button>
                        </div>
                      ))}
                    </div>
                  </div>
                ))}
              </div>
            </section>
          </div>
        )}
      </main>
    </div>
  );
}
