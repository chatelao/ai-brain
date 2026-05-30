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
  StatusBadge: ({ status }: { status: string }) => <div>{status}</div>,
}));

describe('AutorepeatTasks', () => {
  it('renders repository links correctly', () => {
    render(<AutorepeatTasks />);
    // Should find multiple because of mobile and desktop views
    const links = screen.getAllByText('google/jules');
    expect(links.length).toBeGreaterThan(0);
    expect(links[0]).toHaveAttribute('href', 'https://github.com/google/jules');
  });

  it('renders task detail links correctly', () => {
    render(<AutorepeatTasks />);
    const links = screen.getAllByText(/#101/);
    expect(links.length).toBeGreaterThan(0);
    expect(links[0]).toHaveAttribute('href', '/tasks?id=1');
  });

  it('applies correct layout classes for responsiveness', () => {
    render(<AutorepeatTasks />);
    const table = screen.getByRole('table');
    expect(table.className).toContain('table-fixed');

    // Desktop link
    const desktopRepoLink = screen.getAllByText('google/jules').find(el => el.className.includes('break-words'));
    expect(desktopRepoLink).toBeDefined();

    // Mobile link
    const mobileRepoLink = screen.getAllByText('google/jules').find(el => el.className.includes('truncate'));
    expect(mobileRepoLink).toBeDefined();

    const issueLinks = screen.getAllByText(/#101/);
    const desktopIssueLink = issueLinks.find(el => el.className.includes('break-words'));
    expect(desktopIssueLink).toBeDefined();

    const statusHeader = screen.getByText('Status');
    expect(statusHeader.className).toContain('w-32');
  });
});
