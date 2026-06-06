import { TestBed } from '@angular/core/testing';

import { SearchUrlStateService } from './search-url-state.service';

describe('SearchUrlStateService', () => {
  let service: SearchUrlStateService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(SearchUrlStateService);
  });

  it('synchronizes search facets and filters with URL query params', () => {
    const state = service.read({
      q: 'blood',
      community: 'celem-ecosystem',
      plugin: 'bloodcraft',
      category: 'economy',
    });

    expect(state).toEqual({
      q: 'blood',
      community: 'celem-ecosystem',
      plugin: 'bloodcraft',
      category: 'economy',
    });
    expect(service.filters(state)).toEqual({
      community: 'celem-ecosystem',
      plugin: 'bloodcraft',
      category: 'economy',
    });
    expect(service.toQueryParams({ ...state, plugin: undefined })).toEqual({
      q: 'blood',
      community: 'celem-ecosystem',
      plugin: null,
      category: 'economy',
    });
  });
});
