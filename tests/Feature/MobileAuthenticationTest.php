<?php

namespace Tests\Feature;

use App\Models\MobileAuthToken;
use App\Models\User;
use App\Support\MobileAuthHandoff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class MobileAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const ANDROID_USER_AGENT = 'Mozilla/5.0 DavaoRentZoneAndroid/1.0';

    public function test_android_app_hides_its_download_and_marks_social_login_for_native_handoff(): void
    {
        $this->withHeader('User-Agent', self::ANDROID_USER_AGENT)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('Download Android app');

        $this->withHeader('User-Agent', self::ANDROID_USER_AGENT)
            ->get(route('login'))
            ->assertOk()
            ->assertSee(route('auth.google.redirect', ['mobile' => 'android']))
            ->assertSee(route('auth.facebook.redirect', ['mobile' => 'android']))
            ->assertSee('data-native-oauth', false)
            ->assertSee('data-no-loading', false);

        $bridge = file_get_contents(public_path('js/capacitor-android-v1.js'));
        $this->assertIsString($bridge);
        $this->assertStringContainsString('auth/mobile/status', $bridge);
        $this->assertStringContainsString('browserFinished', $bridge);
        $this->assertStringContainsString('davao-rent-zone-startup-permissions-v1', $bridge);
        $this->assertStringContainsString('DavaoRentZoneNativeLocation', $bridge);

        $manifest = file_get_contents(base_path('mobile/android/app/src/main/AndroidManifest.xml'));
        $this->assertIsString($manifest);
        $this->assertStringContainsString('android.permission.ACCESS_COARSE_LOCATION', $manifest);
        $this->assertStringContainsString('android.permission.ACCESS_FINE_LOCATION', $manifest);
        $this->assertStringNotContainsString('android.permission.READ_MEDIA_IMAGES', $manifest);
        $this->assertStringNotContainsString('android.permission.READ_EXTERNAL_STORAGE', $manifest);
    }

    public function test_mobile_oauth_can_start_when_chrome_already_has_a_website_session(): void
    {
        config()->set('services.google.client_id', 'client-id');
        config()->set('services.google.client_secret', 'client-secret');
        Socialite::fake('google');

        $response = $this->actingAs(User::factory()->create())
            ->get('/auth/google?mobile=android');

        $response->assertRedirect()
            ->assertSessionHas(MobileAuthHandoff::SESSION_KEY, 'android');
        $this->assertNotSame(route('dashboard'), $response->headers->get('Location'));
    }

    public function test_google_mobile_login_issues_and_consumes_a_single_use_app_token(): void
    {
        config()->set('services.google.client_id', 'client-id');
        config()->set('services.google.client_secret', 'client-secret');
        Socialite::fake('google');

        $attempt = $this->postJson(route('auth.mobile.attempt'), [
            'provider' => 'google',
        ])->assertOk()
            ->assertJsonStructure(['token', 'authorization_url'])
            ->json();

        $this->assertSame(64, strlen($attempt['token']));

        $this->get($attempt['authorization_url'])
            ->assertRedirect()
            ->assertSessionHas(MobileAuthHandoff::SESSION_KEY, 'android')
            ->assertSessionHas(MobileAuthHandoff::BROWSER_TOKEN_SESSION_KEY, $attempt['token']);

        $this->getJson(route('auth.mobile.status', ['token' => $attempt['token']]))
            ->assertOk()
            ->assertExactJson(['ready' => false]);

        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'mobile-google-123',
            'name' => 'Mobile Google User',
            'email' => 'mobile-google@example.com',
            'email_verified' => true,
        ]));

        $callback = $this->get('/auth/google/callback')->assertRedirect();
        $location = $callback->headers->get('Location');

        $this->assertIsString($location);
        $this->assertStringStartsWith(route('auth.mobile.return').'?token=', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('token', $query);
        $this->assertSame($attempt['token'], $query['token']);
        $this->assertDatabaseCount('mobile_auth_tokens', 1);

        $this->getJson(route('auth.mobile.status', ['token' => $attempt['token']]))
            ->assertOk()
            ->assertExactJson(['ready' => true]);

        $this->get($location)
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Open Davao Rent Zone')
            ->assertSee('davaorentzone:', false)
            ->assertSee('intent://auth/callback?token=', false);

        Auth::logout();
        $this->assertGuest();

        $this->get(route('auth.mobile.complete', ['token' => $query['token']]))
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertNotNull(MobileAuthToken::query()->firstOrFail()->used_at);

        Auth::logout();
        $this->get(route('auth.mobile.complete', ['token' => $query['token']]))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('mobile');
        $this->assertGuest();
    }

    public function test_facebook_mobile_login_returns_to_the_android_app(): void
    {
        config()->set('services.facebook.client_id', 'app-id');
        config()->set('services.facebook.client_secret', 'app-secret');
        Socialite::fake('facebook');

        $attempt = $this->postJson(route('auth.mobile.attempt'), [
            'provider' => 'facebook',
        ])->assertOk()->json();

        $this->get($attempt['authorization_url'])
            ->assertRedirect()
            ->assertSessionHas(MobileAuthHandoff::SESSION_KEY, 'android');

        Socialite::fake('facebook', SocialiteUser::fake([
            'id' => 'mobile-facebook-123',
            'name' => 'Mobile Facebook User',
            'email' => 'mobile-facebook@example.com',
        ]));

        $callback = $this->get('/auth/facebook/callback')
            ->assertRedirect();

        $callback->assertRedirectContains('/auth/mobile/return?token='.$attempt['token']);

        $this->getJson(route('auth.mobile.status', ['token' => $attempt['token']]))
            ->assertOk()
            ->assertExactJson(['ready' => true]);
    }

    public function test_mobile_oauth_status_safely_reports_an_unknown_token_as_not_ready(): void
    {
        $this->postJson(route('auth.mobile.attempt'), [
            'provider' => 'google',
        ])->assertOk();

        $this->getJson(route('auth.mobile.status', ['token' => str_repeat('a', 64)]))
            ->assertOk()
            ->assertExactJson(['ready' => false]);
    }
}
