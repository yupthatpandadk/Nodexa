# Nodexa Android

Native Android client for Nodexa/Pterodactyl.

## Current features

- Connect to a Nodexa panel over HTTPS using a Client API key.
- Server list.
- Start, restart and stop server power controls.
- Root file listing.
- Backup listing.
- Database listing.
- Installed Minecraft Plugin Manager entries.
- Installed Minecraft Mod Manager entries.
- Dark Nodexa mobile UI.

The panel URL and API key are stored locally on the Android device using SharedPreferences.

## Build

```bash
gradle :app:assembleDebug
```

APK output: `app/build/outputs/apk/debug/app-debug.apk`.
