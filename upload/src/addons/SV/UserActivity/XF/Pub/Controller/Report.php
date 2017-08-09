<?php

namespace SV\UserActivity\XF\Pub\Controller;

class Report extends XFCP_Report
{
    protected $activityInjector = [
        'controller' => 'XF\Pub\Controller\Report',
        'type' => 'report',
        'id' => 'report_id',
        'actions' => ['view'],
    ];
    use \SV\UserActivity\ActivityInjector;
}