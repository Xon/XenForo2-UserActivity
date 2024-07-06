<?php

namespace SV\UserActivity\NF\Calendar\Pub\Controller;

use SV\UserActivity\UserActivityInjector;

/**
 * @extends \NF\Calendar\Pub\Controller\Event
 */
class Event extends XFCP_Event
{
    protected $activityInjector = [
        'type'       => 'event',
        'id'         => 'event_id',
        'actions'    => ['view'],
        'activeKey'  => 'nf_calendar',
    ];
    use UserActivityInjector;
}