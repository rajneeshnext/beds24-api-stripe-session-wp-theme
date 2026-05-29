<?php
/*
Template Name: Booking Failed
*/
get_header();
?>

<style>
.site-content{display:block;}
.elementor-widget-theme-site-logo img {
    height: 70px;
    width: 100%;
}

/* =====================================
   TOP PROGRESS STEPS (3-STEP BAR)
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
    background: #d9534f; /* red highlight for failure */
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

/* =====================================
   FAILED MESSAGE BOX
===================================== */
.failed-wrapper {
    max-width: 800px;
    margin: 40px auto;
    padding: 30px;
    background: #fff4f4;
    border: 1px solid #f0c4c4;
    border-radius: 8px;
    text-align: center;
}

.failed-icon {
    font-size: 48px;
    color: #d9534f;
    margin-bottom: 15px;
}

.failed-title {
    font-size: 26px;
    color: #b52b27;
    margin-bottom: 10px;
    font-weight: 700;
}

.failed-text {
    font-size: 16px;
    color: #555;
    margin-bottom: 20px;
    line-height: 1.5;
}

.failed-buttons a {
    display: inline-block;
    padding: 12px 24px;
    border-radius: 6px;
    font-size: 15px;
    text-decoration: none;
    margin: 10px;
    font-weight: 600;
}

.retry-btn {
    background: #3d3732;
    color: #fff;
}

.home-btn {
    background: #ccc;
    color: #333;
}
</style>

<!-- ===========================
     3-STEP PROGRESS BAR
=========================== -->
<div class="steps-wrapper">
    <div class="steps-inner">

        <div class="step-item">
            <div class="step-number">1</div>
            <div class="step-label"><?php pll_e('Choose stay'); ?></div>
        </div>

        <div class="step-divider"></div>

        <div class="step-item">
            <div class="step-number">2</div>
            <div class="step-label"><?php pll_e('Personal details'); ?></div>
        </div>

        <div class="step-divider"></div>

        <!-- Step 3 (ACTIVE — failure) -->
        <div class="step-item active">
            <div class="step-number">3</div>
            <div class="step-label"><?php pll_e('Confirmation'); ?></div>
        </div>

    </div>
</div>

<!-- ===========================
     FAILED BLOCK
=========================== -->
<div class="failed-wrapper">

    <div class="failed-icon">⚠️</div>

    <div class="failed-title"><?php pll_e('Payment Failed'); ?></div>

    <p class="failed-text">
        <?php pll_e('Unfortunately your payment could not be completed.'); ?><br>
        <?php pll_e('This may happen due to insufficient balance, incorrect card details, or a network issue.'); ?>
    </p>

    <p class="failed-text">
        <?php pll_e('Please try again or choose a different payment method.'); ?>
    </p>

    <div class="failed-buttons">
        <a href="javascript:history.back();" class="retry-btn"><?php pll_e('Try Again'); ?></a>
        <a href="<?php echo home_url(); ?>" class="home-btn"><?php pll_e('Return Home'); ?></a>
    </div>

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
