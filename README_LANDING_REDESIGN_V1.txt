BacaDulu Research — Landing Redesign V1

Files in this overlay:
- resources/views/pages/landing.blade.php
- resources/css/pages/landing.css

No migration, database, package, route, or environment changes are included.
No new dependency is required.

Install by extracting this folder and running:

robocopy "D:\Pkl\bacadulu-research-landing-redesign-v1" "D:\Pkl\bacadulu-research" /E /COPY:DAT /DCOPY:T /R:2 /W:2 /XD .git node_modules vendor storage\logs storage\framework\sessions storage\framework\cache storage\framework\views public\uploads /XF .env database.sqlite *.log

Then:

npm run build

For local development you may then run:

npm run dev

The redesign uses existing Laravel routes and existing CSS variables. It does not add external icon/font dependencies; SVG icons are inline in the Blade template.
