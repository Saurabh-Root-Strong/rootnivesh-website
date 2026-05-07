# RootNivesh APK — Build Setup

Capacitor wraps the live website (rootnivesh.in) in an Android shell. Every push to the website auto-flows to the app — no APK rebuild needed for content changes. APK rebuilds are only needed when:
- You change the app icon, name, splash, or version
- Capacitor or its plugins are updated
- You add a new native plugin

## ONE-TIME SETUP ON YOUR LAPTOP

You only do these steps **once** when you first sit down at the laptop. After that, building a new APK is just two commands.

### Step 1 — Install prerequisites

Install in this order. Each is a one-time install:

| Tool | Version | Where to download |
|---|---|---|
| **Node.js** | 18 LTS or higher | https://nodejs.org/ — pick the LTS download |
| **Java JDK** | 17 (Capacitor 6 needs JDK 17, not 21) | https://adoptium.net/temurin/releases/?version=17 |
| **Android Studio** | Latest | https://developer.android.com/studio |
| **Git** | Any recent version | https://git-scm.com/ |

After installing Android Studio:
1. Open it once
2. Go through the setup wizard (it downloads SDKs)
3. Tools → SDK Manager → make sure **Android 14 (API 34)** is installed
4. Tools → SDK Manager → SDK Tools tab → make sure **Android SDK Build-Tools** and **Android SDK Platform-Tools** are checked

### Step 2 — Set environment variables

Windows: search "Environment Variables", click Edit the system environment variables → Environment Variables button.

Add to `Path`:
- `C:\Users\YOU\AppData\Local\Android\Sdk\platform-tools`
- `C:\Users\YOU\AppData\Local\Android\Sdk\cmdline-tools\latest\bin`

Add new variables:
- `ANDROID_HOME` = `C:\Users\YOU\AppData\Local\Android\Sdk`
- `JAVA_HOME` = `C:\Program Files\Eclipse Adoptium\jdk-17.x.x-hotspot` (your installed JDK 17 path)

Restart any open terminals after this.

### Step 3 — Clone the repo and install

```powershell
# Clone the website repo (you already have access)
git clone https://github.com/Saurabh-Root-Strong/rootnivesh-website.git
cd rootnivesh-website\apk

# Install npm dependencies (Capacitor + plugins)
npm install
```

### Step 4 — Initialize the Android project

```powershell
# This creates the android/ folder with the native Android Studio project
npm run init-android
```

When asked "Would you like to update Android Plugin to the latest?" → **yes**.

### Step 5 — Verify everything is good

```powershell
npm run doctor
```

Should print Capacitor / Android health checks. If it complains about missing Android SDK, double-check Step 2 environment variables.

### Step 6 — Add app icons

You need icons in multiple Android sizes. Easiest way:

1. Make a 1024×1024 PNG of the RootNivesh logo with a solid background (transparent backgrounds look bad on some Android launchers)
2. Open https://romannurik.github.io/AndroidAssetStudio/icons-launcher.html
3. Upload your 1024×1024 PNG
4. Set foreground color to gold (#C9A84C) if needed
5. Click Download ZIP
6. Unzip and copy the `res/` folder contents into `apk/android/app/src/main/res/` (it'll merge folders like `mipmap-mdpi`, `mipmap-hdpi`, `mipmap-xhdpi`, `mipmap-xxhdpi`, `mipmap-xxxhdpi`)

### Step 7 — Splash screen (optional but recommended)

1. Make a 2732×2732 PNG with the logo centered on the navy `#05101F` background
2. Use https://romannurik.github.io/AndroidAssetStudio/icons-launcher.html — same flow
3. Save as `splash.png` and copy into `apk/android/app/src/main/res/drawable/`

### Step 8 — Update Android Manifest (one-time)

Open `apk/android/app/src/main/AndroidManifest.xml` and verify:
- `android:label="RootNivesh"` is set on the `<application>` tag
- INTERNET permission is listed (Capacitor adds this automatically)

## EVERY-TIME WORKFLOW (after one-time setup)

To build a new APK:

```powershell
cd rootnivesh-website\apk
npm run build      # syncs config + opens Android Studio
```

This opens Android Studio with the project. Then:

### For testing (debug APK)
1. Plug in your Android phone with USB debugging on (Settings → Developer options → USB debugging)
2. In Android Studio: top-right device dropdown → pick your phone
3. Click the green ▶ Run button → APK installs and launches

### For Play Store (signed AAB)
1. Build → Generate Signed Bundle / APK → choose **Android App Bundle (AAB)**
2. First time: create a new keystore — **save the .jks file and password somewhere safe** (lose it = locked out of updates forever)
3. Pick "release" build variant
4. Wait for build → AAB file appears in `apk/android/app/release/`
5. Upload that .aab to Play Console

## PLAY STORE LISTING

See `playstore-listing.md` for ready-to-paste descriptions, keywords, and a feature graphic spec.

## VERSIONING

Each Play Store release needs a higher `versionCode` and a new `versionName`.

Edit `apk/android/app/build.gradle`:
```gradle
versionCode 2
versionName "1.0.1"
```

## TROUBLESHOOTING

| Problem | Fix |
|---|---|
| `cap: command not found` | Run from `apk/` folder where node_modules is |
| `JAVA_HOME is not set` | Re-do Step 2, restart terminal |
| Gradle build fails with "Could not find Android SDK" | Check `ANDROID_HOME` in env vars |
| App opens to blank screen | rootnivesh.in is offline OR your phone has no internet — falls back to the offline page in `apk/www/` |
| White flash on app launch | Splash screen not configured — see Step 7 |
| Play Store rejects "thin wrapper" | Add native push notifications (we can add `@capacitor/push-notifications` plugin) |

## KEEPING DEPENDENCIES UPDATED

```powershell
npm outdated
npm update
npx cap sync
```

Major Capacitor version bumps (e.g. v6 → v7) need careful migration — check Capacitor's release notes first.
