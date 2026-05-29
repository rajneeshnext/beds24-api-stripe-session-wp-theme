<?php 
/* Template Name: Add/Update Beds24 to All Listings Template */

//every_week_weekly_token_change();
//exit();
// Check for a single 'id' parameter in the URL.
$target_listing_id = null;
if ( isset($_GET['id']) && !empty($_GET['id']) ) {
    $target_listing_id = intval($_GET['id']);
}
//$listing_beds24_id = $listing_id = get_post_meta( 61590, 'listing_beds24_id', true);$target_listing_id=$listing_beds24_id;
add_update_listings_with_beds24($target_listing_id);
// echo "dddd<br/>";
//TestgetBedsListingbyID(241396);

?>