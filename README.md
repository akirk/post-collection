# Post Collection

- Contributors: akirk
- Tags: bookmarks, read-later, reading-list, rss, notes
- Requires at least: 6.0
- Requires PHP: 7.4
- Tested up to: 7.1
- Stable tag: 2.0.0
- License: GPL-2.0-or-later
- License URI: https://www.gnu.org/licenses/gpl-2.0.html

Save articles from around the web, extract readable content, organize them into collections, and review them with notes in WordPress.

## Description

[Try it in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/post-collection/main/blueprint.json) — or [with demo data](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/post-collection/main/demo.json), or [in OpenStation](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/post-collection/main/blueprint-openstation.json), the same app opened in desktop mode with the [OpenStation](https://github.com/WordPress/openstation) plugin.

Post Collection turns your WordPress into a read-it-later archive that you own. Give it the URL of an article and the plugin downloads the page, extracts the readable article text with [Readability](https://github.com/andreskrey/readability.php), and stores it as a collected post in your own database — so the article is still there when the original goes away.

Collected posts live in their own custom post type and are grouped by a Post Collection taxonomy, so you can keep separate collections for separate topics and decide per collection whether it is publicly visible or private to you.

### Save things

- Save any URL from the **Post Collection** app on your site's frontend, from a bookmarklet in Tools, or from the [Friends browser extension](https://chromewebstore.google.com/detail/friends/ledbghpaplkpclndlommpbokndieflhl).
- The article text is extracted from the page, and external images can be downloaded into your media library so the copy is self-contained.
- Site configurations handle pages that do not yield to a plain fetch: YouTube videos are stored as embeds, archive.is snapshots are resolved, and there is a fallback path for Cloudflare-protected pages.
- Archived copies of an original URL can be looked up in the Wayback Machine and on archive.is when the live page is gone.

### Organize and review

- The frontend app lists your collections, the articles in each one, and a review queue.
- Each article can carry a **note** and a reading status — unread, read, skipped or archived — plus a rating, so a long backlog can be worked through instead of just growing.
- Collections can be published so visitors can read your compilation, or kept hidden from the default view.
- Tags on collected posts are kept alongside the article so a collection stays browsable.

### Import and export

- Bulk import from one URL per line, CSV exports from other reading list services, browser `bookmarks.html` files, RSS and Atom feeds, and OPML files. Each URL is fetched and its article content extracted as it imports.
- Export a single collection or all of them as a Netscape **Bookmarks HTML** file (readable by browsers, Pinboard, Raindrop and most reading list apps) or as **OPML**. When exporting everything, each collection becomes its own folder.
- Because everything is stored in ordinary WordPress posts and terms, the built-in WordPress export tool produces a full backup including the article text.

### Works with other plugins

Post Collection runs standalone, but it also integrates with the [Friends](https://wordpress.org/plugins/friends/) plugin: when Friends is active, every post in your feed gets a "Save to Post Collection" entry in its dropdown menu, and notes are shown underneath the post on the Friends frontend.

The plugin also registers WordPress Abilities (listing, creating and updating collections, saving a URL, reading and editing collected articles and their notes), so an MCP client or another plugin can drive the collection programmatically.

**Development of this plugin is done [on GitHub](https://github.com/akirk/post-collection). Pull requests welcome. Please see [issues](https://github.com/akirk/post-collection/issues) reported there before going to the plugin forum.**

## Installation

1. Upload the `post-collection` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Visit `/post-collection/` on your site to create your first collection

## Frequently Asked Questions

### Do I need the Friends plugin?

No. Since version 2.0.0 Post Collection is a standalone plugin. If the Friends plugin happens to be installed, the two integrate: you can save posts from your feed into a collection and see your notes on the Friends frontend.

### Does this plugin create custom database tables?

No. Collected posts are a custom post type, collections are a taxonomy, and notes are a second custom post type. Deleting the plugin leaves your WordPress as slim as it was before.

### Where does the article text come from?

The plugin fetches the URL server-side and runs the page through the Readability library, which is the same approach reader modes in browsers use. If a site needs special handling — YouTube, archive.is, Cloudflare-protected pages — a site configuration takes over.

### Can other people see my collections?

Only if you let them. Each collection can be published or hidden, and the frontend app checks the viewer's access before showing a collection or an individual article.

### Can I get my data out again?

Yes. Every collection can be exported as a Bookmarks HTML file or as OPML, and the standard WordPress export tool produces a complete backup including the stored article content.

## Screenshots

1. A collection in the Post Collection app, listing the saved articles with their tags and notes.

## Changelog

### Unreleased
- Add Bookmarks HTML and OPML export for collections, and OPML import
- Prepare the plugin for the WordPress.org plugin directory

### 2.0.0
- Post Collection now works as a standalone plugin without requiring the Friends plugin ([#7])
- Renamed plugin from Friends Post Collection to Post Collection ([#12])

### 1.2.6
- Use a Read More link as fetch url if exists ([#10])
- Use HTML API in post collection ([#6])

### 1.2.5
- Prevent double submission from the browser extension

### 1.2.4
- Update Share button to the new Friends styling

### 1.2.3
- Supply post collections to the Friends browser extension
- Add site configs to allow storing Youtube videos

### 1.2.2
- Switch the readability library to https://github.com/fivefilters/readability.php
- Prevent wpautop to insert newlines where undesired

### 1.2.1
- Fix UTF-8 problems with downloading external images

### 1.2.0
- Add ability to activate and deactivate Post Collections ([#3])
- Add the ability to copy a post ([#4])
- Add the ability to download external images to the media library ([#5])

### 1.1
- Reduce required priviledges, see https://github.com/akirk/friends/pull/121.

### 1.0
- Add a feed option to fetch full content: Make use of Readability to get the contents of posts from the original URL (useful for excerpt feeds), either via a dropdown entry for each feed item, or for new entries in incoming feeds (checkbox in the feeds overview).

### 0.8
- Update for Friends 2.0

[#12]: https://github.com/akirk/post-collection/pull/12
[#10]: https://github.com/akirk/post-collection/pull/10
[#7]: https://github.com/akirk/post-collection/issues/7
[#6]: https://github.com/akirk/post-collection/pull/6
[#5]: https://github.com/akirk/post-collection/pull/5
[#4]: https://github.com/akirk/post-collection/pull/4
[#3]: https://github.com/akirk/post-collection/pull/3
