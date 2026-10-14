<?php
// index.php - Main entry point
// This file assembles all sections into a single page

require_once __DIR__ . '/includes/security.php';

// Must run before any output (both send headers).
sh_send_page_csp();

// The Career section's Apply form needs CSRF protection (csrf_token()/
// csrf_verify() in starhorse-vessel-admin/functions/helpers.php), which
// relies on $_SESSION.
sh_start_session();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description"
        content="Starhorse Shipping Line, Inc. - Setting the Standards of Excellence and Good Service." />
    <meta name="author" content="Starhorse Shipping Line, Inc." />
    <title>Starhorse Shipping Line, Inc.</title>

    <!-- Lets CSS scope JS-only enhancements (e.g. scroll-reveal start states).
         Allowed by hash in the CSP (includes/security.php) — keep the text identical. -->
    <script>document.documentElement.classList.add('js');</script>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="assets/img/starhorse-logo-wname.svg" />

    <!-- Google fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Merriweather+Sans:wght@400;700&family=Merriweather:ital,wght@0,300;0,400;0,700;1,300;1,400;1,700&display=swap" />

    <!-- Icon fonts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
        integrity="sha384-CK2SzKma4jA5H/MXDUU7i1TqZlCFaD4T01vtyDFvPlD97JQyS+IsSh1nI2EFbpyk" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        integrity="sha384-t1nt8BQoYMLFN5p42tRAtuAAFQaCQODekUVeKKZrEnEyp4H2R0RHFz0KWpmj7i8g" crossorigin="anonymous" />

    <!-- Bootstrap 5 / Creative theme bundle, then site styles -->
    <link rel="stylesheet" href="css/styles.css" />
    <link rel="stylesheet" href="css/site.css" />
</head>

<body id="page-top">
    <a class="skip-link visually-hidden-focusable" href="#main-content">Skip to main content</a>

    <?php include 'includes/header.php'; ?>

    <main id="main-content">
        <?php include 'sections/masthead.php'; ?>
        <?php include 'sections/about.php'; ?>
        <?php include 'sections/vessels.php'; ?>
        <?php include 'sections/routes.php'; ?>
        <?php include 'sections/rates.php'; ?>
        <?php include 'sections/faqs.php'; ?>
        <?php include 'sections/promos.php'; ?>
        <?php include 'sections/career.php'; ?>
        <?php include 'sections/contact.php'; ?>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4"
        crossorigin="anonymous"></script>
    <script src="js/scripts.js"></script>
</body>

</html>
