# Bulk Watermark Manager

A WordPress plugin for applying watermarks to images in your Media Library — individually or in bulk. Features include real-time preview, adjustable watermark positioning and opacity, automatic backup of originals, and a clean, modern admin interface.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
- [How It Works](#how-it-works)
- [File Structure](#file-structure)
- [Hooks & Metadata](#hooks--metadata)
- [Security Notes](#security-notes)
- [Changelog](#changelog)

---

## Features

- **Bulk watermarking** — Select one or many images from your Media Library and apply a watermark to all of them in one click.
- **Live canvas preview** — See exactly how the watermark will look on your image before committing, with real-time updates as you adjust settings.
- **9-position placement** — Pin the watermark to any of 9 positions: top/middle/bottom × left/center/right.
- **Adjustable size** — Control watermark size as a percentage (5–80%) of the base image width.
- **Opacity control** — Set watermark transparency from 10% to 100%.
- **Padding control** — Define the distance from the image edge (0–60 px).
- **Automatic backup** — Optionally save a `_orig` copy of each image before modifying it.
- **Smart filtering** — Filter images by upload month or by watermark status (watermarked / not watermarked).
- **Search** — Find images by title/filename with a debounced search field.
- **Paginated grid** — Loads 32 images at a time with a "Load more" button to keep the interface fast.
- **Progress tracking** — A progress bar and per-image log show real-time status during batch processing.
- **Watermark status badge** — A green checkmark badge overlays images that have already been watermarked.
- **WordPress Media Picker** — Watermark images are selected using the native WordPress Media Library picker.

---

## Requirements

| Requirement | Details |
|---|---|
| **WordPress** | 5.0 or higher |
| **PHP** | 7.4 or higher |
| **PHP GD extension** | Required for server-side image processing (`imagecreatefromjpeg`, `imagecopy`, etc.) |
| **User capability** | `upload_files` (typically Editors and Administrators) |

### Checking the GD extension

```bash
php -m | grep gd
```

Or add this to a temporary PHP file:

```php
<?php phpinfo(INFO_MODULES);
```

If GD is missing, install it:

```bash
# Debian / Ubuntu
sudo apt install php-gd

# CentOS / AlmaLinux
sudo dnf install php-gd

# macOS (Homebrew)
brew install php && brew services restart php
```

---

## Installation

### Method 1: Upload via WordPress Admin

1. Download or clone this repository.
2. Zip the plugin folder.
3. Go to **Plugins → Add New → Upload Plugin**.
4. Upload the zip file and click **Install Now**.
5. Activate the plugin.
6. Navigate to **Media → Watermark Manager**.

### Method 2: FTP / File Manager

1. Upload the `bulk-watermark-manager` folder to `/wp-content/plugins/`.
2. Go to **Plugins** in your WordPress admin and activate **Bulk Watermark Manager**.
3. Navigate to **Media → Watermark Manager**.

### Method 3: WP-CLI

```bash
wp plugin install --activate /path/to/bulk-watermark-manager
```

---

## Usage

### Step 1 — Open the Plugin

In your WordPress admin, go to **Media → Watermark Manager**.

### Step 2 — Select Images

- Click individual images in the grid to select or deselect them.
- Use **Select All** / **Deselect** in the toolbar for quick bulk selection.
- The counter in the header updates in real time.

### Step 3 — Filter & Search (Optional)

- Use the search box to find images by title or filename.
- Filter by **upload month** or **watermark status** using the dropdowns.

### Step 4 — Configure Watermark

1. Click **Set Watermark (N)**.
2. In the configuration panel:
   - Click **Choose from Media** to pick a watermark image.
   - Choose a **position** from the 3×3 grid.
   - Adjust **size**, **opacity**, and **padding** with the sliders.
   - Toggle **Save originals** if you want `_orig` backup copies.

### Step 5 — Preview

The panel shows a live canvas preview based on the first selected image. Adjust sliders and watch the preview update instantly.

### Step 6 — Apply

Click **Apply to N images**. A progress overlay tracks each image through completion, showing a success/error log per item.

---

## How It Works

### Image Loading (AJAX)

When the page loads or filters change, the front-end sends an AJAX request to `wp_ajax_bwm_load_images`. The server queries `WP_Query` for image attachments, optionally filtered by search term, upload month, or watermark metadata. Results are paginated at 32 per page.

### Watermark Application (AJAX)

Each selected image is processed individually via `wp_ajax_bwm_apply_watermark`:

1. The base image and watermark image are loaded using PHP GD functions.
2. The watermark is resized proportionally to the configured size percentage.
3. Opacity is applied via pixel-level alpha manipulation on the resized watermark.
4. The watermark is composited onto the base image at the calculated position.
5. The modified image is saved back to the original file (JPEG 92%, PNG 6, WebP 90%).
6. `_bwm_watermarked` and `_bwm_wm_id` post meta are set on the attachment.
7. `wp_update_attachment_metadata` regenerates WordPress image sizes.

### Preview Rendering (Client-side Canvas)

Before applying, the browser draws a live preview on an HTML `<canvas>` element using the Canvas 2D API. This is purely cosmetic and does not affect the server-side processing.

---

## File Structure

```
bulk-watermark-manager/
├── bulk-watermark-manager.php   # Main plugin file — admin menu, AJAX handlers, rendering
├── assets/
│   ├── script.js                # Front-end: state management, grid, preview, AJAX batching
│   └── style.css                # Admin UI styles — header, toolbar, grid, panel, progress
└── README.md                    # This file
```

### `bulk-watermark-manager.php`

- Defines plugin constants (`BWM_VERSION`, `BWM_DIR`, `BWM_URL`).
- Registers the admin menu item under **Media**.
- Enqueues `style.css` and `script.js` only on the plugin's page.
- Renders the full admin UI (header, toolbar, image grid, watermark panel, progress overlay).
- Handles two AJAX actions:
  - `bwm_load_images` — paginated image listing.
  - `bwm_apply_watermark` — server-side watermark compositing.
- Contains the helper function `bwm_parse_position()`.

### `assets/script.js`

- Manages client-side state (selected images, filters, watermark settings).
- Binds event listeners for selection, filtering, sliders, and the media picker.
- Implements debounced search.
- Draws the live canvas preview.
- Sends watermark requests sequentially (one at a time) to avoid server overload.

### `assets/style.css`

- Custom properties for the plugin's green/navy color palette.
- Styles for the gradient header, toolbar, square image grid, selection states.
- Watermark configuration panel with two-column layout (settings + preview).
- Progress overlay with animated progress bar and log area.
- Loading skeleton shimmer animation.

---

## Hooks & Metadata

### Post Meta

The plugin stores metadata on each watermarked attachment:

| Meta Key | Type | Description |
|---|---|---|
| `_bwm_watermarked` | `int (0/1)` | Whether the image has been watermarked |
| `_bwm_wm_id` | `int` | WordPress attachment ID of the watermark image used |

### AJAX Actions

| Action | Callback | Description |
|---|---|---|
| `bwm_load_images` | `wp_ajax_bwm_load_images` | Fetch paginated image list |
| `bwm_apply_watermark` | `wp_ajax_bwm_apply_watermark` | Apply watermark to one image |

### Nonce

All AJAX requests are secured with a WordPress nonce (`bwm_nonce`) verified via `wp_verify_nonce`. The nonce is created with `wp_create_nonce('bwm_nonce')` and localized as `BWM.nonce`.

---

## Security Notes

- All AJAX endpoints verify the WordPress nonce.
- `current_user_can('upload_files')` is checked before processing watermarks.
- All user inputs are sanitized: `sanitize_text_field()`, `intval()`, `floatval()`.
- File existence and MIME type are validated server-side before any GD operations.
- The plugin only runs when `ABSPATH` is defined, preventing direct access.

---

## Changelog

### 1.0.0
- Initial release.
- Bulk watermark application to Media Library images.
- Live canvas preview.
- 9-position placement with size, opacity, and padding controls.
- Original image backup (`_orig` suffix).
- Search, month filter, and watermark-status filter.
- Watermark status badge on processed images.
- Progress bar and per-image error log.

---

## License

GPL-2.0+
