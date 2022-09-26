#!/bin/bash

if [[ -f /etc/chrony.conf ]]; then
    sudo sed -i 's/#log measurements statistics tracking/log measurements statistics tracking/' /etc/chrony.conf
fi
