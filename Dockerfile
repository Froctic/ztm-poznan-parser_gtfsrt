FROM php:8.2-apache

# Устанавливаем расширение cURL для работы update.php
RUN apt-get update && apt-get install -y libcurl4-openssl-dev pkg-config libssl-dev \
    && docker-php-ext-install curl

# Копируем все твои файлы в рабочую папку веб-сервера
COPY . /var/www/html/

# Даем права на запись, чтобы file_put_contents мог сохранять vehicles.json
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
