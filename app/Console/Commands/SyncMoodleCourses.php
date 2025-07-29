<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MoodleService;

class SyncMoodleCourses extends Command
{
    protected $signature = 'moodle:sync {courseId?} {--all}';
    protected $description = 'Sync courses from Moodle';

    protected MoodleService $moodle;

    public function __construct(MoodleService $moodle)
    {
        parent::__construct();
        $this->moodle = $moodle;
    }

    public function handle(): int
    {
        $id = $this->argument('courseId');

        if ($id) {
            $course = $this->moodle->syncCourse((int) $id);
            $course
                ? $this->info("Course {$course->id} synced")
                : $this->error('Course not found');
            return Command::SUCCESS;
        }

        if ($this->option('all')) {
            $ids = $this->moodle->getCourseIds();

            if (empty($ids)) {
                $this->error('No courses found');
                return Command::FAILURE;
            }

            foreach ($ids as $cid) {
                $course = $this->moodle->syncCourse((int) $cid);
                if ($course) {
                    $this->info("Course {$course->id} synced");
                }
            }

            return Command::SUCCESS;
        }

        $this->error('Specify courseId or use --all');
        return Command::FAILURE;
    }
}
