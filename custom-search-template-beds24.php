<?php
/*
Template Name: Custom Search Results
*/
get_header();
$dates      = custom_get_booking_dates();
$arrive     = $dates['arrive'];
$depart     = $dates['depart'];
$calcNights = (strtotime($depart) - strtotime($arrive)) / 86400;
$nights     = isset($_GET['nights']) ? intval($_GET['nights']) : $calcNights;
$combined_prices = get_combined_room_prices();
?>
<script>
    const COMBINED_PRICES = <?php echo json_encode($combined_prices); ?>;
</script>
<?php

if ($nights <= 0) $nights = 1;
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap-grid.min.css" />
<style>
/* =====================================
   GENERAL LAYOUT
===================================== */
/* ================================
   ALTERNATIVE DATE CARD GRID
================================ */

/* Desktop: 6 columns */
.alt-dates-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 16px;
}

/* Cards */
.alt-card {
    padding: 14px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #fafafa;
    min-width: 150px; /* required for mobile scroll */
}

/* Mobile: Horizontal scroll, 1 card per view */
@media(max-width: 600px){
    .alt-dates-grid {
        display: flex;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        -webkit-overflow-scrolling: touch;
        gap: 12px;
        padding-bottom: 10px;
    }

    .alt-card {
        flex: 0 0 75%;
        scroll-snap-align: center;
        min-width: 150px;
    }

    /* Hide scrollbar (optional) */
    .alt-dates-grid::-webkit-scrollbar {
        display: none;
    }
}
.day-item:hover {
    margin-top: 5px;
    height: 25px;
}
.day-item {
    display: flex !important;
    flex-direction: column !important;
    align-items: center;
    justify-content: flex-start;
    line-height: 1.1;
    padding-top: 5px;
    position: relative;
    height: 25px;
    margin-top: 5px;
}
.lp-price {
    pointer-events: none !important;
    position: absolute;
    bottom: 2px;
    font-size: 10px;
    color: #000;
    top: 19px;
}
.site-content{display: block;}
.container {max-width: 100% !important; padding-left: 5px; padding-right: 5px;}
#beds24_result_section{margin-bottom: 100px;}
.she-header:not(.elementor-sticky){position: relative !important;}
.elementor-widget-theme-site-logo img{height: 70px;width: 100%;}
/* =====================================
   COMPACT SEARCH BAR
===================================== */
@media (max-width: 600px) {
    #guestPanel {
        left: -110px !important;
    }
    #openGuests {
        display: none !important;
    }
    #guestDisplayText {
        font-size: 13px !important;
        white-space: nowrap;
    }
     #daterange::placeholder,
    #nights::placeholder,
    #guests option:first-child {
        color: transparent !important;
    }
    .litepicker {
        max-width: 95vw !important;
        width: 95vw !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
    }
    .litepicker .container__months {
        flex-direction: column !important;
        gap: 15px;
    }

    .litepicker .container__months .month-item {
        width: 100% !important;
    }
    .litepicker .day-item {
        min-width: 32px !important;
        height: 32px !important;
        font-size: 12px !important;
    }
}

