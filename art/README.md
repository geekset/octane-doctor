# Art

Branded assets for the package. This folder is `export-ignore`d in `.gitattributes`, so nothing here ships in the Composer dist tarball.

## `octane-og.png`

GitHub social preview image (1280x640 PNG, under 1MB). Uploaded to the repo via **Settings -> Social preview**. Source files (`octane-og.svg` and `octane-og.html`) are kept alongside so future edits regenerate the PNG deterministically.

### Regenerate the PNG

```bash
/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome \
  --headless=new \
  --disable-gpu \
  --hide-scrollbars \
  --window-size=1280,640 \
  --screenshot=art/octane-og.png \
  "file://$(pwd)/art/octane-og.html"
```

On Linux replace the Chrome path with `chromium --headless ...`.

After regenerating, upload the PNG to GitHub: repo **Settings -> Social preview -> Edit**.
