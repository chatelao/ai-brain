'use client';

import { usePathname } from 'next/navigation';

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
    // 1. API paths should remain absolute root-relative as they are proxied or handled by the server root
    if (targetPath.startsWith('/api/')) {
      return targetPath;
    }

    // 2. External links or already relative links return as is
    if (targetPath.startsWith('http') || targetPath.startsWith('./') || targetPath.startsWith('../')) {
      return targetPath;
    }

    // 3. Identify if the path is intended to be outside the /web basePath (Legacy UI)
    const isLegacy = targetPath.startsWith('/github/') ||
                     targetPath.startsWith('/google/') ||
                     targetPath.startsWith('/admin/') ||
                     targetPath.startsWith('/logout.php') ||
                     targetPath.startsWith('/index.php') ||
                     targetPath.endsWith('.php');

    // Calculate how many levels we are deep within the /web basePath
    // e.g., if at /web/projects/1/, usePathname() returns /projects/1
    const segments = pathname.split('/').filter(s => s.length > 0);
    const depth = segments.length;

    // To reach domain root from /web/..., we need to go up (depth + 1) levels
    // (depth for the path inside /web, plus 1 for /web itself)
    const upToDomainRoot = '../'.repeat(depth + 1);

    if (isLegacy) {
      return upToDomainRoot + targetPath.slice(1);
    }

    // Internal Next-Gen UI paths
    // In Next.js with basePath: '/web', we usually link to '/' or '/logs'
    // and it resolves to '/web/' or '/web/logs/'.
    // Here we want to produce a truly relative link for portability.

    const target = targetPath.startsWith('/') ? targetPath.slice(1) : targetPath;

    // If target is root '/', it means the root of /web
    if (target === '') {
      // From /web/projects/1/ to /web/ -> ../../
      return depth > 0 ? '../'.repeat(depth) : './';
    }

    // From /web/projects/1/ to /web/logs/ -> ../../logs/
    const upToWebRoot = depth > 0 ? '../'.repeat(depth) : './';
    return upToWebRoot + target + (target.includes('.') ? '' : '/');
  };

  return { rel };
};
