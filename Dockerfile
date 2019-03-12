FROM php:7-cli as builder
RUN mkdir -p /adminer
ADD . /adminer
WORKDIR /adminer
RUN php compile_covenantsql.php
RUN echo 'PassEnv CQL_ADAPTER_SERVER' > /etc/apache2/conf-enabled/expose-env.conf

FROM php:7-apache
WORKDIR /var/www/html
COPY --from=builder /adminer/covenantsql-standalone/index.php .