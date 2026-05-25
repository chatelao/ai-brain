'use client';

import React, { useState } from 'react';

interface CreateIssueModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (title: string, body: string) => Promise<void>;
  isSubmitting: boolean;
}

const CreateIssueModal: React.FC<CreateIssueModalProps> = ({ isOpen, onClose, onSubmit, isSubmitting }) => {
  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const [error, setError] = useState<string | null>(null);

  if (!isOpen) return null;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!title.trim()) return;

    setError(null);
    try {
      await onSubmit(title, body);
      setTitle('');
      setBody('');
      onClose();
    } catch (err: any) {
      console.error('Failed to create issue:', err);
      setError(err.response?.data?.error || err.message || 'Failed to create issue. Please try again.');
    }
  };

  return (
    <div className="fixed inset-0 z-[150] flex items-center justify-center p-4 bg-black bg-opacity-50">
      <div className="bg-white rounded-xl shadow-xl max-w-lg w-full overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
          <h3 className="text-lg font-bold text-gray-900">Create New GitHub Issue</h3>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          {error && (
            <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
              {error}
            </div>
          )}

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Title</label>
            <input
              type="text"
              placeholder="Enter issue title"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
              required
              autoFocus
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Body (Optional)</label>
            <textarea
              placeholder="Describe the issue..."
              value={body}
              onChange={(e) => setBody(e.target.value)}
              rows={5}
              className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
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
              disabled={isSubmitting || !title.trim()}
              className="flex-1 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50"
            >
              {isSubmitting ? 'Creating...' : 'Create Issue'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default CreateIssueModal;
