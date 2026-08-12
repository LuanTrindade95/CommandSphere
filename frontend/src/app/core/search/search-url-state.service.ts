import { Injectable } from '@angular/core';
import { Params } from '@angular/router';

import { SearchFilters } from './search.service';

export interface SearchUrlState extends SearchFilters {
  q: string;
}

@Injectable({ providedIn: 'root' })
export class SearchUrlStateService {
  read(params: Params): SearchUrlState {
    return {
      q: this.value(params['q']),
      community: this.optionalValue(params['community']),
      plugin: this.optionalValue(params['plugin']),
      category: this.optionalValue(params['category']),
    };
  }

  toQueryParams(state: SearchUrlState): Params {
    return {
      q: state.q || null,
      community: state.community || null,
      plugin: state.plugin || null,
      category: state.category || null,
    };
  }

  filters(state: SearchUrlState): SearchFilters {
    return {
      community: state.community,
      plugin: state.plugin,
      category: state.category,
    };
  }

  private value(value: unknown): string {
    return typeof value === 'string' ? value : '';
  }

  private optionalValue(value: unknown): string | undefined {
    const resolved = this.value(value);

    return resolved.length > 0 ? resolved : undefined;
  }
}
