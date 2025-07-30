<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MoodleQueryService
{
    protected $connection;

    public function __construct()
    {
        $this->connection = DB::connection('moodle');
    }

    protected function normalizeCarnet(string $carnet): string
    {
        $carnet = preg_replace('/^ASM/i', 'asm', $carnet);
        return strtolower($carnet);
    }

    protected function baseSql(string $extraWhere = ''): string
    {
        $sql = <<<'SQL'
SELECT
    u.id AS userid,
    u.username AS carnet,
    CONCAT(u.firstname, ' ', u.lastname) AS fullname,
    c.id AS courseid,
    c.fullname AS coursename,
    FROM_UNIXTIME(c.startdate) AS fecha_inicio_curso,
    FROM_UNIXTIME(c.enddate) AS fecha_fin_curso,
    ROUND(gg.finalgrade, 2) AS finalgrade,
    CASE
        WHEN c.enddate > 0 AND UNIX_TIMESTAMP() > c.enddate AND gg.finalgrade > 61 THEN 'Aprobado'
        WHEN c.enddate > 0 AND UNIX_TIMESTAMP() > c.enddate THEN 'Reprobado'
        WHEN cc.timecompleted IS NOT NULL THEN 'Completado'
        ELSE 'En curso'
    END AS estado_curso
FROM mdl_user u
JOIN mdl_user_enrolments ue ON ue.userid = u.id AND ue.status = 0
JOIN mdl_enrol e ON e.id = ue.enrolid
JOIN mdl_course c ON c.id = e.courseid
JOIN mdl_grade_items gi ON gi.courseid = c.id AND gi.itemtype = 'course'
JOIN mdl_grade_grades gg ON gg.userid = u.id AND gg.itemid = gi.id
LEFT JOIN mdl_course_completions cc ON cc.userid = u.id AND cc.course = c.id
WHERE u.deleted = 0
  AND u.suspended = 0
  AND (ue.timestart = 0 OR ue.timestart <= UNIX_TIMESTAMP())
  AND (ue.timeend = 0 OR ue.timeend >= UNIX_TIMESTAMP())
  AND LOWER(u.username) = ?
SQL;
        if ($extraWhere) {
            $sql .= "\n  $extraWhere";
        }
        $sql .= "\nORDER BY fullname, coursename";
        return $sql;
    }

    public function cursosPorCarnet(string $carnet): array
    {
        $carnet = $this->normalizeCarnet($carnet);
        return $this->connection->select($this->baseSql(), [$carnet]);
    }

    public function cursosAprobados(string $carnet): array
    {
        $carnet = $this->normalizeCarnet($carnet);
        $sql = $this->baseSql('AND gg.finalgrade > 61');
        return $this->connection->select($sql, [$carnet]);
    }

    public function cursosReprobados(string $carnet): array
    {
        $carnet = $this->normalizeCarnet($carnet);
        $sql = $this->baseSql('AND gg.finalgrade <= 61 AND c.enddate > 0 AND UNIX_TIMESTAMP() > c.enddate');
        return $this->connection->select($sql, [$carnet]);
    }
}
