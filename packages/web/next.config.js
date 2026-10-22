/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  async rewrites() {
    const publicApiUrl = process.env.NEXT_PUBLIC_API_URL;
    if (process.env.VERCEL && !publicApiUrl) {
      throw new Error('Set NEXT_PUBLIC_API_URL in Vercel to your deployed backend origin.');
    }
    const configuredApiUrl = process.env.INTERNAL_API_URL || publicApiUrl || 'http://localhost:8000';
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
