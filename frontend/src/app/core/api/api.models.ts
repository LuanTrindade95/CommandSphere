export interface ApiErrorBody {
  message: string;
  code: string;
  errors?: Record<string, string[]>;
}

export interface ApiCollection<T> {
  data: T[];
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
  name: string;
  slug: string;
}

export interface CommandPluginVersion {
  id: number;
  version: string;
  is_latest: boolean;
}

export interface CommandResult {
  id: number;
  name: string;
  slug: string;
  syntax: string;
  description: string | null;
  aliases: string[];
  category: CommandCategory | null;
  plugin: CommandPlugin | null;
  plugin_version: CommandPluginVersion | null;
  views: number;
}

export interface SearchResponse {
  data: CommandResult[];
  facets: {
    plugin: Record<string, number>;
    category: Record<string, number>;
  };
  meta: {
    query: string;
    estimated_total_hits: number;
  };
}
