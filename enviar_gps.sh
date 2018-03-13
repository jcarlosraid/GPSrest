while :
do
	echo "presione ctrl+c para parar..."
	php /var/www/html/gpsrest1/rest.php
	sleep 30
	echo "enviando datos del segundo vehiculo..."
	php /var/www/html/gpsrest2/rest.php
	sleep 30
done