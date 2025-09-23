# Angie Clone - WordPress plugin (Git-ready)

This repo contains a single-file WordPress plugin plus admin assets that provide a demo "agent" UI. It accepts a natural-language prompt, previews a plan, and applies it. The Elementor page creation tries to sideload an image, and if sideload fails (common on local dev), it falls back to inserting an `<img>` as HTML in a Text Editor widget so you can see the image in the editor and frontend.

## Usage
1. Copy the `angie-clone` folder to `wp-content/plugins/` or upload the plugin zip.
2. Activate the plugin in WP Admin → Plugins.
3. Ensure Elementor is active for page creation features.
4. Go to WP Admin → Angie Clone and enter a prompt such as:
   > Create a new Elementor page with heading "AI for WordPress" and image https://via.placeholder.com/800x400
5. Click **Preview Plan**, then **Apply Plan** to create content.

## Files
- `angie-clone.php` — Main plugin file (this repo's plugin).
- `admin.js` — Admin UI JS responsible for calling the preview/apply REST endpoints.
- `admin.css` — Small admin CSS used on the plugin page.
