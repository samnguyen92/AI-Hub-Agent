/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  env: {
    NEXT_PUBLIC_API_URL:
      process.env.NEXT_PUBLIC_API_URL || '/api/backend',
    NEXT_PUBLIC_AUTH_MODE:
      process.env.NEXT_PUBLIC_AUTH_MODE || (process.env.VERCEL ? 'strict' : 'dev'),
  },
  async rewrites() {
    const publicApiUrl = process.env.NEXT_PUBLIC_API_URL || '/api/backend';
    const configuredApiUrl = process.env.INTERNAL_API_URL || publicApiUrl || 'http://localhost:8000';
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
    ];
  },
};

module.exports = nextConfig;
