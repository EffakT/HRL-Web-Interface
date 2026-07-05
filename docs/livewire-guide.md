# Livewire Guide

Project-specific Livewire + Alpine patterns and gotchas discovered while building this app. Read this before touching any Livewire component.

## Version note

Planned as Livewire v3; composer actually installed **v4.3**. v4 defaults to single-file components (`⚡`-prefixed filenames under `resources/views/components/`). This project deliberately uses the **traditional two-file format** instead — see [coding-standards.md](coding-standards.md). `artisan make:livewire` scaffolds the v4 style; don't use its output as-is.

## Full-page components must have exactly one root element, no `<!DOCTYPE html>`

Livewire full-page components (registered via `Route::get('/x', Component::class)`) are automatically wrapped in a layout by Livewire itself. **Do not** manually embed `<x-layout>` (a full `<!DOCTYPE html>...</html>` document) inside the component's own Blade view — Livewire's "multiple root elements" detector will reject it (the `<!DOCTYPE>` + `<html>` count as two roots even from a single custom component tag).

Correct pattern:
```php
#[Layout('components.layout', ['title' => 'Servers', 'active' => 'servers'])]
class ServerList extends Component { ... }
```
The component's own Blade view renders **only its inner content**, wrapped in a single root `<div>`. Livewire injects that into `components.layout`'s `{{ $slot }}` automatically via `config('livewire.component_layout')` (default: `components.layout`).

Plain (non-Livewire) Blade pages (`welcome`, `opt-in`, `contact`) don't have this restriction — they wrap explicitly with `<x-layout>...</x-layout>`.

## Never collide a route param name with an unrelated public property

```php
// BAD — {map} route segment silently overwrites public string $map before mount() runs
Route::get('/maps/{map}', MapLeaderboard::class);
class MapLeaderboard extends Component {
    public string $map = 'Coldsnap Rally'; // gets clobbered with the raw route id
}
```
Livewire auto-hydrates public properties from route parameters by name. Rename the route segment (e.g. `{mapId}`) instead.

## Alpine transitions on Livewire-toggled elements need a stable `wire:key`

If a modal/popup's visibility is driven by a Livewire property (e.g. `$selectedDriverIndex !== null`) via Alpine `x-show`, the element **must** have a stable `wire:key` that does not change per-render:

```blade
<div wire:key="lap-detail-modal"          {{-- NOT wire:key="lap-detail-{{ $index }}" --}}
     x-show="$wire.selectedDriverIndex !== null"
     x-cloak
     x-transition:enter="transition ease-out duration-200"
     ...>
```
Without a stable key, Livewire's DOM morph recreates the node on every click instead of patching it in place — Alpine then sees a "new" element already in its final visible state, with nothing to animate from. The transition silently does nothing (or worse, shows only a fade if a nested element's transition happens to survive by coincidence).

## `$wire.property` is reactive in Alpine expressions

`x-show="$wire.selectedDriverIndex !== null"` works directly — no `$wire.entangle()` needed for simple reactive reads. This is core Livewire+Alpine integration behavior.

## Registering Alpine plugins on top of Livewire's bundled Alpine

This project has no standalone `import Alpine from 'alpinejs'; Alpine.start()` — Livewire bundles and starts its own Alpine instance via `@livewireScripts`. To add an Alpine plugin (e.g. `@alpinejs/anchor`), register it via the `alpine:init` event in `resources/js/app.js`:

```js
import anchor from '@alpinejs/anchor';

document.addEventListener('alpine:init', () => {
    window.Alpine.plugin(anchor);
});
```

## Use Alpine's Anchor plugin for dynamic positioning, not hand-rolled edge cases

For tooltips/popovers that might overflow a clipped or bounded container (see the `.hud-clip` caveat in [coding-standards.md](coding-standards.md)), use `@alpinejs/anchor` (wraps Floating UI) rather than manually special-casing first/last items:

```blade
<div x-anchor.top.offset.6="$el.parentElement" class="pointer-events-none hidden ... group-hover:block">
    tooltip content
</div>
```
- Reference the trigger directly (`$el.parentElement`, or wherever the hover target is) — no `x-ref` bookkeeping needed for simple one-trigger-per-tooltip loops.
- Remove manual `absolute`/positioning utility classes — the plugin sets `position`/`top`/`left` via inline styles itself.
- Keep `hidden` + `group-hover:block` (or another visibility toggle) — the plugin only handles positioning, not show/hide.

## Scrollable content inside a height-capped modal

For modals that might contain a variable, potentially large number of rows (e.g. up to 14 split-comparison rows), cap the panel height and make only the rows area scroll, keeping header/footer pinned:

```blade
<div class="flex max-h-[85vh] flex-col ...">           {{-- panel: flex column, capped height --}}
    <div class="flex-none ...">header content</div>     {{-- pinned --}}
    <div class="min-h-0 flex-1 overflow-y-auto">         {{-- only this scrolls --}}
        <div class="sticky top-0 z-10 ...">column header</div>
        @foreach (...) ... @endforeach
    </div>
    <div class="flex-none ...">footer/buttons</div>      {{-- pinned --}}
</div>
```
`min-h-0` on the flex-1 scroll container is required — without it, flexbox won't let the container shrink below its content's natural height, and `overflow-y-auto` never kicks in.

## The `cache` table dependency

Every `wire:click` action goes through Livewire's checksum/rate-limiter, which reads the configured cache store. If `CACHE_STORE=database` and the `cache`/`cache_locks` tables don't exist, **every single Livewire interaction fatal-errors**, not just cache-related ones. See [decisions.md](decisions.md) for the incident and fix. If a fresh clone/deploy of this app throws a fatal error the moment you click anything interactive, check this first.
