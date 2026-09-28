FROM php:8.2-apache

# Copy the current directory contents into the container at /var/www/html/
COPY . /var/www/html/

# Expose port 80 to the outside world
EXPOSE 80
