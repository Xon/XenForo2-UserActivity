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

        $options = \XF::options();
        /** @noinspection PhpUndefinedFieldInspection */
        if ($response instanceof view &&
            ($thread = $response->getParam('thread')) &&
            ($forum = $response->getParam('forum')))
        {
            if (empty($options->svUAPopulateUsers['forum']))
            {
                return $response;
            }

            $nodeTrackLimit = intval($options->svUAThreadNodeTrackLimit);
            $nodeTrackLimit = $nodeTrackLimit < 0 ? PHP_INT_MAX : $nodeTrackLimit;
            $session = \XF::session();
            $robotKey = $session->isStarted() ? $session->get('robotId') : true;
            if (!$options->SV_UA_TrackRobots && $robotKey)
            {
                return $response;
            }

            /** @var \XF\Entity\Thread $thread */
            /** @var \XF\Entity\Forum $forum */
            /** @var  UserActivity $repo */
            $repo = \XF::repository('SV\UserActivity:UserActivity');
            if ($nodeTrackLimit !== 0)
            {
                $node = $forum->Node;
                $repo->bufferTrackViewerUsage('node', $node->node_id, 'forum');
                if ($nodeTrackLimit > 1)
                {
                    $count = 1;
                    if ($node->breadcrumb_data)
                    {
                        foreach ($node->breadcrumb_data AS $crumb)
                        {
                            if ($crumb['node_type_id'] === 'Forum')
                            {
                                $repo->bufferTrackViewerUsage('node', $crumb['node_id'], 'forum');
                                $count++;
                                if ($count > $nodeTrackLimit)
                                {
                                    break;
                                }
                            }
                        }
                    }
                }
            }
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
}
