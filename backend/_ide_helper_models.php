<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property int $trainee_id
 * @property int|null $trainer_id
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon $date
 * @property int|null $request_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Trainee $trainee
 * @property-read \App\Models\Trainer|null $trainer
 * @property-read \App\Models\VacationRequest|null $vacationRequest
 * @method static \Database\Factories\AbsenceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absence filter(array $filters)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absence newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absence newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absence query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absence whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absence whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absence whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absence whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absence whereRequestId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absence whereTraineeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absence whereTrainerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Absence whereUpdatedAt($value)
 */
	class Absence extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $title
 * @property string $description
 * @property int $publisher_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AnnouncementAudience> $audiences
 * @property-read int|null $audiences_count
 * @property-read \App\Models\User $publisher
 * @property-read \App\Models\AnnouncementAudience|null $traineeAudience
 * @method static \Database\Factories\AnnouncementFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement wherePublisherId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereUpdatedAt($value)
 */
	class Announcement extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $announcement_id
 * @property int $audience_id
 * @property bool $read
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Announcement $announcement
 * @property-read \App\Models\User $audience
 * @method static \Database\Factories\AnnouncementAudienceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementAudience newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementAudience newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementAudience query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementAudience whereAnnouncementId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementAudience whereAudienceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementAudience whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementAudience whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementAudience whereRead($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementAudience whereUpdatedAt($value)
 */
	class AnnouncementAudience extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $national_id_number
 * @property int $training_program_id
 * @property string $full_name
 * @property string $email
 * @property string $phone_number
 * @property \Illuminate\Support\Carbon|null $birth_date
 * @property \App\Enums\City $city
 * @property string $home_address
 * @property string $university
 * @property string $field_of_study
 * @property string $academic_major
 * @property \App\Enums\YearOfStudy $year_of_study
 * @property numeric|null $gpa
 * @property string|null $technical_skills
 * @property string|null $academic_qualification
 * @property string $cv
 * @property string $national_id
 * @property string $personal_photo
 * @property int $commitment
 * @property int $previous_training
 * @property \App\Enums\HowHeard|null $how_heard
 * @property string|null $additional_info
 * @property \App\Enums\ApplicationStatus $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Trainer> $Trainer
 * @property-read int|null $trainer_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ApplicationAssign> $assigns
 * @property-read int|null $assigns_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Interview> $interviews
 * @property-read int|null $interviews_count
 * @property-read \App\Models\Trainee|null $trainee
 * @property-read \App\Models\TrainingProgram $trainingProgram
 * @method static \Database\Factories\ApplicationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application filter(array $filters)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application whereAcademicMajor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application whereAcademicQualification($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application whereAdditionalInfo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application whereBirthDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application whereCommitment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application whereCv($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application whereFieldOfStudy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application whereFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application whereGpa($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application whereHomeAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application whereHowHeard($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application whereNationalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application whereNationalIdNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application wherePersonalPhoto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application wherePhoneNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application wherePreviousTraining($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application whereTechnicalSkills($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application whereTrainingProgramId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application whereUniversity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Application whereYearOfStudy($value)
 */
	class Application extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $application_id
 * @property int $trainer_id
 * @property \App\Enums\ApplicationStatus $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Application $application
 * @property-read \App\Models\Trainer $trainer
 * @method static \Database\Factories\ApplicationAssignFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationAssign newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationAssign newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationAssign query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationAssign whereApplicationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationAssign whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationAssign whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationAssign whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationAssign whereTrainerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApplicationAssign whereUpdatedAt($value)
 */
	class ApplicationAssign extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $path
 * @property int $task_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Task $task
 * @method static \Database\Factories\AttachmentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereTaskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereUpdatedAt($value)
 */
	class Attachment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $path
 * @property int $trainee_id
 * @property int $training_program_id
 * @property \Illuminate\Support\Carbon $issue_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Trainee $trainee
 * @property-read \App\Models\TrainingProgram $trainingProgram
 * @method static \Database\Factories\CertificateFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereIssueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereTraineeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereTrainingProgramId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereUpdatedAt($value)
 */
	class Certificate extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property numeric $score
 * @property int $evaluation_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $notice
 * @property-read \App\Models\Evaluation $evaluation
 * @method static \Database\Factories\CriteriaFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Criteria newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Criteria newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Criteria query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Criteria whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Criteria whereEvaluationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Criteria whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Criteria whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Criteria whereNotice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Criteria whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Criteria whereUpdatedAt($value)
 */
	class Criteria extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $location
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Program> $programs
 * @property-read int|null $programs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Trainee> $trainees
 * @property-read int|null $trainees_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Trainer> $trainers
 * @property-read int|null $trainers_count
 * @method static \Database\Factories\DepartmentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereUpdatedAt($value)
 */
	class Department extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $token
 * @property string $platform
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken whereLastUsedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken wherePlatform($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken whereUserId($value)
 */
	class DeviceToken extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property numeric|null $score
 * @property string|null $description
 * @property int $trainee_id
 * @property int|null $trainer_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Criteria> $criteria
 * @property-read int|null $criteria_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ReevaluationRequest> $reevaluationRequests
 * @property-read int|null $reevaluation_requests_count
 * @property-read \App\Models\Trainee $trainee
 * @property-read \App\Models\Trainer|null $trainer
 * @method static \Database\Factories\EvaluationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation whereTraineeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation whereTrainerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation whereUpdatedAt($value)
 */
	class Evaluation extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $evidence
 * @property int $task_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Feedback|null $sourceFeedback
 * @property-read \App\Models\Feedback|null $targetFeedback
 * @property-read \App\Models\Task $task
 * @method static \Database\Factories\EvidenceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evidence newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evidence newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evidence query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evidence whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evidence whereEvidence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evidence whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evidence whereTaskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evidence whereUpdatedAt($value)
 */
	class Evidence extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $feedback_id
 * @property int $resubmitted_evidence_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\EvidenceResubmissionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvidenceResubmission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvidenceResubmission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvidenceResubmission query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvidenceResubmission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvidenceResubmission whereFeedbackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvidenceResubmission whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvidenceResubmission whereResubmittedEvidenceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EvidenceResubmission whereUpdatedAt($value)
 */
	class EvidenceResubmission extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $evidence_id
 * @property int $trainer_id
 * @property string|null $feedback
 * @property \App\Enums\TaskStatus $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Evidence $sourceEvidence
 * @property-read \App\Models\Evidence|null $targetEvidence
 * @property-read \App\Models\Trainer $trainer
 * @method static \Database\Factories\FeedbackFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereEvidenceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereFeedback($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereTrainerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereUpdatedAt($value)
 */
	class Feedback extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $trainee_id
 * @property \Illuminate\Support\Carbon $fired_at
 * @property string $reason
 * @property int|null $fired_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $firedBy
 * @property-read \App\Models\Trainee $trainee
 * @method static \Database\Factories\FiredTraineeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FiredTrainee newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FiredTrainee newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FiredTrainee query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FiredTrainee whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FiredTrainee whereFiredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FiredTrainee whereFiredBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FiredTrainee whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FiredTrainee whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FiredTrainee whereTraineeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FiredTrainee whereUpdatedAt($value)
 */
	class FiredTrainee extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $application_id
 * @property int|null $trainer_id
 * @property \Illuminate\Support\Carbon $time
 * @property string $location
 * @property numeric|null $evaluation
 * @property string|null $interview_note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Application $application
 * @property-read \App\Models\Trainer|null $trainer
 * @method static \Database\Factories\InterviewFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interview newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interview newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interview query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interview whereApplicationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interview whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interview whereEvaluation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interview whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interview whereInterviewNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interview whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interview whereTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interview whereTrainerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interview whereUpdatedAt($value)
 */
	class Interview extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $program_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Program $program
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task> $tasks
 * @property-read int|null $tasks_count
 * @method static \Database\Factories\MilestoneFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Milestone newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Milestone newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Milestone query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Milestone whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Milestone whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Milestone whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Milestone whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Milestone whereProgramId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Milestone whereUpdatedAt($value)
 */
	class Milestone extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $title
 * @property string $description
 * @property int $notifier_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Notified> $notified
 * @property-read int|null $notified_count
 * @property-read \App\Models\User $notifier
 * @method static \Database\Factories\NotificationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereNotifierId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereUpdatedAt($value)
 */
	class Notification extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $notification_id
 * @property int $notified_id
 * @property bool $read
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Notification $notification
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\NotifiedFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notified newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notified newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notified query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notified whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notified whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notified whereNotificationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notified whereNotifiedId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notified whereRead($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notified whereUpdatedAt($value)
 */
	class Notified extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $title
 * @property string $description
 * @property int $department_id
 * @property int|null $trainer_id
 * @property string|null $deadline
 * @property int $training_program_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Department $department
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Milestone> $milestones
 * @property-read int|null $milestones_count
 * @property-read \App\Models\Trainer|null $trainer
 * @method static \Database\Factories\ProgramFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Program newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Program newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Program query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Program whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Program whereDeadline($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Program whereDepartmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Program whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Program whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Program whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Program whereTrainerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Program whereTrainingProgramId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Program whereUpdatedAt($value)
 */
	class Program extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $reason
 * @property \App\Enums\EvaluationRequestStatus $status
 * @property string|null $decision_note
 * @property int $trainee_id
 * @property int|null $trainer_id
 * @property int|null $evaluation_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Evaluation|null $evaluation
 * @property-read \App\Models\Trainee $trainee
 * @property-read \App\Models\Trainer|null $trainer
 * @method static \Database\Factories\ReevaluationRequestFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReevaluationRequest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReevaluationRequest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReevaluationRequest query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReevaluationRequest whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReevaluationRequest whereDecisionNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReevaluationRequest whereEvaluationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReevaluationRequest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReevaluationRequest whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReevaluationRequest whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReevaluationRequest whereTraineeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReevaluationRequest whereTrainerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReevaluationRequest whereUpdatedAt($value)
 */
	class ReevaluationRequest extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property \App\Enums\UserRole $role
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Database\Factories\RoleFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereUpdatedAt($value)
 */
	class Role extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $deadline
 * @property int $priority
 * @property string|null $instructions
 * @property int $trainee_id
 * @property int $milestone_id
 * @property \App\Enums\TaskStatus $status
 * @property string|null $started_at
 * @property string|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Attachment> $attachments
 * @property-read int|null $attachments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Evidence> $evidences
 * @property-read int|null $evidences_count
 * @property-read \App\Models\Milestone $milestone
 * @property-read \App\Models\Trainee $trainee
 * @method static \Database\Factories\TaskFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereDeadline($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereInstructions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereMilestoneId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereTraineeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereUpdatedAt($value)
 */
	class Task extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property \App\Enums\TraineeStatus $status
 * @property int $department_id
 * @property int $trainer_id
 * @property int $application_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Absence> $absences
 * @property-read int|null $absences_count
 * @property-read \App\Models\Application $application
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Certificate> $certificates
 * @property-read int|null $certificates_count
 * @property-read \App\Models\Department $department
 * @property-read \App\Models\Evaluation|null $evaluation
 * @property-read \App\Models\FiredTrainee|null $fire
 * @property-read \App\Models\ReevaluationRequest|null $reevaluationRequest
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task> $tasks
 * @property-read int|null $tasks_count
 * @property-read \App\Models\Trainer $trainer
 * @property-read \App\Models\User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\VacationRequest> $vacationRequests
 * @property-read int|null $vacation_requests_count
 * @method static \Database\Factories\TraineeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainee filter(array $filters)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainee newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainee newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainee query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainee whereApplicationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainee whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainee whereDepartmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainee whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainee whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainee whereTrainerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainee whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainee whereUserId($value)
 */
	class Trainee extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property int $department_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Absence> $absences
 * @property-read int|null $absences_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Application> $applications
 * @property-read int|null $applications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ApplicationAssign> $assigns
 * @property-read int|null $assigns_count
 * @property-read \App\Models\Department $department
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Evaluation> $evaluations
 * @property-read int|null $evaluations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Feedback> $feedbacks
 * @property-read int|null $feedbacks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Interview> $interviews
 * @property-read int|null $interviews_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Program> $programs
 * @property-read int|null $programs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ReevaluationRequest> $reevaluationRequests
 * @property-read int|null $reevaluation_requests_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Trainee> $trainees
 * @property-read int|null $trainees_count
 * @property-read \App\Models\User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\VacationRequest> $vacationRequests
 * @property-read int|null $vacation_requests_count
 * @method static \Database\Factories\TrainerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainer whereDepartmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trainer whereUserId($value)
 */
	class Trainer extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $description
 * @property \Illuminate\Support\Carbon $application_start_date
 * @property \Illuminate\Support\Carbon $application_end_date
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property \App\Enums\TrainingProgramStatus $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Application> $applications
 * @property-read int|null $applications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Certificate> $certificates
 * @property-read int|null $certificates_count
 * @method static \Database\Factories\TrainingProgramFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainingProgram newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainingProgram newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainingProgram query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainingProgram whereApplicationEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainingProgram whereApplicationStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainingProgram whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainingProgram whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainingProgram whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainingProgram whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainingProgram whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainingProgram whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainingProgram whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainingProgram whereUpdatedAt($value)
 */
	class TrainingProgram extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $phone_number
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property \App\Enums\UserStatus $status
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AnnouncementAudience> $announcementAudiences
 * @property-read int|null $announcement_audiences_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DeviceToken> $deviceTokens
 * @property-read int|null $device_tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Notification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Notified> $notified
 * @property-read int|null $notified_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Announcement> $publishedAnnouncements
 * @property-read int|null $published_announcements_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \App\Models\Trainee|null $trainee
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FiredTrainee> $traineeFiresMade
 * @property-read int|null $trainee_fires_made_count
 * @property-read \App\Models\Trainer|null $trainer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserRole> $userRoles
 * @property-read int|null $user_roles_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhoneNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 */
	class User extends \Eloquent implements \PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property int $role_id
 * @property string|null $assigned_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\UserRoleFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRole newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRole newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRole query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRole whereAssignedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRole whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRole whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRole whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRole whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRole whereUserId($value)
 */
	class UserRole extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $reason
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property \App\Enums\VacationRequestStatus $status
 * @property int $trainee_id
 * @property int|null $trainer_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Absence> $absences
 * @property-read int|null $absences_count
 * @property-read \App\Models\Trainee $trainee
 * @property-read \App\Models\Trainer|null $trainer
 * @method static \Database\Factories\VacationRequestFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationRequest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationRequest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationRequest query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationRequest whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationRequest whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationRequest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationRequest whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationRequest whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationRequest whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationRequest whereTraineeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationRequest whereTrainerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationRequest whereUpdatedAt($value)
 */
	class VacationRequest extends \Eloquent {}
}

