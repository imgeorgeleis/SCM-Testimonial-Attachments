# SCM Testimonial Attachments

A WordPress plugin by **Second Click Media** that automatically maintains a `testimonial_attachment` taxonomy from existing website content, and lets editors associate **Testimonials** with **People, Pages, Posts, Locations, and Services** through a grouped checkbox metabox.

**Current version:** 1.2.0 · **License:** GPL-2.0-or-later · **PHP:** 7.4+

> **Important:** This plugin does **not** register custom post types or taxonomies. They must already be configured using ACF Pro (or equivalent WordPress registration code). Elementor is responsible for the frontend testimonial display and query configuration.

## Features

- Creates and synchronizes attachment terms for **published** People, Pages, Posts, and Locations.
- Always creates **11 built-in Service attachment terms**, without requiring any WordPress categories or matching posts.
- Updates managed term names when source titles change.
- Removes post-backed attachment terms when their source is unpublished or deleted; never removes the 11 built-in Services because a source category is missing.
- Displays a **Testimonial Attachments** metabox on testimonial edit screens, organized by source type.
- Saves multi-term selections with nonce and capability checks; clearing all checkboxes clears existing assignments.
- Uses unique, source-based managed term slugs (`scm-people-123`, etc.), rather than relying on non-unique post titles.
- Prevents ordinary/manual creation of attachment terms while allowing the plugin to create its own terms.
- Schedules a daily reconciliation via WP-Cron, plus a once-per-version initial sync and immediate save/delete synchronization.
- Provides the taxonomy relationships that Elementor Loop Grid can query.

## Requirements

- WordPress with PHP 7.4 or later.
- **ACF Pro** to register the post types and attachment taxonomy described below.
- **Elementor** with a Loop Grid implementation that supports a query for your Testimonials post type. The available taxonomy controls depend on your Elementor installation; Elementor Pro may be required.
- The classic metabox UI must be supported by your post editing experience. Test the block editor and any ACF taxonomy fields for duplicate or competing assignment controls.

## Install

1. Download the repository as a ZIP, or clone it into `wp-content/plugins/scm-testimonial-attachments/`.
2. Confirm that the ACF registrations below are active and saved.
3. In **Plugins → Installed Plugins**, activate **SCM Testimonial Attachments**.
4. Open the **Testimonials** editor and locate **Testimonial Attachments**.
5. Confirm that attachment terms have been created from your published content.
6. Select relevant terms and save the testimonial.

For a manual ZIP upload, package the `scm-testimonial-attachments/` folder itself, then use **Plugins → Add New → Upload Plugin**.

### Required content registrations (ACF Pro)

| Name | WordPress key | Registration owner |
| --- | --- | --- |
| Testimonials | `testimonial` | ACF Pro |
| People | `people` | ACF Pro |
| Locations | `location` | ACF Pro |
| Testimonial Attachments | `testimonial_attachment` | ACF Pro |
| Pages | `page` | WordPress |
| Posts | `post` | WordPress |
| Services | Built into this plugin | No category registration needed |

In **ACF → Taxonomies**, attach `testimonial_attachment` to the `testimonial` post type. For the metabox to be available, the taxonomy must exist **and** be attached to Testimonials. You can disable the taxonomy's default administrative UI if it creates a duplicate selector; this plugin has its own grouped metabox.

### Built-in Service attachments

The plugin creates these **permanent default attachment terms**, whether or not matching WordPress categories, People, Pages, Posts, or Locations exist:

- General
- State Planning
- Divorce
- High-Asset Divorce
- Child Custody
- Property Division
- Spousal Support
- Prenuptial Agreements
- Domestic Violence
- Wills and Trusts
- Power of Attorney

Edit the `$service_categories` array in `scm-testimonial-attachments.php` to change the list. The spelling **“State Planning”** is deliberately preserved. Defaults use stable `scm-default-service-{name}` slugs and `service_default` term metadata. When an existing source term has the same display name, WordPress may require the default term to display a **“(Service)”** suffix; it is still a distinct selectable term under Services.

