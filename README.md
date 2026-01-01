# Anticipater GA4 Events

A WordPress plugin to manage and track GA4 events with an easy-to-use admin interface.

## Features

- **Event Configuration** - Create and manage GA4 events with conditions and parameters
- **Event Types** - Support for automatic, click, video, and form events
- **Conditional Triggers** - Fire events based on page views, scroll depth, device type, traffic source, and more
- **Event Log** - Debug mode with detailed event logging
- **Cookiebot Integration** - GDPR-compliant tracking with consent management
- **UTM Persistence** - Server-side UTM parameter tracking across sessions
- **Import/Export** - Backup and migrate event configurations

## Installation

1. Download the latest release zip file
2. Upload via WordPress Admin → Plugins → Add New → Upload Plugin
3. Activate the plugin
4. Configure at GA4 Events menu

## Requirements

- WordPress 5.0+
- PHP 7.4+

## GDPR Compliance

This plugin integrates with Cookiebot for consent management. Tracking only fires when the user has given statistics consent.

## Releasing a New Version

1. **Update version numbers** in three files:
   ```php
   // anticipater-ga4-events.php (header)
   * Version: 1.0.2
   
   // anticipater-ga4-events.php (constant)
   define('ANTICIPATER_GA4_VERSION', '1.0.2');
   ```
   ```json
   // composer.json
   "version": "1.0.2"
   ```

2. **Update `update.json`** with new version and changelog:
   ```json
   {
       "version": "1.0.2",
       "download_url": "https://github.com/anticipaterdotcom/wordpress-ga4/releases/download/v1.0.2/anticipater-ga4-events.zip",
       "changelog_html": "<h4>1.0.2</h4><ul><li>Your changes here</li></ul>"
   }
   ```

3. **Commit and push**:
   ```bash
   git add -A
   git commit -m "Bump version to 1.0.2"
   git push
   ```

4. **Create release zip** (from plugins directory):
   ```bash
   zip -r anticipater-ga4-events.zip anticipater-ga4-events -x "*.DS_Store" -x "*.git*"
   ```

5. **Tag and create GitHub release**:
   ```bash
   git tag v1.0.2
   git push origin v1.0.2
   gh release create v1.0.2 anticipater-ga4-events.zip --title "v1.0.2" --notes "Changelog..."
   ```

WordPress sites will automatically detect the update from `update.json`.

## License

Proprietary - © 2026 Anticipater. All rights reserved.
