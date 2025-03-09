<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\StandardLib\Helper;
use SV\UserActivity\Repository\UserActivity as UserActivityRepo;
use SV\UserActivity\UserActivityInjector;
use XF\Entity\Post as PostEntity;
use XF\Entity\Thread as ThreadEntity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View as ViewReply;
use function strtolower;

class Post extends XFCP_Post
{
    protected $activityInjector = [
        'type'       => 'thread',
        'id'         => 'thread_id',
        'actions'    => [],
        'activeKey'  => 'thread',
    ];
    use UserActivityInjector;

    /** @noinspection PhpMissingReturnTypeInspection */
    protected function canUpdateSessionActivity($action, ParameterBag $params, AbstractReply &$reply, &$viewState)
    {
        if ($reply instanceof ViewReply && $reply->getResponseCode() < 400)
        {
            $actionL = strtolower($action);
            switch ($actionL)
            {
                case 'react':
                case 'reactions':
                case 'threadmark':
                    $viewState = 'valid';

                    // note; these are often ajax operation that we still want to consider as valid
                    // $this->request is used and not $params as this requires less data to be logged into the xf_session_activity table
                    $threadId = (int)$this->request->get('thread_id', 0);
                    $thread = Helper::findCached(ThreadEntity::class, $threadId);
                    if ($thread !== null)
                    {
                        UserActivityRepo::get()->pushViewUsageToParent($reply, $thread->Forum->Node);
                    }

                    return true;
            }
        }

        return parent::canUpdateSessionActivity($action, $params, $reply, $viewState);
    }

    protected function svInjectThreadIdIntoRequest(ViewReply $reply, ParameterBag $params)
    {
        $postId = (int)$params->get('post_id', 0);
        if ($postId !== 0)
        {
            $post = Helper::findCached(PostEntity::class, $postId);
            if ($post !== null)
            {
                $this->request->set('thread_id', $post->thread_id);

                return;
            }
        }

        $post = $reply->getParam('post');
        $threadId = (int)($post['thread_id'] ?? 0);
        if ($threadId !== 0)
        {
            $this->request->set('thread_id', $threadId);

            return;
        }

        $thread = $reply->getParam('thread');
        $threadId = (int)($thread['thread_id'] ?? 0);
        if ($threadId !== 0)
        {
            $this->request->set('thread_id', $threadId);
        }
    }

    protected function updateSessionActivity($action, ParameterBag $params, AbstractReply &$reply)
    {
        if ($reply instanceof ViewReply &&
            $this->request->get('thread_id') === false)
        {
            // $reply should be Error if they don't have permission.
            // if the entity is cached, then this request returned something useful
            $this->svInjectThreadIdIntoRequest($reply, $params);
        }

        parent::updateSessionActivity($action, $params, $reply);
    }
}
