<?php

namespace SV\UserActivity\Repository;

use Credis_Client;
use SV\RedisCache\Redis;
use SV\RedisCache\Repository\Redis as RedisRepo;
use XF\Entity\Node;
use XF\Entity\Thread;
use XF\Entity\User;
use XF\Mvc\Entity\Repository;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View as ViewReply;
use XF\Tree;

class UserActivity extends Repository
{
    protected $handlers      = [];
    protected $logging       = true;
    protected $forceFallback = false;

    public function getSampleInterval(): int
    {
        return 30;
    }

    /** @noinspection SpellCheckingInspection */
    public function supressLogging()
    {
        $this->logging = false;
    }

    public function isLogging(): bool
    {
        return $this->logging;
    }

    /**
     * @param array $handler
     * @return array{controller: string, id: int, type: string, actions: array<string>, activeKey: string}
     */
    protected function validateHandler(array $handler): array
    {
        if (!\array_key_exists('controller', $handler) ||
            !\array_key_exists('id', $handler) ||
            !\array_key_exists('type', $handler) || // Content Rating support rewrites the content type key as required
            !\array_key_exists('actions', $handler) ||
            !\is_array($handler['actions']) ||
            !\array_key_exists('activeKey', $handler))
        {
            $error = "activityInjector is not configured properly, expecting array{controller: string, id: int, type: string, actions: array<string>, activeKey: string} ";
            if (\XF::$debugMode)
            {
                throw new \LogicException($error);
            }
            else
            {
                \XF::logError($error);
            }
        }

        return $handler;
    }

    /**
     * @param string $controllerName
     * @param array{controller: string, id: string, type: string, actions: array<string>, activeKey: string}  $handler
     * @return void
     */
    public function registerHandler(string $controllerName, array $handler)
    {
        $this->handlers[$controllerName] = $this->validateHandler($handler);
    }

    /**
     * @param string $controllerName
     * @return array{controller: string, id: int, type: string, actions: array<string>, activeKey: string}
     */
    public function getHandler(string $controllerName): array
    {
        if (empty($this->handlers[$controllerName]))
        {
            return [];
        }

        return $this->validateHandler($this->handlers[$controllerName]);
    }

    /**
     * @param AbstractReply|\XF\Widget\WidgetRenderer $response
     * @param array         $fetchData
     * @return void
     */
    public function insertBulkUserActivityIntoViewResponse(&$response, array $fetchData)
    {
        $visitor = \XF::visitor();
        if (!$visitor->hasPermission('RainDD_UA_PermissionsMain', 'RainDD_UA_ThreadViewers'))
        {
            return;
        }

        if ($response instanceof ViewReply)
        {
            $response->setParam('UA_RecordCounts', $this->getUsersViewingCount($fetchData));
        }
        else if ($response instanceof \XF\Widget\WidgetRenderer)
        {
            $response->setViewParam('UA_RecordCounts', $this->getUsersViewingCount($fetchData));
        }
    }

    /**
     * @param string        $controllerName
     * @param AbstractReply $response
     * @return void
     */
    public function insertUserActivityIntoViewResponse(string $controllerName, AbstractReply &$response)
    {
        if ($response instanceof ViewReply)
        {
            $handler = $this->getHandler($controllerName);
            $contentType = $handler['type'] ?? null;
            $contentIdField = $handler['id'] ?? null;
            if ($contentType === null || $contentIdField === null)
            {
                return;
            }
            $content = $response->getParam($contentType);
            $contentId = $content[$contentIdField] ?? null;
            if ($contentId === null)
            {
                return;
            }

            $visitor = \XF::visitor();
            $session = \XF::session();
            $isRobot = !$session->isStarted() || $session->get('robot');
            if ($isRobot || !$visitor->hasPermission('RainDD_UA_PermissionsMain', 'RainDD_UA_ThreadViewers'))
            {
                return;
            }
            $records = $this->getUsersViewing($contentType, $contentId, $visitor);
            if (!empty($records))
            {
                $response->setParam('UA_Records', $records);
            }
        }
    }

    /**
     * @return Credis_Client|null
     */
    protected function getCredis()
    {
        if ($this->forceFallback)
        {
            return null;
        }
        $app = $this->app();
        /** @var Redis $cache */
        $cache = $app->cache('userActivity');
        if (!($cache instanceof Redis) || !($credis = $cache->getCredis(false)))
        {
            return null;
        }

        return $credis;
    }

