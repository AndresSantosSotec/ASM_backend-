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
    ROUND(gg.finalgrade, 2) AS finalgrade,
    CASE
        WHEN gg.finalgrade >= 61 THEN 'Completado'
        ELSE 'No completado'
    END AS estado_curso
FROM mdl_user u
JOIN mdl_user_enrolments ue ON ue.userid = u.id
JOIN mdl_enrol e ON e.id = ue.enrolid
JOIN mdl_course c ON c.id = e.courseid
JOIN mdl_grade_items gi ON gi.courseid = c.id AND gi.itemtype = 'course'
JOIN mdl_grade_grades gg ON gg.userid = u.id AND gg.itemid = gi.id
WHERE u.deleted = 0
  AND gg.finalgrade IS NOT NULL
  AND LOWER(u.username) = ?
SQL;
        if ($extraWhere) {
            $sql .= "\n  $extraWhere";
        }
        $sql .= "\nORDER BY fullname, coursename";
        return $sql;
    }

    // Método alternativo para todos los cursos (incluyendo los sin calificación)
    protected function baseSqlComplete(string $extraWhere = ''): string
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
        WHEN cc.timecompleted IS NOT NULL THEN 'Completado por criterios'
        WHEN gg.finalgrade >= 61 THEN 'Completado'
        WHEN gg.finalgrade IS NOT NULL AND gg.finalgrade < 61 THEN 'No completado'
        WHEN c.enddate > 0 AND UNIX_TIMESTAMP() > c.enddate THEN 'Expirado'
        ELSE 'En curso'
    END AS estado_curso
