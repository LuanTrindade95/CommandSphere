import { DOCUMENT, isPlatformBrowser } from '@angular/common';
import { inject, Injectable, NgZone, PLATFORM_ID } from '@angular/core';
import Echo, { Broadcaster } from 'laravel-echo';
import Pusher from 'pusher-js';

import { AuthService } from '@app/core/auth/auth.service';
import { COMMANDSPHERE_RUNTIME_CONFIG } from '@app/core/config/runtime-config';

type ReverbEcho = Echo<'reverb'>;
type ReverbPrivateChannel = Broadcaster['reverb']['private'];

export interface RealtimeSubscription {
  stop(): void;
}

@Injectable({ providedIn: 'root' })
export class RealtimeService {
  private readonly auth = inject(AuthService);
  private readonly runtimeConfig = inject(COMMANDSPHERE_RUNTIME_CONFIG);
  private readonly document = inject(DOCUMENT);
  private readonly platformId = inject(PLATFORM_ID);
  private readonly zone = inject(NgZone);
  private echo: ReverbEcho | null = null;

  listenPrivate<TPayload>(
    channelName: string,
    eventName: string,
    handler: (payload: TPayload) => void,
  ): RealtimeSubscription {
    const echo = this.ensureEcho();

    if (echo === null) {
      return { stop: () => undefined };
    }

    const channel = echo.private(channelName) as ReverbPrivateChannel;
    channel.listen(`.${eventName}`, (payload: TPayload) => {
      this.zone.run(() => handler(payload));
    });

    return {
      stop: () => {
        channel.stopListening(`.${eventName}`);
        echo.leave(channelName);
      },
    };
  }

  disconnect(): void {
    this.echo?.disconnect();
    this.echo = null;
  }

  private ensureEcho(): ReverbEcho | null {
    if (!isPlatformBrowser(this.platformId) || this.auth.token() === null) {
      return null;
    }

    if (this.echo !== null) {
      return this.echo;
    }

    const win = this.document.defaultView;

    if (win === null) {
      return null;
    }

    const apiUrl = new URL(this.runtimeConfig.apiBaseUrl, this.runtimeConfig.publicOrigin);
    const { reverb } = this.runtimeConfig;

    this.echo = new Echo({
      broadcaster: 'reverb',
      key: reverb.appKey,
      wsHost: reverb.host || win.location.hostname,
      wsPort: reverb.port,
      wssPort: reverb.port,
      forceTLS: reverb.scheme === 'https',
      enabledTransports: reverb.scheme === 'https' ? ['wss'] : ['ws'],
      authEndpoint: `${apiUrl.origin}/api/broadcasting/auth`,
      auth: {
        headers: {
          Authorization: `Bearer ${this.auth.token()}`,
          Accept: 'application/json',
        },
      },
      Pusher,
    });

    return this.echo;
  }
}
