=== 3task Glossary – Dictionary, Wiki & Knowledge Base ===
Contributors: 3task
Tags: glossary, dictionary, wiki, knowledge-base, a-z-index
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 2.4.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Glossary or knowledge base on normal WordPress pages. A-Z index, categories, table of contents, search, auto-linking. No custom tables.

== Description ==

Most glossary plugins store your terms in their own database tables. If the plugin is abandoned, your content is stranded. 3task Glossary uses normal WordPress pages instead, so every term stays a page you own, indexable and exportable, with or without this plugin.

You get an A-Z index, automatic linking of terms within your glossary pages (site-wide in Pro), reverse links and valid `DefinedTerm` structured data. Nothing is loaded from an external server.

**3task Glossary** helps you create professional glossaries, dictionaries, wikis and knowledge bases using the WordPress page hierarchy you already know. No complex setup required - just select a page as your glossary, and its child pages become your entries.

Perfect for:
* **Glossaries** - Define technical terms, jargon, acronyms
* **Dictionaries** - Build word lists with definitions
* **Knowledge Bases** - Create support documentation
* **Wikis** - Organize encyclopedia-style content
* **Lexicons** - Academic and scientific term collections

### How It Works

1. Create a WordPress page for your glossary (e.g., "Glossary")
2. Add child pages under it for each entry (e.g., "API", "Database", "Widget")
3. Activate the glossary in plugin settings
4. Your glossary page automatically shows A-Z navigation and entry list

### Knowledge Base Mode (new in 2.4)

Switch any glossary to knowledge base mode. The page tree becomes your help center:

* **Topic overview:** Child pages become categories, their child pages become articles. Pages without children are listed under "General".
* **Table of contents:** Built from the H2 and H3 headings of each article, with anchors.
* **Breadcrumbs:** Visible on every article, with BreadcrumbList markup. The markup switches itself off when Yoast SEO, Rank Math or All in One SEO is active, and you can turn breadcrumbs off per knowledge base.
* **Related articles:** Other articles of the same category.
* **Simple search:** Searches titles and text of the articles in this knowledge base. No external service.
* **"Was this helpful?" rating:** Counts yes and no per article. No IP address, no cookie, no personal data. The totals appear in the plugin settings.

### Key Features

* **Uses WordPress Pages** - No new post types, no database tables. Just pages.
* **A-Z Navigation** - Beautiful alphabetical navigation with 2 styles (Buttons, Pills)
* **2 Color Schemes** - Emerald (green) and Slate (neutral gray)
* **Dark Mode Support** - Auto (system), Light, or Dark mode
* **German Umlaut Support** - Proper handling of Ä, Ö, Ü, ß
* **Auto-Linking** - Automatically link glossary terms within your glossary pages
* **Responsive Design** - Layouts adapt to narrow screens
* **Lightweight** - No bloat, fast loading, SEO-friendly structure
* **Multi-Language Ready** - Works with RTL languages, Hebrew, Arabic, Greek
* **Unlimited glossaries and entries** - No artificial limit
* **Structured definitions** - Optional DefinedTerm markup
* **Sidebar widget** - A-Z, recent or popular entries
* **Reverse links** - Find posts that link to a term

### Pro Version Features

Upgrade to **3task Glossary Pro** for advanced features:

**Site-wide auto-linking**
Pro extends linking beyond glossary pages to configured posts and pages. Existing links, headings and code are excluded. Choose where links appear and how often each term is linked.

* **Site-Wide Auto-Linking** - Link glossary terms across ALL posts and pages automatically
* **5 Tooltip Themes** - Show definitions on hover (Light, Dark, Gradient, Minimal, Bordered)
* **AJAX Live Search** - Visitors find terms instantly while typing
* **AI Definition Generator** - Generate definitions with OpenAI, Claude or Gemini (BYOK)
* **Synonyms** - Link multiple terms (AI, KI, Artificial Intelligence) to one entry
* **CSV Import/Export** - Bulk create hundreds of entries, backup your glossary
* **6 Color Schemes** - Emerald, Ocean, Sunset, Berry, Slate + Custom colors
* **Priority Support** - Email support with fast response

