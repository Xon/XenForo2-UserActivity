<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\ActivityInjector;
use XF\App;
use XF\Entity\Node;
use XF\Http\Request;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View;

/**
 * Extends \XF\Pub\Controller\Forum
 */
class Forum extends XFCP_Forum
{
    public function __construct(App $app, Request $request)
    {
        parent::__construct($app, $request);
        /** @noinspection PhpUndefinedFieldInspection */
        if (!\XF::options()->svUATrackForum)
        {
            $this->activityInjector = [];
        }
    }

    protected function postDispatchType($action, ParameterBag $params, AbstractReply &$reply)
    {
        // this updates the session, and occurs after postDispatchController
        parent::postDispatchType($action, $params, $reply);
        $action = strtolower($action); // don't need utf8_strtolower
        switch($action)
        {
            case 'list':
                $this->injectResponse($reply, 1);
                break;
            case 'forum':
                $this->injectResponse($reply, 0);
                break;
        }
    }

    /**
     * @param AbstractReply $response
     * @param int           $childDepth
     */
    public function injectResponse(AbstractReply &$response, $childDepth)
    {
        if ($response instanceof View && !$response->getParam('touchedUA'))
        {
            $response->setParam('touchedUA', true);
            $fetchData = [];
            $options = \XF::options();

            if ($options->svUATrackForum)
            {
                $fetchData['node'] = [];
                /** @var \XF\Tree $nodeTree */
                if ($nodeTree = $response->getParam('nodeTree'))
                {
                    $nodes = [];
                    $flattenedNodeList = $nodeTree->getFlattened();
                    foreach($flattenedNodeList as $id => $node)
                    {
                        /** @var Node[] $nodes */
                        if ($node['depth'] <= $childDepth)
                        {
                            $nodes[] = $id;
                        }
                    }
                    $fetchData['node'] = $nodes;
                }
                /** @var \XF\Entity\Forum $forum */
                if ($forum = $response->getParam('forum'))
                {
                    $fetchData['node'][] = $forum->node_id;
                }
            }

            if ($options->svUADisplayThreads)
            {
                $fetchData['thread'] = [];
                if ($threads = $response->getParam('threads'))
                {
                    $threadIds =  ($threads instanceof AbstractCollection) ? $threads->keys() : array_keys($threads);
                    $fetchData['thread'] = $threadIds;
                }
                if ($threads = $response->getParam('stickyThreads'))
                {
                    $threadIds =  ($threads instanceof AbstractCollection) ? $threads->keys() : array_keys($threads);
                    $fetchData['thread'] = array_merge($fetchData['thread'], $threadIds);
                }
            }
            if ($fetchData)
            {
                /** @var \SV\UserActivity\Repository\UserActivity $userActivityRepo */
                $userActivityRepo = \XF::repository('SV\UserActivity:UserActivity');
                $userActivityRepo->insertBulkUserActivityIntoViewResponse($response, $fetchData);
            }
        }
    }


    protected $activityInjector = [
        'controller' => 'XF\Pub\Controller\Forum',
        'type'       => 'node',
        'id'         => 'node_id',
        'actions'    => [], // deliberate, as we do our own thing to inject content
        'countsOnly' => true,
    ];
    use ActivityInjector;
}
