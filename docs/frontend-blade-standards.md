# Frontend Blade Standards

HairVision dashboard screens use Persian RTL Blade with local assets only. Do
not add React, Vue, Inertia, Livewire, Next.js, CDN CSS, CDN JavaScript, or CDN
icons for the MVP dashboard.

## Components

Reusable dashboard UI belongs in anonymous Blade components under
`resources/views/components/admin`.

Current shared components:

- `x-admin.icon`: local UIcons regular-rounded icon font surface.
- `x-admin.button`: shared dashboard button/link surface with variants, sizes,
  optional icons, and a pointer cursor.
- `x-admin.card`: standard dashboard card surface.
- `x-admin.modal`: CSS-only modal wrapper for create forms.
- `x-admin.badge`: compact status/role badges.
- `x-admin.empty-state`: reusable empty list state.
- `x-admin.validation-summary`: form validation errors inside forms/modals.
- `x-admin.list-filters`: shared search, primary filters, advanced filters,
  saved filter presets, active filter chips, and per-page controls.
- `x-admin.table.sortable-heading`: query-string sortable table heading with
  active direction icon and active color state.
- `x-admin.table.pagination-footer`: table footer pagination summary and
  RTL-aware previous/next controls, page links, and disabled one-page state.
- `x-admin.table.action-menu`: three-dot row action menu.
- `x-admin.table.action-item`: link or simple form action inside row menus.
  Form actions may pass hidden scalar fields through the component `inputs`
  prop for compact status-toggle operations.
- `x-admin.mobile-list`, `x-admin.mobile-list.card`, and
  `x-admin.mobile-list.field`: mobile/tablet card summaries for list and
  supporting detail tables.

Keep components small and specific. Do not hide business rules inside Blade
components; authorization and scoping must still be enforced by policies,
middleware, Form Requests, controllers, and services.

## Responsive Layout

HairVision uses two responsive modes:

- Mobile mode is the default for phones, tablets, and every viewport below
  `1024px`.
- Desktop mode starts at the Tailwind `lg` breakpoint, `1024px`.

Tablet devices must follow the mobile layout. Avoid adding `sm:` or `md:`
layout behavior for dashboard grids, forms, navigation, or table presentation
unless a mobile/tablet override in the shared CSS keeps it single-column below
`lg`.

The Vite CSS entry remains `resources/css/app.css`. It imports focused
responsive files from `resources/css/base`, `resources/css/layouts`, and
`resources/css/components`, while the existing theme tokens and local font/icon
definitions remain available from the main entry.

## Theme

The dashboard theme defaults to light. Dark mode is enabled only when the
browser has a persisted user choice in `localStorage` under
`hairvision.admin.theme`; system color-scheme preferences must not switch the
dashboard to dark by themselves.

Admin layout pages and admin OTP auth pages include the shared early theme
script from `resources/views/admin/partials/theme-script.blade.php` before Vite
assets. The script sets `data-admin-theme` and `color-scheme` on `<html>` to
avoid a light/dark flash before CSS loads.

Dashboard and admin-auth screens must use the shared theme surfaces:

- `admin-shell` for authenticated dashboard pages.
- `admin-auth-shell` for admin login and OTP verification pages.

When adding dashboard UI, prefer existing shared components and the theme tokens
in `resources/css/app.css`. If Tailwind utility colors are needed, keep them in
the existing supported color families so the central dark-mode overrides cover
cards, tables, forms, alerts, badges, modals, and action menus consistently.

## Icons

Use the local `x-admin.icon` component as the dashboard icon surface. Dashboard
buttons, menus, table controls, badges, and primary UI elements must use the
standard local UIcons regular-rounded pack through `fi fi-rr-*` classes.

Do not use Font Awesome. Do not add inline SVG icons, external icon fonts,
external SVG sprite sheets, or one-off custom icons for dashboard UI unless the
standard pack truly lacks a required concept and the exception is documented.
When adding a dashboard icon, add a focused semantic case to `x-admin.icon`,
map it to a standard `fi-rr-*` class, add the matching local CSS glyph mapping
when the subset needs it, and reuse it across menus, buttons, badges, and table
actions.

Search, filter, check, close, sort, and pagination icons are shared UI controls
and must stay on the standard icon pack. Plans use the `package` semantic icon
mapped to the pack's box glyph, while AI model management uses the pack's
`brain` glyph; do not reuse one icon for both menu items.

