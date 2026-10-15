<?php
// sections/rates.php - Shipping Rates / Fare Management section
// Fare data is pulled from the database and managed via
// starhorse-vessel-admin/ (Fare Management module). No fare info
// should be hard-coded here — same convention as sections/vessels.php.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../starhorse-vessel-admin/functions/helpers.php';
require_once __DIR__ . '/../starhorse-vessel-admin/functions/fare_functions.php';

$fares = get_published_fares();
$fareImageBase = 'starhorse-vessel-admin/uploads/fares/';

// Build the unique From/To option lists (in first-seen order) so the
// client-side combobox script can offer them as suggestions. A
// vice-versa route also makes its reverse port valid to pick from
// either field — e.g. a single "Lucena -> Balanacan" (vice-versa) row
// means "Balanacan" must appear as a From suggestion too, and "Lucena"
// as a To suggestion, even though neither is stored in that direction.
$origins = [];
$destinations = [];
foreach ($fares as $fare) {
    if (!in_array($fare['origin'], $origins, true)) {
        $origins[] = $fare['origin'];
    }
    if (!in_array($fare['destination'], $destinations, true)) {
        $destinations[] = $fare['destination'];
    }

    if (!empty($fare['is_vice_versa'])) {
        if (!in_array($fare['destination'], $origins, true)) {
            $origins[] = $fare['destination'];
        }
        if (!in_array($fare['origin'], $destinations, true)) {
            $destinations[] = $fare['origin'];
        }
    }
}

$faresForJs = array_map(function ($fare) use ($fareImageBase) {
    return [
        'origin'        => $fare['origin'],
        'destination'   => $fare['destination'],
        'passenger'     => image_asset_url($fare['passenger_fare_image'], $fareImageBase),
        'cargo'         => image_asset_url($fare['cargo_fare_image'], $fareImageBase),
        'is_vice_versa' => !empty($fare['is_vice_versa']),
    ];
}, $fares);

?>
<section class="page-section bg-light" id="rates">
    <div class="container px-4 px-lg-5">
        <h2 class="text-center mt-0">Shipping Rates</h2>
        <hr class="divider" />

        <?php if (empty($fares)): ?>
        <p class="text-center text-muted">Fare information is currently being updated. Please check back soon.</p>
        <?php else: ?>

        <div class="row justify-content-center">
            <div class="col-lg-9 col-xl-8">
                <div class="sh-fare-card">

                    <div class="sh-fare-route">
                        <div class="sh-fare-route__fields">

                            <div class="sh-fare-combo" data-role="origin">
                                <label for="fareOriginInput" class="sh-fare-label">From</label>
                                <div class="sh-fare-combo__control">
                                    <i class="bi bi-geo-alt sh-fare-combo__icon" aria-hidden="true"></i>
                                    <input type="text" id="fareOriginInput" class="sh-fare-combo__input" role="combobox"
                                        aria-expanded="false" aria-autocomplete="list" aria-controls="fareOriginListbox"
                                        aria-haspopup="listbox" autocomplete="off" placeholder="Select or type a port"
                                        value="">
                                    <button type="button" class="sh-fare-combo__clear" tabindex="-1"
                                        aria-label="Clear origin">
                                        <i class="bi bi-x-circle-fill" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <ul class="sh-fare-combo__listbox" id="fareOriginListbox" role="listbox"
                                    aria-label="Origin suggestions" hidden></ul>
                            </div>

                            <div class="sh-fare-combo" data-role="destination">
                                <label for="fareDestinationInput" class="sh-fare-label">To</label>
                                <div class="sh-fare-combo__control">
                                    <i class="bi bi-geo-alt-fill sh-fare-combo__icon" aria-hidden="true"></i>
                                    <input type="text" id="fareDestinationInput" class="sh-fare-combo__input"
                                        role="combobox" aria-expanded="false" aria-autocomplete="list"
                                        aria-controls="fareDestinationListbox" aria-haspopup="listbox"
                                        autocomplete="off" placeholder="Select or type a port" value="">
                                    <button type="button" class="sh-fare-combo__clear" tabindex="-1"
                                        aria-label="Clear destination">
                                        <i class="bi bi-x-circle-fill" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <ul class="sh-fare-combo__listbox" id="fareDestinationListbox" role="listbox"
                                    aria-label="Destination suggestions" hidden></ul>
                            </div>

                        </div>

                        <div class="sh-fare-route__actions">
                            <button type="button" id="fareSwapBtn" class="sh-fare-swap"
                                aria-label="Swap origin and destination">
                                <i class="bi bi-arrow-left-right" aria-hidden="true"></i>
                                <span>Swap Route</span>
                            </button>
                        </div>
                    </div>

                    <div id="fareResult" class="sh-fare-result">
                        <!-- populated by js/scripts.js based on the selected route -->
                    </div>

                    <p class="text-muted text-center small mb-0 mt-4">*Rates subject to change without prior notice.
                        Please contact us to confirm current fares before booking.</p>
                </div>
            </div>
        </div>

        <script id="fareData" type="application/json">
        <?= json_encode([
            'fares'        => $faresForJs,
            'origins'      => $origins,
            'destinations' => $destinations,
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>
        </script>

        <?php endif; ?>
    </div>
</section>