    /**
     * @param array        $data
     * @param float|null $targetRunTime
     * @return array|null
     * @noinspection PhpUnusedParameterInspection
     */
    protected function _garbageCollectActivityFallback(array $data, float $targetRunTime = null)
    {
        $app = $this->app();
        $options = $app->options();
        $onlineStatusTimeout = (int)($options->onlineStatusTimeout ?? 15) * 60;
        $end = \XF::$time - $onlineStatusTimeout;
        $end = $end - ($end % $this->getSampleInterval());

        $db = $this->db();
        $db->query('DELETE FROM `xf_sv_user_activity` WHERE `timestamp` < ?', $end);

        return null;
    }


    /**
     * @param array $data
     * @param float|null  $targetRunTime
     * @return array|null
     */
    public function garbageCollectActivity(array $data, float $targetRunTime = null)
    {
        $credis = $this->getCredis();
        if (!$credis)
        {
            return $this->_garbageCollectActivityFallback($data, $targetRunTime);
        }

        /** @var Redis $cache */
        $cache = $this->app()->cache('userActivity');

        $onlineStatusTimeout = (int)(($options->onlineStatusTimeout ?? 15) * 60);
        $end = \XF::$time - $onlineStatusTimeout;
        $end = $end - ($end % $this->getSampleInterval());

        $cursor = $data['cursor'] ?? null;
        RedisRepo::instance()->visitCacheByPattern('activity_', $cursor, $targetRunTime ?? 0,
            function (\Credis_Client $credis, array $keys) use ($end) {
                $credis->pipeline();
                foreach ($keys as $key)
                {
                    $credis->zRemRangeByScore($key, 0, $end);
                }
                $credis->exec();
            }, 1000, $cache);
        if (!$cursor)
        {
            return null;
        }

        $data['cursor'] = $cursor;

        return $data;
    }

    const LUA_IFZADDEXPIRE_SH1 = 'dc1d76eefaca2f4ccf848a6ed7e80def200ac7b7';

    /**
     * @param array $updateSet
     * @param int   $time
     * @return void
     */
    protected function _updateSessionActivityFallback(array $updateSet, int $time)
    {
        $db = $this->db();

        $sqlParts = [];
        $sqlArgs = [];
        foreach ($updateSet as $record)
        {
            // $record has the format; [content_type, content_id, `blob`]
            $sqlArgs = \array_merge($sqlArgs, $record);
            $sqlArgs[] = $time;
            $sqlParts[] = '(?,?,?,?)';
        }
        $sql = \implode(',', $sqlParts);
        $sql = "-- XFDB=noForceAllWrite
        INSERT INTO xf_sv_user_activity (content_type, content_id, `blob`, `timestamp`) VALUES 
        {$sql}
        ON DUPLICATE KEY UPDATE `timestamp` = values(`timestamp`)";

        try
        {
            $db->query($sql, $sqlArgs);
        }
        catch (\XF\Db\DeadlockException $e)
        {
            // deadlock detected, try rerunning once
            $db->query($sql, $sqlArgs);
        }
    }

    /**
     * @param int    $threadViewType
     * @param string $ip
     * @param string $robotKey
     * @param User   $viewingUser
     * @return array{user_id: int, username: string, visible: bool, robot: ?int, display_style_group_id: int, avatar_date: int, gravatar: string, ip: string }
     */
    protected function buildSessionActivityBlob(int $threadViewType, string $ip, string $robotKey, User $viewingUser): array
    {
        $userId = $viewingUser->user_id;
        $data = [
            'user_id'                => $userId,
            'username'               => $viewingUser->username,
            'visible'                => $viewingUser->visible && $viewingUser->activity_visible ? 1 : null,
            'robot'                  => empty($robotKey) ? null : 1,
            'display_style_group_id' => null,
            'avatar_date'            => null,
            'gravatar'               => null,
            'ip'                     => null,
        ];

        if ($userId !== 0)
        {
            if ($threadViewType === 0)
            {
                $data['display_style_group_id'] = $viewingUser->display_style_group_id;
            }
            else if ($threadViewType === 1)
            {
                $data['avatar_date'] = $viewingUser->avatar_date;
                $data['gravatar'] = $viewingUser->gravatar;
            }
        }
        else
        {
            $data['ip'] = $ip;
        }

        return $data;
    }

