# WordPress.org SVN Assets

This folder holds the plugin assets deployed to the WordPress.org SVN `assets/` directory.
They are **not** included in the plugin zip — they are deployed separately by the `deploy-wp-org.yml` workflow.

## Required files

| File | Size | Notes |
|------|------|-------|
| `icon-128x128.png` | 128×128 px | Plugin icon (low-res) |
| `icon-256x256.png` | 256×256 px | Plugin icon (high-res / retina) |
| `icon.svg` | any | Vector icon (takes priority over PNGs if present) |
| `banner-772x250.png` | 772×250 px | Plugin banner (standard) |
| `banner-1544x500.png` | 1544×500 px | Plugin banner (retina) |
| `screenshot-1.png` | any | Matches `== Screenshots ==` item 1 in readme.txt |
| `screenshot-2.png` | any | Matches `== Screenshots ==` item 2 in readme.txt |

## Notes

- Icons and banners must be PNG or SVG. JPG is not accepted.
- Screenshots are numbered to match the list in `readme.txt` under `== Screenshots ==`.
- Add or update `readme.txt` screenshot descriptions when adding new screenshots.
- These files are deployed to `https://plugins.svn.wordpress.org/lax-abilities-toolkit/assets/`
  by the `deploy-wp-org.yml` workflow whenever a version tag is pushed.
