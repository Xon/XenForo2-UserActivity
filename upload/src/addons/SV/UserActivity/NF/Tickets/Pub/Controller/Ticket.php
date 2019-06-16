<?php

namespace SV\UserActivity\NF\Tickets\Pub\Controller;


use SV\UserActivity\Repository\UserActivity;
use SV\UserActivity\UserActivityInjector;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;

/**
 * Extends \NF\Tickets\Pub\Controller\Ticket
 */
class Ticket extends XFCP_Ticket
{
//    public function actionView(ParameterBag $params)
//    {
//        $response = parent::actionView($params);
//
//        if ($response instanceof view &&
//            ($ticket = $response->getParam('ticket')))
//        {
//            /** @var \NF\Tickets\Entity\Ticket $ticket */
//            $this->getUserActivityRepo()->pushViewUsageToParent($response, $ticket->, true);
//        }
//
//        return $response;
//    }


    protected function ticketFetcher(
        /** @noinspection PhpUnusedParameterInspection */
        View $response,
        $action,
        array $config)

    {
        /** @var \NF\Tickets\Entity\Ticket $ticket */
        if ($ticket = $response->getParam('ticket'))
        {
            return [$ticket->ticket_id];
        }

        return null;
    }

    protected $activityInjector = [
        'controller' => 'NF\Tickets\Pub\Controller\Ticket',
        'type'       => 'ticket',
        'id'         => 'ticket_id',
        'actions'    => ['view'],
        'activeKey'  => 'nf_ticket',
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