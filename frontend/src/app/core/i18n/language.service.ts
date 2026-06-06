import { Injectable, inject } from '@angular/core';
import { TranslocoService } from '@jsverse/transloco';

export type SupportedLanguage = 'pt-BR' | 'en';

@Injectable({ providedIn: 'root' })
export class LanguageService {
  private readonly transloco = inject(TranslocoService);

  readonly languages: SupportedLanguage[] = ['pt-BR', 'en'];

  activeLanguage(): SupportedLanguage {
    const activeLang = this.transloco.getActiveLang();

    return activeLang === 'en' ? 'en' : 'pt-BR';
  }

  setLanguage(language: SupportedLanguage): void {
    this.transloco.setActiveLang(language);
  }
}
