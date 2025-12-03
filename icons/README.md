# TaskMesh PWA Icons Setup

## Quick Setup - Generate Icons

To generate all required PWA icons, use one of these methods:

### Option 1: Online Tool (Recommended)
1. Visit https://realfavicongenerator.net/
2. Upload your logo/icon (512x512 PNG recommended)
3. Configure iOS, Android, and Windows settings
4. Download the generated package
5. Extract all files to `/TaskMesh/icons/` folder

### Option 2: Using ImageMagick (Command Line)
```bash
# Install ImageMagick first
# Create a base 512x512 icon.png in /icons/ folder, then run:

cd c:\xampp\htdocs\TaskMesh\icons

# Generate all sizes
magick icon.png -resize 72x72 icon-72x72.png
magick icon.png -resize 96x96 icon-96x96.png
magick icon.png -resize 128x128 icon-128x128.png
magick icon.png -resize 144x144 icon-144x144.png
magick icon.png -resize 152x152 icon-152x152.png
magick icon.png -resize 192x192 icon-192x192.png
magick icon.png -resize 384x384 icon-384x384.png
magick icon.png -resize 512x512 icon-512x512.png

# Generate maskable icons (with safe zone padding)
magick icon.png -resize 160x160 -gravity center -extent 192x192 -background "#667eea" icon-maskable-192x192.png
magick icon.png -resize 426x426 -gravity center -extent 512x512 -background "#667eea" icon-maskable-512x512.png
```

### Option 3: PWA Asset Generator (Node.js)
```bash
npm install -g pwa-asset-generator

# Generate from base icon
pwa-asset-generator icon-base.png ./icons --background "#667eea" --padding "10%"
```

## Required Icon Sizes

- **72x72** - Chrome on Android
- **96x96** - Chrome on Android, Edge
- **128x128** - Chrome Web Store
- **144x144** - Windows 8/10
- **152x152** - iPad
- **192x192** - Chrome on Android (primary)
- **384x384** - Chrome splashscreen
- **512x512** - Chrome on Android (high-res), iOS

## Maskable Icons

Maskable icons allow your icon to look great across different device shapes:
- **192x192 maskable** - Android adaptive icons
- **512x512 maskable** - High-res adaptive icons

**Design Guidelines:**
- Keep important content in the center 80% (safe zone)
- Use solid background color matching theme
- Avoid text near edges

## Testing Your Icons

1. Open Chrome DevTools > Application > Manifest
2. Check if all icons are loading correctly
3. Test installation on mobile device
4. Verify icon appears correctly on home screen

## Current Status

✅ manifest.json configured
✅ SVG template created
⏳ PNG icons need to be generated

Use the SVG file (`icon-192x192.svg`) as a starting point or replace with your custom design.
