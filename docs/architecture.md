# Architecture

## Stack

| Layer | Choice | Why |
|---|---|---|
| Framework | **Laravel 13.8**, PHP 8.3+ (planned as "Laravel 12" originally — actual installed version is 13.8; treat 13.8 as current) | Fresh install requirement for Reverb/Livewire |
| Real-time | **Laravel Reverb** + Laravel Echo — live and wired (roadmap item 16, 2026-07-06/08) | First-party, self-hosted, no third-party dependency |
| Frontend | **Livewire** — planned as v3, actually installed **v4.3** (composer pulled v4 by default) | Server-rendered, pairs naturally with Reverb broadcast listeners, less boilerplate than Vue/Inertia here |
| Component style | **Traditional two-file** (`app/Livewire/{Name}.php` + `resources/views/livewire/.../*.blade.php`), created by hand — not Livewire v4's default single-file-component (`⚡`-prefixed) style | Main pages carry real logic (broadcast listeners, computed rankings) — worth separating for readability/testability. `artisan make:livewire` scaffolds v4 SFC style by default; don't use it as-is for these components. |
| Styling | **Tailwind CSS v4** | Matches the HUD design aesthetic (utility-driven, precise spacing/opacity) |
| Interactivity | **Alpine.js**, bundled with Livewire (no separate install) | Mobile nav overlay, modal transitions, tooltip positioning |
| Data layer (fast reads) | SQL `MIN(time)` queries; Redis sorted sets considered but not adopted (still true at real current scale — see [performance.md](performance.md)) | See [performance.md](performance.md) |
| Queue | `database` queue driver (actual, not Redis as originally planned) — moot in practice so far: `ProcessNewLap` deliberately runs synchronously in the webhook's own HTTP request rather than being queued, and `InvalidateHomeHighlightsCache` is deliberately `ShouldQueue`-free for the same reason (see [decisions.md](decisions.md)'s PERF-01 section) | Nothing in this app currently needs deferred/background processing badly enough to justify provisioning Redis |
| Testing | **Pest** (+ Pest Browser/Playwright) | See [testing.md](testing.md) for current suite size/coverage |
| Static analysis | **PHPStan (Larastan)** level 8, **Rector**, **Semgrep** (hosted against the GitHub repo, not a local install — see [testing.md](testing.md)) | Set up proactively before real backend work starts, so it's already in place rather than bolted on later |
| AI tooling | **Laravel Boost** (dev dependency) | MCP server for schema/route/Tinker access; read-only dev DB user, never prod credentials |

## Directory layout (frontend)

```
app/Livewire/
  Home.php
  Servers/ServerList.php, ServerShow.php, ServerMapLeaderboard.php, ServerPlayerShow.php
  Maps/MapList.php, MapLeaderboard.php
  Players/PlayerList.php, PlayerShow.php
  Concerns/HasLapDetailModal.php, HasRankedLeaderboardPagination.php, HasRecordVsRunnerUpReference.php  <- shared traits

resources/views/
  components/layout.blade.php           <- shared <x-layout>, nav + mobile overlay, canonical/OG/robots meta (SEO-01)
  livewire/servers/*.blade.php
  livewire/maps/*.blade.php
  livewire/players/*.blade.php
  livewire/partials/
    leaderboard-podium-and-table.blade.php, podium.blade.php
    lap-detail-modal.blade.php, lap-vs-record-modal.blade.php
    highlights/*.blade.php              <- Home's per-block highlight partials
  opt-in.blade.php, contact.blade.php, api-docs.blade.php   <- plain Blade pages (Home is now a real Livewire component, not a static welcome.blade.php)
```

## Routing hierarchy

The redesign introduces **two distinct leaderboard concepts** that don't exist as separate things on the old site:

