<?php

namespace Spatie\Analytics;

use Google_Client;
use Google_Service_Analytics;
use Google_Service_AnalyticsReporting;
use Illuminate\Contracts\Cache\Repository;
use Madewithlove\IlluminatePsrCacheBridge\Laravel\CacheItemPool;

class AnalyticsClientFactory
{
    public static function createForConfig(array $analyticsConfig): AnalyticsClient
    {
        $authenticatedClient = self::createAuthenticatedGoogleClient($analyticsConfig);

        $googleService = new Google_Service_Analytics($authenticatedClient);

        return self::createAnalyticsClient($analyticsConfig, $googleService);
    }

    public static function createAuthenticatedGoogleClient(array $config): Google_Client
    {
        $client = new Google_Client();

        $client->setScopes([
            Google_Service_Analytics::ANALYTICS_READONLY,
        ]);

        $client->setAuthConfig($config['service_account_credentials_json']);

        self::configureCache($client, $config['cache']);

        return $client;
    }

    public static function createForToken(array $analyticsConfig, $access_token): AnalyticsClient
    {
        $authenticatedClient = self::createAuthenticatedGoogleClientOauth($analyticsConfig, $access_token);

        //$googleService = new Google_Service_Analytics($authenticatedClient);
        $googleService = new Google_Service_AnalyticsReporting($authenticatedClient);

        return self::createAnalyticsClient($analyticsConfig, $googleService);
    }

    public static function createAuthenticatedGoogleClientOauth(array $config, $access_token): Google_Client
    {
        $client = new Google_Client();

        $client->setScopes([
            Google_Service_Analytics::ANALYTICS_READONLY,
        ]);

        $client->setAccessToken($access_token);

        self::configureCacheMultiClient($client, $config['cache']);

        return $client;
    }

    protected static function configureCache(Google_Client $client, $config)
    {
        $config = collect($config);

        $store = \Cache::store($config->get('store'));

        $cache = new CacheItemPool($store);

        $client->setCache($cache);

        $client->setCacheConfig(
            $config->except('store')->toArray()
        );
    }

    protected static function configureCacheMultiClient(Google_Client $client, $config)
    {
        $config = collect($config);

        $store = \Cache::store($config->get('store'));

        $cache = new CacheItemPool($store);

        $client->setCache($cache);

        $cacheConfigNoStore = $config->except('store')->toArray();

        $cacheConfigNoStore['prefix'] = isset($cacheConfigNoStore['prefix'])?$cacheConfigNoStore['prefix']:'' . $client->getAccessToken()['access_token'];

        $client->setCacheConfig(
            $cacheConfigNoStore
        );
    }

    protected static function createAnalyticsClient(array $analyticsConfig, Google_Service_AnalyticsReporting $googleService): AnalyticsClient
    {
        $client = new AnalyticsClient($googleService, app(Repository::class));

        $client->setCacheLifeTimeInMinutes($analyticsConfig['cache_lifetime_in_minutes']);

        return $client;
    }
}
