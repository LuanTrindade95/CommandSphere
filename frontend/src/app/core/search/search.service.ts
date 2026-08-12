import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';

import { SearchResponse } from '@app/core/api/api.models';
import { API_BASE_URL } from '@app/core/api/api.tokens';

export interface SearchFilters {
  community?: string;
  plugin?: string;
  category?: string;
}

@Injectable({ providedIn: 'root' })
export class SearchService {
  private readonly http = inject(HttpClient);
  private readonly apiBaseUrl = inject(API_BASE_URL);

  search(query: string, filters: SearchFilters = {}): Observable<SearchResponse> {
    let params = new HttpParams().set('q', query);

    for (const [key, value] of Object.entries(filters)) {
      if (value !== undefined && value.length > 0) {
        params = params.set(key, value);
      }
    }

    return this.http.get<SearchResponse>(`${this.apiBaseUrl}/search`, { params });
  }
}
