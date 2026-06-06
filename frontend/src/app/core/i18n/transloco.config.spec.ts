import { provideHttpClient } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { TranslocoService, provideTransloco } from '@jsverse/transloco';
import { firstValueFrom, of } from 'rxjs';

import { TranslocoHttpLoader } from './transloco-http-loader';

class TestTranslocoLoader {
  getTranslation(lang: string) {
    const translations = {
      'pt-BR': { app: { name: 'CommandSphere' }, shell: { foundation: 'Fundação SSR' } },
      en: { app: { name: 'CommandSphere' }, shell: { foundation: 'SSR Foundation' } },
    };

    return of(translations[lang as keyof typeof translations]);
  }
}

describe('Transloco configuration', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideTransloco({
          config: {
            availableLangs: ['pt-BR', 'en'],
            defaultLang: 'pt-BR',
            fallbackLang: 'en',
            prodMode: true,
          },
          loader: TestTranslocoLoader,
        }),
        TranslocoHttpLoader,
      ],
    });
  });

  it('resolves pt-BR and en keys', async () => {
    const transloco = TestBed.inject(TranslocoService);

    transloco.setActiveLang('pt-BR');
    await firstValueFrom(transloco.load('pt-BR'));
    expect(transloco.translate('shell.foundation')).toBe('Fundação SSR');

    transloco.setActiveLang('en');
    await firstValueFrom(transloco.load('en'));
    expect(transloco.translate('shell.foundation')).toBe('SSR Foundation');
  });
});
