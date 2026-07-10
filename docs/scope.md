# Scope

## What this project is

A ground-up rebuild of the frontend and API for **HRL (Halo Race Leaderboard)** — a public leaderboard that any Halo dedicated server can opt into to have lap times tracked. Existing app: [EffakT/HRL-Web-Interface](https://github.com/EffakT/HRL-Web-Interface), currently live in production.

The **database and its data are being preserved** — the real production MySQL schema (`redesign_hrl`) is imported into the dev environment and is the single source of truth for what exists today. The **application itself is being reinstalled fresh** on a modern stack (see [architecture.md](architecture.md)), not incrementally upgraded.

## Product pillars

These three concepts run through the whole app, not just one page — established while planning the homepage ([homepage.md](homepage.md)), but they should inform copy, emphasis, and design decisions everywhere:

- **Map = Performance** — where skill is measured.
- **Server = Community** — where people gather and race together.
- **Player = Mastery + Consistency** — who's actually good, over time (this is precisely what [global-ranking.md](global-ranking.md) is built to measure — reward consistency and broad participation, not a single lucky lap).

## In scope

- New Laravel + Livewire frontend covering: Servers (list, single-server map list), Maps (global list, global leaderboard), server-scoped nested leaderboards, Players (list, single-player lap log with lap-detail popup), Opt-In, Contact, Home. **Done** — every page is wired to real data, see [roadmap.md](roadmap.md).
- New REST API fixing known issues in the old one (see [api.md](api.md)). **Done.**
- Real-time leaderboard updates via Laravel Reverb. **Done** (roadmap item 16).
- Webhook → job pipeline for lap submission. **Done** (roadmap item 14), including the SEC-01 HRL query verification layer added afterward — see [security.md](security.md).
- A HUD-styled design system ported from the provided static comps (see [architecture.md](architecture.md) styleguide section).

## Explicitly out of scope (for now)

- **Clans/tags** — real player names are decorated free text with no reliable structured tag convention. Regex-only extraction was tested and rejected. If revisited: a manually-curated `clans` table + admin-confirmed suggestions, never auto-assignment.
- **Claim-code account system** (`users_players`/`users_servers`) — exists in the real schema and will be preserved as-is, but no new feature work is planned around player/server ownership claiming until explicitly revisited.
- **API docs page** (`/docs` on the old site) — not being ported or inspected this round.
- **Multi-tenancy** — no `org_id`/league scoping. Can be retrofitted later without touching current tables.
- **Auth (`/login`, `/register`)** — not built yet; no scaffolding exists.

## Non-goals

- This is **not** a like-for-like port of the old Vue 2 SPA. Where the redesign's UX diverges from the old site (e.g. the server-scoped vs. global leaderboard split — see [decisions.md](decisions.md)), the new design wins.
- Not attempting an incremental Laravel 6→13 upgrade path — old app is retired wholesale after cutover.

## Current status (high-level)

Frontend is built and working end-to-end on **mock data** — every page renders, routes, and interacts correctly, but no page queries the real database yet. This was an explicit sequencing decision: get the UI right first, wire real data second. See [roadmap.md](roadmap.md) for what's next.
