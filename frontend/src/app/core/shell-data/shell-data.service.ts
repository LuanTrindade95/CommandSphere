import { makeStateKey, TransferState } from '@angular/core';
import { Injectable, inject } from '@angular/core';

export interface PublicShellData {
  product: string;
  defaultLocale: 'pt-BR';
}

const PUBLIC_SHELL_DATA_KEY = makeStateKey<PublicShellData>('command-sphere-public-shell-data');

@Injectable({ providedIn: 'root' })
export class ShellDataService {
  private readonly transferState = inject(TransferState);

  readPublicData(): PublicShellData {
    const cached = this.transferState.get(PUBLIC_SHELL_DATA_KEY, null);

    if (cached !== null) {
      return cached;
    }

    const data: PublicShellData = {
      product: 'CommandSphere',
      defaultLocale: 'pt-BR',
    };

    this.transferState.set(PUBLIC_SHELL_DATA_KEY, data);

    return data;
  }
}
