# Frontend Blade Standards

HairVision dashboard screens use Persian RTL Blade with local assets only. Do
not add React, Vue, Inertia, Livewire, Next.js, CDN CSS, CDN JavaScript, or CDN
icons for the MVP dashboard.

## Components

Reusable dashboard UI belongs in anonymous Blade components under
`resources/views/components/admin`.

Current shared components:

- `x-admin.icon`: local Lucide-style line icons.
- `x-admin.card`: standard dashboard card surface.
- `x-admin.modal`: CSS-only modal wrapper for create forms.
- `x-admin.badge`: compact status/role badges.
- `x-admin.empty-state`: reusable empty list state.
- `x-admin.validation-summary`: form validation errors inside forms/modals.
- `x-admin.table.action-menu`: three-dot row action menu.
- `x-admin.table.action-item`: link or simple form action inside row menus.

Keep components small and specific. Do not hide business rules inside Blade
components; authorization and scoping must still be enforced by policies,
middleware, Form Requests, controllers, and services.

## Icons

Use the local `x-admin.icon` component as the dashboard icon surface. Icons
should follow one Lucide-style line treatment: 24px viewbox, no fills, rounded
stroke caps/joins, and consistent `h-4 w-4` or `h-5 w-5` sizing.

Do not use Font Awesome. Do not load icon fonts or external SVG sprite sheets.
When adding a dashboard icon, add a focused case to `x-admin.icon` and reuse it
across menus, buttons, badges, and table actions.

## Tables

Management tables must:

- Place the action column first in markup so it appears at the beginning/right
  side in RTL layouts.
- Use `x-admin.table.action-menu` for row actions.
- Show only important list columns; move secondary details to show/edit pages.
- Use `x-admin.badge` for status, role, and stage values.
- Use `x-admin.empty-state` for empty filtered results.
- Preserve pagination query strings when search or filters are active.

Dangerous actions must use confirmation and must be protected by backend
authorization. Hidden or absent buttons are not a security control.

## Filters

Important lists must support backend search/filter query parameters. Do not load
all records and filter in the browser. Default ordering should show newest
records first unless a module has a clearer domain-specific order.

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
