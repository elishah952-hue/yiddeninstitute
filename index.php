<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/partials.php';

$pageTitle  = 'Yidden Institute';
$pageDesc   = 'Yidden Institute offers practical training in electrical installation, computer studies, web development, business, design, trades and agriculture in Uganda.';
$activePage = 'home';

$pdo = db();
$departments  = get_departments();
$courseCount  = (int) $pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();
$deptCount    = count($departments);
$years        = max(1, (int) date('Y') - FOUNDED);

$featured = $pdo->query(
    'SELECT c.*, d.name AS dept_name, d.slug AS dept_slug
     FROM courses c JOIN departments d ON d.id = c.department_id
     WHERE c.is_featured = 1 ORDER BY c.title LIMIT 6'
)->fetchAll();

$leadStory = $pdo->query('SELECT * FROM news WHERE is_featured = 1 AND published_at <= NOW() ORDER BY published_at DESC LIMIT 1')->fetch() ?: null;
$sideStories = [];
if ($leadStory) {
    $stmt = $pdo->prepare('SELECT * FROM news WHERE id <> :id AND published_at <= NOW() ORDER BY published_at DESC LIMIT 3');
    $stmt->execute([':id' => $leadStory['id']]);
    $sideStories = $stmt->fetchAll();
}
$events       = $pdo->query('SELECT * FROM events WHERE event_date >= NOW() ORDER BY event_date ASC LIMIT 5')->fetchAll();
$testimonials = $pdo->query('SELECT * FROM testimonials ORDER BY id')->fetchAll();
$intakes      = get_intakes();
$nextIntake   = $intakes[0] ?? null;

/** Paragraphs for a news body. */
function news_paragraphs(string $body): string
{
    $html = '';
    foreach (preg_split('/\n{2,}/', trim($body)) ?: [] as $p) {
        $html .= '<p>' . e(trim($p)) . '</p>';
    }
    return $html;
}

include __DIR__ . '/includes/header.php';
?>

<!-- 1. Hero -->
<section class="hero" aria-labelledby="hero-title">
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <h1 id="hero-title" class="fw-bold">Build the skills to shape your future.</h1>
                <p class="lead my-4">
                    Train in electrical installation, computer studies, web development and more at Yidden Institute.
                    Choose from <?= $courseCount ?> hands-on courses across <?= $deptCount ?> departments.
                </p>

                <form class="hero-search" action="courses.php" method="get" role="search">
                    <label for="heroSearch" class="visually-hidden">Search for a course</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white border-0"><i class="bi bi-search" aria-hidden="true"></i></span>
                        <input type="search" id="heroSearch" name="q" class="form-control border-0" maxlength="80"
                               placeholder="Find a course, such as solar, plumbing or design">
                        <button class="btn btn-gold" type="submit">Find a course</button>
                    </div>
                </form>

                <p class="hero__popular mt-3 mb-4">
                    Popular:
                    <a href="courses.php?q=solar">Solar</a>
                    <a href="courses.php?q=digital+marketing">Digital marketing</a>
                    <a href="courses.php?q=plumbing">Plumbing</a>
                    <a href="courses.php?q=graphic">Graphic design</a>
                </p>

                <a href="courses.php" class="btn btn-gold me-2 mb-2">Explore Courses</a>
                <a href="admissions.php" class="btn btn-outline-light rounded-0 mb-2">Apply for Admission</a>
            </div>
        </div>
    </div>
</section>

