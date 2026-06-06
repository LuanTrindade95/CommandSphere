import { ActivatedRoute, convertToParamMap, Router } from '@angular/router';
import { TestBed } from '@angular/core/testing';
import { provideTransloco } from '@jsverse/transloco';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';

import { CatalogService } from '@app/core/catalog/catalog.service';
import { FavoriteService } from '@app/core/favorites/favorite.service';

import { PluginDocsPageComponent } from './plugin-docs.page';

class TestTranslocoLoader {
  getTranslation() {
    return of({
      docs: {
        backToCatalog: 'Back',
        documentsTitle: 'Documents',
        errorDescription: 'Error',
        errorTitle: 'Error',
        indexTitle: 'Index',
        noDescription: 'No description',
        noDocumentDescription: 'No document',
        noDocumentTitle: 'No document',
        versionLabel: 'Version',
      },
      favorites: {
        add: 'Favorite',
        remove: 'Remove',
      },
    });
  }
}

describe('PluginDocsPageComponent', () => {
  it('changes the rendered document when the version selector changes content', async () => {
    await TestBed.configureTestingModule({
      imports: [PluginDocsPageComponent],
      providers: [
        provideRouter([]),
        provideTransloco({
          config: {
            availableLangs: ['pt-BR', 'en'],
            defaultLang: 'pt-BR',
            fallbackLang: 'en',
            prodMode: true,
          },
          loader: TestTranslocoLoader,
        }),
        {
          provide: CatalogService,
          useValue: {
            plugin: () => of({
              data: {
                id: 1,
                community_id: 1,
                community: null,
                name: 'Bloodcraft',
                slug: 'bloodcraft',
                description: null,
                repository_url: 'celem/bloodcraft',
                documentation_path: 'docs',
                default_branch: 'main',
                versions: [
                  { id: 1, version: '1.0.0', is_latest: false },
                  { id: 2, version: '2.0.0', is_latest: true },
                ],
              },
            }),
            versionDocuments: (_slug: string, version: string) => of({
              data: [
                {
                  id: version === '1.0.0' ? 10 : 20,
                  plugin_version_id: version === '1.0.0' ? 1 : 2,
                  plugin_version: null,
                  path: `${version}/intro.md`,
                  title: version === '1.0.0' ? 'Old doc' : 'New doc',
                  frontmatter: {},
                  content_html: `<h2>${version === '1.0.0' ? 'Old content' : 'New content'}</h2>`,
                  sort_order: 1,
                },
              ],
            }),
          },
        },
        {
          provide: FavoriteService,
          useValue: {
            load: () => of({ data: [] }),
            isFavorite: () => false,
            add: () => of({ data: null }),
            remove: () => of(undefined),
          },
        },
        {
          provide: ActivatedRoute,
          useValue: {
            paramMap: of(convertToParamMap({ community: 'celem-ecosystem', plugin: 'bloodcraft' })),
            queryParamMap: of(convertToParamMap({ version: '1.0.0' })),
          },
        },
      ],
    }).compileComponents();

    const router = TestBed.inject(Router);
    const navigate = jest.spyOn(router, 'navigate').mockResolvedValue(true);
    const fixture = TestBed.createComponent(PluginDocsPageComponent);
    fixture.detectChanges();
    expect((fixture.nativeElement as HTMLElement).textContent).toContain('Old doc');

    fixture.componentInstance.selectVersion('2.0.0');
    fixture.detectChanges();

    expect((fixture.nativeElement as HTMLElement).textContent).toContain('New doc');
    expect(navigate).toHaveBeenCalledWith([], expect.objectContaining({
      queryParams: { version: '2.0.0', doc: null },
    }));
  });
});
