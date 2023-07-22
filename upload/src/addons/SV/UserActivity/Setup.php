<?php

namespace SV\UserActivity;

use SV\StandardLib\InstallerHelper;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use SV\RedisCache\Redis;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use XF\Entity\User;

/**
 * Add-on installation, upgrade, and uninstall routines.
 */
class Setup extends AbstractSetup
{
    use InstallerHelper {
        checkRequirements as protected checkRequirementsTrait;
    }
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1(): void
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->createTable($tableName, $callback);
            $sm->alterTable($tableName, $callback);
        }
    }

    public function installStep2(): void
    {
        $sm = $this->schemaManager();

        foreach ($this->getAlterTables() as $tableName => $callback)
        {
            $sm->alterTable($tableName, $callback);
        }
    }

    public function installStep3(): void
    {
        $this->applyDefaultPermissions();
    }

    public function upgrade2040000Step1(): void
    {
        $this->installStep1();
    }

    public function upgrade2040000Step2(): void
    {
        $this->installStep2();
    }

    public function upgrade1690049433Step1(): void
    {
        $this->renamePermission('RainDD_UA_PermissionsMain', 'RainDD_UA_ThreadViewers', 'svUserActivity', 'viewActivity');
    }

    public function upgrade1690049433Step2(): void
    {
        $this->applyDefaultPermissions(1690049433);
    }

    public function uninstallStep1(): void
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->dropTable($tableName);
        }
    }

    public function uninstallStep2(): void
    {
        $sm = $this->schemaManager();

        foreach ($this->getRemoveAlterTables() as $tableName => $callback)
        {
            if ($sm->tableExists($tableName))
            {
                $sm->alterTable($tableName, $callback);
            }
        }
    }

    protected function getTables(): array
    {
        return [
            'xf_sv_user_activity' => function ($table) {
                /** @var Create|Alter $table */
                $this->addOrChangeColumn($table, 'id', 'int')->autoIncrement()->primaryKey();
                $this->addOrChangeColumn($table, 'content_type', 'varbinary', 25);
                $this->addOrChangeColumn($table, 'content_id', 'int');
                $this->addOrChangeColumn($table, 'timestamp', 'int');
                $this->addOrChangeColumn($table, 'blob', 'varbinary', 255);
                $table->addUniqueKey(['content_type', 'content_id', 'blob'], 'content');
                $table->addKey(['timestamp', 'content_id'], 'timestamp');
            }
        ];
    }

    protected function getAlterTables(): array
    {
        return [];
    }

    protected function getRemoveAlterTables(): array
    {
        return [];
    }

    protected function applyDefaultPermissions(int $previousVersion = 0): bool
    {
        $applied = false;

        if ($previousVersion === 0)
        {
            $this->applyGlobalPermissionByGroup('svUserActivity','viewActivity', [User::GROUP_GUEST, User::GROUP_REG]);
            $applied = true;
        }

        if ($previousVersion < 1690049432)
        {
            $this->applyGlobalPermission('svUserActivity','viewCounters', 'svUserActivity','viewActivity');
            $this->applyGlobalPermission('svUserActivity','viewUsers', 'svUserActivity','viewActivity');
            $applied = true;
        }

        return $applied;
    }

    /**
     * @param array $errors
     * @param array $warnings
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function checkRequirements(&$errors = [], &$warnings = []): void
    {
        $this->checkRequirementsTrait($errors,$warnings);
        /** @var Redis $cache */
        $cache = \XF::app()->cache('userActivity');
        if (!($cache instanceof Redis) || $cache->getCredis(false) === null)
        {
            $warnings[] = 'It is recommended that Redis Cache to be installed and configured, but a MySQL fallback is supported';
        }
    }
}
