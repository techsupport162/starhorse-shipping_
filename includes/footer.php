<?php
// includes/footer.php - Site footer: decorative wave + vessel stage, then the
// footer columns. Styles: css/site.css (section 13); vessel entrance: js/scripts.js.
?>
<!-- Decorative wave + vessel on the boundary between #contact and the footer -->
<div class="sh-footer-wave-stage" id="footerWaveStage" aria-hidden="true">

    <svg class="sh-footer-wave" viewBox="0 0 1440 160" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg"
        focusable="false">
        <path d="M0,100 C220,55 380,120 600,90 C820,60 960,115 1180,85 C1300,68 1380,80 1440,90 L1440,160 L0,160 Z" />
    </svg>

    <div class="sh-footer-vessel-wrap" id="footerVesselWrap">
        <div class="sh-footer-vessel-float">
            <svg class="sh-footer-vessel-wake" viewBox="0 0 120 60" xmlns="http://www.w3.org/2000/svg"
                focusable="false">
                <path d="M4,40 C24,34 44,44 64,38" stroke="#eaf4f7" stroke-width="4" fill="none"
                    stroke-linecap="round" />
                <path d="M-2,50 C20,44 40,52 58,47" stroke="#eaf4f7" stroke-width="3" fill="none" stroke-linecap="round"
                    opacity="0.6" />
            </svg>
            <img class="sh-footer-vessel" src="assets/img/vessel.png" width="612" height="434" loading="lazy"
                decoding="async" alt="" />
        </div>
    </div>
</div>

<footer class="sh-footer" id="site-footer" aria-label="Site footer">
    <div class="sh-footer-main">
        <div class="container px-4 px-lg-5">
            <div class="row gy-5">

                <!-- COMPANY -->
                <div class="col-lg-4 col-md-6 sh-footer-col">
                    <a class="sh-footer-logo-badge" href="#page-top"
                        aria-label="Starhorse Shipping Line, Inc. - back to top">
                        <img src="assets/img/starhorse-logo-wname1.svg" alt="Starhorse Shipping Line, Inc. logo" />
                    </a>
                    <p class="sh-footer-desc">
                        Starhorse Shipping Line, Inc. (SSLI) is a MARINA-accredited domestic
                        shipping operator connecting Southern Tagalog, Marinduque, Romblon,
                        Bicol, and Masbate &mdash; moving passengers, freight, and rolling
                        cargo safely, reliably, and on time.
                    </p>
                    <div class="sh-footer-social">
                        <span class="sh-footer-social-label w-100">Follow Us</span>
                        <!-- TODO: replace '#' with the company's official Facebook page URL once available -->
                        <a class="sh-footer-social-icon" href="#" target="_blank" rel="noopener noreferrer"
                            aria-label="Starhorse Shipping Line, Inc. on Facebook (opens in a new tab)">
                            <i class="bi bi-facebook" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>

                <!-- QUICK LINKS -->
                <div class="col-lg-2 col-md-6 sh-footer-col">
                    <h3 class="sh-footer-heading">Quick Links</h3>
                    <ul class="sh-footer-links">
                        <li><a href="#page-top">Home</a></li>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="#vessels">Vessels</a></li>
                        <li><a href="#routes">Routes</a></li>
                        <li><a href="#rates">Rates</a></li>
                        <li><a href="#faqs">FAQs</a></li>
                        <li><a href="#contact">Contact Us</a></li>
                    </ul>
                </div>

                <!-- EXPLORE OUR SERVICES -->
                <div class="col-lg-3 col-md-6 sh-footer-col">
                    <h3 class="sh-footer-heading">Explore Our Services</h3>
                    <ul class="sh-footer-links">
                        <li><a href="#vessels">Our Fleet</a></li>
                        <li><a href="#routes">Routes &amp; Destinations</a></li>
                        <li><a href="#rates">Fares &amp; Rates</a></li>
                    </ul>
                </div>

                <!-- CONTACT -->
                <div class="col-lg-3 col-md-6 sh-footer-col">
                    <a class="sh-footer-book-btn" href="https://ferryeasy.ph/" target="_blank"
                        rel="noopener noreferrer">
                        <i class="bi bi-calendar-check-fill me-2" aria-hidden="true"></i>Book Your Voyage
                    </a>
                    <h3 class="sh-footer-heading">Contact Us</h3>
                    <ul class="sh-footer-contact-list">
                        <li>
                            <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                            <span>Manila North Harbor, Port Area, Manila</span>
                        </li>
                        <li>
                            <i class="bi bi-telephone-fill" aria-hidden="true"></i>
                            <a href="tel:+63281234567">+63 (2) 8123 4567</a>
                        </li>
                        <li>
                            <i class="bi bi-envelope-fill" aria-hidden="true"></i>
                            <a href="mailto:shipping@starhorse.com">shipping@starhorse.com</a>
                        </li>
                    </ul>
                </div>

            </div>
        </div>
    </div>

    <div class="sh-footer-bottom">
        <div class="container px-4 px-lg-5">
            <div class="sh-footer-bottom-inner">
                <p class="sh-footer-copy">
                    Copyright &copy; <?php echo date('Y'); ?> Starhorse Shipping Line, Inc. All rights reserved.
                </p>
            </div>
        </div>
    </div>
</footer>