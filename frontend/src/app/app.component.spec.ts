import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideTransloco } from '@jsverse/transloco';
import { of } from 'rxjs';

import { AppComponent } from './app.component';

class TestTranslocoLoader {
  getTranslation() {
    return of({
      app: { name: 'CommandSphere' },
      shell: {
        foundation: 'Fundação SSR',
        headline: 'Documentação inteligente para ecossistemas de plugins.',
        kicker: 'Angular SSR · Laravel API · Meilisearch',
        summary: 'Fundação validável.',
      },
    });
  }
}

describe('AppComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AppComponent],
      providers: [
        provideTransloco({
          config: {
            availableLangs: ['pt-BR', 'en'],
            defaultLang: 'pt-BR',
            fallbackLang: 'en',
            prodMode: true,
          },
          loader: TestTranslocoLoader,
        }),
        provideRouter([]),
      ],
    }).compileComponents();
  });

  it('should create the app', () => {
    const fixture = TestBed.createComponent(AppComponent);
    const app = fixture.componentInstance;
    expect(app).toBeTruthy();
  });

  it('should render the router outlet', () => {
    const fixture = TestBed.createComponent(AppComponent);
    fixture.detectChanges();
    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.querySelector('router-outlet')).not.toBeNull();
  });
});
