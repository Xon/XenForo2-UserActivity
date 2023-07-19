<?php

namespace SV\UserActivity\NF\Tickets\Pub\Controller;


use SV\UserActivity\Repository\UserActivity as UserActivityRepo;
use SV\UserActivity\UserActivityInjector;
use XF\Mvc\Reply\View as ViewReply;

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


    /** @noinspection PhpUnusedParameterInspection */
    protected function ticketFetcher(ViewReply $response, string $action, array $config): array
    {
        $ticket = $response->getParam('ticket');
        if ($ticket instanceof \NF\Tickets\Entity\Ticket)
        {
            return [$ticket->ticket_id];
        }

        return [];
    }

    protected $activityInjector = [
        'controller' => 'NF\Tickets\Pub\Controller\Ticket',
        'type'       => 'ticket',
        'id'         => 'ticket_id',
        'actions'    => ['view'],
        'activeKey'  => 'nf_ticket',
    ];
    use UserActivityInjector;

    protected function getUserActivityRepo(): UserActivityRepo
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->repository('SV\UserActivity:UserActivity');
    }
}