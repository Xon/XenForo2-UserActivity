<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\Repository\UserActivity as UserActivityRepo;
use SV\UserActivity\UserCountActivityInjector;
use XF\Mvc\Reply\View as ViewReply;

/**
 * Extends \XF\Pub\Controller\WhatsNewPost
 */
class WhatsNewPost extends XFCP_WhatsNewPost
{
    /** @noinspection PhpUnusedParameterInspection */
    protected function threadFetcher(ViewReply $response, string $action, array $config): array
    {
        return $this->getUserActivityRepo()->getFilteredThreadIds($response->getParams(),'threads');
    }

    protected $countActivityInjector = [
        [
            'activeKey' => 'find-new',
            'type'      => 'thread',
            'actions'   => ['index'],
            'fetcher'   => 'threadFetcher'
        ],
    ];
    use UserCountActivityInjector;

    protected function getUserActivityRepo(): UserActivityRepo
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->repository('SV\UserActivity:UserActivity');
    }
}