<!-- 2. Stats strip -->
<section class="stats" aria-label="Yidden Institute at a glance">
    <div class="container">
        <dl class="row g-4 text-center mb-0">
            <div class="col-6 col-lg-3 d-flex flex-column-reverse">
                <dt class="stat__label">Courses offered</dt>
                <dd class="stat__num" data-count="<?= $courseCount ?>"><?= $courseCount ?></dd>
            </div>
            <div class="col-6 col-lg-3 d-flex flex-column-reverse">
                <dt class="stat__label">Departments</dt>
                <dd class="stat__num" data-count="<?= $deptCount ?>"><?= $deptCount ?></dd>
            </div>
            <div class="col-6 col-lg-3 d-flex flex-column-reverse">
                <dt class="stat__label">Learners trained</dt>
                <dd class="stat__num" data-count="<?= STUDENTS_TRAINED ?>" data-suffix="+"><?= number_format(STUDENTS_TRAINED) ?>+</dd>
            </div>
            <div class="col-6 col-lg-3 d-flex flex-column-reverse">
                <dt class="stat__label">Years of training</dt>
                <dd class="stat__num" data-count="<?= $years ?>"><?= $years ?></dd>
            </div>
        </dl>
    </div>
</section>

<!-- 3. About -->
<section class="section-space" id="about" aria-labelledby="about-title">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <h2 id="about-title" class="section-title display-5">Knowledge matters. Practical skills matter too.</h2>
                <p class="text-secondary mt-3 mb-4">
                    Yidden Institute exists to give people in Uganda the skills employers and customers pay for.
                    Every course combines short lessons with long hours in the workshop, computer lab or field,
                    so you finish able to do the work, not just describe it.
                </p>
                <ul class="list-unstyled value-list">
                    <li>
                        <i class="bi bi-tools" aria-hidden="true"></i>
                        <div><h3 class="h6">Learn by doing</h3><p class="text-secondary mb-0">Most of your time is spent on real equipment and real projects.</p></div>
                    </li>
                    <li>
                        <i class="bi bi-people-fill" aria-hidden="true"></i>
                        <div><h3 class="h6">Taught by practitioners</h3><p class="text-secondary mb-0">Instructors work in the trades and industries they teach.</p></div>
                    </li>
                    <li>
                        <i class="bi bi-patch-check-fill" aria-hidden="true"></i>
                        <div><h3 class="h6">Ready for work</h3><p class="text-secondary mb-0">Certificates, portfolios and career support help you get hired or start earning.</p></div>
                    </li>
                </ul>
            </div>
            <div class="col-lg-5">
                <img src="https://images.unsplash.com/photo-1581092160562-40aa08e78837?auto=format&fit=crop&w=900&q=80"
                     class="img-fluid about-img" width="900" height="600"
                     alt="Learner working on a practical technical project" loading="lazy">
            </div>
        </div>
    </div>
</section>

<!-- 4. Departments -->
<section class="section-space cream" id="departments" aria-labelledby="dept-title">
    <div class="container">
        <div class="section-head text-center">
            <h2 id="dept-title" class="section-title display-5">Eight departments, one practical approach</h2>
            <p class="text-secondary">Pick a department to see every course it offers.</p>
        </div>
        <div class="row g-4">
            <?php foreach ($departments as $d): ?>
                <div class="col-sm-6 col-lg-3">
                    <a class="dept-card" href="courses.php?dept=<?= e($d['slug']) ?>">
                        <i class="bi <?= e($d['icon']) ?>" aria-hidden="true"></i>
                        <h3 class="h5"><?= e($d['name']) ?></h3>
                        <p><?= e($d['description']) ?></p>
                        <span class="dept-card__count"><?= (int) $d['course_count'] ?> courses</span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 5. Featured courses -->
<section class="section-space" id="featured" aria-labelledby="featured-title">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-5">
            <div>
                <h2 id="featured-title" class="section-title display-5 mb-2">Featured courses</h2>
                <p class="text-secondary mb-0">Popular choices with strong demand from employers and clients.</p>
            </div>
            <a class="btn btn-outline-navy" href="courses.php">Browse all <?= $courseCount ?> courses</a>
        </div>
        <div class="row g-4">
            <?php foreach ($featured as $c): ?>
                <div class="col-md-6 col-lg-4"><?= render_course_card($c, 'link') ?></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 6. Why study here -->
