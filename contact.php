<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$pageTitle  = 'Contact';
$pageDesc   = 'Contact Yidden Institute by phone, WhatsApp, email or the online form. Find our address, opening hours and map.';
$activePage = 'contact';

$subjects = ['General enquiry', 'Course information', 'Fees and payment', 'Admissions support', 'Partnerships and employers'];
$errors   = [];
$old      = ['name' => '', 'email' => '', 'phone' => '', 'subject' => '', 'message' => ''];
$sent     = false;

if (isset($_GET['sent']) && !empty($_SESSION['contact_done'])) {
    $sent = true;
    unset($_SESSION['contact_done']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $_) {
        $old[$k] = field($_POST, $k, $k === 'message' ? 2000 : 160);
    }

    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $errors['form'] = 'Your session expired. Please check your message and send it again.';
    } elseif (field($_POST, 'website') !== '') {
        $_SESSION['contact_done'] = true; // honeypot: pretend success
        header('Location: contact.php?sent=1#form');
        exit;
    } elseif (rate_limited('contact', 5, 60)) {
        $errors['form'] = 'You have sent several messages recently. Please wait a while or call us on ' . CONTACT_PHONE . '.';
    } else {
        if (!valid_name($old['name'])) {
            $errors['name'] = 'Enter your name using letters only.';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if ($old['phone'] !== '' && !valid_phone($old['phone'])) {
            $errors['phone'] = 'Enter a phone number with 9 to 15 digits, or leave it blank.';
        }
        if (!in_array($old['subject'], $subjects, true)) {
            $errors['subject'] = 'Choose a subject.';
        }
        if (mb_strlen($old['message']) < 10) {
            $errors['message'] = 'Write a message of at least 10 characters.';
        }

        if (!$errors) {
            record_attempt('contact');
            $stmt = db()->prepare(
                'INSERT INTO contact_messages (name, email, phone, subject, message, ip)
                 VALUES (:name, :email, :phone, :subject, :message, :ip)'
            );
            $stmt->execute([
                ':name'    => $old['name'],
                ':email'   => strtolower($old['email']),
                ':phone'   => $old['phone'] !== '' ? $old['phone'] : null,
                ':subject' => $old['subject'],
                ':message' => $old['message'],
                ':ip'      => client_ip(),
            ]);

            send_mail(
                SITE_EMAIL,
                'Website message: ' . $old['subject'],
                "From: {$old['name']} <{$old['email']}>\nPhone: {$old['phone']}\n\n{$old['message']}\n",
                $old['email']
            );
            send_mail(
                $old['email'],
                'We received your message',
                "Hello {$old['name']},\n\nThank you for contacting " . SITE_NAME . ". We have your message and will reply within two working days.\n\n"
                . 'Phone: ' . CONTACT_PHONE . "\n\n" . SITE_NAME
            );

            unset($_SESSION['csrf']);
            $_SESSION['contact_done'] = true;
            header('Location: contact.php?sent=1#form');
            exit;
        }
    }
}

function vclass(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' is-invalid' : '';
}

function ferror(array $errors, string $key, string $fallback): string
{
    return '<div class="invalid-feedback">' . e($errors[$key] ?? $fallback) . '</div>';
}

$telHref = preg_replace('/[^+\d]/', '', CONTACT_PHONE);
$mapQuery = rawurlencode(CONTACT_ADDRESS);

include __DIR__ . '/includes/header.php';
?>

<section class="page-hero" aria-labelledby="page-title">
    <div class="container">
        <nav aria-label="Breadcrumb">
            <ol class="breadcrumb mb-3">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Contact</li>
            </ol>
        </nav>
        <h1 id="page-title" class="fw-bold">Contact the institute</h1>
        <p class="lead mb-0">Questions about courses, fees or admissions? Call, message us or send the form and we will reply within two working days.</p>
    </div>
</section>

