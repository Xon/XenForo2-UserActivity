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
            $ip = $this->request->getIp();
            if ($nodeTrackLimit === 1)
            {
                $repo->trackViewerUsage('node', $thread->node_id, 'forum', $ip);
            }
            else if ($nodeTrackLimit !== 0)
            {
                $node = $forum->Node;
                if ($node->parent_node_id)
                {
                    /** @var \XF\Finder\Node $nodeFinder */
                    $nodeFinder = \XF::finder('XF:Node');
                    $nodeFinder->where('lft', '<', $node->lft)
                               ->where('rgt', '>', $node->rgt)
                               ->where('node_type_id', '=', 'Forum')
                               ->order('lft');

                    $nodeIds = $nodeFinder->fetchColumns('node_id');
                }
                else
                {
                    $nodeIds = [];
                }
                $nodeIds[] = $node;
                $count = count($nodeIds);
                $nodeTrackLimit = $nodeTrackLimit < 0 ? PHP_INT_MAX : $nodeTrackLimit;
                if ($count > $nodeTrackLimit)
                {
                    $nodeIds = \array_splice(array_reverse($nodeIds), 0, $nodeTrackLimit);
                }
                foreach ($nodeIds AS $node)
                {
                    $repo->trackViewerUsage('node', $node['node_id'], 'forum', $ip);
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

    protected $countActivityInjector = [
        [
            'activeKey' => 'similar-threads',
            'type'      => 'thread',
            'actions'   => ['index'],
            'fetcher'   => 'similarThreadFetcher'
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
