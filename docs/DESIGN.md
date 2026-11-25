# Design & CSS reference

Everything the plugin prints lives inside a single `.wfo` root element, so the whole app can be restyled
without touching PHP, and without fighting Elementor.

## The easy way

1. **Elementor widget → Style tab.** Accent, text colours, card background and border, radius, spacing,
   typography for card titles / prices / buttons / category bar. These write CSS variables scoped to that
   one widget instance.
2. **Plugin settings → Design tab.** Accent, radius, dark mode, and a *Custom CSS* box that is printed after
   the plugin stylesheet (site-wide, only on pages that use the menu).
3. **Appearance → Customize → Additional CSS** (or a child theme) for anything else. No Elementor Pro needed.

## Design tokens

Set on `.wfo`. Override them anywhere above the app in the cascade:

```css
.wfo {
  --wfo-accent: #e2572b;      /* primary colour: buttons, active states */
  --wfo-accent-ink: #fff;     /* text on the accent colour */
  --wfo-ink: #14110f;         /* headings and prices */
  --wfo-muted: #6f6862;       /* descriptions, hints */
  --wfo-bg: #fff;             /* page/app background */
  --wfo-surface: #faf7f4;     /* cards, fields, sheets */
  --wfo-line: #e8e2dc;        /* hairline borders */
  --wfo-radius: 14px;         /* cards, fields */
  --wfo-radius-lg: 22px;      /* sheets and dialogs */
  --wfo-gap: 20px;            /* grid gap */
  --wfo-cols: 3;              /* grid columns, set by the widget */
  --wfo-font: inherit;        /* typeface, inherits the theme by default */
  --wfo-shadow: 0 10px 30px rgba(20, 17, 15, .08);
  --wfo-shadow-sm: 0 2px 8px rgba(20, 17, 15, .06);
  --wfo-card-pad: 0px;
}
```

Dark mode: `@media (prefers-color-scheme: dark)` swaps the surface/ink tokens unless the root carries
`data-wfo-dark="light"`. The plugin setting *Design → Dark mode* forces `light`, `dark` or `auto`.

## Class map

| Class | What it is |
|---|---|
| `.wfo` | app root; all tokens live here |
| `.wfo-catbar` | sticky category navigation; `a.is-active` marks the current section |
| `.wfo-cat` | one category section (`#wfo-cat-{slug}`) |
| `.wfo-cat__title` | category heading |
| `.wfo-grid` | item grid; `data-wfo-layout="list"` switches to a row layout |
| `.wfo-card` | item card; `data-wfo-available="0"` when sold out |
| `.wfo-card__hit` | the whole-card button that opens the item dialog |
| `.wfo-card__media` | image box (fixed aspect ratio) |
| `.wfo-card__title` / `.wfo-card__desc` | name and description |
| `.wfo-price` | price, `.is-compare` for the struck-through original |
| `.wfo-badge` | badge chip; `--hot`, `--new`, `--veg` variants |
| `.wfo-card__add` | the round "+" affordance |
| `.wfo-cartbtn` | sticky/floating cart button with count and total |
| `.wfo-dialog` | native `<dialog>` item editor (bottom sheet on mobile) |
| `.wfo-group` | one option group (radio or checkbox) |
| `.wfo-option` | one option row, `input` + label + price delta |
| `.wfo-qty` | quantity stepper |
| `.wfo-drawer` | cart drawer / sheet |
| `.wfo-line` | one cart line |
| `.wfo-totals` | subtotal / delivery / total block |
| `.wfo-field` | labelled form field |
| `.wfo-btn`, `.wfo-btn--primary`, `.wfo-btn--ghost` | buttons |
| `.wfo-confirm` | post-order confirmation panel |
| `.wfo-closed` | closed-hours banner |

## Practical recipes

**Brand accent + squarer cards**
```css
:root { --wfo-accent: #1f6f4a; }
.wfo { --wfo-radius: 4px; --wfo-radius-lg: 6px; }
```

**Serif menu titles, sans body**
```css
.wfo { --wfo-font: "Inter", system-ui, sans-serif; }
.wfo-card__title, .wfo-cat__title { font-family: "Fraunces", Georgia, serif; font-weight: 600; }
```

**Two-column list layout with the price on the right**
```css
.wfo[data-wfo-layout="list"] .wfo-grid { grid-template-columns: 1fr; }
.wfo[data-wfo-layout="list"] .wfo-card__hit { display: flex; gap: 16px; }
```

**Hide the images entirely**
```css
.wfo-card__media { display: none; }
```

Because the stylesheet uses low-specificity selectors, a single class like the above is enough — no
`!important` should ever be necessary. If you find yourself needing one, that is a bug: report it.
