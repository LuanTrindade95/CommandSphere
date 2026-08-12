import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';

import { ApiCollection, ApiResource, CommandResult, CommunityResource, DocumentResource, PluginCreatePayload, PluginResource, PluginSyncResponse } from '@app/core/api/api.models';
import { API_BASE_URL } from '@app/core/api/api.tokens';

@Injectable({ providedIn: 'root' })
export class CatalogService {
  private readonly http = inject(HttpClient);
  private readonly apiBaseUrl = inject(API_BASE_URL);

  communities(): Observable<ApiCollection<CommunityResource>> {
    return this.http.get<ApiCollection<CommunityResource>>(`${this.apiBaseUrl}/communities`);
  }

  community(slug: string): Observable<ApiResource<CommunityResource>> {
    return this.http.get<ApiResource<CommunityResource>>(`${this.apiBaseUrl}/communities/${slug}`);
  }

  plugins(community?: string): Observable<ApiCollection<PluginResource>> {
    const params = community !== undefined ? new HttpParams().set('community', community) : undefined;

    return this.http.get<ApiCollection<PluginResource>>(`${this.apiBaseUrl}/plugins`, { params });
  }

  plugin(slug: string, community?: string): Observable<ApiResource<PluginResource>> {
    const params = community !== undefined ? new HttpParams().set('community', community) : undefined;

    return this.http.get<ApiResource<PluginResource>>(`${this.apiBaseUrl}/plugins/${slug}`, { params });
  }

  versionDocuments(slug: string, version: string): Observable<ApiCollection<DocumentResource>> {
    return this.http.get<ApiCollection<DocumentResource>>(`${this.apiBaseUrl}/plugins/${slug}/versions/${version}/documents`);
  }

  document(id: number): Observable<ApiResource<DocumentResource>> {
    return this.http.get<ApiResource<DocumentResource>>(`${this.apiBaseUrl}/documents/${id}`);
  }

  command(slug: string): Observable<ApiResource<CommandResult>> {
    return this.http.get<ApiResource<CommandResult>>(`${this.apiBaseUrl}/commands/${slug}`);
  }

  createPlugin(payload: PluginCreatePayload): Observable<ApiResource<PluginResource>> {
    return this.http.post<ApiResource<PluginResource>>(`${this.apiBaseUrl}/plugins`, payload);
  }

  syncPlugin(pluginId: number): Observable<PluginSyncResponse> {
    return this.http.post<PluginSyncResponse>(`${this.apiBaseUrl}/plugins/${pluginId}/sync`, {});
  }
}
