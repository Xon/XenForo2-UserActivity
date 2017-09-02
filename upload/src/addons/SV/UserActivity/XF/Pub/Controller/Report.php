<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\ActivityInjector;

class Report extends XFCP_Report
{
    protected $activityInjector = [
        'controller' => 'XF\Pub\Controller\Report',
        'type' => 'report',
        'id' => 'report_id',
        'actions' => ['view'],
    ];
    use ActivityInjector;
}