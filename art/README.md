# Art

Branded assets for the package. This folder is `export-ignore`d in `.gitattributes`, so nothing here ships in the Composer dist tarball.

## Brand

- Accent: `#14B8A6` (teal)
- Dark surface: `#0F172A`
- Off-black background gradient: `#0A0E1A` → `#060912`
- Type: Inter (display) and JetBrains Mono / ui-monospace (code)

## Files

### Social preview

- `octane-og.svg` — canonical source for the 1280x640 GitHub social preview.
- `octane-og.html` — render wrapper that fixes the SVG to 1280x640 for headless Chrome.
- `octane-og.png` — rendered output, uploaded to **Settings -> Social preview**.

### Logo

- `logo-mark.svg` — canonical mark. Stethoscope ring + lightning bolt. Reads as doctor + octane.
- `logo-mark.html` — render wrapper that fixes the mark to 512x512.
- `logo-mark.png` — 512x512 transparent PNG. Use as repo avatar, favicon source, or app icon mark.
- `logo-wordmark.html` — render wrapper for the horizontal wordmark (mark + "Octane Doctor"). Accent on "Octane".
- `logo-wordmark.png` — 900x240 transparent PNG. Use in README headers, docs, slides.

## Regenerate PNGs

```bash
# Mark
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
  --headless=new --disable-gpu --hide-scrollbars --default-background-color=00000000 \
  --window-size=512,512 \
  --screenshot=art/logo-mark.png \
  "file://$(pwd)/art/logo-mark.html"

# Wordmark
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
  --headless=new --disable-gpu --hide-scrollbars --default-background-color=00000000 \
  --window-size=900,240 \
  --screenshot=art/logo-wordmark.png \
  "file://$(pwd)/art/logo-wordmark.html"

# Social preview
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
  --headless=new --disable-gpu --hide-scrollbars \
  --window-size=1280,640 \
  --screenshot=art/octane-og.png \
  "file://$(pwd)/art/octane-og.html"
```

On Linux replace the Chrome path with `chromium --headless ...`.

After regenerating the social preview, upload the PNG to GitHub: repo **Settings -> Social preview -> Edit**.
