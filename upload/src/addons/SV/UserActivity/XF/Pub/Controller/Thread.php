<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\Repository\UserActivity as UserActivityRepo;
use SV\UserActivity\UserActivityInjector;
use SV\UserActivity\UserCountActivityInjector;
use XF\Entity\Thread as ThreadEntity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View as ViewReply;

class Thread extends XFCP_Thread
{
    public function actionIndex(ParameterBag $params)
    {
        $response = parent::actionIndex($params);

        if ($response instanceof ViewReply)
        {
            $thread = $response->getParam('thread');
            if ($thread instanceof ThreadEntity)
            {
                UserActivityRepo::get()->pushViewUsageToParent($response, $thread->Forum->Node, true);
            }
        }

        return $response;
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function similarThreadFetcher(ViewReply $response, string $action, array $config): array
    {
        $threadIds = [];
        $threads = $response->getParam('svSimilarThreads');
        if ($threads)
        {
            /** @var ThreadEntity $thread */
            foreach ($threads as $thread)
            {
                $threadIds[] = $thread->thread_id;
            }
        }

        return $threadIds;
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function threadFetcher(ViewReply $response, string $action, array $config): array
    {
        $thread = $response->getParam('thread');
        if ($thread instanceof ThreadEntity)
        {
            return [$thread->thread_id];
        }

        return [];
    }

    protected $countActivityInjector = [
        [
            'activeKey' => 'similar-threads',
            'type'      => 'thread',
            'actions'   => ['index'],
            'fetcher'   => 'similarThreadFetcher'
        ],
        [
            'activeKey' => 'thread-view',
            'type'      => 'thread',
            'actions'   => ['index'],
            'fetcher'   => 'threadFetcher'
        ],
    ];
    use UserCountActivityInjector;

    protected $activityInjector = [
        'type'       => 'thread',
        'id'         => 'thread_id',
        'actions'    => ['index'],
        'activeKey'  => 'thread',
    ];
    use UserActivityInjector;
}
