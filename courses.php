<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/partials.php';

$pageTitle  = 'Courses';
$pageDesc   = 'Browse 50+ practical courses in electrical, computer studies, web development, business, design, trades, agriculture and communication. Search by topic, level and duration.';
$activePage = 'courses';

$departments = get_departments();
$filters     = course_filters($_GET);
$result      = fetch_courses($filters);
$totalCourses = (int) db()->query('SELECT COUNT(*) FROM courses')->fetchColumn();
$openId      = isset($_GET['open']) ? max(0, (int) $_GET['open']) : 0;

include __DIR__ . '/includes/header.php';
?>

<section class="page-hero" aria-labelledby="page-title">
    <div class="container">
        <nav aria-label="Breadcrumb">
            <ol class="breadcrumb mb-3">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Courses</li>
            </ol>
        </nav>
        <h1 id="page-title" class="fw-bold">Find your course</h1>
        <p class="lead mb-0"><?= $totalCourses ?> practical courses across <?= count($departments) ?> departments. Search, filter and compare fees and duration.</p>
    </div>
</section>

<section class="section-space-sm" id="catalog" data-open="<?= $openId ?>">
    <div class="container">

        <form id="filterForm" class="filters" action="courses.php" method="get" role="search" aria-label="Filter courses">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-lg-4">
                    <label for="q" class="form-label">Search</label>
                    <input type="search" class="form-control" id="q" name="q" maxlength="80"
                           placeholder="Course name or topic" value="<?= e($filters['q']) ?>">
                </div>
                <div class="col-6 col-lg-2">
                    <label for="dept" class="form-label">Department</label>
                    <select class="form-select" id="dept" name="dept">
                        <option value="">All departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= e($d['slug']) ?>" <?= $filters['dept'] === $d['slug'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label for="level" class="form-label">Level</label>
                    <select class="form-select" id="level" name="level">
                        <option value="">All levels</option>
                        <?php foreach (['Beginner', 'Intermediate', 'Advanced'] as $lv): ?>
                            <option <?= $filters['level'] === $lv ? 'selected' : '' ?>><?= $lv ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label for="duration" class="form-label">Duration</label>
                    <select class="form-select" id="duration" name="duration">
                        <option value="">Any duration</option>
                        <option value="short" <?= $filters['duration'] === 'short' ? 'selected' : '' ?>>Up to 1 month</option>
                        <option value="medium" <?= $filters['duration'] === 'medium' ? 'selected' : '' ?>>2 to 3 months</option>
                        <option value="long" <?= $filters['duration'] === 'long' ? 'selected' : '' ?>>4 months or more</option>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label for="sort" class="form-label">Sort by</label>
                    <select class="form-select" id="sort" name="sort">
                        <?php
                        $sorts = ['az' => 'Name A to Z', 'fee_asc' => 'Fee: low to high', 'fee_desc' => 'Fee: high to low',
                                  'duration_asc' => 'Shortest first', 'duration_desc' => 'Longest first'];
                        foreach ($sorts as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $filters['sort'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                <p id="resultSummary" class="mb-0 fw-bold" role="status" aria-live="polite"><?= e(results_summary($result)) ?></p>
                <div class="d-flex gap-2">
                    <noscript><button class="btn btn-navy btn-sm" type="submit">Apply filters</button></noscript>
                    <a href="courses.php" class="btn btn-link btn-sm" id="resetFilters">Clear filters</a>
                </div>
            </div>
        </form>

        <div id="results" class="row g-4 mt-1" aria-busy="false"><?= render_results($result) ?></div>
        <div id="pager" class="mt-5"><?= render_pagination($result, $filters) ?></div>
    </div>
</section>

<!-- Course details modal -->
<div class="modal fade" id="courseModal" tabindex="-1" aria-labelledby="courseModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <p class="story-meta mb-1" id="cmDept"></p>
                    <h2 class="modal-title h3 section-title" id="courseModalTitle">Course details</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="cmLoading" class="text-secondary" role="status">Loading course details...</div>
                <div id="cmError" class="alert alert-warning d-none" role="alert">We could not load this course. Please close this window and try again.</div>
                <div id="cmContent" class="d-none">
                    <p id="cmDescription"></p>
                    <dl class="row course-facts">
                        <div class="col-6 col-md-3"><dt>Level</dt><dd id="cmLevel"></dd></div>
                        <div class="col-6 col-md-3"><dt>Duration</dt><dd id="cmDuration"></dd></div>
                        <div class="col-6 col-md-3"><dt>Mode</dt><dd id="cmMode"></dd></div>
                        <div class="col-6 col-md-3"><dt>Fee</dt><dd id="cmFee"></dd></div>
                    </dl>
                    <h3 class="h5 section-title">What you will learn</h3>
                    <ul id="cmOutcomes" class="check-list"></ul>
                    <h3 class="h5 section-title mt-4">Entry requirements</h3>
                    <p id="cmRequirements" class="mb-0"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-navy" data-bs-dismiss="modal">Close</button>
                <a href="admissions.php" id="cmApply" class="btn btn-gold">Apply for this course</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