<section class="section-space cream" id="why" aria-labelledby="why-title">
    <div class="container">
        <div class="section-head text-center">
            <h2 id="why-title" class="section-title display-5">Why study at Yidden Institute</h2>
            <p class="text-secondary">What you can expect from the first day to graduation.</p>
        </div>
        <div class="row g-4">
            <?php
            $why = [
                ['bi-wrench-adjustable', 'Hands-on workshops', 'Practice on real equipment in workshops, labs and fields, not just in a classroom.'],
                ['bi-person-workspace', 'Experienced instructors', 'Learn from people who do this work every day and can show you how.'],
                ['bi-calendar2-week', 'Flexible schedules', 'Choose full-time, part-time or weekend study to fit work and family.'],
                ['bi-award', 'Recognized certificates', 'Finish with a certificate and a portfolio of work you can show employers.'],
                ['bi-briefcase', 'Job-readiness support', 'CV clinics, mock interviews and employer introductions help you take the next step.'],
                ['bi-cash-coin', 'Affordable fees', 'Clear fees in Ugandan shillings, with short courses starting from UGX 120,000.'],
            ];
            foreach ($why as [$icon, $title, $text]): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="why-block">
                        <i class="bi <?= e($icon) ?>" aria-hidden="true"></i>
                        <h3 class="h5"><?= e($title) ?></h3>
                        <p class="text-secondary mb-0"><?= e($text) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 7. News & events -->
<section class="section-space" id="news" aria-labelledby="news-title">
    <div class="container">
        <h2 id="news-title" class="section-title display-5 mb-5">News and events</h2>
        <div class="row g-5">
            <div class="col-lg-8">
                <?php if ($leadStory): ?>
                    <article class="lead-story">
                        <img src="<?= e($leadStory['image']) ?>" alt="" width="900" height="500" loading="lazy">
                        <p class="story-meta"><?= e($leadStory['category']) ?> &middot; <?= e(fmt_date($leadStory['published_at'])) ?></p>
                        <h3 class="h2 section-title">
                            <button type="button" class="news-link" data-news-id="<?= (int) $leadStory['id'] ?>"
                                    data-bs-toggle="modal" data-bs-target="#newsModal"><?= e($leadStory['title']) ?></button>
                        </h3>
                        <p class="text-secondary"><?= e($leadStory['excerpt']) ?></p>
                    </article>

                    <div class="row g-4 mt-1">
                        <?php foreach ($sideStories as $s): ?>
                            <div class="col-md-4">
                                <article class="side-story">
                                    <img src="<?= e($s['image']) ?>" alt="" width="400" height="250" loading="lazy">
                                    <p class="story-meta"><?= e($s['category']) ?> &middot; <?= e(fmt_date($s['published_at'], 'j M Y')) ?></p>
                                    <h3 class="h6 section-title">
                                        <button type="button" class="news-link" data-news-id="<?= (int) $s['id'] ?>"
                                                data-bs-toggle="modal" data-bs-target="#newsModal"><?= e($s['title']) ?></button>
                                    </h3>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-secondary">News will appear here soon.</p>
                <?php endif; ?>
            </div>

            <aside class="col-lg-4" aria-labelledby="events-title">
                <h3 id="events-title" class="h4 section-title events-title">Upcoming events</h3>
                <?php if ($events): ?>
                    <ul class="list-unstyled event-list">
                        <?php foreach ($events as $ev): $ts = strtotime($ev['event_date']); ?>
                            <li>
                                <time class="event-date" datetime="<?= e(date('Y-m-d', $ts)) ?>">
                                    <span class="event-date__day"><?= date('j', $ts) ?></span>
                                    <span class="event-date__month"><?= date('M', $ts) ?></span>
                                </time>
                                <div>
                                    <h4 class="h6 mb-1"><?= e($ev['title']) ?></h4>
                                    <p class="small text-secondary mb-0">
                                        <?= e(date('g:i a', $ts)) ?>, <?= e($ev['location']) ?><br>
                                        <?= e($ev['description']) ?>
                                    </p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-secondary">No events are scheduled right now. Check back soon.</p>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>

