# Davao Rent Zone Android app

This directory contains the Capacitor Android wrapper for the Laravel application at `https://davaorentzone.com`.

## Requirements

- Node.js 22 or newer
- pnpm
- Android Studio with Android SDK 36
- JDK 21 (the JDK bundled with Android Studio is supported)

## Development build

```bash
pnpm install
pnpm run build
cd android
./gradlew assembleDebug
```

The debug APK is created at:

```text
android/app/build/outputs/apk/debug/app-debug.apk
```

The Android shell intentionally permits navigation only to the production Davao Rent Zone domains and disables clear-text traffic and WebView debugging. It uses the same Laravel sessions, API, and database as the website.

## Release build

Do not commit a signing key or its passwords. Create a private Android signing key, configure it outside Git, and generate a signed release APK before distributing the app to users. Users who install an APK directly must permit installation from the browser or file manager they use to open it.

## Current authentication limitation

Email/password authentication works in the Android shell. Google and Facebook can reject OAuth inside embedded web views. Add native Google and Facebook authentication with an app deep-link callback before relying on those methods in the Android build.
