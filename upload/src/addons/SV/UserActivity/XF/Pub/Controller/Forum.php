<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\ActivityInjector;
use SV\UserActivity\ForumActivityInjector;
use XF\App;
use XF\Http\Request;

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

    protected $forumActivityInjector = [
        'list' => [
            'depth' => 1,
        ],
        'forum' => [
            'depth' => 0,
        ],
    ];
    use ForumActivityInjector;

    protected $activityInjector = [
        'controller' => 'XF\Pub\Controller\Forum',
        'type'       => 'node',
        'id'         => 'node_id',
        'actions'    => [], // deliberate, as we do our own thing to inject content
        'countsOnly' => true,
    ];
    use ActivityInjector;
}
