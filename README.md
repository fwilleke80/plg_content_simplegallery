# SimpleGallery (Joomla Content Plugin)

A lightweight Joomla content plugin that renders image galleries from a folder using a simple tag.

It scans a folder inside Joomla’s `/images` directory, generates thumbnails, and outputs a clean gallery with clickable images (compatible with lightbox extensions like JCE MediaBox).

---

## Features

- Simple `{simplegallery}...{/simplegallery}` syntax
- Automatically reads images from a folder
- Generates thumbnails on demand
- Outputs a responsive gallery grid
- Uses standard `<a>` links (works with external lightbox plugins)
- Clean separation of PHP and CSS
- No heavy dependencies

---

## Installation

1. Zip the plugin folder (or use the provided ZIP)
2. In Joomla backend:
   - Go to **System → Install → Extensions**
   - Upload the ZIP file
3. Enable the plugin:
   - Go to **Extensions → Plugins**
   - Search for **"Content - SimpleGallery"**
   - Enable it

---

## Usage

Insert the gallery tag into any Joomla article:

```text
{simplegallery}my-folder{/simplegallery}
```

### Folder Location

The folder must be located inside:

```txt
/images/stories/my-folder
```

Example:

```txt
/images/stories/urlaub/spanien
```

Used as:

```text
{simplegallery}urlaub/spanien{/simplegallery}
```

---

## Supported Images

The plugin automatically processes common formats:

- JPG / JPEG
- PNG
- GIF
- WebP (if supported by server)

---

## How It Works

1. Joomla triggers `onContentPrepare`
2. The plugin scans for `{simplegallery}` tags
3. It reads all images from the specified folder
4. Thumbnails are generated (cached)
5. HTML output is injected into the article

---

## Lightbox Support

This plugin does **not include its own lightbox**.

Instead, it generates standard links:

```html
<a href="full-image.jpg">
```

If you use an extension like:

- **JCE MediaBox**
- or any lightbox that reacts to `<a>` tags with `rel="Lightbox"`

...images will automatically open in a lightbox. Otherwise, they will simply open as a new page.

---

## 🎨 Styling

CSS is located here:

```css
/media/plg_content_simplegallery/css/simplegallery.css
```

You can freely modify it.

---

## 📁 Project Structure

```txt
plg_content_simplegallery/
├── src/
│   └── Extension/
│       └── Simplegallery.php
├── media/
│   └── css/
│       └── simplegallery.css
├── language/
├── services/
├── simplegallery.xml
```

---

## Debugging

The plugin uses Joomla logging:

- Category: `plg_content_simplegallery`

Enable debug logging in Joomla to inspect behavior.

---

## Notes

- Folder paths are **relative to `/images/stories/`**
- No recursion: only files directly in the folder are used
- Thumbnails are generated automatically (ensure write permissions)
- Works only on frontend (`site` application)

---

## License

GNU General Public License v2 or later.

---

## Copyright

(c) 2026 by Frank Willeke
