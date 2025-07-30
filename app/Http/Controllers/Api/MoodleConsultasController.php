<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MoodleConsultasController extends Controller
{
    public function cursosPorCarnet(Request $request)
    {
        $carnet = strtolower($request->input('carnet'));

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
ORDER BY fullname, coursename
SQL;
        $results = DB::connection('moodle')->select($sql, [$carnet]);

        return response()->json(['data' => $results]);
    }
}

