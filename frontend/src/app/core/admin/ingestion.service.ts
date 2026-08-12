import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';

import { ApiCollection, ApiResource, IngestionRunResource } from '@app/core/api/api.models';
import { API_BASE_URL } from '@app/core/api/api.tokens';

@Injectable({ providedIn: 'root' })
export class IngestionService {
  private readonly http = inject(HttpClient);
  private readonly apiBaseUrl = inject(API_BASE_URL);

  runs(): Observable<ApiCollection<IngestionRunResource>> {
    return this.http.get<ApiCollection<IngestionRunResource>>(`${this.apiBaseUrl}/ingestions`);
  }

  run(id: number): Observable<ApiResource<IngestionRunResource>> {
    return this.http.get<ApiResource<IngestionRunResource>>(`${this.apiBaseUrl}/ingestions/${id}`);
  }
}
