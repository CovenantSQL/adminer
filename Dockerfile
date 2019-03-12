FROM php:7-cli as builder
RUN mkdir -p /adminer
ADD . /adminer
WORKDIR /adminer
RUN php compile_covenantsql.php

FROM php:7-apache
WORKDIR /var/www/html
RUN echo 'PassEnv CQL_ADAPTER_SERVER' > /etc/apache2/conf-enabled/expose-env.conf
COPY --from=builder /adminer/covenantsql-standalone/index.php .
