CRM-Updater
===========

This script will read new trial accounts out of the database (based off of the TIME_SINCE constant) and write 
them to the Trackvia table via API. This is intended to run as a cron job only reading in new trials within the
last TIME_SINCE seconds. The TV table should have a unique constraint on the Account Number column to ensure 
new accounts are not duplicated. 

###Quick Start

* Checkout Repo
* Copy the config.php.template to config.php
* Set the values in the config.php file (Logins and hosts)
* run crm-updater.php "php crm-updater.php"


###Config

see the config.php.template for an example of the configuration parameters



