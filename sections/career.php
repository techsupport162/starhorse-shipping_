<?php
/**
 * sections/career.php — Public Career section.
 * Displays published career opportunities from the Career Management system.
 * Draft/hidden postings must not be displayed publicly.
 *
 * Career postings (positions, recruitment images, Published/Draft status)
 * are pulled from the database and managed via starhorse-vessel-admin/
 * (Career Management module). Only PUBLISHED postings are ever shown
 * here — same convention as sections/vessels.php and sections/rates.php.
 *
 * Behaviour (cards, Details/Apply modals, resume dropzone, submission)
 * lives in js/scripts.js; styles in css/site.css (section 11).
 */

// csrf_token() needs a session — no-op if index.php already started one.
require_once __DIR__ . '/../includes/security.php';
sh_start_session();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../starhorse-vessel-admin/functions/helpers.php';
require_once __DIR__ . '/../starhorse-vessel-admin/functions/career_functions.php';

$careerPostings = get_published_career_postings();
$careerImageBase = 'starhorse-vessel-admin/uploads/career/';

// Only the fields the public page actually needs are exposed here (no
// internal/admin-only data) — embedded as JSON so the Details and Apply
// modals can populate instantly from a single already-loaded payload
// instead of firing a separate request per posting. JSON_HEX_* flags
// keep this safe to embed inside a <script> tag regardless of what
// admin-authored text a posting contains.
$careerPostingsForJs = array_map(function ($p) use ($careerImageBase) {
    return [
        'id'                        => (int) $p['id'],
        'title'                     => $p['title'],
        'position_name'             => $p['position_name'],
        'short_description'         => $p['short_description'],
        'job_description'           => $p['job_description'],
        'qualifications'            => $p['qualifications'],
        'responsibilities'          => $p['responsibilities'],
        'application_instructions' => $p['application_instructions'],
        'contact_email'             => $p['contact_email'],
        'image_url'                 => image_asset_url($p['recruitment_image'], $careerImageBase),
    ];
}, $careerPostings);
?>
<section class="page-section" id="career">
    <div class="container px-4 px-lg-5">
        <div class="row gx-4 gx-lg-5 justify-content-center">
            <div class="col-lg-8 col-xl-6 text-center">
                <h2 class="mt-0">Join Our Team!</h2>
                <hr class="divider" />
                <p class="text-muted mb-5">
                    We're always looking for dedicated individuals to join the Starhorse family.
                    Explore our current openings below and apply today.
                </p>
            </div>
        </div>

        <?php if (empty($careerPostings)): ?>
        <div class="sh-empty-state">
            <i class="bi bi-briefcase" aria-hidden="true"></i>
            <p class="mb-0">No current job openings at the moment. Please check back soon!</p>
        </div>
        <?php else: ?>

        <div class="row g-4 justify-content-center" id="careerCardGrid">
            <?php foreach ($careerPostings as $posting):
                $postingId = (int) $posting['id'];
                $postingTitle = htmlspecialchars($posting['title'], ENT_QUOTES, 'UTF-8');
                $positionName = htmlspecialchars($posting['position_name'], ENT_QUOTES, 'UTF-8');
                $imageAssetUrl = image_asset_url($posting['recruitment_image'], $careerImageBase);
                $imageUrl = $imageAssetUrl !== null
                    ? htmlspecialchars($imageAssetUrl, ENT_QUOTES, 'UTF-8')
                    : null;
            ?>
            <div class="col-md-6 col-lg-4">
                <article class="sh-career-card" data-posting-id="<?= $postingId ?>" data-animate-in>
                    <div class="sh-career-card__media">
                        <?php if ($imageUrl): ?>
                        <img src="<?= $imageUrl ?>" alt="<?= $postingTitle ?> recruitment poster" loading="lazy"
                            decoding="async">
                        <?php else: ?>
                        <div class="sh-career-card__media-empty">
                            <i class="bi bi-briefcase" aria-hidden="true"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="sh-career-card__body">
                        <span class="sh-career-card__badge"><?= $positionName ?></span>
                        <h3 class="sh-career-card__title"><?= $postingTitle ?></h3>
                        <?php if (!empty($posting['short_description'])): ?>
                        <p class="sh-career-card__desc">
                            <?= htmlspecialchars($posting['short_description'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="sh-career-card__footer">
                        <button type="button" class="btn btn-outline-primary btn-sm sh-career-btn-details"
                            data-posting-id="<?= $postingId ?>">
                            View Details
                        </button>
                        <button type="button" class="btn btn-primary btn-sm sh-career-btn-apply"
                            data-posting-id="<?= $postingId ?>">
                            Apply Now
                        </button>
                    </div>
                </article>
            </div>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>
    </div>
</section>

<!-- =========================================================================
     Details Modal — populated from #careerPostingsData by JS. Its own
     "Apply Now" hands off to the Apply modal for the same posting.
     Desktop (lg+): poster on the left, details on the right (the details
     column scrolls on its own). Smaller screens: poster stacked on top.
     ========================================================================= -->
<div class="modal fade sh-career-detail-modal" id="careerDetailsModal" tabindex="-1"
    aria-labelledby="careerDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <button type="button" class="btn-close sh-career-detail__close" data-bs-dismiss="modal"
                aria-label="Close"></button>
            <div class="modal-body p-0">
                <div class="sh-career-detail" id="careerDetailsLayout">
                    <div class="sh-career-detail__media" id="careerDetailsMedia">
                        <!-- populated by JS -->
                    </div>
                    <div class="sh-career-detail__info">
                        <span class="sh-career-card__badge" id="careerDetailsBadge"></span>
                        <h3 class="sh-career-detail__title" id="careerDetailsModalLabel">Job Details</h3>
                        <div id="careerDetailsBody">
                            <!-- populated by JS -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="careerDetailsApplyBtn">
                    <i class="bi bi-send-fill me-1" aria-hidden="true"></i> Apply Now
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     Apply Modal — one reusable form; JS sets which posting it targets.
     Field-level errors render directly under each input (never a single
     generic error banner), matching the rest of the site's forms.
     Same landscape layout as the Details modal: poster left, form right
     (lg+); poster stacked on top on smaller screens.
     ========================================================================= -->
<div class="modal fade sh-career-detail-modal sh-career-apply-modal" id="careerApplyModal" tabindex="-1"
    aria-labelledby="careerApplyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form id="careerApplyForm" class="sh-career-apply-form" novalidate enctype="multipart/form-data">
                <button type="button" class="btn-close sh-career-detail__close" data-bs-dismiss="modal"
                    aria-label="Close"></button>
                <div class="modal-body p-0">
                    <div class="sh-career-detail" id="careerApplyLayout">
                        <div class="sh-career-detail__media" id="careerApplyMedia">
                            <!-- populated by JS -->
                        </div>
                        <div class="sh-career-detail__info">
                            <span class="sh-career-card__badge">Apply Now</span>
                            <h3 class="sh-career-detail__title mb-1" id="careerApplyModalLabel">Submit Your Application
                            </h3>
                            <p class="text-muted mb-4">
                                Applying for: <strong class="text-body" id="careerApplyPositionLabel">&mdash;</strong>
                            </p>

                            <div id="careerApplyAlert" class="alert alert-danger d-none" role="alert"></div>
                            <div id="careerApplySuccess" class="alert alert-success d-none" role="alert"></div>

                            <div id="careerApplyFields">
                                <input type="hidden" name="csrf_token"
                                    value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="posting_id" id="careerApplyPostingId" value="">

                                <div class="row g-3 mb-3">
                                    <div class="col-12">
                                        <label for="careerApplyFullName" class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" id="careerApplyFullName"
                                            name="full_name" maxlength="150" required>
                                        <div class="invalid-feedback"></div>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="careerApplyEmail" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="careerApplyEmail" name="email"
                                            maxlength="150" required>
                                        <div class="invalid-feedback"></div>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="careerApplyContactNumber" class="form-label">Contact Number
                                            *</label>
                                        <input type="tel" class="form-control" id="careerApplyContactNumber"
                                            name="contact_number" maxlength="30" required>
                                        <div class="invalid-feedback"></div>
                                    </div>

                                    <div class="col-12">
                                        <label for="careerApplyAddress" class="form-label">Address</label>
                                        <input type="text" class="form-control" id="careerApplyAddress" name="address"
                                            maxlength="255">
                                        <div class="invalid-feedback"></div>
                                    </div>

                                    <div class="col-12">
                                        <label for="careerApplyMessage" class="form-label">Message / Cover
                                            Letter</label>
                                        <textarea class="form-control" id="careerApplyMessage" name="message" rows="3"
                                            maxlength="5000"
                                            placeholder="Tell us why you'd be a great fit..."></textarea>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>

                                <div class="mb-1">
                                    <label class="form-label d-block">Resume / CV <span class="text-muted small">(PDF,
                                            DOC,
                                            or DOCX &middot; up to 5MB &middot; optional)</span></label>
                                    <div class="sh-career-resume-drop" id="careerResumeDrop" role="button" tabindex="0"
                                        aria-label="Upload resume — drag and drop a file here, or press Enter to browse">
                                        <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>
                                        <div class="small">Drag &amp; drop your resume here, or click to browse</div>
                                        <div class="sh-career-resume-selected d-none" id="careerResumeSelected"></div>
                                    </div>
                                    <input type="file" id="careerResumeInput" name="resume" accept=".pdf,.doc,.docx"
                                        hidden>
                                    <div class="invalid-feedback d-block" id="careerResumeError"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="careerApplySubmitBtn">
                        <span>Submit Application</span>
                        <span class="spinner-border spinner-border-sm ms-1 d-none" id="careerApplySpinner"
                            aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="application/json" id="careerPostingsData">
<?= json_encode(
    $careerPostingsForJs,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>
</script>