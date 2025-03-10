<?php

namespace SV\UserActivity\Repository;

use Credis_Client;
use SV\RedisCache\Redis;
use SV\RedisCache\Repository\Redis as RedisRepo;
use SV\StandardLib\Helper;
use SV\UserActivity\Repository\UserActivity as UserActivityRepo;
use XF\Db\DeadlockException;
use XF\Entity\Node as NodeEntity;
use XF\Entity\Thread as ThreadEntity;
use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\Repository;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View as ViewReply;
use XF\Tree;
use XF\Widget\WidgetRenderer;
use function array_combine;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_merge;
use function array_unique;
use function count;
use function explode;
use function implode;
use function is_array;
use function max;
use function strlen;

class UserActivity extends Repository
{
    protected $handlers      = [];
    protected $logging       = true;
    protected $forceFallback = false;

    public static function get(): self
    {
        return Helper::repository(self::class);
    }

    public function getMergedExisting(array $list, $existing): array
    {
        if (!is_array($existing))
        {
            return $list;
        }

        foreach ($existing as $name => $value)
        {
            if (!array_key_exists($name, $list))
            {
                $list[$name] = $name;
            }
        }

        return $list;
    }

    public function getDisplayCounts(): array
    {
        return [
            'thread' => \XF::phrase('svUserActivity_display_counts.thread'),
            'thread-view' => \XF::phrase('svUserActivity_display_counts.thread_view'),
            'sticky-thread' => \XF::phrase('svUserActivity_display_counts.sticky_thread'),
            'index-forum' => \XF::phrase('svUserActivity_display_counts.index_forum'),
            'index-category' => \XF::phrase('svUserActivity_display_counts.index_category'),
            'forum' => \XF::phrase('svUserActivity_display_counts.forum'),
            'search-forum' => \XF::phrase('svUserActivity_display.search_forum'),
            'category-view' => \XF::phrase('svUserActivity_display_counts.category_view'),
            'similar-threads' => \XF::phrase('svUserActivity_display_counts.similar_threads'),
            'sub-forum' => \XF::phrase('svUserActivity_display_counts.sub_forum'),
            'find-new' => \XF::phrase('svUserActivity_display_counts.find_new'),
            'watched-forums' => \XF::phrase('svUserActivity_display_counts.watched_forums'),
            'watched-threads' => \XF::phrase('svUserActivity_display_counts.watched_threads'),
            'conversation' => \XF::phrase('svUserActivity_display_counts.conversation'),
            'report' => \XF::phrase('svUserActivity_display_counts.report'),
            'report-list' => \XF::phrase('svUserActivity_display_counts.report_list'),
        ];
    }

    public function getDisplayUsers(): array
    {
        return [
            'thread' => \XF::phrase('svUserActivity_display.thread'),
            'forum' => \XF::phrase('svUserActivity_display.forum'),
            'search-forum' => \XF::phrase('svUserActivity_display.search_forum'),
            'conversation' => \XF::phrase('svUserActivity_display.conversation'),
            'report' => \XF::phrase('svUserActivity_display.report'),
            'nf_ticket' => \XF::phrase('svUserActivity_display.nf_ticket'),
            'nf_calendar' => \XF::phrase('svUserActivity_display.nf_calendar'),
        ];
    }

