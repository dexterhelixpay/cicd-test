#!/bin/bash

sudo systemctl stop rpcbind.service
sudo systemctl stop rpcbind.socket
sudo systemctl disable rpcbind.service
sudo systemctl disable rpcbind.socket