FROM mdl_user u
JOIN mdl_user_enrolments ue ON ue.userid = u.id AND ue.status = 0
JOIN mdl_enrol e ON e.id = ue.enrolid
JOIN mdl_course c ON c.id = e.courseid
LEFT JOIN mdl_grade_items gi ON gi.courseid = c.id AND gi.itemtype = 'course'
LEFT JOIN mdl_grade_grades gg ON gg.userid = u.id AND gg.itemid = gi.id
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
        // Usar la consulta completa para mostrar todos los cursos
        $results = $this->connection->select($this->baseSqlComplete(), [$carnet]);

        foreach ($results as $result) {
            $result->coursename = $this->cleanCourseName($result->coursename);
        }

        return $results;
    }

    protected function cleanCourseName(string $name): string
    {
        $month  = '(?:Enero|Febrero|Marzo|Abril|Mayo|Junio|Julio|Agosto|Septiembre|Octubre|Noviembre|Diciembre)';
        $day    = '(?:Lunes|Martes|Mi(?:é|e)rcoles|Jueves|Viernes|S(?:á|a)bado|Domingo)';
        $regex  = '/^(?:' . $month . '\\s+)?(?:' . $day . '\\s+)?(?:\\d{4}\\s+)?(?:[A-Z]{3,}\\s+)?/iu';

        return trim(preg_replace($regex, '', $name));
    }

    public function cursosAprobados(string $carnet): array
    {
        $carnet = $this->normalizeCarnet($carnet);
        // Usar JOIN directo como en tu SQL que funciona, ignorando fechas
        $sql = $this->baseSql('AND gg.finalgrade >= 61');
        $results = $this->connection->select($sql, [$carnet]);

        foreach ($results as $result) {
            $result->coursename = $this->cleanCourseName($result->coursename);
        }

        return $results;
    }

    public function cursosReprobados(string $carnet): array
    {
        $carnet = $this->normalizeCarnet($carnet);
        // Usar JOIN directo para obtener solo cursos con calificación reprobatoria
        $sql = $this->baseSql('AND gg.finalgrade < 61');
        $results = $this->connection->select($sql, [$carnet]);

        foreach ($results as $result) {
            $result->coursename = $this->cleanCourseName($result->coursename);
        }

        return $results;
    }

    // Método adicional para cursos completados por criterios (sin depender de calificaciones)
    public function cursosCompletadosPorCriterios(string $carnet): array
    {
        $carnet = $this->normalizeCarnet($carnet);
        $sql = <<<'SQL'
SELECT
    u.id AS userid,
    u.username AS carnet,
    CONCAT(u.firstname, ' ', u.lastname) AS fullname,
    c.id AS courseid,
    c.fullname AS coursename,
    FROM_UNIXTIME(cc.timecompleted) AS fecha_completado,
    'Completado por criterios' AS estado_curso
FROM mdl_user u
JOIN mdl_user_enrolments ue ON ue.userid = u.id
JOIN mdl_enrol e ON e.id = ue.enrolid
JOIN mdl_course c ON c.id = e.courseid
JOIN mdl_course_completions cc ON cc.userid = u.id AND cc.course = c.id
WHERE u.deleted = 0
  AND cc.timecompleted IS NOT NULL
  AND LOWER(u.username) = ?
ORDER BY fullname, coursename
SQL;

        $results = $this->connection->select($sql, [$carnet]);

        foreach ($results as $result) {
            $result->coursename = $this->cleanCourseName($result->coursename);
        }

        return $results;
    }

    public function estatusAcademico(string $carnet): ?array
    {
        $carnet = $this->normalizeCarnet($carnet);

        $sql = <<<'SQL'
WITH
program_info AS (
  SELECT
    u.id AS userid,
    u.username AS carnet,
    CONCAT(u.firstname, ' ', u.lastname) AS fullname,
    IF(u.suspended=0, 'Activo','Suspendido') AS estado,
    MIN(ue.timestart) AS inscription_date,
    c.name AS program
  FROM mdl_user u
  LEFT JOIN mdl_user_enrolments ue ON ue.userid = u.id
  LEFT JOIN mdl_cohort_members cm ON cm.userid = u.id
  LEFT JOIN mdl_cohort c          ON c.id      = cm.cohortid
  WHERE LOWER(u.username) = :carnet
  GROUP BY u.id, u.username, fullname, estado, c.name
),
grades AS (
  SELECT
    gg.userid,
    gi.courseid,
    ROUND(gg.finalgrade,2) AS grade
  FROM mdl_grade_items gi
  JOIN mdl_grade_grades gg ON gg.itemid = gi.id
  WHERE gi.itemtype = 'course'
    AND gg.userid = (SELECT userid FROM program_info)
),
completions AS (
  SELECT
    cc.course    AS courseid,
    cc.timecompleted
  FROM mdl_course_completions cc
  WHERE cc.userid = (SELECT userid FROM program_info)
    AND cc.timecompleted IS NOT NULL
),
summary AS (
  SELECT
    COUNT(*)                            AS total_courses,
    SUM(grade >= 61)                    AS approved_courses,
    SUM(grade <  61)                    AS failed_courses,
    (COUNT(*) - SUM(grade IS NOT NULL)) AS in_progress_courses
  FROM grades
)
SELECT
  p.userid,
  p.carnet,
  p.fullname,
  p.estado,
  p.program,
  DATE_FORMAT(p.inscription_date, '%Y-%m-%d') AS inscription_date,
  FLOOR(TIMESTAMPDIFF(MONTH, p.inscription_date, CURDATE())/6) + 1 AS semester,
  ROUND((SELECT AVG(grade) FROM grades),2) AS average_grade,
  s.approved_courses,
  s.failed_courses,
  s.in_progress_courses,
  IFNULL(
    (SELECT SUM(c.credits)
     FROM completions cc
     JOIN mdl_course c ON c.id = cc.courseid),
    0
  ) AS credits_completed,
  ROUND(s.approved_courses / NULLIF(s.total_courses,0) * 100,2) AS progress_percentage,
  JSON_ARRAYAGG(
    JSON_OBJECT(
      'courseid',   g.courseid,
      'coursename', (SELECT fullname FROM mdl_course WHERE id = g.courseid),
      'period',     (SELECT DATE_FORMAT(startdate,'%Y-%m') FROM mdl_course WHERE id = g.courseid),
      'state',      CASE
                       WHEN g.grade >= 61 THEN 'Aprobado'
                       WHEN g.grade <  61 THEN 'Reprobado'
                       ELSE 'En curso'
                     END,
      'grade',      g.grade
    )
  ) AS courses
FROM program_info p
JOIN summary      s ON 1=1
LEFT JOIN grades  g ON g.userid = p.userid;
SQL;

        $result = $this->connection->selectOne($sql, ['carnet' => $carnet]);

        if (! $result) {
            return null;
        }

        $result->courses = $result->courses ? json_decode($result->courses, true) : [];

        return (array) $result;
    }
}
