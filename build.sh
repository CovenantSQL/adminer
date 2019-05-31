#!/bin/bash

git submodule update --init
docker build --tag covenantsql/adminer:latest .
docker-compose up -d --force-recreate
src_ip=$(ip route get 1.1.1.1 | grep -Po 'src\s*(?:\d+\.){3}\d+' | grep -Po '(?:\d+\.){3}\d+')
echo "Enjoy cql-adminer in http://${src_ip}:11149"
