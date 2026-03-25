# Simple Gallery Content Plugin for Joomla

A lightweight Joomla! content plugin that renders image galleries from a folder using a simple tag syntax.

The plugin scans article content for `{simplegallery ...}` tags and replaces them with a responsive image gallery, including automatic thumbnail generation and optional Lightbox support (via JCE MediaBox or similar extensions).

---

## Features

- Simple tag-based usage inside Joomla! articles
- Automatic thumbnail generation and caching
- Configurable gallery layout (columns, thumbnail sizes)
- Sorting options (name, date, random)
- Caption support (from filename)
- Lightweight and dependency-free (except optional Lightbox extension)
- CSS loaded from external file for easy customization

---

## Usage

Insert the gallery tag into your article:

```text
{simplegallery folder="vacation/spain"}
```

### Examples

By providing arguments for the tag, the defaults from the plugin settings can be overridden:

```text
{simplegallery folder="vacation/spain" layout="grid" columns="4" sort="date" sortorder="descending"}
{simplegallery folder="vacation/spain" layout="slider" width="640" height="480" showcaptions}
```

---

## Folder Structure

The `folder` parameter is relative to Joomla's `image/stories` directory.

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

| Parameter      | Description                                  |
|----------------|----------------------------------------------|
| `columns`      | Number of thumbnail columns                  |
| `width`        | Max. width of thumbnails (px)                |
| `height`       | Max. height of thumbnails (px)               |
| `sort`         | Sorting method: `filename`, `date`, `random` |
| `sortorder`    | `ascending` or `descending`                  |
| `layout`       | Layout template to use: `grid`, `slider`     |
| `showcaptions` | Set to display image captions                |

---

## Defaults & Fallback Behavior

If a parameter is **not specified in the tag**, the plugin uses the default value configured in the plugin settings.

This applies to all parameters (columns, sorting, sizes, etc.).

---

## Captions

- Captions are derived from the image filename
- Filename is cleaned (underscores and dashes replaced with spaces, safe HTML characters and escaping)

Example:

```text
my_holiday-photo.jpg → "My Holiday Photo"
```

---

### Caption sidecar files

Simplegallery can optionally read captions from text files placed next to the image.

For an image named:

`IMG_12345.jpg`

the plugin checks for:

- `IMG_12345.jpg.txt`

Resolution order:

1. `.txt` sidecar → rendered as plain text
2. no sidecar → caption is generated from the image filename

If a sidecar file exists but is empty, the caption is suppressed completely.

Example:

- `IMG_12345.jpg.txt`  
  Contains plain text caption content. Line breaks are preserved.

### HTML captions in .txt files

You can include HTML in caption files by adding a special prefix.

Example:

```html
!HTML
<strong>Fence post</strong><br>
<em>Neighbour's property</em>
```

If the first line of the `.txt` file is `!HTML`, the remaining content is rendered as raw HTML.

Otherwise, the file is treated as plain text.

---

## Thumbnail Handling

- Thumbnails are generated automatically on first use
- Stored in a cache directory (inside Joomla’s filesystem)
- Reused on subsequent page loads
- Improves performance significantly for large galleries

---

## Lightbox Support

The plugin itself does not implement a Lightbox.

It relies on external extensions such as:

- **System - JCE MediaBox 2**

If installed and enabled, clicking an image will open it in a Lightbox automatically.

This will work with all Lightbox extensions that work with `<a>` tags with `rel="Lightbox"`.

---

## CSS Customization

All styling is located in:

```text
media/plg_content_simplegallery/css/simplegallery.css
```

You can freely modify this file to:

- Change layout appearance
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
- No EXIF-based sorting (currently filename/date/random only)

---

## License

MIT License (or whatever you choose)

---

## Author

Frank Willeke
