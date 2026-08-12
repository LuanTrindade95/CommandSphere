# Product And Domain Context

## Product Thesis

CommandSphere is a documentation intelligence platform for plugin ecosystems. Its strongest portfolio signal is that it turns a messy real-world operational problem into a structured SaaS workflow:

- docs stay in GitHub;
- ingestion is automated and idempotent;
- commands become searchable metadata;
- communities and permissions scope access;
- maintainers get operational visibility;
- public discovery pages are SEO-ready and polished.

Avoid features that make the product look like a generic admin panel. Prefer improvements that deepen the documentation lifecycle: ingestion, discovery, quality, comparison, analytics, and operational trust.

## Users

- Community member: searches commands, reads plugin docs, favorites useful commands.
- Maintainer: manages plugin metadata, triggers or monitors ingestion, reviews warnings.
- Community admin: controls community-scoped access and sees operational/analytics surfaces.
- Public visitor/reviewer: evaluates product quality through SSR pages, screenshots, and documented architecture.

## Domain Model

Core entities:

- `User`: Discord identity, username/avatar/email, memberships.
- `Community`: multi-tenant scope for plugins and permissions.
- `Plugin`: belongs to community, points to a GitHub repository/docs path/default branch.
- `PluginVersion`: version/git ref/is latest, groups documents and commands.
- `Document`: parsed Markdown file, raw content, rendered HTML, frontmatter, title.
- `Command`: extracted structured command metadata, searchable through Meilisearch.
- `Category`: optional command grouping.
- `Favorite`: user relationship to commands/documents.
- `CommandView`: analytics event with dedupe window.
- `IngestionRun`: auditable sync attempt with status, stats, log, timestamps.

## Product Boundaries

In v1:

- GitHub is the only documentation source.
- Markdown convention is explicit; unsupported docs are still ingested as documents with warnings.
- Public read discovery is allowed.
- Mutations and operations remain authenticated and permission-scoped.
- Search is command-focused.

Out of v1 unless deliberately prioritized:

- Payments.
- Marketplace monetization.
- In-app document editing.
- Non-GitHub sources.
- Organization-wide enterprise SSO.

## UX Direction

The interface should feel like a premium developer tool, closer to Raycast, GitBook, Vercel, and Discord Developer Portal than a CRUD dashboard.

UX principles:

- Optimize for fast scanning and keyboard-driven discovery.
- Keep admin operations quiet, dense, and clear.
- Show operational state through timelines, status chips, warnings, and logs.
- Avoid decorative dashboards that do not help the user decide what to do.
- Preserve responsive layouts and SSR-safe behavior.

## Business Rules To Preserve

- A plugin slug is unique only inside its community.
- A document is unique by plugin version and path.
- A command is unique by plugin version and slug.
- Re-ingestion must update existing records instead of duplicating them.
- Commands removed from a document should be reconciled away.
- Partial ingestion is acceptable when some documents fail, but warnings must be visible.
- Webhook requests require valid HMAC signature.
- Admin actions require community-scoped permissions.
- Public data must not accidentally expose private operational information.
