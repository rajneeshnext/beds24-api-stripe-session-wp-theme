<?php
/*
Template Name: Test - Custom Instant Booking
*/
get_header();
session_start();
//$property = beds24_request_get('properties?id=' . $listing_id . '&includeUpsellItems=true');echo "<pre>";print_r($property);exit();
$sessionKey = 'beds24_booking_session_' . session_id();
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sessionData = get_transient($sessionKey);
    if (is_array($sessionData) && isset($sessionData['bookingIds'])) {
        unset($sessionData['bookingIds']);
        set_transient($sessionKey, $sessionData, 3600);
    }
}
// Required URL parameters
$check_in        = isset($_GET['check_in']) ? $_GET['check_in'] : '';
$check_out       = isset($_GET['check_out']) ? $_GET['check_out'] : '';
$room_ids        = isset($_GET['room_id']) ? explode(",", $_GET['room_id']) : [];
$adult_guest     = isset($_GET['adult_guest']) ? intval($_GET['adult_guest']) : 0;
$child_guest     = isset($_GET['child_guest']) ? intval($_GET['child_guest']) : 0;
$room_offer_data = isset($_GET['room_offer_data']) ? explode(",", $_GET['room_offer_data']) : [];
$unit_guest_data = isset($_GET['unit_guest_data']) ? explode(",", $_GET['unit_guest_data']) : [];
$childs_data       = isset($_GET['childs_data']) ? explode(",", $_GET['childs_data']) : [];
$subtotal        = isset($_GET['subtotal']) ? floatval($_GET['subtotal']) : 0;
$total           = isset($_GET['total']) ? floatval($_GET['total']) : 0;
$listing_id      = isset($_GET['listing_id']) ? $_GET['listing_id'] : '';
$voucherCode = trim($_GET['voucher'] ?? '');
$voucherValid = false;
$voucherDiscount = 0;

$rateType		 = isset($_GET['rateType']) ? $_GET['rateType'] : '';
$bookingSummary = [];
$bookingSummary[] = [
    "roomId"   => isset($_GET['room_id']) ? $_GET['room_id'] : "",
    "offerId"  => isset($_GET['offer_id']) ? $_GET['offer_id'] : "",
    "price"    => isset($_GET['price']) ? floatval($_GET['price']) : 0,
    "rateType" => isset($_GET['rateType']) ? $_GET['rateType'] : "",
    "adults"   => $adult_guest,
    "childs"   => $child_guest
];
$arrivalDate   = new DateTime($check_in);
$today         = new DateTime('today');
$daysToArrive  = (int) $today->diff($arrivalDate)->days;
$arrivalMonth  = (int) $arrivalDate->format('n');
$isHighSeason     = ($arrivalMonth >= 6 && $arrivalMonth <= 9);
$minDaysForCharge = $isHighSeason ? 12 : 4;
// Only applies to FLEXIBLE rate
$chargeFlexibleNow = false;
if ($rateType === 'flexible') {
    $chargeFlexibleNow = ($daysToArrive < $minDaysForCharge);
}
// Button label
$payButtonText = (!$chargeFlexibleNow && $rateType === 'flexible')
    ? pll__('Confirm reservation')
    : pll__('Confirm and Pay now');
// SET subtotal and total automatically if missing
if ($subtotal == 0) $subtotal = $bookingSummary[0]["price"];
if ($total == 0) $total = $bookingSummary[0]["price"];
$property = beds24_request_get(
        'properties?id=' . $listing_id . '&includeUpsellItems=true'
);
if ($voucherCode && $listing_id) {
    if (!empty($property['data'][0]['discountVouchers'])) {
        foreach ($property['data'][0]['discountVouchers'] as $voucher) {
            if (
                empty($voucher['phrase']) ||
                empty($voucher['discount']) ||
                $voucher['type'] === 'notUsed'
            ) {
                continue;
            }
            if (trim($voucher['phrase']) === $voucherCode) {

                $voucherValid = true;

                if ($voucher['type'] === 'percent') {
                    $voucherDiscount = ($total * $voucher['discount']) / 100;
                }

                if ($voucher['type'] === 'amount') {
                    $voucherDiscount = min($voucher['discount'], $total);
                }

                break;
            }
        }
    }
    if ($voucherValid) {
        $total = max(0, $total - $voucherDiscount);
    } else {
        wp_safe_redirect(
            add_query_arg('voucher_invalid', '1', remove_query_arg('voucher'))
        );
        exit;
    }
}
$babyBeds = null;
$babyBedsIndex = null;
$babyBedsAmount = 0;
if (!empty($property['data'][0]['upsellItems'])) {
    foreach ($property['data'][0]['upsellItems'] as $item) {
		//print_r($item);exit();
        // We assume BabyBeds is OPTIONAL + oneTime + per booking
        if (
            ($item['type'] ?? '') === 'optional' &&
            ($item['period'] ?? '') === 'oneTime' &&
            ($item['per'] ?? '') === 'child' &&
            ($item['amount'] ?? 0) > 0
        ) {
            // Treat this as BabyBeds
            $babyBeds = $item;
            $babyBedsIndex = $item['index'];
            $babyBedsAmount = (float) $item['amount'];
            break;
        }
    }
}
//$babyBeds = null;
//$babyBedsIndex = null;
//$babyBedsAmount = 0;
?>
<style>
.site-content{display: block;}
.elementor-widget-theme-site-logo img {
    height: 70px;
    width: 100%;
}
.input-error {
    border-color: #e63946 !important;
    background: #ffe5e5 !important;
}

