# Changelog

## 1.2.0 — Permanent default Services

- Always create the 11 configured default Service terms independently of WordPress categories or content titles.
- Migrate matching legacy managed Service terms in place where possible to preserve existing testimonial associations.
- Show built-in defaults under Services in the grouped selector and allow saving their assignments.
- Protect permanent defaults from orphan cleanup and category lifecycle changes.
- Use `scm-default-service-{name}` slugs; where names collide, WordPress may use a `(Service)` display suffix.


## 1.1.0 — GitHub-ready revision

- Fixed managed term insertion being rejected by the plugin's own `pre_insert_term` filter.
- Enabled the grouped Testimonial Attachments editor metabox.
- Changed unconditional full sync on every `init` to an initial per-version sync and daily WP-Cron reconciliation.
- Introduced stable source-type-and-ID term slugs.
- Improved orphan detection by checking source post types.
- Added GitHub documentation and installation notes.

## 1.0.0 — Original implementation

- Initial source-provided plugin with automatic source-to-taxonomy synchronization, a metabox implementation (registration commented out), and Elementor-compatible taxonomy relationships.
