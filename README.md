# Post Collection

**Contributors:** akirk
**Requires at least:** 5.0
**Tested up to:** 6.9
**Requires PHP:** 7.1
**License:** GPLv2 or later
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html
**Stable tag:** 2.0.0

Collect posts from around the web.

## Description

This plugin provides the facilities to store feed items in a separate post type. These can be used to create your own compilation of posts and re-publish them for friends.

## Changelog

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