## How it works

```text
Published People / Page / Post / Location ─┐
                                           ├─> Generated testimonial_attachment terms
11 built-in default Service terms ──────────┘                     │
                                                                 ▼
                                                   Testimonial term assignments
                                                                 │
                                                                 ▼
                                             Elementor Loop Grid taxonomy query
```

Example: a published People entry **Jane Smith** (post ID `123`) generates a term named **Jane Smith** with slug `scm-people-123`. Assign that term to a testimonial to associate the testimonial with Jane Smith. This association does not automatically show the testimonial on Jane Smith's page until an Elementor query is configured to select it.

### Synchronization details

| Event | Behavior |
| --- | --- |
| First request after activation/update (once registrations are available) | Scans published source posts, creates all 11 default Service terms, then removes invalid source-backed terms |
| Published source post created/updated | Creates/renames corresponding term |
| Source post moved out of `publish` | Deletes corresponding managed term |
| Source post permanently deleted | Deletes corresponding managed term |
| WordPress category created, renamed, or deleted | Does not affect built-in Service terms |
| Daily WP-Cron run | Reconciles all configured source content and removes orphaned managed terms |

**Important:** Deleting a generated term also removes that term's existing associations with testimonials. If you republish a deleted/unpublished source, the term is generated again with a **new term ID** and you must reassign affected testimonials.

### What is stored in term metadata?

| Term meta key | Value |
| --- | --- |
| `_scm_attachment_source_id` | Source post ID; unused for built-in Services |
| `_scm_attachment_source_type` | `people`, `page`, `post`, `location`, or `service_default` |
| `_scm_attachment_source_taxonomy` | Empty for built-in Services and source posts |

Term metadata defines ownership. Unmarked legacy/manual terms are left untouched by orphan cleanup, but the grouped metabox only lists **managed** terms. Manual creation of terms in this taxonomy is blocked by the plugin.

## Editorial workflow

1. **Publish content** in People, Pages, Posts, or Locations as needed. All 11 Services are available automatically, without creating WordPress categories.
2. **Add/Edit a Testimonial** in WordPress.
3. In the **Testimonial Attachments** box, select one or more items under the relevant groups.
4. **Publish / Update** the testimonial.
5. Verify that the frontend Elementor widget uses the expected taxonomy term filter.

Example assignment:

| Testimonial | Attachment group | Example selection |
| --- | --- | --- |
| Great result for our family | People | Jane Smith |
| Great result for our family | Locations | Chicago Office |
| Great result for our family | Services | Divorce |

**Note:** If the testimonial editor also contains a separate ACF taxonomy field for `testimonial_attachment`, avoid saving conflicting selections from both interfaces. Use one assignment interface consistently. This plugin replaces the default WordPress taxonomy metabox but cannot automatically remove a custom ACF field.

## Elementor Loop Grid integration

1. Build an Elementor **Loop Item** template that renders testimonial content, e.g. title, description, and any ACF fields.
2. Add a **Loop Grid** widget to the target page and use the Loop Item template.
3. Configure its query to use post type `testimonial`.
4. Add a taxonomy filter using `testimonial_attachment` and the relevant term(s), if available in your Elementor query controls.
5. Save and confirm the results.

**Static example:** On a Chicago office page, select the corresponding `testimonial_attachment` term and show testimonials assigned to it.

**Dynamic templates:** The plugin does **not** currently register an Elementor custom Query ID or automatically map a current People/Location/Service page to an attachment term. For a single reusable template that picks a term from the current page automatically, implement a site-specific Elementor query hook or another supported dynamic query method.

## Technical reference

| Item | Value |
| --- | --- |
| Main file | `scm-testimonial-attachments.php` |
| Main class | `SCM_Testimonial_Attachments` |
| Taxonomy | `testimonial_attachment` |
| Testimonial post type | `testimonial` |
| Daily cron action | `scm_testimonial_attachments_daily_sync` |
| Sync option | `scm_testimonial_attachments_sync_version` |
| Metabox field | `scm_testimonial_attachments[]` |