.error-text {
    color: #e63946;
    font-size: 13px;
    margin-top: -8px;
    margin-bottom: 10px;
}
.disable-click {
    pointer-events: none !important;
    opacity: 0.55 !important;
}
.lds-dual-ring {
  display: inline-block;
  width: 32px;
  height: 32px;
}
.lds-dual-ring:after {
  content: " ";
  display: block;
  width: 32px;
  height: 32px;
  margin: 1px;
  border-radius: 50%;
  border: 4px solid #666;
  border-color: #666 transparent #666 transparent;
  animation: lds-dual-ring 1.2s linear infinite;
}
@keyframes lds-dual-ring {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* =====================================
   TOP PROGRESS STEPS (3-STEP BAR ONLY)
===================================== */
.steps-wrapper {
    width: 100%;
    max-width: 1200px;
    margin: 20px auto 10px auto;
    padding: 10px 15px;
}

.steps-inner {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 14px;
}

.step-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.step-number {
    width: 28px;
    height: 29px;
    border-radius: 50%;
    background: #3d3732;
    color: #fff;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.step-item.active .step-number {
    background: #c59c52; /* gold color for active */
}

.step-label {
    font-size: 15px;
    color: #333;
    font-weight: 500;
}

.step-divider {
    width: 40px;
    height: 2px;
    background: #ccc;
    opacity: 0.7;
}

/* MOBILE */
@media(max-width: 767px){
    .steps-inner { gap: 10px; }
    .step-divider { width: 25px; }
    .step-label { font-size: 13px; }
    .step-number {
        width: 24px;
        height: 24px;
        font-size: 13px;
    }
}
/* ================================================
   MOBILE — EXACT MIRAI BEHAVIOR
================================================ */
.mobile-paynow-bar {display: none;}
/* ================================================
   MOBILE — SIDEBAR ON TOP + MIRAI BEHAVIOR
================================================ */
@media (max-width: 768px) {

    /* Stack layout vertically */
    .booking-container {
        display: flex;
        flex-direction: column;
        padding: 0 12px;
        gap: 0;
    }

    /* Sidebar goes to the top */
    .right-column {
        order: -1 !important;  /* move to top */
        width: 100% !important;
        max-width: 100% !important;
        margin-bottom: 20px !important;
        margin-top: 10px !important;
        position: relative !important;
        top: 0 !important;
        background: #fff;
        padding: 16px !important;
        border-radius: 6px;
        box-shadow: none;
    }

    /* Left column becomes full width */
    .left-column {
        width: 100%;
    }

    /* Section spacing */
    .section-card {
        padding: 16px !important;
        margin-bottom: 18px !important;
    }

    /* Form fields full width */
    .input-box {
        width: 100%;
        font-size: 15px;
        padding: 12px;
    }

    /* Booking details */
    .booking-details {
        margin-top: 20px;
        padding: 16px !important;
    }

    /* Hide desktop pay button inside right sidebar */
    .right-column button {
        display: none;
    }

    /* Mobile sticky pay now bar */
    .mobile-paynow-bar {
        display: block;
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        background: #FF8A3D;
        color: #fff;
        padding: 16px;
        text-align: center;
        font-size: 17px;
        font-weight: 600;
        border-radius: 0;
        z-index: 9999;
        box-shadow: 0 -2px 8px rgba(0,0,0,0.2);
    }
}

</style>

<div class="steps-wrapper">
    <div class="steps-inner">

        <!-- Step 1 -->
        <div class="step-item">
            <div class="step-number">1</div>
            <div class="step-label"><?php pll_e('Choose stay'); ?></div>
        </div>

        <div class="step-divider"></div>

        <!-- Step 2 (ACTIVE) -->
        <div class="step-item active">
            <div class="step-number">2</div>
            <div class="step-label"><?php pll_e('Personal details'); ?></div>
        </div>

        <div class="step-divider"></div>

        <!-- Step 3 -->
        <div class="step-item">
            <div class="step-number">3</div>
            <div class="step-label"><?php pll_e('Confirmation'); ?></div>
        </div>

    </div>
</div>
<!-- BODY WILL BE ADDED LATER -->
<style>
/* ========================================
   MAIN LAYOUT
======================================== */
.booking-container {
    max-width: 1200px;
    margin: 20px auto 80px auto;
    display: flex;
    gap: 30px;
}

.left-column {
    flex: 2;
}

.right-column {
    flex: 1;
    border: 1px solid #e5e5e5;
    padding: 20px;
    border-radius: 4px;
    height: max-content;
    position: sticky;
    top: 20px;
    background: #fafafa;
}

/* SECTION CARD */
.section-card {
    border: 1px solid #e5e5e5;
    padding: 20px;
    border-radius: 4px;
    margin-bottom: 20px;
    background: #fff;
}

.section-title {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 16px;
}

/* INPUT STYLING */
.input-box {
    width: 100%;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 12px;
    font-size: 15px;
}

/* PHONE FIELD */
.phone-row {
    display: flex;
    gap: 10px;
}

.select-prefix {
    width: 90px;
}

/* CHECKBOXES */
.checkbox-row {
    display: flex;
    align-items: center;
    margin-top: 12px;
    gap: 8px;
    font-size: 14px;
}

/* RIGHT SIDEBAR */
.right-title {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 12px;
}

.right-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
}

