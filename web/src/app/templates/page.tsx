'use client';

import React, { useState, useMemo } from 'react';
import Navbar from '@/components/Navbar';
import { useTemplates } from '@/hooks/useTemplates';
import { components } from '@/types/api';
import Breadcrumbs from '@/components/Breadcrumbs';

type Template = components['schemas']['Template'];

export default function TemplatesPage() {
  const { data: templates, isLoading, saveTemplate, deleteTemplate, isSaving } = useTemplates();
  const [editingTemplate, setEditingTemplate] = useState<Partial<Template> | null>(null);

  const placeholders = useMemo(() => {
    if (!editingTemplate) return [];
    const combined = (editingTemplate.title_template || '') + ' ' + (editingTemplate.body_template || '');
    const matches = combined.match(/%\d+/g) || [];
    return [...new Set(matches)].sort((a, b) => parseInt(a.slice(1)) - parseInt(b.slice(1)));
  }, [editingTemplate]);

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    if (editingTemplate) {
      await saveTemplate(editingTemplate);
      setEditingTemplate(null);
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-50">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 pb-12">
      <Navbar />

      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
        <Breadcrumbs items={[{ label: 'Issue Templates' }]} />
        <div className="flex justify-between items-center mb-8">
          <div>
            <h2 className="text-2xl font-bold text-gray-900">Issue Templates</h2>
            <p className="text-gray-500 text-sm mt-1">Manage reusable templates for GitHub issues.</p>
          </div>
          <button
            onClick={() => setEditingTemplate({ name: '', title_template: '', body_template: '', parameter_config: {} })}
            className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700"
          >
            Create New Template
          </button>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <div className="lg:col-span-2 space-y-4">
            <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title Template</th>
                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {templates?.length === 0 ? (
                    <tr>
                      <td colSpan={3} className="px-6 py-12 text-center text-gray-500 italic">No templates found.</td>
                    </tr>
                  ) : (
                    templates?.map((t) => (
                      <tr key={t.id} className="hover:bg-gray-50">
                        <td className="px-6 py-4 text-sm font-medium text-gray-900">{t.name}</td>
                        <td className="px-6 py-4 text-sm text-gray-500 truncate max-w-xs">{t.title_template}</td>
                        <td className="px-6 py-4 text-right text-sm font-medium space-x-3">
                          <button onClick={() => setEditingTemplate(t)} className="text-blue-600 hover:text-blue-900">Edit</button>
                          <button onClick={() => t.id && deleteTemplate(t.id)} className="text-red-600 hover:text-red-900">Delete</button>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>

          <div className="lg:col-span-1">
            {editingTemplate ? (
              <div className="bg-white border border-gray-200 rounded-xl shadow-sm p-6 sticky top-24">
                <h3 className="text-lg font-bold text-gray-900 mb-4">
                  {editingTemplate.id ? 'Edit Template' : 'New Template'}
                </h3>
                <form onSubmit={handleSave} className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700">Name</label>
                    <input
                      type="text"
                      required
                      value={editingTemplate.name}
                      onChange={(e) => setEditingTemplate({ ...editingTemplate, name: e.target.value })}
                      className="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 text-sm"
                      placeholder="e.g. Bug Report"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700">Title Template</label>
                    <input
                      type="text"
                      required
                      value={editingTemplate.title_template}
                      onChange={(e) => setEditingTemplate({ ...editingTemplate, title_template: e.target.value })}
                      className="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 text-sm"
                      placeholder="e.g. Bug: %1 in %2"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700">Body Template</label>
                    <textarea
                      rows={4}
                      value={editingTemplate.body_template || ''}
                      onChange={(e) => setEditingTemplate({ ...editingTemplate, body_template: e.target.value })}
                      className="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 text-sm"
                      placeholder="e.g. Steps to reproduce: %1"
                    />
                  </div>

                  {placeholders.length > 0 && (
                    <div className="space-y-2">
                      <label className="block text-sm font-medium text-gray-700">Parameter Labels</label>
                      {placeholders.map((p) => (
                        <div key={p} className="flex items-center space-x-2">
                          <span className="text-xs font-mono bg-gray-100 px-2 py-1 rounded">{p}</span>
                          <input
                            type="text"
                            value={editingTemplate.parameter_config?.[p] || ''}
                            onChange={(e) => {
                              const newConfig = { ...(editingTemplate.parameter_config || {}) };
                              newConfig[p] = e.target.value;
                              setEditingTemplate({ ...editingTemplate, parameter_config: newConfig });
                            }}
                            className="block w-full border border-gray-300 rounded-md shadow-sm p-1 text-xs"
                            placeholder={`Label for ${p}`}
                          />
                        </div>
                      ))}
                    </div>
                  )}

                  <div className="flex space-x-3 pt-4">
                    <button
                      type="submit"
                      disabled={isSaving}
                      className="flex-1 bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
                    >
                      {isSaving ? 'Saving...' : 'Save'}
                    </button>
                    <button
                      type="button"
                      onClick={() => setEditingTemplate(null)}
                      className="flex-1 bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-50"
                    >
                      Cancel
                    </button>
                  </div>
                </form>
              </div>
            ) : (
              <div className="bg-gray-50 border-2 border-dashed border-gray-200 rounded-xl p-8 text-center">
                <p className="text-sm text-gray-500">Select a template to edit or create a new one.</p>
              </div>
            )}
          </div>
        </div>
      </main>
    </div>
  );
}
