<?php
// sections/about.php - About section

// Years of service, counted from the founding date (Sept 25, 2008)
$ph_tz = new DateTimeZone('Asia/Manila');
$years_of_service = (new DateTime('2008-09-25', $ph_tz))->diff(new DateTime('now', $ph_tz))->y;
?>
<section class="page-section about-section" id="about">

    <!-- Wavy top divider (white -> section) -->
    <div class="about-wave about-wave-top" aria-hidden="true">
        <svg viewBox="0 0 1440 90" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
            <path d="M0,0 L1440,0 L1440,40 C1200,85 900,10 720,40 C540,70 240,10 0,45 Z" />
        </svg>
    </div>

    <div class="ribbon-tl" aria-hidden="true"></div>
    <div class="ribbon-br" aria-hidden="true"></div>

    <div class="about-inner">
        <div class="container px-4 px-lg-5">

            <!-- ============ INTRO ============ -->
            <div class="row justify-content-center">
                <div class="col-lg-9 text-center about-intro">
                    <span class="about-eyebrow">Who We Are</span>
                    <h2 class="mt-3">Setting the Standards of Excellence and Good Service</h2>
                    <hr class="divider" />
                    <p>
                        Starhorse Shipping Lines, Inc. (SSLI) is a MARINA-accredited,
                        fast-growing domestic shipping operator serving Southern Tagalog,
                        Marinduque, Romblon, Bicol, and Masbate. Backed by shore-based
                        professionals and competent shipboard personnel, SSLI combines
                        local expertise with the International Safety Management (ISM)
                        System to move passengers, freight, and rolling cargo safely,
                        reliably, and on time.
                    </p>
                </div>
            </div>

            <!-- ============ STATS ============ -->
            <div class="about-stats">
                <div class="row g-0">
                    <div class="col-6 col-md-3 about-stat">
                        <i class="fas fa-clock about-stat-icon" aria-hidden="true"></i>
                        <span class="num"><?= $years_of_service ?>+</span>
                        <span class="lbl">Years of Service</span>
                    </div>
                    <div class="col-6 col-md-3 about-stat">
                        <i class="fas fa-ship about-stat-icon" aria-hidden="true"></i>
                        <span class="num">16</span>
                        <span class="lbl">Vessels in Fleet</span>
                    </div>
                    <div class="col-6 col-md-3 about-stat">
                        <i class="fas fa-route about-stat-icon" aria-hidden="true"></i>
                        <span class="num">20K</span>
                        <span class="lbl">Trips per Year</span>
                    </div>
                    <div class="col-6 col-md-3 about-stat">
                        <i class="fas fa-smile about-stat-icon" aria-hidden="true"></i>
                        <span class="num">100K+</span>
                        <span class="lbl">Satisfied Clients</span>
                    </div>
                </div>
            </div>

            <!-- ============ MISSION / VISION / PROFILE ============ -->
            <div class="about-block">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="about-mvp-card">
                            <div class="icon">
                                <i class="fas fa-bullseye" aria-hidden="true"></i>
                            </div>
                            <h3 class="h4">Our Mission</h3>
                            <p>
                                To provide modern, reliable marine transportation in a safe
                                and friendly atmosphere that exceeds customer satisfaction
                                while meeting profitability targets — creating a working
                                environment that generates opportunities for growth and
                                preserves the inherent dignity of every crew member.
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="about-mvp-card">
                            <div class="icon">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </div>
                            <h3 class="h4">Our Vision</h3>
                            <p>
                                To be one of the finest domestic shipping service providers
                                across the country.
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="about-mvp-card">
                            <div class="icon">
                                <i class="fas fa-map-marked-alt" aria-hidden="true"></i>
                            </div>
                            <h3 class="h4">Our Coverage</h3>
                            <p>
                                Daily carriage of passengers, freight, and rolling cargo
                                across Southern Tagalog, Marinduque, Romblon, Bicol, and
                                Masbate, with active route-expansion projects underway to
                                extend our service and port coverage nationwide.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============ VIDEO FEATURE ============ -->
            <div class="about-block">
                <div class="row align-items-center g-5">
                    <div class="col-lg-6">
                        <span class="about-eyebrow">See Us In Motion</span>
                        <h3 class="mt-3">A Fleet Built on Trust</h3>
                        <p class="text-muted">
                            From the docks of Lucena City to the shores of Marinduque,
                            Romblon, and Masbate, our RORO vessels sail daily — carrying
                            families, traders, and cargo across the islands they call
                            home. Watch how a Starhorse voyage comes together, from
                            boarding to breakwater.
                        </p>
                    </div>
                    <div class="col-lg-6">
                        <div class="about-video-wrap">
                            <video controls preload="none" playsinline poster="assets/img/about/video-poster-fleet.jpg">
                                <source src="assets/video/starhorse-voyage-tour.mp4" type="video/mp4" />
                                Your browser does not support the video tag.
                            </video>
                        </div>
                        <p class="about-video-caption">
                            Suggested clip: a short (60–90 sec) walkthrough of a vessel
                            boarding/departure at Lucena City port — deck loading, RORO
                            ramp, passengers boarding, and the ship pulling out to sea.
                        </p>
                    </div>
                </div>
            </div>

            <!-- ============ ROUTES ============ -->
            <div class="about-block" id="about-routes">
                <div class="about-block-head">
                    <span class="about-eyebrow">Where We Sail</span>
                    <h3 class="mt-3">Our MARINA-Authorized Routes</h3>
                    <p>Connecting Quezon Province to Marinduque, Romblon, Masbate, Bicol, and Mindoro.</p>
                </div>
                <div class="row g-4 justify-content-center">
                    <div class="col-md-6 col-lg-3">
                        <div class="about-routes-card">
                            <h4><i class="fas fa-map-pin" aria-hidden="true"></i>Lucena City</h4>
                            <ul>
                                <li>Lucena City &ndash; Masbate City and vice versa</li>                                
                                <li>Lucena City &ndash; Balanacan, Mogpog and vice versa</li>
                                <li>Lucena City &ndash; Banton / Simara and vice versa</li>
                                <li>Lucena City &ndash; San Agustin and vice versa</li>
                                <li>Lucena City &ndash; Romblon and vice versa</li>
                                <li>Lucena City &ndash; Sibuyan and vice versa</li>                                
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="about-routes-card">
                            <h4><i class="fas fa-map-pin" aria-hidden="true"></i>Burias Islands</h4>
                            <ul>
                                <li>San Pascual &ndash; San Andres and vice versa</li>
                                <li>San Pascual &ndash; Pasacao and vice versa</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="about-routes-card">
                            <h4><i class="fas fa-map-pin" aria-hidden="true"></i> Quezon Province</h4>
                            <ul>
                                <li>Real, Quezon &ndash; Polillo, Quezon and vice versa</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="about-routes-card">
                            <h4><i class="fas fa-map-pin" aria-hidden="true"></i> Batangas City</h4>
                            <ul>
                                <li>Batangas City &ndash; Calapan City and vice versa</li>
                                <li>Batangas City &ndash; Abra de Ilog and vice versa</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>


            <!-- ============ CTA ============ -->
            <div class="about-cta">
                <h3>Ready to Book Your Voyage?</h3>
                <p>Reach out to our team for schedules, fares, and cargo booking across all Starhorse routes.</p>
                <a class="btn btn-light btn-xl" href="#contact">Get In Touch!</a>
            </div>
        </div>
    </div>
    <!-- Wavy bottom divider (section -> whatever follows) -->
    <div class="about-wave about-wave-bottom" aria-hidden="true">
        <svg viewBox="0 0 1440 90" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
            <path d="M0,45 C240,10 540,70 720,40 C900,10 1200,85 1440,40 L1440,90 L0,90 Z" />
        </svg>
    </div>
</section>