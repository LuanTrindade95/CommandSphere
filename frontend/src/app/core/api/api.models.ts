export interface ApiErrorBody {
  message: string;
  code: string;
  errors?: Record<string, string[]>;
}

export interface ApiCollection<T> {
  data: T[];
}

export interface ApiResource<T> {
  data: T;
}

export interface CommunityPermissionMap {
  'plugins.manage'?: boolean;
  'ingestion.run'?: boolean;
  'analytics.view'?: boolean;
  [permission: string]: boolean | undefined;
}

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  discord_id: string | null;
  username: string | null;
  avatar: string | null;
}

export interface AuthCommunity {
  id: number;
  name: string;
  slug: string;
  role: string;
  permissions: CommunityPermissionMap;
}

export interface AuthSessionResponse {
  token: string;
  token_type: 'Bearer';
}

export interface AuthMeResponse {
  user: AuthUser;
  communities: AuthCommunity[];
}

export interface CommandCategory {
  id: number;
  name: string;
  slug: string;
}

export interface CommandPlugin {
  id: number;
  community_id?: number;
  community?: CommunityResource | null;
  name: string;
  slug: string;
  description?: string | null;
  repository_url?: string | null;
  documentation_path?: string;
  default_branch?: string;
  versions?: CommandPluginVersion[];
}

export interface CommandPluginVersion {
  id: number;
  plugin_id?: number;
  version: string;
  git_ref?: string | null;
  changelog?: string | null;
  published_at?: string | null;
  is_latest: boolean;
}

export interface CommandResult {
  id: number;
  document_id?: number | null;
  name: string;
  slug: string;
  syntax: string;
  description: string | null;
  aliases: string[];
  parameters: unknown[];
  category: CommandCategory | null;
  plugin: CommandPlugin | null;
  plugin_version: CommandPluginVersion | null;
  views: number;
}

export interface CommunityResource {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  branding: Record<string, unknown>;
}

export interface PluginResource {
  id: number;
  community_id: number;
  community: CommunityResource | null;
  name: string;
  slug: string;
  description: string | null;
  repository_url: string | null;
  documentation_path: string;
  default_branch: string;
  versions: CommandPluginVersion[];
}

export interface DocumentResource {
  id: number;
  plugin_version_id: number;
  plugin_version: CommandPluginVersion | null;
  path: string;
  title: string;
  frontmatter: Record<string, unknown>;
  content_html: string;
  sort_order: number;
}

export interface FavoriteResource {
  id: number;
  type: 'command' | 'document' | 'unknown';
  favoritable_id: number;
  item: CommandResult | DocumentResource | null;
  created_at: string | null;
}

export interface IngestionRunResource {
  id: number;
  plugin_version_id: number;
  status: 'queued' | 'running' | 'success' | 'partial' | 'failed' | string;
  stats: Record<string, unknown> | null;
  log: Record<string, unknown> | unknown[] | null;
  started_at: string | null;
  finished_at: string | null;
}

export interface PluginCreatePayload {
  community_id: number;
  name: string;
  slug: string;
  description?: string | null;
  github_repo: string;
  docs_path: string;
  default_branch: string;
}

export interface PluginSyncResponse {
  ingestion_run: IngestionRunResource;
}

export interface SearchResponse {
  data: CommandResult[];
  facets: {
    community: Record<string, number>;
    plugin: Record<string, number>;
    category: Record<string, number>;
  };
  meta: {
    query: string;
    estimated_total_hits: number;
  };
}
