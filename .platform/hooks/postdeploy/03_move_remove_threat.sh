#!/bin/bash

if [[ -d /var/ossec/active-response/bin ]]; then
    sudo mv /tmp/remove-threat.sh /var/ossec/active-response/bin
    sudo chown root:ossec /var/ossec/active-response/bin/remove-threat.sh
fi
