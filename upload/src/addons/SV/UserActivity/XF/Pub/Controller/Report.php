<?php

namespace SV\UserActivity\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Report extends XFCP_Report
{
    const CONTROLLER_NAME = 'XF\Pub\Controller\Report';

    protected function preDispatchController($action, ParameterBag $params)
    {
        $userActivityRepo = $this->app->repository('SV\UserActivity\Repository\UserActivity');
        $userActivityRepo->registerHandler(self::CONTROLLER_NAME, 'report', 'report_id');
        return parent::preDispatchController($action, $params);
    }

    public function actionView(ParameterBag $params)
    {
        $response = parent::actionView($params);
        $userActivityRepo = $this->app->repository('SV\UserActivity\Repository\UserActivity');
        $userActivityRepo->insertUserActivityIntoViewResponse(self::CONTROLLER_NAME, $response);
        return $response;
    }
}