<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$pageTitle  = 'Admissions';
$pageDesc   = 'Apply to Yidden Institute online. See how to apply, upcoming intake dates and answers to common questions, then submit your application in minutes.';
$activePage = 'admissions';

$pdo     = db();
$intakes = get_intakes();
$courses = $pdo->query(
    'SELECT c.id, c.title, c.mode, d.name AS dept_name
     FROM courses c JOIN departments d ON d.id = c.department_id
     ORDER BY d.id, c.title'
)->fetchAll();
$courseIds = array_map('intval', array_column($courses, 'id'));

$educationLevels = ['Primary', 'O-Level (UCE)', 'A-Level (UACE)', 'Certificate', 'Diploma', "Bachelor's degree or higher", 'Other'];
$genders         = ['Female', 'Male', 'Prefer not to say'];
$modes           = ['Full-time', 'Part-time', 'Weekend'];

$errors  = [];
$old     = [
    'full_name' => '', 'email' => '', 'phone' => '', 'dob' => '', 'gender' => '', 'district' => '',
    'education_level' => '', 'course_id' => '', 'study_mode' => '', 'start_date' => '', 'message' => '',
];
$success = null;

// Preselect a course from ?course=ID
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['course'])) {
    $wanted = (int) $_GET['course'];
    if (in_array($wanted, $courseIds, true)) {
        $old['course_id'] = (string) $wanted;
    }
}

