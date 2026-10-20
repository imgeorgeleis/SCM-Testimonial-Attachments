# Changelog

## 1.1.0 — GitHub-ready revision

- Fixed managed term insertion being rejected by the plugin's own `pre_insert_term` filter.
- Enabled the grouped Testimonial Attachments editor metabox.
- Changed unconditional full sync on every `init` to an initial per-version sync and daily WP-Cron reconciliation.
- Introduced stable source-type-and-ID term slugs.
- Improved orphan detection by checking source post types.
- Added GitHub documentation and installation notes.

## 1.0.0 — Original implementation

- Initial source-provided plugin with automatic source-to-taxonomy synchronization, a metabox implementation (registration commented out), and Elementor-compatible taxonomy relationships.
