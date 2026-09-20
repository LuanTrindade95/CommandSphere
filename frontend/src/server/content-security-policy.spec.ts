import { buildContentSecurityPolicy, generateCspNonce, resolveRuntimeBrowserConfig } from './content-security-policy';

describe('resolveRuntimeBrowserConfig', () => {
  it('falls back to same-origin defaults when no env vars are set', () => {
    const config = resolveRuntimeBrowserConfig({}, 'http://localhost:4200');

    expect(config).toEqual({
      apiBaseUrl: '/api/v1',
      publicOrigin: 'http://localhost:4200',
      reverb: {
        appKey: 'local-reverb-key',
        host: 'localhost',
        port: 8080,
        scheme: 'http',
      },
    });
  });

  it('reads the public runtime variables over the internal/dev ones', () => {
    const config = resolveRuntimeBrowserConfig(
      {
        COMMANDSPHERE_API_PUBLIC_URL: 'https://api.commandsphere.dev/api/v1',
        COMMANDSPHERE_REVERB_PUBLIC_HOST: 'ws.commandsphere.dev',
        COMMANDSPHERE_REVERB_PUBLIC_PORT: '443',
        COMMANDSPHERE_REVERB_PUBLIC_SCHEME: 'https',
        COMMANDSPHERE_REVERB_HOST: 'internal-should-not-be-used',
      },
      'https://commandsphere.dev',
    );

    expect(config.apiBaseUrl).toBe('https://api.commandsphere.dev/api/v1');
    expect(config.reverb).toEqual({
      appKey: 'local-reverb-key',
      host: 'ws.commandsphere.dev',
      port: 443,
      scheme: 'https',
    });
  });
});

describe('generateCspNonce', () => {
  it('generates a fresh, non-empty value on every call', () => {
    const first = generateCspNonce();
    const second = generateCspNonce();

    expect(first).not.toHaveLength(0);
    expect(first).not.toBe(second);
  });
});

describe('buildContentSecurityPolicy', () => {
  const baseConfig = {
    apiBaseUrl: '/api/v1',
    publicOrigin: 'http://localhost:4200',
    reverb: { appKey: 'k', host: 'localhost', port: 8080, scheme: 'http' as const },
  };

  it('never includes unsafe-eval or wildcard sources in script-src/connect-src', () => {
    const csp = buildContentSecurityPolicy(baseConfig, 'abc123');

    expect(csp).not.toContain('unsafe-eval');
    expect(csp).not.toMatch(/script-src[^;]*\*/);
    expect(csp).not.toMatch(/connect-src[^;]*\*/);
    expect(csp).not.toMatch(/script-src[^;]*https:/);
    expect(csp).not.toMatch(/connect-src[^;]*https:(?!\/\/)/);
  });

  it('never allows unsafe-inline in style-src', () => {
    const csp = buildContentSecurityPolicy(baseConfig, 'abc123');
    const styleSrc = /style-src ([^;]+)/.exec(csp)?.[1] ?? '';

    expect(styleSrc).not.toContain('unsafe-inline');
    expect(styleSrc).toContain("'self'");
    expect(styleSrc).toContain("'nonce-abc123'");
  });

  it('omits the API origin from connect-src when it resolves to the same origin', () => {
    const csp = buildContentSecurityPolicy(baseConfig, 'abc123');
    const connectSrc = /connect-src ([^;]+)/.exec(csp)?.[1] ?? '';

    expect(connectSrc).toBe("'self' ws://localhost:8080");
  });

  it('adds the API origin to connect-src when COMMANDSPHERE_API_PUBLIC_URL points elsewhere', () => {
    const csp = buildContentSecurityPolicy(
      { ...baseConfig, apiBaseUrl: 'http://localhost:8000/api/v1' },
      'abc123',
    );
    const connectSrc = /connect-src ([^;]+)/.exec(csp)?.[1] ?? '';

    expect(connectSrc).toContain('http://localhost:8000');
  });

  it('uses wss for the Reverb origin when the reverb scheme is https', () => {
    const csp = buildContentSecurityPolicy(
      { ...baseConfig, reverb: { ...baseConfig.reverb, scheme: 'https', host: 'reverb.example.com', port: 443 } },
      'abc123',
    );

    expect(csp).toContain('wss://reverb.example.com:443');
  });

  it('allows self, data, and https images for Markdown-sourced content', () => {
    const csp = buildContentSecurityPolicy(baseConfig, 'abc123');

    expect(csp).toContain("img-src 'self' data: https:");
  });

  it('sets frame-ancestors none and object-src none as hardening defaults', () => {
    const csp = buildContentSecurityPolicy(baseConfig, 'abc123');

    expect(csp).toContain("frame-ancestors 'none'");
    expect(csp).toContain("object-src 'none'");
  });
});
