<?php
/*
Plugin Name: Recruit Wizard Add-on for WP Job Manager
Plugin URI: https://recruitwizard.com/
Description: The Recruit Wizard add-on for the WP Job Manager WordPress plugin allows you integrate jobs posted from your Recruit Wizard account to appear to your WordPress website.
Author: Wizardsoft
Version: 2.1.0
*/
/**
*  Activation Class
**/

if (!class_exists('RecruitWizard_InstallCheck')) {
    class RecruitWizard_InstallCheck{
        static function install(){
            $messageerror = '';
			if( !class_exists( 'WP_Job_Manager' ) ) {
				if ( $messageerror == '' ) {
					$messageerror = '<li style="display: inline;"><a target="_blank" href="https://wordpress.org/plugins/wp-job-manager/">WP Job Manager</a></li>';
				}
				else{
					$messageerror = '<li style="display: inline;">&nbsp;|&nbsp;<a target="_blank" href="https://wordpress.org/plugins/wp-job-manager/">WP Job Manager</a></li>';
				}
			}
			if( !class_exists( 'Astoundify_Job_Manager_Regions' ) ) {
				if ( $messageerror == '' ) {
					$messageerror = $messageerror . '<li style="display: inline;"><a target="_blank" href="https://wordpress.org/plugins/wp-job-manager-locations/">Regions for WP Job Manager</a></li>';
				}
				else{
					$messageerror = $messageerror . '<li style="display: inline;">&nbsp;|&nbsp;<a target="_blank" href="https://wordpress.org/plugins/wp-job-manager-locations/">Regions for WP Job Manager</a></li>';
				}
			}

            if ( $messageerror != '' ) {
				// Deactivate the plugin
				deactivate_plugins(__FILE__);

				// Throw an error in the wordpress admin console
				$error_message = '<div class="error fade">The following plugins are missing and are preventing Recruit Wizard add-on for WP job Manager from running:</div><ul>' . $messageerror . '</ul>' ;
				die($error_message);
			}
        }
    }
}
register_activation_hook( __FILE__, array('RecruitWizard_InstallCheck', 'install') );

add_action('rest_api_init', function() {
    register_rest_route('RecruitWizard', 'create-job', array(
        'methods' => \WP_REST_Server::EDITABLE,
        'callback' => __NAMESPACE__.'recruitwizard_create_job_listing',
		'permission_callback' => 'recruitwizard_create_job_listing_permissions_check',
    ));
	register_rest_route('RecruitWizard', 'categories', array(
		'methods' => 'GET',
		'callback' => __NAMESPACE__.'recruitwizard_get_categories',
		'permission_callback' => 'recruitwizard_create_job_listing_permissions_check'
	));
	register_rest_route('RecruitWizard', 'job-types', array(
		'methods' => 'GET',
		'callback' => __NAMESPACE__.'recruitwizard_get_job_types',
		'permission_callback' => 'recruitwizard_create_job_listing_permissions_check'
	));
	register_rest_route('RecruitWizard', 'regions', array(
		'methods' => 'GET',
		'callback' => __NAMESPACE__.'recruitwizard_get_regions',
		'permission_callback' => 'recruitwizard_create_job_listing_permissions_check'
	));
});

function recruitwizard_create_job_listing_permissions_check(){
	if (! current_user_can('edit_posts')) {
		return new WP_Error('forbidden', esc_html__('Not Authorized', 'my-text-domain'), array('status' => 401));
	}
	return true;
}


function recruitwizard_create_job_listing(WP_REST_Request $request){
    $paramsString = $request->get_body();
	$paramsJSON = json_decode($paramsString);

	$user = get_user_by('login', $paramsJSON->{'username'});

	$the_user_id = $user->ID;

	$post_information = array(
		'ID' => $paramsJSON->{'id'},
		'post_author' => $the_user_id,
        'post_title' => $paramsJSON->{'title'},
        'post_content' => $paramsJSON->{'content'},
        'post_status' => $paramsJSON->{'status'},
		'post_type' => 'job_listing',
		'meta_input' => array(
			'_job_location' => $paramsJSON->{'location'},
			'_application' => $paramsJSON->{'applyurl'},
		    '_job_expires' => $paramsJSON->{'jobexpires'},
			'_job_salary' => $paramsJSON->{'jobsalary'},
			'_thumbnail_id' => $paramsJSON->{'thumbnailid'},
		)
    );

    $postID = wp_insert_post( $post_information );
	$cat_ids = (array)$paramsJSON->{'categories'};
    $jobtypes = (array)$paramsJSON->{'jobtypes'};
    $regions = (array)$paramsJSON->{'regions'};
	$term_res = wp_set_object_terms($postID, $cat_ids, 'job_listing_category');
	if (is_wp_error($term_res)) {
		return rest_ensure_response("Category: ".$term_res->get_error_message());
	}
	else {
		$term_res = wp_set_object_terms($postID, $jobtypes, 'job_listing_type');
		if (is_wp_error($term_res)) {
			return rest_ensure_response("JobType: ".$term_res->get_error_message());
		}
		else {
			$term_res = wp_set_object_terms($postID, $regions, 'job_listing_region');
			if (is_wp_error($term_res)) {
				return rest_ensure_response("Region: ".$term_res->get_error_message());
			}
		}		
	}
    return rest_ensure_response($postID);
}

function recruitwizard_get_categories(WP_REST_Request $request){
	return get_terms( array(
		'taxonomy' => 'job_listing_category',
		'hide_empty' => false,
	) );
}

function recruitwizard_get_job_types(WP_REST_Request $request){
	return get_terms( array(
		'taxonomy' => 'job_listing_type',
		'hide_empty' => false,
	) );
}

function recruitwizard_get_regions(WP_REST_Request $request){
	return get_terms( array(
		'taxonomy' => 'job_listing_region',
		'hide_empty' => false,
	) );
}


/*
status: publish or expired
POST http://wp.wizardsoft.io/wp-json/RecruitWizard/create-job HTTP/1.1
Content-Type: application/json
Authorization: Basic ZW5yaXF1ZS5qaW1lbmV6Ojd0OFkgTU1nRSA5OXBMIENSaFYgOEI3ZiBCVjc3

{
	"id":"",
	"username":"enrique.jimenez",
	"title":"Job Test from JSON 4 Updated!!!!",
	"content":"Content test from JSON 4 Updated!!!",
	"status":"publish",
	"location":"Sydney",
	"applyurl":"https://apply.wizardsoft.com/Apply/Index/8/232360_1/52",
	"jobexpires":"April 9, 2018",
	"jobsalary" : "20k - 30k Plus!!!",
	"categories":["recruitment","services"],
	"jobtypes":["full-time"],
	"regions":["new-south-wales"],
	"thumbnailid":""
}
*/