    protected function buildSessionActivityUpdateSet(array $trackBuffer, array $updateBlob): array
    {
        // encode the data
        $raw = \implode("\n", $updateBlob);
        $outputSet = [];
        foreach ($trackBuffer as $contentType => $contentIds)
        {
            foreach ($contentIds as $contentId => $val)
            {
                $outputSet[] = [$contentType, $contentId, $raw];
            }
        }

        return $outputSet;
    }

    /**
     * @param array $updateSet
     * @return void
     */
    protected function updateSessionActivity(array $updateSet)
    {
        $score = \XF::$time - (\XF::$time % $this->getSampleInterval());

        $credis = $this->getCredis();
        if (!$credis)
        {
            $this->_updateSessionActivityFallback($updateSet, $score);

            return;
        }
        /** @var Redis $cache */
        $cache = $this->app()->cache('userActivity');
        $useLua = $cache->useLua();

        // record keeping
        $options = \XF::options();
        $onlineStatusTimeout = \max(60, \intval($options->onlineStatusTimeout) * 60);

        // not ideal, but fairly cheap
        // cluster support requires that each `key` potentially be on a separate host
        foreach ($updateSet as &$record)
        {
            // $record has the format; [content_type, content_id, `blob`]
            [$contentType, $contentId, $raw] = $record;
            if ($useLua)
            {
                $key = $cache->getNamespacedId("activity_{$contentType}_{$contentId}");
                $ret = $credis->evalSha(self::LUA_IFZADDEXPIRE_SH1, [$key], [$score, $raw, $onlineStatusTimeout]);
                if ($ret === null)
                {
                    $script =
                        "local c = tonumber(redis.call('zscore', KEYS[1], ARGV[2])) " .
                        "local n = tonumber(ARGV[1]) " .
                        "local retVal = 0 " .
                        "if c == nil or n > c then " .
                        "retVal = redis.call('ZADD', KEYS[1], n, ARGV[2]) " .
                        "end " .
                        "redis.call('EXPIRE', KEYS[1], ARGV[3]) " .
                        "return retVal ";
                    /** @noinspection PhpUnusedLocalVariableInspection */
                    $ret = $credis->eval($script, [$key], [$score, $raw, $onlineStatusTimeout]);
                }
            }
            else
            {
                $credis->pipeline()->multi();
                // O(log(N)) for each item added, where N is the number of elements in the sorted set.
                $key = $cache->getNamespacedId("activity_{$contentType}_{$contentId}");
                $credis->zAdd($key, $score, $raw);
                $credis->expire($key, $onlineStatusTimeout);
                $credis->exec();
            }
        }
    }

    const CacheKeys = [
        'user_id',
        'username',
        'visible',
        'robot',
        'display_style_group_id',
        'avatar_date',
        'gravatar',
        'ip',
    ];