[Upgrade to Pro](https://www.3task.de/en/glossary-pro/)

== Installation ==

### Automatic Installation

1. Login to WordPress admin
2. Go to **Plugins > Add New**
3. Search for "3task Glossary"
4. Click **Install Now**, then **Activate**

### Manual Installation

1. Download the plugin zip file
2. Go to **Plugins > Add New > Upload Plugin**
3. Choose the zip file and click **Install Now**
4. Click **Activate**

### Quick Start

1. Create a WordPress page (e.g., "Glossary")
2. Create child pages under it for each entry
3. Go to **3task Glossary** in admin menu
4. Add your page as a glossary
5. Visit your glossary page to see the A-Z navigation

== Frequently Asked Questions ==

= How do I create a glossary? =

1. Create a regular WordPress page (this will be your glossary index)
2. Create child pages under it (each child page is an entry)
3. Go to 3task Glossary menu and add your page as a glossary
4. That's it! Your page now shows A-Z navigation and links to all child pages.

= How do I build a knowledge base? =

1. Create a page for your help center, for example "Help".
2. Create one child page per topic, for example "Getting started" and "Billing". These become categories.
3. Create your articles as child pages of these topics.
4. Add the "Help" page as a glossary and switch its mode to "Knowledge base" in the glossary list.

= Does the rating store personal data? =

No. A vote only increases one of two counters on the article. The visitor's browser remembers in local storage that it has voted, so the buttons are hidden on the next visit. No IP address, user agent or cookie is stored.

= Can I use this for a dictionary or wiki? =

Yes! The plugin works great for dictionaries, wikis, knowledge bases, lexicons, and any A-Z indexed content. Each child page becomes an entry with its own URL.

= How many glossaries and entries can I add? =

The free version has no artificial limits. Create as many glossaries and entries as you need. Pro adds automation features like site-wide auto-linking, tooltips, and import/export.

= Does it work with my theme? =

Yes! The plugin uses clean, semantic HTML that works with any WordPress theme. It includes 2 color schemes (Emerald, Slate) and dark mode support. Pro version offers 6 schemes + custom colors.

= How does auto-linking work? =

**Free version:** Automatically links glossary terms within your glossary pages only.

**Pro version:** Extends linking to configured posts and pages across your site, with optional tooltips. Existing links, headings and code remain unchanged.

= Is it SEO-friendly? =

Yes! The plugin uses proper semantic HTML structure, clean URLs (your existing page permalinks), and doesn't add any bloat. Your existing page SEO remains intact.

= Does it support German umlauts and special characters? =

Yes! German umlauts (Ä, Ö, Ü, ß) are properly sorted under their base letters (A, O, U, S). Works with Hebrew, Greek, Arabic and other languages too.

= Can I customize the appearance? =

Yes! Choose from 2 color schemes (Emerald, Slate), 2 navigation styles (Buttons, Pills), and dark mode settings (Auto, Light, Dark). Pro version offers 6 color schemes, 3 navigation styles, and custom colors.

== Screenshots ==

1. **Glossary Page** - A-Z navigation with entry list
2. **Admin Settings** - Simple glossary configuration
3. **Color Schemes** - Emerald and Slate color options
4. **Navigation Styles** - Buttons or Pills
5. **Entry Page** - Sub-navigation on glossary entries
6. **Dark Mode** - Automatic dark mode support

== Changelog ==

= 2.4.0 =
* New: Knowledge base mode per glossary with topic overview, table of contents, breadcrumbs, related articles, search and a "Was this helpful?" rating.
* New: Mode switch and breadcrumb option in the glossary list, with article and rating totals.
* Knowledge bases are not used as an auto-linking source, so category names are not linked in your texts.

= 2.3.1 =
* Protect private and password-protected glossary pages and parents.
* Preserve HTML when linking terms; improve punctuation, synonyms and cache invalidation.
* Fix all widget modes and protect shared Pro settings during Free uninstall.


= 2.3.0 =
* New: Completely redesigned admin interface with 3task Plugin Design System v1.0
* New: Dashboard tab with stats overview (glossaries, terms, active count)
* New: Animated gradient header with glassmorphism effects
* New: Tab navigation with icons and animated underline
* New: Quick Actions grid for faster navigation
* New: Schema.org DefinedTerm markup for better SEO (can be disabled in Settings)
* New: Sidebar widget with A-Z navigation, recent terms, or popular terms display
* New: Reverse Links showing which posts/pages mention a glossary term
* New: Print stylesheet for clean A4 printing of glossary pages
* New: Onboarding banner for new users
* Improved: DSGVO-compliant design (no Google Fonts, uses System Font Stack)
* Improved: Responsive layout for all screen sizes

= 2.2.1 =
* Fixed: Consistent branding - renamed all "SEO Glossary" labels to "3task Glossary" in admin menu and UI

= 2.2.0 =
* New: Accessibility options for inactive letters (screen reader support)
* New: Four display modes for letters without entries: Accessible (with aria-label), Hidden (aria-hidden), Show (visual only), None (hide completely)
* Improved: Screen readers now properly announce "X, no entries" instead of reading letters as one string
* Thanks to Elena Brescacin for reporting the accessibility issue and contributing the Italian translation

= 2.1.0 =
* Renamed plugin to 3task Glossary
* New: Optimized for dictionary, wiki and knowledge base use cases
* Improved: Better multi-language support

= 2.0.0 =
* Complete rewrite using Child-Pages-System
* New: Uses WordPress pages instead of custom database
* New: 6 color schemes (Emerald, Ocean, Sunset, Berry, Slate, Auto)
* New: 3 navigation styles (Buttons, Pills, Minimal)
* New: Dark mode support (Auto, Light, Dark)
* New: German umlaut support (Ä, Ö, Ü, ß)
* New: Auto-linking within glossary pages
* Improved: Cleaner, lighter codebase
* Improved: Better accessibility
* Improved: Modern admin interface

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 2.4.0 =
New knowledge base mode. Existing A-Z glossaries are not changed.

= 2.3.1 =
Security and reliability update. Back up your site before updating. Requires WordPress 6.2 or newer.


= 2.3.0 =
Major update! New admin dashboard, Schema.org SEO, sidebar widget, reverse links, print styles. Fully DSGVO-compliant.

= 2.2.0 =
Accessibility improvement: Inactive letters now work properly with screen readers. Choose from 4 display modes in Design settings.

= 2.1.0 =
Plugin renamed to 3task Glossary. Now optimized for glossaries, dictionaries, wikis and knowledge bases.

= 2.0.0 =
Major update! Now uses WordPress pages instead of custom database. Create your glossary by making child pages under a parent page. Much simpler and more flexible.

== Developer Notes ==

### Filters

* `azgl_max_glossaries` - Override maximum glossaries limit
* `azgl_max_entries` - Override maximum entries limit
* `azgl_color_schemes` - Add custom color schemes
* `azgl_nav_styles` - Add custom navigation styles

### Actions

* `azgl_before_glossary` - Before glossary output
* `azgl_after_glossary` - After glossary output
* `azgl_before_navigation` - Before navigation output
* `azgl_after_navigation` - After navigation output

### CSS Classes

* `.azgl-nav` - Navigation container
* `.azgl-entries` - Entries container
* `.azgl-section` - Letter section
* `.azgl-letter` - Letter heading
* `.azgl-list` - Entry list
* `.azgl-item` - Entry item
* `.glossary-term` - Auto-linked term

== About 3task.de ==

3task Glossary is developed by **3task.de**, Frank Stemmler, a WordPress developer. The plugin keeps glossary content in ordinary WordPress pages so your definitions remain available independently of the plugin.

[Visit 3task.de](https://www.3task.de/)

== Support ==

**Free Version:**
* [WordPress.org Support Forum](https://wordpress.org/support/plugin/3task-glossary/)
* Built-in Help tab in plugin settings

**Pro Version:**
* Priority email support
* Support from the plugin developer
* Custom development available

[Get Pro Support](https://www.3task.de/en/glossary-pro/)
