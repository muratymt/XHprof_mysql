<?php

namespace xhprof\lib\utils;

class XHProfRunsMysql implements iXHProfRuns
{
    /** @var \PDO */
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Returns XHProf data given a run id ($run) of a given
     * type ($type).
     *
     * Also, a brief description of the run is returned via the
     * $run_desc out parameter.
     */
    public function get_run($run_id, $type, &$run_desc)
    {
        $st = $this->pdo->prepare('
            SELECT run_id, route, created_at, xhprof_data
            FROM xhprof_log
            WHERE run_id = :run_id
        ');
        $st->execute(['run_id' => $run_id]);

        $data = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$data) {
            $run_desc = "Invalid Run Id = $run_id";
            return null;
        }
        $run_desc = "XHProf Run (Route={$data['route']})";
        return \json_decode($data['xhprof_data'], true);
    }

    /**
     * Save XHProf data for a profiler run of specified type
     * ($type).
     *
     * The caller may optionally pass in run_id (which they
     * promise to be unique). If a run_id is not passed in,
     * the implementation of this method must generated a
     * unique run id for this saved XHProf run.
     *
     * Returns the run id for the saved XHProf run.
     *
     */
    public function save_run($xhprof_data, $type, $run_id = null)
    {
        $xhprofData = \json_encode($xhprof_data, \JSON_UNESCAPED_UNICODE);
        $st = $this->pdo->prepare('
            INSERT INTO xhprof_log(route, created_at, xhprof_data) 
            VALUE (:route, :created_at, :xhprof_data)
        ');
        $st->execute(['route' => $type, 'created_at' => \time(), 'xhprof_data' => $xhprofData]);
        return $this->pdo->lastInsertId();
    }

    function list_runs()
    {
        $st = $this->pdo->query('
            SELECT run_id, route, created_at
            FROM xhprof_log
            ORDER BY run_id DESC
        ');
        echo "<hr/>Existing runs:\n<ul>\n";
        while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
            echo '<li><a href="'.htmlentities($_SERVER['SCRIPT_NAME']).'?run='.htmlentities($row['run_id']).'">'
                .htmlentities($row['route']).'</a><small> '.\date('Y-m-d H:i:s', $row['created_at'])."</small></li>\n";
        }
    }

    public function garbage_collection($beforeTime)
    {
        $st = $this->pdo->prepare('
            DELETE FROM xhprof_log 
            WHERE created_at < :created_at
        ');
        $st->execute(['created_at' => $beforeTime]);
    }
}
