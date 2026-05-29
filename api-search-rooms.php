<?php
global $post;
$dates = custom_get_booking_dates();
$arrive = $dates['arrive'];
$depart = $dates['depart'];

$featureMap = [];
$csvFile = get_stylesheet_directory() . '/Beds24_Feature_Code_Mapping-Sheet1.csv';
$combined_prices = get_combined_room_prices();
$upload_dir = wp_upload_dir();
$json_dir   = $upload_dir['basedir'] . '/beds24-availability/';

// Load listing.json (room availability)
$minstay_json = [];

foreach (glob($json_dir . "room-*.json") as $file) {
    $data = json_decode(file_get_contents($file), true);

    // Merge all dates into one map: YYYYMMDD → min_stay, lowest_price
    foreach ($data as $date => $row) {
        $minstay_json[$date] = [
            'min_stay'     => intval($row['min_stay'] ?? 1),
            'lowest_price' => floatval($row['lowest_price'] ?? 0),
        ];
    }
}


if (file_exists($csvFile)) {
    $rows = array_map('str_getcsv', file($csvFile));

    foreach ($rows as $index => $row) {
        if ($index === 0) continue; // skip header row
        if (!isset($row[0]) || !isset($row[1])) continue;

        $code = trim($row[0]);
        $name = trim($row[1]);

        if ($code !== "") {
            $featureMap[$code] = $name;
        }
    }
}

$listing_id = get_post_meta($post->ID,'listing_beds24_id',true);
$current_lang = 'es';
// Get Polylang language if available
if (function_exists('pll_current_language')) {
    $lang = pll_current_language('slug');
    if (!empty($lang)) {
        $current_lang = $lang;
    }
}
// Final query string
$includeLanguages = '&includeLanguages=' . $current_lang;
if($listing_id){
    $url = 'https://beds24.com/api/v2/properties?id='.$listing_id.'&includeTexts=all&includePictures=true&includeOffers=true&includePriceRules=true&includeSearchCriteria=true&includeAllRooms=true&includeUnitDetails=true'.$includeLanguages;
}else{
    $url = 'https://beds24.com/api/v2/properties?includeTexts=all&includePictures=true&includeOffers=true&includePriceRules=true&includeSearchCriteria=true&includeAllRooms=true&includeUnitDetails=true'.$includeLanguages;
}
//echo $url;
$cacheDir = WP_CONTENT_DIR.'/uploads/beds24-cache/';
if(!file_exists($cacheDir)){mkdir($cacheDir,0755,true);}
$listingsCacheFile = $cacheDir.'listings_data.json';
$offersCacheFile = $cacheDir.'room_offers.json';

if(isset($_GET['test']) && $_GET['test']==1 && file_exists($listingsCacheFile)){
    $listings_data = json_decode(file_get_contents($listingsCacheFile),true);
}else{
    $listings_data = getCurlResponse($url);
    file_put_contents($listingsCacheFile,json_encode($listings_data,JSON_PRETTY_PRINT));
}
//echo "<pre>";print_r($listings_data);exit();
$adults=2;$children=0;
if(isset($_GET['adults']) && $_GET['adults']>=1){
    $adults = $_GET['adults'];
}
if(isset($_GET['children']) && $_GET['children']>=1){
    $children = $_GET['children'];
}
$offersUrl = "https://beds24.com/api/v2/inventory/rooms/offers/?arrival=".$arrive."&departure=".$depart."&numAdults=$adults&numChildren=$children";

if(isset($_GET['test']) && $_GET['test']==1 && file_exists($offersCacheFile)){
    $roomOffers = json_decode(file_get_contents($offersCacheFile),true);
}else{
    $roomOffers = getCurlResponse($offersUrl);
    file_put_contents($offersCacheFile,json_encode($roomOffers,JSON_PRETTY_PRINT));
}

$offersIndex = [];
foreach($roomOffers as $item){
    $roomId = isset($item['roomId']) ? $item['roomId'] : '';
    $offersIndex[$roomId] = isset($item['offers']) ? $item['offers'] : [];
}
//echo "<pre>";print_r($roomOffers);exit();
$roomTypes = isset($listings_data[0]['roomTypes']) ? $listings_data[0]['roomTypes'] : [];
$listing_json = [];
$listing_id = $listings_data[0]['id'];
if (file_exists($json_dir . $listing_id.'.json')) {
    $listing_json = json_decode(file_get_contents($json_dir . $listing_id.'.json'), true);
}
$offer_exist = false;
?>
<style>
.no-avail-message{
    background: #eef4fb;
    border: 1px solid #d5e1f2;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    width: 800px;
    margin: 0 auto;
    margin-bottom: 30px;
}
.no-offer-msg{
    padding:12px;
    font-size:15px;
    margin-top:10px;
    background:#fff8e6;
    border:1px solid #ffe4b3;
    border-radius:6px;
    color:#9a6b00;
}
.calendar-container{
    width: 100%;
    overflow: scroll;
}
.room-calendar {
    margin-top: 10px;
    padding: 10px 0;
}
.cal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.cal-nav {
    cursor: pointer;
    font-size: 20px;
    user-select: none;
    color: #222;
}

.cal-nav:hover {
    opacity: 0.6;
}

.cal-title {
    font-weight: 600;
    font-size: 15px;
}

.cal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 700;
    margin-bottom: 8px;
    font-size: 16px;
}

.cal-nav {
    text-decoration: none;
    font-size: 22px;
    padding: 4px 10px;
    color: #0066ff;
}

.cal-wrapper {
    display: flex;
    gap: 30px;
}

.cal-month-title {
    font-weight: 700;
    text-align: center;
    margin-bottom: 6px;
}

.cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 35px);
    gap: 4px;
    font-size: 11px;
    text-align: center;
}

.cal-day {
    padding: 6px 0;
    border-radius: 4px;
}

