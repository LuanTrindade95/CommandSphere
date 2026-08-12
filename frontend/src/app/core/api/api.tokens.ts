import { InjectionToken, inject } from '@angular/core';

import { COMMANDSPHERE_RUNTIME_CONFIG } from '@app/core/config/runtime-config';

export const API_BASE_URL = new InjectionToken<string>('API_BASE_URL', {
  factory: () => inject(COMMANDSPHERE_RUNTIME_CONFIG).apiBaseUrl,
});