    public function getPopulateUsers(): array
    {
        return [
            'thread' => \XF::phrase('svUserActivity_display.thread'),
            'forum' => \XF::phrase('svUserActivity_display.forum'),
            'search-forum' => \XF::phrase('svUserActivity_display.search_forum'),
            'category' => \XF::phrase('svUserActivity_display.category'),
            'conversation' => \XF::phrase('svUserActivity_display.conversation'),
            'report' => \XF::phrase('svUserActivity_display.report'),
            'nf_ticket' => \XF::phrase('svUserActivity_display.nf_ticket'),
            'nf_calendar' => \XF::phrase('svUserActivity_display.nf_calendar'),
        ];
    }

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
        if (!array_key_exists('controller', $handler) ||
            !array_key_exists('id', $handler) ||
            !array_key_exists('type', $handler) || // Content Rating support rewrites the content type key as required
            !array_key_exists('actions', $handler) ||
            !is_array($handler['actions']) ||
            !array_key_exists('activeKey', $handler))
        {
            $error = 'activityInjector is not configured properly, expecting array{controller: string, id: int, type: string, actions: array<string>, activeKey: string} ';
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
    public function registerHandler(string $controllerName, array $handler): void
    {
        $this->handlers[$controllerName] = $this->validateHandler($handler);
    }

    /**
     * @param string $controllerName
     * @return array{controller: string, id: int, type: string, actions: array<string>, activeKey: string}
     */
    public function getHandler(string $controllerName): array
    {
        $handler = $this->handlers[$controllerName] ?? null;

        return $handler ? $this->validateHandler($handler) : [];
    }

    /**
     * @param AbstractReply|WidgetRenderer $response
     * @param array                        $fetchData
     * @return void
     */
    public function insertBulkUserActivityIntoViewResponse(&$response, array $fetchData): void
    {
        $visitor = \XF::visitor();
        if (!$visitor->hasPermission('svUserActivity', 'viewActivity'))
        {
            return;
        }

        if ($response instanceof ViewReply)
        {
            $response->setParam('UA_RecordCounts', $this->getUsersViewingCount($fetchData));
        }
        else if ($response instanceof WidgetRenderer)
        {
            $response->setViewParam('UA_RecordCounts', $this->getUsersViewingCount($fetchData));
        }
    }

    /**
     * @param string        $controllerName
     * @param AbstractReply $response
     * @return void
     */
    public function insertUserActivityIntoViewResponse(string $controllerName, AbstractReply &$response): void
    {
        if ($response instanceof ViewReply)
        {
            $visitor = \XF::visitor();
            if (!$visitor->hasPermission('svUserActivity', 'viewActivity'))
            {
                return;
            }

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

            $session = \XF::session();
            $isRobot = !$session->isStarted() || $session->get('robot');
            if ($isRobot)
            {
                return;
            }
            $fetchUserList = (bool)$visitor->hasPermission('svUserActivity', 'viewUsers');

            $records = $this->getUsersViewing($contentType, $contentId, $visitor, $fetchUserList);
            if (!empty($records))
            {
                $response->setParam('UA_Records', $records);
            }
        }
    }

    public function getRedisConnector(): ?Redis
    {
        if ($this->forceFallback)
        {
            return null;
        }
        return \XF::isAddOnActive('SV/RedisCache') ? RedisRepo::get()->getRedisConnector('userActivity') : null;
    }

    /**
     * @param array        $data
     * @param float|null $targetRunTime
     * @return array|null
     * @noinspection PhpUnusedParameterInspection
     */
    protected function _garbageCollectActivityFallback(array $data, ?float $targetRunTime = null): ?array
    {
        $options = \XF::options();
        $onlineStatusTimeout = ($options->onlineStatusTimeout ?? 15) * 60;
        $end = \XF::$time - $onlineStatusTimeout;
        $end = $end - ($end % $this->getSampleInterval());

        $db = \XF::db();
        $db->query('DELETE FROM `xf_sv_user_activity` WHERE `timestamp` < ?', $end);

        return null;
    }


    /**
     * @param array $data
     * @param float|null  $targetRunTime
     * @return array|null
     */
    public function garbageCollectActivity(array $data, ?float $targetRunTime = null): ?array
    {
        $redis = $this->getRedisConnector();
        if ($redis === null)
        {
            return $this->_garbageCollectActivityFallback($data, $targetRunTime);
        }

        $onlineStatusTimeout = (int)(($options->onlineStatusTimeout ?? 15) * 60);
        $end = \XF::$time - $onlineStatusTimeout;
        $end = $end - ($end % $this->getSampleInterval());

        $cursor = $data['cursor'] ?? null;
        RedisRepo::get()->visitCacheByPattern('activity_', $cursor, $targetRunTime ?? 0,
            function (Credis_Client $credis, array $keys) use ($end) {
                $credis->pipeline();
                foreach ($keys as $key)
                {
                    $credis->zRemRangeByScore($key, 0, $end);
                }
                $credis->exec();
            }, 1000, $redis);
        if (!$cursor)
        {
            return null;
        }

        $data['cursor'] = $cursor;

        return $data;
    }

    protected const LUA_IFZADDEXPIRE_SH1 = 'dc1d76eefaca2f4ccf848a6ed7e80def200ac7b7';
    protected const LUA_IFZADDEXPIRE_SCRIPT = "local c = tonumber(redis.call('zscore', KEYS[1], ARGV[2])) " .
                                              'local n = tonumber(ARGV[1]) ' .
                                              'local retVal = 0 ' .
                                              'if c == nil or n > c then ' .
                                              "retVal = redis.call('ZADD', KEYS[1], n, ARGV[2]) " .
                                              'end ' .
                                              "redis.call('EXPIRE', KEYS[1], ARGV[3]) " .
                                              'return retVal ';

    /**
     * @param array $updateSet
     * @param int   $time
     * @return void
     */
    protected function _updateSessionActivityFallback(array $updateSet, int $time): void
    {
        $db = \XF::db();

        $sqlParts = [];
        $sqlArgs = [];
        foreach ($updateSet as $record)
        {
            // $record has the format; [content_type, content_id, `blob`]
            $sqlArgs = array_merge($sqlArgs, $record);
            $sqlArgs[] = $time;
            $sqlParts[] = '(?,?,?,?)';
        }
        $sql = implode(',', $sqlParts);
        $sql = "-- XFDB=noForceAllWrite
        INSERT INTO xf_sv_user_activity (content_type, content_id, `blob`, `timestamp`) VALUES 
        {$sql}
        ON DUPLICATE KEY UPDATE `timestamp` = values(`timestamp`)";

        try
        {
            $db->query($sql, $sqlArgs);
        }
        catch (DeadlockException $e)
        {
            // deadlock detected, try rerunning once
            $db->query($sql, $sqlArgs);
        }
    }

    /**
     * @param int        $threadViewType
     * @param string     $ip
     * @param string     $robotKey
     * @param UserEntity $viewingUser
     * @return array{user_id: int, username: string, visible: bool, robot: ?int, display_style_group_id: int, avatar_date: int, gravatar: string, ip: string }
     */
    protected function buildSessionActivityBlob(int $threadViewType, string $ip, string $robotKey, UserEntity $viewingUser): array
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
        $raw = implode("\n", $updateBlob);
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
    protected function updateSessionActivity(array $updateSet): void
    {
        $score = \XF::$time - (\XF::$time % $this->getSampleInterval());

        $redis = $this->getRedisConnector();
        if ($redis === null)
        {
            $this->_updateSessionActivityFallback($updateSet, $score);

            return;
        }
        $credis = $redis->getCredis(false);

        // record keeping
        $onlineStatusTimeout = (int)max(60, \XF::options()->onlineStatusTimeout * 60);

        // not ideal, but fairly cheap
        // cluster support requires that each `key` potentially be on a separate host
        foreach ($updateSet as &$record)
        {
            // $record has the format; [content_type, content_id, `blob`]
            [$contentType, $contentId, $raw] = $record;

            $key = $redis->getNamespacedId("activity_{$contentType}_{$contentId}");
            $ret = $credis->evalSha(self::LUA_IFZADDEXPIRE_SH1, [$key], [$score, $raw, $onlineStatusTimeout]);
            if ($ret === null)
            {
                /** @noinspection PhpUnusedLocalVariableInspection */
                $ret = $credis->eval(self::LUA_IFZADDEXPIRE_SCRIPT, [$key], [$score, $raw, $onlineStatusTimeout]);
            }
        }
    }

    protected const CacheKeys = [
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
        $db = \XF::db();
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
     * @param string     $contentType
     * @param int        $contentId
     * @param UserEntity $viewingUser
     * @param bool       $fetchUserList
     * @return array{total: int, members: int, guests: int, robots: int, recordsUnseen: int, records: array}
     */
    protected function getUsersViewing(string $contentType, int $contentId, UserEntity $viewingUser, bool $fetchUserList): array
    {
        $options = \XF::options();
        $cutoff = (int)(max(-1, $options->SV_UA_Cutoff ?? 250));
        if (!$fetchUserList)
        {
            $cutoff = -1;
        }

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
        if (!$isGuest && $cutoff !== -1)
        {
            $rec = [];
            $structure = $viewingUser->structure();
            foreach(self::CacheKeys as $key)
            {
                if (array_key_exists($key, $structure->columns))
                {
                    $rec[$key] = $viewingUser[$key];
                }
            }
            // XF2 does not do effective_last_activity, so emulate it
            $rec['effective_last_activity'] = \XF::$time;
            $records[] = $rec;
        }

        $start = \XF::$time - ($options->onlineStatusTimeout ?? 15) * 60;
        $start = $start - ($start % $this->getSampleInterval());
        $end = \XF::$time + 1;
        $pruneChance = $options->UA_pruneChance ?? 0.1;

        $redis = $this->getRedisConnector();
        if ($redis === null)
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
            $credis = $redis->getCredis(true);
            $key = $redis->getNamespacedId("activity_{$contentType}_{$contentId}");

            $onlineRecords = $credis->zRevRangeByScore($key, $end, $start, ['withscores' => true]);
            // check if the activity counter needs pruning
            if ($pruneChance > 0 && \mt_rand() < $pruneChance)
            {
                $fillFactor = $options->UA_fillFactor ?? 1.2;
                $credis = $redis->getCredis(false);
                if ($credis->zCard($key) >= count($onlineRecords) * $fillFactor)
                {
                    // O(log(N)+M) with N being the number of elements in the sorted set and M the number of elements removed by the operation.
                    $credis->zRemRangeByScore($key, 0, $start - 1);
                }
            }
        }

        $memberVisibleCount = $isGuest ? 0 : 1;
        $recordsUnseen = 0;

        if (is_array($onlineRecords))
        {
            $seen = [$viewingUser->user_id => true];
            $bypassUserPrivacy = $viewingUser->canBypassUserPrivacy();
            $sampleInterval = $this->getSampleInterval();

            foreach ($onlineRecords as $rec => $score)
            {
                $data = explode("\n", $rec);
                try
                {
                    $rec = @array_combine(self::CacheKeys, $data);
                }
                /** @noinspection PhpMultipleClassDeclarationsInspection */
                catch(\ValueError $e)
                {
                    $rec = null;
                }
                if (empty($rec))
                {
                    continue;
                }
                $rec['user_id'] = $userId = (int)$rec['user_id'];
                if ($userId !== 0)
                {
                    if (empty($seen[$userId]))
                    {
                        $seen[$userId] = true;
                        $memberCount += 1;
                        if (!empty($rec['visible']) || $bypassUserPrivacy)
                        {
                            if ($cutoff === -1)
                            {
                                $recordsUnseen += 1;
                                continue;
                            }
                            else
                            {
                                $memberVisibleCount += 1;
                                if ($cutoff !== 0 && $memberVisibleCount > $cutoff)
                                {
                                    $recordsUnseen += 1;
                                    continue;
                                }
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
            'membersCutOff' => $cutoff,
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
        $db = \XF::db();

        $args = [$start];
        $sql = [];
        foreach ($fetchData as $contentType => $list)
        {
            $list = array_filter(array_map('\intval', array_unique($list)));
            if (count($list) !== 0)
            {
                $sql[] = "\n(content_type = " . $db->quote($contentType) . ' AND content_id in (' . $db->quote($list) . '))';
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
        $options = \XF::options();
        $start = \XF::$time - $options->onlineStatusTimeout * 60;
        $start = $start - ($start % $this->getSampleInterval());
        $end = \XF::$time + 1;

        $redis = $this->getRedisConnector();
        $pruneChance = $options->UA_pruneChance ?? 0.1;
        if ($redis === null)
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
            $credis = $redis->getCredis();
            $onlineRecords = [];
            $args = [];
            foreach ($fetchData as $contentType => $list)
            {
                $list = array_filter(array_map('\intval', array_unique($list)));
                foreach ($list as $contentId)
                {
                    $args[] = [$contentType, $contentId];
                }
            }

            /** @noinspection PhpStatementHasEmptyBodyInspection */
            if (false)
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
                    $key = $redis->getNamespacedId("activity_{$row[0]}_{$row[1]}");
                    $credis->zCount($key, $start, $end);
                }
                $ret = $credis->exec();
                foreach ($args as $i => $row)
                {
                    $val = (int)$ret[$i];
                    if ($val !== 0)
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
    public function bufferTrackViewerUsage(string $contentType, int $contentId, string $activeKey): void
    {
        if (strlen($contentType) === 0 ||
            $contentId === 0 ||
            strlen($activeKey) === 0 ||
            !$this->isLogging())
        {
            return;
        }
        if (!(\XF::options()->svUAPopulateUsers[$activeKey] ?? false))
        {
            return;
        }
        $this->trackBuffer[$contentType][$contentId] = true;
    }

    /**
     * @param string|null     $ip
     * @param string|null     $robotKey
     * @param UserEntity|null $viewingUser
     * @return void
     */
    public function flushTrackViewerUsageBuffer(?string $ip = null, ?string $robotKey = null, ?UserEntity $viewingUser = null): void
    {
        if (!$this->isLogging() || count($this->trackBuffer) === 0)
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
            $threadViewType = $options->RainDD_UA_ThreadViewType ?? 0;
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
        if (count($nodeIds) === 0)
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
        /** @var ThreadEntity[]|null $threads */
        $threads = $params[$key] ?? null;
        if ($threads === null)
        {
            return [];
        }

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
     * @param ViewReply  $response
     * @param NodeEntity $node
     * @param bool       $pushToNode
     * @param string[]   $keys
     * @return void
     */
    public function pushViewUsageToParent(ViewReply $response, NodeEntity $node, bool $pushToNode = false, array $keys = ['forum'])
    {
        $options = \XF::options();
        $pop = $options->svUAPopulateUsers ?? [];
        foreach($keys as $key)
        {
            if (!($pop[$key] ?? false))
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

        $nodeTrackLimit = $options->svUAThreadNodeTrackLimit ?? 1;
        $nodeTrackLimit = $nodeTrackLimit < 0 ? PHP_INT_MAX : $nodeTrackLimit;

        $repo = UserActivityRepo::get();
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
