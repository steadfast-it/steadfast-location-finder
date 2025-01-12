#!/bin/bash


echo "Please enter the port number you want Apache to listen on:"
read PORT


if [ -d /var/www/geo-location-api ] && [ $(ls -lt /var/www/geo-location-api | wc -l) -gt 0 ]; then
  echo "Directory /var/www/geo-location-api already exists and contains files. Skipping port validation..."
else
  if ! [ -x "$(command -v netstat)" ]; then
    echo 'netstat command not found, installing net-tools...'
    apt-get -y install net-tools > /dev/null
  fi

  while netstat -tuln | grep ":$PORT" > /dev/null; do
    echo "Port $PORT is already in use. Please enter a different port:"
    read PORT
  done

  if ! grep -q "Listen $PORT" /etc/apache2/ports.conf; then
    echo "Configuring Apache to listen on port $PORT..."
    echo "Listen $PORT" >> /etc/apache2/ports.conf
  else
    echo "Port $PORT is already configured."
  fi
fi

echo "Setting up Apache virtual host on port $PORT..."
VHOST_FILE="/etc/apache2/sites-available/geo-location-api.conf"
cat <<EOF > $VHOST_FILE
<VirtualHost *:$PORT>
    DocumentRoot /var/www/geo-location-api
    DirectoryIndex index.php

    <Directory /var/www/geo-location-api>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF


echo "Enabling site configuration and restarting Apache..."
a2ensite geo-location-api.conf
systemctl restart apache2


echo "Creating directory structure..."
mkdir -p /var/www/geo-location-api
mkdir -p /var/www/geo-location-api/scripts

echo "Moving all files from ./application to /var/www/geo-location-api..."

mv ./application/* /var/www/geo-location-api/

if [ -d ./application/scripts ]; then
    mv ./application/scripts/* /var/www/geo-location-api/scripts/
fi

echo "Replacing SCRIPT_URL_SLOT with http://localhost:$PORT in index.php..."
sed -i "s/SCRIPT_URL_SLOT/http:\/\/localhost:$PORT/g" /var/www/geo-location-api/index.php

echo "Setting file permissions..."
chown -R www-data:www-data /var/www/geo-location-api

if ! grep -q "www-data ALL=(ALL) NOPASSWD: /var/www/geo-location-api/scripts/create.sh" /etc/sudoers; then
    echo "www-data ALL=(ALL) NOPASSWD: /var/www/geo-location-api/scripts/create.sh" | sudo tee -a /etc/sudoers > /dev/null
    echo "Line added successfully to /etc/sudoers"
fi

if ! grep -q "www-data ALL=(ALL) NOPASSWD: /var/www/geo-location-api/scripts/update.sh" /etc/sudoers; then
    echo "www-data ALL=(ALL) NOPASSWD: /var/www/geo-location-api/scripts/update.sh" | sudo tee -a /etc/sudoers > /dev/null
    echo "Line added successfully to /etc/sudoers"
fi

if ! grep -q "www-data ALL=(ALL) NOPASSWD: /var/www/geo-location-api/delete.sh" /etc/sudoers; then
    echo "www-data ALL=(ALL) NOPASSWD: /var/www/geo-location-api/scripts/delete.sh" | sudo tee -a /etc/sudoers > /dev/null
    echo "Line added successfully to /etc/sudoers"
fi

# Finished
echo "Web server setup complete. You can access it at http://localhost:$PORT"