    /**
     * @param string  $contentType
     * @param int $contentId
     * @param int $start
     * @param int $end
     * @return array
     * @noinspection PhpUnusedParameterInspection
     */
    protected function _getUsersViewingFallback(string $contentType, int $contentId, int $start, int $end): array
    {
        $db = $this->db();
        $raw = $db->fetchAll('
            SELECT * 
            FROM xf_sv_user_activity 
            WHERE content_type = ? AND content_id = ? AND `timestamp` >= ? 
            ORDER BY `timestamp` DESC
        ', [$contentType, $contentId, $start]);

        $records = [];
        foreach ($raw as $row)
        {
            $records[$row['blob']] = $row['timestamp'];
        }

        return $records;
    }

    //records: array{user_id: int, username: string, visible: bool, robot: ?int, display_style_group_id: int, avatar_date: int, gravatar: string, ip: string }
    /**
     * @param string $contentType
     * @param int    $contentId
     * @param User   $viewingUser
     * @return array{total: int, members: int, guests: int, robots: int, recordsUnseen: int, records: array}
     */
    protected function getUsersViewing(string $contentType, int $contentId, User $viewingUser): array
    {
        $isGuest = $viewingUser->user_id === 0;
        if ($isGuest)
        {
            $memberCount = 0;
            $guestCount = 1;
        }
        else
        {
            $memberCount = 1;
            $guestCount = 0;
        }
        $robotCount = 0;
        $records = [];
        if (!$isGuest)
        {
            $rec = [];
            $structure = $viewingUser->structure();
            foreach(self::CacheKeys as $key)
            {
                if (isset($structure->columns[$key]))
                {
                    $rec[$key] = $viewingUser[$key];
                }
            }
            // XF2 does not do effective_last_activity, so emulate it
            $rec['effective_last_activity'] = \XF::$time;
            $records[] = $rec;
        }

        $app = $this->app();
        $options = $app->options();
        $start = \XF::$time - (int)($options->onlineStatusTimeout ?? 15) * 60;
        $start = $start - ($start % $this->getSampleInterval());
        $end = \XF::$time + 1;
        $pruneChance = (float)($options->UA_pruneChance ?? 0.1);

        $credis = $this->getCredis();
        if (!$credis)
        {
            $onlineRecords = $this->_getUsersViewingFallback($contentType, $contentId, $start, $end);
            // check if the activity counter needs pruning
            if ($pruneChance > 0 && \mt_rand() < $pruneChance)
            {
                $this->_garbageCollectActivityFallback([]);
            }
        }
        else
        {
            /** @var Redis $cache */
            $cache = $app->cache('userActivity');
            $key = $cache->getNamespacedId("activity_{$contentType}_{$contentId}");

            $onlineRecords = $credis->zRevRangeByScore($key, $end, $start, ['withscores' => true]);
            // check if the activity counter needs pruning
            if ($pruneChance > 0 && \mt_rand() < $pruneChance)
            {
                $fillFactor = (float)($options->UA_fillFactor ?? 1.2);
                $credis = $cache->getCredis(false);
                if ($credis->zCard($key) >= \count($onlineRecords) * $fillFactor)
                {
                    // O(log(N)+M) with N being the number of elements in the sorted set and M the number of elements removed by the operation.
                    $credis->zRemRangeByScore($key, 0, $start - 1);
                }
            }
        }

        $cutoff = (int)($options->SV_UA_Cutoff ?? 250);
        $memberVisibleCount = $isGuest ? 0 : 1;
        $recordsUnseen = 0;

        if (\is_array($onlineRecords))
        {
            $seen = [$viewingUser->user_id => true];
            $bypassUserPrivacy = $viewingUser->canBypassUserPrivacy();
            $sampleInterval = $this->getSampleInterval();

            foreach ($onlineRecords as $rec => $score)
            {
                $data = \explode("\n", $rec);
                try
                {
                    $rec = @\array_combine(self::CacheKeys, $data);
                }
                catch(\ValueError $e)
                {
                    $rec = null;
                }
                if (empty($rec))
                {
                    continue;
                }
                if ($rec['user_id'])
                {
                    if (empty($seen[$rec['user_id']]))
                    {
                        $seen[$rec['user_id']] = true;
                        $memberCount += 1;
                        if (!empty($rec['visible']) || $bypassUserPrivacy)
                        {
                            $memberVisibleCount += 1;
                            if ($cutoff > 0 && $memberVisibleCount > $cutoff)
                            {
                                $recordsUnseen += 1;
                                continue;
                            }
                            $score = $score - ($score % $sampleInterval);
                            $rec['effective_last_activity'] = $score;
                            $records[] = $rec;
                        }
                        else
                        {
                            $recordsUnseen += 1;
                        }
                    }
                }
                else if (empty($rec['robot']))
                {
                    $guestCount += 1;
                }
                else
                {
                    $robotCount += 1;
                }
            }
        }

        return [
            'total'         => $memberCount + $guestCount,
            'members'       => $memberCount,
            'guests'        => $guestCount,
            'robots'        => $robotCount,
            'records'       => $records,
            'recordsUnseen' => $recordsUnseen,
        ];
    }

    /**
     * @param array $fetchData
     * @param int   $start
     * @param int   $end
     * @return array
     * @noinspection PhpUnusedParameterInspection
     */
    protected function _getUsersViewingCountFallback(array $fetchData, int $start, int $end): array
    {
        $db = $this->db();

        $args = [$start];
        $sql = [];
        foreach ($fetchData as $contentType => $list)
        {
            $list = \array_filter(\array_map('\intval', \array_unique($list)));
            if ($list)
            {
                $sql[] = "\n(content_type = " . $db->quote($contentType) . " AND content_id in (" . $db->quote($list) . "))";
            }
        }

        if (!$sql)
        {
            return [];
        }

        $sql = join(' OR ', $sql);

        $raw = $db->fetchAll(
            "SELECT content_type, content_id, count(*) as count
                  FROM xf_sv_user_activity 
                  WHERE `timestamp` >= ?  AND ({$sql})
                  group by content_type, content_id",
            $args
        );

        $records = [];
        foreach ($raw as $row)
        {
            $records[$row['content_type']][$row['content_id']] = $row['count'];
        }

        return $records;
    }

    /**
     * @param array $fetchData
     * @return array
     */
    protected function getUsersViewingCount(array $fetchData): array
    {
        $app = $this->app();
        $options = $app->options();
        $start = \XF::$time - $options->onlineStatusTimeout * 60;
        $start = $start - ($start % $this->getSampleInterval());
        $end = \XF::$time + 1;

        $credis = $this->getCredis();
        $pruneChance = (float)($options->UA_pruneChance ?? 0.1);
        if (!$credis)
        {
            $onlineRecords = $this->_getUsersViewingCountFallback($fetchData, $start, $end);
            // check if the activity counter needs pruning
            if ($pruneChance > 0 && \mt_rand() < $pruneChance)
            {
                $this->_garbageCollectActivityFallback([]);
            }
        }
        else
        {
            /** @var Redis $cache */
            $cache = $app->cache('userActivity');
            $credis = $this->getCredis();
            /** @noinspection PhpUnusedLocalVariableInspection */
            $useLua = $cache->useLua();

            $onlineRecords = [];
            $args = [];
            foreach ($fetchData as $contentType => $list)
            {
                $list = \array_filter(\array_map('\intval', \array_unique($list)));
                foreach ($list as $contentId)
                {
                    $args[] = [$contentType, $contentId];
                }
            }

            /** @noinspection PhpStatementHasEmptyBodyInspection */
            if (false) //$useLua
            {
                /*
                $ret = $credis->evalSha(self::LUA_IFZADDEXPIRE_SH1, [$key], [$score, $raw, $onlineStatusTimeout]);
                if ($ret === null)
                {
                    $script = "";
                    $credis->eval($script, [$key], [$score, $raw, $onlineStatusTimeout]);
                }
                */
            }
            else
            {
                $credis->pipeline()->multi();
                foreach ($args as $row)
                {
                    $key = $cache->getNamespacedId("activity_{$row[0]}_{$row[1]}");
                    $credis->zCount($key, $start, $end);
                }
                $ret = $credis->exec();
                foreach ($args as $i => $row)
                {
                    $val = \intval($ret[$i]);
                    if ($val)
                    {
                        $onlineRecords[$row[0]][$row[1]] = $val;
                    }
                }
            }
        }

        return $onlineRecords;
    }

    protected $trackBuffer = [];

    /**
     * @param string $contentType
     * @param int    $contentId
     * @param string $activeKey
     * @return void
     */
    public function bufferTrackViewerUsage(string $contentType, int $contentId, string $activeKey)
    {
        if (\strlen($contentType) === 0 ||
            $contentId === 0 ||
            \strlen($activeKey) === 0 ||
            !$this->isLogging())
        {
            return;
        }
        $options = \XF::options();
        if (empty($options->svUAPopulateUsers[$activeKey]))
        {
            return;
        }
        $this->trackBuffer[$contentType][$contentId] = true;
    }

    /**
     * @param string|null $ip
     * @param string|null $robotKey
     * @param User|null   $viewingUser
     * @return void
     */
    public function flushTrackViewerUsageBuffer(string $ip = null, string $robotKey = null, User $viewingUser = null)
    {
        if (!$this->isLogging() || \count($this->trackBuffer) === 0)
        {
            return;
        }

        if ($robotKey === null)
        {
            $session = \XF::session();
            $robotKey = $session->isStarted() ? $session->get('robot') : true;
        }
        if ($viewingUser === null)
        {
            $viewingUser = \XF::visitor();
        }
        $options = \XF::options();

        if (empty($robotKey) || ($options->SV_UA_TrackRobots ?? false))
        {
            $threadViewType = (int)($options->RainDD_UA_ThreadViewType ?? 0);
            $blob = $this->buildSessionActivityBlob($threadViewType, $ip, $robotKey, $viewingUser);
            if (!$blob)
            {
                return;
            }

            $updateSet = $this->buildSessionActivityUpdateSet($this->trackBuffer, $blob);
            $this->trackBuffer = [];
            if ($updateSet)
            {
                $this->updateSessionActivity($updateSet);
            }
        }
    }

    public function flattenTreeToDepth(Tree $tree, int $depth): array
    {
        $nodes = [];
        $flattenedNodeList = $tree->getFlattened();
        foreach ($flattenedNodeList as $id => $node)
        {
            if ($node['depth'] <= $depth)
            {
                $nodes[] = $id;
            }
        }

        return $nodes;
    }

    /**
     * @param int[] $nodeIds
     * @return int[]
     */
    public function getFilteredForumNodeIds(array $nodeIds): array
    {
        if (\count($nodeIds) === 0)
        {
            return [];
        }

        $visitor = \XF::visitor();
        $visitor->cacheNodePermissions();
        $permissionSet = $visitor->PermissionSet;
        $filteredNodeIds = [];
        foreach ($nodeIds as $nodeId)
        {
            if ($permissionSet->hasContentPermission('node', $nodeId, 'view') &&
                $permissionSet->hasContentPermission('node', $nodeId, 'viewOthers') &&
                $permissionSet->hasContentPermission('node', $nodeId, 'viewContent'))
            {
                $filteredNodeIds[] = $nodeId;
            }
        }

        return $filteredNodeIds;
    }

    /**
     * @param int[] $nodeIds
     * @return int[]
     */
    public function getFilteredCategoryNodeIds(array $nodeIds): array
    {
        if (!$nodeIds)
        {
            return [];
        }

        $visitor = \XF::visitor();
        $visitor->cacheNodePermissions();
        $permissionSet = $visitor->PermissionSet;
        $filteredNodeIds = [];
        foreach ($nodeIds as $nodeId)
        {
            if ($permissionSet->hasContentPermission('node', $nodeId, 'view'))
            {
                $filteredNodeIds[] = $nodeId;
            }
        }

        return $filteredNodeIds;
    }

    /**
     * @param array  $params
     * @param string $key
     * @return int[]
     */
    public function getFilteredThreadIds(array $params, string $key): array
    {
        if (empty($params[$key]))
        {
            return [];
        }
        /** @var Thread[] $threads */
        $threads = $params[$key];

        $visitor = \XF::visitor();
        $userId = $visitor->user_id;
        $visitor->cacheNodePermissions();
        $permissionSet = $visitor->PermissionSet;
        $threadIds = [];
        foreach ($threads as $thread)
        {
            $nodeId = $thread->node_id;
            if ($permissionSet->hasContentPermission('node', $nodeId, 'view') &&
                $permissionSet->hasContentPermission('node', $nodeId, 'viewContent') &&
                ($permissionSet->hasContentPermission('node', $nodeId, 'viewContent') || ($userId && $thread->user_id === $userId)))
            {
                $threadIds[] = $thread->thread_id;
            }
        }

        return $threadIds;
    }

    /**
     * @param ViewReply $response
     * @param Node      $node
     * @param bool      $pushToNode
     * @param string[]  $keys
     * @return void
     */
    public function pushViewUsageToParent(ViewReply $response, Node $node, bool $pushToNode = false, array $keys = ['forum'])
    {
        $options = \XF::options();
        foreach($keys as $key)
        {
            if (empty($options->svUAPopulateUsers[$key]))
            {
                return;
            }
        }

        if ($response->getParam('node') === null)
        {
            $response->setParam('node', $node);
        }

        $session = \XF::session();
        $isRobot = !$session->isStarted() || $session->get('robot');
        if ($isRobot)
        {
            if (!($options->SV_UA_TrackRobots ?? false))
            {
                return;
            }
        }
        else if (\XF::visitor()->user_id === 0)
        {
            if (!($options->svUserActivityTrackGuests ?? false))
            {
                return;
            }
        }

        $nodeTrackLimit = (int)($options->svUAThreadNodeTrackLimit ?? 1);
        $nodeTrackLimit = $nodeTrackLimit < 0 ? PHP_INT_MAX : $nodeTrackLimit;

        /** @var  UserActivity $repo */
        $repo = \XF::repository('SV\UserActivity:UserActivity');
        if ($nodeTrackLimit > 0)
        {
            if ($pushToNode)
            {
                $repo->bufferTrackViewerUsage('node', $node->node_id, 'forum');
                if ($nodeTrackLimit === 1)
                {
                    return;
                }
            }
            $count = 1;
            if ($node->breadcrumb_data)
            {
                foreach ($node->breadcrumb_data AS $crumb)
                {
                    if ($crumb['node_type_id'] === 'Forum')
                    {
                        $repo->bufferTrackViewerUsage('node', $crumb['node_id'], 'forum');
                        $count++;
                        if ($count > $nodeTrackLimit)
                        {
                            break;
                        }
                    }
                    else if ($crumb['node_type_id'] === 'Category')
                    {
                        $repo->bufferTrackViewerUsage('node', $crumb['node_id'], 'category');
                        $count++;
                        if ($count > $nodeTrackLimit)
                        {
                            break;
                        }
                    }
                }
            }
        }
    }
}