.search-bar-wrapper {
    position: relative;
    width: max-content;
    margin: 18px auto;
    background: #ffffff;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    transition: all 0.25s ease;
    padding: 6px 18px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
    
/* Inputs */
.search-bar-wrapper input,
.search-bar-wrapper select {
    height: 46px;
    border-radius: 8px;
    padding-left: 14px;
    border: 1px solid #d6d6d6;
    font-size: 15px;
}

/* Labels */
.search-bar-wrapper label {
    font-weight: 600;
    margin-bottom: 3px;
    font-size: 14px;
}

/* Search Button */
.search-btn-wrap button {
    height: 46px;
    width: 100%;
    background: #3d3732;
    border-radius: 8px;
    font-size: 16px;
    color: #fff;
    border: none;
}

/* =====================================
   STICKY SEARCH BAR ON SCROLL
===================================== */
.search-bar-wrapper-container.sticky{
    position: fixed !important;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    z-index: 9999;
    backdrop-filter: blur(8px);
    background-color: rgba(246, 246, 246, 0.66); width: 100%;}
.search-bar-wrapper-container.sticky .search-bar-wrapper{
    padding: 10px 16px;
    border: 1px solid #666;
    box-shadow: 0 2px 12px rgba(0,0,0,0.15);
    border-radius: 4px;
}

/* prevents jump */
.search-placeholder {
    height: 0;
    transition: height 0.3s ease;
}

/* Compact Mobile Layout */
@media(max-width: 480px){
    .search-bar-wrapper {
        padding: 10px;
        border-radius: 0;
        width: 100%;
    }
    .search-bar-wrapper input,
    .search-bar-wrapper select {
        height: 42px;
        width: 100%;
    }
    .search-btn-wrap button {
        margin-top: 10px;
        height: 42px;
    }
}
</style>
<style>
.hotel-field {
    position: relative;
    overflow: visible !important;
}

/* The guest dropdown must float ABOVE everything */
#guestPanel {
    position: absolute !important;
    top: 56px !important;
    left: 0;
    z-index: 9999 !important;
    transform: translateY(0);
}
.search-bar-wrapper {
    overflow: visible !important;
}
/* REMOVE LABELS COMPLETELY */
.hotel-field label {
    display: none !important;
}

/* ICON INSIDE INPUT (LEFT SIDE) */
.hotel-field {
    position: relative;
}

.hotel-field .hotel-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    opacity: .55;
}

/* Remove input borders + add nice background */
.hotel-field input,
.hotel-field select {
    height: 52px !important;
    padding: 0 14px 0 40px !important; /* space for icon */
    border-radius: 10px !important;
    background: #fff !important;
    font-size: 15px;
    color: #333;
}

/* Placeholder text styling */
.hotel-field input::placeholder,
.hotel-field select {
    color: #999 !important;
    font-weight: 500;
}

/* Remove dropdown arrow border */
.hotel-field select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg width='14' height='14' fill='none' stroke='%23666' stroke-width='1.8' viewBox='0 0 24 24'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
}

/* Container box matches example */
.search-bar-wrapper {
    border-radius: 5px !important;
    padding: 10px 18px !important;
    border: 1px solid #e4e4e4 !important;
}

/* Search button matches example */
.hotel-search-btn {
    border-radius: 5px !important;
    height: 52px !important;
    font-size: 16px !important;
    padding: 0 35px !important;
}

/* =====================================
   TOP PROGRESS STEPS (3-STEP BAR)
===================================== */
.steps-wrapper {
    width: 100%;
    max-width: 1200px;
    margin: 20px auto 10px auto;
    padding: 10px 0;
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
    height: 28px;
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
    background: #c59c52; /* Golden tone like screenshot */
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
    .steps-inner {
        gap: 10px;
    }
    .step-divider {
        width: 25px;
    }
    .step-label {
        font-size: 13px;
    }
    .step-number {
        padding: 5px 10px;
        font-size: 13px;
    }
}

.hotel-row {
    display: flex;
    gap: 16px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.hotel-field {
    flex: 1;
    min-width: 180px;
}

.hotel-field label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 6px;
    color: #444;
}

.hotel-icon {
    width: 16px;
    height: 16px;
    opacity: 0.65;
}

.hotel-field input,
.hotel-field select {
    width: 100%;
    height: 48px;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 14px;
}

.hotel-button-wrap {
    display: flex;
}

.hotel-search-btn {
    height: 48px;
    padding: 0 28px;
    background: #FF8A3D;
    border-radius: 8px;
    border: none;
    font-size: 17px;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
    transition: .2s ease;
}

.hotel-search-btn:hover {
    background: #2b2622;
}

/* =====================
   VIP CLUB BOX
===================== */
.vip-club-box {
    margin: 18px auto;
    background: #f2f7fc;
    border: 1px solid #dce8f1;
    margin-left: 60px;
    margin-right: 60px;
}

/* HEADER */
.vip-header {
    padding: 15px;
    border: 1px solid #dce8f1;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.vip-header h3 {
    margin: 0;
    font-size: 22px;
    color: #c59c52;
    font-weight: 700;
}

