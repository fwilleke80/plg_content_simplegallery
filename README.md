# Punga Simple Gallery

Punga Simple Gallery is a lightweight Joomla! 5/6 content plugin that renders image and video galleries from folders below Joomla's `/images` directory.

It uses the existing content placeholder syntax:

```text
{simplegallery folder="holiday/spain"}
```

## Features

- Image galleries from local Joomla image folders
- Optional video-only or mixed image/video galleries
- Grid and slider layouts
- Automatic thumbnail generation and caching
- Video poster images
- Built-in image/video lightbox
- Keyboard and swipe navigation
- Lightbox information beside the media, below it, or hidden
- Optional JSON/file metadata and safe EXIF metadata in the lightbox
- Folder-wide `gallery.json` metadata
- Per-file JSON metadata sidecars
- Existing `.txt` caption sidecars remain supported
- External lightbox compatibility via `rel="Lightbox"`
- English and German language files

## Placeholder syntax

```text
{simplegallery
    folder="path/to/gallery"
    columns="4"
    width="240"
    height="180"
    showcaptions="true"
    layout="grid"
    sort="filename"
    sortorder="ascending"
    media="both"
    lightbox="builtin"
    showmetadata="true"
}
```

Only `folder` is required. Every other placeholder parameter overrides the corresponding plugin setting.

The canonical placeholder parameter names are `folder`, `columns`, `width`, `height`, `showcaptions`, `layout`, `sort`, `sortorder`, `media`, `lightbox`, and `showmetadata`. Parameter names are case-insensitive.

For compatibility and convenience, these alternative spellings are also accepted:

- `thumb_width` = `width`
- `thumb_height` = `height`
- `show_captions` = `showcaptions`
- `sort_order` = `sortorder`
- `media_types` = `media`
- `lightbox_mode` = `lightbox`
- `show_lightbox_metadata` = `showmetadata`
- `show_metadata` = `showmetadata`

## Media selection

The `media` parameter accepts:

- `images`
- `videos`
- `both`

The default remains `images` for backwards compatibility.

Supported image extensions:

```text
.jpg .jpeg .png .gif .webp
```

Supported video extensions:

```text
.mp4 .m4v .webm .ogv .ogg .mov
```

Examples:

```text
{simplegallery folder="garden" media="images"}
{simplegallery folder="garden" media="videos"}
{simplegallery folder="garden" media="both"}
```

## Lightbox modes

`lightbox` accepts:

- `builtin` — Punga Simple Gallery's integrated image/video viewer
- `external` — emits the legacy `rel="Lightbox"` links for third-party lightboxes
- `none` — ordinary links to the media files

The built-in lightbox supports:

- images and HTML5 video
- previous/next buttons
- left/right arrow keys
- Escape to close
- touch/pointer swipes
- focus trapping and focus restoration
- adjacent image preloading
- item title and description
- optional metadata rows

The plugin setting **Lightbox information** controls whether the complete information area appears beside the image/video, below it, or is hidden. This is intentionally a plugin-wide presentation setting; there is no `metadataposition` placeholder parameter.

`showmetadata="false"` remains available as a per-gallery override. It suppresses only the metadata rows, not the title or description.

## JSON metadata

Metadata is completely optional.

For metadata covering a complete folder, add:

```text
gallery.json
```

A typical file looks like this:

```json
{
    ".": {
        "title": "Garden, September 2026",
        "description": "A few pictures and videos from the garden.",
        "author": "Example Author",
        "copyright": "© 2026 Example Author",
        "location": "Braunschweig"
    },

    "some_image.jpg": {
        "title": "The garden in September",
        "description": "Looking towards the old chestnut tree.",
        "date": "2026-09-18",
        "alt": "View through the garden towards the chestnut tree"
    },

    "garden_walk.mp4": {
        "title": "Walking through the garden",
        "description": "A short walk through the rear garden.",
        "poster": "garden_walk_poster.jpg"
    }
}
```

### The `.` entry

The special `"."` entry contains metadata for the gallery/folder itself.

These values are inherited by individual items:

- `author`
- `copyright`
- `location`

Gallery `title` and `description` describe the gallery itself and are **not** inherited by items.

### Item metadata fields

Supported item properties are:

