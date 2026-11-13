FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql && a2enmod rewrite \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && printf '<Directory /var/www/html/public>\nAllowOverride All\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/jobsearch.conf \
    && a2enconf jobsearch
WORKDIR /var/www/html
COPY . .