.vip-header p {
    margin: 3px 0 0;
    color: #444;
    font-size: 15px;
}

/* Toggle */
.switch {
  position: relative;
  display: inline-block;
  width: 52px;
  height: 26px;
  margin-left: 10px;
}
.switch input { display: none; }

.slider.round {
  border-radius: 34px;
}
.slider.round:before {
  border-radius: 50%;
}

.slider {
  position: absolute;
  cursor: pointer;
  background-color: #8b7f78;
  transition: .4s;
  border-radius: 30px;
  top: 0; left: 0; right: 0; bottom: 0;
}
.slider:before {
  content: "";
  position: absolute;
  height: 20px; width: 20px;
  left: 3px; bottom: 3px;
  background-color: white;
  transition: .4s;
}

/* On Toggle */
input:checked + .slider {
  background-color: #94857d;
}
input:checked + .slider:before {
  transform: translateX(26px);
}

/* Slider Bar */
.vip-slider {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 10px;
    max-width: 100%;background: #fff;
}

.vip-window {
    overflow: hidden;
    width: 100%;
}

.vip-track {
    display: flex;
    transition: transform 0.35s ease;
}

.vip-slide {
	flex: 0 0 auto;
    box-sizing: border-box;
    padding: 12px 16px;
    border-radius: 6px;
    background: #fff;
    font-size: 15px;
}
@media (max-width: 767px) {
    .vip-slide {
        width: 100%;          /* 1 per row */
        text-align: center;   /* center text */
        align-items: center;
        display: flex;
    }
}
@media (min-width: 768px) {
    .vip-slide {
        width: 33.3333%;     /* 3 per row */
        text-align: left;
    }
}
.vip-btn {
    background: #fff;
    border: 1px solid #c5c0bd;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    cursor: pointer;
    padding: 2px;
    margin-left: 8px;
    margin-right: 8px;
    color: #000;
}
/* FORCE SEARCH BAR TO BE ONE SINGLE LINE */
.hotel-row {
    display: flex !important;
    flex-wrap: nowrap !important;
    align-items: center;
    gap: 12px;
}

/* Fields should NOT expand to new line */
.hotel-field {
    flex: 0 0 auto !important;
    min-width: auto !important;
}

/* Button stays inline */
.hotel-button-wrap {
    flex: 0 0 auto !important;
}

/* On mobile, allow horizontal scroll if width exceeds screen */
@media(max-width: 600px){
    .vip-header h3 {
        font-size: 15px;
    }
    .vip-slider-wrapper{padding: 8px 0px}
    .vip-toggle span{font-size: 14px;}
    .vip-header p {
        font-size: 12px;
        margin-top: 5px;
    }
    .vip-toggle {
        margin-top: 5px;
        font-size: 12px;
    }
    .search-bar-wrapper {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        white-space: nowrap;
    }
    .hotel-field .hotel-icon {
        left: 8px;
    }
    .step-label {
        font-size: 12px;
    }
    .steps-wrapper {
        margin: 15px 0px auto;
        padding: 0px 0;
    }
    .hotel-field input, .hotel-field select {
        padding: 0 14px 0 30px !important;
    }
    .hotel-field {
        flex: 0 0 65px !important;
        min-width: 50px !important;
    }
    .hotel-field:first-child {
        flex: 0 0 152px !important;
        min-width: 152px !important;
    }
    .hotel-field:last-child {
        flex: 0 0 55px !important;
        min-width: 50px !important;
    }
    .hotel-button-wrap {
        flex: 0 0 75px !important;
    }
    .hotel-search-btn{width: 100%;}
    .hotel-field.nights{display: none;}
}

/* ------------------------------
   MOBILE COMPACT 2×2 GRID LAYOUT
-------------------------------- */
@media(max-width: 767px){

    /* Make each field compact */
    .hotel-field {
        min-width: auto;
    }

    /* Make search button full width on new row */
    .hotel-button-wrap {
        grid-column: 1 / 3;
    }

    .hotel-search-btn {
            padding: 0px 11px !important;
            margin: 0px;
            height: 42px !important;
    }

    /* Reduce font sizes for mobile */
    .hotel-field label {
        font-size: 12px;
    }

    .hotel-field input,
    .hotel-field select {
        height: 44px !important;
        font-size: 14px;
        padding-left: 12px;
    }

    /* Reduce overall search wrapper padding */
    .search-bar-wrapper {
        padding: 12px 14px !important;
    }
    .vip-header{display: block;}
    .vip-club-box{margin-left:0;margin-right:0;}
    .search-bar-wrapper-container.sticky{position: relative !important;}
}

