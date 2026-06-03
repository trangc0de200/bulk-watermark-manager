# Bulk Watermark Manager

<!-- Markdown HTML comment used to hide line below from table of contents auto-generators: x -->
<!-- prettier-ignore -->
<p align="center">
  <a href="https://wordpress.org/plugins/bulk-watermark-manager/">
    <img src="https://img.shields.io/badge/WordPress-5.0%2B-blue?style=for-the-badge&logo=wordpress" alt="WordPress" />
  </a>
  <a href="https://www.php.net/releases/7_4.php">
    <img src="https://img.shields.io/badge/PHP-7.4%2B-8892BF?style=for-the-badge&logo=php" alt="PHP" />
  </a>
  <a href="https://www.gnu.org/licenses/gpl-2.0.html">
    <img src="https://img.shields.io/badge/License-GPL%202.0-green?style=for-the-badge" alt="License" />
  </a>
  <a href="https://github.com/TrangDev/bulk-watermark-manager/releases">
    <img src="https://img.shields.io/badge/Version-1.0.0-orange?style=for-the-badge" alt="Version" />
  </a>
</p>

> Batch watermark images in your WordPress Media Library with a modern, intuitive interface. Select images, configure your watermark, preview the result, and apply — all without leaving WordPress admin.

---

## Screenshots

