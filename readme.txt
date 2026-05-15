=== Papy3D NoticeShield ===
Contributors: papy3d
Tags: admin notices, dashboard, notifications, admin cleanup, notices
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 2.1.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Clean and control WordPress admin notices with allow/block rules, history, filters and a modern dashboard interface.

== Description ==

Papy3D NoticeShield helps administrators keep the WordPress dashboard readable while preserving control over third-party admin notices.

Unlike simple notice hiders, Papy3D NoticeShield keeps captured notices reviewable, reversible, and stored locally. It is designed for administrators who want a cleaner dashboard without losing visibility over plugin messages.

= Highlights =

* Review notices before making a decision.
* Allow, block, ask again, or temporarily mute notices.
* Source dashboard to allow or block notices by detected source.
* Decision journal showing what changed and when.
* Export/import rules as JSON for multi-site workflows.
* Safe Core mode designed to avoid WordPress Core workflow notices.
* Optional placeholder for blocked notices.
* Local-only storage. No tracking, no external service.
* No advertising, no upsell, and no commercial prompt inside your dashboard.
* Nonce-protected actions and reversible decisions.
* 100% free plugin, with no paid version planned.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it from the WordPress plugin screen.
2. Activate the plugin.
3. Go to Tools > Papy3D NoticeShield.
4. Review captured notices and choose Allow, Block, Mute, or Ask again.

== Frequently Asked Questions ==

= Does it delete notices? =

No. It stores a local review copy and applies display decisions only.

= Does it hide WordPress Core notices? =

Safe Core mode is enabled by default and is designed to keep WordPress Core workflow notices untouched.

= Does it use an external service? =

No. Everything is stored locally in WordPress options.

= Can I move my rules to another site? =

Yes. Use the Export rules and Import rules tools in the plugin screen.


== Changelog ==

= 2.1.4 =
* Limited own-page notice suppression to generic admin hooks and documented why it is scoped to the plugin screen.
* Added an explicit Safe Core mode notice in the admin interface.
* Clarified the no ads, no upsell, local history, nonce protection and free plugin positioning in the readme.

= 2.1.3 =
* Hardened JSON import/export validation with schema and generator metadata.
* Made Safe Core and Learning mode settings affect runtime behavior explicitly.
* Updated licensing metadata to GPLv2 or later.
* Improved top action button contrast.

= 2.1.1 =
* Prefixed admin view variables for stricter WordPress Coding Standards compatibility.

= 2.1.0 =
* Refactored the plugin into separated classes, traits, views, and assets for cleaner maintenance.
* Kept the main plugin bootstrap minimal and WordPress.org review-friendly.
* Preserved the existing admin notice capture and decision workflow.

= 2.0.4 =
* Renamed internal prefixes from ANC to NS for consistency with NoticeShield.
* Fixed notice-class regex boundary detection.
* Removed the last inline style attribute from the admin screen.
* Added Clear history without removing allow/block rules.
* Added a one-hour pause mode for debugging.
* Added last-seen admin page context for captured notices.
* Added explicit multisite scope information.

= 2.0.3 =
* Added dismissible admin update notices.

= 2.0.2 =
* Improved WordPress admin notice behavior.

= 2.0.1 =
* Fixed admin filters, search, source filtering, and notice preview controls.

= 2.0.0 =
* Renamed to Papy3D NoticeShield.
* Added source control dashboard.
* Added export/import rules.
* Added decision journal.
* Added temporary mute actions.
* Added blocked notice placeholders.
* Moved admin CSS and JavaScript into dedicated assets.
* Improved admin interface and WordPress.org compliance.
