=== amr cron manager ===
Contributors: anmari
Tags: cron, wp-cron, cron manager, cron control, cron cleaner, debug
Tested up to: 5.5
Stable tag: 2.3
License: GPLv2 or later

Overview of wp cron jobs in the site's timezone. The lists show if the action exists and any arguments to the cron job. Highlights old zombie jobs. Reschedule or delete cron jobs.

== Description ==

Overview of all cron jobs, showing whether the corresponding action is present. This is handy if you are struggling to write a plugin with a cron job. If there is no action created by the cron job plugin, nothing will happen - just delete the job.
Includes ability to delete comprehensively and reschedule the jobs.
Shows time in wordpress timezone, not utc time, so it is much easier to understand!

== Installation ==

1. Load like any other plugin, activate, then find it under 'Tools'.


== Changelog == 
= Version 2.3 =
*   Cleaned up spacing a bit
*   Added timezone to the heading
*   released on wordpress.org

= Version 2.2 =
*   Fix time anomaly.   Cron Timeslots are in gmt time
*   Jobs with arguments that are arrays of numeric parameters were difficult to delete since the form passes them as string and wp uses a serialised md has to  make a key for the deletion.
    Plugin now checks for numeric arguments and converts to int and tries again.
*   Help and explanations added.

= Version 1.0 =
*   Began with all the various cron plugins out there.  None addressed all requirements, so I rewrite the whole lot and made a compilation plugin of possible cron features.

== Screenshots ==

1. Overview in wordpress timezone
2. Debug tool - any missing actions are highlighted - cron job will do nothing if there is no action defined
3. Shows the arguments for the cron jobs - a cron job may do different things if different arguments are passed
4. Reschedule any cron task, for 'now' - remember to follow with a 'trigger the cron' - you will be able to see any debug text to the screen if no other page view has triggered the job after your reschedule and before you triggered it.
