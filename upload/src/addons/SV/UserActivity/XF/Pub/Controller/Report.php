<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\UserActivityInjector;

class Report extends XFCP_Report
{
    protected $activityInjector = [
        'controller' => 'XF\Pub\Controller\Report',
        'type'       => 'report',
        'id'         => 'report_id',
        'actions'    => ['view'],
        'activeKey'  => 'report',
    ];
    use UserActivityInjector;
}
