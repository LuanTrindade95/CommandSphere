import { DOCUMENT } from '@angular/common';
import { PLATFORM_ID } from '@angular/core';
import { TestBed } from '@angular/core/testing';

import { COMMANDSPHERE_RUNTIME_CONFIG, resolveRuntimeConfig } from './runtime-config';

describe('runtime config', () => {
  const previousPublicOrigin = process.env['COMMANDSPHERE_PUBLIC_ORIGIN'];

  afterEach(() => {
    delete window.__COMMANDSPHERE_CONFIG__;
    if (previousPublicOrigin === undefined) {
      delete process.env['COMMANDSPHERE_PUBLIC_ORIGIN'];
    } else {
      process.env['COMMANDSPHERE_PUBLIC_ORIGIN'] = previousPublicOrigin;
    }
  });

  it('uses browser runtime config when present', () => {
    window.__COMMANDSPHERE_CONFIG__ = {
      apiBaseUrl: 'https://api.commandsphere.test/api/v1',
      publicOrigin: 'https://commandsphere.test',
      reverb: {
        appKey: 'public-key',
        host: 'ws.commandsphere.test',
        port: 443,
        scheme: 'https',
      },
    };

    const config = resolveRuntimeConfig('browser', window.document);

    expect(config.apiBaseUrl).toBe('https://api.commandsphere.test/api/v1');
    expect(config.publicOrigin).toBe('https://commandsphere.test');
    expect(config.reverb).toEqual({
      appKey: 'public-key',
      host: 'ws.commandsphere.test',
      port: 443,
      scheme: 'https',
    });
  });

  it('falls back to same-origin api outside localhost when config is absent', () => {
    const document = {
      defaultView: {
        location: {
          origin: 'https://commandsphere.test',
        },
      },
    } as Document;

    const config = resolveRuntimeConfig('browser', document);

    expect(config.apiBaseUrl).toBe('https://commandsphere.test/api/v1');
  });

  it('uses command sphere environment variables on the server', () => {
    process.env['COMMANDSPHERE_PUBLIC_ORIGIN'] = 'https://commandsphere.example';

    const config = resolveRuntimeConfig('browser', window.document);

    expect(config.publicOrigin).toBe('https://commandsphere.example');
  });

  it('provides the injection token through Angular DI', () => {
    TestBed.configureTestingModule({
      providers: [
        { provide: PLATFORM_ID, useValue: 'browser' },
        { provide: DOCUMENT, useValue: window.document },
      ],
    });

    expect(TestBed.inject(COMMANDSPHERE_RUNTIME_CONFIG).apiBaseUrl).toContain('/api/v1');
  });
});