<section class="section-space-sm" aria-labelledby="touch-title">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-5">
                <h2 id="touch-title" class="section-title h2 mb-4">Get in touch</h2>
                <ul class="list-unstyled contact-list">
                    <li><i class="bi bi-geo-alt-fill" aria-hidden="true"></i><div><h3 class="h6">Address</h3><address class="mb-0"><?= e(CONTACT_ADDRESS) ?></address></div></li>
                    <li><i class="bi bi-telephone-fill" aria-hidden="true"></i><div><h3 class="h6">Phone</h3><a href="tel:<?= e($telHref) ?>"><?= e(CONTACT_PHONE) ?></a></div></li>
                    <li><i class="bi bi-whatsapp" aria-hidden="true"></i><div><h3 class="h6">WhatsApp</h3><a href="https://wa.me/<?= e(CONTACT_WHATSAPP) ?>" target="_blank" rel="noopener noreferrer">Chat with admissions</a></div></li>
                    <li><i class="bi bi-envelope-fill" aria-hidden="true"></i><div><h3 class="h6">Email</h3><a href="mailto:<?= e(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a></div></li>
                    <li><i class="bi bi-clock-fill" aria-hidden="true"></i><div><h3 class="h6">Opening hours</h3><?= e(CONTACT_HOURS) ?></div></li>
                </ul>
                <h3 class="h6 mt-4">Follow us</h3>
                <div class="d-flex gap-3 social social--dark">
                    <?php foreach (SOCIAL as $name => [$url, $icon]): ?>
                        <a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e($name) ?>"><i class="bi <?= e($icon) ?>" aria-hidden="true"></i></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-lg-7" id="form">
                <h2 class="section-title h2 mb-4">Send us a message</h2>

                <?php if ($sent): ?>
                    <div class="success-panel" role="status" tabindex="-1" id="contactSuccess">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                        <h3 class="h4">Message sent</h3>
                        <p class="mb-3">Thank you. We have your message and will reply within two working days.</p>
                        <a href="contact.php" class="btn btn-navy">Send another message</a>
                    </div>
                <?php else: ?>
                    <?php if (isset($errors['form'])): ?>
                        <div class="alert alert-danger" role="alert"><?= e($errors['form']) ?></div>
                    <?php elseif ($errors): ?>
                        <div class="alert alert-danger" role="alert">Please fix the highlighted fields and send again.</div>
                    <?php endif; ?>

                    <form class="app-form needs-validation" action="contact.php#form" method="post" novalidate>
                        <?= csrf_field() ?>
                        <div class="visually-hidden" aria-hidden="true">
                            <label for="website">Leave this field empty</label>
                            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Your name</label>
                                <input type="text" class="form-control<?= vclass($errors, 'name') ?>" id="name" name="name"
                                       value="<?= e($old['name']) ?>" required minlength="2" maxlength="120" autocomplete="name">
                                <?= ferror($errors, 'name', 'Enter your name.') ?>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email address</label>
                                <input type="email" class="form-control<?= vclass($errors, 'email') ?>" id="email" name="email"
                                       value="<?= e($old['email']) ?>" required maxlength="160" autocomplete="email">
                                <?= ferror($errors, 'email', 'Enter a valid email address.') ?>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone <span class="text-secondary fw-normal">(optional)</span></label>
                                <input type="tel" class="form-control<?= vclass($errors, 'phone') ?>" id="phone" name="phone"
                                       value="<?= e($old['phone']) ?>" pattern="\+?[0-9 \-]{9,20}" maxlength="20" autocomplete="tel">
                                <?= ferror($errors, 'phone', 'Enter a phone number with 9 to 15 digits.') ?>
                            </div>
                            <div class="col-md-6">
                                <label for="subject" class="form-label">Subject</label>
                                <select class="form-select<?= vclass($errors, 'subject') ?>" id="subject" name="subject" required>
                                    <option value="">Choose...</option>
                                    <?php foreach ($subjects as $s): ?>
                                        <option <?= $old['subject'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= ferror($errors, 'subject', 'Choose a subject.') ?>
                            </div>
                            <div class="col-12">
                                <label for="message" class="form-label">Message</label>
                                <textarea class="form-control<?= vclass($errors, 'message') ?>" id="message" name="message" rows="6"
                                          required minlength="10" maxlength="2000"><?= e($old['message']) ?></textarea>
                                <?= ferror($errors, 'message', 'Write a message of at least 10 characters.') ?>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-gold btn-lg mt-4">Send message</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="map-wrap" aria-label="Map">
    <iframe title="Map showing the institute location" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
            src="https://www.google.com/maps?q=<?= e($mapQuery) ?>&amp;output=embed" allowfullscreen></iframe>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
