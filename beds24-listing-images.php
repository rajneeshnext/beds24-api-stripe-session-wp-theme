<?php 
/* Template Name: Add/Update All Listings IMAGES */
ini_set('max_execution_time', 600);
ini_set('memory_limit', '2048M');    // Increase memory limit
ini_set('max_input_time', 600);     // Increase input time limit
global $current_user;
$from = date("Ymd");
$to=date("Ymd",strtotime("+1 days"));
$next=date("Ymd",strtotime("+360 days"));

if(isset($_GET['run_availability'])){
generate_beds24_availability_json_single(119628);
exit;
}

if(isset($_GET['run_rate_269520'])){
getCurlResponseV1Rates(119628,269520,$to,$next);
exit;
}

if(isset($_GET['run_rate_269521'])){
getCurlResponseV1Rates(119628,269521,$to,$next);
exit;
}

exit();

$flag = 1;
if( !empty($current_user->roles) ){
	foreach ($current_user->roles as $key => $value) {
		if( $value == 'administrator' ){
			$flag = 0;
		}
	}
}
echo $listings_id=119628;
require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
$getPropKeyArray = getPropKey();
//echo "<pre>";print_r($getPropKeyArray);
foreach($getPropKeyArray->getProperties as $properties){
//	print_r($properties);
	$prop_arr[$properties->propId] = $properties->propKey;
}
//print_r($prop_arr);
echo $listing_prop_key = $prop_arr[$listings_id];
if($listings_id > 0) {
    // Start output buffering when needed
    $update_all = !isset($_GET['update_all']) || $_GET['update_all'] == 'true';
    $listing_images = getCurlResponseV1Images($listing_prop_key);

    $hosted_images   = $listing_images->hosted ?? [];
    $external_images = $listing_images->external ?? [];
    
    $final = [
        "propId" => $listings_id,
        "rooms"  => []
    ];
    
    // Helper to add an image into correct room
    function addImageData(&$final, $imgObj) {
        if (empty($imgObj->url) || empty($imgObj->map)) return;
    
        foreach ($imgObj->map as $m) {
            if (empty($m->roomId)) continue;
    
            $roomId   = $m->roomId;
            $position = $m->position ?? 0;
    
            if (!isset($final["rooms"][$roomId])) {
                $final["rooms"][$roomId] = [];
            }
    
            $final["rooms"][$roomId][] = [
                "url"      => $imgObj->url,
                "position" => intval($position)
            ];
        }
    }
    
    // Process Hosted
    foreach ($hosted_images as $img) {
        addImageData($final, $img);
    }
    
    // Process External (if any valid)
    foreach ($external_images as $img) {
        addImageData($final, $img);
    }
    
    // Sort images inside each room by position ascending
    foreach ($final["rooms"] as $roomId => &$imgList) {
        usort($imgList, function($a, $b){
            return $a["position"] <=> $b["position"];
        });
    }
    
    // Save JSON
    $upload_dir = wp_upload_dir();
    $json_dir   = $upload_dir['basedir'] . '/listing-images';
    
    if (!file_exists($json_dir)) {
        wp_mkdir_p($json_dir);
    }
    
    $json_file = $json_dir . "/{$listings_id}.json";
    
    file_put_contents($json_file, json_encode($final, JSON_PRETTY_PRINT));
    
    // Output summary
    echo "<strong>JSON saved:</strong> {$upload_dir['baseurl']}/listing-images/{$listings_id}.json<br>";
    echo "Rooms found: " . count($final["rooms"]) . "<br>";
    exit();

}

exit();

?>