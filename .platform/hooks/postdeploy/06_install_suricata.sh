#!/bin/bash

# sudo amazon-linux-extras enable epel

# sudo yum clean metadata
# sudo yum install suricata -y

# wget https://rules.emergingthreats.net/open/suricata-4.0/emerging.rules.tar.gz
# tar zxvf emerging.rules.tar.gz
# sudo rm /etc/suricata/rules/* -f
# sudo mv rules/*.rules /etc/suricata/rules/
# sudo rm -f emerging.rules.tar.gz

# sudo rm -f /etc/suricata/suricata.yaml
# sudo wget -O /etc/suricata/suricata.yaml http://www.branchnetconsulting.com/wazuh/suricata.yaml

# sudo systemctl daemon-reload
# sudo systemctl enable suricata
# sudo systemctl reload-or-restart suricata
