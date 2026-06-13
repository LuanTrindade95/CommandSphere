import { DOCUMENT, isPlatformServer } from '@angular/common';
import { InjectionToken, PLATFORM_ID, inject } from '@angular/core';

interface RuntimeProcess {
  env?: Record<string, string | undefined>;
}

export interface CommandSphereRuntimeConfig {
  apiBaseUrl: string;
  publicOrigin: string;
  reverb: {
    appKey: string;
    host: string;
    port: number;
    scheme: 'http' | 'https';
  };
}

declare global {
  interface Window {
    __COMMANDSPHERE_CONFIG__?: Partial<CommandSphereRuntimeConfig>;
  }
}

export const COMMANDSPHERE_RUNTIME_CONFIG = new InjectionToken<CommandSphereRuntimeConfig>('COMMANDSPHERE_RUNTIME_CONFIG', {
  factory: () => resolveRuntimeConfig(inject(PLATFORM_ID), inject(DOCUMENT)),
});

export function resolveRuntimeConfig(platformId: object | string, document: Document): CommandSphereRuntimeConfig {
  const runtime = globalThis as typeof globalThis & { process?: RuntimeProcess };
  const env = runtime.process?.env;

  if (env !== undefined && (isPlatformServer(platformId as object) || hasCommandSphereEnv(env))) {
    const publicOrigin = env['COMMANDSPHERE_PUBLIC_ORIGIN'] ?? 'http://localhost:4200';

    return normalizeConfig({
      apiBaseUrl: env['COMMANDSPHERE_API_INTERNAL_URL'] ?? env['COMMANDSPHERE_API_PUBLIC_URL'] ?? 'http://backend:8000/api/v1',
      publicOrigin,
      reverb: {
        appKey: env['COMMANDSPHERE_REVERB_APP_KEY'] ?? 'local-reverb-key',
        host: env['COMMANDSPHERE_REVERB_HOST'] ?? 'localhost',
        port: Number(env['COMMANDSPHERE_REVERB_PORT'] ?? 8080),
        scheme: scheme(env['COMMANDSPHERE_REVERB_SCHEME']),
      },
    }, publicOrigin);
  }

  const win = document.defaultView;
  const publicOrigin = win?.location.origin ?? 'http://localhost:4200';

  return normalizeConfig(win?.__COMMANDSPHERE_CONFIG__ ?? {}, publicOrigin);
}

function hasCommandSphereEnv(env: Record<string, string | undefined>): boolean {
  return env['COMMANDSPHERE_API_INTERNAL_URL'] !== undefined
    || env['COMMANDSPHERE_API_PUBLIC_URL'] !== undefined
    || env['COMMANDSPHERE_PUBLIC_ORIGIN'] !== undefined;
}

function normalizeConfig(config: Partial<CommandSphereRuntimeConfig>, publicOrigin: string): CommandSphereRuntimeConfig {
  const reverb: Partial<CommandSphereRuntimeConfig['reverb']> = config.reverb ?? {};

  return {
    apiBaseUrl: config.apiBaseUrl ?? defaultBrowserApiBaseUrl(publicOrigin),
    publicOrigin: config.publicOrigin ?? publicOrigin,
    reverb: {
      appKey: reverb.appKey ?? 'local-reverb-key',
      host: reverb.host ?? new URL(publicOrigin).hostname,
      port: Number.isFinite(reverb.port) ? Number(reverb.port) : 8080,
      scheme: scheme(reverb.scheme),
    },
  };
}

function defaultBrowserApiBaseUrl(publicOrigin: string): string {
  const url = new URL(publicOrigin);

  if (['localhost', '127.0.0.1'].includes(url.hostname)) {
    return 'http://localhost:8000/api/v1';
  }

  return new URL('/api/v1', url).toString();
}

function scheme(value: string | undefined): 'http' | 'https' {
  return value === 'https' ? 'https' : 'http';
}
