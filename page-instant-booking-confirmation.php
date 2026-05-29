<?php
/*
Template Name: Custom Booking Confirmation
*/
get_header();
session_start();

/* ============================================================
   LOAD ALL DATA FROM TRANSIENT
============================================================ */
$data = get_transient("beds24_booking_session_" . session_id());
/*$data = [
    "bookingIds" => [
        0 => 79342124
    ],
    "total" => 4,
    "check_in" => "2026-01-14",
    "check_out" => "2026-01-16",
    "bookingSummary" => [
        [
            "roomId"   => 628783,
            "offerId"  => 1,
            "adults"   => 1,
            "children" => 2,
            "price"    => 4,
            "rateType" => "flexible"
        ]
    ],
    "customer" => [
        "first"   => "RAJNEESH",
        "last"    => "SAINI",
        "email"   => "rajnish123saini@gmail.com",
        "mobile"  => "8729064430",
        "comment" => ""
    ]
];*/

if (!$data || empty($data["bookingIds"])) {
?>
    <div style="text-align:center;margin-top:20px;width: 100%;">
        <h2 style='text-align:center;color:red;'>Booking session expired.</h2><br/><br/><br/>
        <a href="<?php echo home_url(); ?>" 
           style="background:#3d3732;color:#fff;padding:12px 24px;border-radius:6px;font-size:16px;text-decoration:none;">
            <?php pll_e('Return to homepage'); ?>
        </a>
    </div>
<?php    
    get_footer();
    exit;
}

$bookingIds      = $data["bookingIds"];
$totalPaid       = $data["total"];
$check_in        = $data["check_in"];
$check_out       = $data["check_out"];
$bookingSummary  = $data["bookingSummary"];
$customer        = $data["customer"];

/* ============================================================
   FETCH REAL BOOKINGS FROM BEDS24 API
============================================================ */
$idsQuery = implode("&id[]=", $bookingIds);
$response = beds24_request_get("bookings?id[]=" . $idsQuery);
if (empty($response["data"])) {
    echo "<h2 style='text-align:center;color:red;'>Unable to load booking details.</h2>";
    get_footer();
    exit;
}

$allBookings = $response["data"];
//echo "<pre>";print_r($allBookings);exit();
/* ============================================================
   FILTER BOOKINGS EXACTLY TO MATCH THE $bookingIds ORDER
============================================================ */
$bookings = [];
foreach ($bookingIds as $bid) {
    foreach ($allBookings as $b) {
        if ($b["id"] == $bid) {
            $bookings[] = $b;
            break;
        }
    }
}

if (empty($bookings)) {
    echo "<h2 style='text-align:center;color:red;'>No matching bookings found.</h2>";
    get_footer();
    exit;
}

/* ============================================================
   SHARED BOOKING INFO
============================================================ */
$first = $bookings[0];

$customerName  = trim(($first["firstName"] ?? '') . " " . ($first["lastName"] ?? ''));
$customerEmail = $first["email"] ?? $customer["email"];
$customerPhone = $first["mobile"] ?? $customer["mobile"];

$nights = (strtotime($check_out) - strtotime($check_in)) / 86400;

/* ============================================================
   PAYMENT DETAILS
============================================================ */
$rateTypes = array_column($bookingSummary, 'rateType');
// flexible = pending payment
$isFlexible = in_array("flexible", $rateTypes);
// everything else = prepaid
$isPrepaid  = !$isFlexible;
$paymentMode = $isFlexible ? "Pending payment" : "Paid via Stripe";
$confirmationText = $isFlexible
    ? "Your reservation is received but payment is pending."
    : "Your reservation has been successfully confirmed.";

/* ============================================================
   BUILD FULL HTML EMAIL MATCHING CONFIRMATION PAGE
============================================================ */

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Booking Confirmation</title>
</head>

