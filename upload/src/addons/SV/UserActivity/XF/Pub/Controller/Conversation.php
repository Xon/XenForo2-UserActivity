<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\UserActivityInjector;
use SV\UserActivity\UserCountActivityInjector;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Reply\View;

class Conversation extends XFCP_Conversation
{
    protected function conversationFetcher(
        /** @noinspection PhpUnusedParameterInspection */
        View $response,
        $action,
        array $config)

    {
        /** @var AbstractCollection $conversations */
        $conversations = $response->getParam('userConvs');
        if (!$conversations || !$conversations->count())
        {
            return null;
        }

        return $conversations->keys();
    }

    protected $countActivityInjector = [
        [
            'activeKey' => 'conversation',
            'type'      => 'conversation',
            'actions'   => ['index'],
            'fetcher'   => 'conversationFetcher'
        ],
    ];
    use UserCountActivityInjector;

    protected $activityInjector = [
        'controller' => 'XF\Pub\Controller\Conversation',
        'type'       => 'conversation',
        'id'         => 'conversation_id',
        'actions'    => ['view'],
        'activeKey'  => 'conversation',
    ];
    use UserActivityInjector;
}
