<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\ActivityInjector;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;

class Thread extends XFCP_Thread
{
    public function actionIndex(ParameterBag $params)
    {
        $response = parent::actionIndex($params);

        $options = \XF::options();
        if ($response instanceof View &&
            $options->svUATrackForum &&
            ($thread = $response->getParam('thread')))
        {
            $nodeId = $thread['node_id'];

            /** @var \SV\UserActivity\Repository\UserActivity $userActivityRepo */
            $userActivityRepo = \XF::repository('SV\UserActivity:UserActivity');
            if ($nodeId && $userActivityRepo->isLogging())
            {
                $session = \XF::session();
                $robotKey = isset($session['robotId']) ? $session['robotId'] : '';
                $ip = $this->request->getIp();

                if ($options->SV_UA_TrackRobots || empty($robotKey))
                {
                    $visitor = \XF::visitor();
                    $userActivityRepo->updateSessionActivity('node', $nodeId, $ip, $robotKey, $visitor);
                }
            }
        }

        return $response;
    }

    protected $activityInjector = [
        'controller' => 'XF\Pub\Controller\Thread',
        'type' => 'thread',
        'id' => 'thread_id',
        'actions' => ['index'],
    ];
    use ActivityInjector;
}
