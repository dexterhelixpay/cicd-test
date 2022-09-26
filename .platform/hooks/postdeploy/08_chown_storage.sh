#!/bin/bash

if [[ -d /var/app/current/bootstrap/cache ]]; then
    sudo chown webapp:webapp -R /var/app/current/bootstrap/cache
fi

if [[ -d /var/app/current/storage ]]; then
    sudo chown webapp:webapp -R /var/app/current/storage
fi
