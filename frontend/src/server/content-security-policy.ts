import { randomBytes } from 'node:crypto';

/**
 * Runtime browser configuration resolved from environment variables.
 *
 * This mirrors the shape consumed by the client at
 * `window.__COMMANDSPHERE_CONFIG__` (see `server.ts#runtimeConfigScript`) so
 * that the Content-Security-Policy header is derived from the exact same
 * source of truth as the runtime config script, never from a hardcoded host.
 */
export interface RuntimeBrowserConfig {
  apiBaseUrl: string;
  publicOrigin: string;
  reverb: {
    appKey: string;
    host: string;
    port: number;
    scheme: 'http' | 'https';
  };
}

/**
 * Reads the same environment variables used to build `runtime-config.js` and
 * returns the resolved runtime configuration. Both the runtime config script
 * and the Content-Security-Policy header are derived from this single
 * function so that changing `COMMANDSPHERE_API_PUBLIC_URL` or the Reverb
 * public variables changes both consistently.
 */
export function resolveRuntimeBrowserConfig(env: NodeJS.ProcessEnv, publicOrigin: string): RuntimeBrowserConfig {
  return {
    apiBaseUrl: env['COMMANDSPHERE_API_PUBLIC_URL'] ?? '/api/v1',
    publicOrigin,
    reverb: {
      appKey: env['COMMANDSPHERE_REVERB_APP_KEY'] ?? 'local-reverb-key',
      host: env['COMMANDSPHERE_REVERB_PUBLIC_HOST'] ?? env['COMMANDSPHERE_REVERB_HOST'] ?? 'localhost',
      port: Number(env['COMMANDSPHERE_REVERB_PUBLIC_PORT'] ?? env['COMMANDSPHERE_REVERB_PORT'] ?? 8080),
      scheme: (env['COMMANDSPHERE_REVERB_PUBLIC_SCHEME'] ?? env['COMMANDSPHERE_REVERB_SCHEME'] ?? 'http') === 'https' ? 'https' : 'http',
    },
  };
}

/**
 * Generates a cryptographically random, per-response nonce for `style-src`.
 * A fresh nonce must be generated for every request; responses carrying a
 * nonce must never be cached (see `Cache-Control: no-store` in `server.ts`).
 */
export function generateCspNonce(): string {
  return randomBytes(16).toString('base64');
}

/**
 * Builds the `Content-Security-Policy` header for SSR-rendered HTML.
 *
 * Directive-by-directive rationale (see the inventory in the delivery
 * package for the full source-by-source breakdown):
 * - `script-src 'self'`: only same-origin bundles and `/runtime-config.js`
 *   execute; the JSON transfer-state/JSON-LD `<script>` tags are not
 *   JavaScript and are unaffected by `script-src`.
 * - `style-src 'self' 'nonce-...'`: the external stylesheet is same-origin;
 *   the nonce covers `<style>` tags Angular injects at runtime for
 *   lazy-loaded route styles (critical CSS inlining is disabled in
 *   `angular.json` so no inline `<style>` needs `unsafe-inline`).
 * - `img-src 'self' data: https:`: covers local assets plus external images
 *   referenced by ingested Markdown documents (owner decision).
 * - `connect-src`: always same-origin plus the Reverb WebSocket origin, and
 *   the API origin only when `COMMANDSPHERE_API_PUBLIC_URL` resolves to a
 *   different origin than the page itself.
 * - `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`,
 *   `frame-ancestors 'none'`: standard hardening; `frame-ancestors` mirrors
 *   the existing `X-Frame-Options: DENY`.
 */
export function buildContentSecurityPolicy(config: RuntimeBrowserConfig, nonce: string): string {
  const connectSrc = ["'self'", reverbConnectOrigin(config.reverb)];
  const apiOrigin = resolveForeignOrigin(config.apiBaseUrl, config.publicOrigin);

  if (apiOrigin !== null) {
    connectSrc.push(apiOrigin);
  }

  const directives: [string, string[]][] = [
    ['default-src', ["'self'"]],
    ['script-src', ["'self'"]],
    ['style-src', ["'self'", `'nonce-${nonce}'`]],
    ['img-src', ["'self'", 'data:', 'https:']],
    ['font-src', ["'self'"]],
    ['connect-src', connectSrc],
    ['object-src', ["'none'"]],
    ['base-uri', ["'self'"]],
    ['form-action', ["'self'"]],
    ['frame-ancestors', ["'none'"]],
  ];

  return directives.map(([directive, sources]) => `${directive} ${sources.join(' ')}`).join('; ');
}

function reverbConnectOrigin(reverb: RuntimeBrowserConfig['reverb']): string {
  const wsScheme = reverb.scheme === 'https' ? 'wss' : 'ws';

  return `${wsScheme}://${reverb.host}:${reverb.port}`;
}

/**
 * Returns the origin of `value` when it resolves to a different origin than
 * `publicOrigin`, or `null` when it is same-origin (including relative
 * paths such as the default `/api/v1`), meaning `'self'` already covers it.
 */
function resolveForeignOrigin(value: string, publicOrigin: string): string | null {
  try {
    const resolved = new URL(value, publicOrigin);
    const self = new URL(publicOrigin);

    return resolved.origin === self.origin ? null : resolved.origin;
  } catch {
    return null;
  }
}
