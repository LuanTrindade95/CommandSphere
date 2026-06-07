import { isPlatformServer } from '@angular/common';
import { InjectionToken, PLATFORM_ID, inject } from '@angular/core';

interface RuntimeProcess {
  env?: Record<string, string | undefined>;
}

export const API_BASE_URL = new InjectionToken<string>('API_BASE_URL', {
  factory: () => {
    const platformId = inject(PLATFORM_ID);

    if (isPlatformServer(platformId)) {
      const runtime = globalThis as typeof globalThis & { process?: RuntimeProcess };
      return runtime.process?.env?.['COMMANDSPHERE_API_INTERNAL_URL'] ?? 'http://backend:8000/api/v1';
    }

    return 'http://localhost:8000/api/v1';
  },
});
