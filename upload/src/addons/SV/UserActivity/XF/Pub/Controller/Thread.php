<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\ActivityInjector;

class Thread extends XFCP_Thread
{
    protected $activityInjector = [
        'controller' => 'XF\Pub\Controller\Thread',
        'type' => 'thread',
        'id' => 'thread_id',
        'actions' => ['index'],
    ];
    use ActivityInjector;
}