### WordPress hooks

| Hook | Purpose |
| --- | --- |
| `init` (priority 100) | First-run synchronization after registrations |
| `save_post` (priority 999) | Sync source posts |
| `before_delete_post` | Delete associated source term |
| `add_meta_boxes_testimonial` | Register grouped metabox |
| `save_post_testimonial` (priority 9999) | Store selected term IDs |
| `pre_insert_term` | Prevent manual term creation, except plugin-managed internal insertions |
| `admin_menu` | Hide taxonomy management submenu |
| `scm_testimonial_attachments_daily_sync` | Daily reconciliation |

### Operational considerations

- The initial sync and the daily cron may be **expensive on large sites**: the implementation queries all published supported content and all attachment terms. Consider batching/CLI synchronization for large installations.
- WP-Cron executes when WordPress receives traffic unless a real server cron is configured.
- The plugin uses PHP-side term selection and a WordPress classic metabox; check how your editor (Classic/Block/ACF) handles metabox submissions.
- The plugin **does not migrate** or automatically reassign existing testimonials when changing generated term IDs or merging duplicate terms.
- Source-based slugs avoid title-dependent slugs. If an unrelated term already occupies a reserved managed slug, the plugin skips its creation rather than taking over the existing term.
- Term creation failure is silent; verify taxonomy registration and check for conflicting terms or filters.
- No public shortcode, REST endpoint, Elementor query hook, or settings page is included.

## Troubleshooting

**No attachment terms appear** — Check that the taxonomy is registered with the exact key `testimonial_attachment`; ensure the taxonomy exists; the 11 built-in Service terms require no categories. The first sync runs after registration on the next request; WP-Cron periodically reconciles changes.

**No grouped selector** — Check that Testimonials uses the exact post type key `testimonial`, and that the taxonomy is attached to that post type. Look for the **Testimonial Attachments** metabox in screen preferences. Some editor configurations hide legacy metaboxes.

**Selected terms disappear** — Ensure the editor submitted the metabox nonce, your account can edit that testimonial, and no ACF taxonomy field or other plugin is overwriting the assignment afterward.

**Testimonials don't appear in Elementor** — Check testimonial publication status, term assignment, query post type, taxonomy and term, as well as Elementor's cache/query behavior. This plugin does not create the frontend query automatically.

**Duplicate or unexpected terms** — Earlier plugin versions used name-based slugs. This version identifies managed terms by `_scm_attachment_source_id` + `_scm_attachment_source_type` and renames existing managed slugs to a stable source-based format. Review any hard-coded term **slugs** in frontend filters after updating.

**Manual term creation gives an error** — Expected behavior. Terms must come from supported source content.

## Upgrading from versions 1.0.0 and 1.1.0

Version 1.1.0 fixed an internal term-creation conflict, re-enables the grouped selector, and introduces a once-per-version sync plus daily cron rather than running a complete scan on every page load. Existing **managed** terms are matched by their source metadata. Their slugs are normalized to `scm-{source-type}-{source-id}`; if you depend on term slugs in Elementor, update your filters accordingly.

Version 1.2.0 introduces permanent default Services. It migrates matching category-backed service terms **in place**, retaining their term IDs and existing testimonial relationships wherever a prior managed Service term can be identified. Existing Elementor queries targeting old *slugs* may need updating to `scm-default-service-{name}`.

**Before upgrading:** take a database backup and test on staging. Review legacy/manual terms and ACF taxonomy fields. Avoid running two variants of this plugin concurrently.

## Development / validation

The supplied package was syntax-checked with `php -l`. A live WordPress + ACF Pro + Elementor integration test has **not** been run as part of package creation. Test on staging before production deployment, especially frontend queries and editor metabox behavior.

## Repository structure

```text
scm-testimonial-attachments/
├── scm-testimonial-attachments.php
├── README.md
├── readme.txt
├── CHANGELOG.md
├── LICENSE
└── .gitignore
```

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

© Second Click Media contributors.
