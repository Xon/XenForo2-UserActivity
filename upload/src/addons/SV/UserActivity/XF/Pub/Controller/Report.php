<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\ReportCentreEssentials\Entity\ReportQueue as ReportQueueEntity;
use SV\UserActivity\UserActivityInjector;
use SV\UserActivity\UserCountActivityInjector;
use XF\Entity\Report as ReportEntity;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Reply\View as ViewReply;
use function is_array;

class Report extends XFCP_Report
{
    protected $activityInjector = [
        'type'       => 'report',
        'id'         => 'report_id',
        'actions'    => ['view'],
        'activeKey'  => 'report',
    ];
    use UserActivityInjector;

    protected $countActivityInjector = [
//        [
//            'activeKey' => 'report-list',
//            'type'      => 'report-queue',
//            'actions'   => ['index', 'closed'],
//            'fetcher'   => 'reportQueueFetcher',
//        ],
        [
            'activeKey' => 'report-list',
            'type'      => 'report',
            'actions'   => ['index', 'closed'],
            'fetcher'   => 'reportsFetcher',
        ],
        [
            'activeKey' => 'report',
            'type'      => 'report',
            'actions'   => ['view'],
            'fetcher'   => 'reportsFetcher'
        ],
    ];
    use UserCountActivityInjector;

    /** @noinspection PhpUnusedParameterInspection */
    protected function reportsFetcher(ViewReply $response, string $action, array $config): array
    {
        $reportIds = [];

        $reports = $response->getParam('openReports');
        if ($reports instanceof AbstractCollection || is_array($reports))
        {
            foreach($reports as $report)
            {
                if ($report instanceof ReportEntity)
                {
                    $reportIds[$report->report_id] = true;
                }
            }
        }

        $reports = $response->getParam('closedReports');
        if ($reports instanceof AbstractCollection || is_array($reports))
        {
            foreach($reports as $report)
            {
                if ($report instanceof ReportEntity)
                {
                    $reportIds[$report->report_id] = true;
                }
            }
        }

        $report = $response->getParam('report');
        if ($report instanceof ReportEntity)
        {
            $reportIds[$report->report_id] = true;
        }

        return array_keys($reportIds);
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function reportQueueFetcher(ViewReply $response, string $action, array $config): array
    {
        $queueIds = [];
        if (\XF::isAddOnActive('SV/ReportCentreEssentials'))
        {
            $queues = $response->getParam('reportQueues');
            if ($queues instanceof AbstractCollection || is_array($queues))
            {
                foreach($queues as $queue)
                {
                    if ($queue instanceof ReportQueueEntity)
                    {
                        $queueIds[$queue->queue_id] = true;
                    }
                }
            }

            $reportQueue = $response->getParam('reportQueue');
            if ($reportQueue instanceof ReportQueueEntity)
            {
                $queueIds[$reportQueue->queue_id] = true;
            }
            $report = $response->getParam('report');
            if ($report instanceof ReportQueueEntity)
            {
                $queueIds[$report->queue_id] = true;
            }
        }

        return $queueIds;
    }

//    public function actionView(ParameterBag $params)
//    {
//        $response = parent::actionView($params);
//
//        if ($response instanceof ViewReply)
//        {
//            $report = $response->getParam('report');
//            if ($report instanceof \XF\Entity\Report)
//            {
//                /** @var \SV\ReportCentreEssentials\XF\Entity\Report $report */
//                UserActivityRepo::get()->pushViewUsageToParent($response, $report->ReportQueue, true);
//            }
//        }
//
//        return $response;
//    }
//
//    protected function updateSessionActivity($action, ParameterBag $params, AbstractReply &$reply)
//    {
//        if ($reply instanceof ViewReply &&
//            \XF::isAddOnActive('SV/ReportCentreEssentials') &&
//            $params->get('queue_id') === null)
//        {
//            $reportQueue = $reply->getParam('reportQueue');
//            if ($reportQueue instanceof ReportQueueEntity)
//            {
//                $params['queue_id'] = $reportQueue->queue_id;
//            }
//        }
//
//        parent::updateSessionActivity($action, $params, $reply);
//    }
}
