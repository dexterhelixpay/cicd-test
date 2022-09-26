#!/bin/bash

ciphers='Ciphers aes128-ctr,aes192-ctr,aes256-ctr'
hostkey='HostKeyAlgorithms ecdsa-sha2-nistp256,ecdsa-sha2-nistp384,ecdsa-sha2-nistp521,ssh-rsa,ssh-dss'
kex='KexAlgorithms ecdh-sha2-nistp256,ecdh-sha2-nistp384,ecdh-sha2-nistp521,diffie-hellman-group14-sha1,diffie-hellman-group-exchange-sha256'
macs='MACs hmac-sha1,umac-64@openssh.com,hmac-ripemd160'

if [[ -f /etc/ssh/sshd_config ]]; then
    sudo sed -i '/^Ciphers /d' /etc/ssh/sshd_config
    sudo echo $ciphers >> /etc/ssh/sshd_config

    sudo sed -i '/^HostKeyAlgorithms /d' /etc/ssh/sshd_config
    sudo echo $hostkey >> /etc/ssh/sshd_config

    sudo sed -i '/^KexAlgorithms /d' /etc/ssh/sshd_config
    sudo echo $kex >> /etc/ssh/sshd_config

    sudo sed -i '/^MACs /d' /etc/ssh/sshd_config
    sudo echo $macs >> /etc/ssh/sshd_config

    sudo service sshd reload
    sudo service sshd restart
fi
