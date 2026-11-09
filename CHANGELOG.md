# Changelog

All notable changes to TERAX AI Assistant. Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versioning: [Semantic Versioning](https://semver.org/).

How to add an entry: every pull request adds one line under `## [Unreleased]`, in the matching group (`### Added`, `### Changed`, `### Fixed`, `### Removed`, `### Security`). One entry is one line starting with `- `, written for the practice staff who read it, no ticket numbers. `npm run version:bump -- <version>` moves the entries to the new version and copies them to the changelog in `readme.txt`. See `docs/RELEASE.md`.

## [Unreleased]

### Added
- Insights page: questions the assistant could not answer, grouped by topic, with an "Add answer" shortcut to the Knowledge page; the Dashboard shows the answer rate
- Call-back requests: a form in the chat when the assistant cannot answer, and a Requests page in the admin
- Sales mode with a lead form for sites that are not medical practices
- First-run setup wizard (AI connection, booking link, build the knowledge index)
- Notice in the admin and an e-mail to the site admin when the AI provider keeps failing
- CSV export of conversations with a choice of separator for German or US Excel

### Changed
- The free plugin no longer limits messages, indexed pages or log retention, and the "Powered by TERAX" link in the chat is off until you turn it on in the settings
- The admin hides the License page unless the Pro add-on is active
- The paid features (licence, Managed AI, sales mode, call-back requests, custom instructions, insights) now come from the separate "TERAX AI Assistant Pro" add-on; the free plugin has no licence code and sends nothing to TERAX servers
- The admin loads faster: its script is split per page (about 216 KB down to 91 KB gzip)
- The admin uses the React that WordPress already loads instead of its own copy, so its main script is about half the size (47 KB instead of 91 KB gzip) and keeps working when WordPress updates React
- Questions in German now find content written in English, and the other way round
- An API key that cannot be decrypted (changed salt, moved site) is no longer sent to the AI vendor; the admin asks to enter the key again

### Fixed
- The Conversations, Requests and Insights pages were blank on sites with plain permalinks
- A page added to the excluded pages stayed searchable until the next index rebuild
- A link label could be replaced in the middle of a sentence
- Pages built with Divi, WPBakery or Avada lost all their text in the knowledge index; the text and the accordion titles are now indexed

## [1.0.0] - 2026-10-09

### Added
- Initial release
