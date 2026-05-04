$script = <<-'SCRIPT'
#!/bin/bash

# Update and install basic dependencies
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y software-properties-common curl zip unzip git

# Add PHP 8.3 PPA
add-apt-repository -y ppa:ondrej/php
apt-get update

# Install PHP 8.3 and extensions
apt-get install -y php8.3-fpm php8.3-mysql php8.3-curl php8.3-xml php8.3-mbstring php8.3-zip php8.3-sqlite3 php8.3-gd

# Install MySQL
apt-get install -y mysql-server

# Configure MySQL
DB_NAME="aibrain"
DB_USER="aibrain"
DB_PASS="aibrain_pass"

# Wait for MySQL to be ready
echo "Waiting for MySQL to start..."
while ! mysqladmin ping -h"localhost" --silent; do
    sleep 1
done

mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME};"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# Import schema
mysql ${DB_NAME} < /vagrant/src/sql/schema.sql

# Install Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Install project dependencies
cd /vagrant
composer install

# Install and configure Nginx
apt-get install -y nginx

# Install phpMyAdmin
echo "phpmyadmin phpmyadmin/dbconfig-install boolean true" | debconf-set-selections
echo "phpmyadmin phpmyadmin/app-password password ${DB_PASS}" | debconf-set-selections
echo "phpmyadmin phpmyadmin/mysql/admin-pass password ${DB_PASS}" | debconf-set-selections
echo "phpmyadmin phpmyadmin/mysql/app-pass password ${DB_PASS}" | debconf-set-selections
echo "phpmyadmin phpmyadmin/reconfigure-webserver multiselect none" | debconf-set-selections
apt-get install -y phpmyadmin

cat <<EOF > /etc/nginx/sites-available/aibrain
server {
    listen 80;
    server_name localhost;
    root /vagrant/src/frontend;

    index index.php index.html index.htm;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;

        # Environment variables
        fastcgi_param DB_HOST localhost;
        fastcgi_param DB_NAME ${DB_NAME};
        fastcgi_param DB_USER ${DB_USER};
        fastcgi_param DB_PASS ${DB_PASS};

        # Placeholders for other required env vars
        fastcgi_param GOOGLE_CLIENT_ID "your_google_client_id";
        fastcgi_param GOOGLE_CLIENT_SECRET "your_google_client_secret";
        fastcgi_param GOOGLE_REDIRECT_URI "http://localhost:8080/callback.php";
        fastcgi_param GITHUB_CLIENT_ID "your_github_client_id";
        fastcgi_param GITHUB_CLIENT_SECRET "your_github_client_secret";
        fastcgi_param GITHUB_REDIRECT_URI "http://localhost:8080/github-callback.php";
    }

    location /phpmyadmin {
        root /usr/share/;
        index index.php index.html index.htm;
        location ~ ^/phpmyadmin/(.+\.php)$ {
            try_files \$uri =404;
            root /usr/share/;
            fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
            include snippets/fastcgi-php.conf;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        }
        location ~* ^/phpmyadmin/(.+\.(jpg|jpeg|gif|css|png|js|ico|html|xml|txt))$ {
            root /usr/share/;
        }
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF

ln -sf /etc/nginx/sites-available/aibrain /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Restart services
systemctl restart php8.3-fpm
systemctl restart nginx
SCRIPT

# Check for Dropbox or OneDrive path which often causes E_ACCESSDENIED (0x80070005)
if Dir.pwd.downcase.include?('dropbox') || Dir.pwd.downcase.include?('onedrive')
  puts "WARNING: You are running Vagrant from a Dropbox or OneDrive folder."
  puts "Cloud sync file locking can cause 'E_ACCESSDENIED (0x80070005)' errors with VirtualBox."
  puts "If 'vagrant up' fails, try pausing sync or moving the project outside of these folders."
end

Vagrant.configure("2") do |config|
  config.vm.box = "ubuntu/jammy64"

  config.vm.network "forwarded_port", guest: 80, host: 8080, host_ip: "127.0.0.1", auto_correct: true

  config.vm.provider "virtualbox" do |vb|
    vb.name = "ai-brain-dev-#{File.basename(Dir.pwd)}"
    vb.memory = "2048"
    vb.cpus = 2
    vb.customize ["modifyvm", :id, "--graphicscontroller", "vmsvga"]
    vb.customize ["modifyvm", :id, "--audio", "none"]
  end

  config.vm.provision "shell", inline: $script
end
