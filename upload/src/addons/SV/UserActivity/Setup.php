<?php


namespace SV\UserActivity;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use SV\RedisCache\Redis;
use XF\Db\Schema\Create;

/**
 * Add-on installation, upgrade, and uninstall routines.
 */
class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public static $defaultGuestGroupId = 1;
    public static $defaultRegisteredGroupId = 2;
    public static $defaultAdminGroupId = 3;
    public static $defaultModeratorGroupId = 4;

    public function installStep1()
    {
        $this->db()->query(
            "INSERT IGNORE INTO xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int) VALUES
            (?, 0, 'RainDD_UA_PermissionsMain', 'RainDD_UA_ThreadViewers', 'allow', '0'),
            (?, 0, 'RainDD_UA_PermissionsMain', 'RainDD_UA_ThreadViewers', 'allow', '0')
        ", [self::$defaultGuestGroupId, self::$defaultRegisteredGroupId]
        );
    }

    public function installStep2()
    {
        $sm = $this->schemaManager();
        $sm->createTable('xf_sv_user_activity', function(Create $table) {
            $table->addColumn('id', 'int')->autoIncrement()->primaryKey();
            $table->addColumn('content_type', 'varbinary', 25);
            $table->addColumn('content_id', 'int');
            $table->addColumn('timestamp', 'int');
            $table->addColumn('blob', 'varbinary', 255);
            $table->addUniqueKey(['content_type','content_id','blob'], 'content');
        });
    }

    public function upgrade2010000Step1()
    {
        $this->installStep2();
    }

    public function uninstallStep1()
    {
        $sm = $this->schemaManager();
        $sm->dropTable('xf_sv_user_activity');

        $this->db()->query(
            "INSERT IGNORE INTO xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int) VALUES
            (?, 0, 'RainDD_UA_PermissionsMain', 'RainDD_UA_ThreadViewers', 'allow', '0'),
            (?, 0, 'RainDD_UA_PermissionsMain', 'RainDD_UA_ThreadViewers', 'allow', '0')
        ", [self::$defaultGuestGroupId, self::$defaultRegisteredGroupId]
        );
    }

    public function checkRequirements(&$errors = [], &$warnings = [])
    {
        /** @var Redis $cache */
        $cache = \XF::app()->cache();
        if (!($cache instanceof Redis) || !($credis = $cache->getCredis(false)))
        {
            $warnings[] = 'It is recommended that Redis Cache to be installed and configured, but a MySQL fallback is supported';
        }
    }
}
