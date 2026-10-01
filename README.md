# Phoca Download Schema Plugin

A Joomla system plugin that adds JSON-LD Schema.org structured data markup to the [Phoca Download](https://github.com/PhocaCz/PhocaDownload) component.

The plugin automatically generates schema markup for Phoca Download pages, helping search engines better understand downloadable files, download categories, and category listings.

## Features

* Adds Schema.org JSON-LD markup automatically to Phoca Download pages.
* Supports:

  * Individual download files
  * Individual download categories
  * Phoca Download category index pages
* Uses native Joomla events and APIs.
* Respects Joomla and Phoca Download access permissions.
* Filters unpublished, unapproved, restricted, or unavailable files from generated schema.
* Generates absolute URLs compatible with Joomla SEF routing.
* Outputs valid JSON-LD directly into the document head.

## Supported Schema Types

### Download File Pages

Generates:

```json
{
  "@type": "DigitalDocument"
}
```

Includes:

* Document name
* URL
* Category
* Description
* Publication date
* Version number

Example:

```json
{
  "@context": "https://schema.org",
  "@type": "DigitalDocument",
  "name": "Example Download",
  "url": "https://example.com/download/file",
  "category": "Category Name",
  "description": "Download description",
  "version": "1.0"
}
```

---

### Category Pages

Generates:

```json
{
  "@type": "CollectionPage"
}
```

Includes:

* Category name
* Description
* List of available downloads

The generated `ItemList` only contains files visible to the current user.

---

### Category Index Pages

Generates:

```json
{
  "@type": "CollectionPage"
}
```

Includes:

* Page title
* List of accessible Phoca Download categories

---

## Requirements

* Joomla 5.x
* Phoca Download component
* PHP 8.1+

## Installation

1. Download the plugin package.
2. Install it through:

```
Joomla Administrator → System → Install → Extensions
```

3. Enable the plugin:

```
System → Manage → Plugins
```

4. Search for:

```
Phoca Download Schema Plugin
```

5. Enable the plugin.

No configuration is required.

---

## How It Works

The plugin listens to the Joomla event:

```
onBeforeCompileHead
```

When a Phoca Download page is detected, it:

1. Confirms that the Phoca Download component is enabled.
2. Loads the required Phoca Download libraries.
3. Detects the current view:

   * `file`
   * `category`
   * `categories`
4. Builds Schema.org JSON-LD data.
5. Injects the JSON-LD markup into the page `<head>` section.

---

## Access Control

The plugin applies the same visibility rules as Phoca Download.

Generated schema excludes:

* Unpublished files
* Unapproved files
* Files outside publication dates
* Categories unavailable to the visitor
* Files restricted by Joomla viewing access levels
* Files restricted by Phoca Download user access rules

This prevents exposing private download URLs or metadata through structured data.

---

## Schema Validation

After installation, schema output can be tested using:

* Google Rich Results Test
  [https://search.google.com/test/rich-results](https://search.google.com/test/rich-results)

* Schema.org Validator
  [https://validator.schema.org/](https://validator.schema.org/)

---

## Compatibility

This plugin is designed for:

* Joomla 5
* Phoca Download 5.x

It uses Joomla namespaces and modern plugin event subscriptions.
* Added category index schema support.
* Added Joomla and Phoca Download access filtering.