.right-total {
    font-size: 18px;
    font-weight: 700;
    padding: 10px 0;
}

.pay-btn {
    width: 100%;
    background: #4c3f36;
    padding: 14px;
    border-radius: 6px;
    border: none;
    font-size: 16px;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
    margin-top: 10px;
}

/* BOOKING DETAILS TABLE */
.booking-details {
    margin-top: 25px;
    border: 1px solid #e5e5e5;
    padding: 20px;
    border-radius: 4px;
}

.details-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
    font-size: 15px;
}

.details-row:last-child {
    border-bottom: none;
}

/* POLICIES */
.policies {
    margin-top: 25px;
    font-size: 13px;
    line-height: 1.5;
}

.policies-title {
    font-weight: 700;
    margin-top: 18px;
}
.select2-container--default .select2-selection--single {
    border: 1px solid #aaa !important;
    border-radius: 0 !important;
    color: #666666 !important;
    background-color: #fafafa !important;
    border-color: #cccccc !important;
    height: 44px !important;
    padding-top: 8px !important;
}
.select2-container--default .select2-selection--single .select2-selection__arrow b{margin-top: 5px !important;}
/* ===========================
   RESERVATION CARD
=========================== */
.reservation-card {
    display: flex;
    gap: 16px;
    padding: 16px;
    border: 1px solid #e6e6e6;
    border-radius: 10px;
    background: #fff;
    margin-bottom: 14px;
}

.reservation-image {
    width: 90px;
    height: 90px;
    object-fit: cover;
    border-radius: 8px;
    flex-shrink: 0;
}

