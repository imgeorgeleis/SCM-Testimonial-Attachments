=== SCM Testimonial Attachments ===
Contributors: secondclickmedia
Tags: testimonials, taxonomy, acf, elementor
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically manages testimonial attachment terms and provides a grouped selector for testimonial editors.

== Description ==

Requires pre-registered post types testimonial, people, location, and taxonomy testimonial_attachment (typically using ACF Pro). Registers no post types or taxonomies itself.

Synchronizes published People, Pages, Posts, Locations, and 11 permanent default Service terms (no WordPress categories required) into managed attachment terms. Testimonials can be assigned these terms in a grouped WordPress metabox; Elementor Loop Grid can filter by the taxonomy once configured.

Refer to README.md for complete setup, compatibility notes, caveats, and developer documentation.

== Installation ==

1. Configure required post types and taxonomy in ACF Pro and attach the taxonomy to Testimonials.
2. Upload this plugin directory to wp-content/plugins/ or install the packaged ZIP.
3. Activate the plugin.
4. Edit a Testimonial and select its attachment terms.
5. Configure Elementor Loop Grid queries separately.

== Frequently Asked Questions ==

= Does it automatically configure Elementor filtering? =
No. It supplies taxonomy associations. Configure Elementor queries for the page or template.

= Does it create CPTs or taxonomies? =
No. Register them separately, e.g. with ACF Pro.

= Why can't I add attachment terms manually? =
Terms are generated from supported source content or from the 11 built-in default Services.

== Changelog ==

= 1.2.0 =
* Always create 11 permanent Service terms without requiring WordPress categories.
* Preserve matching existing service term IDs and testimonial assignments on upgrade.
* Protect built-in Services from orphan cleanup.


= 1.2.0 =
* Allow internal term creation while blocking manual term creation.
* Enable grouped testimonial checkbox metabox.
* Add daily reconciliation and once-per-version bulk synchronization.
* Generate stable source-ID-based slugs for managed terms.

= 1.0.0 =
* Initial custom plugin implementation.