// Show the success panel once after a redirect
if (isset($_GET['submitted']) && !empty($_SESSION['application_done'])) {
    $success = $_SESSION['application_done'];
    unset($_SESSION['application_done']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $_) {
        $old[$k] = field($_POST, $k, $k === 'message' ? 1000 : 160);
    }

    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $errors['form'] = 'Your session expired. Please check your details and submit again.';
    } elseif (field($_POST, 'website') !== '') {
        // Honeypot filled: pretend success and store nothing.
        header('Location: admissions.php?submitted=1');
        $_SESSION['application_done'] = ['reference' => 'YI-' . date('Y') . '-0000', 'name' => 'there', 'course' => '', 'start' => ''];
        exit;
    } elseif (rate_limited('application', 5, 60)) {
        $errors['form'] = 'You have submitted several applications recently. Please wait a while or call us on ' . CONTACT_PHONE . '.';
    } else {
        // ---- Validate ----
        if (!valid_name($old['full_name'])) {
            $errors['full_name'] = 'Enter your full name using letters only.';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if (!valid_phone($old['phone'])) {
            $errors['phone'] = 'Enter a phone number with 9 to 15 digits, for example +256 700 000 000.';
        }
        $dob = DateTime::createFromFormat('!Y-m-d', $old['dob']);
        if (!$dob || $dob->format('Y-m-d') !== $old['dob']) {
            $errors['dob'] = 'Enter your date of birth.';
        } elseif ($dob > new DateTime('-14 years') || $dob < new DateTime('1940-01-01')) {
            $errors['dob'] = 'Applicants must be at least 14 years old.';
        }
        if (!in_array($old['gender'], $genders, true)) {
            $errors['gender'] = 'Choose an option.';
        }
        if (mb_strlen($old['district']) < 2 || !preg_match('/^[\p{L} .\'\-]{2,80}$/u', $old['district'])) {
            $errors['district'] = 'Enter your district.';
        }
        if (!in_array($old['education_level'], $educationLevels, true)) {
            $errors['education_level'] = 'Choose your highest level of education.';
        }
        if (!in_array((int) $old['course_id'], $courseIds, true)) {
            $errors['course_id'] = 'Choose a course.';
        }
        if (!in_array($old['study_mode'], $modes, true)) {
            $errors['study_mode'] = 'Choose a study mode.';
        }
        $intakeDates = array_column($intakes, 'start_date');
        if (!in_array($old['start_date'], $intakeDates, true)) {
            $errors['start_date'] = 'Choose one of the available start dates.';
        }
        if (mb_strlen($old['message']) > 1000) {
            $errors['message'] = 'Please keep your message under 1,000 characters.';
        }

        // ---- Save ----
        if (!$errors) {
            record_attempt('application');

            $pdo->beginTransaction();
            try {
                $insert = $pdo->prepare(
                    'INSERT INTO applications
                       (reference, course_id, full_name, email, phone, dob, gender, district, education_level, study_mode, start_date, message, ip)
                     VALUES
                       (:ref, :course, :name, :email, :phone, :dob, :gender, :district, :edu, :mode, :start, :msg, :ip)'
                );
                $insert->execute([
                    ':ref'      => 'TMP-' . bin2hex(random_bytes(8)),
                    ':course'   => (int) $old['course_id'],
                    ':name'     => $old['full_name'],
                    ':email'    => strtolower($old['email']),
                    ':phone'    => $old['phone'],
                    ':dob'      => $old['dob'],
                    ':gender'   => $old['gender'],
                    ':district' => $old['district'],
                    ':edu'      => $old['education_level'],
                    ':mode'     => $old['study_mode'],
                    ':start'    => $old['start_date'],
                    ':msg'      => $old['message'] !== '' ? $old['message'] : null,
                    ':ip'       => client_ip(),
                ]);
                $id  = (int) $pdo->lastInsertId();
                $ref = sprintf('YI-%s-%04d', date('Y'), $id);
                $pdo->prepare('UPDATE applications SET reference = :ref WHERE id = :id')->execute([':ref' => $ref, ':id' => $id]);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            $course = get_course((int) $old['course_id']);
            $courseTitle = $course['title'] ?? 'your chosen course';

            // Emails never block the application.
            send_mail(
                $old['email'],
                'We received your application, ' . $ref,
                "Hello {$old['full_name']},\n\nThank you for applying to " . SITE_NAME . ".\n\n"
                . "Reference number: {$ref}\nCourse: {$courseTitle}\nStudy mode: {$old['study_mode']}\n"
                . 'Preferred start: ' . fmt_date($old['start_date']) . "\n\n"
                . "Our admissions team will contact you within 2 working days. Please quote your reference number in any message.\n\n"
                . 'Phone: ' . CONTACT_PHONE . "\nEmail: " . SITE_EMAIL . "\n\n" . SITE_NAME
            );
            send_mail(
                SITE_EMAIL,
                "New application {$ref}: {$courseTitle}",
                "New application received.\n\nReference: {$ref}\nName: {$old['full_name']}\nEmail: {$old['email']}\nPhone: {$old['phone']}\n"
                . "Course: {$courseTitle}\nMode: {$old['study_mode']}\nStart: {$old['start_date']}\n",
                $old['email']
            );

            unset($_SESSION['csrf']); // fresh token for the next form
            $_SESSION['application_done'] = [
                'reference' => $ref,
                'name'      => $old['full_name'],
                'course'    => $courseTitle,
                'start'     => fmt_date($old['start_date']),
            ];
            header('Location: admissions.php?submitted=1#apply');
            exit;
        }
    }
}

/** Bootstrap validation class for a field. */
function vclass(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' is-invalid' : '';
}

function ferror(array $errors, string $key, string $fallback): string
{
    return '<div class="invalid-feedback">' . e($errors[$key] ?? $fallback) . '</div>';
}

include __DIR__ . '/includes/header.php';
?>

<section class="page-hero" aria-labelledby="page-title">
    <div class="container">
        <nav aria-label="Breadcrumb">
            <ol class="breadcrumb mb-3">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Admissions</li>
            </ol>
        </nav>
        <h1 id="page-title" class="fw-bold">Apply to Yidden Institute</h1>
        <p class="lead mb-0">Choose a course, tell us about yourself and we will confirm your place within two working days.</p>
    </div>
</section>

<!-- How to apply -->
<section class="section-space-sm" aria-labelledby="how-title">
    <div class="container">
        <h2 id="how-title" class="section-title h2 mb-4">How to apply</h2>
        <ol class="steps">
            <li><span class="steps__num">1</span><h3 class="h6">Choose a course</h3><p class="small text-secondary mb-0">Browse the <a href="courses.php">course catalog</a> and compare fees, level and duration.</p></li>
            <li><span class="steps__num">2</span><h3 class="h6">Submit the form</h3><p class="small text-secondary mb-0">Fill in the application below. It takes about five minutes.</p></li>
            <li><span class="steps__num">3</span><h3 class="h6">Get confirmation</h3><p class="small text-secondary mb-0">We contact you within two working days with your next steps.</p></li>
            <li><span class="steps__num">4</span><h3 class="h6">Pay and start</h3><p class="small text-secondary mb-0">Secure your place with your fees and join us on the start date.</p></li>
        </ol>
    </div>
</section>

<!-- Application form -->
<section class="section-space-sm cream" id="apply" aria-labelledby="apply-title">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-8">
                <h2 id="apply-title" class="section-title h2 mb-4">Application form</h2>

                <?php if ($success): ?>
                    <div class="success-panel" role="status" tabindex="-1" id="applySuccess">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                        <h3 class="h4">Application received</h3>
                        <p>Thank you, <?= e($success['name']) ?>. Your reference number is:</p>
                        <p class="success-panel__ref"><?= e($success['reference']) ?></p>
                        <?php if ($success['course'] !== ''): ?>
                            <p class="mb-2">Course: <strong><?= e($success['course']) ?></strong><br>
                            Preferred start: <strong><?= e($success['start']) ?></strong></p>
                        <?php endif; ?>
                        <p class="mb-3">Keep this number and quote it if you contact us. We will call or email you within two working days.</p>
                        <a href="courses.php" class="btn btn-navy">Browse more courses</a>
                    </div>
                <?php else: ?>
                    <?php if (isset($errors['form'])): ?>
                        <div class="alert alert-danger" role="alert"><?= e($errors['form']) ?></div>
                    <?php elseif ($errors): ?>
                        <div class="alert alert-danger" role="alert">Please fix the highlighted fields and submit again.</div>
                    <?php endif; ?>

                    <form class="app-form needs-validation" action="admissions.php#apply" method="post" novalidate>
                        <?= csrf_field() ?>
                        <div class="visually-hidden" aria-hidden="true">
                            <label for="website">Leave this field empty</label>
                            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <fieldset class="row g-3">
                            <legend class="h5 section-title">About you</legend>
                            <div class="col-md-6">
                                <label for="full_name" class="form-label">Full name</label>
                                <input type="text" class="form-control<?= vclass($errors, 'full_name') ?>" id="full_name" name="full_name"
                                       value="<?= e($old['full_name']) ?>" required minlength="2" maxlength="120" autocomplete="name">
                                <?= ferror($errors, 'full_name', 'Enter your full name.') ?>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email address</label>
                                <input type="email" class="form-control<?= vclass($errors, 'email') ?>" id="email" name="email"
                                       value="<?= e($old['email']) ?>" required maxlength="160" autocomplete="email">
                                <?= ferror($errors, 'email', 'Enter a valid email address.') ?>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone number</label>
                                <input type="tel" class="form-control<?= vclass($errors, 'phone') ?>" id="phone" name="phone"
                                       value="<?= e($old['phone']) ?>" required pattern="\+?[0-9 \-]{9,20}" maxlength="20"
                                       placeholder="+256 700 000 000" autocomplete="tel">
                                <?= ferror($errors, 'phone', 'Enter a phone number with 9 to 15 digits.') ?>
                            </div>
                            <div class="col-md-6">
                                <label for="dob" class="form-label">Date of birth</label>
                                <input type="date" class="form-control<?= vclass($errors, 'dob') ?>" id="dob" name="dob"
                                       value="<?= e($old['dob']) ?>" required min="1940-01-01" max="<?= e(date('Y-m-d', strtotime('-14 years'))) ?>" autocomplete="bday">
                                <?= ferror($errors, 'dob', 'Enter your date of birth. Applicants must be 14 or older.') ?>
                            </div>
                            <div class="col-md-6">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-select<?= vclass($errors, 'gender') ?>" id="gender" name="gender" required>
                                    <option value="">Choose...</option>
                                    <?php foreach ($genders as $g): ?>
                                        <option <?= $old['gender'] === $g ? 'selected' : '' ?>><?= e($g) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= ferror($errors, 'gender', 'Choose an option.') ?>
                            </div>
                            <div class="col-md-6">
                                <label for="district" class="form-label">District</label>
                                <input type="text" class="form-control<?= vclass($errors, 'district') ?>" id="district" name="district"
                                       value="<?= e($old['district']) ?>" required minlength="2" maxlength="80" autocomplete="address-level2">
                                <?= ferror($errors, 'district', 'Enter your district.') ?>
                            </div>
                            <div class="col-12">
                                <label for="education_level" class="form-label">Highest education level</label>
                                <select class="form-select<?= vclass($errors, 'education_level') ?>" id="education_level" name="education_level" required>
                                    <option value="">Choose...</option>
                                    <?php foreach ($educationLevels as $lv): ?>
                                        <option <?= $old['education_level'] === $lv ? 'selected' : '' ?>><?= e($lv) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= ferror($errors, 'education_level', 'Choose your highest level of education.') ?>
                            </div>
                        </fieldset>

                        <fieldset class="row g-3 mt-3">
                            <legend class="h5 section-title">Your course</legend>
                            <div class="col-12">
                                <label for="course_id" class="form-label">Course</label>
                                <select class="form-select<?= vclass($errors, 'course_id') ?>" id="course_id" name="course_id" required>
                                    <option value="">Choose a course...</option>
                                    <?php
                                    $currentDept = null;
                                    foreach ($courses as $c):
                                        if ($c['dept_name'] !== $currentDept):
                                            if ($currentDept !== null) echo '</optgroup>';
                                            $currentDept = $c['dept_name'];
                                            echo '<optgroup label="', e($currentDept), '">';
                                        endif; ?>
                                        <option value="<?= (int) $c['id'] ?>" <?= (string) $c['id'] === $old['course_id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
                                    <?php endforeach;
                                    if ($currentDept !== null) echo '</optgroup>'; ?>
                                </select>
                                <?= ferror($errors, 'course_id', 'Choose a course.') ?>
                            </div>
                            <div class="col-md-6">
                                <label for="study_mode" class="form-label">Study mode</label>
                                <select class="form-select<?= vclass($errors, 'study_mode') ?>" id="study_mode" name="study_mode" required>
                                    <option value="">Choose...</option>
                                    <?php foreach ($modes as $m): ?>
                                        <option <?= $old['study_mode'] === $m ? 'selected' : '' ?>><?= e($m) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= ferror($errors, 'study_mode', 'Choose a study mode.') ?>
                            </div>
                            <div class="col-md-6">
                                <label for="start_date" class="form-label">Preferred start date</label>
                                <select class="form-select<?= vclass($errors, 'start_date') ?>" id="start_date" name="start_date" required>
                                    <option value="">Choose...</option>
                                    <?php foreach ($intakes as $in): ?>
                                        <option value="<?= e($in['start_date']) ?>" <?= $old['start_date'] === $in['start_date'] ? 'selected' : '' ?>>
                                            <?= e(fmt_date($in['start_date'])) ?> (<?= e($in['label']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?= ferror($errors, 'start_date', 'Choose a start date.') ?>
                            </div>
                            <div class="col-12">
                                <label for="message" class="form-label">Message <span class="text-secondary fw-normal">(optional)</span></label>
                                <textarea class="form-control<?= vclass($errors, 'message') ?>" id="message" name="message" rows="4" maxlength="1000"
                                          placeholder="Anything we should know, such as previous experience or questions about fees"><?= e($old['message']) ?></textarea>
                                <?= ferror($errors, 'message', 'Please keep your message under 1,000 characters.') ?>
                            </div>
                        </fieldset>

                        <button type="submit" class="btn btn-gold btn-lg mt-4">Submit application</button>
                        <p class="small text-secondary mt-3 mb-0">We use your details only to process your application and contact you about it.</p>
                    </form>
                <?php endif; ?>
            </div>

            <aside class="col-lg-4" aria-label="Intake dates and help">
                <div class="side-panel">
                    <h2 class="h5 section-title">Upcoming intakes</h2>
                    <?php if ($intakes): ?>
                        <ul class="list-unstyled intake-list mb-0">
                            <?php foreach ($intakes as $in): ?>
                                <li>
                                    <strong><?= e($in['label']) ?></strong>
                                    <span class="d-block">Starts <?= e(fmt_date($in['start_date'])) ?></span>
                                    <span class="d-block small text-secondary">Apply by <?= e(fmt_date($in['application_deadline'])) ?>. <?= e($in['notes']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="mb-0">New intake dates will be published soon. Contact us to join the waiting list.</p>
                    <?php endif; ?>
                </div>
                <div class="side-panel mt-4">
                    <h2 class="h5 section-title">Need help applying?</h2>
                    <p class="small mb-3">Talk to the admissions team about courses, fees and entry requirements.</p>
                    <a class="btn btn-outline-navy btn-sm w-100 mb-2" href="tel:<?= e(preg_replace('/[^+\d]/', '', CONTACT_PHONE)) ?>"><i class="bi bi-telephone-fill" aria-hidden="true"></i> <?= e(CONTACT_PHONE) ?></a>
                    <a class="btn btn-outline-navy btn-sm w-100" href="https://wa.me/<?= e(CONTACT_WHATSAPP) ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-whatsapp" aria-hidden="true"></i> Message on WhatsApp</a>
                </div>
            </aside>
        </div>
    </div>
</section>

<!-- FAQ -->
<section class="section-space-sm" aria-labelledby="faq-title">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <h2 id="faq-title" class="section-title h2 mb-4">Frequently asked questions</h2>
                <div class="accordion" id="faq">
                    <?php
                    $faq = [
                        ['Do I need experience before I apply?', 'No. Beginner courses start from the basics. Intermediate and advanced courses list their entry requirements in the course details.'],
                        ['Can I study while I work?', 'Yes. Many courses run part-time or on weekends. Choose your preferred study mode in the application form.'],
                        ['When will I hear back about my application?', 'The admissions team contacts you within two working days by phone or email, using the reference number we give you after you apply.'],
                        ['How do I pay my fees?', 'Fees are shown in Ugandan shillings on every course. Payment details are sent when your place is confirmed. Contact admissions if you would like to discuss a payment plan.'],
                        ['Will I receive a certificate?', 'Learners who complete a course and its practical assessments receive a certificate from the institute.'],
                    ];
                    foreach ($faq as $i => [$q, $a]): ?>
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button <?= $i ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#faq<?= $i ?>" aria-expanded="<?= $i ? 'false' : 'true' ?>" aria-controls="faq<?= $i ?>"><?= e($q) ?></button>
                            </h3>
                            <div id="faq<?= $i ?>" class="accordion-collapse collapse <?= $i ? '' : 'show' ?>" data-bs-parent="#faq">
                                <div class="accordion-body text-secondary"><?= e($a) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
