import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import { TaskSidebar } from '../TaskSidebar';
import { components } from '@/types/api';

type Task = components['schemas']['Task'];

// Mock useRelativePath hook
vi.mock('@/hooks/useRelativePath', () => ({
  useRelativePath: () => ({
    rel: (path: string) => path,
  }),
}));

describe('TaskSidebar', () => {
  const mockTask: Task = {
    id: 1,
    project_id: 1,
    issue_number: 131,
    github_repo: 'TODO_OWNER/TODO_REPO',
    title: 'Test Task',
    status: 'analyzing',
    jules_url: 'https://jules.google.com/task/123',
    pr_url: 'https://github.com/TODO_OWNER/TODO_REPO/pull/132',
    created_at: '2023-10-27T10:10:00Z',
    last_synced_at: '2023-10-27T10:15:00Z',
  };

  it('renders GitHub issue link correctly with repo name', () => {
    render(<TaskSidebar task={mockTask} />);
    const link = screen.getByText('#131');
    expect(link).toHaveAttribute('href', 'https://github.com/TODO_OWNER/TODO_REPO/issues/131');
  });

  it('renders Jules session link correctly', () => {
    render(<TaskSidebar task={mockTask} />);
    const link = screen.getByText('View', { selector: '.text-purple-600' });
    expect(link).toHaveAttribute('href', 'https://jules.google.com/task/123');
  });

  it('renders Pull Request link correctly', () => {
    render(<TaskSidebar task={mockTask} />);
    const link = screen.getByText('View', { selector: '.text-green-600' });
    expect(link).toHaveAttribute('href', 'https://github.com/TODO_OWNER/TODO_REPO/pull/132');
  });

  it('renders None when links are missing', () => {
    const taskWithoutLinks: Task = {
      ...mockTask,
      github_repo: undefined,
      jules_url: null,
      pr_url: null,
    };
    render(<TaskSidebar task={taskWithoutLinks} />);
    expect(screen.getByText('#131').tagName).toBe('SPAN');
    expect(screen.getAllByText('None')).toHaveLength(2);
  });
});
