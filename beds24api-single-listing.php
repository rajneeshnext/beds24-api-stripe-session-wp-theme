<?php 
/* Template Name: Add/Update Beds24 to Single Listing Template */
$target_listing_id = null;
if ( isset($_GET['listings_id']) && !empty($_GET['listings_id']) ) {
    $target_listing_id = intval($_GET['listings_id']);
    add_update_listings_with_beds24($target_listing_id);
}

?>