</style>
<div class="container">
    <!-- ===========================
     BOOKING STEPS PROGRESS BAR
=========================== -->
<div class="steps-wrapper">
    <div class="steps-inner">

        <!-- Step 1 -->
        <div class="step-item active">
            <div class="step-number">1</div>
            <div class="step-label"><?php echo pll__('Choose stay'); ?></div>
        </div>

        <div class="step-divider"></div>

        <!-- Step 2 -->
        <div class="step-item">
            <div class="step-number">2</div>
            <div class="step-label"><?php echo pll__('Personal details'); ?></div>
        </div>

        <div class="step-divider"></div>

        <!-- Step 3 -->
        <div class="step-item">
            <div class="step-number">3</div>
            <div class="step-label"><?php echo pll__('Confirmation'); ?></div>
        </div>

    </div>
</div>

    <!-- ===========================
         SEARCH BAR
    ============================ -->
    <div class="search-bar-wrapper-container" id="mainSearchBar">
        <form method="GET" class="search-bar-wrapper" id="topSearchForm">
    
            <div class="hotel-row">
    
                <!-- Combined Calendar Field -->
                <div class="hotel-field">
                    <svg class="hotel-icon" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M7 11h2v2H7zm4 0h2v2h-2zm4 0h2v2h-2zM5 21q-.825 0-1.412-.587T3 19V7q0-.825.588-1.412T5 5h1V3h2v2h8V3h2v2h1q.825 0 1.413.588T21 7v12q0 .825-.587 1.413T19 21zM5 19h14V10H5z"/>
                    </svg>
                    <input type="text" id="daterange" readonly placeholder="<?php echo pll__('Check-in → Check-out'); ?>">
                </div>
    
                <!-- Nights -->
                <div class="hotel-field nights">
                    <svg class="hotel-icon" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M12 2q1.65 0 2.825 1.175T16 6q0 1.65-1.175 2.825T12 10q-1.65 0-2.825-1.175T8 6q0-1.65 1.175-2.825T12 2z"/>
                    </svg>
                    <input type="number" id="nights" name="nights" min="1" value="<?php echo esc_attr($nights); ?>" placeholder="<?php echo pll__('Nights'); ?>">
                </div>
    
                <!-- Guests (new style: Adults + Children counters) -->
                <div class="hotel-field" style="min-width:220px;position:relative;">
                    <svg class="hotel-icon" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M12 12q-1.65 0-2.825-1.175T8 8q0-1.65 1.175-2.825T12 4q1.65 0 2.825 1.175T16 8q0 1.65-1.175 2.825T12 12zm-8 8v-2q0-1.65 1.175-2.825T8 14h8q1.65 0 2.825 1.175T20 18v2z"/>
                    </svg>
    
                    <!-- Compact display text -->
                    <div id="guestDisplay" style="height:44px;display:flex;align-items:center;justify-content:space-between;padding:0 12px;border-radius:8px;border:1px solid #d6d6d6;background:#fff;font-weight:600;">
                        <span style="margin-left: 20px;" id="guestDisplayText"><?php echo (isset($_GET['adults'])?intval($_GET['adults']):2); ?> <?php echo pll_e('adults'); ?> · <?php echo (isset($_GET['children'])?intval($_GET['children']):2); ?> <?php echo pll__('children'); ?></span>
                        <button type="button" id="openGuests" style="background:none;border:0;cursor:pointer;padding:6px 10px;"><?php echo pll__('Edit'); ?></button>
                    </div>
    
                    <!-- Popup panel (hidden by default) -->
                    <div id="guestPanel" style="display:none;position:absolute;left:0;top:56px;width:260px;background:#fff;border:1px solid #ddd;border-radius:8px;padding:12px;box-shadow:0 6px 20px rgba(0,0,0,0.08);z-index:999;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                            <div style="font-weight:700"><?php echo pll__('Who'); ?></div>
                            <div style="color:#888;font-size:13px"></div>
                        </div>
    
                        <!-- Adults Row -->
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-top:1px solid #f4f4f4;">
                            <div>
                                <div style="font-weight:700"><?php echo pll__('Adults'); ?></div>
                                <div style="font-size:12px;color:#888"><?php echo pll__('From 15 years'); ?></div>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <button type="button" class="g-dec" data-field="adults" aria-label="Decrease adults" style="/* width:34px; *//* height:34px; */border-radius:6px;border:1px solid #e6e6e6;background:#fff;color: grey;padding: 3px 10px;">−</button>
                                <div id="adultsCount" style="min-width:34px;text-align:center;font-weight:700"><?php echo (isset($_GET['adults'])?intval($_GET['adults']):2); ?></div>
                                <button type="button" class="g-inc" data-field="adults" aria-label="Increase adults" style="border-radius:6px;border:1px solid #e6e6e6;background:#fff;color: grey;padding: 3px 10px;">+</button>
                            </div>
                        </div>
    
                        <!-- Children Row -->
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-top:1px solid #f4f4f4;">
                            <div>
                                <div style="font-weight:700"><?php echo pll__('Children'); ?></div>
                                <div style="font-size:12px;color:#888"><?php echo pll__('Up to 14 years'); ?></div>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <button type="button" class="g-dec" data-field="children" aria-label="Decrease children" style="border-radius:6px;border:1px solid #e6e6e6;background:#fff;color: grey;padding: 3px 10px;">−</button>
                                <div id="childrenCount" style="min-width:34px;text-align:center;font-weight:700"><?php echo (isset($_GET['children'])?intval($_GET['children']):0); ?></div>
                                <button type="button" class="g-inc" data-field="children" aria-label="Increase children" style="border-radius:6px;border:1px solid #e6e6e6;background:#fff;color: grey;padding: 3px 10px;">+</button>
                            </div>
                        </div>
    
                        <div style="display:flex;gap:8px;margin-top:12px;">
                            <button type="button" id="guestApply" style="flex:1;background:#FF8A3D;color:#fff;border:0;border-radius:8px;padding:10px;cursor:pointer;"><?php echo pll__('Apply'); ?></button>
                        </div>
                    </div>
    
                </div>
    
                <!-- Button -->
                <div class="hotel-button-wrap">
                    <button type="submit" class="hotel-search-btn"><?php echo pll__('Search'); ?></button>
                </div>
    
            </div>
    
            <!-- Hidden fields -->
            <input type="hidden" name="arrive" id="arrive" value="<?php echo esc_attr($arrive); ?>">
            <input type="hidden" name="depart" id="depart" value="<?php echo esc_attr($depart); ?>">
            <input type="hidden" name="adults" id="adults" value="<?php echo (isset($_GET['adults'])?intval($_GET['adults']):2); ?>">
            <input type="hidden" name="children" id="children" value="<?php echo (isset($_GET['children'])?intval($_GET['children']):0); ?>">
            <input type="hidden" name="guests" id="guests" value="<?php echo ((isset($_GET['adults'])?intval($_GET['adults']):2) + (isset($_GET['children'])?intval($_GET['children']):0)); ?>">
    
        </form>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function(){
    
        const guestDisplay = document.getElementById('guestDisplay');
        const guestDisplayText = document.getElementById('guestDisplayText');
        const openBtn = document.getElementById('openGuests');
        const panel = document.getElementById('guestPanel');
    
        const adultsCount = document.getElementById('adultsCount');
        const childrenCount = document.getElementById('childrenCount');
    
        const inputAdults = document.getElementById('adults');
        const inputChildren = document.getElementById('children');
        const inputGuests = document.getElementById('guests');
    
        const guestApply = document.getElementById('guestApply');
    
        function refreshDisplay(){
            const a = parseInt(inputAdults.value) || 0;
            const c = parseInt(inputChildren.value) || 0;
            const total = a + c;
        
            inputGuests.value = total;
        
            // Desktop text
            let fullText = `${a} <?php echo addslashes(pll_e('Adults')); ?>`;
            if (c > 0) fullText += ` · ${c} <?php echo addslashes(pll_e('Children')); ?>`;
        
            // Mobile text (total only)
            let mobileText = `${total}`;
        
            if (window.innerWidth <= 600) {
                guestDisplayText.innerText = mobileText;
            } else {
                guestDisplayText.innerText = fullText;
            }
        }
    
    
    
        refreshDisplay();
    
        // OPEN PANEL when clicking entire guest field
        guestDisplay.addEventListener('click', function(e){
            e.stopPropagation();
            panel.style.display = 'block';
        });
    
        // CLOSE PANEL on outside click
        document.addEventListener('click', function(e){
            if (!panel.contains(e.target) && !guestDisplay.contains(e.target)){
                panel.style.display = 'none';
            }
        });
    
        // Increment buttons
        document.querySelectorAll('.g-inc').forEach(btn=>{
            btn.addEventListener('click', function(){
                const field = this.dataset.field;
        
                let adults = parseInt(inputAdults.value) || 0;
                let children = parseInt(inputChildren.value) || 0;
        
                let total = adults + children;
        
                // STOP if total already 5
                if (total >= 5) return;
        
                if(field === 'adults'){
                    adults = Math.min(5, adults + 1);
                    inputAdults.value = adults;
                    adultsCount.innerText = adults;
                } else {
                    children = Math.min(5 - adults, children + 1);
                    inputChildren.value = children;
                    childrenCount.innerText = children;
                }
            });
        });
        
        // Decrement buttons
        document.querySelectorAll('.g-dec').forEach(btn=>{
            btn.addEventListener('click', function(){
                const field = this.dataset.field;
        
                if(field === 'adults'){
                    let v = Math.max(1, (parseInt(inputAdults.value) || 1) - 1);
                    inputAdults.value = v; adultsCount.innerText = v;
                } else {
                    let v = Math.max(0, (parseInt(inputChildren.value) || 0) - 1);
                    inputChildren.value = v; childrenCount.innerText = v;
                }
            });
        });
    
    
        // APPLY
        guestApply.addEventListener('click', function(){
            refreshDisplay();
            panel.style.display = 'none';
        });
    
    });
    </script>
	<!-- Litepicker -->
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css">
	<script src="https://cdn.jsdelivr.net/npm/litepicker/dist/litepicker.js"></script>
	<script>
	function getCalMonthFromUrl() {
		const params = new URLSearchParams(window.location.search);
		const calMonth = params.get('cal_month'); // YYYY-MM
		if (!calMonth) return null;

		const [y, m] = calMonth.split('-');
		return new Date(parseInt(y), parseInt(m) - 1, 1);
	}
	</script>
	<script>
	const SITE_LANG = "<?php echo esc_js( pll_current_language() ); ?>";
	document.addEventListener("DOMContentLoaded", function () {

		const dr = document.getElementById('daterange');
		const nights = document.getElementById('nights');
		const calMonthDate = getCalMonthFromUrl();
		const picker = new Litepicker({
			element: dr,
			singleMode: false,
			numberOfMonths: 2,
			numberOfColumns: 2,
			autoApply: true,
			lang: SITE_LANG,
			minDate: (() => {
				const d = new Date();
				d.setHours(0, 0, 0, 0);
				return d;
			})(),
			format: 'MMM D',  // display format
			setup: (picker) => {
				picker.on('selected', (start, end) => {

					if (!start || !end) return;

					let a = start.format('YYYY-MM-DD');
					let d = end.format('YYYY-MM-DD');

					// Human format for UI
					const opt = { month: 'short', day: 'numeric' };
					let fa = new Date(a).toLocaleDateString('en-US', opt);
					let fd = new Date(d).toLocaleDateString('en-US', opt);

					// Show formatted date in field
					dr.value = `${fa} - ${fd}`;

					// Update hidden fields
					document.getElementById('arrive').value = a;
					document.getElementById('depart').value = d;

					// Update nights
					let diff = Math.round((new Date(d) - new Date(a)) / 86400000);
					nights.value = diff > 0 ? diff : 1;
				});
			}
		});

		if (calMonthDate) {
			setTimeout(() => {
				picker.gotoDate(calMonthDate);
			}, 0);
		}

		picker.on('render', () => {
			console.log("before:render fired"); // Debug
			setTimeout(() => {
				document.querySelectorAll('.day-item').forEach(cell => {
					const timestamp = cell.dataset.time;
					if (!timestamp) return;

					const date = new Date(parseInt(timestamp));
					const yyyy = date.getFullYear();
					const mm = String(date.getMonth() + 1).padStart(2, '0');
					const dd = String(date.getDate()).padStart(2, '0');
					const key = `${yyyy}${mm}${dd}`;
					console.log(COMBINED_PRICES); // Debug
					console.log(key + " == " + COMBINED_PRICES[key]); // Debug
					if (COMBINED_PRICES[key]) {
						let price = Math.round(COMBINED_PRICES[key]);
						if (!cell.querySelector('.lp-price')) {
							cell.insertAdjacentHTML(
								'beforeend',
								`<div class="lp-price">€${price}</div>`
							);
						}
					}
				});
			}, 10); // small delay ensures DOM exists
		});


		nights.addEventListener('change', function () {

			let n = parseInt(nights.value);
			if (!n || n <= 0) {
				nights.value = 1;
				n = 1;
			}

			let arrive = document.getElementById('arrive').value;
			if (!arrive) return;

			let startDate = new Date(arrive);
			let newDepart = new Date(startDate.getTime() + n * 86400000);

			// Format YYYY-MM-DD
			let yyyy = newDepart.getFullYear();
			let mm = String(newDepart.getMonth() + 1).padStart(2, '0');
			let dd = String(newDepart.getDate()).padStart(2, '0');

			let formatted = `${yyyy}-${mm}-${dd}`;

			// Update hidden field
			document.getElementById('depart').value = formatted;

			// Update visual date range text
			let opt = { month: 'short', day: 'numeric' };
			let fa = new Date(arrive).toLocaleDateString('en-US', opt);
			let fd = newDepart.toLocaleDateString('en-US', opt);

			dr.value = `${fa} - ${fd}`;
		});

		// Set initial field display on page load
		<?php if ($arrive && $depart) : ?>
			dr.value = "<?php echo date('M j', strtotime($arrive)) . ' - ' . date('M j', strtotime($depart)); ?>";
		<?php endif; ?>
	});
	</script>
	<?php
	if (empty($arrive) || empty($depart)) {
		echo '<div class="vip-club-box booking-notice" style="
				max-width: 500px;
				margin: 0 auto;
			">
						<div class="vip-header" style="
			">'.pll__('Please select your dates to see availability and prices.').'
					</div></div>';
		return; // stop further rendering
	}
	?>
    <!-- placeholder for sticky bar spacing -->
    <!-- ===========================
         ESTANQUES VIP CLUB SECTION
    =========================== -->
    <div id="flexible-dates-container"></div>
    <div class="vip-club-box">
    
        <div class="vip-header">
            <div>
                <h3><?php echo pll__('Join apartamento sestanques VIP Club'); ?></h3>
                <p><?php echo pll__('As a member of our loyalty club, you can benefit from exclusive advantages when booking online.'); ?></p>
            </div>
    
            <div class="vip-toggle">
                <span><?php echo pll__("Members' benefits applied"); ?></span>
                <label class="switch">
                  <input type="checkbox" checked>
                  <span class="slider round"></span>
                </label>
            </div>
        </div>
    
        <div class="vip-slider">
				<button class="vip-btn vip-prev" type="button">←</button>
				<div class="vip-window">
					<div class="vip-track">
						<div class="vip-slide">&nbsp; <?php echo pll__('10% extra discount on all your bookings'); ?> </div>
						<div class="vip-slide">&nbsp; <?php echo pll__('Daily complimentary items included'); ?> </div>
						<div class="vip-slide">&nbsp; <?php echo pll__('Preferred plant choice'); ?> </div>
						<div class="vip-slide">&nbsp; <?php echo pll__('Free complete beach kit'); ?> </div>
						<div class="vip-slide">&nbsp; <?php echo pll__('Flexible check-in'); ?> </div>
						<div class="vip-slide">&nbsp; <?php echo pll__('Free parking in establishments where available'); ?> </div>
						<div class="vip-slide">&nbsp; <?php echo pll__('More benefits 1'); ?> </div>
						<div class="vip-slide">&nbsp; <?php echo pll__('More benefits 2'); ?> </div>
						<div class="vip-slide">&nbsp; <?php echo pll__('Access to exclusive services in all our hotels'); ?> </div>
						<div class="vip-slide">&nbsp; <?php echo pll__('Welcome gift upon arrival'); ?> </div>
						<div class="vip-slide">&nbsp; <?php echo pll__('Daily complimentary items included'); ?> </div>
						<div class="vip-slide">&nbsp; <?php echo pll__('And many more benefits!'); ?> </div>
					</div>
				</div>
				<button class="vip-btn vip-next" type="button">→</button>
		</div>
    </div>
    <!-- ===========================
         RESULTS SECTION
    ============================ -->
    <div id="beds24_result_section">
        <?php get_template_part('api-search-rooms'); ?>
    </div>
