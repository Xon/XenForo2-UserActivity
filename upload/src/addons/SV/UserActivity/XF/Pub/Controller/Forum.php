<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\ActivityInjector;
use XF\App;
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

    public function actionList(ParameterBag $params)
    {
        return $this->injectResponse(parent::actionList($params));
    }

    public function actionForum(ParameterBag $params)
    {
        return $this->injectResponse(parent::actionForum($params));
    }

    /**
     * @param AbstractReply $response
     * @return AbstractReply
     */
    public function injectResponse($response)
    {
        if ($response instanceof View && !$response->getParam('touchedUA'))
        {
            $response->setParam('touchedUA', true);
            $fetchData = [];
            $options = \XF::options();

            /** @noinspection PhpUndefinedFieldInspection */
            if ($options->svUATrackForum)
            {
                $fetchData['node'] = [];
                if ($nodeExtras = $response->getParam('nodeExtras'))
                {
                    $fetchData['node'] = array_keys($nodeExtras);
                }
                /** @var \XF\Entity\Forum $forum */
                if ($forum = $response->getParam('forum'))
                {
                    $fetchData['node'][] = $forum->node_id;
                }
            }

            /** @noinspection PhpUndefinedFieldInspection */
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

        return $response;
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