- **Server-scoped (nested) leaderboard**: `/servers` → `/servers/{serverId}` (that server's map list) → `/servers/{serverId}/maps/{mapId}` (leaderboard for that server+map combo only).
- **Global leaderboard**: `/maps` (all maps, like Players) → `/maps/{mapId}` (leaderboard aggregated across ALL servers for that map).

```
GET  /                                    -> Livewire\Home             (home) — real community data, see [homepage.md](homepage.md)
GET  /servers                             -> Servers\ServerList        (servers.index)
GET  /servers/{serverId}                  -> Servers\ServerShow        (servers.show) — stats card, top players, all-laps table all real, see [server-single.md](server-single.md)
GET  /servers/{serverId}/maps/{mapId}     -> Servers\ServerMapLeaderboard (servers.maps.show) — nested
GET  /servers/{serverId}/players/{playerId} -> Servers\ServerPlayerShow (servers.players.show) — server-scoped player profile
GET  /maps                                -> Maps\MapList              (maps.index)
GET  /maps/{mapId}                        -> Maps\MapLeaderboard       (maps.show) — global
GET  /players                             -> Players\PlayerList        (players.index) — real Global Leaderboard, see [players-list.md](players-list.md)
GET  /players/{playerId}                  -> Players\PlayerShow        (players.show) — real lap log + Lap Detail popup, see [player-single.md](player-single.md)
GET  /opt-in, /contact                    -> plain Blade views, real content ported from old site
GET  /api-docs                            -> plain Blade view, human-readable reference for docs/api.md's real /api/v1 endpoints
GET  /robots.txt, /sitemap.xml            -> config-driven (SEO-01, see docs/decisions.md)
GET  /login, /register                    -> not built yet
```

This split is a **deliberate addition beyond the old site** (which never had a per-server map list route — `/servers/{id}` 404s there). See [decisions.md](decisions.md) for the full rationale and how it was arrived at.

Lap Detail is **never its own route** on any variant — it's a modal/popup layered over whichever leaderboard or player page opened it, matching the old site (no dedicated per-lap URL there either).

## Shared abstractions

`Servers\ServerMapLeaderboard`, `Maps\MapLeaderboard`, and `Players\PlayerShow` all render a near-identical "ranked rows + Lap Detail popup with split comparison" UI. Rather than duplicate this three times:

- **`App\Livewire\Concerns\HasLapDetailModal`** trait — `$selectedDriverIndex`, `openLap()`, `closeLap()`, `getComparisonProperty()` (mock split-comparison data). Used by all three components.
- **`resources/views/livewire/partials/leaderboard-podium-and-table.blade.php`** and **`.../lap-detail-modal.blade.php`** — shared via `@include`, used by the two leaderboard components (podium+table partial isn't used by `PlayerShow`, which has its own simpler table).

This was extracted **from the moment the second leaderboard component was created**, not as a later refactor — the duplication was obvious immediately. See [coding-standards.md](coding-standards.md) for the general rule this follows.

## Shared layout & nav

`resources/views/components/layout.blade.php` (used as `<x-layout>`) provides:
- Desktop top nav: brand mark, nav links with active-state underline, LIVE indicator, hamburger (mobile only).
- Mobile: full-screen overlay nav (Alpine `x-show`/`x-transition`, scanline background, large uppercase links, HUD-styled close button).

**Non-Livewire pages** (`welcome`, `opt-in`, `contact`, `api-docs`) wrap explicitly with `<x-layout>...</x-layout>`. **Livewire full-page components** use the `#[Layout('components.layout', [...])]` attribute instead — see [livewire-guide.md](livewire-guide.md) for why manually embedding `<x-layout>` inside a Livewire component breaks it.

## Styleguide

Source comps live in `../../redesign-files/` at the data root (sibling to the app dir): `Servers.dc.html`, `Map Leaderboard.dc.html`, `Lap Detail.dc.html`, `HRL Leaderboard.dc.html` (index/gallery), `screenshots/hub.png`. Aesthetic: dark tactical HUD.

### Breakpoints

Tailwind's default scale (`sm`/`md`/`lg`/`xl`/`2xl`) is **fully replaced** (via `--breakpoint-*: initial;` in `resources/css/app.css`, then redefined) with a custom project-specific scale. Do not use `sm:`/`md:`/`lg:`/etc. anywhere — they no longer exist as variants and will silently do nothing.

| Name | Min-width |
|---|---|
| `mm` | 375px |
| `ml` | 500px |
| `tp` | 735px |
| `tl` | 1020px |
| `d` | 1270px |
| `dlg` | 1440px |
| `dxlg` | 1800px |
| `dxxlg` | 2160px |

All existing desktop/mobile view toggles (`hidden {bp}:block` / `{bp}:hidden` table-vs-list patterns, the nav's mobile overlay breakpoint, grid column reflows) were migrated from the old `sm:`/`md:`/`lg:` usages onto `tp:` (735px) — that's the de facto "desktop vs. mobile" split point across the app. One-off narrower breakpoints exist too (e.g. `max-[550px]:`/`max-[360px]:` arbitrary variants on the Servers featured-card layout) — those are intentional fine-tuning, not part of the named scale, and don't need to map onto a named breakpoint.

### Fonts
- **Chakra Petch** (400/500/600/700) — headlines, page titles, names, labels, big rank numbers.
- **JetBrains Mono** (400/500/700/800) — all data values (times, counts, stats), nav labels, small uppercase tags/eyebrows.

### Color palette
| Token | Hex/value | Usage |
|---|---|---|
| Background base | `#040706` | Page background |
| Background glow | `#0a1a14` | Radial gradient center behind content |
| Panel/card base | `#070b0a` | Card/board backgrounds |
| Modal gradient | `--color-hud-modal-start: #0e1713` → `--color-hud-modal-end: #0a110e` | Lap Detail modal/popup panels (theme tokens, not hardcoded per-use) |
| Accent green (primary) | `#34e39b` | Brand mark, active nav, live dot, primary CTAs, record/best highlights |
| Secondary cyan | `#37d0e0` | Section eyebrows, 2nd-place podium, "now playing" map name |
| Gold | `#f2b544` | 3rd podium slot, gap values, slower-than-average split segments |
| Red | `#f2544e` | Slower split deltas, gap-to-record, destructive hover |
| Text bright/body/muted/dim | `#eaf5f0` / `#d7e4de` / `#9fb3aa` / `#6b7d75` | Hierarchy from headline to disabled state |

### Layout & shape
- **Clipped-corner cards** (`.hud-clip`/`.hud-clip-sm` utility classes, `clip-path` polygon) instead of `border-radius` — the single most recurring motif. **Caveat: `clip-path` clips descendant overflow too**, not just literal content — see the tooltip-positioning gotcha in [decisions.md](decisions.md).
- Borders: 1px solid, accent green at low alpha, brightening on hover/active/record states.
- Tables: CSS grid rows, not `<table>` — fixed column templates, transparent `border-left` that turns accent-green on hover.
- Scanline overlay (`.hud-scanlines`): CRT/HUD texture on panels.
- Glows: box-shadow with large negative spread on accent color for "record"/"featured" states.

### Recurring components
- Live indicator: pulsing dot + "LIVE" mono label.
- Stat pill badges: bordered boxes, mono uppercase text.
- Primary CTA: solid green bg, dark text, clipped corners, hover glow + lift.
- Split-pace sparkline (podium): segmented bar, width proportional to split time, green/gold color-coded, hover tooltips via Alpine Anchor (see [livewire-guide.md](livewire-guide.md)).
- Lap Detail modal/popup: clipped-corner panel, `flex flex-col max-h-[85vh]`, scrollable split-rows area with sticky column header, pinned header/footer.

### Terminology note
Design comps label people "DRIVER" in UI copy (table headers, badges) even though the data entity is named `Player`. Keep this UI-label vs. entity-name distinction — see [glossary.md](glossary.md).
