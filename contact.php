<?php

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth-guard.php';
require_once __DIR__ . '/includes/mailer.php';

$page_title = 'Contact Us';
$active_nav = 'contact';

const CONTACT_SUBJECTS = [
    'general'   => 'General Enquiry',
    'order'     => 'Order Issue',
    'seller'    => 'Becoming a Seller',
    'feedback'  => 'Feedback',
    'technical' => 'Technical Problem',
    'other'     => 'Other',
];

// Prefill name and email for logged-in users
$prefill_name  = '';
$prefill_email = '';
if (is_logged_in()) {
    $prefill_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
    $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $prefill_email = (string)$stmt->fetchColumn();
}

$errors  = [];
$success = isset($_GET['sent']);
$fields  = [
    'name'    => $prefill_name,
    'email'   => $prefill_email,
    'subject' => '',
    'message' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fields['name']    = trim($_POST['name']    ?? '');
    $fields['email']   = strtolower(trim($_POST['email'] ?? ''));
    $fields['subject'] = trim($_POST['subject'] ?? '');
    $fields['message'] = trim($_POST['message'] ?? '');

    if ($fields['name'] === '')         $errors['name'] = 'Your name is required.';
    elseif (mb_strlen($fields['name']) > 200)
                                        $errors['name'] = 'Name is too long.';
    if ($fields['email'] === '')        $errors['email'] = 'Email address is required.';
    elseif (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL))
                                        $errors['email'] = 'Please enter a valid email address.';
    if (!isset(CONTACT_SUBJECTS[$fields['subject']]))
                                        $errors['subject'] = 'Please select a subject.';
    if ($fields['message'] === '')      $errors['message'] = 'Message cannot be empty.';
    elseif (mb_strlen($fields['message']) < 10)
                                        $errors['message'] = 'Message is too short — please provide more detail.';
    elseif (mb_strlen($fields['message']) > 1000)
                                        $errors['message'] = 'Message must be 1000 characters or fewer.';

    // Hidden field that people never fill in; simple bots do.
    $is_bot = trim($_POST['website'] ?? '') !== '';

    if (!$errors && !$is_bot && too_many_attempts($pdo, 'contact', $fields['email'])) {
        $errors['general'] = 'You have sent several messages recently. Please wait a while before sending another.';
    }

    if (!$errors) {
        if (!$is_bot) {
            record_attempt($pdo, 'contact', $fields['email']);

            // 1. Save the message, so nothing is lost even if email fails
            $pdo->prepare('
                INSERT INTO contact_messages (user_id, name, email, subject, message, ip)
                VALUES (?, ?, ?, ?, ?, ?)
            ')->execute([
                is_logged_in() ? $_SESSION['user_id'] : null,
                $fields['name'], $fields['email'], $fields['subject'], $fields['message'], client_ip(),
            ]);
            $message_id = (int)$pdo->lastInsertId();

            // 2. Email it to the team; replying goes straight to the sender
            $sent = send_mail(
                CONTACT_TO,
                '[Yum-Yum contact #' . $message_id . '] ' . CONTACT_SUBJECTS[$fields['subject']] . ' from ' . $fields['name'],
                "New message from the Yum-Yum contact form\n\n"
                . "Name:    {$fields['name']}\n"
                . "Email:   {$fields['email']}\n"
                . 'Subject: ' . CONTACT_SUBJECTS[$fields['subject']] . "\n"
                . 'Account: ' . (is_logged_in() ? $_SESSION['role'] . ' #' . $_SESSION['user_id'] : 'not logged in') . "\n\n"
                . $fields['message'] . "\n",
                $fields['email']
            );
            if ($sent) {
                $pdo->prepare('UPDATE contact_messages SET emailed = 1 WHERE id = ?')->execute([$message_id]);
            }
        }
        // Post/Redirect/Get: refreshing the page will not send it twice
        header('Location: ' . BASE_PATH . 'contact.php?sent=1');
        exit;
    }
}

