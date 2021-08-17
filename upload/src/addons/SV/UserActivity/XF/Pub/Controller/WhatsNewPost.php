<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\Repository\UserActivity;
use SV\UserActivity\UserCountActivityInjector;
use XF\Mvc\Reply\View;
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

    /**
     * @return \XF\Mvc\Entity\Repository|UserActivity
     */
    protected function getUserActivityRepo()
    {
        return \XF::repository('SV\UserActivity:UserActivity');
    }
}