</div>
<script>
/* ============================
   STICKY SEARCH BAR BEHAVIOR
============================ */
document.addEventListener("DOMContentLoaded", function(){

    const bar = document.getElementById("mainSearchBar");
    const originalTop = bar.offsetTop;

    window.addEventListener("scroll", function(){

        if(window.scrollY > originalTop){
            if(!bar.classList.contains("sticky")){
                bar.classList.add("sticky");
            }
        } else {
            bar.classList.remove("sticky");
        }

    });
});
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {

    const track = document.querySelector(".vip-track");
    const slides = document.querySelectorAll(".vip-slide");
    const next = document.querySelector(".vip-next");
    const prev = document.querySelector(".vip-prev");

    let page = 0;

    function itemsPerView() {
        return window.innerWidth >= 768 ? 3 : 1;
    }

    function totalPages() {
        return Math.ceil(slides.length / itemsPerView());
    }

    function update() {
        track.style.transform = `translateX(-${page * 100}%)`;
    }

    next.onclick = () => {
        if (page < totalPages() - 1) {
            page++;
        } else {
            page = 0; // reset
        }
        update();
    };

    prev.onclick = () => {
        if (page > 0) {
            page--;
        } else {
            page = totalPages() - 1;
        }
        update();
    };

    window.addEventListener("resize", () => {
        page = 0;
        update();
    });

});
</script>
<div style="
    text-align:center;
    padding:30px 0;
    font-size:14px;
    color:#666;
    width:100%;