function field_err(string $f, array $e): string {
    return isset($e[$f])
        ? '<span style="font-size:0.75rem;font-weight:600;color:#ef4444;margin-top:0.25rem;">' . htmlspecialchars($e[$f]) . '</span>'
        : '';
}

function input_style(string $f, array $e): string {
    $base = 'width:100%;padding:0.875rem 1rem;background:var(--bg-light);border:1.5px solid var(--border-light);border-radius:var(--radius-lg);font-family:var(--font-body);font-size:0.9375rem;color:var(--earth);outline:none;transition:border-color 0.15s ease,box-shadow 0.15s ease;';
    return $base . (isset($e[$f]) ? 'border-color:#ef4444;box-shadow:0 0 0 3px rgba(239,68,68,0.12);' : '');
}

require_once __DIR__ . '/includes/header.php';
?>
<section style="background:var(--bg-dark);padding:4.5rem 1.5rem;text-align:center;position:relative;overflow:hidden;">
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:500px;height:250px;background:var(--primary);opacity:0.06;border-radius:50%;filter:blur(70px);pointer-events:none;"></div>
    <div style="max-width:560px;margin:0 auto;position:relative;z-index:1;">
        <span style="display:inline-flex;align-items:center;gap:0.5rem;background:rgba(244,209,37,0.12);color:var(--primary);padding:0.375rem 1rem;border-radius:9999px;font-size:0.875rem;font-weight:700;margin-bottom:1.25rem;">
            <span class="material-symbols-outlined" style="font-size:1rem;font-variation-settings:'FILL' 1;">chat</span>
            Get in Touch
        </span>
        <h1 style="font-size:clamp(2rem,4vw,3rem);font-weight:800;color:var(--white);letter-spacing:-0.03em;line-height:1.15;margin-bottom:1rem;">
            We'd love to <span style="color:var(--primary);">hear from you.</span>
        </h1>
        <p style="color:rgba(255,255,255,0.65);font-size:1rem;line-height:1.75;font-weight:500;">
            Whether you have a question, a complaint, or just want to say hi — our team reads every message and gets back to you within 24 hours.
        </p>
    </div>
</section>