.reservation-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.reservation-header {
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.reservation-title {
    font-size: 16px;
    font-weight: 700;
    color: #222;
}

.reservation-meta {
    font-size: 13px;
    color: #777;
    margin-top: 2px;
}

.reservation-price {
    font-size: 16px;
    font-weight: 700;
    color: #111;
    white-space: nowrap;
}

.reservation-footer {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    color: #666;
    padding-top: 8px;
    border-top: 1px dashed #e5e5e5;
    margin-top: 10px;
}

.reservation-rate {
    font-weight: 500;
}

/* MOBILE */
@media (max-width: 600px) {
    .reservation-card {
        padding: 14px;
    }

    .reservation-image {
        width: 72px;
        height: 72px;
    }

    .reservation-title {
        font-size: 15px;
    }

    .reservation-price {
        font-size: 15px;
    }
}
	
</style>

<div class="booking-container">

    <!-- LEFT SIDE -->
    <div class="left-column">
		<?php if ($babyBeds): ?>
		<div class="section-card">
			<div class="section-title"><?php pll_e('BabyBeds'); ?></div>

			<label style="display:flex;align-items:center;gap:10px;font-size:15px;">
				<input
					type="checkbox"
					id="babybeds_checkbox"
					data-index="<?php echo esc_attr($babyBedsIndex); ?>"
					data-amount="<?php echo esc_attr($babyBedsAmount); ?>"
				>
				<?php pll_e('Baby bed'); ?> (+€<?php echo number_format($babyBedsAmount, 2); ?>)
			</label>
		</div>
		<?php endif; ?>

        <!-- PERSONAL DETAILS -->
        <div class="section-card">
            <div class="section-title"><?php pll_e('Personal details'); ?></div>

            <input type="text" name="first_name" class="input-box" placeholder="<?php pll_e('First name (required)'); ?>">
            <input type="text" name="last_name" class="input-box" placeholder="<?php pll_e('Last name (required)'); ?>">
            <input type="email" name="email" class="input-box" placeholder="<?php pll_e('Email (required)'); ?>">
			<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
			<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
            <div class="phone-row">
                <select name="prefix" class="input-box select-prefix">
                    <!-- Popular / Important -->
                    <option value="+1">+1 (USA / Canada)</option>
                    <option value="+44">+44 (United Kingdom)</option>
                    <option value="+91">+91 (India)</option>
                    <option value="+971">+971 (UAE)</option>
                    <option value="+61">+61 (Australia)</option>
                    <option value="+65">+65 (Singapore)</option>
                
                    <!-- European Union Countries -->
                    <option value="+43">+43 (Austria)</option>
                    <option value="+32">+32 (Belgium)</option>
                    <option value="+359">+359 (Bulgaria)</option>
                    <option value="+385">+385 (Croatia)</option>
                    <option value="+357">+357 (Cyprus)</option>
                    <option value="+420">+420 (Czech Republic)</option>
                    <option value="+45">+45 (Denmark)</option>
                    <option value="+372">+372 (Estonia)</option>
                    <option value="+358">+358 (Finland)</option>
                    <option value="+33">+33 (France)</option>
                    <option value="+49">+49 (Germany)</option>
                    <option value="+30">+30 (Greece)</option>
                    <option value="+36">+36 (Hungary)</option>
                    <option value="+353">+353 (Ireland)</option>
                    <option value="+39">+39 (Italy)</option>
                    <option value="+371">+371 (Latvia)</option>
                    <option value="+370">+370 (Lithuania)</option>
                    <option value="+352">+352 (Luxembourg)</option>
                    <option value="+356">+356 (Malta)</option>
                    <option value="+31">+31 (Netherlands)</option>
                    <option value="+48">+48 (Poland)</option>
                    <option value="+351">+351 (Portugal)</option>
                    <option value="+40">+40 (Romania)</option>
                    <option value="+421">+421 (Slovakia)</option>
                    <option value="+386">+386 (Slovenia)</option>
                    <option value="+34">+34 (Spain)</option>
                    <option value="+46">+46 (Sweden)</option>
                
                    <!-- Other European (non-EU but important) -->
                    <option value="+41">+41 (Switzerland)</option>
                    <option value="+47">+47 (Norway)</option>
                    <option value="+354">+354 (Iceland)</option>
                    <option value="+423">+423 (Liechtenstein)</option>
                
                    <!-- Other Important Countries -->
                    <option value="+86">+86 (China)</option>
                    <option value="+81">+81 (Japan)</option>
                    <option value="+82">+82 (South Korea)</option>
                    <option value="+60">+60 (Malaysia)</option>
                    <option value="+62">+62 (Indonesia)</option>
                    <option value="+63">+63 (Philippines)</option>
                    <option value="+92">+92 (Pakistan)</option>
                    <option value="+94">+94 (Sri Lanka)</option>
                    <option value="+55">+55 (Brazil)</option>
                    <option value="+52">+52 (Mexico)</option>
                    <option value="+27">+27 (South Africa)</option>
                </select>
                <input type="text" name="mobile" class="input-box" placeholder="<?php pll_e('Phone (required)'); ?>">
				<script>
					jQuery(document).ready(function($){
						$('.select-prefix').select2({
							width: '90px',
							dropdownAutoWidth: true,
							minimumResultsForSearch: 0, // ALWAYS show search
							placeholder: '+ code'
						});
					});
				</script>
            </div>

            <div class="checkbox-row" style="margin-bottom: 10px;">
                <input type="checkbox" name="agree_terms">
                <label>
                    <?php
						$text        = trim(pll__('I agree and accept the payment terms, cancellation, other conditions, the'));
						$legal_label = trim(pll__('Legal Notice'));
						$privacy_label = trim(pll__('Privacy & Cookies Policy'));
						$and_label   = trim(pll__('and'));

						$legal_url   = home_url('/aviso-legal/');
						$privacy_url = home_url('/cookies-policy/');

						/* --- TEXT PART --- */
						if ($text !== '') {
							echo '<span class="checkout-terms-text">' . esc_html($text) . '</span>';
						}

						/* --- LINKS PART --- */
						$links = [];

						if ($legal_label !== '' && $legal_url) {
							$links[] = '<a href="' . esc_url($legal_url) . '" target="_blank">'
								. esc_html($legal_label) . '</a>';
						}

						if ($privacy_label !== '' && $privacy_url) {
							$links[] = '<a href="' . esc_url($privacy_url) . '" target="_blank">'
								. esc_html($privacy_label) . '</a>';
						}

						if (!empty($links)) {
							echo ' ' . implode(' ' . esc_html($and_label) . ' ', $links);
						}
						?>

                </label>
            </div>
        </div>
        <div class="section-card">
            <div class="section-title"><?php pll_e('Voucher'); ?></div>

            <form method="get" style="display:flex;gap:10px;align-items:center;">
                <input
                    type="text"
                    name="voucher"
                    class="input-box"
                    placeholder="<?php pll_e('Enter voucher code'); ?>"
                    value="<?php echo esc_attr($_GET['voucher'] ?? ''); ?>"
                    style="margin-bottom:0;flex:1;">

                <?php foreach ($_GET as $k => $v): ?>
                    <?php if (!in_array($k, ['voucher', 'voucher_invalid'])): ?>
                        <input type="hidden" name="<?php echo esc_attr($k); ?>" value="<?php echo esc_attr($v); ?>">
                    <?php endif; ?>
                <?php endforeach; ?>

                <button class="pay-btn" style="padding:12px 18px;font-size:14px;width: auto;margin: 0;">
                    <?php pll_e('Apply'); ?>
                </button>
            </form>

            <?php if (!empty($_GET['voucher']) && empty($_GET['voucher_invalid'])): ?>
                <div style="margin-top:8px;font-size:13px;color:#1a7f37;font-weight:600;">
                    ✓ <?php pll_e('Voucher applied'); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($_GET['voucher_invalid'])): ?>
                <div class="error-text" style="display:block;margin-top:8px;">
                    <?php pll_e('Voucher code is invalid'); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ONLINE PAYMENT -->
        <div class="section-card">
            <div class="section-title"><?php pll_e('Online secure payment'); ?></div>
            <p style="font-size:14px;margin-bottom:12px;"><?php pll_e("You'll be redirected to complete your payment."); ?></p>

            <input type="text" name="card_name" class="input-box" placeholder="<?php pll_e('Cardholder name (required)'); ?>">
			<!-- CARD NUMBER -->
			<input type="text" name="card_number" class="input-box" 
				   placeholder="<?php pll_e('Card number'); ?>" 
				   maxlength="19" autocomplete="cc-number">

			<!-- EXPIRY + CVC -->
			<div style="display:flex; gap:10px;">
				<input type="text" name="card_expiry" class="input-box" 
					   placeholder="<?php pll_e('MM/YY'); ?>" maxlength="5" autocomplete="cc-exp">
				<input type="text" name="card_cvc" class="input-box" 
					   placeholder="<?php pll_e('CVC'); ?>" maxlength="4" autocomplete="cc-csc">
			</div>
        </div>

        <!-- ADDITIONAL INFO -->
        <div class="section-card">
            <div class="section-title"><?php pll_e('Additional Information'); ?></div>

            <textarea name="comments" class="input-box" placeholder="<?php pll_e('Comments'); ?>"></textarea>

            <!--<small><?php pll_e('We will try to handle your requests, but we cannot always guarantee it.'); ?></small>

            <select name="arrival_time" class="input-box" style="margin-top:12px;">
                <option>Not sure yet</option>
                <option>Morning</option>
                <option>Afternoon</option>
                <option>Evening</option>
            </select>

            <div class="checkbox-row">
                <input type="checkbox" name="future_offers">
                <label><?php pll_e('I would like to receive future offers and news.'); ?></label>
            </div>

            <div class="checkbox-row">
                <input type="checkbox" name="become_member">
                <label><?php pll_e('I want to become a member and accept terms.'); ?></label>
            </div>-->
        </div>

        <div class="booking-details" style="
            border:1px solid #e5e5e5;
            padding:20px;
            border-radius:6px;
            margin-top:25px;
        ">
        
            <div class="section-title" style="font-size:20px;font-weight:700;margin-bottom:20px;">
                <?php pll_e('Booking details'); ?>
            </div>
        
            <?php foreach ($bookingSummary as $i => $room): ?>
        
                <!-- TOP ROW: IMAGE + TITLE + SUBTITLE -->
                <div class="reservation-card">
					<img class="reservation-image"
						 src="<?php echo site_url(); ?>/wp-content/uploads/2025/05/apartamentos-estanques-coloniasantjordi-mallorca-27.jpg"
						 alt="Apartment">

					<div class="reservation-content">
						<div class="reservation-header">
							<div>
								<div class="reservation-title">
									<?php pll_e('Apartment'); ?> <?php echo esc_html($room['roomId']); ?>
								</div>
								<div class="reservation-meta">
									<?php echo $room['adults']; ?> <?php pll_e('adults'); ?>
									<?php if ($room['childs'] > 0): ?>
										· <?php echo $room['childs']; ?> <?php pll_e('children'); ?>
									<?php endif; ?>
								</div>
							</div>

							<div class="reservation-price">
								€<?php echo number_format($room['price'], 2); ?>
							</div>
						</div>

						<div class="reservation-footer">
							<span>
								<?php
								echo esc_html(
									wp_date(get_option('date_format'), strtotime($check_in))
								);
								?>
							</span>
							<span class="reservation-rate"><?php pll_e('Room only'); ?></span>
						</div>
					</div>
				</div>
            <?php endforeach; ?>
        
        </div>


        <!-- POLICIES -->
        <div class="policies" style="border:1px solid #e5e5e5; padding:20px; border-radius:4px; margin-top:25px; background:#fff;">

            <div class="policies-title" style="font-weight:700; font-size:15px; margin-bottom:6px;">
                <?php pll_e('Payment terms'); ?>
            </div>
            <div style="font-size:14px; margin-bottom:18px;">
                <?php pll_e('Prepayment required: 100%: Online secure payment'); ?>
            </div>
        
            <div class="policies-title" style="font-weight:700; font-size:15px; margin-bottom:6px;">
                <?php pll_e('Cancellation policy'); ?>
            </div>
            <div style="font-size:14px; margin-bottom:18px; line-height:1.45;">
                <?php pll_e('The refund of this amount in case of justified cancellation.
                The refund of the prepaid amount is not allowed if the reason for cancellation is not included in the general 
                conditions of the policy (26 cases contemplated). In case of no show, the refund of the prepaid amount is not allowed.'); ?>
            </div>
            <div class="policies-title" style="font-weight:700; font-size:15px; margin-bottom:6px;">
                <?php pll_e('Other terms'); ?>
            </div>
            <div style="font-size:14px; line-height:1.45;">
                <p>
                <?php pll_e('The hotel will adapt to comply with current protocols and safety measures dictated by the authorities at all times.
                Non refundable reservations are associated with cancellation insurance when formalizing the reservation. Check all conditions here.
                You can find all the information about our cancellation insurance in FAQs.'); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- RIGHT SIDE SUMMARY -->
    <div class="right-column" style="
            border:1px solid #e5e5e5;
            padding:20px;
            border-radius:6px;
            background:#fafafa;
            max-width:380px;
            position:sticky;
            top:20px;
        ">
        
            <!-- BOOKING SUMMARY TITLE -->
            <div style="font-size:20px;font-weight:700;margin-bottom:16px;">
                <?php pll_e('Booking summary'); ?>
            </div>
        
            <!-- CHECK-IN / CHECK-OUT -->
            <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
                <div>
					<div style="font-weight:700;">
						<?php pll_e('Check-in'); ?>
					</div>
					<div>
						<?php
						echo esc_html(
							wp_date(
								'D, M j, Y',
								strtotime($check_in)
							)
						);
						?>
					</div>
				</div>

				<div>
					<div style="font-weight:700;">
						<?php pll_e('Check-out'); ?>
					</div>
					<div>
						<?php
						echo esc_html(
							wp_date(
								'D, M j, Y',
								strtotime($check_out)
							)
						);
						?>
					</div>
					<!-- <div style="font-size:12px;color:#777;">to 12:00</div> -->
				</div>

            </div>
        
            <!-- YOUR RESERVATION -->
            <!-- YOUR RESERVATION -->
            <div style="font-weight:700;margin-top:14px;"><?php pll_e('Your reservation'); ?></div>
            
            <?php foreach ($bookingSummary as $i => $unit): ?>
                <div style="font-size:14px;margin-top:8px;margin-bottom:4px;">
                    <strong><?php pll_e('Room'); ?> <?php echo $unit['roomId']; ?></strong>
                </div>
                <div style="font-size:13px;color:#555;margin-left:10px;">
                    <?php pll_e('Adults'); ?>: <?php echo $unit['adults']; ?>  
                    &nbsp;·&nbsp;
                    <?php pll_e('Children'); ?>: <?php echo $unit['childs']; ?>
                </div>
            <?php endforeach; ?>
            
            <hr style="border-top:1px solid #ddd;margin:20px 0;">
        
            <!-- PRICE SUMMARY -->
            <div style="font-size:18px;font-weight:700;margin-bottom:14px;">
                <?php pll_e('Price summary'); ?>
            </div>
            <?php
            $start   = new DateTime($check_in);
            $end     = new DateTime($check_out);
        
            $adultCount = intval($_GET['adults'] ?? 2);
            $touristTax = 0;
            
            // Loop through each night and add correct tax
            $period = new DatePeriod($start, new DateInterval('P1D'), $end);
            
            foreach ($period as $day) {
                $month = intval($day->format('m'));
            
                if ($month >= 5 && $month <= 10) {
                    // May → October
                    $touristTax += 2 * $adultCount;
                } else {
                    // November → April
                    $touristTax += 0.50 * $adultCount;
                }
            }
            
            // Add tax to total (if needed)
            //$touristTax = 0;
            //$total = $total + $touristTax;
            ?>

            <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                <span><?php pll_e('Base price'); ?></span>
                <span>€<?php echo number_format($subtotal,2); ?></span>
            </div>
            <?php if ($voucherValid): ?>
            <div class="right-row" style="color:#c00;">
                <span><?php pll_e('Voucher'); ?> (<?php echo esc_html($voucherCode); ?>)</span>
                <span>-€<?php echo number_format($voucherDiscount, 2); ?></span>
            </div>
            <?php endif; ?>

            <!--
            <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                <span><?php pll_e('Offer discount'); ?></span>
                <span style="color:#c00;">-€<?php echo number_format(($subtotal - $total),2); ?></span>
            </div>-->
			<div id="babybeds_summary_row"></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                <span><?php pll_e('Tourist tax'); ?></span>
                <span>€<?php echo number_format($touristTax, 2); ?></span>
            </div>

            <div style="display:flex;justify-content:space-between;font-size:18px;font-weight:700;margin:12px 0;">
                <span><?php pll_e('Total'); ?></span>
                <span>€<?php echo number_format($total, 2); ?></span>
            </div>

        
            <!-- WARNING BOX -->
            <div style="
                font-size:13px;
                color:#7a5f2a;
                background:#fff6e5;
                border-left:3px solid #e0b86c;
                padding:10px;
                border-radius:4px;
                margin-bottom:18px;
                line-height:1.4;
            ">
                <?php pll_e("Your booking will be processed in the hotels currency. Currency conversion rates may vary. Prepayment now is partial. Local taxes will be paid in the property."); ?>
            </div>
        
            <!-- PAY NOW BUTTON -->
            <button class="paynow-desktop-btn" style="
                width:100%;
                background:#FF8A3D;
                color:#fff;
                font-size:16px;
                padding:14px;
                border:none;
                border-radius:6px;
                cursor:pointer;
                font-weight:600;
                margin-bottom:14px;
            ">
				<?php echo $payButtonText; ?> €<?php echo number_format($total,2); ?>
            </button>
        
            <div style="font-size:12px;text-align:center;color:#777;">
                <?php pll_e('Your information is protected with SSL encryption.'); ?>
            </div>
            <div id="payment-loader" style="display:none; margin-top:15px; text-align:center;">
                <div class="lds-dual-ring"></div>
                <div style="margin-top:8px;font-size:15px;color:#444;">
                    <?php pll_e('Processing payment'); ?>…
                </div>
            </div>
        </div>
</div>
<div class="mobile-paynow-bar">
	<?php echo $payButtonText; ?> €<?php echo number_format($total,2); ?>
</div>
<div style="
    text-align:center;
    padding:30px 0;
    font-size:14px;
    color:#666;
    width:100%;
">

    <!-- COPYRIGHT -->
    <div style="margin-bottom:6px;">
        &copy;
        <span>2025 <?php pll_e('Apartments Ponds'); ?></span>
    </div>

    <!-- LINKS -->
   <div style="margin-top:6px;">
    <a href="<?php echo esc_url( pll_home_url() . 'aviso-legal/' ); ?>"
       style="color:#666;text-decoration:none;margin-right:14px;">
        <?php pll_e('Legal Notice'); ?>
    </a>

    <a href="<?php echo esc_url( pll_home_url() . 'politica-de-cookies/' ); ?>"
       style="color:#666;text-decoration:none;margin-right:14px;">
        <?php pll_e('Cookies Policy'); ?>
    </a>

    <a href="<?php echo esc_url( pll_home_url() . 'politica-de-privacidad/' ); ?>"
       style="color:#666;text-decoration:none;">
        <?php pll_e('Privacy Policy'); ?>
    </a>
</div>

</div>
<script>
jQuery(document).ready(function($){
	/* ============================
   BABY BEDS UPSELL
============================ */
	let babyBedsApplied = false;
	function updateTotalDisplay(newTotal) {
		$(".right-column span:contains('Total')")
			.next()
			.text("€" + newTotal.toFixed(2));

		$(".paynow-desktop-btn").text(
			"<?php echo esc_js($payButtonText); ?> €" + newTotal.toFixed(2)
		);

		$(".mobile-paynow-bar").text(
			"<?php echo esc_js($payButtonText); ?> €" + newTotal.toFixed(2)
		);
	}
	$(document).on("change", "#babybeds_checkbox", function () {
		let amount = parseFloat($(this).data("amount")) || 0;
		let total = parseFloat("<?php echo $total; ?>");
		if (this.checked && !babyBedsApplied) {
			babyBedsApplied = true;
			total += amount;
			$("#babybeds_summary_row").html(`
				<div class="right-row">
					<span><?php pll_e('Baby bed'); ?></span>
					<span>€${amount.toFixed(2)}</span>
				</div>
			`);
			updateTotalDisplay(total);
		}
		if (!this.checked && babyBedsApplied) {
			babyBedsApplied = false;
			//total -= amount;
			$("#babybeds_summary_row").empty();
			updateTotalDisplay(total);
		}
	});

    window.paymentInProgress = false;

    /* ============================
       ERROR HANDLING
    ============================ */
    function clearErrors() {
        $(".input-box").removeClass("input-error");
        $(".error-text").remove();
    }

    function showError(input, message) {
        $(input).addClass("input-error");
        $(input).after(`<div class="error-text">${message}</div>`);
    }

    /* ============================
       VALIDATION
    ============================ */
    function validateForm() {
        clearErrors();

        let valid = true;

        let firstName = $("input[name='first_name']");
        let lastName  = $("input[name='last_name']");
        let email     = $("input[name='email']");
        let mobile    = $("input[name='mobile']");
        let cardName  = $("input[name='card_name']");
        let agree     = $("input[name='agree_terms']");
		let cardNumber = $("input[name='card_number']");
		let cardExpiry = $("input[name='card_expiry']");
		let cardCvc    = $("input[name='card_cvc']");
		// CARD NUMBER (basic Luhn-safe length check)
		if (!/^\d{13,19}$/.test(cardNumber.val().replace(/\s+/g,''))) {
			showError(cardNumber, "Enter a valid card number");
			valid = false;
		}

		// EXPIRY MM/YY
		if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(cardExpiry.val())) {
			showError(cardExpiry, "Expiry must be MM/YY");
			valid = false;
		}

		// CVC
		if (!/^\d{3,4}$/.test(cardCvc.val())) {
			showError(cardCvc, "Invalid CVC");
			valid = false;
		}		

        if (firstName.val().trim() === "") {
            showError(firstName, "First name is required");
            valid = false;
        }

        if (lastName.val().trim() === "") {
            showError(lastName, "Last name is required");
            valid = false;
        }

        if (email.val().trim() === "" || !email.val().includes("@")) {
            showError(email, "Valid email is required");
            valid = false;
        }

        if (mobile.val().trim() === "" || mobile.val().length < 6) {
            showError(mobile, "Valid phone number is required");
            valid = false;
        }

        if (cardName.val().trim() === "") {
            showError(cardName, "Cardholder name is required");
            valid = false;
        }

        if (!agree.is(":checked")) {
            showError(agree.parent(), "You must accept the terms to continue.");
            valid = false;
        }

        return valid;
    }

    /* ============================
       LOADER CONTROL
    ============================ */
    function showLoader() {
        $("#payment-loader").show();

        // disable BOTH buttons
        $(".paynow-desktop-btn, .mobile-paynow-bar").addClass("disable-click");
    }

    function hideLoader() {
        $("#payment-loader").hide();

        // re-enable both buttons
        $(".paynow-desktop-btn, .mobile-paynow-bar").removeClass("disable-click");
    }

    /* ============================
       MAIN PAYMENT AJAX
    ============================ */
    function triggerBeds24Payment() {

        // Prevent double click on either button
        if (window.paymentInProgress) {
            console.log("⚠ Already processing payment. Double click blocked.");
            return;
        }

        // Validation first
        if (!validateForm()) {
            $("html, body").animate({ 
                scrollTop: $(".input-error:first").offset().top - 120 
            }, 300);
            return;
        }

        window.paymentInProgress = true; // BLOCK
        showLoader();
		// Card number formatting (xxxx xxxx xxxx xxxx)
		$("input[name='card_number']").on("input", function () {
			let v = $(this).val().replace(/\D/g, "").substring(0,19);
			$(this).val(v.replace(/(.{4})/g, "$1 ").trim());
		});

		// Expiry auto slash
		$("input[name='card_expiry']").on("input", function () {
			let v = $(this).val().replace(/\D/g, "").substring(0,4);
			if (v.length >= 3) v = v.substring(0,2) + "/" + v.substring(2);
			$(this).val(v);
		});
		let babyBedsEl = document.getElementById("babybeds_checkbox");
		let babyBedsData = {
			index: babyBedsEl && babyBedsEl.checked ? babyBedsEl.dataset.index : null,
			amount: babyBedsEl && babyBedsEl.checked ? babyBedsEl.dataset.amount : 0
		};
        let payload = {
			action: "beds24_create_booking_and_stripe",
			babyBeds: babyBedsData,
			first_name: $("input[name='first_name']").val(),
			last_name: $("input[name='last_name']").val(),
			email: $("input[name='email']").val(),
			mobile: $("input[name='mobile']").val(),
			comment: $("textarea[name='comments']").val(),
            voucher: "<?php echo esc_js($voucherCode); ?>",
			// CARD DATA
			card_name: $("input[name='card_name']").val(),
			card_number: $("input[name='card_number']").val(),
			card_expiry: $("input[name='card_expiry']").val(),
			card_cvc: $("input[name='card_cvc']").val(),

			check_in: "<?php echo $check_in; ?>",
			check_out: "<?php echo $check_out; ?>",
			bookingSummary: <?php echo json_encode($bookingSummary); ?>,
			total: "<?php echo $total; ?>"
		};


        $.post("<?php echo admin_url('admin-ajax.php'); ?>", payload)
            .done(function(res){
				try {
					let json = JSON.parse(res);
					if (json.error) {
						hideLoader();
						window.paymentInProgress = false;
						alert("Error: " + json.error);
						return;
					}
					if (json.payment && json.payment.error) {
						hideLoader();
						window.paymentInProgress = false;
						alert("Error: " + json.payment.error);
						return;
					}
					let redirectUrl =
						json.url ||
						json.redirect ||
						(json.payment && json.payment.url);

					if (redirectUrl) {
						window.location.href = redirectUrl;
						return;
					}

					hideLoader();
					window.paymentInProgress = false;
					alert("Unexpected server response.");

				}
                catch (e) {
                    hideLoader();
                    window.paymentInProgress = false;
                    alert("Invalid response from server.");
                }
            })
            .fail(function(){
                hideLoader();
                window.paymentInProgress = false;
                alert("Network error. Please try again.");
            });
    }

    /* ============================
       BUTTON HANDLERS (Both Buttons)
    ============================ */
    $(document).on("click", ".paynow-desktop-btn, .mobile-paynow-bar", function(e){
        e.preventDefault();
        triggerBeds24Payment();
    });

});
</script>

<?php get_footer(); ?>