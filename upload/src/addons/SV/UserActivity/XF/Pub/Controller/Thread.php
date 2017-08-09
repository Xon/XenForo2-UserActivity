<?php

namespace SV\UserActivity\XF\Pub\Controller;

class Thread extends XFCP_Thread
{
    protected $activityInjector = [
        'controller' => 'XF\Pub\Controller\Thread',
        'type' => 'thread',
        'id' => 'thread_id',
        'actions' => ['index'],
    ];
    use \SV\UserActivity\ActivityInjector;
}