<body style="margin:0;padding:0;background:#f7f7f7;font-family:Arial, Helvetica, sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;background:#f7f7f7;">
<tr>
<td align="center">

    <table width="650" cellpadding="0" cellspacing="0"
           style="background:#ffffff;border-radius:8px;border:1px solid #e5e5e5;padding:30px;">

        <tr>
            <td align="center" style="text-align:center;padding-bottom:20px;">
                <h2 style="font-size:26px;color:#333;margin:0;">
                    <?php pll_e("Thank you for your booking!");?>
                </h2>
                <p style="font-size:15px;color:#666;margin:8px 0 0;">
                    <?php pll_e($confirmationText);?>
                </p>

                <p style="font-size:15px;margin-top:12px;color:#000;">
                    <strong>Booking IDs:</strong> <?php echo implode(", ", $bookingIds); ?>
                </p>

                <p style="font-size:15px;margin-top:5px;color:#000;">
                    <strong>Payment method:</strong> <?php echo $paymentMode; ?>
                </p>
            </td>
        </tr>

        <tr><td><hr style="border:0;border-top:1px solid #eee;margin:20px 0;"></td></tr>

        <tr>
            <td>
                <h3 style="font-size:20px;font-weight:700;margin:0 0 12px;">Your stay</h3>

                <table width="100%" cellpadding="5" cellspacing="0" style="font-size:15px;color:#333;">
                    <tr>
                        <td>Check-in:</td>
                        <td align="right"><strong><?php echo date("D, M j, Y", strtotime($check_in)); ?></strong></td>
                    </tr>
                    <tr>
                        <td>Check-out:</td>
                        <td align="right"><strong><?php echo date("D, M j, Y", strtotime($check_out)); ?></strong></td>
                    </tr>
                    <tr>
                        <td>Total nights:</td>
                        <td align="right"><strong><?php echo $nights; ?></strong></td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr><td><hr style="border:0;border-top:1px solid #eee;margin:20px 0;"></td></tr>

        <tr>
            <td>
                <h3 style="font-size:20px;font-weight:700;margin:0 0 12px;"><?php pll_e("Guest information");?></h3>

                <p style="font-size:15px;margin:4px 0;">
                    <strong>Name:</strong> <?php echo esc_html($customerName); ?>
                </p>
                <p style="font-size:15px;margin:4px 0;">
                    <strong>Email:</strong> <?php echo esc_html($customerEmail); ?>
                </p>
                <p style="font-size:15px;margin:4px 0;">
                    <strong>Mobile:</strong> <?php echo esc_html($customerPhone); ?>
                </p>
            </td>
        </tr>

        <tr><td><hr style="border:0;border-top:1px solid #eee;margin:20px 0;"></td></tr>

        <tr>
            <td>
                <h3 style="font-size:20px;font-weight:700;margin:0 0 12px;">Your rooms</h3>

                <?php foreach ($bookings as $i => $b): ?>
                <div style="padding:10px 0;border-bottom:1px solid #eee;">
                    <div style="font-size:16px;font-weight:600;margin-bottom:5px;">
                        Unit <?php echo $i+1; ?> Room <?php echo $b["roomId"]; ?> (Unit ID: <?php echo $b["unitId"]; ?>)
                    </div>
                    <div style="font-size:14px;color:#777;">
                        Rate type: <strong><?php echo ucfirst($bookingSummary[$index]["rateType"]); ?></strong>
                    </div>
                    
                    <div style="font-size:14px;color:#333;">
                        Price: €<?php echo number_format($bookingSummary[$index]["price"], 2); ?>
                    </div>

                    <div style="font-size:14px;color:#333;margin:5px 0;">
                        Adults: <?php echo $bookingSummary[$index]["adults"]; ?>  
                        Children: <?php echo $bookingSummary[$index]["childs"]; ?>
                    </div>

                    <div style="font-size:13px;color:#777;margin-top:4px;">
                        Booked on: <?php echo date("D, M j, Y â€” H:i", strtotime($b["bookingTime"])); ?>
                    </div>

                    <?php if (!empty($b["comments"])): ?>
                    <div style="font-size:14px;margin-top:8px;">
                        <strong>Guest comments:</strong>
                        <?php echo nl2br(esc_html($b["comments"])); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

            </td>
        </tr>

        <tr><td><hr style="border:0;border-top:1px solid #eee;margin:20px 0;"></td></tr>

        <tr>
            <td>
                <h3 style="font-size:20px;font-weight:700;margin:0 0 12px;">Payment summary</h3>

                <table width="100%" cellpadding="5" cellspacing="0">
                    <tr>
                        <td style="font-size:15px;">Total paid:</td>
                        <td align="right" style="font-size:18px;font-weight:700;">
                            €<?php echo number_format($totalPaid, 2); ?>
                        </td>
                    </tr>
                </table>

                <p style="font-size:13px;color:#777;margin-top:10px;">
                    Your payment was processed securely. A confirmation email has been sent.
                </p>
            </td>
        </tr>

    </table>

