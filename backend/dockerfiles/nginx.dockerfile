FROM nginx:alpine

WORKDIR /var/www/html

COPY . .

WORKDIR /etc/nginx/conf.d


COPY ./nginx/default.conf .
