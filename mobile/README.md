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

## Native push notifications

The Capacitor app uses Firebase Cloud Messaging (FCM), while normal browsers continue to use Web Push/VAPID.

1. Create or open a Firebase project, add an Android app with package name `com.davaorentzone.app`, and download `google-services.json`.
2. Save that client file locally as `mobile/android/app/google-services.json`. It is intentionally ignored by Git, so copy it onto any computer that builds the APK.
3. In Firebase project settings, open **Service accounts**, generate a new private key, and upload the downloaded service-account JSON to a private directory on the Laravel server outside `public_html`.
4. Configure the production Laravel `.env` with the Firebase project ID and the absolute private path to that server credential file:

```dotenv
FIREBASE_PROJECT_ID=your-firebase-project-id
GOOGLE_APPLICATION_CREDENTIALS=/home/your-hostinger-user/firebase/davao-rent-zone-service-account.json
```

Never place the service-account JSON in `public_html`, the downloadable APK, or Git. After deploying, run the database migration and refresh Laravel's configuration cache. Rebuild and reinstall the APK because `google-services.json` is compiled into the Android app.

## Release build

Do not commit a signing key or its passwords. Create a private Android signing key, configure it outside Git, and generate a signed release APK before distributing the app to users. Users who install an APK directly must permit installation from the browser or file manager they use to open it.

## Social sign-in

Google and Facebook authentication opens in Android's secure browser tab because those providers can reject embedded web views. Before opening it, the app prepares a short-lived, single-use handoff token. When the browser closes or the app resumes, the app checks that pending handoff and completes sign-in even when Android blocks the automatic app link. The browser return page also provides an **Open Davao Rent Zone** button as a fallback.

Deploy the Laravel routes, return page, and compiled `public/js/capacitor-android-v1.js` before distributing a newly built APK so the server and native shell use the same handoff flow.
