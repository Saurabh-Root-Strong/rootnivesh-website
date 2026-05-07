# App Icons

Place your generated icons here for safe-keeping. The actual app icons live in `apk/android/app/src/main/res/mipmap-*` after you generate them.

## Required source files (you create these once)

| File | Size | Purpose |
|---|---|---|
| `icon-source.png` | 1024×1024 | Master icon — used to generate launcher icons |
| `icon-playstore.png` | 512×512 | Uploaded to Play Console as the app icon |
| `splash-source.png` | 2732×2732 | Splash screen — logo centered on navy `#05101F` |
| `feature-graphic.png` | 1024×500 | Play Store header banner (no text) |

## Quick recipe (5 minutes)

1. Take `images/logo.png` from the website root (or a higher-res version)
2. Open https://www.canva.com or any image editor
3. Create a 1024×1024 canvas, fill with navy `#05101F`, place the logo centered, leave 10–15% padding all sides
4. Export as PNG → save as `icon-source.png` here
5. Open https://romannurik.github.io/AndroidAssetStudio/icons-launcher.html
6. Upload `icon-source.png` → adjust foreground/background → Download ZIP
7. Unzip → copy the `res/mipmap-*` folders into `apk/android/app/src/main/res/`

For the Play Store icon (512×512): just resize `icon-source.png` to 512×512 and save as `icon-playstore.png`.

## DO NOT commit your final icons to git unless they're public-final

Keep work-in-progress design files (PSD, AI, Figma exports) out of the repo. The PNGs that ship in the APK go into `apk/android/app/src/main/res/` and ARE committed (Capacitor expects them there).
