<?php
/**
 * Run WordPress cron from the command line
 **/

include('cli-load.php');

ini_set('memory_limit', -1);

global $wpdb;

$start_idx = $argv[1];
$end_idx   = $argv[2];

if ($start_idx == null)
    die('Must specify a start index as first argument.');

$offset    = $start_idx;
$length    = (!empty($end_idx))? $end_idx - $start_idx:null;

$query = "select blog_id from wp_blogs where blog_id > 1";
$results = array_slice(
    $wpdb->get_results($query, 'ARRAY_A'), $offset, $length);

define('DOING_CRON', true);

foreach ( $results as $blog ) {
    switch_to_blog($blog['blog_id']);

    print("Running cron for blog {$blog['blog_id']}... ");

    # Lifted from wp-cron.php because it can't handle multiple blogs
    if ( false === $crons = _get_cron_array() ) {
        print("no crons.\n");
        continue;
    }

    $keys = array_keys( $crons );
    $local_time = time();

    if ( isset($keys[0]) && $keys[0] > $local_time ) {
        print("not yet time.\n");
        continue;
    }

    foreach ($crons as $timestamp => $cronhooks) {
        if ( $timestamp > $local_time )
            break;

        foreach ($cronhooks as $hook => $keys) {

            foreach ($keys as $k => $v) {

                $schedule = $v['schedule'];

                if ($schedule != false) {
                    $new_args = array($timestamp, $schedule, $hook, $v['args']);
                    call_user_func_array('wp_reschedule_event', $new_args);
                }

                wp_unschedule_event($timestamp, $hook, $v['args']);

                print($hook . "... ");
                do_action_ref_array($hook, $v['args']);
            }
        }
    }
    # /end lifted from wp-cron.php

    print("done.\n");

    restore_current_blog();
}


print "All done!\n";