<div style="max-width:1100px;margin:0 auto;padding:4rem 1.5rem 5rem;">
    <div style="display:grid;grid-template-columns:1fr 420px;gap:3rem;align-items:start;">

        <!-- Left: contact form -->
        <div>
            <h2 style="font-size:1.375rem;font-weight:800;color:var(--earth);letter-spacing:-0.02em;margin-bottom:0.375rem;">
                Send us a message
            </h2>
            <p style="color:var(--sage);font-size:0.9375rem;margin-bottom:2rem;">
                Fill in the form and we'll get back to you as soon as possible.
            </p>

            <!-- Success state -->
            <?php if ($success): ?>
                <div style="background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.25);border-radius:var(--radius-2xl);padding:2.5rem;text-align:center;">
                    <div style="width:4rem;height:4rem;background:rgba(34,197,94,0.15);border-radius:9999px;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;">
                        <span class="material-symbols-outlined" style="font-size:2rem;color:#16a34a;font-variation-settings:'FILL' 1;">check_circle</span>
                    </div>
                    <h3 style="font-size:1.25rem;font-weight:800;color:var(--earth);margin-bottom:0.5rem;">Message sent!</h3>
                    <p style="color:var(--sage);font-size:0.9375rem;line-height:1.7;margin-bottom:1.5rem;">
                        Thanks for reaching out. We'll get back to you within 24 hours.
                    </p>
                    <a href="<?= BASE_PATH ?>contact.php"
                            style="display:inline-flex;align-items:center;gap:0.5rem;background:var(--primary);color:var(--earth);padding:0.75rem 1.5rem;border-radius:var(--radius-xl);border:none;font-family:var(--font-body);font-weight:800;font-size:0.875rem;cursor:pointer;">
                        Send Another Message
                    </a>
                </div>

            <?php else: ?>

                <!-- Error banner -->
                <?php if (!empty($errors)): ?>
                    <div style="padding:0.875rem 1.25rem;border-radius:var(--radius-lg);background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);color:#dc2626;font-size:0.9375rem;font-weight:600;margin-bottom:1.5rem;display:flex;align-items:center;gap:0.625rem;">
                        <span class="material-symbols-outlined" style="font-size:1.1rem;font-variation-settings:'FILL' 1;">error</span>
                        <?= e($errors['general'] ?? 'Please fix the errors below.') ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" novalidate
                      style="display:flex;flex-direction:column;gap:1.25rem;background:var(--white);border:1px solid var(--border-light);border-radius:var(--radius-2xl);padding:2rem;box-shadow:var(--shadow-sm);">
                    <?= csrf_field() ?>

                    <!-- Left empty on purpose: a trap for spam bots -->
                    <div style="position:absolute;left:-9999px;" aria-hidden="true">
                        <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off" /></label>
                    </div>

                    <!-- Name + Email row -->
                    <div class="contact-two-col">

                        <div style="display:flex;flex-direction:column;gap:0.4rem;">
                            <label style="font-size:0.875rem;font-weight:700;color:var(--earth);">
                                Full Name <span style="color:#ef4444;">*</span>
                            </label>
                            <input
                                type="text"
                                name="name"
                                value="<?= htmlspecialchars($fields['name']) ?>"
                                placeholder="Ananya Sharma"
                                style="<?= input_style('name', $errors) ?>"
                                onfocus="this.style.borderColor='var(--primary)';this.style.boxShadow='0 0 0 3px rgba(244,209,37,0.2)'"
                                onblur="this.style.borderColor='var(--border-light)';this.style.boxShadow='none'"
                            />
                            <?= field_err('name', $errors) ?>
                        </div>

                        <div style="display:flex;flex-direction:column;gap:0.4rem;">
                            <label style="font-size:0.875rem;font-weight:700;color:var(--earth);">
                                Email Address <span style="color:#ef4444;">*</span>
                            </label>
                            <input
                                type="email"
                                name="email"
                                value="<?= htmlspecialchars($fields['email']) ?>"
                                placeholder="johnsmith@gmail.com"
                                style="<?= input_style('email', $errors) ?>"
                                onfocus="this.style.borderColor='var(--primary)';this.style.boxShadow='0 0 0 3px rgba(244,209,37,0.2)'"
                                onblur="this.style.borderColor='var(--border-light)';this.style.boxShadow='none'"
                            />
                            <?= field_err('email', $errors) ?>
                        </div>

                    </div>

                    <!-- Subject -->
                    <div style="display:flex;flex-direction:column;gap:0.4rem;">
                        <label style="font-size:0.875rem;font-weight:700;color:var(--earth);">
                            Subject <span style="color:#ef4444;">*</span>
                        </label>
                        <select
                            name="subject"
                            style="<?= input_style('subject', $errors) ?> cursor:pointer;"
                            onfocus="this.style.borderColor='var(--primary)';this.style.boxShadow='0 0 0 3px rgba(244,209,37,0.2)'"
                            onblur="this.style.borderColor='var(--border-light)';this.style.boxShadow='none'"
                        >
                            <option value="">— Select a subject —</option>
                            <?php foreach (CONTACT_SUBJECTS as $val => $label): ?>
                                <option value="<?= $val ?>" <?= $fields['subject'] === $val ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_err('subject', $errors) ?>
                    </div>

                    <!-- Message -->
                    <div style="display:flex;flex-direction:column;gap:0.4rem;">
                        <label style="font-size:0.875rem;font-weight:700;color:var(--earth);">
                            Message <span style="color:#ef4444;">*</span>
                        </label>
                        <textarea
                            name="message"
                            rows="6"
                            placeholder="Tell us what's on your mind…"
                            style="<?= input_style('message', $errors) ?> resize:vertical;"
                            onfocus="this.style.borderColor='var(--primary)';this.style.boxShadow='0 0 0 3px rgba(244,209,37,0.2)'"
                            onblur="this.style.borderColor='var(--border-light)';this.style.boxShadow='none'"
                        ><?= htmlspecialchars($fields['message']) ?></textarea>
                        <?= field_err('message', $errors) ?>
                        <span style="font-size:0.75rem;color:var(--sage);font-weight:600;align-self:flex-end;" id="char-count">0 / 1000</span>
                    </div>

                    <!-- Submit -->
                    <button type="submit"
                            style="display:inline-flex;align-items:center;justify-content:center;gap:0.5rem;background:var(--primary);color:var(--earth);padding:1rem;border-radius:var(--radius-xl);border:none;font-family:var(--font-body);font-size:1rem;font-weight:800;cursor:pointer;box-shadow:0 4px 14px rgba(244,209,37,0.3);transition:box-shadow 0.25s ease,transform 0.15s ease;"
                            onmouseover="this.style.boxShadow='0 6px 20px rgba(244,209,37,0.45)';this.style.transform='translateY(-1px)'"
                            onmouseout="this.style.boxShadow='0 4px 14px rgba(244,209,37,0.3)';this.style.transform='translateY(0)'">
                        <span class="material-symbols-outlined">send</span>
                        Send Message
                    </button>

                </form>

            <?php endif; ?>
        </div>

        <!-- Right: contact info cards -->
        <div style="display:flex;flex-direction:column;gap:1rem;position:sticky;top:88px;">

            <?php
            $info = [
                ['icon' => 'mail',           'color' => 'rgba(59,130,246,0.12)',  'ic' => '#2563eb', 'title' => 'Email Us',        'lines' => ['hello@yumyum.app', 'We reply within 24 hours']],
                ['icon' => 'phone',          'color' => 'rgba(34,197,94,0.12)',   'ic' => '#16a34a', 'title' => 'Call Us',         'lines' => ['0483287548',  'Mon–Fri, 9am–6pm']],
                ['icon' => 'location_on',    'color' => 'rgba(244,209,37,0.15)',  'ic' => '#92400e', 'title' => 'Find Us',         'lines' => ['119 Queen Street', 'Brisbane, QLD 4000']],
                ['icon' => 'schedule',       'color' => 'rgba(139,92,246,0.12)',  'ic' => '#7c3aed', 'title' => 'Business Hours', 'lines' => ['Mon–Fri: 9am – 6pm']],
            ];
            foreach ($info as $card): ?>
                <div style="background:var(--white);border:1px solid var(--border-light);border-radius:var(--radius-2xl);padding:1.375rem 1.5rem;box-shadow:var(--shadow-sm);display:flex;align-items:flex-start;gap:1rem;">
                    <div style="width:2.75rem;height:2.75rem;border-radius:var(--radius-lg);background:<?= $card['color'] ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <span class="material-symbols-outlined" style="font-size:1.25rem;color:<?= $card['ic'] ?>;font-variation-settings:'FILL' 1;"><?= $card['icon'] ?></span>
                    </div>
                    <div>
                        <div style="font-size:0.875rem;font-weight:800;color:var(--earth);margin-bottom:0.25rem;"><?= $card['title'] ?></div>
                        <?php foreach (($card['lines'] ?? []) as $line): ?>
                            <div style="font-size:0.875rem;color:var(--sage);font-weight:600;line-height:1.6;"><?= htmlspecialchars($line) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>


        </div>

    </div>
</div>

<script>
// Character counter for message textarea
(function () {
    const textarea  = document.querySelector('textarea[name="message"]');
    const counter   = document.getElementById('char-count');
    if (!textarea || !counter) return;

    function update() {
        const len = textarea.value.length;
        counter.textContent = len + ' / 1000';
        if (len > 900) counter.style.color = '#ef4444';
        else if (len > 700) counter.style.color = '#f97316';
        else counter.style.color = 'var(--sage)';
        if (len > 1000) textarea.value = textarea.value.substring(0, 1000);
    }

    textarea.addEventListener('input', update);
    update();
})();
</script>

<style>
@media (max-width: 900px) {
    div[style*="grid-template-columns:1fr 420px"] {
        grid-template-columns: 1fr !important;
    }
    div[style*="grid-template-columns:1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
    div[style*="position:sticky"] {
        position: static !important;
    }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>