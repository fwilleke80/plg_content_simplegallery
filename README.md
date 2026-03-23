# Simple Gallery Content Plugin for Joomla

A lightweight Joomla! content plugin that renders image galleries from a folder using a simple tag syntax.

The plugin scans article content for `{simplegallery ...}` tags and replaces them with a responsive image gallery, including automatic thumbnail generation and optional Lightbox support (via JCE MediaBox or similar extensions).

---

## Features

- Simple tag-based usage inside Joomla! articles
- Automatic thumbnail generation and caching
- Configurable gallery layout (columns, spacing, sizes)
- Sorting options (name, date)
- Caption support (from filename)
- Lightweight and dependency-free (except optional Lightbox extension)
- CSS loaded from external file for easy customization

---

## Usage

Insert the gallery tag into your article:

```text
{simplegallery folder="vacation/spain"}
```

### Example

```text
{simplegallery folder="vacation/spain" layout="grid" columns="4" sort="date" sortorder="descending"}
```

---

## Folder Structure

The `folder` parameter is relative to Joomla’s `image/stories` directory.

```text
/images/stories/<folder>
```

Example:

```text
{simplegallery folder="holiday/spain"}
```

will resolve to:

```text
/images/stories/holiday/spain
```

---

## Tag Parameters

All parameters are optional except `folder`.

### Required

| Parameter | Description                                               |
|-----------|-----------------------------------------------------------|
| `folder`  | Path to the image folder (relative to `/images/stories`)  |

---

### Optional

| Parameter      | Description                              |
|----------------|------------------------------------------|
| `columns`      | Number of thumbnail columns              |
| `width`        | Max. width of thumbnails (px)            |
| `height`       | Max. height of thumbnails (px)           |
| `sort`         | Sorting method: `filename`, `date`.      |
| `sortorder`    | `asc` or `desc`                          |
| `layout`       | Layout template to use: `grid`, `slider` |
| `showcaptions` | Set to display image captions            |

---

## Defaults & Fallback Behavior

If a parameter is **not specified in the tag**, the plugin uses the default value configured in the plugin settings.

This applies to all parameters (columns, sorting, sizes, etc.).

---

## Thumbnail Handling

- Thumbnails are generated automatically on first use
- Stored in a cache directory (inside Joomla’s filesystem)
- Reused on subsequent page loads
- Improves performance significantly for large galleries

---

## Captions

- Captions are derived from the image filename
- Filename is cleaned (underscores and dashes replaced with spaces)

Example:

```text
my_holiday-photo.jpg → "my holiday photo"
```

---

## Lightbox Support

The plugin itself does not implement a Lightbox.

It relies on external extensions such as:

- **System - JCE MediaBox 2**

If installed and enabled, clicking an image will open it in a Lightbox automatically.

---

## CSS Customization

All styling is located in:

```text
media/plg_content_simplegallery/css/simplegallery.css
```

You can freely modify this file to:

- Change layout appearance
- Adjust spacing
- Customize hover effects
- Integrate with your site design

---

## How It Works (Technical Overview)

1. `onContentPrepare()` scans article text
2. Detects `{simplegallery ...}` tags
3. Parses parameters from the tag
4. Resolves folder and loads images
5. Applies sorting and configuration
6. Generates thumbnails if needed
7. Renders HTML output
8. Replaces the tag in the article

---

## Limitations

- Only supports local image folders
- No built-in slider or carousel (grid layout only for now)
- No EXIF-based sorting (currently filename/date only)

---

## Planned Improvements

- Template-based rendering system (grid, slider, carousel)
- More flexible caption handling
- Optional lazy loading
- Better Lightbox integration

---

## License

MIT License (or whatever you choose)

---

## Author

Frank Willeke
