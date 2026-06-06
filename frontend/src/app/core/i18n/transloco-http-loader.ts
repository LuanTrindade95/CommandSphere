import { Injectable } from '@angular/core';
import { Translation, TranslocoLoader } from '@jsverse/transloco';
import { Observable } from 'rxjs';
import { of } from 'rxjs';

import enTranslations from '../../../../public/i18n/en.json';
import ptBrTranslations from '../../../../public/i18n/pt-BR.json';

const TRANSLATIONS: Record<string, Translation> = {
  'pt-BR': ptBrTranslations,
  en: enTranslations,
};

@Injectable({ providedIn: 'root' })
export class TranslocoHttpLoader implements TranslocoLoader {
  getTranslation(lang: string): Observable<Translation> {
    return of(TRANSLATIONS[lang] ?? TRANSLATIONS['pt-BR']);
  }
}
