# HRL (Halo Race Leaderboard) — Agent Handoff

Orientation doc for any AI agent (Claude Code or otherwise) working in this repo. This file is intentionally short — it tells you what the project is and where to find the details. **Keep this file and everything in `docs/` up to date as work progresses**: when you make an architectural decision, resolve an open item, hit and fix a real bug, or change the plan, update the relevant doc in the same session — don't just report it in chat and let the docs go stale. See [docs/decisions.md](docs/decisions.md) for the standing format (chronological, includes reversals and incidents, not just current-state facts).

## What this is

Rebuilding the frontend and API for a public Halo racing leaderboard (HRL). Existing app: https://github.com/EffakT/HRL-Web-Interface, live at https://hrl.effakt.info. The **database is preserved** (real prod data imported into dev), the **application is reinstalled fresh** on Laravel 13 + Livewire + Tailwind. Full detail: [docs/scope.md](docs/scope.md).

## Current state (at a glance)

**Phase 1 (frontend design) is complete.** Phase 2 (backend/real data) is underway: Eloquent models exist for every real table, and **five pages are wired to real data** — Servers List (`ServerList`), Server Single (`ServerShow`: Maps, Stats Card, Latest Laps), the nested Server Map Leaderboard (`ServerMapLeaderboard`), Maps List (`MapList`), and the global Map Leaderboard (`MapLeaderboard`). Both leaderboard pages have real ranking, gaps, server/player identity, and split comparison via `LapTimeSplit::compare()`. Everything else (`PlayerList`, `PlayerShow`, `Home`) is still mock. See [docs/roadmap.md](docs/roadmap.md) for the full plan, and [docs/database.md](docs/database.md) for real data quirks the wiring surfaced: `lap_times`' timestamp precision, duplicate pivot rows, archived-server exclusion, and sparse split coverage (~4%).

## Where to find things

| Topic | Doc |
|---|---|
| What's in/out of scope, non-goals | [docs/scope.md](docs/scope.md) |
| Stack, directory layout, routing hierarchy, styleguide | [docs/architecture.md](docs/architecture.md) |
| Real schema, data model decisions, webhook plan | [docs/database.md](docs/database.md) |
| Global Player Ranking spec (planned, not built) | [docs/global-ranking.md](docs/global-ranking.md) |
| Most Active Server scoring spec (planned, not built) | [docs/most-active-server.md](docs/most-active-server.md) |
| Server Single page additions spec (built, mock data) | [docs/server-single.md](docs/server-single.md) |
| Players List → Global Leaderboard redesign spec (built, mock data) | [docs/players-list.md](docs/players-list.md) |
| Player Single page expansion spec (built, mock data) | [docs/player-single.md](docs/player-single.md) |
| Homepage redesign spec (built, mock data) | [docs/homepage.md](docs/homepage.md) |
| Secrets handling, auth status, known issues | [docs/security.md](docs/security.md) |
| Project-specific conventions (Livewire, Tailwind, Blade, mock data) | [docs/coding-standards.md](docs/coding-standards.md) |
| Planned REST endpoints | [docs/api.md](docs/api.md) |
| Livewire + Alpine patterns and gotchas (read before touching any component) | [docs/livewire-guide.md](docs/livewire-guide.md) |
| Scale, query strategy, real-time updates | [docs/performance.md](docs/performance.md) |
| Test status and plan | [docs/testing.md](docs/testing.md) |
| Environment, build process, cutover plan | [docs/deployment.md](docs/deployment.md) |
| **Chronological decision log** — the "why," including reversals and incidents | [docs/decisions.md](docs/decisions.md) |
| What's done, what's next, open questions | [docs/roadmap.md](docs/roadmap.md) |
| Domain terms (Player vs. Driver, nested vs. global leaderboard, split vs. sector, etc.) | [docs/glossary.md](docs/glossary.md) |

## Ground rules

- Nothing in this app is a like-for-like port of the old site by default — where the redesign's UX diverges (e.g. the server-scoped vs. global leaderboard split), the new design wins. Check [docs/decisions.md](docs/decisions.md) before assuming old-site behavior is the target.
- The whole frontend currently runs on mock data by deliberate sequencing choice, not oversight. Don't "fix" this unprompted — wiring real data is a planned, sequenced step (see [docs/roadmap.md](docs/roadmap.md)).
- Read [docs/livewire-guide.md](docs/livewire-guide.md) before writing or editing any Livewire component — several non-obvious framework gotchas are already solved there; don't rediscover them.
- Follow [docs/coding-standards.md](docs/coding-standards.md) for project-specific conventions not covered by the general Laravel Boost guidelines (see `CLAUDE.md`).
