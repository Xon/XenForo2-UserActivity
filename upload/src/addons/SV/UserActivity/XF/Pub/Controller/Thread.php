<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\Repository\UserActivity;
use SV\UserActivity\UserActivityInjector;
use SV\UserActivity\UserCountActivityInjector;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;

class Thread extends XFCP_Thread
{
    public function actionIndex(ParameterBag $params)
    {
        $response = parent::actionIndex($params);

        if ($response instanceof view &&
            ($thread = $response->getParam('thread')))
        {
            /** @var \XF\Entity\Thread $thread */
            $this->getUserActivityRepo()->pushViewUsageToParent($response, $thread->Forum->Node, true);
        }

        return $response;
    }

    protected function similarThreadFetcher(
        /** @noinspection PhpUnusedParameterInspection */
        View $response,
        $action,
        array $config)

    {
        if ($threads = $response->getParam('svSimilarThreads'))
        {
            $threadIds = [];
            foreach ($threads as $content)
            {
                if ($content['content_type'] === 'thread')
                {
                    $threadIds[] = $content['content_id'];
                }
            }

            return $threadIds;
        }

        return null;
    }

    protected function threadFetcher(
        /** @noinspection PhpUnusedParameterInspection */
        View $response,
        $action,
        array $config)

    {
        /** @var \XF\Entity\Thread $thread */
        if ($thread = $response->getParam('thread'))
        {
            return [$thread->thread_id];
        }

        return null;
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
        'controller' => 'XF\Pub\Controller\Thread',
        'type'       => 'thread',
        'id'         => 'thread_id',
        'actions'    => ['index'],
        'activeKey'  => 'thread',
    ];
    use UserActivityInjector;

    /**
     * @return \XF\Mvc\Entity\Repository|UserActivity
     */
    protected function getUserActivityRepo()
    {
        return \XF::repository('SV\UserActivity:UserActivity');
    }
}