- `title`
- `description`
- `date`
- `author`
- `location`
- `copyright`
- `alt`
- `poster` (videos only)
- `camera`
- `lens`
- `exposure`
- `aperture`
- `iso`
- `focal_length`

All fields are optional.

### Per-file JSON sidecars

A media file may also have its own JSON file:

```text
some_image.jpg
some_image.jpg.json

garden_walk.mp4
garden_walk.mp4.json
```

The sidecar contains only the item object:

```json
{
    "title": "The garden in September",
    "description": "Looking towards the old chestnut tree.",
    "date": "2026-09-18",
    "author": "Example Author",
    "location": "Braunschweig",
    "copyright": "© 2026 Example Author",
    "alt": "View through the garden towards the chestnut tree"
}
```

Metadata precedence is:

```text
automatically derived values, including enabled EXIF fields
→ inheritable values from "." in gallery.json
→ matching file entry in gallery.json
→ individual filename.ext.json sidecar
```

JSON therefore overrides matching EXIF values.

## Video posters

A video poster can be specified explicitly:

```json
{
    "garden_walk.mp4": {
        "poster": "garden_walk_poster.jpg"
    }
}
```

If `poster` is omitted, the plugin looks for an automatic sidecar poster using the **complete video filename**:

```text
garden_walk.mp4.jpg
garden_walk.mp4.png
garden_walk.mp4.webp
```

Poster files used by videos are treated as auxiliary files and are not displayed as separate gallery items.

If no poster is available, the gallery uses a neutral video tile with a play symbol and file type label.

## Captions and legacy `.txt` sidecars

The existing caption sidecar format remains supported:

```text
some_image.jpg.txt
some_video.mp4.txt
```

Caption precedence for the gallery grid/slider is:

1. `.txt` sidecar
2. JSON `title`
3. title generated from filename

An empty `.txt` file suppresses the visible gallery caption completely.

Plain text captions are HTML-escaped and preserve line breaks.

Trusted HTML mode remains available by putting `!HTML` on the first line:

```html
!HTML
<strong>Fence post</strong><br>
<em>Neighbour's property</em>
```

The remaining content is then emitted as raw HTML, just as in previous plugin versions.

## Metadata display and EXIF

Metadata is intended primarily for the built-in lightbox and is independent from captions in the gallery grid/slider.

The plugin settings let you choose which metadata rows should be shown when values exist:

- date
- author
- location
- copyright
- image dimensions
- file size
- filename
- camera
- lens
- exposure time
- aperture
- ISO
- focal length

Image dimensions and file size are derived automatically. Empty values are omitted.

EXIF reading is optional and disabled by default. When enabled, JPEG files are inspected only if PHP's EXIF extension is available. Punga Simple Gallery reads a conservative set of fields: capture date, author, copyright, camera make/model, lens model, exposure time, aperture, ISO and focal length. GPS EXIF sections are deliberately not requested or exposed.

The JSON keys `date`, `author`, `copyright`, `camera`, `lens`, `exposure`, `aperture`, `iso` and `focal_length` may override those automatically read values.

`showmetadata="false"` can suppress the metadata rows for one gallery without disabling its title or description. The placement of the information area itself is controlled only in the plugin settings.

## Sorting

Supported sort modes are:

- `filename`
- `date` — filesystem modification date
- `random`

Sort order is:

- `ascending`
- `descending`

Example:

```text
{simplegallery folder="garden" sort="date" sortorder="descending"}
```

## Folder resolution

`folder` is resolved below `/images` first and then below `/images/stories` for compatibility with older installations.

For example:

```text
{simplegallery folder="holiday/spain"}
```

may resolve to:

```text
/images/holiday/spain
```

or, if that does not exist:

```text
/images/stories/holiday/spain
```

Path traversal outside Joomla's image tree is rejected.

## Thumbnail cache

Image thumbnails and video poster thumbnails are generated on demand and cached. The default cache folder is:

```text
images/.simplegallery-cache
```

It can be changed in the plugin settings.

## Plugin identity and upgrades

The visible product name is **Punga Simple Gallery**.

The Joomla plugin element remains `content/simplegallery` and the article placeholder remains `{simplegallery ...}` so the plugin upgrades the existing installation in place and existing content does not need to be changed.

## License

GNU General Public License version 2 or later. See `LICENSE`.
