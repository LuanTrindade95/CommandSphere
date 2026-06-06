import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';

import { ApiCollection, ApiResource, CommandResult } from '@app/core/api/api.models';
import { API_BASE_URL } from '@app/core/api/api.tokens';

export interface AnalyticsResponse extends ApiCollection<CommandResult> {
  meta: {
    period_started_at: string;
  };
}

export interface CommandViewResponse {
  data: {
    command_id: number;
    recorded: boolean;
  };
}

@Injectable({ providedIn: 'root' })
export class AnalyticsService {
  private readonly http = inject(HttpClient);
  private readonly apiBaseUrl = inject(API_BASE_URL);

  recordCommandView(slug: string): Observable<CommandViewResponse> {
    return this.http.post<CommandViewResponse>(`${this.apiBaseUrl}/commands/${slug}/view`, {});
  }

  mostViewed(community?: string, days = 30): Observable<AnalyticsResponse> {
    let params = new HttpParams().set('days', days);

    if (community !== undefined && community.length > 0) {
      params = params.set('community', community);
    }

    return this.http.get<AnalyticsResponse>(`${this.apiBaseUrl}/analytics/most-viewed`, { params });
  }
}
