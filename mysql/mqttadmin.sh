#!/bin/sh
mysql -u statuslight -p$MYSQL_PASSWORD statuslight -e \
"INSERT INTO mqttadmins (name, username, password) VALUES('SL Application', 'adm_app', MD5('$MQTTADMINPASS')) 
	ON DUPLICATE KEY UPDATE    
	password=MD5('$MQTTADMINPASS');"
