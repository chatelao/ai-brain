/**
 * Converts a root-relative path (relative to domain root or basePath root) to a relative path.
 * This ensures the exported static site is portable and doesn't rely on being at the domain root.
 *
 * @param targetPath The path starting with '/'
 * @param currentPathname The current pathname (excluding basePath), e.g. from usePathname()
 * @returns A relative path starting with './' or '../'
 */
export const getRelativePath = (targetPath: string, currentPathname: string): string => {
  const pathname = currentPathname || '/';

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
                   /\.php(\?|$)/.test(targetPath);

  // Calculate how many levels we are deep within the /web basePath
  const segments = pathname.split('/').filter(s => s.length > 0);
  const depth = segments.length;

  // To reach domain root from /web/..., we need to go up (depth + 1) levels
  // (depth for the path inside /web, plus 1 for /web itself)
  const upToDomainRoot = '../'.repeat(depth + 1);

  if (isLegacy) {
    const target = targetPath.startsWith('/') ? targetPath.slice(1) : targetPath;
    return upToDomainRoot + target;
  }

  // Internal Next-Gen UI paths
  const target = targetPath.startsWith('/') ? targetPath.slice(1) : targetPath;

  // If target is root '/', it means the root of /web
  if (target === '') {
    return depth > 0 ? '../'.repeat(depth) : './';
  }

  const upToWebRoot = depth > 0 ? '../'.repeat(depth) : './';
  return upToWebRoot + target + (target.includes('.') ? '' : '/');
};
