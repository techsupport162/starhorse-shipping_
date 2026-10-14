<?php
// includes/header.php - Navigation bar shared across all pages
?>
<nav class="navbar navbar-expand-xl fixed-top" id="mainNav" aria-label="Main navigation">
    <div class="container px-4 px-lg-5">

        <!-- Brand shown next to the hamburger toggle (collapsed menu only) -->
        <a class="navbar-brand d-xl-none" href="#page-top">
            <img src="assets/img/starhorse-logo-wname1.5.svg" alt="Starhorse Shipping Lines, Inc." class="nav-logo" />
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarResponsive"
            aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarResponsive">
            <div class="navbar-nav-wrapper">
                <ul class="navbar-nav nav-left">
                    <li class="nav-item"><a class="nav-link" href="#page-top">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#vessels">Vessels</a></li>
                    <li class="nav-item"><a class="nav-link" href="#routes">Routes</a></li>
                    <li class="nav-item"><a class="nav-link" href="#rates">Rates</a></li>
                </ul>

                <!-- Centred brand (expanded desktop bar only) -->
                <a class="navbar-brand navbar-brand-center d-none d-xl-flex" href="#page-top">
                    <img src="assets/img/starhorse-logo-wname1.svg" alt="Starhorse Shipping Lines, Inc." class="nav-logo" />
                </a>

                <ul class="navbar-nav nav-right">
                    <li class="nav-item"><a class="nav-link" href="#faqs">FAQs</a></li>
                    <li class="nav-item"><a class="nav-link" href="#promos">Promos</a></li>
                    <li class="nav-item"><a class="nav-link" href="#career">Career</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                    <li class="nav-item">
                        <a class="nav-link book-now-link" href="https://ferryeasy.ph/" target="_blank"
                            rel="noopener noreferrer">
                            <span class="book-now-btn">
                                <i class="bi bi-calendar-check-fill me-2" aria-hidden="true"></i>Book Now
                                <span class="visually-hidden">(opens in a new tab)</span>
                            </span>
                        </a>
                    </li>
                </ul>

            </div>
        </div>
    </div>
</nav>
