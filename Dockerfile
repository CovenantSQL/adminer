FROM php:7-cli as builder
RUN mkdir -p /adminer
ADD . /adminer
WORKDIR /adminer
RUN php compile_covenantsql.php

FROM php:7-apache
WORKDIR /var/www/html
COPY --from=builder /adminer/covenantsql-standalone/index.php .