<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\ActivityInjector;

class Conversation extends XFCP_Conversation
{
    protected $activityInjector = [
        'controller' => 'XF\Pub\Controller\Conversation',
        'type' => 'conversation',
        'id' => 'conversation_id',
        'actions' => ['view'],
    ];
    use ActivityInjector;
}