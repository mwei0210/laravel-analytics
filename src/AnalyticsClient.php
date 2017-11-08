<?php

namespace Spatie\Analytics;

use DateTime;
use Google_Service_Analytics;
use Illuminate\Contracts\Cache\Repository;
use Google_Service_AnalyticsReporting;
use Google_Service_AnalyticsReporting_DateRange;
use Google_Service_AnalyticsReporting_Metric;
use Google_Service_AnalyticsReporting_Dimension;
use Google_Service_AnalyticsReporting_ReportRequest;
use Google_Service_AnalyticsReporting_GetReportsRequest;
use Google_Service_AnalyticsReporting_OrderBy;

class AnalyticsClient
{
    /** @var \Google_Service_Analytics */
    protected $service;

    /** @var \Illuminate\Contracts\Cache\Repository */
    protected $cache;

    /** @var int */
    protected $cacheLifeTimeInMinutes = 0;

    public function __construct(Google_Service_AnalyticsReporting $service, Repository $cache)
    {
        $this->service = $service;

        $this->cache = $cache;
    }

    /**
     * Set the cache time.
     *
     * @param int $cacheLifeTimeInMinutes
     *
     * @return self
     */
    public function setCacheLifeTimeInMinutes(int $cacheLifeTimeInMinutes)
    {
        $this->cacheLifeTimeInMinutes = $cacheLifeTimeInMinutes;

        return $this;
    }

    /**
     * Query the Google Analytics Service with given parameters.
     *
     * @param string    $viewId
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param string    $metrics
     * @param array     $others
     *
     * @return array|null
     */
    public function performQuery(string $viewId, DateTime $startDate, DateTime $endDate, array $metrics, array $dimensions , string $sortByField = null, int $maxResults, array $others = [])
    {
        $cacheName = $this->determineCacheName(func_get_args());

        if ($this->cacheLifeTimeInMinutes == 0) {
            $this->cache->forget($cacheName);
        }

        // Create the DateRange object.
        $dateRange = new Google_Service_AnalyticsReporting_DateRange();
        $dateRange->setStartDate($startDate->format('Y-m-d'));
        $dateRange->setEndDate($endDate->format('Y-m-d'));

        $dimensionObjArr = [];
        $metricObjArr    = [];
        foreach ($metrics as $key => $metric) {
// Create the Metrics object.
            $metricObj = new Google_Service_AnalyticsReporting_Metric();
            $metricObj->setExpression("ga:" . $metric);
            $metricObj->setAlias($metric);
            $metricObjArr[] = $metricObj;
        }
        foreach ($dimensions as $key => $dimension) {
//Create the Dimensions object.
            $dimensionObj = new Google_Service_AnalyticsReporting_Dimension();
            $dimensionObj->setName("ga:" . $dimension);
            $dimensionObjArr[] = $dimensionObj;
        }



// Create the ReportRequest object.
        $request = new Google_Service_AnalyticsReporting_ReportRequest();
        $request->setViewId($viewId);
        $request->setDateRanges($dateRange);
        $request->setDimensions($dimensionObjArr);
        $request->setMetrics($metricObjArr);

        if($sortByField){
        $ordering = new Google_Service_AnalyticsReporting_OrderBy();
        $ordering->setFieldName("ga:".$sortByField);
        $ordering->setOrderType("VALUE");   
        $ordering->setSortOrder("DESCENDING");
        $request->setOrderBys($ordering); 
    }
        $body = new Google_Service_AnalyticsReporting_GetReportsRequest();
        $body->setReportRequests(array($request));
        return $this->cache->remember($cacheName, $this->cacheLifeTimeInMinutes, function () use ($body) {
            return self::processResult($this->service->reports->batchGet($body));
        });

    }

    public function getAnalyticsService(): Google_Service_Analytics
    {
        return $this->service;
    }

    /*
     * Determine the cache name for the set of query properties given.
     */
    protected function determineCacheName(array $properties): string
    {
        return 'spatie.laravel-analytics.' . md5(serialize($properties));
    }

    /**
 * Parses and prints the Analytics Reporting API V4 response.
 *
 * @param An Analytics Reporting API V4 response.
 */
protected function processResult($reports) {
    $results = [];
  for ( $reportIndex = 0; $reportIndex < count( $reports ); $reportIndex++ ) {
    $report = $reports[ $reportIndex ];
    $header = $report->getColumnHeader();
    $dimensionHeaders = $header->getDimensions();
    $metricHeaders = $header->getMetricHeader()->getMetricHeaderEntries();
    $rows = $report->getData()->getRows();

    for ( $rowIndex = 0; $rowIndex < count($rows); $rowIndex++) {
      $row = $rows[ $rowIndex ];
      $dimensions = $row->getDimensions();
      $metrics = $row->getMetrics();
      for ($i = 0; $i < count($dimensionHeaders) && $i < count($dimensions); $i++) {
        $results[$rowIndex][] = $dimensions[$i];
      }

      for ($j = 0; $j < count($metrics); $j++) {
        $values = $metrics[$j]->getValues();
        for ($k = 0; $k < count($values); $k++) {
          $entry = $metricHeaders[$k];
            $results[$rowIndex][] = $values[$k];
        }
      }
    }
  }
  return $results;
}


}
