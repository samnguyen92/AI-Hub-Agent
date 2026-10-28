/** @type {import('next').NextConfig} */
const publicApiUrl = process.env.VERCEL
  ? '/api/backend'
  : process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

const nextConfig = {
  reactStrictMode: true,
  env: {
    NEXT_PUBLIC_API_URL: publicApiUrl,
    NEXT_PUBLIC_AUTH_MODE:
      process.env.NEXT_PUBLIC_AUTH_MODE || (process.env.VERCEL ? 'strict' : 'dev'),
  },
  async rewrites() {
    const configuredApiUrl = process.env.INTERNAL_API_URL || publicApiUrl;
    if (configuredApiUrl.startsWith('/')) return [];
    const parsedApiUrl = new URL(configuredApiUrl);
    if (!['http:', 'https:'].includes(parsedApiUrl.protocol) || parsedApiUrl.pathname !== '/') {
      throw new Error('Backend URL must be an http(s) origin without a path.');
    }
    const apiHost = parsedApiUrl.origin;
    return [
      {
        source: '/api/backend/:path*',
        destination: `${apiHost}/:path*`,
      },
      {
        source: '/projects/:projectId/preview/:token/:path*',
        destination: `${apiHost}/projects/:projectId/preview/:token/:path*`,
      },
      {
        source: '/projects/:projectId/files/:path*',
        destination: `${apiHost}/projects/:projectId/files/:path*`,
      },
      {
        source: '/projects/:projectId/files',
        destination: `${apiHost}/projects/:projectId/files`,
      },
    ];
  },
};

module.exports = nextConfig;
