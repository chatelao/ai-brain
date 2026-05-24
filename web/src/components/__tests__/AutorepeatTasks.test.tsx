import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import { AutorepeatTasks } from '../AutorepeatTasks';

// Mock hooks
vi.mock('@/hooks/useAutorepeatTasks', () => ({
  useAutorepeatTasks: () => ({
    data: [
      {
        id: 1,
        github_repo: 'google/jules',
        issue_number: 101,
        title: 'Auto task',
        status: 'executing',
      },
    ],
    isLoading: false,
  }),
}));

vi.mock('@/hooks/useRelativePath', () => ({
  useRelativePath: () => ({
    rel: (path: string) => path,
  }),
}));

// Mock StatusBadge
vi.mock('../StatusBadge', () => ({
  default: ({ status }: { status: string }) => <div>{status}</div>,
}));

describe('AutorepeatTasks', () => {
  it('renders repository links correctly', () => {
    render(<AutorepeatTasks />);
    const link = screen.getByText('google/jules');
    expect(link).toHaveAttribute('href', 'https://github.com/google/jules');
  });

  it('renders task detail links correctly', () => {
    render(<AutorepeatTasks />);
    const link = screen.getByText(/#101/);
    expect(link).toHaveAttribute('href', '/tasks?id=1');
  });
});
