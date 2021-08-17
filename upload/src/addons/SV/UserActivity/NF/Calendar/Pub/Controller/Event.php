<?php

namespace SV\UserActivity\NF\Calendar\Pub\Controller;

use SV\UserActivity\UserActivityInjector;

/**
 * Extends \NF\Calendar\Pub\Controller\Event
 */
class Event extends XFCP_Event
{
    protected $activityInjector = [
        'controller' => 'NF\Calendar\Pub\Controller\Event',
        'type'       => 'event',
        'id'         => 'event_id',
        'actions'    => ['view'],
        'activeKey'  => 'nf_calendar',
    ];
    use UserActivityInjector;
}