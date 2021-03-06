<?php

namespace Spatie\Analytics;

use Carbon\Carbon;
use Google_Service_Analytics;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;

class Analytics
{
    use Macroable;

    /** @var \Spatie\Analytics\AnalyticsClient */
    protected $client;

    /** @var string */
    protected $viewId;

    /**
     * @param \Spatie\Analytics\AnalyticsClient $client
     * @param string                            $viewId
     */
    public function __construct(AnalyticsClient $client, string $viewId)
    {
        $this->client = $client;

        $this->viewId = $viewId;
    }

    /**
     * @param string $viewId
     *
     * @return $this
     */
    public function setViewId(string $viewId)
    {
        $this->viewId = $viewId;

        return $this;
    }

    public function fetchVisitorsAndPageViews(Period $period, int $maxResults = null): Collection
    {
        //$period->endDate->addDay();
        $response = $this->performQuery(
            $period,
            ['users','pageviews'],
            ['date','pageTitle'],
            'pageviews',
            $maxResults
        );

        return collect($response ?? [])->map(function (array $dateRow) {
            return [
                'date' => Carbon::createFromFormat('Ymd', $dateRow[0])->format('D, M j, Y'),
                'pageTitle' => $dateRow[1],
                'visitors' => (int) $dateRow[2],
                'pageViews' => (int) $dateRow[3],
            ];
        });
    }

    public function fetchTotalVisitorsAndPageViews(Period $period, int $maxResults = null): Collection
    {
        $response = $this->performQuery(
            $period,
            ['users','pageviews'],
            ['date'],
            'pageviews',
            $maxResults
        );

        return collect($response ?? [])->map(function (array $dateRow) {
            return [
                'date' => Carbon::createFromFormat('Ymd', $dateRow[0])->format('D, M j, Y'),
                'visitors' => (int) $dateRow[1],
                'pageViews' => (int) $dateRow[2],
            ];
        });
    }

    public function fetchMostVisitedPages(Period $period, int $maxResults = null): Collection
    {
        $response = $this->performQuery(
            $period,
            ['pageviews'],
            ['pagePath','pageTitle'],
            'pageviews',
            $maxResults
        );

        return collect($response ?? [])
            ->map(function (array $pageRow) {
                return [
                    'url' => $pageRow[0],
                    'pageTitle' => $pageRow[1],
                    'pageViews' => (int) $pageRow[2],
                ];
            });
    }

    public function fetchTopReferrers(Period $period, int $maxResults = null): Collection
    {
        $response = $this->performQuery($period,
            ['pageviews'],
            ['fullReferrer'],
            'pageviews',
            $maxResults
        );

        return collect($response ?? [])->map(function (array $pageRow) {
            return [
                'url' => $pageRow[0],
                'pageViews' => (int) $pageRow[1],
            ];
        });
    }

    public function fetchTopBrowsers(Period $period, int $maxResults = 10): Collection
    {
        $response = $this->performQuery(
            $period,
            ['sessions'],
            ['browser'],
            'sessions'
        );

        $topBrowsers = collect($response ?? [])->map(function (array $browserRow) {
            return [
                'browser' => $browserRow[0],
                'sessions' => (int) $browserRow[1],
            ];
        });

        if ($topBrowsers->count() <= $maxResults) {
            return $topBrowsers;
        }

        return $this->summarizeTopBrowsers($topBrowsers, $maxResults);
    }

    protected function summarizeTopBrowsers(Collection $topBrowsers, int $maxResults): Collection
    {
        return $topBrowsers
            ->take($maxResults - 1)
            ->push([
                'browser' => 'Others',
                'sessions' => $topBrowsers->splice($maxResults - 1)->sum('sessions'),
            ]);
    }

    /**
     * Call the query method on the authenticated client.
     *
     * @param Period $period
     * @param string $metrics
     * @param array  $others
     *
     * @return array|null
     */
    public function performQuery(Period $period, array $metrics, array $dimensions = [], string $sortByField = null, int $maxResults = null, array $others = [])
    {
        return $this->client->performQuery(
            $this->viewId,
            $period->startDate,
            $period->endDate,
            $metrics,
            $dimensions,
            $sortByField,
            $maxResults,
            $others
        );
    }

    public function fetchDemographics(Period $period, int $maxResults = null): Collection
    {
        $response = $this->performQuery(
            $period,
            [
                'users'
            ],
            [
                'userAgeBracket',
                'userGender',
            ]
        );

        return collect($response ?? [])->map(function (array $demoRow) {
            return [
                'userAgeBracket' => $demoRow[0],
                'userGender' => $demoRow[1],
                'visitors' => $demoRow[2]
            ];
        });

    }

    public function fetchGeo(Period $period, int $maxResults = null): Collection
    {
        $response = $this->performQuery(
            $period,
            [
                'users'
            ],
            [
                'language',
                'city',
                'country'
            ],
            'users',
            $maxResults
        );

        return collect($response ?? [])->map(function (array $geoRow) {
            return [
                'language' => $geoRow[0],
                'city' => $geoRow[1],
                'country' => $geoRow[2],
                'visitors' => $geoRow[3]
            ];
        });

    }

    public function fetchTrafficSummary(Period $period): Collection
    {
        $response = $this->performQuery(
            $period,
            ['users','pageviews','sessions','bounceRate'],
            ['date']
        );

        return collect($response ?? [])->map(function (array $dateRow) {
            return [
                'date' => Carbon::createFromFormat('Ymd', $dateRow[0])->format('j M'),
                'visitors' => (int) $dateRow[1],
                'pageViews' => (int) $dateRow[2],
                'sessions' => $dateRow[3],
                'bounceRate' => $dateRow[4],
            ];
        });
    }

    public function fetchLanguages(Period $period, int $maxResults = null): Collection
    {
        $response = $this->performQuery(
            $period,
            [
                'users'
            ],
            [
                'language'
            ],
            'users',
            $maxResults
        );

        return collect($response ?? [])->map(function (array $geoRow) {
            return [
                'language' => $geoRow[0],
                'visitors' => $geoRow[1]
            ];
        });

    }

    public function fetchCities(Period $period, int $maxResults = null): Collection
    {
        $response = $this->performQuery(
            $period,
            [
                'users'
            ],
            [
                'city',
                'country'
            ],
            'users',
            $maxResults
        );

        return collect($response ?? [])->map(function (array $geoRow) {
            return [
                'city' => $geoRow[0].', '.$geoRow[1],
                'visitors' => $geoRow[2]
            ];
        });

    }

    public function fetchCountries(Period $period, int $maxResults = null): Collection
    {
        $response = $this->performQuery(
            $period,
            [
                'users'
            ],
            [
                'country'
            ],
            'users',
            $maxResults
        );

        return collect($response ?? [])->map(function (array $geoRow) {
            return [
                'country' => $geoRow[0],
                'visitors' => $geoRow[1]
            ];
        });

    }

    public function fetchTrafficByDayHour(Period $period): Collection
    {
        $response = $this->performQuery(
            $period,
            ['users'],
            ['dateHour']
        );

        $result = [];

        foreach ($response as $key => $dateRow) {
            $dateHour = Carbon::createFromFormat('YmdH', $dateRow[0]);
            $result[$dateHour->format('l')][] = [
                'hour' => $dateHour->format('H'),
                'visitors' => (int) $dateRow[1]
            ];
        }

        return collect($result);
    }

    /*
     * Get the underlying Google_Service_Analytics object. You can use this
     * to basically call anything on the Google Analytics API.
     */
    public function getAnalyticsService(): Google_Service_Analytics
    {
        return $this->client->getAnalyticsService();
    }
}
