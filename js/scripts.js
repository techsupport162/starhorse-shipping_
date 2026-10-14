/*!
 * Starhorse Shipping Line, Inc. — site scripts
 * Originally based on Start Bootstrap - Creative v7.0.7 (MIT)
 * https://github.com/StartBootstrap/startbootstrap-creative/blob/master/LICENSE
 *
 * Loaded at the end of <body>, after Bootstrap's JS bundle.
 *
 * Contents
 *   1. Helpers
 *   2. Navigation (shrink on scroll, scrollspy, close mobile menu on click)
 *   3. Scroll reveal ([data-animate-in])
 *   4. Vessels carousel (#vesselCarousel)
 *   5. Routes port logo marquee (#portMarquee)
 *   6. Fare finder (#rates)
 *   7. Career postings, Details + Apply modals (#career)
 *   8. Footer vessel entrance
 *   9. Boot
 */
(() => {
    "use strict";

    /* =========================================================================
       1. HELPERS
       ========================================================================= */
    const reducedMotionQuery = window.matchMedia("(prefers-reduced-motion: reduce)");
    const prefersReducedMotion = () => reducedMotionQuery.matches;

    const HTML_ESCAPES = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" };

    /** Escapes text for safe use in HTML content and quoted attribute values. */
    const escapeHtml = (value) => String(value ?? "").replace(/[&<>"']/g, (c) => HTML_ESCAPES[c]);

    /** Parses the JSON payload of a <script type="application/json"> element. */
    function readJsonData(id, fallback) {
        const el = document.getElementById(id);
        if (!el) return null;
        try {
            return JSON.parse(el.textContent || "") ?? fallback;
        } catch (err) {
            console.error(`[scripts] Invalid JSON in #${id}`, err);
            return fallback;
        }
    }

    /* =========================================================================
       2. NAVIGATION
       ========================================================================= */
    function initNavigation() {
        const mainNav = document.getElementById("mainNav");
        if (!mainNav) return;

        // Solid background once the page is scrolled (transparent over the hero)
        const updateShrink = () => mainNav.classList.toggle("navbar-shrink", window.scrollY > 0);
        updateShrink();
        document.addEventListener("scroll", updateShrink, { passive: true });

        new bootstrap.ScrollSpy(document.body, {
            target: "#mainNav",
            rootMargin: "0px 0px -40%",
        });

        // Close the collapsed (hamburger) menu once a link is chosen
        const menu = document.getElementById("navbarResponsive");
        if (!menu) return;
        menu.querySelectorAll(".nav-link").forEach((link) => {
            link.addEventListener("click", () => {
                if (menu.classList.contains("show")) {
                    bootstrap.Collapse.getOrCreateInstance(menu, { toggle: false }).hide();
                }
            });
        });
    }

    /* =========================================================================
       3. SCROLL REVEAL
       Elements with [data-animate-in] start hidden (CSS, only when html.js is
       present) and animate in when they scroll into view. Once the entrance
       finishes the attribute is removed, so the element's own hover
       transforms are no longer overridden by the animation's fill state.
       ========================================================================= */
    function initScrollReveal() {
        const items = document.querySelectorAll("[data-animate-in]");
        if (!items.length) return;

        const reveal = (el, delaySeconds = 0) => {
            if (delaySeconds) el.style.animationDelay = `${delaySeconds}s`;
            el.addEventListener("animationend", function onEnd(event) {
                if (event.target !== el) return;
                el.removeEventListener("animationend", onEnd);
                el.removeAttribute("data-animate-in");
                el.classList.remove("is-visible");
                el.style.animationDelay = "";
            });
            el.classList.add("is-visible");
        };

        if (prefersReducedMotion() || !("IntersectionObserver" in window)) {
            items.forEach((el) => el.classList.add("is-visible"));
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            let staggerIndex = 0;
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                reveal(entry.target, Math.min(staggerIndex++, 5) * 0.06);
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.12 });

        items.forEach((el) => observer.observe(el));
    }

    /* =========================================================================
       4. VESSELS CAROUSEL
       Infinite round-robin loop via edge cloning: a clone of the last slide
       is prepended and a clone of the first appended. After a clone slides
       into view the track snaps (without transition) to the matching real
       slide. Each slide also has its own independent photo gallery.
       ========================================================================= */
    function initVesselCarousel() {
        const root = document.getElementById("vesselCarousel");
        if (!root) return; // no published vessels

        const track = document.getElementById("vesselTrack");
        const viewport = track.parentElement;
        const originalSlides = Array.from(track.children);
        const vesselCount = originalSlides.length;
        if (vesselCount === 0) return;

        const prevBtn = document.getElementById("vesselPrevBtn");
        const nextBtn = document.getElementById("vesselNextBtn");
        const dotsWrap = document.getElementById("vesselDots");
        const liveRegion = document.getElementById("vesselLiveRegion");

        const DRAG_LOCK_PX = 10; // movement before committing to horizontal drag vs page scroll
        const DRAG_COMMIT_RATIO = 0.18; // fraction of viewport width a drag must cross to change vessel
        const GALLERY_SWIPE_PX = 40; // minimum horizontal travel for a photo swipe

        const looped = vesselCount > 1;
        let position = 0; // index within track.children (after cloning, if looped)
        let isAnimating = false;
        let dragState = null;

        const slides = () => Array.from(track.children);
        const currentSlide = () => track.children[position];

        function realIndexFromPosition(pos) {
            if (!looped) return pos;
            if (pos === 0) return vesselCount - 1; // prepended clone of the last slide
            if (pos === vesselCount + 1) return 0; // appended clone of the first slide
            return pos - 1;
        }

        function setTrackPosition() {
            track.style.transform = `translateX(-${position * 100}%)`;
        }

        function applyPositionInstant() {
            track.style.transition = "none";
            setTrackPosition();
            void track.offsetWidth; // force reflow so the NEXT change animates
            track.style.transition = "";
        }

        function goTo(newPosition) {
            if (isAnimating || newPosition === position) return;
            isAnimating = true;
            position = newPosition;
            setTrackPosition();
            updateActiveStates();
            updateViewportHeight();
            // With reduced motion the transition is disabled, so transitionend
            // never fires — settle synchronously instead.
            if (prefersReducedMotion()) settleAfterTransition();
        }

        const next = () => goTo(position + 1);
        const prev = () => goTo(position - 1);

        function goToRealIndex(index) {
            if (isAnimating || index === realIndexFromPosition(position)) return;
            goTo(looped ? index + 1 : index);
        }

        function settleAfterTransition() {
            isAnimating = false;
            if (!looped) return;
            if (position === vesselCount + 1) {
                position = 1;
                applyPositionInstant();
            } else if (position === 0) {
                position = vesselCount;
                applyPositionInstant();
            }
        }

        function snapBackToCurrentPosition() {
            // A drag that didn't cross the commit threshold springs back
            isAnimating = !prefersReducedMotion();
            setTrackPosition();
        }

        function updateActiveStates() {
            const activeRealIndex = realIndexFromPosition(position);

            slides().forEach((slide, i) => {
                const isActive = i === position;
                slide.setAttribute("aria-hidden", isActive ? "false" : "true");
                slide.classList.toggle("is-active", isActive);
                // Keep off-screen slides (and clones) out of the tab order
                if ("inert" in slide) slide.inert = !isActive;
            });

            if (dotsWrap) {
                Array.from(dotsWrap.children).forEach((dot, i) => {
                    const isActive = i === activeRealIndex;
                    dot.classList.toggle("is-active", isActive);
                    dot.setAttribute("aria-selected", isActive ? "true" : "false");
                });
            }

            const activeSlide = currentSlide();
            resetGalleryForSlide(activeSlide);

            if (liveRegion && activeSlide) {
                const nameEl = activeSlide.querySelector(".sh-vc-info__name");
                liveRegion.textContent = nameEl ? `Showing ${nameEl.textContent}` : "";
            }
        }

        function updateViewportHeight() {
            // Slides differ in height; match the viewport to the active one
            requestAnimationFrame(() => {
                const active = currentSlide();
                if (active) viewport.style.height = `${active.offsetHeight}px`;
            });
        }

        /* ---- Per-vessel photo gallery (independent of the vessel carousel) ---- */
        function setMainImage(imgEl, src) {
            if (!src || imgEl.getAttribute("src") === src) return;
            imgEl.classList.add("is-fading");
            window.setTimeout(() => {
                imgEl.src = src;
                imgEl.classList.remove("is-fading");
            }, 180);
        }

        function resetGalleryForSlide(slideEl) {
            if (!slideEl) return;
            const mainImg = slideEl.querySelector("[data-main-image]");
            const thumbs = slideEl.querySelectorAll(".sh-vc-thumb");
            if (mainImg && thumbs.length) {
                setMainImage(mainImg, thumbs[0].getAttribute("data-img-src"));
                thumbs.forEach((t, i) => {
                    t.classList.toggle("is-active", i === 0);
                    t.setAttribute("aria-selected", i === 0 ? "true" : "false");
                });
                const thumbsTrack = slideEl.querySelector("[data-thumbs-track]");
                if (thumbsTrack) thumbsTrack.scrollTo({ left: 0, top: 0, behavior: "auto" });
            }
            updateThumbOverflow(slideEl);
        }

        // Single path for "this thumbnail is now selected" — used by thumbnail
        // clicks, main-image swipes, and gallery keyboard navigation alike.
        function selectGalleryImage(slideEl, thumb, { scrollIntoView = false } = {}) {
            if (!slideEl || !thumb) return;
            const mainImg = slideEl.querySelector("[data-main-image]");
            slideEl.querySelectorAll(".sh-vc-thumb").forEach((t) => {
                t.classList.remove("is-active");
                t.setAttribute("aria-selected", "false");
            });
            thumb.classList.add("is-active");
            thumb.setAttribute("aria-selected", "true");
            if (mainImg) setMainImage(mainImg, thumb.getAttribute("data-img-src"));
            if (scrollIntoView) {
                thumb.scrollIntoView({
                    block: "nearest",
                    inline: "nearest",
                    behavior: prefersReducedMotion() ? "auto" : "smooth",
                });
            }
        }

        // Moves the slide's gallery by `delta` photos, wrapping around
        function stepGalleryImage(slideEl, delta) {
            if (!slideEl) return;
            const thumbs = Array.from(slideEl.querySelectorAll(".sh-vc-thumb"));
            if (thumbs.length < 2) return;
            const activeIndex = Math.max(0, thumbs.findIndex((t) => t.classList.contains("is-active")));
            const nextIndex = (activeIndex + delta + thumbs.length) % thumbs.length;
            selectGalleryImage(slideEl, thumbs[nextIndex], { scrollIntoView: true });
        }

        // Fade at the strip's trailing edge only when there is more to scroll
        function updateThumbOverflow(slideEl) {
            if (!slideEl) return;
            const wrap = slideEl.querySelector("[data-thumbs-wrap]");
            const thumbsTrack = slideEl.querySelector("[data-thumbs-track]");
            if (!wrap || !thumbsTrack) return;

            const isHorizontal = window.getComputedStyle(thumbsTrack).flexDirection === "row";
            const overflowing = isHorizontal
                ? thumbsTrack.scrollWidth - thumbsTrack.clientWidth > 4
                : thumbsTrack.scrollHeight - thumbsTrack.clientHeight > 4;
            wrap.setAttribute("data-overflowing", overflowing ? "true" : "false");
        }

        /* ---- Pointer swipe for the VESSEL carousel ---- */
        function onDragStart(e) {
            if (isAnimating) return;
            if (e.pointerType === "mouse" && e.button !== 0) return;
            // Thumbnails, dots, arrows and the main image handle their own input
            if (e.target.closest(".sh-vc-thumb, .sh-vc-dot, .sh-vc-arrow, .sh-vc-gallery__main")) return;

            dragState = {
                startX: e.clientX,
                startY: e.clientY,
                lastX: e.clientX,
                dragging: false,
                axis: null,
                width: viewport.getBoundingClientRect().width || 1,
                pointerId: e.pointerId,
            };
        }

        function onDragMove(e) {
            if (!dragState || e.pointerId !== dragState.pointerId) return;
            const dx = e.clientX - dragState.startX;
            const dy = e.clientY - dragState.startY;

            if (!dragState.axis) {
                if (Math.abs(dx) < DRAG_LOCK_PX && Math.abs(dy) < DRAG_LOCK_PX) return;
                dragState.axis = Math.abs(dx) > Math.abs(dy) ? "x" : "y";
                if (dragState.axis === "x") {
                    try {
                        track.setPointerCapture(e.pointerId);
                    } catch (err) {
                        /* not critical */
                    }
                }
            }
            if (dragState.axis !== "x") return; // vertical intent — let the page scroll

            dragState.dragging = true;
            dragState.lastX = e.clientX;
            if (e.cancelable) e.preventDefault();

            track.style.transition = "none";
            track.style.transform = `translateX(calc(${-(position * 100)}% + ${dx}px))`;
        }

        function onDragEnd(e) {
            if (!dragState || (e.pointerId !== undefined && e.pointerId !== dragState.pointerId)) return;
            const { dragging, lastX, startX, width } = dragState;
            dragState = null;

            track.style.transition = "";
            if (!dragging) return;

            root.setAttribute("data-swiped", "true");
            const ratio = (lastX - startX) / width;

            if (Math.abs(ratio) > DRAG_COMMIT_RATIO) {
                isAnimating = false;
                if (ratio < 0) next();
                else prev();
            } else {
                snapBackToCurrentPosition();
            }
        }

        function bindVesselSwipe() {
            viewport.addEventListener("pointerdown", onDragStart);
            viewport.addEventListener("pointermove", onDragMove);
            viewport.addEventListener("pointerup", onDragEnd);
            viewport.addEventListener("pointercancel", onDragEnd);
            viewport.addEventListener("pointerleave", (e) => {
                if (dragState && e.pointerType === "mouse") onDragEnd(e);
            });
        }

        /* ---- Pointer swipe + keyboard for the per-vessel GALLERY ---- */
        function bindGalleryInput() {
            let galleryDrag = null;

            track.addEventListener("pointerdown", (e) => {
                const main = e.target.closest(".sh-vc-gallery__main");
                if (!main || !main.hasAttribute("tabindex")) return; // 0–1 photos: nothing to swipe
                if (e.pointerType === "mouse" && e.button !== 0) return;
                galleryDrag = { startX: e.clientX, startY: e.clientY, axis: null, main, pointerId: e.pointerId };
            });

            track.addEventListener("pointermove", (e) => {
                if (!galleryDrag || e.pointerId !== galleryDrag.pointerId) return;
                const dx = e.clientX - galleryDrag.startX;
                const dy = e.clientY - galleryDrag.startY;
                if (!galleryDrag.axis) {
                    if (Math.abs(dx) < DRAG_LOCK_PX && Math.abs(dy) < DRAG_LOCK_PX) return;
                    galleryDrag.axis = Math.abs(dx) > Math.abs(dy) ? "x" : "y";
                }
                if (galleryDrag.axis === "x" && e.cancelable) e.preventDefault();
            });

            const endGalleryDrag = (e) => {
                if (!galleryDrag || (e.pointerId !== undefined && e.pointerId !== galleryDrag.pointerId)) return;
                const dx = e.clientX - galleryDrag.startX;
                const { main, axis } = galleryDrag;
                galleryDrag = null;
                if (axis !== "x" || Math.abs(dx) < GALLERY_SWIPE_PX) return;
                stepGalleryImage(main.closest(".sh-vc-slide"), dx < 0 ? 1 : -1);
            };
            track.addEventListener("pointerup", endGalleryDrag);
            track.addEventListener("pointercancel", endGalleryDrag);

            // Left/Right browse photos while the main image has focus
            track.addEventListener("keydown", (e) => {
                const main = e.target.closest(".sh-vc-gallery__main");
                if (!main || !main.hasAttribute("tabindex")) return;
                if (e.key !== "ArrowLeft" && e.key !== "ArrowRight") return;
                e.preventDefault();
                stepGalleryImage(main.closest(".sh-vc-slide"), e.key === "ArrowRight" ? 1 : -1);
            });
        }

        function bindEvents() {
            prevBtn?.addEventListener("click", prev);
            nextBtn?.addEventListener("click", next);

            dotsWrap?.addEventListener("click", (e) => {
                const dot = e.target.closest(".sh-vc-dot");
                if (dot) goToRealIndex(parseInt(dot.getAttribute("data-goto"), 10));
            });

            track.addEventListener("transitionend", (e) => {
                if (e.target === track && e.propertyName === "transform") settleAfterTransition();
            });

            // Thumbnail clicks (delegated across all slides, including clones)
            track.addEventListener("click", (e) => {
                if (dragState?.dragging) return; // a drag release is not a click
                const thumb = e.target.closest(".sh-vc-thumb");
                if (thumb) selectGalleryImage(thumb.closest(".sh-vc-slide"), thumb);
            });

            // The thumbnail strip's own scrolling keeps the overflow fade honest
            track.addEventListener("scroll", (e) => {
                const thumbsTrack = e.target.closest?.("[data-thumbs-track]");
                if (thumbsTrack) updateThumbOverflow(thumbsTrack.closest(".sh-vc-slide"));
            }, true);

            // Left/Right change vessel when the carousel region has focus
            // (unless focus is on the main image — that browses photos instead)
            root.addEventListener("keydown", (e) => {
                if (e.key !== "ArrowLeft" && e.key !== "ArrowRight") return;
                if (e.target.closest(".sh-vc-gallery__main")) return;
                e.preventDefault();
                if (e.key === "ArrowLeft") prev();
                else next();
            });

            // Lazy images and web fonts change slide heights after init
            track.addEventListener("load", updateViewportHeight, true);
            document.fonts?.ready.then(updateViewportHeight);

            let resizeRaf = null;
            window.addEventListener("resize", () => {
                if (resizeRaf) return;
                resizeRaf = requestAnimationFrame(() => {
                    resizeRaf = null;
                    updateViewportHeight();
                    updateThumbOverflow(currentSlide());
                });
            }, { passive: true });

            bindVesselSwipe();
            bindGalleryInput();
        }

        if (looped) {
            const firstClone = originalSlides[0].cloneNode(true);
            const lastClone = originalSlides[vesselCount - 1].cloneNode(true);
            firstClone.setAttribute("data-clone", "true");
            lastClone.setAttribute("data-clone", "true");
            track.appendChild(firstClone);
            track.insertBefore(lastClone, track.firstChild);
            position = 1; // real vessel 0 now sits at index 1
        }

        applyPositionInstant();
        bindEvents();
        updateActiveStates();
        updateViewportHeight();
    }

    /* =========================================================================
       5. ROUTES PORT LOGO MARQUEE
       The scrolling itself is pure CSS (see "Port logo marquee" in
       css/site.css). This only sets the duration from the rendered width so
       the strip moves at the same speed whether there are 3 ports or 30.
       Set via the CSSOM, which the page CSP allows (inline style="" is not).
       ========================================================================= */
    function initPortMarquee() {
        const marquee = document.getElementById("portMarquee");
        if (!marquee) return;

        const track = marquee.querySelector(".port-marquee__track");
        const group = marquee.querySelector(".port-marquee__group");
        if (!track || !group) return;

        const PX_PER_SECOND = 45;

        const updateDuration = () => {
            const groupWidth = group.getBoundingClientRect().width;
            if (groupWidth > 0) {
                track.style.setProperty("--port-marquee-duration", `${(groupWidth / PX_PER_SECOND).toFixed(1)}s`);
            }
        };

        updateDuration();
        // Logo widths change with the viewport (clamp() sizes) and as images load
        if ("ResizeObserver" in window) {
            new ResizeObserver(updateDuration).observe(group);
        } else {
            window.addEventListener("resize", updateDuration);
        }
    }

    /* =========================================================================
       6. FARE FINDER
       Two accessible comboboxes (From / To) that suggest ports, restricted to
       ports actually connected to the other field's value (honouring
       vice-versa routes), then show the matching fare charts.
       ========================================================================= */
    function initFareFinder() {
        const payload = readJsonData("fareData", {});
        if (!payload) return; // no published fares

        const fares = payload.fares || [];
        const resultEl = document.getElementById("fareResult");
        const swapBtn = document.getElementById("fareSwapBtn");
        const normalize = (value) => (value || "").trim().toLowerCase();

        /* ---- Origin <-> destination adjacency (vice-versa opens the reverse edge) ---- */
        const destinationsByOrigin = {}; // normalized origin -> [destination, ...]
        const originsByDestination = {}; // normalized destination -> [origin, ...]

        function addEdge(map, fromLabel, toLabel) {
            const key = normalize(fromLabel);
            if (!key) return;
            map[key] = map[key] || [];
            if (!map[key].includes(toLabel)) map[key].push(toLabel);
        }

        fares.forEach((fare) => {
            addEdge(destinationsByOrigin, fare.origin, fare.destination);
            addEdge(originsByDestination, fare.destination, fare.origin);
            if (fare.is_vice_versa) {
                addEdge(destinationsByOrigin, fare.destination, fare.origin);
                addEdge(originsByDestination, fare.origin, fare.destination);
            }
        });

        const hasKnownPort = (map, label) => {
            const key = normalize(label);
            return !!key && Object.prototype.hasOwnProperty.call(map, key);
        };

        /* ---- Accessible combobox: searchable dropdown + free typing ---- */
        class Combobox {
            constructor(role, options) {
                this.role = role;
                this.wrap = document.querySelector(`.sh-fare-combo[data-role="${role}"]`);
                this.input = this.wrap.querySelector(".sh-fare-combo__input");
                this.listbox = this.wrap.querySelector(".sh-fare-combo__listbox");
                this.clearBtn = this.wrap.querySelector(".sh-fare-combo__clear");
                this.allOptions = options;
                this.activeIndex = -1;
                this.currentOptions = [];
                this.restrictedEmpty = false;
                // Set after both comboboxes exist (each reads the other's value)
                this.getExcludeValue = () => "";
                this.getRelatedOptions = () => null;

                this.bind();
                this.syncHasValue();
            }

            get value() {
                return this.input.value;
            }

            set value(next) {
                this.input.value = next;
                this.syncHasValue();
            }

            get isOpen() {
                return this.listbox.classList.contains("is-open");
            }

            bind() {
                this.input.addEventListener("input", () => {
                    this.syncHasValue();
                    this.open(this.input.value);
                    onFieldChange();
                });
                this.input.addEventListener("focus", () => this.open(this.input.value));
                this.input.addEventListener("keydown", (e) => this.handleKeydown(e));
                this.input.addEventListener("blur", () => {
                    // Delay so a click on an option registers before closing
                    window.setTimeout(() => {
                        this.close();
                        onFieldChange();
                    }, 120);
                });

                this.clearBtn.addEventListener("mousedown", (e) => e.preventDefault());
                this.clearBtn.addEventListener("click", () => {
                    this.value = "";
                    this.input.focus();
                    this.open("");
                    onFieldChange();
                });
            }

            syncHasValue() {
                this.wrap.classList.toggle("has-value", this.input.value.trim().length > 0);
            }

            getFilteredOptions(query) {
                const exclude = normalize(this.getExcludeValue());
                const q = normalize(query);

                // Restrict to ports connected to the OTHER field's value; fall
                // back to the full list when it is empty or unrecognised, so
                // free typing never gets stuck with zero suggestions.
                const related = this.getRelatedOptions();
                const base = related ?? this.allOptions;

                // Duplicate-route prevention: never offer the other field's port
                const available = base.filter((opt) => !(exclude && normalize(opt) === exclude));

                // "This port has no connections" vs "your query matches nothing"
                this.restrictedEmpty = related !== null && available.length === 0;

                return q ? available.filter((opt) => normalize(opt).includes(q)) : available;
            }

            open(query) {
                this.currentOptions = this.getFilteredOptions(query);
                this.activeIndex = -1;
                this.renderOptions();
                this.listbox.hidden = false;
                this.listbox.classList.add("is-open");
                this.input.setAttribute("aria-expanded", "true");
            }

            close() {
                this.listbox.classList.remove("is-open");
                this.input.setAttribute("aria-expanded", "false");
                this.input.removeAttribute("aria-activedescendant");
                window.setTimeout(() => {
                    if (!this.isOpen) this.listbox.hidden = true;
                }, 150);
            }

            renderOptions() {
                this.listbox.innerHTML = "";

                if (!this.currentOptions.length) {
                    const empty = document.createElement("li");
                    empty.className = "sh-fare-combo__option sh-fare-combo__option--empty";
                    empty.setAttribute("role", "presentation");
                    const typed = this.input.value.trim();
                    if (this.restrictedEmpty) {
                        empty.textContent = "No routes available from this location.";
                    } else {
                        empty.textContent = typed
                            ? `No matching port — you can still use "${typed}"`
                            : "No suggestions available";
                    }
                    this.listbox.appendChild(empty);
                    return;
                }

                const selected = normalize(this.input.value);
                this.currentOptions.forEach((opt, i) => {
                    const li = document.createElement("li");
                    li.className = "sh-fare-combo__option";
                    li.id = `sh-fare-opt-${this.role}-${i}`;
                    li.setAttribute("role", "option");
                    li.setAttribute("aria-selected", String(normalize(opt) === selected));
                    li.innerHTML = '<i class="bi bi-geo-alt" aria-hidden="true"></i><span></span>';
                    li.querySelector("span").textContent = opt;

                    li.addEventListener("mousedown", (e) => e.preventDefault()); // keep focus
                    li.addEventListener("click", () => this.select(opt));

                    this.listbox.appendChild(li);
                });
            }

            setActive(index) {
                const items = this.listbox.querySelectorAll(".sh-fare-combo__option");
                items.forEach((el) => el.classList.remove("is-active"));
                if (index >= 0 && items[index]) {
                    items[index].classList.add("is-active");
                    items[index].scrollIntoView({ block: "nearest" });
                    this.input.setAttribute("aria-activedescendant", items[index].id);
                } else {
                    this.input.removeAttribute("aria-activedescendant");
                }
                this.activeIndex = index;
            }

            select(value) {
                this.value = value;
                this.close();
                onFieldChange();
            }

            handleKeydown(e) {
                switch (e.key) {
                    case "ArrowDown":
                        e.preventDefault();
                        if (!this.isOpen) this.open(this.input.value);
                        else this.setActive(Math.min(this.activeIndex + 1, this.currentOptions.length - 1));
                        break;
                    case "ArrowUp":
                        e.preventDefault();
                        if (this.isOpen) this.setActive(Math.max(this.activeIndex - 1, 0));
                        break;
                    case "Enter":
                        if (this.isOpen && this.activeIndex > -1 && this.currentOptions[this.activeIndex]) {
                            e.preventDefault();
                            this.select(this.currentOptions[this.activeIndex]);
                        } else {
                            this.close();
                        }
                        break;
                    case "Escape":
                        if (this.isOpen) {
                            e.preventDefault();
                            this.close();
                        }
                        break;
                    case "Tab":
                        this.close();
                        break;
                    default:
                        break;
                }
            }
        }

        const originCombo = new Combobox("origin", payload.origins || []);
        const destinationCombo = new Combobox("destination", payload.destinations || []);

        originCombo.getExcludeValue = () => destinationCombo.value;
        destinationCombo.getExcludeValue = () => originCombo.value;

        // null means "don't restrict" (other field empty or unrecognised)
        originCombo.getRelatedOptions = () => {
            const dest = destinationCombo.value;
            return hasKnownPort(originsByDestination, dest) ? originsByDestination[normalize(dest)] : null;
        };
        destinationCombo.getRelatedOptions = () => {
            const origin = originCombo.value;
            return hasKnownPort(destinationsByOrigin, origin) ? destinationsByOrigin[normalize(origin)] : null;
        };

        /* ---- Route lookup: exact match first, else a vice-versa reverse record ---- */
        function findExactRoute(origin, destination) {
            const o = normalize(origin);
            const d = normalize(destination);
            return fares.find((f) => normalize(f.origin) === o && normalize(f.destination) === d) || null;
        }

        function findRoute(origin, destination) {
            const direct = findExactRoute(origin, destination);
            if (direct) return direct;

            const reverse = findExactRoute(destination, origin);
            if (reverse && reverse.is_vice_versa) {
                return { origin, destination, passenger: reverse.passenger, cargo: reverse.cargo, is_vice_versa: true };
            }
            return null;
        }

        /* ---- Rendering ---- */
        function renderGroup(title, imageUrl, routeLabel) {
            const body = imageUrl
                ? `<div class="sh-fare-group__image-wrap"><img class="sh-fare-group__image" src="${escapeHtml(imageUrl)}" alt="${escapeHtml(`${title} — ${routeLabel}`)}" loading="lazy"></div>`
                : '<div class="sh-fare-group__empty"><i class="bi bi-image d-block mb-2 fs-3" aria-hidden="true"></i>Fare chart not yet available for this route</div>';

            return `<div class="sh-fare-group"><div class="sh-fare-group__title">${escapeHtml(title)}</div>${body}</div>`;
        }

        function renderMessage(icon, html) {
            resultEl.innerHTML = `<hr class="sh-fare-divider"><div class="sh-fare-noroute"><i class="bi ${icon}" aria-hidden="true"></i>${html}</div>`;
        }

        function attachImageFadeIn(container) {
            container.querySelectorAll(".sh-fare-group__image").forEach((img) => {
                if (img.complete && img.naturalWidth) img.classList.add("is-loaded");
                else img.addEventListener("load", () => img.classList.add("is-loaded"), { once: true });
            });
        }

        function renderNow() {
            const origin = originCombo.value.trim();
            const destination = destinationCombo.value.trim();

            if (!origin || !destination) {
                renderMessage("bi-signpost-2",
                    "Choose a <strong>From</strong> and <strong>To</strong> port to view the fare chart for that route.");
                return;
            }

            const route = findRoute(origin, destination);
            if (!route) {
                renderMessage("bi-compass",
                    `No published fare is available for <strong>${escapeHtml(origin)} → ${escapeHtml(destination)}</strong> yet. ` +
                    "Please try another route or contact us directly.");
                return;
            }

            const routeLabel = `${route.origin} to ${route.destination}`;
            resultEl.innerHTML =
                '<hr class="sh-fare-divider"><div class="sh-fare-groups">' +
                renderGroup("Passenger Fare", route.passenger, routeLabel) +
                renderGroup("Cargo / Vehicle Fare", route.cargo, routeLabel) +
                "</div>";
            attachImageFadeIn(resultEl);
        }

        // Smooth cross-fade instead of an abrupt content swap
        let renderTimer = null;
        function onFieldChange() {
            window.clearTimeout(renderTimer);
            resultEl.classList.add("is-fading");
            renderTimer = window.setTimeout(() => {
                renderNow();
                resultEl.classList.remove("is-fading");
            }, 160);
        }

        swapBtn.addEventListener("click", () => {
            [originCombo.value, destinationCombo.value] = [destinationCombo.value, originCombo.value];
            swapBtn.classList.add("is-swapping");
            window.setTimeout(() => swapBtn.classList.remove("is-swapping"), 350);
            onFieldChange();
        });

        // Close open listboxes when clicking anywhere outside them
        document.addEventListener("click", (e) => {
            [originCombo, destinationCombo].forEach((combo) => {
                if (!combo.wrap.contains(e.target)) combo.close();
            });
        });

        renderNow();
    }

    /* =========================================================================
       7. CAREER — posting cards, Details + Apply modals
       ========================================================================= */
    function initCareer() {
        const postings = readJsonData("careerPostingsData", []);
        const cardGrid = document.getElementById("careerCardGrid");
        if (!postings || !postings.length || !cardGrid) return;

        const postingsById = new Map(postings.map((p) => [p.id, p]));

        const detailsModalEl = document.getElementById("careerDetailsModal");
        const detailsLayout = document.getElementById("careerDetailsLayout");
        const detailsMedia = document.getElementById("careerDetailsMedia");
        const detailsBadge = document.getElementById("careerDetailsBadge");
        const detailsTitle = document.getElementById("careerDetailsModalLabel");
        const detailsBody = document.getElementById("careerDetailsBody");
        const detailsApplyBtn = document.getElementById("careerDetailsApplyBtn");

        const applyModalEl = document.getElementById("careerApplyModal");
        const applyLayout = document.getElementById("careerApplyLayout");
        const applyMedia = document.getElementById("careerApplyMedia");
        const applyForm = document.getElementById("careerApplyForm");
        const applyFields = document.getElementById("careerApplyFields");
        const applyPositionLabel = document.getElementById("careerApplyPositionLabel");
        const applyPostingIdInput = document.getElementById("careerApplyPostingId");
        const applyAlert = document.getElementById("careerApplyAlert");
        const applySuccess = document.getElementById("careerApplySuccess");
        const applySubmitBtn = document.getElementById("careerApplySubmitBtn");
        const applySpinner = document.getElementById("careerApplySpinner");

        const resumeDrop = document.getElementById("careerResumeDrop");
        const resumeInput = document.getElementById("careerResumeInput");
        const resumeSelected = document.getElementById("careerResumeSelected");
        const resumeError = document.getElementById("careerResumeError");

        const detailsModal = () => bootstrap.Modal.getOrCreateInstance(detailsModalEl);
        const applyModal = () => bootstrap.Modal.getOrCreateInstance(applyModalEl);

        let currentDetailsPostingId = null;
        let selectedResumeFile = null;

        /* ---- Details modal ---- */
        const detailSection = (label, value) =>
            value
                ? `<div class="sh-career-detail-section"><h6>${escapeHtml(label)}</h6><p>${escapeHtml(value)}</p></div>`
                : "";

        /* ---- Shared by both modals: poster panel + scroll reset ---- */
        function renderPoster(layoutEl, mediaEl, p) {
            // No poster → the right column takes the full width (no empty left panel).
            layoutEl.classList.toggle("sh-career-detail--no-media", !p.image_url);
            mediaEl.innerHTML = p.image_url
                ? `<img src="${escapeHtml(p.image_url)}" alt="${escapeHtml(p.title)} recruitment poster">` +
                  `<a class="sh-career-detail__zoom" href="${escapeHtml(p.image_url)}" target="_blank" rel="noopener">` +
                  '<i class="bi bi-arrows-fullscreen me-1" aria-hidden="true"></i>View full size</a>'
                : "";
        }

        // Each posting opens at the top, not where the previous one was scrolled to.
        function resetScroll(modalEl) {
            modalEl.querySelectorAll(".modal-body, .sh-career-detail__info").forEach((el) => {
                el.scrollTop = 0;
            });
        }

        function openDetails(id) {
            const p = postingsById.get(id);
            if (!p) return;
            currentDetailsPostingId = id;

            renderPoster(detailsLayout, detailsMedia, p);
            detailsBadge.textContent = p.position_name;
            detailsBody.innerHTML =
                detailSection("Short Description", p.short_description) +
                detailSection("Job Description", p.job_description) +
                detailSection("Qualifications / Requirements", p.qualifications) +
                detailSection("Responsibilities", p.responsibilities) +
                detailSection("Application Instructions", p.application_instructions) +
                detailSection("Contact / Application Email", p.contact_email);

            detailsTitle.textContent = p.title;
            resetScroll(detailsModalEl);
            detailsModal().show();
        }

        /* ---- Apply modal ---- */
        function openApply(id) {
            const p = postingsById.get(id);
            if (!p) return;
            resetApplyForm();
            renderPoster(applyLayout, applyMedia, p);
            applyPostingIdInput.value = p.id;
            applyPositionLabel.textContent = `${p.position_name} — ${p.title}`;
            resetScroll(applyModalEl);
            applyModal().show();
        }

        cardGrid.addEventListener("click", (e) => {
            const detailsBtn = e.target.closest(".sh-career-btn-details");
            const applyBtn = e.target.closest(".sh-career-btn-apply");
            if (detailsBtn) openDetails(parseInt(detailsBtn.dataset.postingId, 10));
            else if (applyBtn) openApply(parseInt(applyBtn.dataset.postingId, 10));
        });

        detailsApplyBtn.addEventListener("click", () => {
            if (currentDetailsPostingId === null) return;
            detailsModal().hide();
            openApply(currentDetailsPostingId);
        });

        /* ---- Resume dropzone (click-to-browse + drag & drop) ---- */
        function setResumeFile(file) {
            selectedResumeFile = file || null;
            resumeError.textContent = "";
            if (!selectedResumeFile) {
                resumeSelected.classList.add("d-none");
                resumeSelected.innerHTML = "";
                return;
            }
            resumeSelected.classList.remove("d-none");
            resumeSelected.innerHTML =
                '<i class="bi bi-file-earmark-check me-1" aria-hidden="true"></i>' +
                escapeHtml(selectedResumeFile.name) +
                '<button type="button" class="btn btn-link btn-sm text-danger" data-resume-remove>Remove</button>';
        }

        resumeSelected.addEventListener("click", (e) => {
            if (!e.target.closest("[data-resume-remove]")) return;
            e.stopPropagation(); // don't re-open the file picker
            resumeInput.value = "";
            setResumeFile(null);
        });

        resumeDrop.addEventListener("click", () => resumeInput.click());
        resumeDrop.addEventListener("keydown", (e) => {
            // Enter/Space activation, as for a native button
            if (e.key === "Enter" || e.key === " ") {
                e.preventDefault();
                resumeInput.click();
            }
        });
        resumeInput.addEventListener("change", () => setResumeFile(resumeInput.files?.[0] ?? null));

        ["dragenter", "dragover"].forEach((type) => {
            resumeDrop.addEventListener(type, (e) => {
                e.preventDefault();
                e.stopPropagation();
                resumeDrop.classList.add("is-active");
            });
        });
        ["dragleave", "drop"].forEach((type) => {
            resumeDrop.addEventListener(type, (e) => {
                e.preventDefault();
                e.stopPropagation();
                resumeDrop.classList.remove("is-active");
            });
        });
        resumeDrop.addEventListener("drop", (e) => {
            const file = e.dataTransfer?.files?.[0];
            if (file) setResumeFile(file);
        });

        /* ---- Validation + submission ---- */
        function clearFieldErrors() {
            applyForm.querySelectorAll(".is-invalid").forEach((el) => el.classList.remove("is-invalid"));
            applyForm.querySelectorAll(".invalid-feedback").forEach((el) => {
                el.textContent = "";
            });
        }

        function setSubmitting(isSubmitting) {
            applySubmitBtn.disabled = isSubmitting;
            applySpinner.classList.toggle("d-none", !isSubmitting);
        }

        function resetApplyForm() {
            applyForm.reset();
            clearFieldErrors();
            applyAlert.classList.add("d-none");
            applySuccess.classList.add("d-none");
            applyFields.classList.remove("d-none");
            applySubmitBtn.classList.remove("d-none");
            setSubmitting(false);
            setResumeFile(null);
        }

        function showFieldError(fieldName, message) {
            if (fieldName === "resume") {
                resumeError.textContent = message;
                return;
            }
            const input = applyForm.querySelector(`[name="${fieldName}"]`);
            if (!input) return;
            input.classList.add("is-invalid");
            const feedback = input.parentElement.querySelector(".invalid-feedback");
            if (feedback) feedback.textContent = message;
        }

        function validateClientSide() {
            let valid = true;
            const fullName = applyForm.full_name.value.trim();
            const email = applyForm.email.value.trim();
            const contactNumber = applyForm.contact_number.value.trim();

            if (!fullName) {
                showFieldError("full_name", "Full Name is required.");
                valid = false;
            }
            if (!email) {
                showFieldError("email", "Email is required.");
                valid = false;
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showFieldError("email", "Please enter a valid email address.");
                valid = false;
            }
            if (!contactNumber) {
                showFieldError("contact_number", "Contact Number is required.");
                valid = false;
            }
            return valid;
        }

        function showSuccess(message) {
            // Hide the fields (not the whole form, which also holds this
            // success alert) and auto-close shortly after.
            applyFields.classList.add("d-none");
            applySubmitBtn.classList.add("d-none");
            applySuccess.textContent = message || "Your application has been submitted successfully!";
            applySuccess.classList.remove("d-none");
            // It sits at the top of the form column — bring it into view.
            applySuccess.scrollIntoView({ block: "nearest" });
            window.setTimeout(() => applyModal().hide(), 2200);
        }

        function submitApplication() {
            clearFieldErrors();
            applyAlert.classList.add("d-none");
            if (!validateClientSide()) return;

            const formData = new FormData(applyForm);
            if (selectedResumeFile) formData.set("resume", selectedResumeFile);

            setSubmitting(true);

            fetch("ajax/career_apply.php", {
                method: "POST",
                headers: { "X-Requested-With": "XMLHttpRequest" },
                body: formData,
            })
                .then((response) =>
                    response
                        .json()
                        .catch(() => ({})) // non-JSON (e.g. a PHP error page)
                        .then((data) => ({ ok: response.ok, data })))
                .then(({ ok, data }) => {
                    if (ok && data.success) {
                        showSuccess(data.message);
                        return;
                    }
                    if (data.errors) {
                        Object.entries(data.errors).forEach(([field, message]) => showFieldError(field, message));
                        return;
                    }
                    throw new Error(data.message || "Something went wrong. Please try again.");
                })
                .catch((err) => {
                    applyAlert.textContent = err.message || "Something went wrong. Please try again.";
                    applyAlert.classList.remove("d-none");
                    applyAlert.scrollIntoView({ block: "nearest" });
                })
                .finally(() => setSubmitting(false));
        }

        applyForm.addEventListener("submit", (e) => {
            e.preventDefault();
            submitApplication();
        });

        applyModalEl.addEventListener("hidden.bs.modal", resetApplyForm);
    }

    /* =========================================================================
       8. FOOTER VESSEL ENTRANCE
       Plays once when the footer first scrolls into view. Without JS, or with
       reduced motion, the vessel simply stays in its resting position.
       ========================================================================= */
    function initFooterVessel() {
        const vesselWrap = document.getElementById("footerVesselWrap");
        const stage = document.getElementById("footerWaveStage");
        if (!vesselWrap || !stage || prefersReducedMotion()) return;

        // Opt into the hidden "about to sail in" pose only now that JS runs
        vesselWrap.classList.add("js-vessel-ready");

        if (!("IntersectionObserver" in window)) {
            vesselWrap.classList.add("sh-vessel-animate");
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            if (!entries.some((entry) => entry.isIntersecting)) return;
            vesselWrap.classList.add("sh-vessel-animate");
            observer.disconnect();
        }, { threshold: 0.15 });

        observer.observe(stage);
    }

    /* =========================================================================
       9. BOOT
       Each feature is isolated so a failure in one never blocks the others.
       ========================================================================= */
    const features = {
        initScrollReveal,
        initNavigation,
        initVesselCarousel,
        initPortMarquee,
        initFareFinder,
        initCareer,
        initFooterVessel,
    };

    const boot = () => {
        Object.entries(features).forEach(([name, init]) => {
            try {
                init();
            } catch (err) {
                console.error(`[scripts] ${name} failed`, err);
            }
        });
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