</td>
</tr>
</table>

</body>
</html>
<?php
$emailHTML = ob_get_clean();
/*if ($isPrepaid && empty($_SESSION[$bookingKey])) {
    wp_mail(
        $customerEmail,
        "Booking Confirmation – Your Stay",
        $emailHTML,
        ["Content-Type: text/html; charset=UTF-8"]
    );
    $_SESSION[$bookingKey] = true;
}*/

session_start();
// Create a unique key for this booking
$bookingKey = "email_sent_" . $data["bookingIds"][0];
if (empty($_SESSION[$bookingKey])) {
    wp_mail(
        $customerEmail,
        "Booking Confirmation – Your Stay",
        $emailHTML,
        ["Content-Type: text/html; charset=UTF-8"]
    );
    // Mark email as sent
    $_SESSION[$bookingKey] = true;
}

?>
<style>
.container {max-width: 100% !important; padding-left: 5px; padding-right: 5px;}
.site-content{display:block;}
.elementor-widget-theme-site-logo img{height:70px;width:100%;}
p{margin:0;}
hr{margin:20px 0;}

/* Step bar */
.steps-wrapper{max-width:1200px;margin:20px auto;padding:10px 15px;}
.steps-inner{display:flex;justify-content:center;gap:14px;align-items: center; }
.step-item{display:flex;align-items:center;gap:8px;}
.step-number{
    padding: 8px;
    width:28px;height:28px;border-radius:50%;
    background:#3d3732;color:#fff;font-weight:600;
    display:flex;align-items:center;justify-content:center;
}
.step-item.active .step-number{background:#c59c52;}
.step-label{font-size:15px;font-weight:500;color:#333;}
.step-divider{width:40px;height:2px;background:#ccc;opacity:0.7;}
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
        flex: 0 0 55px !important;
        min-width: 50px !important;
    }
    .hotel-field:first-child {
        flex: 0 0 155px !important;
        min-width: 155px !important;
    }
    .hotel-field:last-child {
        flex: 0 0 55px !important;
        min-width: 50px !important;
    }
    .hotel-button-wrap {
        flex: 0 0 90px !important;
    }
    .hotel-search-btn{width: 100%;}
    .hotel-field.nights{display: none;}
}
</style>
<div class="container">
    <!-- STEP BAR -->
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
    <!-- MAIN WRAPPER -->
    <div class="confirmation-wrapper" style="
        max-width:900px;margin:30px auto;padding:25px;
        background:#fff;border:1px solid #e5e5e5;border-radius:8px;">
    
        <!-- HEADER -->
        <div style="text-align:center;margin-bottom:25px;">
            <h2 style="font-size:26px;margin-bottom:10px;"><?php pll_e('Thank you for your booking!'); ?></h2>
            <p style="font-size:15px;color:#666;"><?php pll_e($confirmationText); ?></p>
    
            <p style="font-size:15px;margin-top:8px;">
                <strong><?php pll_e('Booking IDs'); ?>:</strong> <?php echo implode(", ", $bookingIds); ?>
            </p>
    
            <p style="font-size:15px;margin-top:8px;">
                <strong><?php pll_e('Payment method'); ?>:</strong> <?php echo $paymentMode; ?>
            </p>
        </div>
    
        <hr>
    
        <!-- STAY DETAILS -->
        <h3 style="font-size:20px;font-weight:700;margin-bottom:15px;"><?php pll_e('Your stay'); ?></h3>
    
        <div style="display:flex;justify-content:space-between;">
            <span><?php pll_e('Check-in'); ?>:</span>
            <strong><?php echo date("D, M j, Y", strtotime($check_in)); ?></strong>
        </div>
    
        <div style="display:flex;justify-content:space-between;">
            <span><?php pll_e('Check-out'); ?>:</span>
            <strong><?php echo date("D, M j, Y", strtotime($check_out)); ?></strong>
        </div>
    
        <div style="display:flex;justify-content:space-between;">
            <span><?php pll_e('Total nights'); ?>:</span>
            <strong><?php echo $nights; ?></strong>
        </div>
    
        <hr>
    
        <!-- CUSTOMER INFO -->
        <h3 style="font-size:20px;font-weight:700;margin-bottom:15px;"><?php pll_e('Guest information'); ?></h3>
    
        <p><strong><?php pll_e('Name'); ?>:</strong> <?php echo esc_html($customerName); ?></p>
        <p><strong><?php pll_e('Email'); ?>:</strong> <?php echo esc_html($customerEmail); ?></p>
        <p><strong><?php pll_e('Mobile'); ?>:</strong> <?php echo esc_html($customerPhone); ?></p>
    
        <hr>
    
        <!-- ROOMS -->
        <h3 style="font-size:20px;font-weight:700;margin-bottom:15px;"><?php pll_e('Your rooms'); ?></h3>
    
        <?php foreach ($bookings as $index => $b): ?>
            <div style="padding:15px 0;border-bottom:1px solid #eee;">
                <div style="font-size:16px;font-weight:600;">
                    <?php pll_e('Unit'); ?> <?php echo $index+1; ?> â€”
                    <?php pll_e('Room'); ?> <?php echo $b["roomId"]; ?>  
                    (<?php pll_e('Unit ID'); ?>: <?php echo $b["unitId"]; ?>)
                </div>
    
                <div style="font-size:14px;margin-top:5px;">
                    <?php pll_e('Adults'); ?>: <?php echo $b["numAdult"]; ?> Â· 
                    <?php pll_e('Children'); ?>: <?php echo $b["numChild"]; ?>
                </div>
    
                <div style="font-size:14px;color:#777;margin-top:5px;">
                    <?php pll_e('Booked on'); ?>: 
                    <?php echo date("D, M j, Y â€” H:i", strtotime($b["bookingTime"])); ?>
                </div>
    
                <?php if (!empty($b["comments"])): ?>
                    <div style="font-size:14px;margin-top:8px;">
                        <strong><?php pll_e('Guest comments'); ?>:</strong>
                        <?php echo esc_html($b["comments"]); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    
        <hr>
    
        <!-- PAYMENT SUMMARY -->
        <h3 style="font-size:20px;font-weight:700;margin-bottom:15px;"><?php pll_e('Payment summary'); ?></h3>
    
        <div style="display:flex;justify-content:space-between;">
            <span><?php pll_e('Total paid'); ?>:</span>
            <strong style="font-size:18px;">€<?php echo number_format($totalPaid, 2); ?></strong>
            <?php if ($isFlexible): ?>
                <p style="font-size:14px;color:#c00;margin-top:6px;">
                    Payment will be charged later according to the flexible rate policy.
                </p>
            <?php endif; ?>
        </div>
    
        <p style="font-size:13px;color:#777;margin-top:10px;">
            <?php pll_e('Your payment was processed securely. A confirmation email has been sent.'); ?>
        </p>
    
        <div style="text-align:center;margin-top:20px;">
            <a href="<?php echo home_url(); ?>" 
               style="background:#3d3732;color:#fff;padding:12px 24px;border-radius:6px;font-size:16px;text-decoration:none;">
                <?php pll_e('Return to homepage'); ?>
            </a>
        </div>
    </div>
</div>
<?php get_footer(); ?>
