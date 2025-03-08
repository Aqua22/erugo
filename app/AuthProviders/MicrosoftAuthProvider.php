<?php

namespace App\AuthProviders;

use Exception;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Azure\Provider as AzureProvider;
use App\AuthProviders\AuthProviderUser;
use App\Models\AuthProvider as AuthProviderModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;

class MicrosoftAuthProvider extends BaseAuthProvider
{
  protected $client_id;
  protected $client_secret;
  protected $tenant;
  protected $provider;

  public function __construct(AuthProviderModel $provider)
  {
    $this->client_id = $provider->provider_config->client_id;
    $this->client_secret = $provider->provider_config->client_secret;
    $this->tenant = $provider->provider_config->tenant ?? 'common';
    $this->provider = $provider;
  }

  private function getConfig(bool $asArray = false)
  {
    if (!$asArray) {
      return new \SocialiteProviders\Manager\Config(
        $this->client_id,
        $this->client_secret,
        route('social.provider.callback', ['provider' => $this->provider->uuid]),
        [
          'tenant' => $this->tenant,
        ]
      );
    } else {
      return [
        'client_id' => $this->client_id,
        'client_secret' => $this->client_secret,
        'redirect' => route('social.provider.callback', ['provider' => $this->provider->uuid]),
        'tenant' => $this->tenant,
      ];
    }
  }

  private function createClient()
  {
    // Let's check we have all the required data
    if (!$this->client_id || !$this->client_secret) {
      $this->throwMissingDataException();
    }

    return Socialite::buildProvider(AzureProvider::class, $this->getConfig(true))->setConfig($this->getConfig());
  }

  public function redirect()
  {
    try {
      // Create Azure provider
      $azureProvider = $this->createClient();


      return $azureProvider->scopes(['openid', 'profile', 'email', 'User.Read'])->with([
        'prompt' => 'select_account',
        'response_type' => 'code',
        'response_mode' => 'query'
      ])->redirect();
    } catch (Exception $e) {
      Log::error('Microsoft redirect error: ' . $e->getMessage());
      throw $e;
    }
  }

  public function handleCallback(): AuthProviderUser
  {
    try {
      // Get the request object to check for code presence
      $request = app(Request::class);

      // Check if code is present
      if (!$request->has('code')) {
        throw new Exception('Authorization code is missing from the callback URL');
      }

      // Create Microsoft provider
      $microsoftProvider = $this->createClient();

      // Get the user information
      $user = $microsoftProvider->user();

      // Return the user information as an AuthProviderUser
      return new AuthProviderUser([
        'sub' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'avatar' => $user->avatar ?? null,
        'verified' => $user->user['email_verified'] ?? false
      ]);
    } catch (Exception $e) {
      // Log the error for debugging
      Log::error('Microsoft authentication error: ' . $e->getMessage());
      throw $e;
    }
  }

  public static function getIcon(): string
  {
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50"><path d="M 5 4 C 4.448 4 4 4.447 4 5 L 4 24 L 24 24 L 24 4 L 5 4 z M 26 4 L 26 24 L 46 24 L 46 5 C 46 4.447 45.552 4 45 4 L 26 4 z M 4 26 L 4 45 C 4 45.553 4.448 46 5 46 L 24 46 L 24 26 L 4 26 z M 26 26 L 26 46 L 45 46 C 45.552 46 46 45.553 46 45 L 46 26 L 26 26 z"></path></svg>';
  }

  public static function getName(): string
  {
    return 'Microsoft';
  }

  public static function getDescription(): string
  {
    return 'Microsoft is a popular authentication provider that allows users to sign in to your application using their Microsoft account.';
  }

  public static function getValidator(array $data): Validator
  {
    return ValidatorFacade::make($data, [
      'client_id' => ['required', 'string'],
      'client_secret' => ['required', 'string'],
      'tenant' => ['required', 'string'],
    ]);
  }

  public static function getInformationUrl(): ?string
  {
    return 'https://docs.microsoft.com/en-us/azure/active-directory/develop/v2-overview';
  }

  public static function getEmptyProviderConfig(): array
  {
    return [
      'client_id' => '',
      'client_secret' => '',
      'tenant' => 'common',
    ];
  }
}