">

    <!-- COPYRIGHT -->
    <div style="margin-bottom:6px;">
        &copy; <?php echo (date('Y') + 1);echo " ";echo pll__('Apartments Ponds'); ?>
    </div>

    <!-- LINKS -->
    <div style="margin-top:6px;">
    <?php
    // Legal Notice
    if ($page = get_page_by_path('aviso-legal')) {
        $url = get_permalink(pll_get_post($page->ID));
        echo '<a href="' . esc_url($url) . '" style="color:#666;text-decoration:none;margin-right:14px;">'
            . esc_html(pll__('Legal Notice')) .
            '</a>';
    }

    // Cookies Policy
    if ($page = get_page_by_path('politica-de-cookies')) {
        $url = get_permalink(pll_get_post($page->ID));
        echo '<a href="' . esc_url($url) . '" style="color:#666;text-decoration:none;margin-right:14px;">'
            . esc_html(pll__('Cookies Policy')) .
            '</a>';
    }

    // Privacy Policy
    if ($page = get_page_by_path('politica-de-privacidad')) {
        $url = get_permalink(pll_get_post($page->ID));
        echo '<a href="' . esc_url($url) . '" style="color:#666;text-decoration:none;">'
            . esc_html(pll__('Privacy Policy')) .
            '</a>';
    }
    ?>
</div>


</div>

<?php get_footer(); ?>