.cal-green { background: #22c55e; color:#fff; }
.cal-red   { background: #fda4a4; color:#fff; }
.cal-today { background: #2563eb; color:#fff; }

.cal-empty { visibility:hidden; }

.offer-badge-colored div {
    background: none !important;
    font-size: 16px !important;
    border-left: 0px solid #fff !important;
    padding: 4px 0px !important;
}
/* Grid + Card layout */
.container-rooms{margin:0 auto;}
.rooms-grid{
	display:grid;
	grid-template-columns:repeat(3,1fr);
	gap:20px;
	align-items:start;
}
.rooms-grid.few-items {
    display: flex !important;
    justify-content: center !important;
    gap: 20px;
    flex-wrap: wrap;
}

.rooms-grid.few-items .room-card {
    width: 415px; /* adjusts card width — you can change it */
}
/* Card */
.room-card{
	background:#fff;
	border:1px solid #ddd;
	border-radius:6px;
	overflow:hidden;
	display:flex;
	flex-direction:column;
	box-shadow:0 1px 0 rgba(0,0,0,0.02);
}

/* image */
.room-card .card-image{
	position:relative;
	width:100%;
	height:200px;
	overflow:hidden;
}
.room-card .card-image img{
	width:100%;
	height:100%;
	object-fit:cover;
	transition:transform .25s ease;
	display:block;
}
.room-card .card-image:hover img{ transform:scale(1.02); }

/* photo count badge */
.photo-count{
	position:absolute;
	bottom:10px;
	right:10px;
	background:rgba(0,0,0,0.6);
	color:#fff;
	padding:6px 8px;
	border-radius:4px;
	font-size:13px;
	display:inline-flex;
	align-items:center;
	gap:6px;
}

/* top info row under image */
.card-topinfo{
	display:flex;
	justify-content:space-between;
	align-items:center;
	padding:10px 14px;
	border-bottom:1px solid #ddd;
	font-size:13px;
	color:#555;
}
.card-topinfo .left-icons{display:flex;gap:12px;align-items:center}
.card-topinfo .left-icons span{display:inline-flex;gap:6px;align-items:center}
.badge-alert{color:#e33;padding-left:8px;font-weight:700}

/* content body */
.card-body{padding:14px 14px 8px 14px;flex:1;display:flex;flex-direction:column}
.room-title{font-size:18px;margin:0 0 8px 0;font-weight:700}
.room-features{font-size:13px;color:#666;line-height: normal;margin-left:0;margin-bottom: 0;}
.room-features li{margin:6px 0;list-style:none;display:flex;align-items:flex-start;gap:0px}
.room-features li:before{content:"\2713";color:#2a9d4a;margin-right:6px;font-weight:700}

/* offers section */
.card-offers{margin-top:6px;border-top:1px solid #f4f4f4;padding-top:10px}
.offer-row{justify-content:space-between;align-items:flex-start;padding:10px 0;border-bottom:1px solid #f6f6f6}
.offer-left{font-weight:700;color:#333}
.offer-meta{font-size:13px;color:#6b6b6b}
.offer-price{font-weight:700;color:#1f1f1f}

/* footer: pricing + book */
.card-footer{
	padding:12px 14px;
	border-top:1px solid #ddd;
	display:flex;
	justify-content:space-between;
	gap:12px;
}
.direct-benefit{
	background:#f5f5f5;
	padding:8px 10px;
	border-radius:4px;
	font-size:13px;color:#444;
}
.card-price{font-weight:800;font-size:18px}
.card-book-btn{
    margin: 8px;
	background:#FF8A3D;color:#fff;border:0;padding:8px 12px;border-radius:6px;cursor:pointer;
}

/* small helper */
.small-muted{font-size:12px;color:#8b8b8b;margin-bottom: 5px;}
.static-note{font-size:12px;color:#8b8b8b;margin-top:6px}

/* responsive */
@media(max-width:1100px){
	.rooms-grid{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:700px){
	.rooms-grid{grid-template-columns:1fr}
	.room-card .card-image{height:220px}
	.card-topinfo{flex-direction:column;align-items:flex-start;gap:8px}
}
/* Offer section improved layout */
.enhanced-offer {
    margin-top: 10px;
    padding: 0px 0 10px 0px;
    border-bottom: 3px solid #f2f2f2;
}
.enhanced-offer:nth-of-type(2) {
    margin-top: 10px;
}
.offer-headline {
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.offer-radio-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
}

.offer-radio {
    transform: scale(1.2);
    cursor: pointer;
}

.offer-badge-colored {
    padding: 0px 0px;
    font-weight: 700;
    border-radius: 4px;
    font-size: 14px;
    display: inline-block;
    line-height: 1.3;
}
.offer-headline-right {
    text-align: right;
    min-width: 120px;
}
/* Modern select dropdown styling */
.qty-select,
.offer-right-column select,
.unit-box select {
    appearance: none;
    -webkit-appearance: none;

    background: #ffffff;
    border: 1px solid #d5d5d5;
    border-radius: 6px;

    padding: 8px 36px 8px 12px;
    font-size: 14px;
    color: #333;
    width: 100%;
    cursor: pointer;

    transition: 0.15s ease;
    position: relative;
}

/* Hover effect */
.qty-select:hover,
.offer-right-column select:hover,
.unit-box select:hover {
    border-color: #b0b0b0;
}

/* Focus */
.qty-select:focus,
.offer-right-column select:focus,
.unit-box select:focus {
    outline: none;
    border-color: #5a4038;      /* your theme brown */
    box-shadow: 0 0 0 2px rgba(90, 64, 56, 0.2);
}

/* Custom dropdown arrow */
.qty-select,
.offer-right-column select,
.unit-box select {
    background-image: url("data:image/svg+xml;utf8,<svg fill='%23666' height='18' width='18' viewBox='0 0 20 20' xmlns='http://www.w3.org/2000/svg'><polygon points='5 7 10 12 15 7'/></svg>");
    background-repeat: no-repeat;
    background-position: right 12px center;
}

/* Inside unit dropdown rows */
.unit-box {
    background: #fafafa;
    border: 1px solid #e6e6e6;
    border-radius: 6px;
    padding: 12px;
    margin-top: 10px;

    display: flex;
    gap: 10px;
    align-items: center;
}

.unit-price {
    font-weight: 700;
    color: #333;
    width: 60px;
    width: 100%;
}
.toggle-details-link {
    color: #0d6efd;
    font-size: 14px;
    cursor: pointer;
    text-decoration: none;
}

.offer-details-full {
    margin-top: 10px;
    padding: 10px;
    background: #fafafa;
    border-radius: 6px;
    border: 1px solid #eee;
}
.fixed_book_now{
    backdrop-filter: blur(8px);
    background-color: rgba(246, 246, 246, 0.66);
    position:fixed;bottom:0;left:0;width:100%;padding:12px;text-align:center;z-index:9999
}
.room-card-slider-wrapper {
    position: relative;
    width: 100%;
    height: 200px;
    overflow: hidden;
}

.room-card-slider-track {
    display: flex;
    width: 100%;
    height: 100%;
    transition: transform .35s ease;
}

.room-card-slider-track img {
    width: 100%;
    height: 200px;
    object-fit: cover;
    flex-shrink: 0;
}

.room-card-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    padding: 6px 12px;
    background: rgba(0,0,0,0.45);
    color: white;
    font-size: 24px;
    border-radius: 4px;
    cursor: pointer;
    user-select: none;
}

.room-card-prev { left: 8px; }
.room-card-next { right: 8px; }

</style>

<div class="container-rooms">
    <?php if(count($roomTypes) < 1): ?>
        <div class="no-offer-box" style="margin-bottom:18px;">
            <span style="font-size:26px">📅</span>
            <div>
                <div style="font-weight:700"><?php pll_e('No rooms found'); ?></div>
                <div style="color:#555"><?php pll_e('Please try different dates'); ?></div>
            </div>
        </div>
    <?php endif; ?>

    <div class="rooms-grid">
    <?php 
        foreach($roomTypes as $roomType):
		//echo "<pre>";print_r($roomType);exit();
        $listing_id = $roomType['propertyId'];
        $maxPeople = $roomType['maxPeople'];
        $roomId    = isset($roomType['id']) ? $roomType['id'] : '';
        $upload_dir = wp_upload_dir();
        $json_file  = $upload_dir['basedir'] . '/listing-images/' . $listing_id . '.json';
        $jsonImages = [];
        if (file_exists($json_file)) {
            $decoded = json_decode(file_get_contents($json_file), true);
            //echo "<pre>";print_r($decoded);
            if (!empty($decoded['rooms'][$roomId])) {
                // Extract only URLs (sorted already)
                foreach ($decoded['rooms'][$roomId] as $img) {
                    if (!empty($img['url'])) {
                        $jsonImages[] = $img['url'];
                    }
                }
            }
        }
        $galleryImages = [];
        if (!empty($jsonImages)) {
            // Use JSON images first
            $galleryImages = $jsonImages;
        } else {
            // Fallback to Beds24 API pictures
            $roomPictures = isset($roomType['pictures']) ? $roomType['pictures'] : [];
            foreach ($roomPictures as $p) {
                if (!empty($p['url'])) {
                    $galleryImages[] = $p['url'];
                }
            }
        }
        // ===============================================
        // FINAL FALLBACK IF STILL EMPTY
        // ===============================================
        if (empty($galleryImages)) {
            $galleryImages[] = home_url().'/wp-content/uploads/2025/05/apartamentos-estanques-coloniasantjordi-mallorca-27.jpg';
        }
        // MAIN IMAGE
        $roomImage = $galleryImages[0] ?? 'https://media.xmlcal.com/pic/p0030/1355/01.400.png';
        $roomOffersList = isset($offersIndex[$roomId]) ? $offersIndex[$roomId] : [];
		$englishTexts = [];
        $texts = !empty($roomType['texts']) && is_array($roomType['texts'])
			? $roomType['texts']
			: [];
		foreach($texts as $t){ if(isset($t['language']) && $t['language']=='en'){ $englishTexts = $t; break; } }
		$matchedTexts = [];
		foreach ($texts as $t) {
			if (
				isset($t['language']) &&
				$t['language'] === $current_lang
			) {
				$matchedTexts = $t;
				break;
			}
		}
		$englishTexts = $matchedTexts;
		// Fallback to base language name if nothing found
		$roomDescription = !empty($matchedTexts['roomDescription'])
			? $matchedTexts['roomDescription']
			: '';

        $static_size = isset($roomType['roomSize']) ? $roomType['roomSize'] : 35;
        $nameLower = strtolower($roomType['name']);
        if(strpos($nameLower,'sweet') !== false) $static_size = 26;
        if(strpos($nameLower,'princess') !== false) $static_size = 35;
        if(strpos($nameLower,'gallery') !== false) $static_size = 35;

        $bed_count = 'x2';

        // compute price shown at top (use first offer price if exists else 0)
        $topPrice = 0;
        if(!empty($roomOffersList)){
            // get first offer price as representative (safe guard)
            $topPrice = min(array_column($roomOffersList, 'price'));
        }
    ?>
        <div class="room-card" id="room_<?php echo esc_attr($roomId); ?>">
            <div class="card-image room-image-wrap" data-room="<?php echo esc_attr($roomId); ?>">
                <div class="room-card-slider-wrapper">
                    <div class="room-card-slider-track" id="slider_track_<?php echo $roomId; ?>">
                        <?php foreach ($galleryImages as $img): ?>
                            <img src="<?php echo esc_url($img); ?>">
                        <?php endforeach; ?>
                    </div>
                    <div class="room-card-arrow room-card-prev" data-room="<?php echo $roomId; ?>">&#10094;</div>
                    <div class="room-card-arrow room-card-next" data-room="<?php echo $roomId; ?>">&#10095;</div>
                </div>
                <div class="photo-count"><?php echo count($galleryImages); ?> 📷</div>
            </div>


            <div class="card-topinfo">
                <div class="left-icons">
                    <span><strong><?php echo esc_html($static_size); ?> m²</strong></span>
                    <span> <span style="font-weight:700"><?php echo esc_html($roomType['maxAdult']?:2); ?></span> <span class="small-muted">👤</span></span>
                </div>
            </div>

            <div class="card-body">
				<?php
					$amenities_auxiliary="";
					$roomName = $roomType['name']; // fallback
					// Check translated texts
					if (!empty($roomType['texts']) && is_array($roomType['texts'])) {
						foreach ($roomType['texts'] as $text) {
							if (
								isset($text['language'], $text['displayName']) &&
								$text['language'] === $current_lang &&
								trim($text['displayName']) !== ''
							) {
								$roomName = $text['displayName'];
								$amenities_auxiliary = $text['auxiliary'];
								break;
							}
						}
					}
				?>
				<h3 class="room-title"><?php echo esc_html($roomName); ?></h3>
                <?php
                $features_raw = isset($roomType['featureCodes']) ? $roomType['featureCodes'] : [];
                $features = [];
                foreach ($features_raw as $f) {
                    if (is_array($f)) {
                        if (isset($f[0]) && $f[0] !== '') {
                            $features[] = (string)$f[0];
                        } else {
                            foreach ($f as $v) {
                                if (!empty($v)) { $features[] = (string)$v; break; }
                            }
                        }
                    } else {
                        if ($f !== '') $features[] = (string)$f;
                    }
                }
                $features = array_values(array_unique(array_filter(array_map('trim', $features))));
                $mapped = [];
                foreach ($features as $code) {
                    if (isset($featureMap[$code])) {
                        $mapped[] = $featureMap[$code];
                    } else {
                        $mapped[] = ucwords(str_replace('_',' ', strtolower($code)));
                    }
                }
                $top_features = array_slice($mapped, 0, 3);
                $top_features=[];
                ?>
                <ul class="room-features">
                    <?php foreach ($top_features as $feat): ?>
                        <li><?php echo esc_html($feat); ?></li>
                    <?php endforeach; ?>
                </ul>

                <a href="#" class="more-info-link" data-room="<?php echo esc_attr($roomId); ?>"><?php pll_e('More info'); ?></a>
                <div class="room-calendar-wrap">
                    <svg class="hotel-icon" viewBox="0 0 24 24" style="float: left;margin-top: 5px;margin-right: 5px;">
                        <path fill="currentColor" d="M7 11h2v2H7zm4 0h2v2h-2zm4 0h2v2h-2zM5 21q-.825 0-1.412-.587T3 19V7q0-.825.588-1.412T5 5h1V3h2v2h8V3h2v2h1q.825 0 1.413.588T21 7v12q0 .825-.587 1.413T19 21zM5 19h14V10H5z"></path>
                    </svg>
                    <a href="#" class="toggle-calendar"
                       data-room="<?php echo $roomId; ?>"
                       style="display:block;margin-bottom:8px;font-size: 15px;">
                       <?php echo pll__('Check availability'); ?>
                    </a>
                
                    <div class="calendar-container" id="cal_<?php echo $roomId; ?>" style="display: none;">
                        <div class="calendar-months" id="calendar_content_<?php echo $roomId; ?>">
                            <?php
                            $calMonth = isset($_GET['cal_month']) ? $_GET['cal_month'] : date('Y-m');
                            echo render_room_calendar($roomId, $listing_id, $calMonth);
                            ?>
                        </div>
                    </div>
                </div>
                <?php 
                    $totalGuests = (int)$adults + (int)$children;
                    $maxPeople   = (int)($roomType['maxPeople'] ?? 0);
                    // No offers available for this room
                    if (empty($roomOffersList)) {
						$offer_exist = false;
						// CASE 1 — Guest count exceeds max occupancy
                        if ($totalGuests > $maxPeople && $maxPeople > 0) {
                            echo '<div class="no-offer-msg">
                                '.sprintf(
                                    pll__("Maximum occupancy for this apartment is %d guests"),
                                    intval($maxPeople)
                                ).'
                            </div>';
                        } 
                        // CASE 2 — Offers missing for another reason (likely min night restriction)
                        else {
                            $room_json_file = $json_dir . "room-{$roomId}.json";
                            $room_json = file_exists($room_json_file)
                                ? json_decode(file_get_contents($room_json_file), true)
                                : [];
                                
                            $error_msg = check_room_availability(
                                $arrive,
                                $depart,
                                $listing_json,
                                $room_json,
                                $roomId
                            );
                            
                            if ($error_msg !== true) {
                                echo '<div class="no-offer-msg">'.$error_msg.'</div>';
                            } else {
								echo '<div class="no-offer-msg">'.pll__('Not available').'</div>';
                            }  
                        }
                    }else{$offer_exist = true;}
                ?>
                <!-- Offers area (NO qty, NO units) -->
                <div class="card-offers">
                <?php foreach($roomOffersList as $offer):
                    $oid            = $offer['offerId'];
                    $offerPrice     = $offer['price'];
                    $unitsAvailable = $offer['unitsAvailable'];

                    $offerDescription = $englishTexts['offerDescription'.$oid] 
                                        ?? $offer['offerName'];

                    $offerMarketing  = $englishTexts['offerMarketing'.$oid] 
                                        ?? '';
                    // determine rateType
                    $offerNameLower = strtolower($offerDescription);
                    $rateType = 'standard';
                    if (strpos($offerNameLower,'flex') !== false || strpos($offerNameLower,'flexible') !== false) $rateType = 'flexible';
                    if (strpos($offerNameLower,'saving') !== false || strpos($offerNameLower,'savings') !== false) $rateType = 'savings';
                ?>
                    <div class="offer-row enhanced-offer" data-room="<?php echo esc_attr($roomId); ?>" data-offer="<?php echo esc_attr($oid); ?>">

                        <div class="offer-columns" style="display:flex;justify-content:space-between;gap:12px;align-items:center;">
                    
                            <!-- LEFT -->
                            <div style="flex:1;">
                                <label style="display:flex;align-items:center;gap:12px;cursor:pointer;">
                                    <input type="radio"
                                           name="selected_offer"
                                           class="offer-radio-select"
                                           data-room="<?php echo esc_attr($roomId); ?>"
                                           data-offer="<?php echo esc_attr($oid); ?>"
                                           data-price="<?php echo esc_attr($offerPrice); ?>"
                                           data-ratetype="<?php echo esc_attr($rateType); ?>"
                                           style="transform:scale(1.2);margin-right:6px;">
                                    <span class="offer-badge-colored 
                                        <?php echo (stripos($offerDescription,'flex')!==false ? 'badge-green' : 'badge-orange'); ?>">
										<?php echo $offerDescription; ?>
										<?php
											if(stripos($offerDescription,'flex')!==false){?>
												<p class="small-muted">
													⭐ <?php echo pll__('Includes progressive discounts for longer stays'); ?>
												</p>
											<?php }else{?>
												<p class="small-muted">
													⭐ <?php echo pll__('Lowest price available'); ?>
												</p>
											<?php }
										?>
										
                                    </span>
                                </label>

                                <?php if(!empty($offerMarketing)): ?>
									<div class="offer-details-toggle">
										<a href="#" class="toggle-details-link"
										   data-key="<?php echo esc_attr($roomId.'_'.$oid); ?>"
										   data-room="<?php echo esc_attr($roomId); ?>"
										   data-offer="<?php echo esc_attr($oid); ?>">
										   <?php echo pll__('View details'); ?>
										</a>
									</div>
									<script>
										window.offerPopupData = window.offerPopupData || {};
										window.offerPopupData["<?php echo $roomId.'_'.$oid; ?>"] = {
											roomId: "<?php echo $roomId; ?>",
											offerId: "<?php echo $oid; ?>",
											title: <?php echo json_encode($offerDescription); ?>,
											marketing: <?php echo json_encode($offerMarketing); ?>,
											price: "<?php echo number_format($offerPrice,2); ?>",
											rateType: "<?php echo esc_js($rateType); ?>"
										};
									</script>
                                <?php endif; ?>
                            </div>

                            <!-- RIGHT (kept minimal) -->
                            <div style="min-width:120px;text-align:right;">
                                <div class="offer-meta"><?php echo pll__('Per stay'); ?></div>
                                <div class="offer-price">€<?php echo number_format($offerPrice,2); ?></div>
                            </div>
                    
                        </div>
                    
                    </div>

                <?php endforeach; ?>
                </div>

                <div style="flex:1"></div>
            </div>

            <div class="card-footer">
                <div>
                    <div class="direct-benefit">
                        <?php 
                        $multiplier = get_option('booking_direct_multiplier', 1.15);
                        // Booking.com price
                        $bookingPrice = $topPrice * $multiplier;
                        // Savings
                        $saveAmount = $bookingPrice - $topPrice;
                        if($saveAmount>0){?>
                        <div class="booking-price" style="font-size:14px; color:#666;">
                            Booking.com: €<?php echo number_format($bookingPrice, 2); ?>
                        </div>
                        <div class="save-direct" style="font-size:14px; color:green; font-weight:bold;">
                            <?php echo pll__('Save'); ?> €<?php echo number_format($saveAmount, 2); ?> <?php echo pll__('booking direct'); ?>
                        </div>
                        <?php } ?>
                    </div>
                    <div class="small-muted" style="margin-top:6px"><?php pll_e('Includes taxes. Excludes fees'); ?></div>
                </div>
                <div style="text-align:right">
                    <div class="card-price">
                        <?php echo pll__('From'); ?><br/>€<?php echo number_format($topPrice, 2); ?>
                    </div>
                </div>

            </div>
        <?php
        $page = get_page_by_path('instant-booking');
        if ($page) {
            $path_next = get_permalink(pll_get_post($page->ID));
        }
        ?>
            <!-- keep original per-card book button but it will simply scroll to global book or focus it -->
            <button class="card-book-btn" data-room="<?php echo esc_attr($roomId); ?>"><?php pll_e('Book'); ?></button>
			<script>
            window.roomPopupData = window.roomPopupData || {};
            window.roomPopupData["<?php echo $roomId; ?>"] = {
                id: "<?php echo $roomId; ?>",
                title: <?php echo json_encode($roomName); ?>,
                description: <?php echo json_encode($roomDescription); ?>,
                maxAdult: <?php echo (int)$roomType['maxAdult']; ?>,
                beds: <?php echo json_encode($bed_count); ?>,
                images: <?php echo json_encode($galleryImages); ?>,
                features: <?php echo json_encode($amenities_auxiliary); ?>
            };
            </script>
        </div>

    <?php endforeach; 
        if(!$offer_exist){?>
            <div id="flexible-dates-container"></div>
                <script>
                document.addEventListener("DOMContentLoaded", function() {
                
                    var offerFound = <?php echo $offer_exist ? 'true' : 'false'; ?>;
                
                    if (!offerFound) {
                        loadFlexibleDates();
                    }
                
                    function loadFlexibleDates() {
                		console.log("loadFlexibleDates==>");
                        let combinedPrices = <?php echo json_encode($combined_prices ?? []); ?>;
                        let listing_json  = <?php echo json_encode($listing_json, JSON_UNESCAPED_UNICODE); ?>;
                        let min_stay_json = <?php echo json_encode($minstay_json, JSON_UNESCAPED_UNICODE); ?>;
                        let currentPageUrl = window.location.href.split('#')[0];
                
                        let formData = new FormData();
                        formData.append("action", "load_flexible_dates");
                        formData.append("arrive", "<?php echo $arrive; ?>");
                        formData.append("depart", "<?php echo $depart; ?>");
                        formData.append("combined_prices", JSON.stringify(combinedPrices));
                        formData.append("listing", JSON.stringify(listing_json));
                        formData.append("minstay", JSON.stringify(min_stay_json));
                        formData.append("page_url", currentPageUrl);
                        
                        fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
                            method: "POST",
                            body: formData
                        })
                        .then(res => res.json())
                        .then(res => {
                            if (res.success) {
                                document.getElementById("flexible-dates-container").innerHTML = res.data.html;
                            }
                        });
                    }
                });
                </script>
        <?php }
    ?>
    </div>

    <input type="hidden" name="room_id" id="room_id" value="<?php echo isset($roomTypes[0]['id']) ? esc_attr($roomTypes[0]['id']) : ''; ?>">

</div>

<script>
jQuery(document).ready(function($){
    // Show bottom bar if at least one offer exists
    if ($(".offer-radio-select").length > 0) {
        $(".fixed_book_now").show();
    }

    // per-card Book button scrolls to bottom & focuses global book
    $(".card-book-btn").on("click", function(){
        $('html, body').animate({ scrollTop: $(document).height() }, 250, function(){
            $("#bookNowBtn").focus();
        });
        gatherAndGo();
    });

    // Toggle details (keeps your previous behaviour)
    jQuery(document).ready(function($){
		$(document).on("click", ".toggle-details-link", function(e){
			e.preventDefault();
			let key  = $(this).data("key");
			let data = window.offerPopupData[key];
			console.log(data);
			if (!data) return;
			$(".offer-popup-title").text(data.title);
			$(".offer-popup-text").html(data.marketing);
			$(".offer-popup-price").text(data.price);
			console.log(data.title);
			console.log(data.marketing); 
			console.log(data.price);
			let badge =
				data.rateType === "flexible"
				? "⭐ <?php echo pll__('Flexible rate'); ?>"
				: "💰 <?php echo pll__('Lowest price'); ?>";
			$(".offer-popup-badge").text(badge);

			$("#offerInfoModal").fadeIn(200);
			$("body").css("overflow","hidden");
		});

		$("#offerInfoModal, #offerInfoModal .room-info-close").on("click", function(e){
			if(e.target.id === "offerInfoModal" || $(e.target).hasClass("room-info-close")){
				$("#offerInfoModal").fadeOut(200);
				$("body").css("overflow","auto");
			}
		});
	});
    // Global Book Now handler (collect single selected offer + guest counts)
    function gatherAndGo(){
        var selected = $("input[name='selected_offer']:checked");
        if (!selected.length){
            alert("<?php echo addslashes(pll__('Please select one offer to book.')); ?>");
            return;
        }

        // read attributes
        var roomId = selected.data('room');
        var offerId = selected.data('offer');
        var price = selected.data('price') || 0;
        var rateType = selected.data('ratetype') || 'standard';

        // guests (from search top hidden inputs)
        var adult_guest = parseInt($("input[name='adults']").val()) || 0;
        var child_guest = parseInt($("input[name='children']").val()) || 0;
        var guest = adult_guest + child_guest;

        // dates
        var checkIn = $("input[name='arrive']").val();
        var checkOut = $("input[name='depart']").val();

        // listing id from PHP
        var listingId = "<?php echo esc_js($listing_id); ?>";
        // Build url (exact parameter names you approved)
        var url = "<?php echo $path_next; ?>?check_in="+encodeURIComponent(checkIn)
            +"&check_out="+encodeURIComponent(checkOut)
            +"&room_id="+encodeURIComponent(roomId)
            +"&offer_id="+encodeURIComponent(offerId)
            +"&adult_guest="+encodeURIComponent(adult_guest)
            +"&child_guest="+encodeURIComponent(child_guest)
            +"&guest="+encodeURIComponent(guest)
            +"&listing_id="+encodeURIComponent(listingId)
            +"&price="+encodeURIComponent(price)
            +"&rateType="+encodeURIComponent(rateType);

        // redirect
        window.location.href = url;
    }

    $("#bookNowBtn").on("click", function(){
        gatherAndGo();
    });

    // helper: when user selects a radio, ensure only that is checked (browser already does it)
    $(document).on('change', "input[name='selected_offer']", function(){
        // no special action required but you can highlight selection etc.
        // e.g. add class to parent for visual clarity
        $(".offer-row").removeClass("selected-offer");
        $(this).closest(".offer-row").addClass("selected-offer");
    });
});
</script>

<!-- keep lightbox and modal code unchanged (copied as-is from yours) -->
<!-- ... lightbox markup & roomInfoModal code unchanged ... -->
<!-- Lightbox markup (kept identical to your existing code) -->
<style>
#lightboxModal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:999999;justify-content:center;align-items:center;}
.lightbox-content{position:relative;max-width:90%;max-height:90%;}
#lightboxImage{max-width:90vw;max-height:90vh;object-fit:contain;border-radius:8px;box-shadow:0 0 15px rgba(0,0,0,0.6);transition:opacity .25s ease;}
.lightbox-close{position:absolute;top:-45px;right:0;font-size:32px;color:#fff;cursor:pointer;}
.lightbox-arrow{position:absolute;top:50%;transform:translateY(-50%);font-size:50px;color:white;cursor:pointer;user-select:none;padding:10px 20px;opacity:.8;transition:.2s;}
.lightbox-prev{ left:-70px; }
.lightbox-next{ right:-70px; }
@media(max-width:700px){ .lightbox-prev{ left:10px; } .lightbox-next{ right:10px; } }
</style>
<div id="lightboxModal">
	<div class="lightbox-content">
		<span class="lightbox-close">&times;</span>
		<img id="lightboxImage" src="">
		<div class="lightbox-arrow lightbox-prev">&#10094;</div>
		<div class="lightbox-arrow lightbox-next">&#10095;</div>
	</div>
</div>
<!-- ============================
     ROOM INFO MODAL (STATIC)
=============================== -->
<style>
    /* Modal background */
.room-info-modal {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.65);
    z-index: 99999999;
    justify-content: center;
    align-items: flex-start;
    overflow-y: auto;
    padding: 40px 0;
}

/* Modal container */
.room-info-content {
    background: #fff;
    width: 90%;
    max-width: 900px;
    border-radius: 8px;
    padding: 2px 0px 40px 0px;
    position: relative;
    margin: 0 auto;
    height: 700px;
    overflow-y: scroll;
    /* Hide scrollbar */
    scrollbar-width: none;        /* Firefox */
}
.room-info-content::-webkit-scrollbar {
    display: none;                /* Chrome, Safari */
}
/* Close button */
.room-info-close {
    position: absolute;
    top: 5px;
    right: 15px;
    font-size: 30px;
    cursor: pointer;
}

/* Title */
.room-info-title {
    text-align: center;
    margin-top: 10px;
    margin-bottom: 20px;
}

/* Banner */
.room-info-banner {
    position: relative;
    width: 100%;
    height: 320px;
    overflow: hidden;
}
.room-info-banner img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.room-info-photo-count {
    position: absolute;
    bottom: 10px;
    right: 12px;
    background: rgba(0,0,0,0.6);
    color: #fff;
    padding: 6px 10px;
    font-size: 12px;
    border-radius: 4px;
}

/* Section */
.room-info-section {
    border-top: 1px solid #eee;
    padding-top: 20px;
    margin-top: 20px;
    padding-left: 20px;
    padding-right: 20px;
}
.room-info-section h3 {
    margin-bottom: 10px;
    font-size: 24px;
}

/* Amenities grid */
.amenities-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}
.amen-item {
    text-align: center;
}
.amen-icon {
    font-size: 26px;
    margin-bottom: 8px;
}

/* More services 2-column layout */
.more-services-grid {
    display: grid;
    grid-template-columns: repeat(2,1fr);
    gap: 20px;
}
.more-services-grid ul {
    margin: 0;
    padding-left: 20px;
}
.more-services-grid li {
    margin-bottom: 6px;
}
@media (max-width: 600px) {
    .no-avail-message{width: 100%;margin-bottom: 20px;}
    .amenities-grid {
        grid-template-columns: repeat(2, 1fr) !important;
    }
    .more-services-grid {
        grid-template-columns: 1fr !important;
    }
}
.room-info-banner {
    width: 100%;
    height: 320px;
    position: relative;
    overflow: hidden;
}

.banner-slider-wrapper {
    width: 100%;
    height: 100%;
    position: relative;
}

.banner-slide-track {
    display: flex;
    width: 100%;
    height: 100%;
    transition: transform .4s ease;
}

.banner-slide-track img {
    width: 100%;
    height: 320px;
    object-fit: cover;
    flex-shrink: 0;
}

.banner-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    font-size: 38px;
    color: white;
    cursor: pointer;
    padding: 10px;
    opacity: .8;
    user-select: none;
}

.banner-prev { left: 10px; }
.banner-next { right: 10px; }

.room-info-photo-count {
    position: absolute;
    bottom: 12px;
    right: 12px;
    background: rgba(0,0,0,0.6);
    padding: 6px 10px;
    font-size: 13px;
    color: #fff;
    border-radius: 4px;
}
</style>
<div id="offerInfoModal" class="room-info-modal">
    <div class="room-info-content" style="max-width:600px;height:auto">

        <span class="room-info-close">&times;</span>

        <h2 class="room-info-title"></h2>

        <div class="room-info-section">
            <p class="offer-popup-text"></p>
        </div>

        <div class="room-info-section">
            <strong><?php pll_e('Price'); ?>:</strong>
            €<span class="offer-popup-price"></span>
        </div>

        <div class="room-info-section">
            <span class="offer-popup-badge"></span>
        </div>

    </div>
</div>
<div id="roomInfoModal" class="room-info-modal">
    <div class="room-info-content">

        <!-- Close button -->
        <span class="room-info-close">&times;</span>

        <!-- Header Title -->
        <h2 class="room-info-title"></h2>

        <!-- Banner Slider -->
        <div class="room-info-banner">
            <div class="banner-slider-wrapper">
                <div class="banner-slide-track"></div>
        
                <div class="banner-nav banner-prev">&#10094;</div>
                <div class="banner-nav banner-next">&#10095;</div>
        
                <div class="room-info-photo-count"><span class="slide-current">1</span> / <span class="slide-total">1</span></div>
            </div>
        </div>

        <!-- Description -->
        <div class="room-info-section">
            <h3><?php pll_e('Description'); ?></h3>
        </div>
        <!-- Selected occupation -->
        <div class="room-info-section occupation_adults">
            <h3><?php pll_e('Selected occupation'); ?></h3>
            <p>2 <?php pll_e("Adults");?></p>
        </div>

        <!-- Bed arrangements -->
        <div class="room-info-section beds_arrangements">
            <h3><?php pll_e('Possible bed arrangements'); ?></h3>
            <p>🛏 x2</p>
        </div>

        <!-- Top amenities -->
        <div class="room-info-section">
            <h3><?php pll_e('Top amenities'); ?></h3>

            <div class="amenities-grid">
                <div class="amen-item">
                    <div class="amen-icon">🕒</div>
                    <div><?php pll_e('24h room service'); ?></div>
                </div>
                <div class="amen-item">
                    <div class="amen-icon">🌿</div>
                    <div><?php pll_e('Eco-friendly'); ?></div>
                </div>

                <div class="amen-item">
                    <div class="amen-icon">🔒</div>
                    <div><?php pll_e('Free safe'); ?></div>
                </div>

                <div class="amen-item">
                    <div class="amen-icon">🛏</div>
                    <div><?php pll_e('Mattress'); ?></div>
                </div>
            </div>
        </div>

        <!-- More services -->
        <div class="room-info-section">
            <h3><?php pll_e('More services'); ?></h3>

            <div class="more-services-grid">
                <ul>
                </ul>

                <ul>
                </ul>
            </div>
        </div>

    </div>
</div>
<script>
jQuery(document).ready(function($){
    let count = $(".rooms-grid .room-card").length;
    if(count <= 2){
        $(".rooms-grid").addClass("few-items");
    }
});
jQuery(document).ready(function($){
    // OPEN MODAL
    $(".more-info-link").on("click", function(e){
        e.preventDefault();
    
        let roomId = $(this).data("room");
        let data   = window.roomPopupData[roomId];
    
        if (!data) return;
    
        /** 1. Title */
        $(".room-info-title").text(data.title);
    
        /** 2. Banner Image */
        $(".room-info-banner img").attr("src", data.images[0]);
        $(".room-info-photo-count").text(data.images.length + " <?php pll_e("Photos");?>");
    
        /** 3. Description */
        $(".room-info-section:eq(1) p").html(data.description);
    
        /** 4. Selected occupation */
        $(".room-info-section.occupation_adults p").text(data.maxAdult + " <?php pll_e("Adults");?>");
    
        /** 5. Bed arrangements */
        $(".room-info-section.beds_arrangements p").text("🛏 " + data.beds);
    
        /** 6. Top Amenities (first 4) */
		//console.log(data.features);
		// 1. Remove HTML tags and convert to array
		let cleanFeatures = data.features
			.replace(/<[^>]*>/g, '')   // remove HTML tags
			.split(',')                // split by comma
			.map(f => f.trim())        // trim spaces
			.filter(f => f.length);    // remove empty values

		// overwrite data.features with clean array
		//data.features = cleanFeatures;

        let top = cleanFeatures.slice(0,4);
		//console.log("44444444");
        let topHTML = "";
        top.forEach(f => {
            topHTML += `
                <div class="amen-item">
                    <div class="amen-icon">✔️</div>
                    <div>${f}</div>
                </div>`;
        });
		//console.log(topHTML);
        $(".room-info-section .amenities-grid").html(topHTML);
    
        /** 7. Remaining Amenities → More Services */
        let more = cleanFeatures.slice(4);
        let left = "";
        let right = "";
    
        more.forEach((f, index) => {
            if (index % 2 === 0) left += `<li>${f}</li>`;
            else right += `<li>${f}</li>`;
        });
    
        $(".more-services-grid ul:eq(0)").html(left);
        $(".more-services-grid ul:eq(1)").html(right);
    
        /** Show popup */
        $("#roomInfoModal").fadeIn(200);
        $("body").css("overflow","hidden");
        let images = data.images || [];
        let track = $(".banner-slide-track");
        track.html(""); // clear previous slides
        
        images.forEach(url => {
            track.append(`<img src="${url}">`);
        });
        
        // Update total count
        $(".slide-total").text(images.length);
        
        // Slider state
        let currentIndex = 0;
        
        // Update slide position
        function updateSlide() {
            track.css("transform", "translateX(-" + (currentIndex * 100) + "%)");
            $(".slide-current").text(currentIndex + 1);
        }
        
        updateSlide();
        
        /** Navigation arrows **/
        $(".banner-prev").off().on("click", function () {
            currentIndex = (currentIndex === 0) ? images.length - 1 : currentIndex - 1;
            updateSlide();
        });
        
        $(".banner-next").off().on("click", function () {
            currentIndex = (currentIndex === images.length - 1) ? 0 : currentIndex + 1;
            updateSlide();
        });
        
        /** Mobile swipe support **/
        let startX = 0;
        $(".banner-slide-track").on("touchstart", function (e) {
            startX = e.originalEvent.touches[0].clientX;
        });
        $(".banner-slide-track").on("touchend", function (e) {
            let endX = e.originalEvent.changedTouches[0].clientX;
            if (endX - startX > 40) {
                $(".banner-prev").click();
            } else if (startX - endX > 40) {
                $(".banner-next").click();
            }
        });
    });


    // CLOSE MODAL
    $(".room-info-close, #roomInfoModal").on("click", function(e){
        if(e.target.id === "roomInfoModal" || $(e.target).hasClass("room-info-close")){
            $("#roomInfoModal").fadeOut(200);
            $("body").css("overflow","auto");
        }
    });
});
document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("roomInfoModal");
    const content = document.querySelector(".room-info-content");
    const closeBtn = document.querySelector(".room-info-close");

    // Close on X click
    if (closeBtn) {
        closeBtn.addEventListener("click", function () {
            modal.style.display = "none";
            document.body.style.overflow = "auto";
        });
    }

    // Close when clicking OUTSIDE content box
    modal.addEventListener("click", function (e) {
        if (e.target === modal) {
            modal.style.display = "none";
            document.body.style.overflow = "auto";
        }
    });

    // Prevent clicks INSIDE box from closing popup
    content.addEventListener("click", function (e) {
        e.stopPropagation();
    });
});
jQuery(document).ready(function($){

    $(document).on("click", ".toggle-calendar", function(e){
        e.preventDefault();

        let roomId = $(this).data("room");
        let wrap   = $("#cal_" + roomId);

        if (wrap.is(":visible")) {
            wrap.slideUp(150);
            $(this).text("<?php echo pll__('Check availability'); ?>");
        } else {
            wrap.slideDown(150);
            $(this).text("✖ <?php echo pll__('less details'); ?>");
        }
    });

});
jQuery(document).ready(function($){

    $(".room-card").each(function(){

        let roomId = $(this).attr("id").replace("room_", "");
        let track  = $("#slider_track_" + roomId);
        let imgs   = track.find("img").length;
        let index  = 0;

        function updateCardSlide() {
            track.css("transform", "translateX(-" + (index * 100) + "%)");
        }

        $(".room-card-next[data-room='" + roomId + "']").on("click", function(){
            index = (index + 1) % imgs;
            updateCardSlide();
        });

        $(".room-card-prev[data-room='" + roomId + "']").on("click", function(){
            index = (index === 0 ? imgs - 1 : index - 1);
            updateCardSlide();
        });

    });

});

</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const wrapper = document.querySelector(".rooms-grid");
    if (!wrapper) return;

    const cards = Array.from(wrapper.querySelectorAll(".room-card"));

    const withOffer = [];
    const noOffer = [];

    cards.forEach(card => {
        if (card.querySelector(".no-offer-msg")) {
            noOffer.push(card);
        } else {
            withOffer.push(card);
        }
    });

    // Re-append in desired order
    [...withOffer, ...noOffer].forEach(card => {
        wrapper.appendChild(card);
    });
});
</script>
