import { usePathname } from 'next/navigation';

/**
 * Hook to provide relative path resolution.
 * Useful for making the application base-path agnostic.
 */
export const useRelativePath = () => {
  const pathname = usePathname();

  /**
   * Returns the relative prefix to reach the application root (e.g., './' or '../../').
   */
  const getAppRootPrefix = () => {
    if (!pathname) return './';
    const segments = pathname.split('/').filter(Boolean);
    const depth = segments.length;
    if (depth === 0) return './';
    return '../'.repeat(depth);
  };

  const appRoot = getAppRootPrefix();

  // The legacy UI is at the domain root.
  // Since the Next-gen UI is served at /web/, we need to go up one more level from the app root.
  const legacyPrefix = '../' + appRoot;

  return {
    /**
     * Resolves an internal app path to a relative one.
     * @param path The path starting from the app root (e.g., 'logs' or '/logs').
     */
    toApp: (path: string) => {
      const p = path.startsWith('/') ? path.slice(1) : path;
      if (!p) return appRoot;
      // Ensure trailing slash for consistency with next.config.ts trailingSlash: true
      return `${appRoot}${p}${p.endsWith('/') ? '' : '/'}`;
    },
    /**
     * Resolves a path to the legacy UI (domain root).
     * @param path The path starting from the domain root (e.g., 'github/login.php').
     */
    toLegacy: (path: string) => {
      const p = path.startsWith('/') ? path.slice(1) : path;
      return `${legacyPrefix}${p}`;
    },
    appRoot,
    legacyPrefix,
  };
};

/**
 * Vanilla JS version of relative path resolution for non-component files.
 * @param currentPathname window.location.pathname (includes basePath)
 * @returns prefix to domain root
 */
export const getRelativePrefixToDomainRoot = (currentPathname: string) => {
  const segments = currentPathname.split('/').filter(Boolean);
  const depth = segments.length;
  if (depth === 0) return './';
  return '../'.repeat(depth);
};
