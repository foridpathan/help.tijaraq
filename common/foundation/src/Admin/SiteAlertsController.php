<?php

namespace Common\Admin;

use Common\Core\BaseController;
use Common\Logging\Schedule\ScheduleLogItem;

class SiteAlertsController extends BaseController
{
    public function __construct()
    {
        $this->middleware('isAdmin');
    }

    public function index()
    {
        $alerts = [];

        if (!config('app.demo')) {
            if (!ScheduleLogItem::scheduleRanInLast30Minutes()) {
                $alerts[] = [
                    'id' => 'cronNotSetup',
                    'title' => 'There is an issue with CRON schedule',
                    'severity' => 'error',
                    'description' =>
                        'The CRON schedule has not run in the last 30 minutes. Add a cron entry that runs "php artisan schedule:run" every minute (see docs/helpdesk-deploy.md).',
                ];
            }
        }

        return $this->success([
            'alerts' => $alerts,
        ]);
    }
}