| Main Interface | Watermark Configuration | Live Preview |
|:---:|:---:|:---:|
| ![Main Interface](https://via.placeholder.com/400x300/1a5f3c/ffffff?text=Main+Interface) | ![Configuration Panel](https://via.placeholder.com/400x300/0d395e/ffffff?text=Configuration) | ![Live Preview](https://via.placeholder.com/400x300/27ae60/ffffff?text=Live+Preview) |

> _Screenshots coming soon — the plugin is actively maintained._

---

## Table of Contents

- [Features](#features)
- [Quick Start](#quick-start)
- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
- [Technical Reference](#technical-reference)
- [Hooks & Metadata](#hooks--metadata)
- [Security](#security)
- [Contributing](#contributing)
- [Changelog](#changelog)
- [License](#license)

---

## Features

| Feature | Description |
|---|---|
| **Bulk Processing** | Apply watermarks to hundreds of images simultaneously |
| **Live Preview** | Canvas-based real-time preview updates as you adjust settings |
| **9 Position Anchors** | Place watermarks at any corner, edge, or center |
| **Fine-Tuned Controls** | Adjustable size (5–80%), opacity (10–100%), and edge padding (0–60px) |
| **Original Backups** | Automatically save `_orig` copies before modifying images |
| **Smart Filtering** | Filter by upload date or watermark status |
| **Search** | Find images by title or filename with debounced search |
| **Progress Tracking** | Real-time progress bar and per-image success/error log |
| **Watermark Badges** | Visual indicator on already-watermarked images |
| **WP Media Picker** | Select watermark images using the native WordPress picker |

---

## Quick Start

```
1. Install & activate the plugin
2. Go to Media → Watermark Manager
3. Select images from the grid
4. Choose a watermark image
5. Adjust position, size, opacity, and padding
6. Preview on canvas, then click Apply
```

---

## Requirements

| Dependency | Minimum Version | Notes |
|---|---|---|
| **WordPress** | 5.0 | Tested up to the latest stable release |
| **PHP** | 7.4 | GD extension must be enabled |
| **PHP GD extension** | — | Used for all server-side image operations |
| **User role** | Editor+ | Requires `upload_files` capability |

### Verify GD is installed

```bash
php -m | grep -i gd
```

Expected output contains `gd`.

If GD is missing, install it:

```bash
# Debian / Ubuntu
sudo apt install php-gd && sudo systemctl restart php-fpm

# CentOS / AlmaLinux / RHEL
sudo dnf install php-gd && sudo systemctl restart php-fpm

# macOS (Homebrew)
brew install php && brew services restart php
```

---

## Installation

### Option 1 — WordPress Admin Upload

1. Download the repository as a ZIP file.
2. Navigate to **Plugins → Add New → Upload Plugin**.
3. Upload the ZIP and click **Install Now**.
4. Activate the plugin.
5. Go to **Media → Watermark Manager**.

### Option 2 — FTP / File Manager

1. Upload the `bulk-watermark-manager` folder to `/wp-content/plugins/`.
2. Activate via **Plugins** in your WordPress admin.
3. Go to **Media → Watermark Manager**.

### Option 3 — WP-CLI

```bash
wp plugin install --activate /absolute/path/to/bulk-watermark-manager
```

---

## Usage

### Selecting Images

- **Individual selection** — Click any image in the grid to toggle its selection. A green border and checkmark indicate selected items.
- **Bulk selection** — Use **Select All** / **Deselect** in the toolbar.
- The header counter updates in real time as you select or deselect.

### Filtering & Searching

| Control | Purpose |
|---|---|
| **Search box** | Filter by image title or filename (debounced, 350ms) |
| **Date filter** | Show images uploaded in a specific month |
| **Status filter** | Show only watermarked or non-watermarked images |

### Configuring Watermarks

Click **Set Watermark (N)** to open the configuration panel:

| Setting | Range | Default | Effect |
|---|---|---|---|
| **Watermark Image** | Any image from Media Library | — | Source of the watermark overlay |
| **Position** | 9 anchors (3×3 grid) | Bottom-right | Where on the image the watermark is placed |
| **Size** | 5–80% | 25% | Watermark width as a percentage of base image |
| **Opacity** | 10–100% | 80% | Transparency level of the watermark |
| **Edge Padding** | 0–60px | 10px | Distance from the nearest image edge |
| **Save Original** | On/Off | On | Stores a `_orig` copy before overwriting |

### Previewing

The right-hand panel renders a live canvas preview using the first selected image. All slider changes reflect instantly — no save required to preview.

### Applying

Click **Apply to N images**. The progress overlay shows:

- An animated progress bar
- A running count (`N / M`)
- A scrollable log with per-image success (`OK`) or failure (`FAIL`) entries

After completion, the grid updates in place and the watermark badge appears on processed images.

---

## Technical Reference

### Image Loading Flow

```
Page Load / Filter Change
       │
       ▼
$.post( 'wp_ajax_bwm_load_images' )
       │
       ▼
WP_Query( post_type=attachment, mime_type=image, ... )
       │
       ▼
Post meta _bwm_watermarked merged into results
       │
       ▼
Paginated JSON response (32 items/page)
       │
       ▼
Grid renders with selection state
```

### Watermark Processing Flow

```
User clicks "Apply to N images"
       │
       ▼
Sequential $.post() per attachment_id
       │
       ├──► PHP GD: imagecreatefromjpeg / png / webp
       ├──► PHP GD: resize watermark proportionally
       ├──► PHP GD: alpha manipulation for opacity
       ├──► PHP GD: imagecopy onto base at position
       ├──► PHP GD: imagejpeg / imagepng / imagewebp
       ├──► update_post_meta( _bwm_watermarked )
       ├──► wp_update_attachment_metadata()
       └──► JSON response { url: "...?v=timestamp" }
```

### Supported Image Formats

| Role | JPEG | PNG | WebP |
|---|---|---|---|
| **Base image** | ✅ | ✅ | ✅ |
| **Watermark image** | ✅ | ✅ | ✅ |

---

## Hooks & Metadata

### Post Meta

| Key | Type | Description |
|---|---|---|
| `_bwm_watermarked` | `int` (0 or 1) | Whether the attachment has been watermarked |
| `_bwm_wm_id` | `int` | WordPress attachment ID of the watermark image used |

### AJAX Actions

| Action | Callback | Auth |
|---|---|---|
| `bwm_load_images` | `wp_ajax_bwm_load_images` | Nonce only |
| `bwm_apply_watermark` | `wp_ajax_bwm_apply_watermark` | Nonce + `upload_files` capability |

### Nonce

All AJAX requests include `BWM.nonce`, verified server-side with `wp_verify_nonce()`. The nonce is initialized via `wp_create_nonce('bwm_nonce')` and passed to JavaScript through `wp_localize_script()`.

---

## Security

- `ABSPATH` guard prevents direct file access.
- All AJAX endpoints verify the WordPress nonce before processing.
- `current_user_can('upload_files')` gates watermark application.
- Input sanitization: `sanitize_text_field()`, `intval()`, `floatval()` on all user-supplied values.
- MIME type and file existence validated server-side before GD operations.

---

## Contributing

Contributions are welcome. Please follow these guidelines:

1. **Fork** the repository and create a feature branch (`feature/your-feature-name`).
2. **Write clean, readable code** — follow the existing style.
3. **Test thoroughly** on a local WordPress installation before submitting.
4. **Commit with clear messages** — use conventional commit format.
5. **Open a Pull Request** against `main` with a description of your changes.

For bug reports and feature requests, open an issue on GitHub.

---

## Changelog

All notable changes are documented below. This project follows [Keep a Changelog](https://keepachangelog.com/) conventions.

### [1.0.0](https://github.com/TrangDev/bulk-watermark-manager/releases/tag/1.0.0) — 2024

#### Added
- Bulk watermark application to Media Library images with sequential AJAX processing.
- Live canvas preview with real-time slider updates.
- 9-position placement grid (top/middle/bottom × left/center/right).
- Size, opacity, and edge padding controls.
- Automatic original image backup with `_orig` suffix.
- Image search, date filter, and watermark-status filter.
- Watermark status badge on processed images.
- Progress bar with per-image success/error log.
- Native WordPress Media Library picker for watermark selection.
- Support for JPEG, PNG, and WebP base and watermark images.
- Responsive admin UI with loading skeleton and smooth transitions.

---

## License

[GPL-2.0](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html) © Trang Dev

---

<p align="center">
  Built with care for the WordPress community
</p>
