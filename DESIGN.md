# SelectDB Design System

## 1. Atmosphere & Identity

SelectDB is a focused academic administration dashboard built on the existing AdminLTE 4 component system in `../dist`. It should feel familiar, efficient, and dependable. The signature is a single-page grade directory where the data table remains visible while add-data work happens in a modal.

## 2. Color

### Palette

| Role | Token | Light | Dark | Usage |
|---|---|---|---|---|
| Surface/primary | `--bs-body-bg` | AdminLTE default | AdminLTE default | Page background |
| Surface/secondary | `--bs-secondary-bg` | AdminLTE default | AdminLTE default | Sidebar and grouped surfaces |
| Surface/elevated | `--bs-body-bg` | AdminLTE default | AdminLTE default | Cards and modal |
| Text/primary | `--bs-body-color` | AdminLTE default | AdminLTE default | Main copy |
| Text/secondary | `--bs-secondary-color` | AdminLTE default | AdminLTE default | Metadata and hints |
| Border/default | `--bs-border-color` | AdminLTE default | AdminLTE default | Dividers and controls |
| Accent/primary | `--bs-primary` | AdminLTE blue | AdminLTE blue | Primary action and focus |
| Status/success | `--bs-success` | Bootstrap green | Bootstrap green | Saved confirmation |
| Status/error | `--bs-danger` | Bootstrap red | Bootstrap red | Validation errors |

### Rules

- AdminLTE and Bootstrap semantic tokens are the source of truth. No page-specific palette is introduced.
- Accent is reserved for the primary add action, links, and keyboard focus.
- Status is always paired with text or an icon, never communicated by color alone.

## 3. Typography

### Scale

| Level | Size | Weight | Line Height | Usage |
|---|---|---|---|---|
| H1 | `2rem` | 600 | 1.2 | Page title |
| H2 | `1.25rem` | 600 | 1.3 | Modal title |
| Body | `1rem` | 400 | 1.5 | Default text |
| Body/sm | `0.875rem` | 400 | 1.5 | Table metadata and hints |
| Caption | `0.75rem` | 500 | 1.4 | Compact labels |

### Font Stack

- Primary: Source Sans 3, system-ui, sans-serif.
- Mono: not used.

### Rules

- Body text stays at least 14px on desktop and 16px for form controls on mobile.
- Labels remain visible above inputs. Placeholders only provide examples.

## 4. Spacing & Layout

### Base Unit

All spacing derives from a 4px base and uses Bootstrap/AdminLTE spacing utilities.

| Token | Value | Usage |
|---|---|---|
| `--space-1` | 4px | Tight icon gap |
| `--space-2` | 8px | Compact controls |
| `--space-3` | 12px | Field rhythm |
| `--space-4` | 16px | Standard padding |
| `--space-6` | 24px | Card and modal sections |
| `--space-8` | 32px | Page-level grouping |

### Grid

- Shell: AdminLTE fixed sidebar shell.
- Main content: fluid container with one primary card.
- Breakpoint behavior: sidebar collapses below AdminLTE's large breakpoint; filters stack below 768px.
- Scroll owner: the document owns vertical scrolling; the table wrapper alone may scroll horizontally on narrow screens.

## 5. Components

### App Shell

- **Structure**: AdminLTE header, fixed sidebar, main, footer.
- **Variants**: expanded desktop and collapsed mobile sidebar.
- **States**: current navigation item uses AdminLTE active wash and icon.
- **Accessibility**: landmarks, labelled sidebar toggle, skip link supplied by AdminLTE.
- **Motion**: AdminLTE sidebar transition; reduced-motion override removes positional transitions.
- **Layout**: fixed-sidenav-shell; document is the vertical scroll owner.

### Directory Card

- **Structure**: title and count, search/filter form, responsive table, pagination footer.
- **Variants**: populated, empty, database error.
- **States**: controls provide default, hover, active, focus, and disabled states through Bootstrap.
- **Accessibility**: table caption, scoped headers, labelled filters, status text for results.
- **Motion**: none.
- **Layout**: stack plus cluster; table wrapper owns horizontal overflow only.

### Grade Form Modal

- **Structure**: modal header, inline error summary, labelled fields, cancel and save actions.
- **Variants**: create, edit, validation error, submitting.
- **States**: invalid fields use `aria-invalid` and inline messages; focus returns to the trigger after close.
- **Accessibility**: native Bootstrap dialog semantics, Escape closes, visible labels, numeric input mode.
- **Motion**: Bootstrap modal fade; reduced-motion becomes effectively instant.
- **Layout**: centered dialog on desktop, near-full-width sheet on mobile.

### Row Actions

- **Structure**: compact Edit and Delete icon buttons grouped in the final table column.
- **Variants**: primary-outline edit and danger-outline delete, using Bootstrap semantic tokens.
- **States**: default, hover, focus-visible, active, and disabled are inherited from Bootstrap.
- **Accessibility**: every icon-only control has a record-specific accessible label and a minimum 44px target.
- **Motion**: color feedback only; no row movement or decorative animation.
- **Layout**: actions stay grouped on one line while the table wrapper owns narrow-screen overflow.

### Delete Confirmation Modal

- **Structure**: title, record-specific consequence copy, cancel action, and explicit destructive action.
- **States**: idle and submitting; the destructive button disables after submission.
- **Accessibility**: native Bootstrap dialog semantics, named description, Escape closes, focus returns to the trigger.
- **Motion**: Bootstrap modal fade; reduced-motion becomes effectively instant.

### Feedback Alert

- **Structure**: semantic alert with icon, message, and dismiss control where appropriate.
- **Variants**: success and error.
- **Accessibility**: `role="status"` for success and `role="alert"` for errors.
- **Motion**: none.

Primitive states are exercised by AdminLTE's existing component examples in `../dist/UI/general.html`, `../dist/forms/elements.html`, and `../dist/tables/simple.html`; this project composes those existing primitives without creating a competing component library.

## 6. Motion & Interaction

| Type | Duration | Easing | Usage |
|---|---|---|---|
| Micro | AdminLTE default | ease-out | Button and focus feedback |
| Standard | AdminLTE default | ease-in-out | Sidebar and modal |

- Motion only communicates navigation or state change.
- `prefers-reduced-motion: reduce` disables smooth scrolling and makes transitions immediate.
- Save actions disable the submit button on submission to prevent duplicate records.

## 7. Depth & Surface

### Strategy

Mixed, inherited from AdminLTE: borders define cards and fields; restrained shadows communicate elevated modal and sidebar surfaces.

### Radius

Use AdminLTE and Bootstrap variables: `--bs-border-radius-sm`, `--bs-border-radius`, and `--bs-border-radius-lg`. Nested surfaces keep concentric corners.

## 8. Accessibility Constraints & Accepted Debt

### Constraints

- WCAG 2.2 AA target.
- Visible keyboard focus for every control.
- Full keyboard access to navigation, filters, modal, and pagination.
- Light, dark, and automatic theme modes remain available through AdminLTE.
- The main task must reflow at 390px without page-level horizontal overflow.
- Error messages identify the field and the corrective action in Indonesian.

### Accepted Debt

No accepted design or accessibility debt.
