#!/bin/bash

docker-compose rm -s -f
docker-compose up -d --force-recreate
echo "Enjoy cql-adminer in http://127.0.0.1:11149"