## Tables

Management tables must:

- Place the action column first in markup so it appears at the beginning/right
  side in RTL layouts.
- Use `x-admin.table.action-menu` for row actions.
- Summary tables that link to a record, such as dashboard recent rows, should
  still provide a standard action menu when the user is authorized to view the
  row.
- Show only important list columns; move secondary details to show/edit pages.
- Use `x-admin.badge` for status, role, and stage values.
- Use `x-admin.empty-state` for empty filtered results.
- Preserve pagination query strings when search or filters are active.
- Put pagination in `x-admin.table.pagination-footer` directly after the table
  container. When there is only one page, pagination controls must render dimmed
  and disabled instead of disappearing.
- Pagination controls are RTL-aware: previous uses the right chevron and next
  uses the left chevron. Do not call `$paginator->links()` directly from table
  pages.
- Use `x-admin.table.sortable-heading` for sortable columns. The active column
  must show a different text/icon color and an up/down direction icon. Do not
  add sortable links for columns that the backend `PaginationParameters`
  allow-list does not accept.
- Keep table columns focused on the highest-value scanning data. Move secondary
  values to show/edit pages or row action destinations.
- Plan definitions are a management table for users with plan-management
  permissions. Clinic users should not see plan tables; their dashboard should
  show the active plan and link to the purchase/upgrade flow instead.
- Important list tables should render a desktop table only from `lg` upward and
  a mobile/tablet `x-admin.mobile-list` card summary below `lg`.
- Mobile cards should show primary identity, the most important status/metadata
  fields, and the same permission-aware `x-admin.table.action-menu` actions.
- In RTL tables and mobile cards, action menus open from the inline end/left
  side of the trigger to avoid clipping against the right edge.
- Backend search, filters, sorting, and pagination remain unchanged; the mobile
  list is presentation only.

Dangerous actions must use confirmation and must be protected by backend
authorization. Hidden or absent buttons are not a security control.

## Filters

Important lists must support backend search/filter query parameters. Do not load
all records and filter in the browser. Default ordering should show newest
records first unless a module has a clearer domain-specific order. New table
searches should use `App\Support\Query\SearchFilter` and preserve query strings
through pagination.

Use `x-admin.list-filters` for dashboard list filters instead of hand-written
GET forms. The component provides:

- one consistent search button title and icon
- up to two primary filters in the compact top bar by default
- all remaining filters inside the advanced filter dialog
- a highlighted edit-filter button when any filter is active
- active filter chips that open the edit dialog when clicked
- an `x` action beside each active chip to remove that single filter
- a separate `x` beside the filter button to clear all advanced filters
- saved filter presets scoped by route/preset key
- date, sort, direction, and per-page controls when enabled

Controllers must still validate allowed sort columns through
`PaginationParameters::fromRequest`. Filtering in Blade is only the UI layer;
backend queries remain authoritative.

## Create Forms

Create-capable list pages should expose a colored button above the list. The
button opens `x-admin.modal`, and the modal submits to the existing Laravel POST
route with a Form Request or validated controller request.

When no AJAX flow exists, use the standard Laravel redirect flow:

1. Submit the modal form.
2. Show validation errors inside the modal when validation fails.
3. Redirect back to the refreshed list with a success flash when the form is
   submitted from a list modal.

## Sidebar

Sidebar groups should be few and meaningful. Single visible items render as
plain links. Groups with multiple visible items render as collapsible submenus
with clear parent and child active states. Menu visibility must remain
permission-aware, and route access must remain protected server-side.

The desktop sidebar is used only from `lg` upward. Below `1024px`, the dashboard
uses an off-canvas drawer with an overlay, scroll lock, Escape close behavior,
and the same permission-aware navigation partial as desktop.

The mobile header is a dedicated compact surface: menu button on the right,
centered HairVision logo, and a user avatar trigger on the left for account,
theme, and logout actions.

## Accessibility

Dashboard modals must expose `role="dialog"`, `aria-modal`, an accessible title,
keyboard-operable open/close controls, and an easy close target. Mobile drawer
open buttons must keep `aria-expanded` in sync with drawer state. Touch targets
for drawer buttons, action menus, inputs, selects, and primary form controls
should be at least `44px` high in mobile mode.