<!-- News story modal and templates -->
<?php foreach (array_merge($leadStory ? [$leadStory] : [], $sideStories) as $n): ?>
    <template id="news-<?= (int) $n['id'] ?>"
              data-title="<?= e($n['title']) ?>"
              data-meta="<?= e($n['category'] . ' · ' . fmt_date($n['published_at'])) ?>">
        <?= news_paragraphs($n['body']) ?>
    </template>
<?php endforeach; ?>
<div class="modal fade" id="newsModal" tabindex="-1" aria-labelledby="newsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <p class="story-meta mb-1" id="newsModalMeta"></p>
                    <h2 class="modal-title h4 section-title" id="newsModalTitle"></h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="newsModalBody"></div>
        </div>
    </div>
</div>

<!-- 8. Student stories -->
<?php if ($testimonials): ?>
<section class="section-space stories" id="stories" aria-labelledby="stories-title">
    <div class="container">
        <h2 id="stories-title" class="section-title display-6 text-center mb-5">Student stories</h2>
        <div id="storyCarousel" class="carousel slide" data-bs-ride="false">
            <div class="carousel-indicators">
                <?php foreach ($testimonials as $i => $t): ?>
                    <button type="button" data-bs-target="#storyCarousel" data-bs-slide-to="<?= $i ?>"
                            <?= $i === 0 ? 'class="active" aria-current="true"' : '' ?>
                            aria-label="Story <?= $i + 1 ?> of <?= count($testimonials) ?>"></button>
                <?php endforeach; ?>
            </div>
            <div class="carousel-inner">
                <?php foreach ($testimonials as $i => $t): ?>
                    <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                        <figure class="story mx-auto">
                            <blockquote><p><?= e($t['quote']) ?></p></blockquote>
                            <figcaption>
                                <span class="story__avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($t['name'], 0, 1))) ?></span>
                                <span><strong><?= e($t['name']) ?></strong><br><span class="small"><?= e($t['course']) ?></span></span>
                            </figcaption>
                        </figure>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#storyCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="visually-hidden">Previous story</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#storyCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span><span class="visually-hidden">Next story</span>
            </button>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- 9. Admissions call to action -->
<section class="section-space admissions" id="admissions" aria-labelledby="cta-title">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <h2 id="cta-title" class="display-5 section-title text-white">Your next chapter starts here.</h2>
                <?php if ($nextIntake): ?>
                    <p class="mb-0 fs-5">
                        Our next intake, <strong><?= e($nextIntake['label']) ?></strong>, starts on
                        <strong><?= e(fmt_date($nextIntake['start_date'])) ?></strong>.
                        Apply by <?= e(fmt_date($nextIntake['application_deadline'])) ?>.
                    </p>
                <?php else: ?>
                    <p class="mb-0 fs-5">Contact the institute to confirm available courses, fees and upcoming intakes.</p>
                <?php endif; ?>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="admissions.php" class="btn btn-gold btn-lg">Start Your Application</a>
            </div>
        </div>
    </div>
</section>

<!-- 10. Newsletter -->
<section class="section-space newsletter" id="newsletter" aria-labelledby="news-sign-title">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7 text-center">
                <h2 id="news-sign-title" class="section-title h2">Get course updates by email</h2>
                <p class="text-secondary">New intakes, short courses and events. No spam, and you can leave any time.</p>
                <form id="newsletterForm" class="newsletter-form" action="api/newsletter.php" method="post" novalidate>
                    <?= csrf_field() ?>
                    <div class="visually-hidden" aria-hidden="true">
                        <label for="nl-website">Leave this field empty</label>
                        <input type="text" id="nl-website" name="website" tabindex="-1" autocomplete="off">
                    </div>
                    <div class="input-group input-group-lg has-validation">
                        <label for="nl-email" class="visually-hidden">Email address</label>
                        <input type="email" id="nl-email" name="email" class="form-control" placeholder="Your email address"
                               required maxlength="160" autocomplete="email">
                        <button class="btn btn-navy" type="submit">Subscribe</button>
                        <div class="invalid-feedback">Enter a valid email address.</div>
                    </div>
                    <p class="form-status mt-3 mb-0" role="status" aria-live="polite"></p>
                </form>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
