<?php

namespace SV\UserActivity\XF\Pub\Controller;

class Conversation extends XFCP_Conversation
{
    protected $activityInjector = [
        'controller' => 'XF\Pub\Controller\Conversation',
        'type' => 'conversation',
        'id' => 'conversation_id',
        'actions' => ['view'],
    ];
    use \SV\UserActivity\ActivityInjector;
}