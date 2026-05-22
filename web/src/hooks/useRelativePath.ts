'use client';

import { usePathname } from 'next/navigation';
import { getRelativePath } from '@/utils/paths';

/**
 * Hook to convert root-relative paths to relative paths based on current URL depth.
 * This ensures the exported static site is portable and doesn't rely on being at the domain root.
 */
export const useRelativePath = () => {
  const pathname = usePathname() || '/';

  /**
   * Converts a root-relative path (relative to domain root or basePath root) to a relative path.
   *
   * @param targetPath The path starting with '/'
   * @returns A relative path starting with './' or '../'
   */
  const rel = (targetPath: string): string => {
    return getRelativePath(targetPath, pathname);
  };

  return { rel };
};
