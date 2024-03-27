<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\UserActivityInjector;
use SV\UserActivity\UserCountActivityInjector;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Reply\View as ViewReply;
use function array_merge;

/**
 * @extends \XF\Pub\Controller\Conversation
 */
class Conversation extends XFCP_Conversation
{
    /** @noinspection PhpUnusedParameterInspection */
    protected function conversationFetcher(ViewReply $response, string $action, array $config): array
    {
        /** @var AbstractCollection $conversations */
        $conversations = $response->getParam('userConvs');
        if (!$conversations || !$conversations->count())
        {
            return [];
        }

        $conversationIds = $conversations->keys();

        /** @var AbstractCollection $conversations */
        $conversations = $response->getParam('stickyUserConvs');
        if ($conversations && $conversations->count())
        {
            $conversationIds = array_merge($conversationIds, $conversations->keys());
        }

        return $conversationIds;
    }

    protected $countActivityInjector = [
        [
            'activeKey' => 'conversation',
            'type'      => 'conversation',
            'actions'   => ['index', 'labeled'],
            'fetcher'   => 'conversationFetcher'
        ],
    ];
    use UserCountActivityInjector;

    protected $activityInjector = [
        'controller' => \XF\Pub\Controller\Conversation::Class,
        'type'       => 'conversation',
        'id'         => 'conversation_id',
        'actions'    => ['view'],
        'activeKey'  => 'conversation',
    ];
    use UserActivityInjector;
}
