# UI Design Agent Instructions

## Color And Typography
- Use this project system UI palette as the default source for interface colors:
  - Carbon Black: `#1b2021` for primary text, deep sidebar surfaces, and strongest contrast.
  - Ebony: `#51513d` for secondary navigation, subdued controls, and supporting text.
  - Palm Leaf: `#a6a867` for selected states, meaningful status accents, and restrained highlights.
  - Vanilla Custard: `#e3dc95` for warm warnings, totals, and low-intensity emphasis.
  - Sand Dune: `#e3dcc2` for page backgrounds, quiet bands, and soft panel contrast.
- Prefer flat colors and subtle tints derived from the palette; do not use the provided gradient variants as decorative page or panel backgrounds unless explicitly requested.
- Do not let the interface become dominated by one hue.
- Use normal letter spacing.
- Keep headings compact inside panels.
- Avoid viewport-scaled font sizes.

## Technical Constraints
- This project uses native PHP MVC, not Laravel or Symfony.
- Views live in `app/Views/`.
- Frontend assets live in `public/assets/css/app.css` and `public/assets/js/app.js`.
- Keep changes compatible with the existing `/BTS` base path.
- Do not introduce a frontend framework unless explicitly requested.
- Use plain HTML, CSS, and small JavaScript enhancements.

## Verification Expectations
- Check desktop and narrow viewport layouts.
- Confirm text does not overlap or overflow buttons, panels, tables, or form fields.
- Confirm saved, error, printable, and disabled states are visibly distinct.
- Confirm workflow actions remain usable after styling.
